<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Rap2hpoutre\FastExcel\FastExcel;

/**
 * AuditLogController
 *
 * Gère la consultation et l'export des journaux d'audit.
 *
 * ADAPTATION fast-excel :
 * La version originale utilisait Maatwebsite\Excel avec des
 * classes anonymes implémentant FromCollection/WithHeadings/WithStyles.
 * Ces interfaces n'existent pas dans fast-excel.
 *
 * Avec rap2hpoutre/fast-excel, l'export se fait en une seule ligne :
 *   (new FastExcel($collection))->download('fichier.xlsx')
 * ou pour plus de contrôle sur les colonnes :
 *   (new FastExcel($collection))->download('fichier.xlsx', function($row) { ... })
 *
 * Accessible aux utilisateurs ayant la permission "view-audit-logs"
 * (supérieurs et administrateurs).
 */
class AuditLogController extends Controller
{
    /**
     * Affiche le tableau de bord des audit logs avec filtres.
     *
     * Filtres disponibles via paramètres GET :
     *   ?user_id=X          → filtrer par agent
     *   ?action=PUSH_DB2    → filtrer par type d'action
     *   ?statut=ERREUR      → filtrer par résultat
     *   ?date_debut=...     → borne inférieure de date
     *   ?date_fin=...       → borne supérieure de date
     *   ?submission_id=X    → filtrer par dossier
     */
    public function index(Request $request)
    {
        $query = AuditLog::with(['user', 'submission'])
            ->orderByDesc('created_at');

        if ($request->filled('user_id'))      $query->where('user_id', $request->user_id);
        if ($request->filled('action'))        $query->where('action', $request->action);
        if ($request->filled('statut'))        $query->where('statut', $request->statut);
        if ($request->filled('submission_id')) $query->where('submission_id', $request->submission_id);
        if ($request->filled('date_debut'))    $query->whereDate('created_at', '>=', $request->date_debut);
        if ($request->filled('date_fin'))      $query->whereDate('created_at', '<=', $request->date_fin);

        $logs = $query->paginate(25)->withQueryString();

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
     * ADAPTATION fast-excel :
     * Maatwebsite nécessitait une classe avec FromCollection +
     * WithHeadings + WithStyles.
     *
     * FastExcel simplifie tout ça :
     * on passe une collection au constructeur et on définit
     * les colonnes via un callback dans download().
     *
     * Le callback reçoit chaque objet AuditLog et retourne
     * un tableau associatif dont les clés = en-têtes de colonnes.
     */
    public function exportExcel(Request $request)
    {
        // Récupère les logs filtrés sans pagination (max 5000 lignes)
        $logs = $this->getFilteredLogs($request);

        $filename = 'audit_logs_' . now()->format('Y-m-d_His') . '.xlsx';

        /*
         * FastExcel accepte une Collection Eloquent directement.
         * Le callback définit les colonnes à exporter.
         * Les clés du tableau retourné deviennent les en-têtes Excel.
         *
         * Note : FastExcel ne supporte pas les styles (gras, couleurs)
         * nativement comme Maatwebsite. Si le formatage avancé est
         * nécessaire, il faudra utiliser PhpSpreadsheet directement.
         */
        return (new FastExcel($logs))->download($filename, function (AuditLog $log) {
            return [
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
            ];
        });
    }

    /**
     * Exporte les audit logs filtrés en PDF.
     * Utilise barryvdh/laravel-dompdf — pas de changement ici
     * car DomPDF n'est pas concerné par le remplacement de Maatwebsite.
     */
    public function exportPdf(Request $request)
    {
        $logs = $this->getFilteredLogs($request);

        $pdf = Pdf::loadView('audit.export-pdf', [
            'logs'       => $logs,
            'exportedAt' => now()->format('d/m/Y à H:i'),
            'exportedBy' => auth()->user()->name,
            'filters'    => $request->only([
                'user_id', 'action', 'statut', 'date_debut', 'date_fin',
            ]),
        ])->setPaper('a4', 'landscape');

        $filename = 'audit_logs_' . now()->format('Y-m-d_His') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Méthode privée partagée entre exportExcel() et exportPdf().
     * Applique les mêmes filtres que index() sans pagination.
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

        return $query->limit(5000)->get();
    }
}