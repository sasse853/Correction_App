{{--
    ============================================================
    VUE : audit/index.blade.php
    ------------------------------------------------------------
    Journal d'audit avec filtres avancés et boutons d'export.
    Accessible aux rôles ayant la permission "view-audit-logs"
    (admin et supérieur).

    Variables transmises par AuditLogController@index :
      - $logs    → LengthAwarePaginator (paginé par 25)
                   avec relations user et submission chargées
      - $users   → Collection de tous les User (filtre agent)
      - $actions → array des types d'actions possibles

    Filtres disponibles via GET :
      ?user_id=X
      ?action=PUSH_DB2
      ?statut=ERREUR
      ?submission_id=X
      ?date_debut=2026-01-01
      ?date_fin=2026-12-31
    ============================================================
--}}

@extends('layouts.app')

@section('title', 'Journal d\'audit')
@section('page-title', 'Journal d\'audit')

@section('content')

{{-- ──────────────────────────────────────────────────────────
     EN-TÊTE : Titre + boutons d'export
     Les exports reprennent les mêmes filtres actifs que la vue.
     Les paramètres GET sont automatiquement transmis via l'URL.
     ────────────────────────────────────────────────────────── --}}
<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px;">
    <div>
        <h2 style="font-family:'Syne',sans-serif; font-size:1.3rem; font-weight:700; margin:0 0 4px;">
            Journal d'audit
        </h2>
        <p style="font-size:0.85rem; color:var(--text-muted); margin:0;">
            Traçabilité complète de toutes les actions effectuées
        </p>
    </div>

    {{-- Boutons d'export — on passe les filtres actuels en query string
         pour que l'export soit cohérent avec ce qui est affiché --}}
    <div style="display:flex; gap:10px;">

        {{-- Export Excel --}}
        <a href="{{ route('audit.export.excel') . '?' . http_build_query(request()->only(['user_id','action','statut','date_debut','date_fin','submission_id'])) }}"
           class="btn-ghost"
           style="display:inline-flex; align-items:center; gap:6px;">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
                <polyline points="10 9 9 9 8 9"/>
            </svg>
            Exporter Excel
        </a>

        {{-- Export PDF --}}
        <a href="{{ route('audit.export.pdf') . '?' . http_build_query(request()->only(['user_id','action','statut','date_debut','date_fin','submission_id'])) }}"
           class="btn-ghost"
           style="display:inline-flex; align-items:center; gap:6px;">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
            </svg>
            Exporter PDF
        </a>

    </div>
</div>

{{-- ──────────────────────────────────────────────────────────
     BLOC FILTRES
     ────────────────────────────────────────────────────────── --}}
<div class="card" style="margin-bottom:20px;">
    <form method="GET" action="{{ request()->url() }}">

        {{-- Ligne 1 : agent, action, statut, dossier --}}
        <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:12px;">

            {{-- Filtre par agent (utilisateur) --}}
            <div>
                <label for="user_id">Agent</label>
                <select name="user_id" id="user_id" class="input">
                    <option value="">Tous les agents</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}"
                            {{ request('user_id') == $user->id ? 'selected' : '' }}>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Filtre par type d'action --}}
            <div>
                <label for="action">Action</label>
                <select name="action" id="action" class="input">
                    <option value="">Toutes les actions</option>
                    @foreach($actions as $action)
                        <option value="{{ $action }}"
                            {{ request('action') == $action ? 'selected' : '' }}>
                            {{--
                                On réutilise getActionLabel() du model AuditLog
                                pour afficher les labels lisibles.
                                On crée une instance vide juste pour appeler la méthode.
                            --}}
                            {{ (new \App\Models\AuditLog(['action' => $action]))->getActionLabel() }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Filtre par statut OK / ERREUR --}}
            <div>
                <label for="statut">Statut</label>
                <select name="statut" id="statut" class="input">
                    <option value="">Tous</option>
                    <option value="OK"     {{ request('statut') == 'OK'     ? 'selected' : '' }}>OK</option>
                    <option value="ERREUR" {{ request('statut') == 'ERREUR' ? 'selected' : '' }}>Erreur</option>
                </select>
            </div>

            {{-- Filtre par numéro de dossier --}}
            <div>
                <label for="submission_id">N° dossier</label>
                <input type="number"
                       name="submission_id"
                       id="submission_id"
                       class="input"
                       placeholder="Ex: 42"
                       value="{{ request('submission_id') }}">
            </div>

        </div>

        {{-- Ligne 2 : période + boutons --}}
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr auto; gap:12px; align-items:flex-end;">

            <div>
                <label for="date_debut">Du</label>
                <input type="date"
                       name="date_debut"
                       id="date_debut"
                       class="input"
                       value="{{ request('date_debut') }}">
            </div>

            <div>
                <label for="date_fin">Au</label>
                <input type="date"
                       name="date_fin"
                       id="date_fin"
                       class="input"
                       value="{{ request('date_fin') }}">
            </div>

            {{-- Espace vide pour aligner les boutons à droite --}}
            <div></div>

            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn-primary">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                    </svg>
                    Filtrer
                </button>
                <a href="{{ request()->url() }}" class="btn-ghost">✕</a>
            </div>

        </div>

    </form>
</div>

{{-- ──────────────────────────────────────────────────────────
     INDICATEURS RAPIDES
     Compteurs filtrés pour donner une vision synthétique
     ────────────────────────────────────────────────────────── --}}
