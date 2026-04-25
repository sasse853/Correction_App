{{--
    ============================================================
    VUE : employee/submissions/show.blade.php
    ------------------------------------------------------------
    Affiche le détail complet d'un dossier soumis par l'employé.

    Variables transmises par SubmissionController@show :
      - $submission    → objet Submission (avec relations chargées)
      - $corrections   → LengthAwarePaginator des lignes StagingCorrection
                         (filtrées sur la version courante, paginées par 20)
      - $reviews       → Collection de Review (avec reviewer chargé)
                         triée du plus récent au plus ancien

    Cas couverts par cette vue :
      1. Dossier EN_ATTENTE / EN_REVISION  → lecture seule, pas d'action
      2. Dossier EN_CORRECTION             → affiche les commentaires du
                                             supérieur + formulaire de
                                             re-soumission
      3. Dossier APPROUVE / TERMINE /
         TERMINE_AVEC_ERREURS              → lecture seule, résultat du push
    ============================================================
--}}

@extends('layouts.app')

{{-- Titre affiché dans la <title> du navigateur --}}
@section('title', 'Dossier #' . $submission->id)

{{-- Titre affiché dans la topbar --}}
@section('page-title', 'Dossier #' . $submission->id . ' — ' . $submission->file_original_name)

@section('content')

{{-- ──────────────────────────────────────────────────────────
     LIGNE 1 : En-tête du dossier
     Affiche les métadonnées principales + badge de statut
     ────────────────────────────────────────────────────────── --}}
<div style="display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:16px; margin-bottom:28px;">

    {{-- Colonne gauche : infos du dossier --}}
    <div>
        {{-- Lien retour vers la liste des dossiers --}}
        <a href="{{ route('employe.submissions.index') }}"
           style="display:inline-flex; align-items:center; gap:6px; font-size:0.8rem; color:var(--text-muted); text-decoration:none; margin-bottom:12px;">
            {{-- Icône flèche gauche --}}
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="M19 12H5M12 5l-7 7 7 7"/>
            </svg>
            Retour à mes dossiers
        </a>

        {{-- Nom du fichier original soumis --}}
        <h2 style="font-family:'Syne',sans-serif; font-size:1.3rem; font-weight:700; margin:0 0 8px;">
            {{ $submission->file_original_name }}
        </h2>

        {{-- Métadonnées : date, version, nombre de corrections --}}
        <div style="display:flex; align-items:center; gap:20px; flex-wrap:wrap;">
            <span style="font-size:0.8rem; color:var(--text-muted);">
                {{-- Icône calendrier --}}
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:inline;vertical-align:-2px;margin-right:4px;">
                    <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
                </svg>
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

    {{--
        Badge de statut — on utilise getStatutBadgeClass() défini dans le model
        qui retourne la bonne classe CSS selon le statut (ex: badge-yellow pour EN_ATTENTE)
    --}}
    <div>
        @php
            /*
             * On mappe le statut Tailwind du model vers nos classes CSS custom.
             * getStatutBadgeClass() retourne des classes bg-xxx/text-xxx Tailwind,
             * mais notre design system utilise .badge-xxx — on fait donc la conversion ici.
             */
            $badgeMap = [
                'EN_ATTENTE'           => 'badge-yellow',
                'EN_REVISION'          => 'badge-blue',
                'EN_CORRECTION'        => 'badge-orange',
                'APPROUVE'             => 'badge-green',
                'TERMINE'              => 'badge-emerald',
                'TERMINE_AVEC_ERREURS' => 'badge-red',
            ];
            $badgeClass = $badgeMap[$submission->statut] ?? 'badge-gray';
        @endphp
        <span class="badge {{ $badgeClass }}" style="font-size:0.8rem; padding:6px 14px;">
            {{ $submission->getStatutLabel() }}
        </span>
    </div>
</div>

{{-- ──────────────────────────────────────────────────────────
     BLOC CONDITIONNEL : Commentaires de rejet + re-soumission
     N'apparaît QUE si le statut est EN_CORRECTION,
     c'est-à-dire que le supérieur a demandé des corrections.
     ────────────────────────────────────────────────────────── --}}
