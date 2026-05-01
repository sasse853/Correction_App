{{--
    ============================================================
    VUE : employee/submissions/show.blade.php
    ------------------------------------------------------------
    MISE À JOUR : Ajout du scroll automatique vers la prochaine
    ligne refusée après chaque correction enregistrée.
    ============================================================
--}}

@extends('layouts.app')

@section('title', 'Dossier #' . $submission->id)
@section('page-title', 'Dossier #' . $submission->id)

@section('content')

{{-- EN-TÊTE --}}
<div style="display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:16px; margin-bottom:28px;">
    <div>
        <a href="{{ route('employe.submissions.index') }}"
           style="display:inline-flex; align-items:center; gap:6px; font-size:0.8rem; color:var(--text-muted); text-decoration:none; margin-bottom:12px;">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="M19 12H5M12 5l-7 7 7 7"/>
            </svg>
            Retour à mes dossiers
        </a>
        <h2 style="font-family:'Syne',sans-serif; font-size:1.3rem; font-weight:700; margin:0 0 8px;">
            {{ $submission->file_original_name }}
        </h2>
        <div style="display:flex; gap:20px; flex-wrap:wrap;">
            <span style="font-size:0.8rem; color:var(--text-muted);">
                Soumis le {{ $submission->created_at->format('d/m/Y à H:i') }}
            </span>
            <span style="font-size:0.8rem; color:var(--text-muted);">
                Version <strong style="color:var(--text-main);">v{{ $submission->version }}</strong>
            </span>
            <span style="font-size:0.8rem; color:var(--text-muted);">
                <strong style="color:var(--text-main);">{{ count($excelData) }}</strong> ligne(s)
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

{{-- BLOC EN_CORRECTION --}}
@if($submission->statut === 'EN_CORRECTION')
    <div style="background:rgba(251,146,60,0.08); border:1px solid rgba(251,146,60,0.3); border-radius:12px; padding:20px; margin-bottom:20px;">
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px;">
            <svg width="18" height="18" fill="none" stroke="#fb923c" stroke-width="2" viewBox="0 0 24 24">
                <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <strong style="color:#fb923c; font-family:'Syne',sans-serif;">
                Corrections demandées — {{ $refusedCount }} ligne(s) à corriger
            </strong>
        </div>
        <p style="font-size:0.875rem; color:var(--text-muted); margin:0 0 16px;">
            Les lignes surlignées en rouge ci-dessous ont été refusées par le supérieur.
            Consultez le commentaire sur chaque ligne et apportez vos corrections directement
            dans le tableau, puis re-soumettez.
        </p>

        {{-- Progression --}}
        <div style="margin-bottom:16px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                <span style="font-size:0.78rem; color:var(--text-muted);">Progression</span>
                <span id="progress-text" style="font-size:0.78rem; color:var(--text-muted);">
                    {{ $correctedCount }} / {{ $refusedCount }} ligne(s) corrigée(s)
                </span>
            </div>
            <div style="height:6px; background:rgba(255,255,255,0.06); border-radius:99px; overflow:hidden;">
                <div id="progress-bar"
                     style="height:100%; border-radius:99px; background:linear-gradient(90deg,#fb923c,#fbbf24); transition:width 0.4s ease;
                            width:{{ $refusedCount > 0 ? round(($correctedCount / $refusedCount) * 100) : 0 }}%;">
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('employe.submissions.resubmit', $submission) }}">
            @csrf
            <button type="submit"
                    id="btn-resubmit"
                    class="btn-primary"
                    {{ $correctedCount < $refusedCount ? 'disabled' : '' }}
                    style="{{ $correctedCount < $refusedCount ? 'opacity:0.4; cursor:not-allowed;' : '' }} width:100%;">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
                </svg>
                @if($correctedCount < $refusedCount)
                    Re-soumettre ({{ $refusedCount - $correctedCount }} ligne(s) restante(s))
                @else
                    Re-soumettre le dossier corrigé
                @endif
            </button>
        </form>
    </div>
@endif

