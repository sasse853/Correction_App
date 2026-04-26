{{--
    ============================================================
    VUE : superior/submissions/show.blade.php
    ------------------------------------------------------------
    MISE À JOUR : Affichage des valeurs corrigées par l'employé.
    Quand une ligne a été corrigée, la nouvelle valeur apparaît
    en vert avec l'ancienne valeur barrée pour toutes les colonnes.
    ============================================================
--}}

@extends('layouts.app')

@section('title', 'Révision — Dossier #' . $submission->id)
@section('page-title', 'Révision du dossier #' . $submission->id)

@section('content')

{{-- EN-TÊTE --}}
<div style="display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:16px; margin-bottom:24px;">

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
        <div style="display:flex; gap:20px; flex-wrap:wrap;">
            <span style="font-size:0.8rem; color:var(--text-muted);">
                Soumis par <strong style="color:var(--text-main);">{{ $submission->user->name }}</strong>
            </span>
            <span style="font-size:0.8rem; color:var(--text-muted);">
                le {{ $submission->created_at->format('d/m/Y à H:i') }}
            </span>
            <span style="font-size:0.8rem; color:var(--text-muted);">
                Version <strong style="color:var(--text-main);">v{{ $submission->version }}</strong>
            </span>
        </div>
    </div>

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
</div>

{{-- BARRE DE PROGRESSION --}}
<div class="card" style="margin-bottom:20px;">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
        <h3 style="font-family:'Syne',sans-serif; font-size:0.95rem; font-weight:700; margin:0;">
            Progression de la révision
        </h3>
        <span id="progress-text" style="font-size:0.8rem; color:var(--text-muted);">
            {{ $validatedLines + $refusedLines }} / {{ $totalLines }} lignes traitées
        </span>
    </div>

    <div style="height:8px; background:rgba(255,255,255,0.06); border-radius:99px; overflow:hidden; margin-bottom:16px;">
        <div id="progress-bar"
             style="height:100%; border-radius:99px; background:linear-gradient(90deg,var(--accent),#4ade80); transition:width 0.4s ease;
                    width:{{ $totalLines > 0 ? round((($validatedLines + $refusedLines) / $totalLines) * 100) : 0 }}%;">
        </div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:12px;">
        <div style="text-align:center; padding:10px; background:rgba(34,197,94,0.08); border-radius:8px;">
            <div id="count-validated" style="font-size:1.4rem; font-weight:800; font-family:'Syne',sans-serif; color:#4ade80;">
                {{ $validatedLines }}
            </div>
            <div style="font-size:0.75rem; color:var(--text-muted);">Validées</div>
        </div>
        <div style="text-align:center; padding:10px; background:rgba(239,68,68,0.08); border-radius:8px;">
            <div id="count-refused" style="font-size:1.4rem; font-weight:800; font-family:'Syne',sans-serif; color:#f87171;">
                {{ $refusedLines }}
            </div>
            <div style="font-size:0.75rem; color:var(--text-muted);">Refusées</div>
        </div>
        <div style="text-align:center; padding:10px; background:rgba(255,255,255,0.04); border-radius:8px;">
            <div id="count-pending" style="font-size:1.4rem; font-weight:800; font-family:'Syne',sans-serif; color:var(--text-muted);">
                {{ $pendingLines }}
            </div>
            <div style="font-size:0.75rem; color:var(--text-muted);">En attente</div>
        </div>
    </div>
</div>

