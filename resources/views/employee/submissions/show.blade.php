{{--
    ============================================================
    VUE : employee/submissions/show.blade.php
    ------------------------------------------------------------
    MISE À JOUR : Toutes les colonnes d'une ligne refusée
    sont maintenant modifiables inline par l'employé.

    Colonnes modifiables sur une ligne REFUSE :
      - table_db2       → champ texte
      - cle_primaire    → champ texte
      - champ           → champ texte
      - valeur          → champ texte (déjà existant)
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
                <strong style="color:var(--text-main);">{{ $submission->getTotalCorrections() }}</strong> correction(s)
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
                Corrections demandées par le supérieur
            </strong>
        </div>
        <p style="font-size:0.875rem; color:var(--text-muted); margin:0 0 16px;">
            Les lignes en rouge ci-dessous ont été refusées. Cliquez sur une ligne pour modifier
            toutes ses colonnes, puis re-soumettez le dossier.
        </p>

        {{-- Barre de progression --}}
        <div style="margin-bottom:12px;">
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

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:16px;">
            <div style="text-align:center; padding:10px; background:rgba(239,68,68,0.08); border-radius:8px;">
                <div style="font-size:1.3rem; font-weight:800; font-family:'Syne',sans-serif; color:#f87171;">{{ $refusedCount }}</div>
                <div style="font-size:0.75rem; color:var(--text-muted);">Lignes refusées</div>
            </div>
            <div style="text-align:center; padding:10px; background:rgba(34,197,94,0.08); border-radius:8px;">
                <div id="count-corrected" style="font-size:1.3rem; font-weight:800; font-family:'Syne',sans-serif; color:#4ade80;">{{ $correctedCount }}</div>
                <div style="font-size:0.75rem; color:var(--text-muted);">Corrigées</div>
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

{{-- TABLEAU DES CORRECTIONS --}}
<div class="card" style="margin-bottom:20px;">

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
        <h3 style="font-family:'Syne',sans-serif; font-size:1rem; font-weight:700; margin:0;">
            Corrections — Version {{ $submission->version }}
        </h3>
        <span style="font-size:0.8rem; color:var(--text-muted);">{{ $corrections->total() }} ligne(s)</span>
    </div>

    @if($corrections->isEmpty())
        <div style="text-align:center; padding:40px; color:var(--text-muted);">
            <p style="font-size:0.875rem;">Aucune correction trouvée.</p>
        </div>
    @else

        {{-- Message d'aide si EN_CORRECTION --}}
        @if($submission->statut === 'EN_CORRECTION')
            <div style="padding:10px 14px; background:rgba(79,124,255,0.08); border-radius:8px; margin-bottom:16px; font-size:0.8rem; color:var(--accent-light);">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:inline; vertical-align:-2px; margin-right:4px;">
                    <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
                </svg>
                Les lignes <span style="color:#f87171; font-weight:600;">en rouge</span> ont été refusées.
                Cliquez sur le bouton <strong>✏️ Modifier</strong> pour corriger toutes les colonnes de la ligne.
            </div>
        @endif

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Table DB2</th>
                        <th>Clé primaire</th>
                        <th>Champ</th>
                        <th>Valeur</th>
                        <th>Révision</th>
                        <th>Push</th>
                        @if($submission->statut === 'EN_CORRECTION')
                            <th>Action</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($corrections as $correction)
                        {{-- Ligne principale --}}
                        <tr id="row-{{ $correction->id }}"
                            style="{{ $correction->statut_revision === 'REFUSE' ? 'background:rgba(239,68,68,0.04);' : '' }}
                                   {{ $correction->statut_revision === 'VALIDE' ? 'background:rgba(34,197,94,0.03);' : '' }}">

                            <td style="color:var(--text-muted); font-size:0.8rem; text-align:center;">{{ $correction->ligne_ref }}</td>

                            {{--
                                Pour chaque colonne on affiche :
                                - La valeur corrigée en vert si elle existe
                                - La valeur originale sinon
                                Ça permet à l'employé de voir ses corrections appliquées.
                            --}}
                            <td>
                                @if($correction->table_db2_corrigee)
                                    <span style="color:#4ade80; font-size:0.8rem; font-weight:500;">{{ $correction->table_db2_corrigee }}</span>
                                    <span style="color:var(--text-muted); font-size:0.72rem; text-decoration:line-through; display:block;">{{ $correction->table_db2 }}</span>
                                @else
                                    <code style="background:rgba(79,124,255,0.1); color:var(--accent-light); padding:2px 7px; border-radius:5px; font-size:0.8rem;">{{ $correction->table_db2 }}</code>
                                @endif
                            </td>

                            <td style="font-size:0.8rem; color:var(--text-muted);">
                                @if($correction->cle_primaire_corrigee)
                                    <span style="color:#4ade80; font-weight:500;">{{ $correction->cle_primaire_corrigee }}</span>
                                    <span style="color:var(--text-muted); font-size:0.72rem; text-decoration:line-through; display:block;">{{ $correction->cle_primaire }}</span>
                                @else
                                    {{ $correction->cle_primaire ?? '—' }}
                                @endif
                            </td>

                            <td style="font-weight:500;">
                                @if($correction->champ_corrige)
                                    <span style="color:#4ade80;">{{ $correction->champ_corrige }}</span>
                                    <span style="color:var(--text-muted); font-size:0.72rem; text-decoration:line-through; display:block;">{{ $correction->champ }}</span>
                                @else
                                    {{ $correction->champ }}
                                @endif
                            </td>

                            <td style="font-size:0.875rem;">
                                @if($correction->valeur_corrigee)
                                    <span style="color:#4ade80; font-weight:500;">{{ $correction->valeur_corrigee }}</span>
                                    <span style="color:var(--text-muted); font-size:0.72rem; text-decoration:line-through; display:block;">{{ $correction->valeur_correction }}</span>
                                @else
                                    {{ $correction->valeur_correction }}
                                @endif
                            </td>

                            {{-- Statut révision + commentaire supérieur --}}
                            <td>
                                @if($correction->statut_revision === 'VALIDE')
                                    <span class="badge badge-emerald">Validé</span>
                                @elseif($correction->statut_revision === 'REFUSE')
                                    <span class="badge badge-red">Refusé</span>
                                    @if($correction->commentaire_sup)
                                        <div style="margin-top:6px; padding:6px 8px; background:rgba(239,68,68,0.08); border-left:2px solid rgba(239,68,68,0.5); border-radius:0 4px 4px 0; max-width:200px;">
                                            <p style="font-size:0.72rem; color:#f87171; margin:0; line-height:1.5;">
                                                <strong>Note :</strong> {{ $correction->commentaire_sup }}
                                            </p>
                                        </div>
                                    @endif
                                    @if($correction->isCorrectedByEmployee())
                                        <div style="margin-top:4px;">
                                            <span style="font-size:0.7rem; color:#4ade80;">✓ Corrigé</span>
                                        </div>
                                    @endif
                                @else
                                    <span class="badge badge-gray">En attente</span>
                                @endif
                            </td>

                            {{-- Push statut --}}
                            <td>
                                @if($correction->push_statut === 'OK')
                                    <span class="badge badge-emerald">OK</span>
                                @elseif($correction->push_statut === 'ERREUR')
                                    <span class="badge badge-red" title="{{ $correction->push_message }}">Erreur</span>
                                @else
                                    <span class="badge badge-gray">—</span>
                                @endif
                            </td>

                            {{-- Bouton modifier (seulement pour les lignes refusées) --}}
                            @if($submission->statut === 'EN_CORRECTION')
                                <td id="action-{{ $correction->id }}">
                                    @if($correction->statut_revision === 'REFUSE')
                                        <button type="button"
                                                onclick="toggleEditForm({{ $correction->id }})"
                                                style="padding:5px 10px; background:rgba(79,124,255,0.1); color:var(--accent-light); border:1px solid rgba(79,124,255,0.3); border-radius:7px; font-size:0.78rem; cursor:pointer; font-family:inherit;">
                                            ✏️ Modifier
                                        </button>
                                    @else
                                        <span style="font-size:0.75rem; color:var(--text-muted);">—</span>
                                    @endif
                                </td>
                            @endif
                        </tr>

                        {{--
                            Formulaire d'édition inline — toutes les colonnes modifiables.
                            Caché par défaut, affiché au clic sur "Modifier".
                            Chaque champ est prérempli avec la valeur corrigée si elle existe,
                            sinon avec la valeur originale.
                        --}}
                        @if($submission->statut === 'EN_CORRECTION' && $correction->statut_revision === 'REFUSE')
                            <tr id="edit-form-{{ $correction->id }}" style="display:none;">
                                <td colspan="8" style="padding:0 16px 16px; background:rgba(79,124,255,0.04);">
                                    <div style="padding:16px; border:1px solid rgba(79,124,255,0.2); border-radius:10px; margin-top:4px;">

                                        <p style="font-size:0.8rem; color:var(--accent-light); font-weight:600; margin:0 0 14px;">
                                            ✏️ Modifier la ligne #{{ $correction->ligne_ref }}
                                        </p>

                                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">

                                            {{-- Champ Table DB2 --}}
                                            <div>
                                                <label style="font-size:0.75rem; color:var(--text-muted); margin-bottom:4px; display:block;">
                                                    Table DB2
                                                    <span style="color:var(--text-muted); font-weight:400;">(original : {{ $correction->table_db2 }})</span>
                                                </label>
                                                <input type="text"
                                                       id="table-{{ $correction->id }}"
                                                       value="{{ $correction->table_db2_corrigee ?? $correction->table_db2 }}"
                                                       placeholder="{{ $correction->table_db2 }}"
                                                       style="width:100%; padding:7px 10px; background:var(--bg-main); border:1px solid var(--border); border-radius:7px; color:var(--text-main); font-size:0.8rem; font-family:inherit;">
                                            </div>

                                            {{-- Champ Clé primaire --}}
                                            <div>
                                                <label style="font-size:0.75rem; color:var(--text-muted); margin-bottom:4px; display:block;">
                                                    Clé primaire
                                                    <span style="color:var(--text-muted); font-weight:400;">(original : {{ $correction->cle_primaire ?? '—' }})</span>
                                                </label>
                                                <input type="text"
                                                       id="cle-{{ $correction->id }}"
                                                       value="{{ $correction->cle_primaire_corrigee ?? $correction->cle_primaire }}"
                                                       placeholder="{{ $correction->cle_primaire ?? 'Ex: ID=1042' }}"
                                                       style="width:100%; padding:7px 10px; background:var(--bg-main); border:1px solid var(--border); border-radius:7px; color:var(--text-main); font-size:0.8rem; font-family:inherit;">
                                            </div>

                                            {{-- Champ Champ DB2 --}}
                                            <div>
                                                <label style="font-size:0.75rem; color:var(--text-muted); margin-bottom:4px; display:block;">
                                                    Champ
                                                    <span style="color:var(--text-muted); font-weight:400;">(original : {{ $correction->champ }})</span>
                                                </label>
                                                <input type="text"
                                                       id="champ-{{ $correction->id }}"
                                                       value="{{ $correction->champ_corrige ?? $correction->champ }}"
                                                       placeholder="{{ $correction->champ }}"
                                                       style="width:100%; padding:7px 10px; background:var(--bg-main); border:1px solid var(--border); border-radius:7px; color:var(--text-main); font-size:0.8rem; font-family:inherit;">
                                            </div>

                                            {{-- Champ Valeur --}}
                                            <div>
                                                <label style="font-size:0.75rem; color:var(--text-muted); margin-bottom:4px; display:block;">
                                                    Valeur
                                                    <span style="color:var(--text-muted); font-weight:400;">(original : {{ $correction->valeur_correction }})</span>
                                                </label>
                                                <input type="text"
                                                       id="valeur-{{ $correction->id }}"
                                                       value="{{ $correction->valeur_corrigee ?? $correction->valeur_correction }}"
                                                       placeholder="{{ $correction->valeur_correction }}"
                                                       style="width:100%; padding:7px 10px; background:var(--bg-main); border:1px solid var(--border); border-radius:7px; color:var(--text-main); font-size:0.8rem; font-family:inherit;">
                                            </div>

                                        </div>

                                        {{-- Boutons Enregistrer / Annuler --}}
                                        <div style="display:flex; gap:8px; justify-content:flex-end;">
                                            <button type="button"
                                                    onclick="toggleEditForm({{ $correction->id }})"
                                                    style="padding:6px 14px; background:transparent; color:var(--text-muted); border:1px solid var(--border); border-radius:7px; font-size:0.78rem; cursor:pointer; font-family:inherit;">
                                                Annuler
                                            </button>
                                            <button type="button"
                                                    onclick="saveLine({{ $correction->id }})"
                                                    style="padding:6px 14px; background:rgba(79,124,255,0.15); color:var(--accent-light); border:1px solid rgba(79,124,255,0.3); border-radius:7px; font-size:0.78rem; font-weight:600; cursor:pointer; font-family:inherit;">
                                                ✓ Enregistrer les corrections
                                            </button>
                                        </div>

                                    </div>
                                </td>
                            </tr>
                        @endif

                        {{-- Message erreur push --}}
                        @if($correction->push_statut === 'ERREUR' && $correction->push_message)
                            <tr>
                                <td colspan="8" style="padding:4px 16px 10px; background:rgba(239,68,68,0.04);">
                                    <span style="font-size:0.75rem; color:#f87171;">↳ {{ $correction->push_message }}</span>
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
     * Affiche ou cache le formulaire d'édition inline d'une ligne.
     */
    function toggleEditForm(correctionId) {
        const form = document.getElementById(`edit-form-${correctionId}`);
        if (form.style.display === 'none') {
            form.style.display = 'table-row';
        } else {
            form.style.display = 'none';
        }
    }

    /**
     * Enregistre toutes les corrections d'une ligne via PATCH.
     * Envoie les 4 colonnes modifiables au serveur.
     */
    async function saveLine(correctionId) {
        const table  = document.getElementById(`table-${correctionId}`)?.value.trim();
        const cle    = document.getElementById(`cle-${correctionId}`)?.value.trim();
        const champ  = document.getElementById(`champ-${correctionId}`)?.value.trim();
        const valeur = document.getElementById(`valeur-${correctionId}`)?.value.trim();

        if (!table && !cle && !champ && !valeur) {
            alert('Veuillez renseigner au moins une correction.');
            return;
        }

        try {
            const response = await fetch(`${BASE_URL}/${correctionId}`, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    table_db2_corrigee:    table  || null,
                    cle_primaire_corrigee: cle    || null,
                    champ_corrige:         champ  || null,
                    valeur_corrigee:       valeur || null,
                }),
            });

            const data = await response.json();

            if (data.success) {
                // Cache le formulaire
                toggleEditForm(correctionId);

                // Recharge la ligne visuellement — on recharge la page
                // pour afficher les valeurs corrigées en vert avec barré
                const wasAlreadyCorrected = document.getElementById(`row-${correctionId}`)
                    .dataset.corrected === 'true';

                if (!wasAlreadyCorrected) {
                    correctedCount++;
                    document.getElementById(`row-${correctionId}`).dataset.corrected = 'true';
                    updateProgress();
                }

                // Recharge la page pour afficher les nouvelles valeurs
                window.location.reload();
            } else {
                alert(data.error ?? 'Une erreur est survenue.');
            }
        } catch (err) {
            console.error('Erreur:', err);
            alert('Une erreur réseau est survenue.');
        }
    }

    /**
     * Met à jour la barre de progression et le bouton re-soumettre.
     */
    function updateProgress() {
        const progressBar  = document.getElementById('progress-bar');
        const progressText = document.getElementById('progress-text');
        const countEl      = document.getElementById('count-corrected');
        const btnResubmit  = document.getElementById('btn-resubmit');

        if (progressBar) {
            const pct = REFUSED_TOTAL > 0 ? Math.round((correctedCount / REFUSED_TOTAL) * 100) : 0;
            progressBar.style.width = pct + '%';
        }
        if (progressText) progressText.textContent = `${correctedCount} / ${REFUSED_TOTAL} ligne(s) corrigée(s)`;
        if (countEl) countEl.textContent = correctedCount;

        if (btnResubmit) {
            const allDone = correctedCount >= REFUSED_TOTAL;
            btnResubmit.disabled      = !allDone;
            btnResubmit.style.opacity = allDone ? '1' : '0.4';
            btnResubmit.style.cursor  = allDone ? 'pointer' : 'not-allowed';
        }
    }

    updateProgress();
</script>
@endpush