{{-- RÉSULTAT PUSH --}}
@if(in_array($submission->statut, ['TERMINE', 'TERMINE_AVEC_ERREURS']))
    <div class="card" style="margin-bottom:20px; border-color:{{ $submission->statut === 'TERMINE' ? 'rgba(34,197,94,0.3)' : 'rgba(239,68,68,0.3)' }};">
        <h4 style="font-family:'Syne',sans-serif; font-size:0.9rem; font-weight:700; margin:0 0 14px;">Résultat du push DB2</h4>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
            <div style="text-align:center; padding:12px; background:rgba(34,197,94,0.08); border-radius:8px;">
                <div style="font-size:1.4rem; font-weight:700; color:#4ade80; font-family:'Syne',sans-serif;">{{ $submission->getAppliedCorrections() }}</div>
                <div style="font-size:0.75rem; color:var(--text-muted);">Lignes OK</div>
            </div>
            <div style="text-align:center; padding:12px; background:rgba(239,68,68,0.08); border-radius:8px;">
                <div style="font-size:1.4rem; font-weight:700; color:#f87171; font-family:'Syne',sans-serif;">{{ $submission->getTotalCorrections() - $submission->getAppliedCorrections() }}</div>
                <div style="font-size:0.75rem; color:var(--text-muted);">Erreurs</div>
            </div>
        </div>
    </div>
@endif