{{-- BOUTONS DE FINALISATION --}}
<div id="finalize-actions"
     style="{{ $pendingLines > 0 ? 'display:none;' : '' }} margin-bottom:20px;">
    <div class="card" style="border-color:rgba(79,124,255,0.3);">
        <p style="font-size:0.875rem; color:var(--text-muted); margin:0 0 16px;">
            Toutes les lignes ont été traitées. Choisissez l'action finale :
        </p>
        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            @if(in_array($submission->statut, ['EN_ATTENTE', 'EN_REVISION']) && auth()->user()->hasRole('superieur'))
                <button type="button"
                        id="btn-reject-final"
                        class="btn-danger"
                        onclick="openRejectModal()"
                        {{ $refusedLines === 0 ? 'disabled' : '' }}
                        style="{{ $refusedLines === 0 ? 'opacity:0.4; cursor:not-allowed;' : '' }}">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Retourner à l'employé
                    @if($refusedLines > 0)
                        ({{ $refusedLines }} ligne(s) à corriger)
                    @endif
                </button>

                <button type="button"
                        id="btn-approve-final"
                        class="btn-success"
                        onclick="openApproveModal()"
                        {{ $refusedLines > 0 ? 'disabled' : '' }}
                        style="{{ $refusedLines > 0 ? 'opacity:0.4; cursor:not-allowed;' : '' }}">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M5 13l4 4L19 7"/>
                    </svg>
                    Approuver & Push DB2
                    @if($refusedLines === 0)
                        ({{ $validatedLines }} ligne(s))
                    @endif
                </button>
            @endif
        </div>
    </div>
</div>

