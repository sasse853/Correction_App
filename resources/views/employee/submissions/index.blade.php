{{--
    ============================================================
    VUE : employee/submissions/index.blade.php
    ------------------------------------------------------------
    Liste tous les dossiers de l'employé connecté.

    MISE À JOUR : Ajout du bouton "Supprimer" pour les dossiers
    EN_ATTENTE uniquement. Un dossier déjà en révision ne peut
    plus être supprimé.

    Variables transmises par SubmissionController@index :
      - $submissions → LengthAwarePaginator (paginé par 10)
    ============================================================
--}}

@extends('layouts.app')

@section('title', 'Mes dossiers')
@section('page-title', 'Mes dossiers')

@section('content')

{{-- EN-TÊTE --}}
<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px;">
    <div>
        <h2 style="font-family:'Syne',sans-serif; font-size:1.3rem; font-weight:700; margin:0 0 4px;">
            Mes dossiers
        </h2>
        <p style="font-size:0.85rem; color:var(--text-muted); margin:0;">
            Suivez l'état de vos corrections soumises
        </p>
    </div>
    <a href="{{ route('employe.submissions.create') }}" class="btn-primary">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
        </svg>
        Soumettre un fichier
    </a>
</div>

{{-- CARDS STATS --}}
@if($submissions->total() > 0)
<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:24px;">
    @php
        $statsStatut = \App\Models\Submission::where('user_id', auth()->id())
            ->selectRaw('statut, count(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut');
    @endphp

    <div class="card" style="padding:14px; border-color:rgba(245,158,11,0.25);">
        <p style="font-size:0.7rem; color:var(--text-muted); margin:0 0 6px; text-transform:uppercase; letter-spacing:0.5px;">En attente</p>
        <p style="font-size:1.6rem; font-weight:800; font-family:'Syne',sans-serif; margin:0; color:#fbbf24;">
            {{ ($statsStatut['EN_ATTENTE'] ?? 0) + ($statsStatut['EN_REVISION'] ?? 0) }}
        </p>
    </div>

    <div class="card" style="padding:14px; border-color:rgba(251,146,60,0.25);">
        <p style="font-size:0.7rem; color:var(--text-muted); margin:0 0 6px; text-transform:uppercase; letter-spacing:0.5px;">À corriger</p>
        <p style="font-size:1.6rem; font-weight:800; font-family:'Syne',sans-serif; margin:0; color:#fb923c;">
            {{ $statsStatut['EN_CORRECTION'] ?? 0 }}
        </p>
    </div>

    <div class="card" style="padding:14px; border-color:rgba(34,197,94,0.25);">
        <p style="font-size:0.7rem; color:var(--text-muted); margin:0 0 6px; text-transform:uppercase; letter-spacing:0.5px;">Terminés</p>
        <p style="font-size:1.6rem; font-weight:800; font-family:'Syne',sans-serif; margin:0; color:#4ade80;">
            {{ $statsStatut['TERMINE'] ?? 0 }}
        </p>
    </div>

    <div class="card" style="padding:14px;">
        <p style="font-size:0.7rem; color:var(--text-muted); margin:0 0 6px; text-transform:uppercase; letter-spacing:0.5px;">Total</p>
        <p style="font-size:1.6rem; font-weight:800; font-family:'Syne',sans-serif; margin:0; color:var(--text-main);">
            {{ $submissions->total() }}
        </p>
    </div>
</div>
@endif

{{-- ALERTE dossiers EN_CORRECTION --}}
@php
    $nbEnCorrection = \App\Models\Submission::where('user_id', auth()->id())
        ->where('statut', 'EN_CORRECTION')->count();
@endphp
@if($nbEnCorrection > 0)
    <div class="alert alert-warning" style="margin-bottom:20px;">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;">
            <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <span>
            Vous avez <strong>{{ $nbEnCorrection }} dossier(s)</strong> en attente de correction.
            Consultez les commentaires du supérieur et apportez vos corrections.
        </span>
    </div>
@endif

