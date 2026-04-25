{{--
    ============================================================
    VUE : superior/submissions/index.blade.php
    ------------------------------------------------------------
    Liste tous les dossiers avec filtres avancés.
    Accessible au supérieur ET à l'admin (lecture seule pour l'admin).

    Variables transmises par ReviewController@allSubmissions :
      - $submissions → LengthAwarePaginator (paginé par 15)
                       chaque item a : user, latestReview.reviewer
      - $employes    → Collection des utilisateurs avec rôle 'employe'
                       (pour le menu déroulant de filtre)

    Filtres disponibles via GET :
      ?statut=EN_ATTENTE
      ?user_id=3
      ?date_debut=2026-01-01
      ?date_fin=2026-12-31
    ============================================================
--}}

@extends('layouts.app')

@section('title', 'Tous les dossiers')
@section('page-title', 'Tous les dossiers')

@section('content')

{{-- ──────────────────────────────────────────────────────────
     BLOC FILTRES
     Formulaire GET : les filtres sont passés en paramètres URL,
     ce qui permet de paginer tout en conservant les filtres actifs
     (grâce à ->withQueryString() dans le controller).
     ────────────────────────────────────────────────────────── --}}
<div class="card" style="margin-bottom:20px;">

    <form method="GET" action="{{ request()->url() }}">

        <div style="display:grid; grid-template-columns:repeat(4,1fr) auto; gap:12px; align-items:flex-end;">

            {{-- Filtre par statut --}}
            <div>
                <label for="statut">Statut</label>
                <select name="statut" id="statut" class="input">
                    <option value="">Tous les statuts</option>
                    @foreach([
                        'EN_ATTENTE'           => 'En attente',
                        'EN_REVISION'          => 'En révision',
                        'EN_CORRECTION'        => 'En correction',
                        'APPROUVE'             => 'Approuvé',
                        'TERMINE'              => 'Terminé',
                        'TERMINE_AVEC_ERREURS' => 'Terminé avec erreurs',
                    ] as $value => $label)
                        {{--
                            selected() est un helper Blade qui marque l'option
                            comme sélectionnée si la valeur correspond au filtre actif.
                            Cela permet de "mémoriser" le filtre après soumission.
                        --}}
                        <option value="{{ $value }}" {{ request('statut') == $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Filtre par employé --}}
            <div>
                <label for="user_id">Employé</label>
                <select name="user_id" id="user_id" class="input">
                    <option value="">Tous les employés</option>
                    @foreach($employes as $employe)
                        <option value="{{ $employe->id }}" {{ request('user_id') == $employe->id ? 'selected' : '' }}>
                            {{ $employe->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Filtre date début --}}
            <div>
                <label for="date_debut">Du</label>
                <input type="date"
                       name="date_debut"
                       id="date_debut"
                       class="input"
                       value="{{ request('date_debut') }}">
            </div>

            {{-- Filtre date fin --}}
            <div>
                <label for="date_fin">Au</label>
                <input type="date"
                       name="date_fin"
                       id="date_fin"
                       class="input"
                       value="{{ request('date_fin') }}">
            </div>

            {{-- Boutons : Filtrer et Réinitialiser --}}
            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn-primary">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                    </svg>
                    Filtrer
                </button>
                {{-- Réinitialiser = simple lien vers l'URL sans paramètres --}}
                <a href="{{ request()->url() }}" class="btn-ghost">
                    ✕
                </a>
            </div>

        </div>
    </form>

</div>
{{-- Fin bloc filtres --}}

{{-- ──────────────────────────────────────────────────────────
     TABLEAU DES DOSSIERS
     ────────────────────────────────────────────────────────── --}}
<div class="card">

    {{-- En-tête : titre + compteur de résultats --}}
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
        <h3 style="font-family:'Syne',sans-serif; font-size:1rem; font-weight:700; margin:0;">
            Dossiers
        </h3>
        <span style="font-size:0.8rem; color:var(--text-muted);">
            {{-- total() retourne le nombre total d'enregistrements (sans pagination) --}}
            {{ $submissions->total() }} résultat(s)
        </span>
    </div>

    @if($submissions->isEmpty())
        {{-- État vide --}}
        <div style="text-align:center; padding:48px 20px; color:var(--text-muted);">
            <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24" style="margin:0 auto 12px; opacity:0.3;">
                <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p style="font-size:0.9rem; margin:0;">Aucun dossier trouvé pour ces critères.</p>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Dossier</th>
                        <th>Employé</th>
                        <th>Version</th>
                        <th>Lignes</th>
                        <th>Statut</th>
                        <th>Dernière révision</th>
                        <th>Date soumission</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($submissions as $submission)
                        <tr>
                            {{-- ID + nom du fichier --}}
                            <td>
                                <div style="font-weight:600; font-size:0.875rem;">
                                    #{{ $submission->id }}
                                </div>
                                <div style="font-size:0.76rem; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:200px;">
                                    {{ $submission->file_original_name }}
                                </div>
                            </td>

                            {{-- Nom de l'employé --}}
                            <td>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    {{-- Mini-avatar --}}
                                    <div style="width:28px; height:28px; border-radius:50%; background:linear-gradient(135deg,var(--accent),var(--accent-light)); display:flex; align-items:center; justify-content:center; font-size:0.75rem; font-weight:700; color:white; flex-shrink:0;">
                                        {{ strtoupper(substr($submission->user->name, 0, 1)) }}
                                    </div>
                                    <span style="font-size:0.875rem;">{{ $submission->user->name }}</span>
                                </div>
                            </td>

                            {{-- Numéro de version --}}
                            <td style="text-align:center;">
                                <span style="font-size:0.8rem; color:var(--text-muted);">v{{ $submission->version }}</span>
                            </td>

                            {{-- Nombre de corrections --}}
                            <td style="text-align:center;">
                                <span style="font-size:0.875rem;">{{ $submission->getTotalCorrections() }}</span>
                            </td>

                            {{-- Badge statut --}}
                            <td>
                                @php
                                    $badgeMap = [
                                        'EN_ATTENTE'           => 'badge-yellow',
                                        'EN_REVISION'          => 'badge-blue',
                                        'EN_CORRECTION'        => 'badge-orange',
                                        'APPROUVE'             => 'badge-green',
                                        'TERMINE'              => 'badge-emerald',
                                        'TERMINE_AVEC_ERREURS' => 'badge-red',
                                    ];
                                @endphp
                                <span class="badge {{ $badgeMap[$submission->statut] ?? 'badge-gray' }}">
                                    {{ $submission->getStatutLabel() }}
                                </span>
                            </td>

                            {{-- Dernière révision (reviewer + décision) --}}
                            <td>
                                @if($submission->latestReview)
                                    <div style="font-size:0.78rem;">
                                        <span style="color:{{ $submission->latestReview->isApproved() ? '#4ade80' : '#f87171' }}; font-weight:600;">
                                            {{ $submission->latestReview->isApproved() ? 'Approuvé' : 'Rejeté' }}
                                        </span>
                                        <span style="color:var(--text-muted);">
                                            par {{ $submission->latestReview->reviewer?->name ?? '—' }}
                                        </span>
                                    </div>
                                @else
                                    <span style="font-size:0.78rem; color:var(--text-muted);">—</span>
                                @endif
                            </td>

                            {{-- Date de soumission --}}
                            <td style="font-size:0.8rem; color:var(--text-muted); white-space:nowrap;">
                                {{ $submission->created_at->format('d/m/Y H:i') }}
                            </td>

                            {{-- Bouton d'action --}}
                            <td>
                                @if(auth()->user()->hasRole('superieur') && in_array($submission->statut, ['EN_ATTENTE', 'EN_REVISION']))
                                    {{-- Bouton "Réviser" si le dossier nécessite une action --}}
                                    <a href="{{ route('superieur.submissions.show', $submission) }}"
                                       class="btn-primary"
                                       style="padding:6px 12px; font-size:0.78rem;">
                                        Réviser
                                    </a>
                                @else
                                    {{-- Bouton "Voir" pour consultation simple --}}
                                    <a href="{{ route('superieur.submissions.show', $submission) }}"
                                       class="btn-ghost"
                                       style="padding:6px 12px; font-size:0.78rem;">
                                        Voir
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination avec conservation des filtres --}}
        @if($submissions->hasPages())
            <div style="margin-top:20px; display:flex; justify-content:center;">
                {{ $submissions->links() }}
            </div>
        @endif

    @endif
</div>

@endsection