{{-- TABLEAU DE RÉVISION --}}
<div class="card">

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
        <h3 style="font-family:'Syne',sans-serif; font-size:1rem; font-weight:700; margin:0;">
            Lignes à réviser — Version {{ $submission->version }}
        </h3>
        <span style="font-size:0.8rem; color:var(--text-muted);">
            {{ $corrections->total() }} ligne(s)
        </span>
    </div>

    @if($corrections->isEmpty())
        <div style="text-align:center; padding:40px; color:var(--text-muted);">
            <p>Aucune correction à réviser.</p>
        </div>
    @else
        <div class="table-wrap">
            <table id="corrections-table">
                <thead>
                    <tr>
                        <th>Ligne</th>
                        <th>Table DB2</th>
                        <th>Clé primaire</th>
                        <th>Champ</th>
                        <th>Valeur proposée</th>
                        <th>Statut</th>
                        @if(in_array($submission->statut, ['EN_ATTENTE', 'EN_REVISION']) && auth()->user()->hasRole('superieur'))
                            <th>Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($corrections as $correction)
                        <tr id="row-{{ $correction->id }}" style="transition:background 0.3s;">

                            <td style="color:var(--text-muted); font-size:0.8rem; text-align:center;">
                                {{ $correction->ligne_ref }}
                            </td>

                            {{--
                                MISE À JOUR : Pour chaque colonne, on affiche
                                la valeur corrigée par l'employé en vert avec
                                l'ancienne valeur barrée si une correction existe.
                                Sinon on affiche la valeur originale normalement.
                            --}}

                            {{-- Table DB2 --}}
                            <td>
                                @if($correction->table_db2_corrigee)
                                    <span style="color:#4ade80; font-size:0.8rem; font-weight:500;">
                                        {{ $correction->table_db2_corrigee }}
                                    </span>
                                    <span style="color:var(--text-muted); font-size:0.72rem; text-decoration:line-through; display:block;">
                                        {{ $correction->table_db2 }}
                                    </span>
                                @else
                                    <code style="background:rgba(79,124,255,0.1); color:var(--accent-light); padding:2px 7px; border-radius:5px; font-size:0.8rem;">
                                        {{ $correction->table_db2 }}
                                    </code>
                                @endif
                            </td>

                            {{-- Clé primaire --}}
                            <td style="font-size:0.8rem; color:var(--text-muted);">
                                @if($correction->cle_primaire_corrigee)
                                    <span style="color:#4ade80; font-weight:500;">
                                        {{ $correction->cle_primaire_corrigee }}
                                    </span>
                                    <span style="color:var(--text-muted); font-size:0.72rem; text-decoration:line-through; display:block;">
                                        {{ $correction->cle_primaire ?? '—' }}
                                    </span>
                                @else
                                    {{ $correction->cle_primaire ?? '—' }}
                                @endif
                            </td>

                            {{-- Champ --}}
                            <td style="font-weight:500;">
                                @if($correction->champ_corrige)
                                    <span style="color:#4ade80;">{{ $correction->champ_corrige }}</span>
                                    <span style="color:var(--text-muted); font-size:0.72rem; text-decoration:line-through; display:block;">
                                        {{ $correction->champ }}
                                    </span>
                                @else
                                    {{ $correction->champ }}
                                @endif
                            </td>

                            {{-- Valeur --}}
                            <td style="font-size:0.875rem;">
                                @if($correction->valeur_corrigee)
                                    <span style="color:#4ade80; font-weight:500;">
                                        {{ $correction->valeur_corrigee }}
                                    </span>
                                    <span style="color:var(--text-muted); font-size:0.72rem; text-decoration:line-through; display:block;">
                                        {{ $correction->valeur_correction }}
                                    </span>
                                @else
                                    {{ $correction->valeur_correction }}
                                @endif
                            </td>

                            {{-- Badge statut --}}
                            <td id="status-{{ $correction->id }}">
                                @if($correction->statut_revision === 'VALIDE')
                                    <span class="badge badge-emerald">Validé</span>
                                @elseif($correction->statut_revision === 'REFUSE')
                                    <div>
                                        <span class="badge badge-red">Refusé</span>
                                        @if($correction->commentaire_sup)
                                            <div style="margin-top:6px; padding:6px 8px; background:rgba(239,68,68,0.08); border-left:2px solid rgba(239,68,68,0.4); border-radius:0 4px 4px 0;">
                                                <p style="font-size:0.75rem; color:#f87171; margin:0;">
                                                    {{ $correction->commentaire_sup }}
                                                </p>
                                            </div>
                                        @endif
                                        {{-- Indicateur que l'employé a corrigé cette ligne --}}
                                        @if($correction->isCorrectedByEmployee())
                                            <div style="margin-top:4px;">
                                                <span style="font-size:0.7rem; color:#4ade80;">✓ Corrigé par l'employé</span>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <span class="badge badge-gray">En attente</span>
                                @endif
                            </td>

                            {{-- Boutons d'action --}}
                            @if(in_array($submission->statut, ['EN_ATTENTE', 'EN_REVISION']) && auth()->user()->hasRole('superieur'))
                                <td id="actions-{{ $correction->id }}">
                                    @if($correction->statut_revision === 'PENDING')
                                        <div style="display:flex; gap:6px; align-items:center;">
                                            <button type="button"
                                                    onclick="validateLine({{ $correction->id }})"
                                                    title="Valider cette ligne"
                                                    style="width:30px; height:30px; border-radius:7px; border:1px solid rgba(34,197,94,0.3); background:rgba(34,197,94,0.1); color:#4ade80; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all 0.18s;"
                                                    onmouseover="this.style.background='rgba(34,197,94,0.2)'"
                                                    onmouseout="this.style.background='rgba(34,197,94,0.1)'">
                                                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                    <path d="M5 13l4 4L19 7"/>
                                                </svg>
                                            </button>
                                            <button type="button"
                                                    onclick="openRefuseForm({{ $correction->id }})"
                                                    title="Refuser cette ligne"
                                                    style="width:30px; height:30px; border-radius:7px; border:1px solid rgba(239,68,68,0.3); background:rgba(239,68,68,0.1); color:#f87171; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all 0.18s;"
                                                    onmouseover="this.style.background='rgba(239,68,68,0.2)'"
                                                    onmouseout="this.style.background='rgba(239,68,68,0.1)'">
                                                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                    <path d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </div>
                                    @else
                                        <button type="button"
                                                onclick="resetLine({{ $correction->id }})"
                                                title="Annuler et re-traiter"
                                                style="font-size:0.72rem; padding:3px 8px; border-radius:5px; border:1px solid var(--border); background:transparent; color:var(--text-muted); cursor:pointer;">
                                            Annuler
                                        </button>
                                    @endif
                                </td>
                            @endif
                        </tr>

                        {{-- Formulaire de refus inline --}}
                        @if(in_array($submission->statut, ['EN_ATTENTE', 'EN_REVISION']) && auth()->user()->hasRole('superieur'))
                            <tr id="refuse-form-{{ $correction->id }}" style="display:none;">
                                <td colspan="7" style="padding:0 16px 12px; background:rgba(239,68,68,0.04);">
                                    <div style="display:flex; gap:10px; align-items:flex-start; padding-top:8px;">
                                        <div style="flex:1;">
                                            <textarea
                                                id="commentaire-{{ $correction->id }}"
                                                placeholder="Expliquez ce qui doit être corrigé sur cette ligne... (obligatoire)"
                                                style="width:100%; padding:8px 12px; background:var(--bg-main); border:1px solid rgba(239,68,68,0.3); border-radius:8px; color:var(--text-main); font-size:0.8rem; font-family:inherit; resize:vertical; min-height:60px;"
                                                rows="2"></textarea>
                                        </div>
                                        <div style="display:flex; flex-direction:column; gap:6px; flex-shrink:0;">
                                            <button type="button"
                                                    onclick="refuseLine({{ $correction->id }})"
                                                    style="padding:7px 14px; background:rgba(239,68,68,0.15); color:#f87171; border:1px solid rgba(239,68,68,0.3); border-radius:7px; font-size:0.78rem; font-weight:600; cursor:pointer; font-family:inherit;">
                                                Confirmer le refus
                                            </button>
                                            <button type="button"
                                                    onclick="closeRefuseForm({{ $correction->id }})"
                                                    style="padding:7px 14px; background:transparent; color:var(--text-muted); border:1px solid var(--border); border-radius:7px; font-size:0.78rem; cursor:pointer; font-family:inherit;">
                                                Annuler
                                            </button>
                                        </div>
                                    </div>
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

{{-- HISTORIQUE DES RÉVISIONS --}}
@if($reviews->isNotEmpty())
    <div class="card" style="margin-top:20px;">
        <h3 style="font-family:'Syne',sans-serif; font-size:1rem; font-weight:700; margin:0 0 16px;">
            Historique des révisions
        </h3>
        <div style="display:flex; flex-direction:column;">
            @foreach($reviews as $review)
                <div style="display:flex; gap:12px; padding-bottom:16px; position:relative;">
                    @if(!$loop->last)
                        <div style="position:absolute; left:15px; top:30px; bottom:0; width:1px; background:var(--border);"></div>
                    @endif
                    <div style="width:30px; height:30px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center;
                                background:{{ $review->isApproved() ? 'rgba(34,197,94,0.15)' : 'rgba(239,68,68,0.15)' }};
                                border:1px solid {{ $review->isApproved() ? 'rgba(34,197,94,0.3)' : 'rgba(239,68,68,0.3)' }};">
                        @if($review->isApproved())
                            <svg width="13" height="13" fill="none" stroke="#4ade80" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                        @else
                            <svg width="13" height="13" fill="none" stroke="#f87171" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                        @endif
                    </div>
                    <div style="flex:1;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                            <span style="font-size:0.8rem; font-weight:600; color:{{ $review->isApproved() ? '#4ade80' : '#f87171' }};">
                                {{ $review->isApproved() ? 'Approuvé' : 'Rejeté' }}
                            </span>
                            <span style="font-size:0.72rem; color:var(--text-muted);">
                                {{ $review->created_at->format('d/m/Y H:i') }}
                            </span>
                        </div>
                        <div style="font-size:0.78rem; color:var(--text-muted);">
                            par {{ $review->reviewer?->name ?? 'Inconnu' }}
                        </div>
                        @if($review->commentaire)
                            <div style="margin-top:8px; padding:8px 10px; background:rgba(255,255,255,0.03); border-radius:6px; border-left:2px solid {{ $review->isApproved() ? 'rgba(34,197,94,0.4)' : 'rgba(239,68,68,0.4)' }};">
                                <p style="font-size:0.8rem; color:var(--text-main); margin:0; line-height:1.5;">
                                    {{ $review->commentaire }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

{{-- MODALS --}}
<div id="modal-approve" style="display:none; position:fixed; inset:0; z-index:100; background:rgba(0,0,0,0.7); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
    <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:16px; padding:28px; width:100%; max-width:480px; margin:0 16px;">
        <h3 style="font-family:'Syne',sans-serif; font-size:1.1rem; font-weight:700; margin:0 0 8px; color:#4ade80;">
            Confirmer l'approbation
        </h3>
        <p style="font-size:0.875rem; color:var(--text-muted); margin:0 0 20px;">
            Toutes les lignes sont validées. Cette action va déclencher le push vers DB2. Irréversible.
        </p>
        <form method="POST" action="{{ route('superieur.submissions.approve', $submission) }}">
            @csrf
            <div style="margin-bottom:20px;">
                <label for="commentaire-approve">Commentaire (facultatif)</label>
                <textarea name="commentaire" id="commentaire-approve" class="input" rows="3" style="resize:vertical;"></textarea>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn-ghost" onclick="closeApproveModal()">Annuler</button>
                <button type="submit" class="btn-success">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                    Confirmer et pousser vers DB2
                </button>
            </div>
        </form>
    </div>
</div>

<div id="modal-reject" style="display:none; position:fixed; inset:0; z-index:100; background:rgba(0,0,0,0.7); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
    <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:16px; padding:28px; width:100%; max-width:480px; margin:0 16px;">
        <h3 style="font-family:'Syne',sans-serif; font-size:1.1rem; font-weight:700; margin:0 0 8px; color:#f87171;">
            Retourner le dossier à l'employé
        </h3>
        <p style="font-size:0.875rem; color:var(--text-muted); margin:0 0 20px;">
            L'employé recevra une notification avec les commentaires sur chaque ligne refusée.
        </p>
        <form method="POST" action="{{ route('superieur.submissions.reject', $submission) }}">
            @csrf
            <div style="margin-bottom:20px;">
                <label for="commentaire-reject">Message général (facultatif)</label>
                <textarea name="commentaire" id="commentaire-reject" class="input" rows="3"
                          placeholder="Message global pour accompagner les corrections..."></textarea>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn-ghost" onclick="closeRejectModal()">Annuler</button>
                <button type="submit" class="btn-danger">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    Retourner à l'employé
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const BASE_URL   = "{{ url('/superieur/submissions/' . $submission->id . '/corrections') }}";
    const TOTAL      = {{ $totalLines }};

    let countValidated = {{ $validatedLines }};
    let countRefused   = {{ $refusedLines }};
    let countPending   = {{ $pendingLines }};

    function updateProgress() {
        const done = countValidated + countRefused;
        document.getElementById('count-validated').textContent = countValidated;
        document.getElementById('count-refused').textContent   = countRefused;
        document.getElementById('count-pending').textContent   = countPending;
        document.getElementById('progress-text').textContent   = `${done} / ${TOTAL} lignes traitées`;

        const pct = TOTAL > 0 ? Math.round((done / TOTAL) * 100) : 0;
        document.getElementById('progress-bar').style.width = pct + '%';

        if (countPending === 0) {
            document.getElementById('finalize-actions').style.display = 'block';
            const btnApprove = document.getElementById('btn-approve-final');
            const btnReject  = document.getElementById('btn-reject-final');
            if (btnApprove) {
                btnApprove.disabled      = countRefused > 0;
                btnApprove.style.opacity = countRefused > 0 ? '0.4' : '1';
                btnApprove.style.cursor  = countRefused > 0 ? 'not-allowed' : 'pointer';
            }
            if (btnReject) {
                btnReject.disabled      = countRefused === 0;
                btnReject.style.opacity = countRefused === 0 ? '0.4' : '1';
                btnReject.style.cursor  = countRefused === 0 ? 'not-allowed' : 'pointer';
            }
        }
    }

    async function validateLine(correctionId) {
        const btn = document.querySelector(`#actions-${correctionId} button:first-child`);
        if (btn) { btn.disabled = true; btn.style.opacity = '0.5'; }

        try {
            const response = await fetch(`${BASE_URL}/${correctionId}/validate`, {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            });
            const data = await response.json();
            if (data.success) {
                document.getElementById(`status-${correctionId}`).innerHTML = `<span class="badge badge-emerald">Validé</span>`;
                document.getElementById(`actions-${correctionId}`).innerHTML = `
                    <button type="button" onclick="resetLine(${correctionId})"
                            style="font-size:0.72rem; padding:3px 8px; border-radius:5px; border:1px solid var(--border); background:transparent; color:var(--text-muted); cursor:pointer; font-family:inherit;">
                        Annuler
                    </button>`;
                document.getElementById(`row-${correctionId}`).style.background = 'rgba(34,197,94,0.04)';
                countValidated++; countPending--;
                updateProgress();
            }
        } catch (err) {
            console.error('Erreur validation ligne:', err);
            if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
        }
    }

    function openRefuseForm(correctionId) {
        document.getElementById(`refuse-form-${correctionId}`).style.display = 'table-row';
        document.getElementById(`commentaire-${correctionId}`).focus();
    }

    function closeRefuseForm(correctionId) {
        document.getElementById(`refuse-form-${correctionId}`).style.display = 'none';
        document.getElementById(`commentaire-${correctionId}`).value = '';
    }

    async function refuseLine(correctionId) {
        const commentaire = document.getElementById(`commentaire-${correctionId}`).value.trim();
        if (commentaire.length < 5) { alert('Le commentaire doit contenir au moins 5 caractères.'); return; }

        try {
            const response = await fetch(`${BASE_URL}/${correctionId}/refuse`, {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ commentaire }),
            });
            const data = await response.json();
            if (data.success) {
                document.getElementById(`status-${correctionId}`).innerHTML = `
                    <div>
                        <span class="badge badge-red">Refusé</span>
                        <div style="margin-top:6px; padding:6px 8px; background:rgba(239,68,68,0.08); border-left:2px solid rgba(239,68,68,0.4); border-radius:0 4px 4px 0;">
                            <p style="font-size:0.75rem; color:#f87171; margin:0;">${data.commentaire}</p>
                        </div>
                    </div>`;
                document.getElementById(`actions-${correctionId}`).innerHTML = `
                    <button type="button" onclick="resetLine(${correctionId})"
                            style="font-size:0.72rem; padding:3px 8px; border-radius:5px; border:1px solid var(--border); background:transparent; color:var(--text-muted); cursor:pointer; font-family:inherit;">
                        Annuler
                    </button>`;
                closeRefuseForm(correctionId);
                document.getElementById(`row-${correctionId}`).style.background = 'rgba(239,68,68,0.04)';
                countRefused++; countPending--;
                updateProgress();
            }
        } catch (err) { console.error('Erreur refus ligne:', err); }
    }

    async function resetLine(correctionId) {
        if (!confirm('Annuler cette décision et re-traiter la ligne ?')) return;
        try {
            const response = await fetch(`${BASE_URL}/${correctionId}/reset`, {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            });
            const data = await response.json();
            if (data.success) {
                const wasValidated = data.was === 'VALIDE';
                if (wasValidated) { countValidated--; } else { countRefused--; }
                countPending++;
                document.getElementById(`status-${correctionId}`).innerHTML = `<span class="badge badge-gray">En attente</span>`;
                document.getElementById(`actions-${correctionId}`).innerHTML = `
                    <div style="display:flex; gap:6px; align-items:center;">
                        <button type="button" onclick="validateLine(${correctionId})" title="Valider"
                                style="width:30px; height:30px; border-radius:7px; border:1px solid rgba(34,197,94,0.3); background:rgba(34,197,94,0.1); color:#4ade80; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                        </button>
                        <button type="button" onclick="openRefuseForm(${correctionId})" title="Refuser"
                                style="width:30px; height:30px; border-radius:7px; border:1px solid rgba(239,68,68,0.3); background:rgba(239,68,68,0.1); color:#f87171; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>`;
                document.getElementById(`row-${correctionId}`).style.background = '';
                document.getElementById('finalize-actions').style.display = 'none';
                updateProgress();
            }
        } catch (err) { console.error('Erreur reset ligne:', err); }
    }

    function openApproveModal()  { document.getElementById('modal-approve').style.display = 'flex'; }
    function closeApproveModal() { document.getElementById('modal-approve').style.display = 'none'; }
    function openRejectModal()   { document.getElementById('modal-reject').style.display = 'flex'; }
    function closeRejectModal()  { document.getElementById('modal-reject').style.display = 'none'; }

    document.getElementById('modal-approve').addEventListener('click', function(e) { if (e.target === this) closeApproveModal(); });
    document.getElementById('modal-reject').addEventListener('click',  function(e) { if (e.target === this) closeRejectModal(); });

    updateProgress();
</script>
@endpush