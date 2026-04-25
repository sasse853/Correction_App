{{--
    ============================================================
    VUE : superior/submissions/show.blade.php
    ------------------------------------------------------------
    Vue de révision d'un dossier. C'est la vue la plus critique
    du workflow : c'est ici que le supérieur approuve ou rejette.

    Variables transmises par ReviewController@show :
      - $submission  → objet Submission (statut mis à jour EN_REVISION
                        automatiquement dans le controller si c'était EN_ATTENTE)
      - $corrections → LengthAwarePaginator des lignes StagingCorrection
                        de la version courante (paginées par 25)
      - $reviews     → Collection de Review avec reviewer, ordre DESC

    Comportement des boutons :
      - Approuver → POST /{id}/approve → déclenche push DB2 immédiatement
      - Rejeter   → ouvre un modal avec textarea pour le commentaire
                    → POST /{id}/reject

    La vue est en LECTURE SEULE si le dossier n'est plus
    EN_ATTENTE ou EN_REVISION (déjà traité).
    ============================================================
--}}

@extends('layouts.app')

@section('title', 'Révision — Dossier #' . $submission->id)
@section('page-title', 'Révision du dossier #' . $submission->id)

@section('content')

{{-- ──────────────────────────────────────────────────────────
     EN-TÊTE : Infos du dossier + actions principales
     ────────────────────────────────────────────────────────── --}}
<div style="display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:16px; margin-bottom:28px;">

    {{-- Colonne gauche : navigation + métadonnées --}}
    <div>
        <a href="{{ route('superieur.dashboard') }}"
           style="display:inline-flex; align-items:center; gap:6px; font-size:0.8rem; color:var(--text-muted); text-decoration:none; margin-bottom:12px;">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="M19 12H5M12 5l-7 7 7 7"/>
            </svg>
            Retour au tableau de bord
        </a>

        <h2 style="font-family:'Syne',sans-serif; font-size:1.3rem; font-weight:700; margin:0 0 8px;">
            {{ $submission->file_original_name }}
        </h2>

        <div style="display:flex; align-items:center; gap:20px; flex-wrap:wrap;">
            <span style="font-size:0.8rem; color:var(--text-muted);">
                Soumis par <strong style="color:var(--text-main);">{{ $submission->user->name }}</strong>
            </span>
            <span style="font-size:0.8rem; color:var(--text-muted);">
                le {{ $submission->created_at->format('d/m/Y à H:i') }}
            </span>
            <span style="font-size:0.8rem; color:var(--text-muted);">
                Version <strong style="color:var(--text-main);">v{{ $submission->version }}</strong>
            </span>
            <span style="font-size:0.8rem; color:var(--text-muted);">
                <strong style="color:var(--text-main);">{{ $submission->getTotalCorrections() }}</strong> correction(s)
            </span>
        </div>

        {{-- Description facultative du dossier --}}
        @if($submission->description)
            <p style="margin-top:10px; font-size:0.85rem; color:var(--text-muted); font-style:italic;">
                "{{ $submission->description }}"
            </p>
        @endif
    </div>

    {{-- Colonne droite : badge statut + boutons d'action --}}
    <div style="display:flex; flex-direction:column; align-items:flex-end; gap:12px;">

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
        <span class="badge {{ $badgeMap[$submission->statut] ?? 'badge-gray' }}" style="font-size:0.8rem; padding:6px 14px;">
            {{ $submission->getStatutLabel() }}
        </span>

        {{--
            Boutons d'action UNIQUEMENT si le dossier est encore décidable.
            Si le dossier est déjà TERMINE, APPROUVE, etc. → pas de boutons.
        --}}
        @if(in_array($submission->statut, ['EN_ATTENTE', 'EN_REVISION']) && auth()->user()->hasRole('superieur'))
            <div style="display:flex; gap:10px;">

                {{-- Bouton Rejeter → ouvre le modal de rejet --}}
                <button type="button"
                        class="btn-danger"
                        onclick="openRejectModal()">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Rejeter
                </button>

                {{-- Bouton Approuver → ouvre le modal de confirmation --}}
                <button type="button"
                        class="btn-success"
                        onclick="openApproveModal()">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M5 13l4 4L19 7"/>
                    </svg>
                    Approuver & Push DB2
                </button>

            </div>
        @endif

    </div>
</div>

{{-- ──────────────────────────────────────────────────────────
     GRILLE 2 COLONNES
     Gauche (65%) : tableau des corrections à réviser
     Droite (35%) : historique des révisions
     ────────────────────────────────────────────────────────── --}}
<div style="display:grid; grid-template-columns:1fr 360px; gap:20px; align-items:start;">

    {{-- ══════════════════════════════════════════════
         COLONNE GAUCHE : Corrections à réviser
         Le supérieur consulte ligne par ligne ce qui
         va être appliqué sur DB2 avant de décider.
         ══════════════════════════════════════════════ --}}
    <div class="card">

        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
            <h3 style="font-family:'Syne',sans-serif; font-size:1rem; font-weight:700; margin:0;">
                Lignes à appliquer sur DB2
            </h3>
            <span style="font-size:0.8rem; color:var(--text-muted);">
                {{ $corrections->total() }} ligne(s) — version {{ $submission->version }}
            </span>
        </div>

        @if($corrections->isEmpty())
            <div style="text-align:center; padding:40px; color:var(--text-muted);">
                <p style="font-size:0.875rem;">Aucune correction à afficher.</p>
            </div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Ligne</th>
                            <th>Table DB2</th>
                            <th>Clé primaire</th>
                            <th>Champ</th>
                            <th>Nouvelle valeur</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($corrections as $correction)
                            <tr>
                                <td style="color:var(--text-muted); font-size:0.8rem; text-align:center;">
                                    {{ $correction->ligne_ref }}
                                </td>
                                <td>
                                    <code style="background:rgba(79,124,255,0.1); color:var(--accent-light); padding:2px 7px; border-radius:5px; font-size:0.8rem;">
                                        {{ $correction->table_db2 }}
                                    </code>
                                </td>
                                <td style="font-size:0.8rem; color:var(--text-muted);">
                                    {{ $correction->cle_primaire ?? '—' }}
                                </td>
                                <td style="font-weight:500; font-size:0.875rem;">
                                    {{ $correction->champ }}
                                </td>
                                <td style="font-size:0.875rem;">
                                    {{ $correction->valeur_correction }}
                                </td>
                                <td>
                                    @if($correction->push_statut === 'OK')
                                        <span class="badge badge-emerald">OK</span>
                                    @elseif($correction->push_statut === 'ERREUR')
                                        <span class="badge badge-red" title="{{ $correction->push_message }}">Erreur</span>
                                    @else
                                        <span class="badge badge-gray">En attente</span>
                                    @endif
                                </td>
                            </tr>
                            {{-- Message d'erreur détaillé si push en erreur --}}
                            @if($correction->push_statut === 'ERREUR' && $correction->push_message)
                                <tr>
                                    <td colspan="6" style="padding:4px 16px 10px; background:rgba(239,68,68,0.04);">
                                        <span style="font-size:0.75rem; color:#f87171;">
                                            ↳ {{ $correction->push_message }}
                                        </span>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($corrections->hasPages())
                <div style="margin-top:16px; display:flex; justify-content:center;">
                    {{ $corrections->links() }}
                </div>
            @endif
        @endif

    </div>

    {{-- ══════════════════════════════════════════════
         COLONNE DROITE : Historique des révisions
         ══════════════════════════════════════════════ --}}
    <div style="display:flex; flex-direction:column; gap:16px;">

        {{-- Résultat du push si le dossier est terminé --}}
        @if(in_array($submission->statut, ['TERMINE', 'TERMINE_AVEC_ERREURS']))
            <div class="card" style="border-color:{{ $submission->statut === 'TERMINE' ? 'rgba(34,197,94,0.3)' : 'rgba(239,68,68,0.3)' }};">
                <h4 style="font-family:'Syne',sans-serif; font-size:0.9rem; font-weight:700; margin:0 0 14px;">
                    Résultat du push DB2
                </h4>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div style="text-align:center; padding:12px; background:rgba(34,197,94,0.08); border-radius:8px;">
                        <div style="font-size:1.4rem; font-weight:700; color:#4ade80; font-family:'Syne',sans-serif;">
                            {{ $submission->getAppliedCorrections() }}
                        </div>
                        <div style="font-size:0.75rem; color:var(--text-muted);">Lignes OK</div>
                    </div>
                    <div style="text-align:center; padding:12px; background:rgba(239,68,68,0.08); border-radius:8px;">
                        <div style="font-size:1.4rem; font-weight:700; color:#f87171; font-family:'Syne',sans-serif;">
                            {{ $submission->getTotalCorrections() - $submission->getAppliedCorrections() }}
                        </div>
                        <div style="font-size:0.75rem; color:var(--text-muted);">Erreurs</div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Historique des décisions --}}
        <div class="card">
            <h4 style="font-family:'Syne',sans-serif; font-size:0.9rem; font-weight:700; margin:0 0 16px;">
                Historique des révisions
            </h4>

            @if($reviews->isEmpty())
                <p style="font-size:0.875rem; color:var(--text-muted); text-align:center; padding:20px 0;">
                    Aucune révision encore effectuée.
                </p>
            @else
                <div style="display:flex; flex-direction:column;">
                    @foreach($reviews as $review)
                        @php
                            $isApproved = $review->isApproved();
                        @endphp
                        <div style="display:flex; gap:12px; padding-bottom:16px; position:relative;">
                            @if(!$loop->last)
                                <div style="position:absolute; left:15px; top:30px; bottom:0; width:1px; background:var(--border);"></div>
                            @endif
                            <div style="width:30px; height:30px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center;
                                        background:{{ $isApproved ? 'rgba(34,197,94,0.15)' : 'rgba(239,68,68,0.15)' }};
                                        border:1px solid {{ $isApproved ? 'rgba(34,197,94,0.3)' : 'rgba(239,68,68,0.3)' }};">
                                @if($isApproved)
                                    <svg width="13" height="13" fill="none" stroke="#4ade80" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                                @else
                                    <svg width="13" height="13" fill="none" stroke="#f87171" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                                @endif
                            </div>
                            <div style="flex:1; min-width:0;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                                    <span style="font-size:0.8rem; font-weight:600; color:{{ $isApproved ? '#4ade80' : '#f87171' }};">
                                        {{ $isApproved ? 'Approuvé' : 'Rejeté' }}
                                    </span>
                                    <span style="font-size:0.72rem; color:var(--text-muted);">
                                        {{ $review->created_at->format('d/m/Y H:i') }}
                                    </span>
                                </div>
                                <div style="font-size:0.78rem; color:var(--text-muted);">
                                    par {{ $review->reviewer?->name ?? 'Inconnu' }}
                                </div>
                                @if($review->commentaire)
                                    <div style="margin-top:8px; padding:8px 10px; background:rgba(255,255,255,0.03); border-radius:6px; border-left:2px solid {{ $isApproved ? 'rgba(34,197,94,0.4)' : 'rgba(239,68,68,0.4)' }};">
                                        <p style="font-size:0.8rem; color:var(--text-main); margin:0; line-height:1.5;">
                                            {{ $review->commentaire }}
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>
</div>

{{-- ──────────────────────────────────────────────────────────
     MODAL : Confirmation d'approbation
     S'affiche quand le supérieur clique sur "Approuver".
     Lui permet d'ajouter un commentaire facultatif.
     ────────────────────────────────────────────────────────── --}}
<div id="modal-approve"
     style="display:none; position:fixed; inset:0; z-index:100; background:rgba(0,0,0,0.7); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
    <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:16px; padding:28px; width:100%; max-width:480px; margin:0 16px;">

        <h3 style="font-family:'Syne',sans-serif; font-size:1.1rem; font-weight:700; margin:0 0 8px; color:#4ade80;">
            Confirmer l'approbation
        </h3>
        <p style="font-size:0.875rem; color:var(--text-muted); margin:0 0 20px;">
            Cette action va immédiatement déclencher le push de
            <strong style="color:var(--text-main);">{{ $submission->getTotalCorrections() }} correction(s)</strong>
            vers la base DB2. Cette opération est irréversible.
        </p>

        {{-- Formulaire d'approbation --}}
        <form method="POST" action="{{ route('superieur.submissions.approve', $submission) }}">
            @csrf

            <div style="margin-bottom:20px;">
                <label for="commentaire-approve">Commentaire (facultatif)</label>
                <textarea name="commentaire"
                          id="commentaire-approve"
                          class="input"
                          rows="3"
                          placeholder="Note pour l'employé..."
                          style="resize:vertical;"></textarea>
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn-ghost" onclick="closeApproveModal()">
                    Annuler
                </button>
                <button type="submit" class="btn-success">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M5 13l4 4L19 7"/>
                    </svg>
                    Confirmer et pousser vers DB2
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ──────────────────────────────────────────────────────────
     MODAL : Formulaire de rejet
     Le commentaire est OBLIGATOIRE (min 10 caractères)
     pour que l'employé sache quoi corriger.
     ────────────────────────────────────────────────────────── --}}
<div id="modal-reject"
     style="display:none; position:fixed; inset:0; z-index:100; background:rgba(0,0,0,0.7); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
    <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:16px; padding:28px; width:100%; max-width:480px; margin:0 16px;">

        <h3 style="font-family:'Syne',sans-serif; font-size:1.1rem; font-weight:700; margin:0 0 8px; color:#f87171;">
            Demander une correction
        </h3>
        <p style="font-size:0.875rem; color:var(--text-muted); margin:0 0 20px;">
            Expliquez à l'employé ce qui doit être corrigé.
            Ce commentaire lui sera transmis par notification.
        </p>

        <form method="POST" action="{{ route('superieur.submissions.reject', $submission) }}">
            @csrf

            <div style="margin-bottom:20px;">
                <label for="commentaire-reject">
                    Commentaire de rejet
                    <span style="color:var(--danger);">*</span>
                </label>
                <textarea name="commentaire"
                          id="commentaire-reject"
                          class="input"
                          rows="5"
                          placeholder="Ex: La colonne NOM contient des valeurs incorrectes à la ligne 3. Veuillez vérifier les identifiants..."
                          required
                          minlength="10"
                          style="resize:vertical;">{{ old('commentaire') }}</textarea>
                {{-- Affiche l'erreur de validation si le commentaire est trop court --}}
                @error('commentaire')
                    <p style="color:var(--danger); font-size:0.8rem; margin-top:6px;">{{ $message }}</p>
                @enderror
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn-ghost" onclick="closeRejectModal()">
                    Annuler
                </button>
                <button type="submit" class="btn-danger">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Confirmer le rejet
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
    /**
     * Ouvre le modal d'approbation.
     * On utilise flex pour centrer le modal (display:flex sur la div overlay).
     */
    function openApproveModal() {
        document.getElementById('modal-approve').style.display = 'flex';
    }
    function closeApproveModal() {
        document.getElementById('modal-approve').style.display = 'none';
    }

    function openRejectModal() {
        document.getElementById('modal-reject').style.display = 'flex';
    }
    function closeRejectModal() {
        document.getElementById('modal-reject').style.display = 'none';
    }

    /**
     * Ferme les modals si on clique sur l'overlay (fond sombre),
     * mais pas si on clique à l'intérieur du modal.
     */
    document.getElementById('modal-approve').addEventListener('click', function(e) {
        if (e.target === this) closeApproveModal();
    });
    document.getElementById('modal-reject').addEventListener('click', function(e) {
        if (e.target === this) closeRejectModal();
    });

    /**
     * Si une erreur de validation existe (commentaire trop court),
     * on rouvre automatiquement le modal de rejet au chargement.
     */
    @if($errors->has('commentaire'))
        openRejectModal();
    @endif
</script>
@endpush