{{-- LISTE DES DOSSIERS --}}
<div class="card">

    @if($submissions->isEmpty())
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
                            <td>
                                <div style="font-weight:600; font-size:0.875rem; margin-bottom:3px;">
                                    {{ $submission->file_original_name }}
                                </div>
                                <div style="font-size:0.76rem; color:var(--text-muted);">
                                    Soumis le {{ $submission->created_at->format('d/m/Y à H:i') }}
                                </div>
                            </td>

                            <td style="text-align:center;">
                                <span style="font-size:0.8rem; color:var(--text-muted);">
                                    v{{ $submission->version }}
                                </span>
                            </td>

                            <td style="text-align:center;">
                                <span style="font-size:0.875rem; font-weight:600;">
                                    {{ $submission->getTotalCorrections() }}
                                </span>
                            </td>

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
                                @if($submission->statut === 'EN_CORRECTION')
                                    <div style="font-size:0.72rem; color:#fb923c; margin-top:3px;">
                                        ↳ Action requise
                                    </div>
                                @endif
                            </td>

                            <td style="font-size:0.8rem; color:var(--text-muted);">
                                {{ $submission->updated_at->diffForHumans() }}
                            </td>

                            {{-- COLONNE ACTIONS --}}
                            <td>
                                <div style="display:flex; align-items:center; gap:8px;">

                                    {{-- Bouton Corriger (EN_CORRECTION) --}}
                                    @if($submission->statut === 'EN_CORRECTION')
                                        <a href="{{ route('employe.submissions.show', $submission) }}"
                                           style="display:inline-flex; align-items:center; gap:5px; padding:6px 12px; background:rgba(251,146,60,0.15); color:#fb923c; border:1px solid rgba(251,146,60,0.3); border-radius:7px; font-size:0.78rem; font-weight:600; text-decoration:none;">
                                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                                <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                            Corriger
                                        </a>

                                    {{-- Bouton Voir (autres statuts) --}}
                                    @else
                                        <a href="{{ route('employe.submissions.show', $submission) }}"
                                           class="btn-ghost"
                                           style="padding:6px 12px; font-size:0.78rem;">
                                            Voir
                                        </a>
                                    @endif

                                    {{--
                                        Bouton Supprimer — visible UNIQUEMENT si le statut
                                        est EN_ATTENTE. Une fois qu'un supérieur a ouvert
                                        le dossier (EN_REVISION ou autre), la suppression
                                        est bloquée côté controller ET masquée ici.

                                        On utilise un formulaire DELETE car les liens <a>
                                        ne supportent pas la méthode DELETE nativement.
                                        @method('DELETE') génère le champ _method pour Laravel.
                                    --}}
                                    @if($submission->statut === 'EN_ATTENTE')
                                        <form method="POST"
                                              action="{{ route('employe.submissions.destroy', $submission) }}"
                                              style="display:inline;"
                                              onsubmit="return confirm('Supprimer le dossier \"{{ addslashes($submission->file_original_name) }}\" ? Cette action est irréversible.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    title="Supprimer ce dossier"
                                                    style="padding:6px 12px; background:rgba(239,68,68,0.1); color:#f87171; border:1px solid rgba(239,68,68,0.25); border-radius:7px; font-size:0.78rem; font-weight:500; cursor:pointer; font-family:inherit; display:inline-flex; align-items:center; gap:5px; transition:all 0.18s;"
                                                    onmouseover="this.style.background='rgba(239,68,68,0.2)'"
                                                    onmouseout="this.style.background='rgba(239,68,68,0.1)'">
                                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                    <polyline points="3 6 5 6 21 6"/>
                                                    <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/>
                                                    <path d="M10 11v6M14 11v6"/>
                                                    <path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/>
                                                </svg>
                                                Supprimer
                                            </button>
                                        </form>
                                    @endif

                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($submissions->hasPages())
            <div style="margin-top:20px; display:flex; justify-content:center;">
                {{ $submissions->links() }}
            </div>
        @endif

    @endif
</div>

@endsection