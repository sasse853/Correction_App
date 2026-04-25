{{--
    ============================================================
    VUE : admin/dashboard.blade.php
    ------------------------------------------------------------
    Tableau de bord de l'administrateur.

    Variables transmises par AdminController@dashboard :
      - $stats      → array [
                          'total_users'       => int,
                          'active_users'      => int,
                          'total_submissions' => int,
                          'pending_reviews'   => int,
                      ]
      - $recentLogs → Collection des 10 derniers AuditLog
                      avec relation user chargée
    ============================================================
--}}

@extends('layouts.app')

@section('title', 'Tableau de bord — Admin')
@section('page-title', 'Tableau de bord')

@section('content')

{{-- ──────────────────────────────────────────────────────────
     LIGNE 1 : 4 cards de statistiques globales
     ────────────────────────────────────────────────────────── --}}
<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:28px;">

    {{-- Card 1 : Total utilisateurs --}}
    <div class="card">
        <div style="display:flex; align-items:center; justify-content:space-between;">
            <div>
                <p style="font-size:0.7rem; color:var(--text-muted); margin:0 0 8px; text-transform:uppercase; letter-spacing:0.5px;">
                    Utilisateurs
                </p>
                <p style="font-size:2rem; font-weight:800; font-family:'Syne',sans-serif; margin:0; color:var(--text-main);">
                    {{ $stats['total_users'] }}
                </p>
                <p style="font-size:0.75rem; color:var(--text-muted); margin:4px 0 0;">
                    dont <strong style="color:#4ade80;">{{ $stats['active_users'] }}</strong> actifs
                </p>
            </div>
            <div style="width:44px; height:44px; border-radius:12px; background:rgba(79,124,255,0.12); display:flex; align-items:center; justify-content:center;">
                <svg width="20" height="20" fill="none" stroke="var(--accent-light)" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                </svg>
            </div>
        </div>
        {{-- Lien rapide --}}
        <a href="{{ route('admin.users.index') }}"
           style="display:block; margin-top:14px; font-size:0.75rem; color:var(--accent); text-decoration:none;">
            Gérer les utilisateurs →
        </a>
    </div>

    {{-- Card 2 : Total dossiers --}}
    <div class="card">
        <div style="display:flex; align-items:center; justify-content:space-between;">
            <div>
                <p style="font-size:0.7rem; color:var(--text-muted); margin:0 0 8px; text-transform:uppercase; letter-spacing:0.5px;">
                    Dossiers
                </p>
                <p style="font-size:2rem; font-weight:800; font-family:'Syne',sans-serif; margin:0; color:var(--text-main);">
                    {{ $stats['total_submissions'] }}
                </p>
                <p style="font-size:0.75rem; color:var(--text-muted); margin:4px 0 0;">
                    soumissions totales
                </p>
            </div>
            <div style="width:44px; height:44px; border-radius:12px; background:rgba(168,85,247,0.12); display:flex; align-items:center; justify-content:center;">
                <svg width="20" height="20" fill="none" stroke="#c084fc" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                </svg>
            </div>
        </div>
        <a href="{{ route('admin.submissions.index') }}"
           style="display:block; margin-top:14px; font-size:0.75rem; color:var(--accent); text-decoration:none;">
            Voir tous les dossiers →
        </a>
    </div>

    {{-- Card 3 : Dossiers en attente --}}
    <div class="card" style="border-color:rgba(245,158,11,0.3);">
        <div style="display:flex; align-items:center; justify-content:space-between;">
            <div>
                <p style="font-size:0.7rem; color:var(--text-muted); margin:0 0 8px; text-transform:uppercase; letter-spacing:0.5px;">
                    En attente
                </p>
                <p style="font-size:2rem; font-weight:800; font-family:'Syne',sans-serif; margin:0; color:#fbbf24;">
                    {{ $stats['pending_reviews'] }}
                </p>
                <p style="font-size:0.75rem; color:var(--text-muted); margin:4px 0 0;">
                    dossiers à réviser
                </p>
            </div>
            <div style="width:44px; height:44px; border-radius:12px; background:rgba(245,158,11,0.12); display:flex; align-items:center; justify-content:center;">
                <svg width="20" height="20" fill="none" stroke="#fbbf24" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                </svg>
            </div>
        </div>
        <a href="{{ route('admin.submissions.index') }}?statut=EN_ATTENTE"
           style="display:block; margin-top:14px; font-size:0.75rem; color:#fbbf24; text-decoration:none;">
            Voir les dossiers urgents →
        </a>
    </div>

    {{-- Card 4 : Comptes inactifs --}}
    @php
        $inactiveUsers = $stats['total_users'] - $stats['active_users'];
    @endphp
    <div class="card" style="border-color:{{ $inactiveUsers > 0 ? 'rgba(239,68,68,0.3)' : 'var(--border)' }};">
        <div style="display:flex; align-items:center; justify-content:space-between;">
            <div>
                <p style="font-size:0.7rem; color:var(--text-muted); margin:0 0 8px; text-transform:uppercase; letter-spacing:0.5px;">
                    Comptes inactifs
                </p>
                <p style="font-size:2rem; font-weight:800; font-family:'Syne',sans-serif; margin:0; color:{{ $inactiveUsers > 0 ? '#f87171' : 'var(--text-main)' }};">
                    {{ $inactiveUsers }}
                </p>
                <p style="font-size:0.75rem; color:var(--text-muted); margin:4px 0 0;">
                    compte(s) désactivé(s)
                </p>
            </div>
            <div style="width:44px; height:44px; border-radius:12px; background:rgba(239,68,68,0.1); display:flex; align-items:center; justify-content:center;">
                <svg width="20" height="20" fill="none" stroke="#f87171" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
                    <line x1="18" y1="8" x2="23" y2="13"/><line x1="23" y1="8" x2="18" y2="13"/>
                </svg>
            </div>
        </div>
        <a href="{{ route('admin.users.index') }}?status=inactive"
           style="display:block; margin-top:14px; font-size:0.75rem; color:{{ $inactiveUsers > 0 ? '#f87171' : 'var(--accent)' }}; text-decoration:none;">
            Voir les comptes inactifs →
        </a>
    </div>

