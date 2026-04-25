{{--
    ============================================================
    VUE : audit/export-pdf.blade.php
    ------------------------------------------------------------
    Template HTML utilisé par DomPDF pour générer le PDF
    des audit logs exportés.

    Cette vue est rendue côté serveur par barryvdh/laravel-dompdf
    via : Pdf::loadView('audit.export-pdf', [...])

    Variables transmises par AuditLogController@exportPdf :
      - $logs        → Collection des logs filtrés (max 5000)
      - $exportedAt  → string "d/m/Y à H:i" (date de génération)
      - $exportedBy  → string (nom de l'utilisateur qui exporte)
      - $filters     → array des filtres actifs (pour les afficher)

    IMPORTANT — Contraintes DomPDF :
      - Pas de CSS externe (tout doit être inline ou dans <style>)
      - Pas de flexbox ni grid (support limité dans DomPDF)
      - Utiliser des <table> pour les mises en page
      - Pas de variables CSS (--accent etc.) — couleurs en dur
      - Format paysage défini dans le controller (setPaper a4 landscape)
    ============================================================
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal d'audit — Export PDF</title>

    <style>
        /*
         * Toutes les règles CSS sont inline ici car DomPDF
         * ne charge pas les feuilles de style externes.
         * On utilise des couleurs en dur (pas de variables CSS).
         */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif; /* DejaVu est la police par défaut de DomPDF, supporte les accents */
            font-size: 9px;
            color: #1f2937;
            background: #ffffff;
            padding: 20px;
        }

        /* ── En-tête du document ── */
        .header {
            border-bottom: 2px solid #4f7cff;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }

        .header-table {
            width: 100%;
        }

        .app-title {
            font-size: 18px;
            font-weight: bold;
            color: #4f7cff;
            letter-spacing: 1px;
        }

        .doc-title {
            font-size: 13px;
            font-weight: bold;
            color: #1f2937;
            margin-top: 2px;
        }

        .meta {
            font-size: 8px;
            color: #6b7280;
            text-align: right;
        }

        /* ── Filtres actifs ── */
        .filters-section {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 8px 12px;
            margin-bottom: 14px;
            font-size: 8px;
            color: #6b7280;
        }

        .filters-section strong {
            color: #374151;
        }

        /* ── Tableau principal ── */
        .main-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        .main-table thead tr {
            background: #4f7cff;
            color: #ffffff;
        }

        .main-table thead th {
            padding: 6px 8px;
            text-align: left;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.3px;
            border: 1px solid #3b6fe8;
        }

        .main-table tbody tr:nth-child(even) {
            background: #f9fafb; /* Alternance de couleur pour la lisibilité */
        }

        .main-table tbody tr:nth-child(odd) {
            background: #ffffff;
        }

        .main-table tbody td {
            padding: 5px 8px;
            border: 1px solid #e5e7eb;
            vertical-align: top;
            font-size: 8px;
            color: #374151;
        }

        /* ── Badges statut ── */
        .badge-ok {
            background: #d1fae5;
            color: #065f46;
            padding: 1px 5px;
            border-radius: 3px;
            font-size: 7px;
            font-weight: bold;
        }

        .badge-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 1px 5px;
            border-radius: 3px;
            font-size: 7px;
            font-weight: bold;
        }

        /* ── Pied de page ── */
        .footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 8px;
            font-size: 7.5px;
            color: #9ca3af;
        }

        .footer-table {
            width: 100%;
        }

        /* ── Code table DB2 ── */
        .code-cell {
            background: #eff6ff;
            color: #1d4ed8;
            padding: 1px 4px;
            border-radius: 2px;
            font-family: monospace;
            font-size: 7.5px;
        }

        /* ── Texte muted ── */
        .text-muted {
            color: #9ca3af;
        }
    </style>
</head>
<body>

    {{-- ──────────────────────────────────────────────
         EN-TÊTE DU DOCUMENT
         On utilise un <table> pour le layout 2 colonnes
         car DomPDF ne supporte pas flexbox/grid
         ────────────────────────────────────────────── --}}
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width:60%;">
                    <div class="app-title">DataCorrection</div>
                    <div class="doc-title">Journal d'audit — Export PDF</div>
                </td>
                <td style="width:40%;" class="meta">
                    <div>Généré le : <strong>{{ $exportedAt }}</strong></div>
                    <div>Par : <strong>{{ $exportedBy }}</strong></div>
                    <div>Nombre d'entrées : <strong>{{ $logs->count() }}</strong></div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ──────────────────────────────────────────────
         FILTRES ACTIFS
         On affiche les filtres qui ont été appliqués
         pour que l'export soit auto-documenté.
         ────────────────────────────────────────────── --}}
    @php
        // On construit le résumé des filtres actifs
        $filterLabels = [];
        if (!empty($filters['user_id']))     $filterLabels[] = 'Agent ID: ' . $filters['user_id'];
        if (!empty($filters['action']))      $filterLabels[] = 'Action: ' . $filters['action'];
        if (!empty($filters['statut']))      $filterLabels[] = 'Statut: ' . $filters['statut'];
        if (!empty($filters['date_debut'])) $filterLabels[] = 'Du: ' . $filters['date_debut'];
        if (!empty($filters['date_fin']))   $filterLabels[] = 'Au: ' . $filters['date_fin'];
    @endphp

    <div class="filters-section">
        <strong>Filtres appliqués :</strong>
        @if(empty($filterLabels))
            Aucun filtre — export complet
        @else
            {{ implode(' · ', $filterLabels) }}
        @endif
    </div>

    {{-- ──────────────────────────────────────────────
         TABLEAU DES LOGS
         ────────────────────────────────────────────── --}}
    @if($logs->isEmpty())
        <p style="text-align:center; color:#9ca3af; padding:30px 0;">
            Aucune entrée à exporter pour ces critères.
        </p>
    @else
        <table class="main-table">
            <thead>
                <tr>
                    <th style="width:70px;">Date & Heure</th>
                    <th style="width:90px;">Agent</th>
                    <th style="width:55px;">Rôle</th>
                    <th style="width:80px;">Action</th>
                    <th style="width:45px;">Dossier</th>
                    <th style="width:65px;">Table DB2</th>
                    <th style="width:60px;">Champ</th>
                    <th>Valeur appliquée</th>
                    <th style="width:40px;">Statut</th>
                    <th style="width:75px;">IP</th>
                </tr>
            </thead>
            <tbody>
                @foreach($logs as $log)
                    <tr>
                        {{-- Date & heure --}}
                        <td>
                            {{ $log->created_at->format('d/m/Y') }}<br>
                            <span class="text-muted">{{ $log->created_at->format('H:i:s') }}</span>
                        </td>

                        {{-- Agent --}}
                        <td>{{ $log->user?->name ?? 'Système' }}</td>

                        {{-- Rôle --}}
                        <td class="text-muted">{{ $log->role ?? '—' }}</td>

                        {{-- Action --}}
                        <td style="font-weight:bold;">
                            {{ $log->getActionLabel() }}
                        </td>

                        {{-- Dossier --}}
                        <td style="text-align:center; color:#4f7cff;">
                            {{ $log->submission_id ? '#' . $log->submission_id : '—' }}
                        </td>

                        {{-- Table DB2 --}}
                        <td>
                            @if($log->table_db2)
                                <span class="code-cell">{{ $log->table_db2 }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>

                        {{-- Champ modifié --}}
                        <td class="text-muted">{{ $log->champ_modifie ?? '—' }}</td>

                        {{-- Valeur appliquée, tronquée pour ne pas casser le tableau --}}
                        <td>
                            @if($log->valeur_appliquee)
                                {{-- Str::limit tronque à 60 caractères pour le PDF --}}
                                {{ Str::limit($log->valeur_appliquee, 60) }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>

                        {{-- Statut --}}
                        <td style="text-align:center;">
                            @if($log->statut === 'OK')
                                <span class="badge-ok">OK</span>
                            @else
                                <span class="badge-error">ERREUR</span>
                                @if($log->message_erreur)
                                    <br><span style="font-size:7px; color:#ef4444;">
                                        {{ Str::limit($log->message_erreur, 30) }}
                                    </span>
                                @endif
                            @endif
                        </td>

                        {{-- Adresse IP --}}
                        <td class="text-muted">{{ $log->ip_address ?? '—' }}</td>

                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- ──────────────────────────────────────────────
         PIED DE PAGE
         ────────────────────────────────────────────── --}}
    <div class="footer">
        <table class="footer-table">
            <tr>
                <td>
                    DataCorrection — Document confidentiel à usage interne
                </td>
                <td style="text-align:right;">
                    Export généré le {{ $exportedAt }} par {{ $exportedBy }}
                </td>
            </tr>
        </table>
    </div>

</body>
</html>