<div style="display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:20px;">

    <div class="card" style="padding:14px;">
        <p style="font-size:0.7rem; color:var(--text-muted); margin:0 0 6px; text-transform:uppercase; letter-spacing:0.5px;">
            Total (filtrés)
        </p>
        <p style="font-size:1.6rem; font-weight:800; font-family:'Syne',sans-serif; margin:0;">
            {{ $logs->total() }}
        </p>
    </div>

    <div class="card" style="padding:14px; border-color:rgba(34,197,94,0.25);">
        <p style="font-size:0.7rem; color:var(--text-muted); margin:0 0 6px; text-transform:uppercase; letter-spacing:0.5px;">
            Actions OK
        </p>
        <p style="font-size:1.6rem; font-weight:800; font-family:'Syne',sans-serif; margin:0; color:#4ade80;">
            {{ $logs->getCollection()->where('statut', 'OK')->count() }}
        </p>
    </div>

    <div class="card" style="padding:14px; border-color:rgba(239,68,68,0.25);">
        <p style="font-size:0.7rem; color:var(--text-muted); margin:0 0 6px; text-transform:uppercase; letter-spacing:0.5px;">
            Erreurs
        </p>
        <p style="font-size:1.6rem; font-weight:800; font-family:'Syne',sans-serif; margin:0; color:#f87171;">
            {{ $logs->getCollection()->where('statut', 'ERREUR')->count() }}
        </p>
    </div>

</div>

{{-- ──────────────────────────────────────────────────────────
     TABLEAU DES LOGS
     ────────────────────────────────────────────────────────── --}}
<div class="card">

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
        <h3 style="font-family:'Syne',sans-serif; font-size:1rem; font-weight:700; margin:0;">
            Entrées du journal
        </h3>
        <span style="font-size:0.8rem; color:var(--text-muted);">
            {{ $logs->total() }} entrée(s) — page {{ $logs->currentPage() }}/{{ $logs->lastPage() }}
        </span>
    </div>

    @if($logs->isEmpty())
        <div style="text-align:center; padding:48px 20px; color:var(--text-muted);">
            <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24" style="margin:0 auto 12px; opacity:0.3;">
                <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p style="font-size:0.9rem; margin:0;">Aucune entrée trouvée pour ces critères.</p>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date & Heure</th>
                        <th>Agent</th>
                        <th>Action</th>
                        <th>Dossier</th>
                        <th>Table DB2</th>
                        <th>Champ</th>
                        <th>Valeur appliquée</th>
                        <th>Statut</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $log)
                        <tr>

                            {{-- Date et heure de l'action --}}
                            <td style="font-size:0.78rem; color:var(--text-muted); white-space:nowrap;">
                                {{ $log->created_at->format('d/m/Y') }}
                                <span style="display:block; font-size:0.72rem;">
                                    {{ $log->created_at->format('H:i:s') }}
                                </span>
                            </td>

                            {{-- Agent : nom + rôle --}}
                            <td>
                                <span style="font-size:0.8rem; font-weight:500;">
                                    {{ $log->user?->name ?? 'Système' }}
                                </span>
                                @if($log->role)
                                    <span style="display:block; font-size:0.7rem; color:var(--text-muted);">
                                        {{ $log->role }}
                                    </span>
                                @endif
                            </td>

                            {{-- Type d'action avec label lisible --}}
                            <td>
                                <span style="font-size:0.8rem; font-weight:600;">
                                    {{ $log->getActionLabel() }}
                                </span>
                            </td>

                            {{-- Dossier lié (si applicable) --}}
                            <td style="font-size:0.8rem;">
                                @if($log->submission_id)
                                    <span style="color:var(--accent-light);">#{{ $log->submission_id }}</span>
                                @else
                                    <span style="color:var(--text-muted);">—</span>
                                @endif
                            </td>

                            {{-- Table DB2 ciblée --}}
                            <td>
                                @if($log->table_db2)
                                    <code style="background:rgba(79,124,255,0.1); color:var(--accent-light); padding:1px 6px; border-radius:4px; font-size:0.75rem;">
                                        {{ $log->table_db2 }}
                                    </code>
                                @else
                                    <span style="color:var(--text-muted); font-size:0.8rem;">—</span>
                                @endif
                            </td>

                            {{-- Champ modifié --}}
                            <td style="font-size:0.8rem; color:var(--text-muted);">
                                {{ $log->champ_modifie ?? '—' }}
                            </td>

                            {{-- Valeur appliquée, tronquée si trop longue --}}
                            <td style="font-size:0.8rem; max-width:180px;">
                                @if($log->valeur_appliquee)
                                    <span title="{{ $log->valeur_appliquee }}"
                                          style="display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:180px;">
                                        {{ $log->valeur_appliquee }}
                                    </span>
                                @else
                                    <span style="color:var(--text-muted);">—</span>
                                @endif
                            </td>

                            {{-- Statut OK / ERREUR --}}
                            <td>
                                @if($log->statut === 'OK')
                                    <span class="badge badge-emerald">OK</span>
                                @else
                                    {{--
                                        Pour les erreurs on affiche le message au survol
                                        via l'attribut title (tooltip natif HTML).
                                    --}}
                                    <span class="badge badge-red"
                                          title="{{ $log->message_erreur }}"
                                          style="cursor:help;">
                                        Erreur
                                    </span>
                                    @if($log->message_erreur)
                                        <span style="display:block; font-size:0.7rem; color:#f87171; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:120px;">
                                            {{ Str::limit($log->message_erreur, 40) }}
                                        </span>
                                    @endif
                                @endif
                            </td>

                            {{-- Adresse IP --}}
                            <td style="font-size:0.75rem; color:var(--text-muted); white-space:nowrap;">
                                {{ $log->ip_address ?? '—' }}
                            </td>

                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination avec conservation des filtres --}}
        @if($logs->hasPages())
            <div style="margin-top:20px; display:flex; justify-content:center;">
                {{ $logs->links() }}
            </div>
        @endif

    @endif

</div>

@endsection