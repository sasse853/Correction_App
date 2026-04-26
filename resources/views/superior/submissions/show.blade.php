@extends('layouts.app')

@section('titre', 'Dossier #' . $submission->id)
@section('page-title', 'Révision — ' . $submission->file_original_name)

{{-- Formulaire global pour toutes les décisions --}}
@if(in_array($submission->statut, ['EN_ATTENTE', 'EN_REVISION']))
<form id="form-revision"
      method="POST"
      action="{{ route('superieur.submissions.reject', $submission) }}">
    @csrf
</form>

{{-- Bouton de soumission fixe en bas de page --}}
<div style="position:fixed; bottom:0; left:260px; right:0; background:white;
            border-top:1px solid var(--gris-border); padding:14px 28px;
            display:flex; align-items:center; justify-content:space-between;
            z-index:50; box-shadow:0 -4px 12px rgba(0,0,0,0.06);">

    <span style="font-size:0.85rem; color:var(--gris-text);">
        Vérifiez chaque ligne avant de soumettre votre révision
    </span>

    <div style="display:flex; gap:12px;">
        {{-- Bouton approuver tout --}}
        <button type="button"
                onclick="validerTout()"
                class="btn btn-success">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="M5 13l4 4L19 7"/>
            </svg>
            Tout valider
        </button>

        {{-- Bouton soumettre la révision --}}
        <button type="submit"
                form="form-revision"
                class="btn btn-primary"
                onclick="return verifierAvantSoumission()">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
            </svg>
            Soumettre la révision
        </button>
    </div>
</div>

{{-- Marge pour éviter que le contenu soit caché par la barre fixe --}}
<div style="height:70px;"></div>
@endif

@section('content')

{{-- En-tête du dossier --}}
<div style="display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:16px; margin-bottom:28px;">

    <div>
        <a href="{{ route('superieur.submissions.index') }}"
           style="display:inline-flex; align-items:center; gap:6px; font-size:0.8rem; color:var(--gris-text); text-decoration:none; margin-bottom:12px;">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="M19 12H5M12 5l-7 7 7 7"/>
            </svg>
            Retour aux dossiers
        </a>

        <h2 style="font-size:1.3rem; font-weight:700; margin:0 0 8px; color:var(--texte);">
            {{ $submission->file_original_name }}
        </h2>

        <div style="display:flex; align-items:center; gap:20px; flex-wrap:wrap;">
            <span style="font-size:0.8rem; color:var(--gris-text);">
                Soumis le {{ $submission->created_at->format('d/m/Y à H:i') }}
            </span>
            <span style="font-size:0.8rem; color:var(--gris-text);">
                Par <strong style="color:var(--texte);">{{ $submission->user->name }}</strong>
            </span>
            <span style="font-size:0.8rem; color:var(--gris-text);">
                Version <strong style="color:var(--texte);">v{{ $submission->version }}</strong>
            </span>
        </div>
    </div>

    {{-- Badge statut --}}
    @php
        $badgeMap = [
            'EN_ATTENTE'           => 'badge-attente',
            'EN_REVISION'          => 'badge-revision',
            'EN_CORRECTION'        => 'badge-correction',
            'APPROUVE'             => 'badge-approuve',
            'TERMINE'              => 'badge-termine',
            'TERMINE_AVEC_ERREURS' => 'badge-erreur',
        ];
        $badgeClass = $badgeMap[$submission->statut] ?? 'badge-attente';
    @endphp
    <span class="badge {{ $badgeClass }}" style="font-size:0.8rem; padding:6px 14px;">
        {{ $submission->getStatutLabel() }}
    </span>

</div>

{{-- ════════════════════════════════════════════
     DONNÉES DU FICHIER EXCEL
════════════════════════════════════════════ --}}
<<div class="card" style="margin-bottom:24px;">

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px; flex-wrap:wrap; gap:12px;">
        <h3 style="font-size:1rem; font-weight:700; margin:0; color:var(--rouge);">
            📊 Contenu du fichier Excel
        </h3>
        <div style="display:flex; gap:10px; align-items:center;">
            <span style="font-size:0.8rem; color:var(--gris-text);">
                {{ count($excelData) }} ligne(s)
            </span>
            {{-- Bouton télécharger --}}
            <a href="{{ route('superieur.submissions.download', $submission) }}"
               class="btn btn-ghost"
               style="font-size:0.8rem; padding:7px 14px;">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                </svg>
                Télécharger le fichier
            </a>
        </div>
    </div>

    @if(empty($excelData))
        <div style="text-align:center; padding:40px; color:var(--gris-text);">
            <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 12px; opacity:0.3;">
                <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p style="font-size:0.875rem;">Impossible de lire le fichier Excel ou fichier vide.</p>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        @foreach($excelColumns as $colonne)
                            <th>{{ $colonne }}</th>
                        @endforeach
                        <th>Commentaire</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($excelData as $index => $ligne)
                        <tr>
                            <td style="color:var(--gris-text); font-size:0.8rem; white-space:nowrap;">
                                {{ $index + 1 }}
                            </td>
                            @foreach($excelColumns as $colonne)
                                <td style="white-space:nowrap; font-size:0.82rem;">
                                    {{ $ligne[$colonne] ?? '—' }}
                                </td>
                            @endforeach
                            {{-- Cellule décision par ligne --}}
