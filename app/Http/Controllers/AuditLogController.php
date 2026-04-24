<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * AuditLogController
 *
 * Gère la consultation et l'export des journaux d'audit.
 *
 * Accessible aux utilisateurs ayant la permission "view-audit-logs"
 * c'est-à-dire les supérieurs et les administrateurs.
 *
 * Fonctionnalités :
 *   - Liste paginée avec filtres avancés (agent, action, statut, période)
 *   - Export en Excel (.xlsx) avec mise en forme
 *   - Export en PDF avec en-tête et pied de page
 */
class AuditLogController extends Controller
{
    /**
     * Affiche le tableau de bord des audit logs avec filtres.
     *
     * Filtres disponibles via paramètres GET :
     *   ?user_id=X    → filtrer par agent
     *   ?action=PUSH_DB2 → filtrer par type d'action
     *   ?statut=ERREUR → filtrer par résultat
     *   ?date_debut=2024-01-01 → borne inférieure de date
     *   ?date_fin=2024-12-31   → borne supérieure de date
     *   ?submission_id=X → filtrer par dossier
     */
    public function index(Request $request)
    {
        $query = AuditLog::with(['user', 'submission'])
            ->orderByDesc('created_at');

        // Filtre par utilisateur (agent)
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filtre par type d'action
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // Filtre par statut (OK ou ERREUR)
        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        // Filtre par dossier
        if ($request->filled('submission_id')) {
            $query->where('submission_id', $request->submission_id);
        }

        // Filtre par période : date de début
        if ($request->filled('date_debut')) {
            $query->whereDate('created_at', '>=', $request->date_debut);
        }

        // Filtre par période : date de fin
        if ($request->filled('date_fin')) {
            $query->whereDate('created_at', '<=', $request->date_fin);
        }

        // Pagination avec conservation des paramètres de filtre dans les liens
        $logs = $query->paginate(25)->withQueryString();

        // Données pour les menus déroulants des filtres
        $users   = User::orderBy('name')->get();
        $actions = [
            'UPLOAD', 'APPROBATION', 'REJET', 'CORRECTION',
            'PUSH_DB2', 'LOGIN', 'LOGOUT', 'CREATE_USER',
            'UPDATE_USER', 'DEACTIVATE_USER',
        ];

        return view('audit.index', compact('logs', 'users', 'actions'));
    }

    /**
     * Exporte les audit logs filtrés en fichier Excel (.xlsx).
     *
     * On réutilise les mêmes filtres que la vue index.
     * Le fichier est généré à la volée et téléchargé directement.
     */
    public function exportExcel(Request $request)
    {
        // On récupère les logs filtrés (sans pagination pour l'export)
        $logs = $this->getFilteredLogs($request);

        // Nom du fichier avec date pour identifier l'export
        $filename = 'audit_logs_' . now()->format('Y-m-d_His') . '.xlsx';

        // On utilise une classe anonyme qui implémente les interfaces
        // FromCollection et WithHeadings de Maatwebsite Excel
        $export = new class($logs) implements FromCollection, WithHeadings, WithStyles {
            public function __construct(private $logs) {}

            /** Retourne les données à écrire dans le fichier */
            public function collection()
            {
                return $this->logs->map(fn($log) => [
                    'ID'              => $log->id,
                    'Date & Heure'    => $log->created_at->format('d/m/Y H:i:s'),
                    'Agent'           => $log->user?->name ?? 'Système',
                    'Rôle'            => $log->role ?? '-',
                    'Action'          => $log->getActionLabel(),
                    'Dossier'         => $log->submission_id ?? '-',
                    'Table DB2'       => $log->table_db2 ?? '-',
                    'Champ modifié'   => $log->champ_modifie ?? '-',
                    'Valeur'          => $log->valeur_appliquee ?? '-',
                    'Statut'          => $log->statut,
                    'Erreur'          => $log->message_erreur ?? '-',
                    'Adresse IP'      => $log->ip_address ?? '-',
                ]);
            }

            /** Définit les en-têtes de colonnes */
            public function headings(): array
            {
                return [
                    'ID', 'Date & Heure', 'Agent', 'Rôle', 'Action',
                    'Dossier', 'Table DB2', 'Champ modifié', 'Valeur',
                    'Statut', 'Erreur', 'Adresse IP',
                ];
            }

            /** Met en gras la première ligne (en-têtes) */
            public function styles(Worksheet $sheet): array
            {
                return [
                    1 => ['font' => ['bold' => true]],
                ];
            }
        };

        return Excel::download($export, $filename);
    }

    /**
     * Exporte les audit logs filtrés en PDF.
     *
     * Utilise le package barryvdh/laravel-dompdf.
     * Le rendu est basé sur une vue Blade dédiée à l'impression.
     */
    public function exportPdf(Request $request)
    {
        $logs = $this->getFilteredLogs($request);

        // Génération du PDF depuis la vue Blade dédiée
        $pdf = Pdf::loadView('audit.export-pdf', [
            'logs'       => $logs,
            'exportedAt' => now()->format('d/m/Y à H:i'),
            'exportedBy' => auth()->user()->name,
            'filters'    => $request->only([
                'user_id', 'action', 'statut', 'date_debut', 'date_fin',
            ]),
        ])->setPaper('a4', 'landscape'); // Format paysage pour les colonnes larges

        $filename = 'audit_logs_' . now()->format('Y-m-d_His') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Méthode privée : récupère les logs avec les filtres appliqués.
     * Partagée entre exportExcel() et exportPdf() pour éviter la duplication.
     *
     * @param  Request  $request
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function getFilteredLogs(Request $request)
    {
        $query = AuditLog::with(['user', 'submission'])
            ->orderByDesc('created_at');

        if ($request->filled('user_id'))      $query->where('user_id', $request->user_id);
        if ($request->filled('action'))        $query->where('action', $request->action);
        if ($request->filled('statut'))        $query->where('statut', $request->statut);
        if ($request->filled('submission_id')) $query->where('submission_id', $request->submission_id);
        if ($request->filled('date_debut'))    $query->whereDate('created_at', '>=', $request->date_debut);
        if ($request->filled('date_fin'))      $query->whereDate('created_at', '<=', $request->date_fin);

        // Limite à 5000 lignes pour éviter les exports trop lourds
        return $query->limit(5000)->get();
    }
}