@if($submission->statut === 'EN_CORRECTION')

    {{--
        On récupère le dernier review de type REJET pour afficher
        le commentaire du supérieur. $reviews est triée DESC donc
        le premier élément est le plus récent.
    --}}
    @php
        $dernierRejet = $reviews->where('decision', 'REJET')->first();
    @endphp

    {{-- Encart d'avertissement avec les commentaires du supérieur --}}
    <div style="background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.3); border-radius:12px; padding:20px; margin-bottom:24px;">

        <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px;">
            {{-- Icône avertissement --}}
            <svg width="18" height="18" fill="none" stroke="#fbbf24" stroke-width="2" viewBox="0 0 24 24">
                <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <strong style="color:#fbbf24; font-family:'Syne',sans-serif;">
                Correction demandée par {{ $dernierRejet?->reviewer?->name ?? 'le supérieur' }}
            </strong>
            @if($dernierRejet)
                <span style="font-size:0.75rem; color:var(--text-muted); margin-left:auto;">
                    {{ $dernierRejet->created_at->format('d/m/Y à H:i') }}
                </span>
            @endif
        </div>

        {{-- Texte du commentaire --}}
        @if($dernierRejet?->commentaire)
            <p style="font-size:0.875rem; color:var(--text-main); line-height:1.6; margin:0; padding:12px; background:rgba(0,0,0,0.2); border-radius:8px;">
                {{ $dernierRejet->commentaire }}
            </p>
        @endif
    </div>

    {{-- ──────────────────────────────────────────────
         FORMULAIRE DE RE-SOUMISSION
         Envoie vers SubmissionController@resubmit
         Route : POST /employe/submissions/{id}/resubmit
         ────────────────────────────────────────────── --}}
    <div class="card" style="margin-bottom:28px; border-color:rgba(79,124,255,0.3);">

        <h3 style="font-family:'Syne',sans-serif; font-size:1rem; font-weight:700; margin:0 0 16px; display:flex; align-items:center; gap:8px;">
            {{-- Icône upload --}}
            <svg width="17" height="17" fill="none" stroke="var(--accent)" stroke-width="2" viewBox="0 0 24 24">
                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
            </svg>
            Soumettre le fichier corrigé
        </h3>

        {{--
            enctype="multipart/form-data" est OBLIGATOIRE pour les uploads de fichiers.
            Sans cet attribut, le fichier ne sera jamais transmis au serveur.
        --}}
        <form method="POST"
              action="{{ route('employe.submissions.resubmit', $submission) }}"
              enctype="multipart/form-data">
            @csrf {{-- Token de sécurité Laravel contre les attaques CSRF --}}

            <div style="margin-bottom:16px;">
                <label for="fichier">Fichier Excel corrigé (.xlsx, .xls, .csv)</label>

                {{--
                    Zone de dépôt de fichier stylisée.
                    L'input file réel est caché, le clic sur la zone le déclenche via JS.
                --}}
                <div id="drop-zone"
                     style="border:2px dashed var(--border); border-radius:10px; padding:32px; text-align:center; cursor:pointer; transition:border-color 0.2s;"
                     onclick="document.getElementById('fichier').click()"
                     ondragover="event.preventDefault(); this.style.borderColor='var(--accent)'"
                     ondragleave="this.style.borderColor='var(--border)'"
                     ondrop="handleDrop(event)">

                    {{-- Icône fichier Excel --}}
                    <svg width="32" height="32" fill="none" stroke="var(--text-muted)" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 10px;">
                        <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>

                    <p style="color:var(--text-muted); font-size:0.875rem; margin:0 0 4px;">
                        Glissez votre fichier ici ou <span style="color:var(--accent); font-weight:600;">cliquez pour parcourir</span>
                    </p>
                    <p id="file-name" style="font-size:0.8rem; color:var(--text-muted); margin:0;">
                        Formats acceptés : .xlsx, .xls, .csv — Max 20 Mo
                    </p>
                </div>

                {{-- Input file réel, caché visuellement --}}
                <input type="file"
                       id="fichier"
                       name="fichier"
                       accept=".xlsx,.xls,.csv"
                       style="display:none;"
                       onchange="updateFileName(this)">

                {{-- Affichage de l'erreur de validation Laravel si présente --}}
                @error('fichier')
                    <p style="color:var(--danger); font-size:0.8rem; margin-top:6px;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Boutons : Annuler (retour à la liste) ou Soumettre --}}
            <div style="display:flex; gap:12px; justify-content:flex-end;">
                <a href="{{ route('employe.submissions.index') }}" class="btn-ghost">
                    Annuler
                </a>
                <button type="submit" class="btn-primary">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
                    </svg>
                    Soumettre la correction v{{ $submission->version + 1 }}
                </button>
            </div>
        </form>
    </div>

@endif
{{-- Fin du bloc conditionnel EN_CORRECTION --}}

{{-- ──────────────────────────────────────────────────────────
     LIGNE 2 : Grille 2 colonnes
     Gauche (65%) : tableau des corrections
     Droite (35%)  : historique des révisions
     ────────────────────────────────────────────────────────── --}}
<div style="display:grid; grid-template-columns:1fr 380px; gap:20px; align-items:start;">

    {{-- ══════════════════════════════════════════════
         COLONNE GAUCHE : Tableau des corrections
         Données issues de staging_corrections pour
         la version courante de cette soumission.
         ══════════════════════════════════════════════ --}}
    <div class="card">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
            <h3 style="font-family:'Syne',sans-serif; font-size:1rem; font-weight:700; margin:0;">
                Corrections — Version {{ $submission->version }}
            </h3>
            {{-- Compteur de corrections --}}
            <span style="font-size:0.8rem; color:var(--text-muted);">
                {{ $corrections->total() }} ligne(s)
            </span>
        </div>

        @if($corrections->isEmpty())
            {{-- Aucune correction parsée — peut arriver si le fichier était vide --}}
            <div style="text-align:center; padding:40px; color:var(--text-muted);">
                <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 10px; opacity:0.4;">
                    <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
                </svg>
                <p style="font-size:0.875rem;">Aucune correction trouvée pour cette version.</p>
            </div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>         {{-- Numéro de ligne dans le fichier Excel --}}
                            <th>Table DB2</th>  {{-- Table cible dans DB2 --}}
                            <th>Clé primaire</th>
                            <th>Champ</th>      {{-- Colonne à modifier --}}
                            <th>Valeur</th>     {{-- Nouvelle valeur à appliquer --}}
                            <th>Statut push</th>{{-- PENDING / OK / ERREUR (après push DB2) --}}
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($corrections as $correction)
                            <tr>
                                {{-- Numéro de ligne du fichier Excel original --}}
                                <td style="color:var(--text-muted); font-size:0.8rem;">
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
                                <td style="font-weight:500;">{{ $correction->champ }}</td>
                                <td style="font-size:0.875rem;">{{ $correction->valeur_correction }}</td>
                                <td>
                                    {{--
                                        Badge de statut du push pour chaque ligne.
                                        PENDING = pas encore envoyé vers DB2
                                        OK      = correction appliquée avec succès
                                        ERREUR  = échec lors du push
                                    --}}
                                    @if($correction->push_statut === 'OK')
                                        <span class="badge badge-emerald">OK</span>
                                    @elseif($correction->push_statut === 'ERREUR')
                                        {{-- On affiche le message d'erreur au survol --}}
                                        <span class="badge badge-red" title="{{ $correction->push_message }}">
                                            Erreur
                                        </span>
                                    @else
                                        <span class="badge badge-gray">En attente</span>
                                    @endif
                                </td>
                            </tr>

                            {{-- Si la ligne est en erreur, on affiche le message dessous --}}
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

            {{-- Pagination Laravel (liens Précédent / Suivant) --}}
            @if($corrections->hasPages())
                <div style="margin-top:16px; display:flex; justify-content:center;">
                    {{ $corrections->links() }}
                </div>
            @endif
        @endif
    </div>
    {{-- Fin colonne gauche --}}

    {{-- ══════════════════════════════════════════════
         COLONNE DROITE : Historique des révisions
         Affiche toutes les décisions prises sur
         ce dossier, du plus récent au plus ancien.
         ══════════════════════════════════════════════ --}}
    <div style="display:flex; flex-direction:column; gap:16px;">

        {{-- Card : résumé statistique du push (si terminé) --}}
        @if(in_array($submission->statut, ['TERMINE', 'TERMINE_AVEC_ERREURS']))
            <div class="card" style="border-color:{{ $submission->statut === 'TERMINE' ? 'rgba(34,197,94,0.3)' : 'rgba(239,68,68,0.3)' }};">
                <h4 style="font-family:'Syne',sans-serif; font-size:0.9rem; font-weight:700; margin:0 0 14px;">
                    Résultat du push DB2
                </h4>
                {{-- Lignes OK et Lignes en erreur côte à côte --}}
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

        {{-- Card : historique des révisions --}}
        <div class="card">
            <h4 style="font-family:'Syne',sans-serif; font-size:0.9rem; font-weight:700; margin:0 0 16px;">
                Historique des révisions
            </h4>

            @if($reviews->isEmpty())
                <p style="font-size:0.875rem; color:var(--text-muted); text-align:center; padding:20px 0;">
                    Aucune révision encore effectuée.
                </p>
            @else
                {{-- Timeline verticale des décisions --}}
                <div style="display:flex; flex-direction:column; gap:0;">
                    @foreach($reviews as $review)
                        <div style="display:flex; gap:12px; padding-bottom:16px; position:relative;">

                            {{-- Ligne verticale de la timeline (sauf pour le dernier item) --}}
                            @if(! $loop->last)
                                <div style="position:absolute; left:15px; top:30px; bottom:0; width:1px; background:var(--border);"></div>
                            @endif

                            {{-- Icône de décision (check = approuvé, croix = rejeté) --}}
                            <div style="width:30px; height:30px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center;
                                        background:{{ $review->isApproved() ? 'rgba(34,197,94,0.15)' : 'rgba(239,68,68,0.15)' }};
                                        border:1px solid {{ $review->isApproved() ? 'rgba(34,197,94,0.3)' : 'rgba(239,68,68,0.3)' }};">
                                @if($review->isApproved())
                                    <svg width="13" height="13" fill="none" stroke="#4ade80" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path d="M5 13l4 4L19 7"/>
                                    </svg>
                                @else
                                    <svg width="13" height="13" fill="none" stroke="#f87171" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                @endif
                            </div>

                            {{-- Contenu de la révision --}}
                            <div style="flex:1; min-width:0;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
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
                                {{-- Commentaire du supérieur s'il y en a un --}}
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
            @endif
        </div>
        {{-- Fin card historique --}}

    </div>
    {{-- Fin colonne droite --}}

</div>
{{-- Fin grille 2 colonnes --}}

@endsection

{{-- ──────────────────────────────────────────────────────────
     JAVASCRIPT : Gestion de la zone de dépôt de fichier
     Uniquement pour le bloc re-soumission (EN_CORRECTION)
     ────────────────────────────────────────────────────────── --}}
@push('scripts')
<script>
    /**
     * Met à jour le texte de la zone de drop quand l'utilisateur
     * sélectionne un fichier via le bouton parcourir.
     * @param {HTMLInputElement} input
     */
    function updateFileName(input) {
        const label = document.getElementById('file-name');
        const zone  = document.getElementById('drop-zone');
        if (input.files && input.files[0]) {
            const file = input.files[0];
            // Affiche le nom et la taille du fichier sélectionné
            label.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' Mo)';
            label.style.color = 'var(--accent-light)';
            zone.style.borderColor = 'var(--accent)';
        }
    }

    /**
     * Gère le glisser-déposer de fichier sur la zone.
     * Simule la sélection de fichier via l'input hidden.
     * @param {DragEvent} event
     */
    function handleDrop(event) {
        event.preventDefault();
        const input = document.getElementById('fichier');
        const zone  = document.getElementById('drop-zone');
        if (event.dataTransfer.files.length) {
            // On injecte les fichiers droppés dans l'input file natif
            input.files = event.dataTransfer.files;
            updateFileName(input);
        }
        zone.style.borderColor = 'var(--border)';
    }
</script>
@endpush