<td style="min-width:280px;">
    @if(in_array($submission->statut, ['EN_ATTENTE', 'EN_REVISION']))
        <div style="display:flex; flex-direction:column; gap:8px;">

            {{-- Cases à cocher --}}
            <div style="display:flex; gap:16px; align-items:center;">
                <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:0.8rem; color:#16a34a; font-weight:600; margin:0;">
                    <input type="radio"
                           name="decisions[{{ $index }}][statut]"
                           value="valide"
                           form="form-revision"
                           onchange="toggleJustification({{ $index }}, 'valide')"
                           style="accent-color:#16a34a; width:15px; height:15px;">
                    ✓ Valider
                </label>

                <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:0.8rem; color:var(--rouge); font-weight:600; margin:0;">
                    <input type="radio"
                           name="decisions[{{ $index }}][statut]"
                           value="rejete"
                           form="form-revision"
                           onchange="toggleJustification({{ $index }}, 'rejete')"
                           style="accent-color:#C8102E; width:15px; height:15px;">
                    ✕ Rejeter
                </label>
            </div>

            {{-- Champ justification caché par défaut --}}
            <div id="justification-{{ $index }}"
                 style="display:none;">
                <input type="text"
                       name="decisions[{{ $index }}][justification]"
                       form="form-revision"
                       placeholder="Raison du rejet (obligatoire)..."
                       style="width:100%; padding:6px 10px; border:1px solid var(--rouge);
                              border-radius:6px; font-size:0.78rem; background:transparent;
                              color:var(--texte); outline:none; font-family:'Century Gothic',sans-serif;">
            </div>

        </div>
    @endif
</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>