</div>
{{-- Fin cards stats --}}

{{-- ──────────────────────────────────────────────────────────
     LIGNE 2 : Grille 2 colonnes
     Gauche (60%) : journal d'activité récente
     Droite (40%) : actions rapides admin
     ────────────────────────────────────────────────────────── --}}
<div style="display:grid; grid-template-columns:1fr 320px; gap:20px; align-items:start;">

    {{-- ══════════════════════════════════════════════
         COLONNE GAUCHE : 10 derniers audit logs
         Donne à l'admin une vue en temps réel de
         toutes les actions effectuées sur l'application.
         ══════════════════════════════════════════════ --}}
    <div class="card">

        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
            <h3 style="font-family:'Syne',sans-serif; font-size:1rem; font-weight:700; margin:0;">
                Activité récente
            </h3>
            <a href="{{ route('audit.index') }}"
               style="font-size:0.78rem; color:var(--accent); text-decoration:none;">
                Journal complet →
            </a>
        </div>

        @if($recentLogs->isEmpty())
            <p style="font-size:0.875rem; color:var(--text-muted); text-align:center; padding:24px 0;">
                Aucune activité enregistrée.
            </p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Agent</th>
                            <th>Action</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentLogs as $log)
                            <tr>
                                {{-- Date formatée de façon compacte --}}
                                <td style="font-size:0.78rem; color:var(--text-muted); white-space:nowrap;">
                                    {{ $log->created_at->format('d/m H:i') }}
                                </td>

                                {{-- Nom de l'utilisateur qui a effectué l'action --}}
                                <td style="font-size:0.8rem;">
                                    {{ $log->user?->name ?? 'Système' }}
                                    @if($log->role)
                                        <span style="font-size:0.7rem; color:var(--text-muted); display:block;">
                                            {{ $log->role }}
                                        </span>
                                    @endif
                                </td>

                                {{-- Label lisible de l'action via getActionLabel() du model --}}
                                <td style="font-size:0.8rem; font-weight:500;">
                                    {{ $log->getActionLabel() }}
                                    @if($log->submission_id)
                                        <span style="font-size:0.7rem; color:var(--text-muted); display:block;">
                                            Dossier #{{ $log->submission_id }}
                                        </span>
                                    @endif
                                </td>

                                {{-- Badge OK / ERREUR --}}
                                <td>
                                    @if($log->statut === 'OK')
                                        <span class="badge badge-emerald">OK</span>
                                    @else
                                        <span class="badge badge-red" title="{{ $log->message_erreur }}">Erreur</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

    </div>

    {{-- ══════════════════════════════════════════════
         COLONNE DROITE : Actions rapides
         Raccourcis vers les opérations les plus
         fréquentes de l'administrateur.
         ══════════════════════════════════════════════ --}}
    <div style="display:flex; flex-direction:column; gap:14px;">

        <div class="card">
            <h3 style="font-family:'Syne',sans-serif; font-size:0.95rem; font-weight:700; margin:0 0 16px;">
                Actions rapides
            </h3>

            <div style="display:flex; flex-direction:column; gap:10px;">

                {{-- Créer un utilisateur --}}
                <a href="{{ route('admin.users.create') }}"
                   style="display:flex; align-items:center; gap:12px; padding:12px 14px; background:rgba(79,124,255,0.08); border:1px solid rgba(79,124,255,0.2); border-radius:10px; text-decoration:none; transition:border-color 0.18s;"
                   onmouseover="this.style.borderColor='rgba(79,124,255,0.5)'"
                   onmouseout="this.style.borderColor='rgba(79,124,255,0.2)'">
                    <div style="width:34px; height:34px; border-radius:9px; background:rgba(79,124,255,0.15); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <svg width="16" height="16" fill="none" stroke="var(--accent-light)" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/>
                            <line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>
                        </svg>
                    </div>
                    <div>
                        <p style="font-size:0.85rem; font-weight:600; margin:0; color:var(--text-main);">
                            Créer un utilisateur
                        </p>
                        <p style="font-size:0.75rem; color:var(--text-muted); margin:2px 0 0;">
                            Nouveau compte employé ou supérieur
                        </p>
                    </div>
                </a>

                {{-- Gérer les utilisateurs --}}
                <a href="{{ route('admin.users.index') }}"
                   style="display:flex; align-items:center; gap:12px; padding:12px 14px; background:rgba(255,255,255,0.02); border:1px solid var(--border); border-radius:10px; text-decoration:none; transition:border-color 0.18s;"
                   onmouseover="this.style.borderColor='rgba(79,124,255,0.4)'"
                   onmouseout="this.style.borderColor='var(--border)'">
                    <div style="width:34px; height:34px; border-radius:9px; background:rgba(255,255,255,0.04); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <svg width="16" height="16" fill="none" stroke="var(--text-muted)" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                        </svg>
                    </div>
                    <div>
                        <p style="font-size:0.85rem; font-weight:600; margin:0; color:var(--text-main);">
                            Gérer les utilisateurs
                        </p>
                        <p style="font-size:0.75rem; color:var(--text-muted); margin:2px 0 0;">
                            Modifier, activer, désactiver
                        </p>
                    </div>
                </a>

                {{-- Audit logs --}}
                <a href="{{ route('audit.index') }}"
                   style="display:flex; align-items:center; gap:12px; padding:12px 14px; background:rgba(255,255,255,0.02); border:1px solid var(--border); border-radius:10px; text-decoration:none; transition:border-color 0.18s;"
                   onmouseover="this.style.borderColor='rgba(79,124,255,0.4)'"
                   onmouseout="this.style.borderColor='var(--border)'">
                    <div style="width:34px; height:34px; border-radius:9px; background:rgba(255,255,255,0.04); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <svg width="16" height="16" fill="none" stroke="var(--text-muted)" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div>
                        <p style="font-size:0.85rem; font-weight:600; margin:0; color:var(--text-main);">
                            Journal d'audit
                        </p>
                        <p style="font-size:0.75rem; color:var(--text-muted); margin:2px 0 0;">
                            Toutes les actions tracées
                        </p>
                    </div>
                </a>

                {{-- Voir tous les dossiers --}}
                <a href="{{ route('admin.submissions.index') }}"
                   style="display:flex; align-items:center; gap:12px; padding:12px 14px; background:rgba(255,255,255,0.02); border:1px solid var(--border); border-radius:10px; text-decoration:none; transition:border-color 0.18s;"
                   onmouseover="this.style.borderColor='rgba(79,124,255,0.4)'"
                   onmouseout="this.style.borderColor='var(--border)'">
                    <div style="width:34px; height:34px; border-radius:9px; background:rgba(255,255,255,0.04); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <svg width="16" height="16" fill="none" stroke="var(--text-muted)" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                        </svg>
                    </div>
                    <div>
                        <p style="font-size:0.85rem; font-weight:600; margin:0; color:var(--text-main);">
                            Tous les dossiers
                        </p>
                        <p style="font-size:0.75rem; color:var(--text-muted); margin:2px 0 0;">
                            Consultation en lecture seule
                        </p>
                    </div>
                </a>

            </div>
        </div>

        {{-- Card : Compte connecté --}}
        <div class="card" style="padding:14px;">
            <p style="font-size:0.7rem; color:var(--text-muted); margin:0 0 10px; text-transform:uppercase; letter-spacing:0.5px;">
                Connecté en tant que
            </p>
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg,var(--accent),var(--accent-light)); display:flex; align-items:center; justify-content:center; font-family:'Syne',sans-serif; font-weight:700; font-size:0.9rem; color:white; flex-shrink:0;">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
                <div>
                    <p style="font-size:0.875rem; font-weight:600; margin:0;">{{ auth()->user()->name }}</p>
                    <p style="font-size:0.75rem; color:var(--text-muted); margin:0;">{{ auth()->user()->email }}</p>
                </div>
            </div>
        </div>

    </div>
    {{-- Fin colonne droite --}}

</div>

@endsection