{{-- TABLEAU DU FICHIER EXCEL BRUT --}}
<div class="card" style="margin-bottom:20px;">

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
        <h3 style="font-family:'Syne',sans-serif; font-size:1rem; font-weight:700; margin:0;">
            Contenu du fichier — {{ $submission->file_original_name }}
        </h3>
        <div style="display:flex; align-items:center; gap:12px;">
            @if($submission->statut === 'EN_CORRECTION')
                <div style="display:flex; align-items:center; gap:8px; font-size:0.75rem; color:var(--text-muted);">
                    <span style="width:10px; height:10px; border-radius:2px; background:rgba(239,68,68,0.3); display:inline-block;"></span> Ligne refusée
                    <span style="width:10px; height:10px; border-radius:2px; background:rgba(34,197,94,0.3); display:inline-block; margin-left:6px;"></span> Corrigée
                </div>
            @endif
            <span style="font-size:0.8rem; color:var(--text-muted);">{{ count($excelData) }} ligne(s)</span>
        </div>
    </div>

    @if(! $fileExists)
        <div style="text-align:center; padding:40px; color:var(--text-muted);">
            <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 12px; opacity:0.4;">
                <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p style="font-size:0.875rem;">Le fichier original n'est plus disponible.</p>
        </div>

    @elseif(empty($excelColumns))
        <div style="text-align:center; padding:40px; color:var(--text-muted);">
            <p style="font-size:0.875rem;">Impossible de lire le contenu du fichier.</p>
        </div>

    @else
        <div style="overflow-x:auto; overflow-y:auto; max-height:65vh; border-radius:8px;">
            <table style="border-collapse:collapse; width:100%;">
                <thead style="position:sticky; top:0; z-index:10;">
                    <tr>
                        <th style="width:50px; background:var(--bg-card); border-bottom:1px solid var(--border);">#</th>
                        @foreach($excelColumns as $col)
                            <th style="background:var(--bg-card); border-bottom:1px solid var(--border);">{{ $col }}</th>
                        @endforeach
                        @if($submission->statut === 'EN_CORRECTION' || $refusedCount > 0)
                            <th style="background:var(--bg-card); border-bottom:1px solid var(--border);">Révision</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @php $lineNumber = $firstDataLine; @endphp
                    @foreach($excelData as $row)
                        @php
                            $correction  = $correctionsByLine[$lineNumber] ?? null;
                            $isRefused   = $correction && $correction->statut_revision === 'REFUSE';
                            $isValide    = $correction && $correction->statut_revision === 'VALIDE';
                            $isCorrected = $correction && $correction->isCorrectedByEmployee();
                        @endphp

                        <tr id="row-line-{{ $lineNumber }}"
                            style="{{ $isRefused && !$isCorrected ? 'background:rgba(239,68,68,0.06);' : '' }}
                                   {{ $isCorrected ? 'background:rgba(34,197,94,0.04);' : '' }}">

                            <td style="color:var(--text-muted); font-size:0.75rem; text-align:center; font-weight:500;">
                                {{ $lineNumber }}
                            </td>

                            @foreach($excelColumns as $colIndex => $col)
                                <td style="font-size:0.8rem;">
                                    @if($isRefused && $submission->statut === 'EN_CORRECTION')
                                        <input type="text"
                                               id="cell-{{ $lineNumber }}-{{ $colIndex }}"
                                               value="{{ $row[$col] ?? '' }}"
                                               data-original="{{ $row[$col] ?? '' }}"
                                               data-col="{{ $col }}"
                                               data-line="{{ $lineNumber }}"
                                               oninput="markCellEdited(this)"
                                               style="width:100%; min-width:80px; padding:3px 6px; background:transparent; border:1px solid transparent; border-radius:4px; color:var(--text-main); font-size:0.8rem; font-family:inherit; transition:border-color 0.18s;"
                                               onfocus="this.style.borderColor='rgba(251,146,60,0.5)'; this.style.background='var(--bg-main)'"
                                               onblur="this.style.borderColor='transparent'; this.style.background='transparent'">
                                    @else
                                        {{ $row[$col] ?? '—' }}
                                    @endif
                                </td>
                            @endforeach

                            @if($submission->statut === 'EN_CORRECTION' || $refusedCount > 0)
                                <td style="min-width:180px;">
                                    @if($isRefused)
                                        <div>
                                            @if($isCorrected)
                                                <span class="badge badge-emerald" style="font-size:0.7rem;">✓ Corrigé</span>
                                            @else
                                                <span class="badge badge-red" style="font-size:0.7rem;">Refusé</span>
                                            @endif

                                            @if($correction->commentaire_sup)
                                                <div style="margin-top:6px; padding:5px 7px; background:rgba(239,68,68,0.08); border-left:2px solid rgba(239,68,68,0.5); border-radius:0 4px 4px 0;">
                                                    <p style="font-size:0.72rem; color:#f87171; margin:0; line-height:1.4;">
                                                        <strong>Note :</strong> {{ $correction->commentaire_sup }}
                                                    </p>
                                                </div>
                                            @endif

                                            @if($submission->statut === 'EN_CORRECTION')
                                                <button type="button"
                                                        id="save-btn-{{ $lineNumber }}"
                                                        onclick="saveLine({{ $lineNumber }}, {{ $correction->id }})"
                                                        style="margin-top:8px; padding:4px 10px; background:rgba(251,146,60,0.15); color:#fb923c; border:1px solid rgba(251,146,60,0.3); border-radius:6px; font-size:0.72rem; font-weight:600; cursor:pointer; font-family:inherit; display:none;">
                                                    ✓ Enregistrer
                                                </button>
                                            @endif
                                        </div>
                                    @elseif($isValide)
                                        <span class="badge badge-emerald" style="font-size:0.7rem;">Validé</span>
                                    @else
                                        <span style="font-size:0.75rem; color:var(--text-muted);">—</span>
                                    @endif
                                </td>
                            @endif
                        </tr>

                        @php $lineNumber++; @endphp
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- HISTORIQUE DES RÉVISIONS --}}
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
                @php $isApproved = $review->isApproved(); @endphp
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
                        <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
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
                                <p style="font-size:0.8rem; color:var(--text-main); margin:0; line-height:1.5;">{{ $review->commentaire }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

@endsection

@push('scripts')
<script>
    const CSRF_TOKEN    = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const BASE_URL      = "{{ url('/employe/submissions/' . $submission->id . '/corrections') }}";
    const REFUSED_TOTAL = {{ $refusedCount }};
    let correctedCount  = {{ $correctedCount }};

    /**
     * Scrolle automatiquement vers la prochaine ligne refusée
     * non encore corrigée, après qu'une ligne a été enregistrée.
     *
     * On cherche toutes les lignes du tableau dont la cellule
     * de révision contient encore le badge "Refusé" (pas "Corrigé").
     * On prend la première qui vient APRÈS la ligne courante.
     *
     * Exemple : lignes refusées = 4, 10, 34
     * Après correction de la ligne 4 → scroll vers ligne 10
     * Après correction de la ligne 10 → scroll vers ligne 34
     *
     * @param {number} currentLineNumber - numéro de la ligne qui vient d'être corrigée
     */
    function scrollToNextRefused(currentLineNumber) {
        /*
         * On récupère toutes les lignes du tableau identifiées
         * par leur id "row-line-XX".
         */
        const allRows = document.querySelectorAll('tbody tr[id^="row-line-"]');
        let nextRefusedRow = null;

        for (const row of allRows) {
            const rowNum = parseInt(row.id.replace('row-line-', ''));

            // On ne s'intéresse qu'aux lignes APRÈS la ligne courante
            if (rowNum <= currentLineNumber) continue;

            /*
             * On vérifie si cette ligne est encore refusée et
             * pas encore corrigée. On cherche le badge "Refusé"
             * dans la dernière cellule de la ligne.
             * Si le badge est "✓ Corrigé" on passe à la suivante.
             */
            const lastCell = row.querySelector('td:last-child');
            if (lastCell) {
                const badgeRed = lastCell.querySelector('.badge-red');
                if (badgeRed && badgeRed.textContent.includes('Refusé')) {
                    nextRefusedRow = row;
                    break;
                }
            }
        }

        if (nextRefusedRow) {
            /*
             * Scroll fluide vers la prochaine ligne refusée.
             * block: 'center' place la ligne au milieu de l'écran.
             */
            nextRefusedRow.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });

            /*
             * Effet de surbrillance orange pendant 800ms
             * pour attirer l'attention sur la prochaine ligne à corriger.
             * On utilise orange (couleur EN_CORRECTION) pour rester
             * cohérent avec le thème de cette étape.
             */
            nextRefusedRow.style.background = 'rgba(251,146,60,0.15)';
            setTimeout(() => {
                // Restaure la couleur rouge de base pour les lignes refusées
                nextRefusedRow.style.background = 'rgba(239,68,68,0.06)';
            }, 800);
        }
    }

    function markCellEdited(input) {
        const lineNumber = input.dataset.line;
        const saveBtn    = document.getElementById(`save-btn-${lineNumber}`);
        const original   = input.dataset.original;

        if (saveBtn) {
            const hasChanges = Array.from(
                document.querySelectorAll(`input[data-line="${lineNumber}"]`)
            ).some(inp => inp.value !== inp.dataset.original);
            saveBtn.style.display = hasChanges ? 'inline-block' : 'none';
        }

        if (input.value !== original) {
            input.style.color = '#fb923c';
        } else {
            input.style.color = 'var(--text-main)';
        }
    }

    async function saveLine(lineNumber, correctionId) {
        const cells = document.querySelectorAll(`input[data-line="${lineNumber}"]`);

        const values = {};
        cells.forEach((cell, index) => {
            values[`col_${index}`] = cell.value.trim();
        });

        const colKeys = Object.keys(values);
        const payload = {
            table_db2_corrigee:    colKeys[0] ? values[colKeys[0]] : null,
            cle_primaire_corrigee: colKeys[1] ? values[colKeys[1]] : null,
            champ_corrige:         colKeys[2] ? values[colKeys[2]] : null,
            valeur_corrigee:       colKeys[3] ? values[colKeys[3]] : null,
        };

        try {
            const response = await fetch(`${BASE_URL}/${correctionId}`, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();

            if (data.success) {
                const saveBtn = document.getElementById(`save-btn-${lineNumber}`);
                if (saveBtn) saveBtn.style.display = 'none';

                // Coloration verte de la ligne corrigée
                document.getElementById(`row-line-${lineNumber}`).style.background = 'rgba(34,197,94,0.04)';

                // Mise à jour du badge : Refusé → ✓ Corrigé
                const lastCell = document.querySelector(`#row-line-${lineNumber} td:last-child`);
                if (lastCell) {
                    const badgeRed = lastCell.querySelector('.badge-red');
                    if (badgeRed) {
                        badgeRed.className    = 'badge badge-emerald';
                        badgeRed.style.fontSize = '0.7rem';
                        badgeRed.textContent  = '✓ Corrigé';
                    }
                }

                // Mise à jour du compteur
                const wasAlreadyCorrected = document.getElementById(`row-line-${lineNumber}`).dataset.corrected === 'true';
                if (!wasAlreadyCorrected) {
                    correctedCount++;
                    document.getElementById(`row-line-${lineNumber}`).dataset.corrected = 'true';
                    updateProgress();
                }

                /*
                 * Scroll automatique vers la prochaine ligne refusée.
                 * Appelé après chaque enregistrement réussi.
                 * Guide l'employé directement vers la prochaine
                 * ligne qui nécessite son attention.
                 */
                scrollToNextRefused(lineNumber);

            } else {
                alert(data.error ?? 'Une erreur est survenue.');
            }
        } catch (err) {
            console.error('Erreur:', err);
            alert('Une erreur réseau est survenue.');
        }
    }

    function updateProgress() {
        const progressBar  = document.getElementById('progress-bar');
        const progressText = document.getElementById('progress-text');
        const btnResubmit  = document.getElementById('btn-resubmit');

        if (progressBar) {
            const pct = REFUSED_TOTAL > 0 ? Math.round((correctedCount / REFUSED_TOTAL) * 100) : 0;
            progressBar.style.width = pct + '%';
        }
        if (progressText) {
            progressText.textContent = `${correctedCount} / ${REFUSED_TOTAL} ligne(s) corrigée(s)`;
        }
        if (btnResubmit) {
            const allDone = correctedCount >= REFUSED_TOTAL;
            btnResubmit.disabled      = !allDone;
            btnResubmit.style.opacity = allDone ? '1' : '0.4';
            btnResubmit.style.cursor  = allDone ? 'pointer' : 'not-allowed';
            btnResubmit.innerHTML     = allDone
                ? `<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg> Re-soumettre le dossier corrigé`
                : `<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg> Re-soumettre (${REFUSED_TOTAL - correctedCount} ligne(s) restante(s))`;
        }
    }

    updateProgress();
</script>
@endpush