{{-- ════════════════════════════════════════════
     GRILLE : Actions + Historique
════════════════════════════════════════════ --}}
<div style="display:grid; grid-template-columns:1fr 360px; gap:20px; align-items:start;">

    {{-- COLONNE GAUCHE : Actions du supérieur --}}
    <div style="display:flex; flex-direction:column; gap:16px;">

        {{-- Formulaire Approbation --}}
        @if(in_array($submission->statut, ['EN_ATTENTE', 'EN_REVISION']))
        <div class="card" style="border-left:4px solid #16a34a;">

            <h3 style="font-size:1rem; font-weight:700; margin:0 0 16px; color:#16a34a;">
                ✅ Approuver le dossier
            </h3>

            <form method="POST" action="{{ route('superieur.submissions.approve', $submission) }}">
                @csrf
                <div style="margin-bottom:14px;">
                    <label>Commentaire (facultatif)</label>
                    <textarea name="commentaire"
                              rows="3"
                              placeholder="Ajouter un commentaire d'approbation..."
                              class="input"
                              style="resize:vertical;">{{ old('commentaire') }}</textarea>
                </div>
                <button type="submit"
                        class="btn btn-success"
                        onclick="return confirm('Confirmer l\'approbation de ce dossier ? Le push vers DB2 sera déclenché.')">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M5 13l4 4L19 7"/>
                    </svg>
                    Approuver et pousser vers DB2
                </button>
            </form>

        </div>

        {{-- Formulaire Rejet --}}
        <div class="card" style="border-left:4px solid var(--rouge);">

            <h3 style="font-size:1rem; font-weight:700; margin:0 0 16px; color:var(--rouge);">
                ✕ Demander une correction
            </h3>

            <form method="POST" action="{{ route('superieur.submissions.reject', $submission) }}">
                @csrf
                <div style="margin-bottom:14px;">
                    <label>
                        Commentaire de rejet
                        <span style="color:var(--rouge);">*</span>
                    </label>
                    <textarea name="commentaire"
                              rows="4"
                              placeholder="Expliquez les corrections à apporter (min. 10 caractères)..."
                              class="input"
                              style="resize:vertical;"
                              required>{{ old('commentaire') }}</textarea>
                    @error('commentaire')
                        <p style="color:var(--rouge); font-size:0.8rem; margin-top:4px;">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit"
                        class="btn btn-danger"
                        onclick="return confirm('Confirmer le rejet ? L\'employé sera notifié.')">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Rejeter et notifier l'employé
                </button>
            </form>

        </div>
        @endif

    </div>
    {{-- Fin colonne gauche --}}

    {{-- COLONNE DROITE : Historique des révisions --}}
    <div class="card">
        <h4 style="font-size:0.9rem; font-weight:700; margin:0 0 16px; color:var(--texte);">
            Historique des révisions
        </h4>

        @if($reviews->isEmpty())
            <p style="font-size:0.875rem; color:var(--gris-text); text-align:center; padding:20px 0;">
                Aucune révision encore effectuée.
            </p>
        @else
            <div style="display:flex; flex-direction:column; gap:0;">
                @foreach($reviews as $review)
                    <div style="display:flex; gap:12px; padding-bottom:16px; position:relative;">

                        @if(! $loop->last)
                            <div style="position:absolute; left:15px; top:30px; bottom:0; width:1px; background:var(--gris-border);"></div>
                        @endif

                        <div style="width:30px; height:30px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center;
                                    background:{{ $review->decision === 'APPROUVE' ? '#dcfce7' : '#fee2e2' }};
                                    border:1px solid {{ $review->decision === 'APPROUVE' ? '#86efac' : '#fca5a5' }};">
                            @if($review->decision === 'APPROUVE')
                                <svg width="13" height="13" fill="none" stroke="#16a34a" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path d="M5 13l4 4L19 7"/>
                                </svg>
                            @else
                                <svg width="13" height="13" fill="none" stroke="#dc2626" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            @endif
                        </div>

                        <div style="flex:1; min-width:0;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                                <span style="font-size:0.8rem; font-weight:600;
                                             color:{{ $review->decision === 'APPROUVE' ? '#16a34a' : 'var(--rouge)' }};">
                                    {{ $review->decision === 'APPROUVE' ? 'Approuvé' : 'Rejeté' }}
                                </span>
                                <span style="font-size:0.72rem; color:var(--gris-text);">
                                    {{ $review->created_at->format('d/m/Y H:i') }}
                                </span>
                            </div>
                            <div style="font-size:0.78rem; color:var(--gris-text);">
                                par {{ $review->reviewer?->name ?? 'Inconnu' }}
                            </div>
                            @if($review->commentaire)
                                <div style="margin-top:8px; padding:8px 10px; background:var(--gris-bg); border-radius:6px;
                                            border-left:2px solid {{ $review->decision === 'APPROUVE' ? '#86efac' : '#fca5a5' }};">
                                    <p style="font-size:0.8rem; color:var(--texte); margin:0; line-height:1.5;">
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

@section('scripts')
<script>
    function toggleJustification(index, statut) {
        const zoneJustification = document.getElementById('justification-' + index);

        if (statut === 'rejete') {
            zoneJustification.style.display = 'block';
            zoneJustification.querySelector('input').required = true;
            zoneJustification.querySelector('input').focus();
        } else {
            zoneJustification.style.display = 'none';
            zoneJustification.querySelector('input').required = false;
            zoneJustification.querySelector('input').value = '';
        }
    }

    // Cocher "valider" pour toutes les lignes d'un coup
    function validerTout() {
        document.querySelectorAll('input[type="radio"][value="valide"]').forEach(radio => {
            radio.checked = true;
            // Déclencher l'événement onchange pour cacher les justifications
            const index = radio.name.match(/\[(\d+)\]/)[1];
            toggleJustification(index, 'valide');
        });
    }

    // Vérifier que toutes les lignes ont une décision avant de soumettre
    function verifierAvantSoumission() {
        const totalLignes = {{ count($excelData) }};
        let lignesSansDecision = [];

        for (let i = 0; i < totalLignes; i++) {
            const radios = document.querySelectorAll(`input[name="decisions[${i}][statut]"]`);
            const uneCochee = Array.from(radios).some(r => r.checked);
            if (!uneCochee) {
                lignesSansDecision.push(i + 1);
            }
        }

        if (lignesSansDecision.length > 0) {
            alert(`Les lignes suivantes n'ont pas de décision : ${lignesSansDecision.join(', ')}`);
            return false;
        }

        return confirm('Confirmer la soumission de votre révision ?');
    }
</script>
@endsection

@endsection