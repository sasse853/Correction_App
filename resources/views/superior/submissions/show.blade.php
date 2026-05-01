{{--
    ============================================================
    VUE : superior/submissions/show.blade.php
    ------------------------------------------------------------
    MISE À JOUR : Ajout du scroll automatique vers la prochaine
    ligne en attente après chaque action valider/refuser.
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
            <div id="count-validated" style="font-size:1.4rem; font-weight:800; font-family:'Syne',sans-serif; color:#4ade80;">{{ $validatedLines }}</div>
            <div style="font-size:0.75rem; color:var(--text-muted);">Validées</div>
        </div>
        <div style="text-align:center; padding:10px; background:rgba(239,68,68,0.08); border-radius:8px;">
            <div id="count-refused" style="font-size:1.4rem; font-weight:800; font-family:'Syne',sans-serif; color:#f87171;">{{ $refusedLines }}</div>
            <div style="font-size:0.75rem; color:var(--text-muted);">Refusées</div>
        </div>
        <div style="text-align:center; padding:10px; background:rgba(255,255,255,0.04); border-radius:8px;">
            <div id="count-pending" style="font-size:1.4rem; font-weight:800; font-family:'Syne',sans-serif; color:var(--text-muted);">{{ $pendingLines }}</div>
            <div style="font-size:0.75rem; color:var(--text-muted);">En attente</div>
        </div>
    </div>
</div>

{{-- BOUTONS DE FINALISATION --}}
<div id="finalize-actions" style="{{ $pendingLines > 0 ? 'display:none;' : '' }} margin-bottom:20px;">
    <div class="card" style="border-color:rgba(79,124,255,0.3);">
        <p style="font-size:0.875rem; color:var(--text-muted); margin:0 0 16px;">
            Toutes les lignes ont été traitées. Choisissez l'action finale :
        </p>
        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            @if(in_array($submission->statut, ['EN_ATTENTE', 'EN_REVISION']) && auth()->user()->hasRole('superieur'))
                <button type="button" id="btn-reject-final" class="btn-danger" onclick="openRejectModal()"
                        {{ $refusedLines === 0 ? 'disabled' : '' }}
                        style="{{ $refusedLines === 0 ? 'opacity:0.4; cursor:not-allowed;' : '' }}">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    Retourner à l'employé @if($refusedLines > 0)({{ $refusedLines }} ligne(s))@endif
                </button>
                <button type="button" id="btn-approve-final" class="btn-success" onclick="openApproveModal()"
                        {{ $refusedLines > 0 ? 'disabled' : '' }}
                        style="{{ $refusedLines > 0 ? 'opacity:0.4; cursor:not-allowed;' : '' }}">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                    Approuver & Push DB2 @if($refusedLines === 0)({{ $validatedLines }} ligne(s))@endif
                </button>
            @endif
        </div>
    </div>
</div>

{{-- TABLEAU DU FICHIER EXCEL BRUT --}}
<div class="card">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
        <h3 style="font-family:'Syne',sans-serif; font-size:1rem; font-weight:700; margin:0;">
            Contenu du fichier — {{ $submission->file_original_name }}
        </h3>
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="display:flex; align-items:center; gap:8px; font-size:0.75rem; color:var(--text-muted);">
                <span style="width:10px; height:10px; border-radius:2px; background:rgba(34,197,94,0.3); display:inline-block;"></span> Validée
                <span style="width:10px; height:10px; border-radius:2px; background:rgba(239,68,68,0.3); display:inline-block; margin-left:6px;"></span> Refusée
            </div>
            <span style="font-size:0.8rem; color:var(--text-muted);">{{ count($excelData) }} ligne(s)</span>
        </div>
    </div>

    @if(! $fileExists)
        <div style="text-align:center; padding:40px; color:var(--text-muted);">
            <p style="font-size:0.875rem;">Le fichier original n'est plus disponible.</p>
        </div>
    @elseif(empty($excelColumns))
        <div style="text-align:center; padding:40px; color:var(--text-muted);">
            <p style="font-size:0.875rem;">Impossible de lire le contenu du fichier.</p>
        </div>
    @else
        {{--
            Le sticky header nécessite que le scroll vertical soit
            sur le même conteneur que le tableau. On remplace .table-wrap
            par un div avec overflow-y:auto et max-height pour que
            position:sticky top:0 fonctionne correctement.
        --}}
        <div style="overflow-x:auto; overflow-y:auto; max-height:65vh; border-radius:8px;">
            <table style="border-collapse:collapse; width:100%;">
                <thead style="position:sticky; top:0; z-index:10;">
                    <tr>
                        <th style="width:50px; background:var(--bg-card); border-bottom:1px solid var(--border);">#</th>
                        @foreach($excelColumns as $col)
                            <th style="background:var(--bg-card); border-bottom:1px solid var(--border);">{{ $col }}</th>
                        @endforeach
                        <th style="background:var(--bg-card); border-bottom:1px solid var(--border); min-width:100px;">Statut</th>
                        @if(in_array($submission->statut, ['EN_ATTENTE', 'EN_REVISION']) && auth()->user()->hasRole('superieur'))
                            <th style="background:var(--bg-card); border-bottom:1px solid var(--border); min-width:90px;">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @php $lineNumber = $firstDataLine; @endphp
                    @foreach($excelData as $row)
                        @php
                            $correction  = $correctionsByLine[$lineNumber] ?? null;
                            $isValide    = $correction && $correction->statut_revision === 'VALIDE';
                            $isRefuse    = $correction && $correction->statut_revision === 'REFUSE';
                            $isCorrected = $correction && $correction->isCorrectedByEmployee();
                            $corrId      = $correction?->id;
                        @endphp

                        <tr id="row-{{ $lineNumber }}"
                            style="transition:background 0.3s;
                                   {{ $isValide ? 'background:rgba(34,197,94,0.04);' : '' }}
                                   {{ $isRefuse && !$isCorrected ? 'background:rgba(239,68,68,0.04);' : '' }}
                                   {{ $isCorrected ? 'background:rgba(251,146,60,0.04);' : '' }}">

                            <td style="color:var(--text-muted); font-size:0.75rem; text-align:center; font-weight:500;">
                                {{ $lineNumber }}
                            </td>

                            @foreach($excelColumns as $col)
                                <td style="font-size:0.8rem;">{{ $row[$col] ?? '—' }}</td>
                            @endforeach

                            <td id="status-{{ $lineNumber }}">
                                @if($isValide)
                                    <span class="badge badge-emerald" style="font-size:0.7rem;">Validé</span>
                                @elseif($isRefuse)
                                    <div>
                                        @if($isCorrected)
                                            <span class="badge badge-orange" style="font-size:0.7rem;">Corrigé</span>
                                        @else
                                            <span class="badge badge-red" style="font-size:0.7rem;">Refusé</span>
                                        @endif
                                        @if($correction->commentaire_sup)
                                            <div style="margin-top:4px; padding:4px 6px; background:rgba(239,68,68,0.08); border-left:2px solid rgba(239,68,68,0.4); border-radius:0 4px 4px 0;">
                                                <p style="font-size:0.7rem; color:#f87171; margin:0; line-height:1.4;">{{ $correction->commentaire_sup }}</p>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <span class="badge badge-gray" style="font-size:0.7rem;">En attente</span>
                                @endif
                            </td>

                            @if(in_array($submission->statut, ['EN_ATTENTE', 'EN_REVISION']) && auth()->user()->hasRole('superieur'))
                                <td id="actions-{{ $lineNumber }}">
                                    @if($correction && $correction->statut_revision === 'PENDING')
                                        <div style="display:flex; gap:5px;">
                                            <button type="button"
                                                    onclick="validateLine({{ $lineNumber }}, {{ $corrId }})"
                                                    title="Valider"
                                                    style="width:28px; height:28px; border-radius:6px; border:1px solid rgba(34,197,94,0.3); background:rgba(34,197,94,0.1); color:#4ade80; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                                            </button>
                                            <button type="button"
                                                    onclick="openRefuseForm({{ $lineNumber }}, {{ $corrId }})"
                                                    title="Refuser"
                                                    style="width:28px; height:28px; border-radius:6px; border:1px solid rgba(239,68,68,0.3); background:rgba(239,68,68,0.1); color:#f87171; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                    @elseif($correction)
                                        <button type="button"
                                                onclick="resetLine({{ $lineNumber }}, {{ $corrId }})"
                                                style="font-size:0.7rem; padding:3px 7px; border-radius:5px; border:1px solid var(--border); background:transparent; color:var(--text-muted); cursor:pointer; font-family:inherit;">
                                            Annuler
                                        </button>
                                    @else
                                        <span style="font-size:0.75rem; color:var(--text-muted);">—</span>
                                    @endif
                                </td>
                            @endif
                        </tr>

                        @if($correction && in_array($submission->statut, ['EN_ATTENTE', 'EN_REVISION']) && auth()->user()->hasRole('superieur'))
                            <tr id="refuse-form-{{ $lineNumber }}" style="display:none;">
                                <td colspan="{{ count($excelColumns) + 3 }}" style="padding:0 16px 12px; background:rgba(239,68,68,0.04);">
                                    <div style="display:flex; gap:10px; align-items:flex-start; padding-top:8px;">
                                        <div style="flex:1;">
                                            <textarea id="commentaire-{{ $lineNumber }}"
                                                      placeholder="Expliquez ce qui doit être corrigé... (obligatoire)"
                                                      style="width:100%; padding:8px 12px; background:var(--bg-main); border:1px solid rgba(239,68,68,0.3); border-radius:8px; color:var(--text-main); font-size:0.8rem; font-family:inherit; resize:vertical; min-height:60px;"
                                                      rows="2"></textarea>
                                        </div>
                                        <div style="display:flex; flex-direction:column; gap:6px; flex-shrink:0;">
                                            <button type="button"
                                                    onclick="refuseLine({{ $lineNumber }}, {{ $corrId }})"
                                                    style="padding:7px 14px; background:rgba(239,68,68,0.15); color:#f87171; border:1px solid rgba(239,68,68,0.3); border-radius:7px; font-size:0.78rem; font-weight:600; cursor:pointer; font-family:inherit;">
                                                Confirmer le refus
                                            </button>
                                            <button type="button"
                                                    onclick="closeRefuseForm({{ $lineNumber }})"
                                                    style="padding:7px 14px; background:transparent; color:var(--text-muted); border:1px solid var(--border); border-radius:7px; font-size:0.78rem; cursor:pointer; font-family:inherit;">
                                                Annuler
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif

                        @php $lineNumber++; @endphp
                    @endforeach
                </tbody>
            </table>
        </div>
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
                        <div style="font-size:0.78rem; color:var(--text-muted);">par {{ $review->reviewer?->name ?? 'Inconnu' }}</div>
                        @if($review->commentaire)
                            <div style="margin-top:8px; padding:8px 10px; background:rgba(255,255,255,0.03); border-radius:6px; border-left:2px solid {{ $review->isApproved() ? 'rgba(34,197,94,0.4)' : 'rgba(239,68,68,0.4)' }};">
                                <p style="font-size:0.8rem; color:var(--text-main); margin:0; line-height:1.5;">{{ $review->commentaire }}</p>
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
        <h3 style="font-family:'Syne',sans-serif; font-size:1.1rem; font-weight:700; margin:0 0 8px; color:#4ade80;">Confirmer l'approbation</h3>
        <p style="font-size:0.875rem; color:var(--text-muted); margin:0 0 20px;">Toutes les lignes sont validées. Push vers DB2 immédiat. Irréversible.</p>
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
        <h3 style="font-family:'Syne',sans-serif; font-size:1.1rem; font-weight:700; margin:0 0 8px; color:#f87171;">Retourner à l'employé</h3>
        <p style="font-size:0.875rem; color:var(--text-muted); margin:0 0 20px;">L'employé recevra une notification avec les commentaires sur chaque ligne refusée.</p>
        <form method="POST" action="{{ route('superieur.submissions.reject', $submission) }}">
            @csrf
            <div style="margin-bottom:20px;">
                <label for="commentaire-reject">Message général (facultatif)</label>
                <textarea name="commentaire" id="commentaire-reject" class="input" rows="3" placeholder="Message global..."></textarea>
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
            if (btnApprove) { btnApprove.disabled = countRefused > 0; btnApprove.style.opacity = countRefused > 0 ? '0.4' : '1'; btnApprove.style.cursor = countRefused > 0 ? 'not-allowed' : 'pointer'; }
            if (btnReject)  { btnReject.disabled  = countRefused === 0; btnReject.style.opacity = countRefused === 0 ? '0.4' : '1'; btnReject.style.cursor = countRefused === 0 ? 'not-allowed' : 'pointer'; }
        }
    }

    /**
     * Scrolle automatiquement vers la prochaine ligne en attente.
     *
     * On cherche toutes les lignes du tableau dont la cellule
     * de statut contient encore "En attente".
     * On prend la première qui vient APRÈS la ligne courante.
     *
     * @param {number} currentLineNumber - numéro de la ligne traitée
     */
    function scrollToNextPending(currentLineNumber) {
        /*
         * On récupère toutes les lignes du tableau identifiées
         * par leur id "row-XX". On cherche la première ligne
         * dont le statut est encore "En attente" et dont le
         * numéro est supérieur à la ligne courante.
         */
        const allRows = document.querySelectorAll('tbody tr[id^="row-"]');
        let nextPendingRow = null;

        for (const row of allRows) {
            const rowNum = parseInt(row.id.replace('row-', ''));

            // On ne s'intéresse qu'aux lignes après la ligne courante
            if (rowNum <= currentLineNumber) continue;

            // On vérifie si cette ligne est encore en attente
            // en cherchant le badge "En attente" dans sa cellule statut
            const statusCell = document.getElementById(`status-${rowNum}`);
            if (statusCell && statusCell.textContent.trim().includes('En attente')) {
                nextPendingRow = row;
                break;
            }
        }

        if (nextPendingRow) {
            /*
             * Scroll fluide vers la prochaine ligne en attente.
             * block: 'center' place la ligne au milieu de l'écran
             * pour que le supérieur voie le contexte autour.
             */
            nextPendingRow.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });

            /*
             * Effet de surbrillance bleue pendant 800ms
             * pour attirer l'attention sur la prochaine ligne.
             * Le supérieur sait immédiatement où agir.
             */
            nextPendingRow.style.background = 'rgba(79,124,255,0.12)';
            setTimeout(() => {
                nextPendingRow.style.background = '';
            }, 800);
        }
    }

    async function validateLine(lineNumber, correctionId) {
        try {
            const response = await fetch(`${BASE_URL}/${correctionId}/validate`, {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            });
            const data = await response.json();
            if (data.success) {
                document.getElementById(`status-${lineNumber}`).innerHTML = `<span class="badge badge-emerald" style="font-size:0.7rem;">Validé</span>`;
                document.getElementById(`actions-${lineNumber}`).innerHTML = `
                    <button type="button" onclick="resetLine(${lineNumber}, ${correctionId})"
                            style="font-size:0.7rem; padding:3px 7px; border-radius:5px; border:1px solid var(--border); background:transparent; color:var(--text-muted); cursor:pointer; font-family:inherit;">
                        Annuler
                    </button>`;
                document.getElementById(`row-${lineNumber}`).style.background = 'rgba(34,197,94,0.04)';
                countValidated++; countPending--;
                updateProgress();
                /*
                 * Scroll automatique vers la prochaine ligne en attente.
                 * Appelé après chaque validation pour guider le supérieur.
                 */
                scrollToNextPending(lineNumber);
            }
        } catch (err) { console.error('Erreur:', err); }
    }

    function openRefuseForm(lineNumber, correctionId) {
        document.getElementById(`refuse-form-${lineNumber}`).style.display = 'table-row';
        document.getElementById(`commentaire-${lineNumber}`).focus();
    }

    function closeRefuseForm(lineNumber) {
        document.getElementById(`refuse-form-${lineNumber}`).style.display = 'none';
        document.getElementById(`commentaire-${lineNumber}`).value = '';
    }

    async function refuseLine(lineNumber, correctionId) {
        const commentaire = document.getElementById(`commentaire-${lineNumber}`).value.trim();
        if (commentaire.length < 5) { alert('Le commentaire doit contenir au moins 5 caractères.'); return; }

        try {
            const response = await fetch(`${BASE_URL}/${correctionId}/refuse`, {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ commentaire }),
            });
            const data = await response.json();
            if (data.success) {
                document.getElementById(`status-${lineNumber}`).innerHTML = `
                    <div>
                        <span class="badge badge-red" style="font-size:0.7rem;">Refusé</span>
                        <div style="margin-top:4px; padding:4px 6px; background:rgba(239,68,68,0.08); border-left:2px solid rgba(239,68,68,0.4); border-radius:0 4px 4px 0;">
                            <p style="font-size:0.7rem; color:#f87171; margin:0;">${data.commentaire}</p>
                        </div>
                    </div>`;
                document.getElementById(`actions-${lineNumber}`).innerHTML = `
                    <button type="button" onclick="resetLine(${lineNumber}, ${correctionId})"
                            style="font-size:0.7rem; padding:3px 7px; border-radius:5px; border:1px solid var(--border); background:transparent; color:var(--text-muted); cursor:pointer; font-family:inherit;">
                        Annuler
                    </button>`;
                closeRefuseForm(lineNumber);
                document.getElementById(`row-${lineNumber}`).style.background = 'rgba(239,68,68,0.04)';
                countRefused++; countPending--;
                updateProgress();
                /*
                 * Scroll automatique après un refus aussi.
                 * Le supérieur continue sur la ligne suivante
                 * sans avoir à chercher.
                 */
                scrollToNextPending(lineNumber);
            }
        } catch (err) { console.error('Erreur:', err); }
    }

    async function resetLine(lineNumber, correctionId) {
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
                document.getElementById(`status-${lineNumber}`).innerHTML = `<span class="badge badge-gray" style="font-size:0.7rem;">En attente</span>`;
                document.getElementById(`actions-${lineNumber}`).innerHTML = `
                    <div style="display:flex; gap:5px;">
                        <button type="button" onclick="validateLine(${lineNumber}, ${correctionId})" title="Valider"
                                style="width:28px; height:28px; border-radius:6px; border:1px solid rgba(34,197,94,0.3); background:rgba(34,197,94,0.1); color:#4ade80; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                        </button>
                        <button type="button" onclick="openRefuseForm(${lineNumber}, ${correctionId})" title="Refuser"
                                style="width:28px; height:28px; border-radius:6px; border:1px solid rgba(239,68,68,0.3); background:rgba(239,68,68,0.1); color:#f87171; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>`;
                document.getElementById(`row-${lineNumber}`).style.background = '';
                document.getElementById('finalize-actions').style.display = 'none';
                updateProgress();
            }
        } catch (err) { console.error('Erreur:', err); }
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