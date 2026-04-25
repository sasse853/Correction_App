{{--
    ============================================================
    VUE : employee/submissions/index.blade.php
    ------------------------------------------------------------
    Liste tous les dossiers soumis par l'employé connecté.
    C'est aussi le dashboard de l'employé (route employe.dashboard
    pointe vers SubmissionController@index).

    Variables transmises par SubmissionController@index :
      - $submissions → LengthAwarePaginator (paginé par 10)
                       triés du plus récent au plus ancien
                       appartenant uniquement à auth()->user()
    ============================================================
--}}

@extends('layouts.app')

@section('title', 'Mes dossiers')
@section('page-title', 'Mes dossiers')

@section('content')

{{-- ──────────────────────────────────────────────────────────
     EN-TÊTE : Titre + bouton Nouveau dossier
     ────────────────────────────────────────────────────────── --}}
<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px;">
    <div>
        <h2 style="font-family:'Syne',sans-serif; font-size:1.3rem; font-weight:700; margin:0 0 4px;">
            Mes dossiers
        </h2>
        <p style="font-size:0.85rem; color:var(--text-muted); margin:0;">
            Suivez l'état de vos corrections soumises
        </p>
    </div>

    {{-- Bouton principal : soumettre un nouveau fichier Excel --}}
    <a href="{{ route('employe.submissions.create') }}" class="btn-primary">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
        </svg>
        Soumettre un fichier
    </a>
</div>

{{-- ──────────────────────────────────────────────────────────
     CARDS DE STATUT RAPIDE
     Donne à l'employé une vue synthétique de ses dossiers
     sans qu'il ait à parcourir toute la liste.
     ────────────────────────────────────────────────────────── --}}
@if($submissions->total() > 0)
<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:24px;">

    {{--
        On calcule les compteurs par statut directement depuis
        la collection complète. On utilise une requête séparée
        pour ne pas être limité par la pagination (10 par page).
        Mais ici on travaille avec ce qu'on a : $submissions->total()
        nous donne le total global, pas par statut.
        On fait donc une petite requête inline pour les stats.
    --}}
    @php
        // Compteurs par statut pour les cards — requête légère
        $statsStatut = \App\Models\Submission::where('user_id', auth()->id())
            ->selectRaw('statut, count(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut');
    @endphp

    {{-- Card En attente --}}
    <div class="card" style="padding:14px; border-color:rgba(245,158,11,0.25);">
        <p style="font-size:0.7rem; color:var(--text-muted); margin:0 0 6px; text-transform:uppercase; letter-spacing:0.5px;">En attente</p>
        <p style="font-size:1.6rem; font-weight:800; font-family:'Syne',sans-serif; margin:0; color:#fbbf24;">
            {{ ($statsStatut['EN_ATTENTE'] ?? 0) + ($statsStatut['EN_REVISION'] ?? 0) }}
        </p>
    </div>

    {{-- Card En correction (rejetés) --}}
    <div class="card" style="padding:14px; border-color:rgba(251,146,60,0.25);">
        <p style="font-size:0.7rem; color:var(--text-muted); margin:0 0 6px; text-transform:uppercase; letter-spacing:0.5px;">À corriger</p>
        <p style="font-size:1.6rem; font-weight:800; font-family:'Syne',sans-serif; margin:0; color:#fb923c;">
            {{ $statsStatut['EN_CORRECTION'] ?? 0 }}
        </p>
    </div>

    {{-- Card Terminés avec succès --}}
    <div class="card" style="padding:14px; border-color:rgba(34,197,94,0.25);">
        <p style="font-size:0.7rem; color:var(--text-muted); margin:0 0 6px; text-transform:uppercase; letter-spacing:0.5px;">Terminés</p>
        <p style="font-size:1.6rem; font-weight:800; font-family:'Syne',sans-serif; margin:0; color:#4ade80;">
            {{ $statsStatut['TERMINE'] ?? 0 }}
        </p>
    </div>

    {{-- Card Total --}}
    <div class="card" style="padding:14px;">
        <p style="font-size:0.7rem; color:var(--text-muted); margin:0 0 6px; text-transform:uppercase; letter-spacing:0.5px;">Total</p>
        <p style="font-size:1.6rem; font-weight:800; font-family:'Syne',sans-serif; margin:0; color:var(--text-main);">
            {{ $submissions->total() }}
        </p>
    </div>

</div>
@endif

{{-- ──────────────────────────────────────────────────────────
     ALERTE : Dossiers en attente de correction
     Si l'employé a des dossiers EN_CORRECTION, on l'avertit
     avec une bannière orange bien visible.
     ────────────────────────────────────────────────────────── --}}
