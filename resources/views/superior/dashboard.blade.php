{{--
    ============================================================
    VUE : superior/dashboard.blade.php
    ------------------------------------------------------------
    Tableau de bord du supérieur.

    Variables transmises par ReviewController@dashboard :
      - $enAttente  → LengthAwarePaginator des dossiers EN_ATTENTE
                      et EN_REVISION (les plus anciens en premier,
                      paginés par 10 avec le paramètre 'attente_page')
      - $traites    → Collection des 10 derniers dossiers traités
                      (TERMINE, TERMINE_AVEC_ERREURS, EN_CORRECTION)
      - $stats      → array [
                          'en_attente'          => int,
                          'traites_aujourd_hui' => int,
                      ]

    Cette vue est le point d'entrée du supérieur.
    Elle lui donne une vision immédiate des dossiers
    qui nécessitent son attention.
    ============================================================
--}}

@extends('layouts.app')

@section('title', 'Tableau de bord — Supérieur')
@section('page-title', 'Tableau de bord')

@section('content')

{{-- ──────────────────────────────────────────────────────────
     LIGNE 1 : Cards de statistiques rapides
     Donnent une vue synthétique de l'activité en cours.
     ────────────────────────────────────────────────────────── --}}
<div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:28px;">

    {{-- Card 1 : Dossiers en attente de décision --}}
    <div class="card" style="border-color:rgba(245,158,11,0.3);">
        <div style="display:flex; align-items:center; justify-content:space-between;">
            <div>
                <p style="font-size:0.75rem; color:var(--text-muted); margin:0 0 8px; letter-spacing:0.5px; text-transform:uppercase;">
                    En attente
                </p>
                {{-- Nombre de dossiers qui nécessitent une action immédiate --}}
                <p style="font-size:2rem; font-weight:800; font-family:'Syne',sans-serif; margin:0; color:#fbbf24;">
                    {{ $stats['en_attente'] }}
                </p>
                <p style="font-size:0.75rem; color:var(--text-muted); margin:4px 0 0;">
                    dossier(s) à réviser
                </p>
            </div>
            {{-- Icône représentative --}}
            <div style="width:44px; height:44px; border-radius:12px; background:rgba(245,158,11,0.12); display:flex; align-items:center; justify-content:center;">
                <svg width="20" height="20" fill="none" stroke="#fbbf24" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                </svg>
            </div>
        </div>
    </div>

    {{-- Card 2 : Dossiers traités aujourd'hui --}}
    <div class="card" style="border-color:rgba(34,197,94,0.3);">
        <div style="display:flex; align-items:center; justify-content:space-between;">
            <div>
                <p style="font-size:0.75rem; color:var(--text-muted); margin:0 0 8px; letter-spacing:0.5px; text-transform:uppercase;">
                    Traités aujourd'hui
                </p>
                <p style="font-size:2rem; font-weight:800; font-family:'Syne',sans-serif; margin:0; color:#4ade80;">
                    {{ $stats['traites_aujourd_hui'] }}
                </p>
                <p style="font-size:0.75rem; color:var(--text-muted); margin:4px 0 0;">
                    dossier(s) finalisés
                </p>
            </div>
            <div style="width:44px; height:44px; border-radius:12px; background:rgba(34,197,94,0.12); display:flex; align-items:center; justify-content:center;">
                <svg width="20" height="20" fill="none" stroke="#4ade80" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
    </div>

    {{-- Card 3 : Lien rapide vers tous les dossiers --}}
    <div class="card" style="border-color:rgba(79,124,255,0.3);">
        <div style="display:flex; align-items:center; justify-content:space-between;">
            <div>
                <p style="font-size:0.75rem; color:var(--text-muted); margin:0 0 8px; letter-spacing:0.5px; text-transform:uppercase;">
                    Tous les dossiers
                </p>
                <p style="font-size:0.875rem; font-weight:600; margin:0; color:var(--text-main);">
                    Historique complet
                </p>
                <p style="font-size:0.75rem; color:var(--text-muted); margin:4px 0 0;">
                    avec filtres avancés
                </p>
            </div>
            <a href="{{ route('superieur.submissions.index') }}"
               style="width:44px; height:44px; border-radius:12px; background:rgba(79,124,255,0.12); display:flex; align-items:center; justify-content:center; text-decoration:none; flex-shrink:0;">
                <svg width="20" height="20" fill="none" stroke="var(--accent-light)" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                </svg>
            </a>
        </div>
    </div>

</div>
{{-- Fin cards stats --}}

{{-- ──────────────────────────────────────────────────────────
     LIGNE 2 : Grille 2 colonnes
     Gauche (60%) : dossiers urgents à réviser
     Droite (40%) : dossiers récemment traités
     ────────────────────────────────────────────────────────── --}}
<div style="display:grid; grid-template-columns:1fr 380px; gap:20px; align-items:start;">

    {{-- ══════════════════════════════════════════════
         COLONNE GAUCHE : Dossiers en attente
         Ce sont les dossiers EN_ATTENTE ou EN_REVISION
         qui nécessitent une action du supérieur.
         Triés du plus ancien au plus récent (urgence).
         ══════════════════════════════════════════════ --}}
    <div class="card">

        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
            <h3 style="font-family:'Syne',sans-serif; font-size:1rem; font-weight:700; margin:0; display:flex; align-items:center; gap:8px;">
                {{-- Point orange clignotant pour attirer l'attention --}}
                <span style="width:8px; height:8px; border-radius:50%; background:#fbbf24; display:inline-block; box-shadow:0 0 0 3px rgba(245,158,11,0.2);"></span>
                Dossiers à réviser
            </h3>
            {{-- Badge compteur --}}
            @if($stats['en_attente'] > 0)
                <span class="badge badge-yellow">{{ $stats['en_attente'] }} en attente</span>
            @endif
        </div>

        @if($enAttente->isEmpty())
            {{-- État vide : aucun dossier en attente --}}
            <div style="text-align:center; padding:48px 20px; color:var(--text-muted);">
                <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24" style="margin:0 auto 12px; opacity:0.3;">
                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p style="font-size:0.9rem; font-weight:600; margin:0 0 4px;">Tout est à jour !</p>
                <p style="font-size:0.8rem; margin:0;">Aucun dossier en attente de révision.</p>
            </div>
        @else
            {{-- Liste des dossiers urgents --}}
            <div style="display:flex; flex-direction:column; gap:10px;">
                @foreach($enAttente as $submission)
                    <div style="display:flex; align-items:center; gap:14px; padding:14px 16px; background:rgba(255,255,255,0.02); border:1px solid var(--border); border-radius:10px; transition:border-color 0.18s;"
                         onmouseover="this.style.borderColor='rgba(79,124,255,0.4)'"
                         onmouseout="this.style.borderColor='var(--border)'">

                        {{-- Avatar de l'employé : initiale de son nom --}}
                        <div style="width:38px; height:38px; border-radius:50%; background:linear-gradient(135deg,var(--accent),var(--accent-light)); display:flex; align-items:center; justify-content:center; font-family:'Syne',sans-serif; font-weight:700; font-size:0.9rem; color:white; flex-shrink:0;">
                            {{ strtoupper(substr($submission->user->name, 0, 1)) }}
                        </div>

                        {{-- Informations principales du dossier --}}
                        <div style="flex:1; min-width:0;">
                            {{-- Nom du fichier, tronqué si trop long --}}
                            <p style="font-size:0.875rem; font-weight:600; margin:0 0 3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                {{ $submission->file_original_name }}
                            </p>
                            <p style="font-size:0.78rem; color:var(--text-muted); margin:0;">
                                par <strong style="color:var(--text-main);">{{ $submission->user->name }}</strong>
                                · {{ $submission->created_at->diffForHumans() }}
                                · v{{ $submission->version }}
                                · {{ $submission->getTotalCorrections() }} ligne(s)
                            </p>
                        </div>

                        {{-- Badge statut --}}
                        <div style="flex-shrink:0;">
                            @if($submission->statut === 'EN_REVISION')
                                <span class="badge badge-blue">En révision</span>
                            @else
                                <span class="badge badge-yellow">En attente</span>
                            @endif
                        </div>

                        {{-- Bouton "Réviser" → ouvre le dossier --}}
                        <a href="{{ route('superieur.submissions.show', $submission) }}"
                           class="btn-primary"
                           style="flex-shrink:0; padding:7px 14px; font-size:0.8rem;">
                            Réviser
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>
                @endforeach
            </div>

            {{-- Pagination si plus de 10 dossiers en attente --}}
            @if($enAttente->hasPages())
                <div style="margin-top:16px; display:flex; justify-content:center;">
                    {{ $enAttente->links() }}
                </div>
            @endif
        @endif

    </div>
    {{-- Fin colonne gauche --}}

    {{-- ══════════════════════════════════════════════
         COLONNE DROITE : Activité récente
         Les 10 derniers dossiers traités :
         TERMINE, TERMINE_AVEC_ERREURS, EN_CORRECTION
         ══════════════════════════════════════════════ --}}
    <div class="card">

        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
            <h3 style="font-family:'Syne',sans-serif; font-size:1rem; font-weight:700; margin:0;">
                Activité récente
            </h3>
            <a href="{{ route('superieur.submissions.index') }}"
               style="font-size:0.78rem; color:var(--accent); text-decoration:none;">
                Tout voir →
            </a>
        </div>

        @if($traites->isEmpty())
            <p style="font-size:0.875rem; color:var(--text-muted); text-align:center; padding:24px 0;">
                Aucun dossier traité récemment.
            </p>
        @else
            <div style="display:flex; flex-direction:column;">
                @foreach($traites as $submission)

                    {{--
                        On définit la couleur et l'icône selon le statut.
                        TERMINE              → vert (succès total)
                        TERMINE_AVEC_ERREURS → rouge (succès partiel)
                        EN_CORRECTION        → orange (rejeté, en cours)
                    --}}
                    @php
                        $color = match($submission->statut) {
                            'TERMINE'              => '#4ade80',
                            'TERMINE_AVEC_ERREURS' => '#f87171',
                            'EN_CORRECTION'        => '#fb923c',
                            default                => 'var(--text-muted)',
                        };
                    @endphp

                    <div style="display:flex; align-items:flex-start; gap:10px; padding:12px 0; border-bottom:1px solid var(--border);">

                        {{-- Indicateur coloré selon le statut --}}
                        <div style="width:8px; height:8px; border-radius:50%; background:{{ $color }}; flex-shrink:0; margin-top:6px;"></div>

                        <div style="flex:1; min-width:0;">
                            {{-- Nom du fichier --}}
                            <p style="font-size:0.8rem; font-weight:600; margin:0 0 2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                {{ $submission->file_original_name }}
                            </p>
                            {{-- Employé + date --}}
                            <p style="font-size:0.74rem; color:var(--text-muted); margin:0;">
                                {{ $submission->user->name }}
                                · {{ $submission->updated_at->diffForHumans() }}
                            </p>
                        </div>

                        {{-- Lien vers le détail --}}
                        <a href="{{ route('superieur.submissions.show', $submission) }}"
                           style="font-size:0.75rem; color:var(--accent); text-decoration:none; flex-shrink:0; margin-top:2px;">
                            Voir
                        </a>
                    </div>

                @endforeach
            </div>
        @endif

    </div>
    {{-- Fin colonne droite --}}

</div>
{{-- Fin grille --}}

@endsection