@php
    $nbEnCorrection = \App\Models\Submission::where('user_id', auth()->id())
        ->where('statut', 'EN_CORRECTION')
        ->count();
@endphp
@if($nbEnCorrection > 0)
    <div class="alert alert-warning" style="margin-bottom:20px;">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;">
            <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <span>
            Vous avez <strong>{{ $nbEnCorrection }} dossier(s)</strong> en attente de correction.
            Consultez les commentaires du supérieur et re-soumettez un fichier corrigé.
        </span>
    </div>
@endif

{{-- ──────────────────────────────────────────────────────────
     LISTE DES DOSSIERS
     ────────────────────────────────────────────────────────── --}}
<div class="card">

    @if($submissions->isEmpty())
        {{-- État vide : premier dossier à soumettre --}}
        <div style="text-align:center; padding:60px 20px;">
            <svg width="48" height="48" fill="none" stroke="var(--text-muted)" stroke-width="1.2" viewBox="0 0 24 24" style="margin:0 auto 16px; opacity:0.3;">
                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
            </svg>
            <h3 style="font-family:'Syne',sans-serif; font-size:1rem; font-weight:700; margin:0 0 8px; color:var(--text-muted);">
                Aucun dossier soumis
            </h3>
            <p style="font-size:0.875rem; color:var(--text-muted); margin:0 0 20px;">
                Commencez par soumettre votre premier fichier Excel de corrections.
            </p>
            <a href="{{ route('employe.submissions.create') }}" class="btn-primary">
                Soumettre un fichier
            </a>
        </div>

    @else
        {{-- Tableau des dossiers --}}
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Dossier</th>
                        <th>Version</th>
                        <th>Corrections</th>
                        <th>Statut</th>
                        <th>Dernière mise à jour</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($submissions as $submission)
                        <tr>

                            {{-- Nom du fichier + date de soumission --}}
                            <td>
                                <div style="font-weight:600; font-size:0.875rem; margin-bottom:3px;">
                                    {{ $submission->file_original_name }}
                                </div>
                                <div style="font-size:0.76rem; color:var(--text-muted);">
                                    Soumis le {{ $submission->created_at->format('d/m/Y à H:i') }}
                                </div>
                            </td>

                            {{-- Numéro de version --}}
                            <td style="text-align:center;">
                                <span style="font-size:0.8rem; color:var(--text-muted);">
                                    v{{ $submission->version }}
                                </span>
                            </td>

                            {{-- Nombre de lignes de corrections --}}
                            <td style="text-align:center;">
                                <span style="font-size:0.875rem; font-weight:600;">
                                    {{ $submission->getTotalCorrections() }}
                                </span>
                            </td>

                            {{-- Badge de statut --}}
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

                                {{--
                                    Indicateur supplémentaire pour EN_CORRECTION :
                                    petit texte d'action pour que l'employé comprenne
                                    immédiatement qu'il doit agir.
                                --}}
                                @if($submission->statut === 'EN_CORRECTION')
                                    <div style="font-size:0.72rem; color:#fb923c; margin-top:3px;">
                                        ↳ Action requise
                                    </div>
                                @endif
                            </td>

                            {{-- Date de dernière mise à jour --}}
                            <td style="font-size:0.8rem; color:var(--text-muted);">
                                {{ $submission->updated_at->diffForHumans() }}
                            </td>

                            {{-- Bouton d'action --}}
                            <td>
                                @if($submission->statut === 'EN_CORRECTION')
                                    {{--
                                        Dossier rejeté : bouton orange "Corriger"
                                        plus visible pour inciter à l'action
                                    --}}
                                    <a href="{{ route('employe.submissions.show', $submission) }}"
                                       style="display:inline-flex; align-items:center; gap:5px; padding:6px 12px; background:rgba(251,146,60,0.15); color:#fb923c; border:1px solid rgba(251,146,60,0.3); border-radius:7px; font-size:0.78rem; font-weight:600; text-decoration:none;">
                                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                            <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                        Corriger
                                    </a>
                                @else
                                    {{-- Autres statuts : bouton "Voir" standard --}}
                                    <a href="{{ route('employe.submissions.show', $submission) }}"
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

        {{-- Pagination --}}
        @if($submissions->hasPages())
            <div style="margin-top:20px; display:flex; justify-content:center;">
                {{ $submissions->links() }}
            </div>
        @endif

    @endif

</div>

@endsection