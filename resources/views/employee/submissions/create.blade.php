{{--
    ============================================================
    VUE : employee/submissions/create.blade.php
    ------------------------------------------------------------
    Formulaire de soumission d'un nouveau fichier Excel.
    C'est ici que l'employé uploade ses corrections.

    Pas de variables transmises par SubmissionController@create,
    la méthode retourne simplement cette vue.

    Le formulaire soumet vers SubmissionController@store
    via POST /employe/submissions
    ============================================================
--}}

@extends('layouts.app')

@section('title', 'Nouveau dossier')
@section('page-title', 'Soumettre un fichier')

@section('content')

{{-- Lien retour --}}
<a href="{{ route('employe.submissions.index') }}"
   style="display:inline-flex; align-items:center; gap:6px; font-size:0.8rem; color:var(--text-muted); text-decoration:none; margin-bottom:24px;">
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <path d="M19 12H5M12 5l-7 7 7 7"/>
    </svg>
    Retour à mes dossiers
</a>

{{-- ──────────────────────────────────────────────────────────
     GRILLE 2 COLONNES
     Gauche (55%) : formulaire d'upload
     Droite (45%) : aide sur le format attendu
     ────────────────────────────────────────────────────────── --}}
<div style="display:grid; grid-template-columns:1fr 400px; gap:24px; align-items:start;">

    {{-- ══════════════════════════════════════════════
         COLONNE GAUCHE : Formulaire
         ══════════════════════════════════════════════ --}}
    <div class="card">

        <h2 style="font-family:'Syne',sans-serif; font-size:1.1rem; font-weight:700; margin:0 0 6px;">
            Nouveau dossier de corrections
        </h2>
        <p style="font-size:0.85rem; color:var(--text-muted); margin:0 0 24px;">
            Soumettez votre fichier Excel contenant les corrections à appliquer sur DB2.
            Les supérieurs seront notifiés automatiquement.
        </p>

        {{--
            enctype="multipart/form-data" OBLIGATOIRE pour les uploads.
            Sans cet attribut, le fichier n'est jamais envoyé au serveur,
            Laravel reçoit null et la validation échoue avec "required".
        --}}
        <form method="POST"
              action="{{ route('employe.submissions.store') }}"
              enctype="multipart/form-data">
            @csrf

            {{-- ── Zone de dépôt du fichier ── --}}
            <div style="margin-bottom:20px;">
                <label for="fichier" style="margin-bottom:8px; display:block;">
                    Fichier Excel
                    <span style="color:var(--danger);">*</span>
                </label>

                {{--
                    Zone drag & drop stylisée.
                    L'input file natif est caché (display:none).
                    Le clic sur la zone déclenche l'input via onclick.
                    Le drag over / drop sont gérés en JS en bas de page.
                --}}
                <div id="drop-zone"
                     onclick="document.getElementById('fichier').click()"
                     ondragover="event.preventDefault(); this.style.borderColor='var(--accent)'; this.style.background='rgba(79,124,255,0.05)'"
                     ondragleave="this.style.borderColor='var(--border)'; this.style.background='transparent'"
                     ondrop="handleDrop(event)"
                     style="border:2px dashed var(--border); border-radius:12px; padding:40px 20px; text-align:center; cursor:pointer; transition:all 0.2s;">

                    {{-- Icône upload --}}
                    <div id="drop-icon" style="margin-bottom:12px;">
                        <svg width="40" height="40" fill="none" stroke="var(--text-muted)" stroke-width="1.3" viewBox="0 0 24 24" style="margin:0 auto; opacity:0.5;">
                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
                        </svg>
                    </div>

                    <p style="font-size:0.9rem; color:var(--text-muted); margin:0 0 6px;">
                        Glissez votre fichier ici ou
                        <span style="color:var(--accent); font-weight:600;">cliquez pour parcourir</span>
                    </p>

                    {{-- Texte dynamique : affiche le nom du fichier une fois sélectionné --}}
                    <p id="file-info" style="font-size:0.78rem; color:var(--text-muted); margin:0;">
                        Formats acceptés : .xlsx, .xls, .csv &nbsp;·&nbsp; Taille max : 20 Mo
                    </p>
                </div>

                {{-- Input file réel caché --}}
                <input type="file"
                       id="fichier"
                       name="fichier"
                       accept=".xlsx,.xls,.csv"
                       style="display:none;"
                       onchange="updateFileInfo(this)">

                @error('fichier')
                    <p style="color:var(--danger); font-size:0.8rem; margin-top:8px; display:flex; align-items:center; gap:4px;">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
                        </svg>
                        {{ $message }}
                    </p>
                @enderror
            </div>

            {{-- ── Description facultative ── --}}
            <div style="margin-bottom:24px;">
                <label for="description">
                    Description
                    <span style="font-size:0.75rem; color:var(--text-muted); font-weight:400;">(facultatif)</span>
                </label>
                <textarea name="description"
                          id="description"
                          class="input"
                          rows="3"
                          maxlength="500"
                          placeholder="Ex: Corrections des noms clients pour la région Nord — Batch Avril 2026"
                          style="resize:vertical;">{{ old('description') }}</textarea>
                {{--
                    Compteur de caractères en temps réel.
                    Mis à jour par le JS en bas de page.
                --}}
                <p style="font-size:0.75rem; color:var(--text-muted); text-align:right; margin-top:4px;">
                    <span id="desc-count">0</span>/500 caractères
                </p>

                @error('description')
                    <p style="color:var(--danger); font-size:0.8rem; margin-top:4px;">{{ $message }}</p>
                @enderror
            </div>

            {{-- ── Boutons ── --}}
            <div style="display:flex; gap:12px; justify-content:flex-end;">
                <a href="{{ route('employe.submissions.index') }}" class="btn-ghost">
                    Annuler
                </a>
                <button type="submit" id="btn-submit" class="btn-primary">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
                    </svg>
                    Soumettre le dossier
                </button>
            </div>

        </form>
    </div>
    {{-- Fin colonne gauche --}}

    {{-- ══════════════════════════════════════════════
         COLONNE DROITE : Aide & format attendu
         Explique à l'employé comment structurer
         son fichier Excel pour qu'il soit accepté.
         ══════════════════════════════════════════════ --}}
    <div style="display:flex; flex-direction:column; gap:16px;">

        {{-- Card : Format attendu --}}
        <div class="card" style="border-color:rgba(79,124,255,0.3);">
            <h3 style="font-family:'Syne',sans-serif; font-size:0.95rem; font-weight:700; margin:0 0 14px; display:flex; align-items:center; gap:8px;">
                <svg width="16" height="16" fill="none" stroke="var(--accent)" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
                </svg>
                Format du fichier Excel
            </h3>

            <p style="font-size:0.8rem; color:var(--text-muted); margin:0 0 14px;">
                Votre fichier doit contenir exactement ces
                <strong style="color:var(--text-main);">4 colonnes</strong>
                en première ligne :
            </p>

            {{-- Tableau d'exemple des colonnes attendues --}}
            <div class="table-wrap" style="margin-bottom:14px;">
                <table style="font-size:0.78rem;">
                    <thead>
                        <tr>
                            <th>table_db2</th>
                            <th>cle_primaire</th>
                            <th>champ</th>
                            <th>valeur</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Ligne d'exemple --}}
                        <tr>
                            <td>
                                <code style="background:rgba(79,124,255,0.1); color:var(--accent-light); padding:1px 5px; border-radius:4px;">
                                    CLIENT
                                </code>
                            </td>
                            <td style="color:var(--text-muted);">ID=1042</td>
                            <td style="font-weight:500;">NOM</td>
                            <td>Dupont</td>
                        </tr>
                        <tr>
                            <td>
                                <code style="background:rgba(79,124,255,0.1); color:var(--accent-light); padding:1px 5px; border-radius:4px;">
                                    PRODUIT
                                </code>
                            </td>
                            <td style="color:var(--text-muted);">REF=P-007</td>
                            <td style="font-weight:500;">PRIX</td>
                            <td>149.99</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Description de chaque colonne --}}
            <div style="display:flex; flex-direction:column; gap:8px;">
                @foreach([
                    ['table_db2',    'Nom exact de la table DB2 à modifier (ex: CLIENT, COMMANDE)'],
                    ['cle_primaire', 'Identifiant de la ligne à modifier (ex: ID=1042, REF=P-007)'],
                    ['champ',        'Nom exact de la colonne à modifier (ex: NOM, PRIX)'],
                    ['valeur',       'Nouvelle valeur à appliquer sur cette colonne'],
                ] as [$col, $desc])
                    <div style="display:flex; gap:8px; align-items:flex-start;">
                        <code style="background:rgba(79,124,255,0.1); color:var(--accent-light); padding:2px 7px; border-radius:5px; font-size:0.75rem; flex-shrink:0;">
                            {{ $col }}
                        </code>
                        <span style="font-size:0.78rem; color:var(--text-muted); line-height:1.5;">
                            {{ $desc }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Card : Workflow après soumission --}}
        <div class="card">
            <h3 style="font-family:'Syne',sans-serif; font-size:0.95rem; font-weight:700; margin:0 0 14px; display:flex; align-items:center; gap:8px;">
                <svg width="16" height="16" fill="none" stroke="var(--text-muted)" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Ce qui se passe ensuite
            </h3>

            {{-- Timeline du workflow --}}
            <div style="display:flex; flex-direction:column; gap:0;">
                @foreach([
                    ['#fbbf24', 'Votre dossier est soumis', 'Les supérieurs reçoivent une notification.'],
                    ['#7b9fff', 'Révision en cours', 'Un supérieur examine vos corrections.'],
                    ['#4ade80', 'Approbation', 'Les corrections sont appliquées automatiquement sur DB2.'],
                    ['#fb923c', 'Si rejeté', 'Vous recevez un commentaire et pouvez re-soumettre.'],
                ] as $i => [$color, $title, $desc])
                    <div style="display:flex; gap:12px; padding-bottom:{{ $i < 3 ? '14px' : '0' }}; position:relative;">
                        {{-- Ligne verticale de connexion entre les étapes --}}
                        @if($i < 3)
                            <div style="position:absolute; left:7px; top:18px; bottom:0; width:1px; background:var(--border);"></div>
                        @endif
                        {{-- Point coloré --}}
                        <div style="width:16px; height:16px; border-radius:50%; background:{{ $color }}; flex-shrink:0; margin-top:2px; opacity:0.8;"></div>
                        <div>
                            <p style="font-size:0.8rem; font-weight:600; margin:0 0 2px; color:var(--text-main);">{{ $title }}</p>
                            <p style="font-size:0.75rem; color:var(--text-muted); margin:0;">{{ $desc }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    </div>
    {{-- Fin colonne droite --}}

</div>

@endsection

@push('scripts')
<script>
    /**
     * Met à jour la zone de drop visuellement quand un fichier
     * est sélectionné via le bouton "parcourir".
     * @param {HTMLInputElement} input
     */
    function updateFileInfo(input) {
        const info = document.getElementById('file-info');
        const zone = document.getElementById('drop-zone');
        const icon = document.getElementById('drop-icon');

        if (input.files && input.files[0]) {
            const file = input.files[0];
            const sizeMo = (file.size / 1024 / 1024).toFixed(2);

            // Mise à jour du texte d'info
            info.innerHTML = `<strong style="color:var(--accent-light);">${file.name}</strong> &nbsp;·&nbsp; ${sizeMo} Mo`;

            // Changement visuel de la zone : bordure bleue + fond légèrement teinté
            zone.style.borderColor = 'var(--accent)';
            zone.style.background  = 'rgba(79,124,255,0.04)';

            // Remplacement de l'icône par un check vert
            icon.innerHTML = `
                <div style="width:44px; height:44px; border-radius:50%; background:rgba(34,197,94,0.15); display:flex; align-items:center; justify-content:center; margin:0 auto;">
                    <svg width="22" height="22" fill="none" stroke="#4ade80" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
            `;
        }
    }

    /**
     * Gère le glisser-déposer de fichier sur la zone.
     * On injecte le fichier droppé dans l'input natif caché
     * pour que Laravel le reçoive normalement via $request->file('fichier').
     * @param {DragEvent} event
     */
    function handleDrop(event) {
        event.preventDefault();
        const input = document.getElementById('fichier');
        const zone  = document.getElementById('drop-zone');

        if (event.dataTransfer.files.length) {
            input.files = event.dataTransfer.files;
            updateFileInfo(input);
        }

        zone.style.background = 'rgba(79,124,255,0.04)';
    }

    /**
     * Compteur de caractères pour le champ description.
     * Se met à jour à chaque frappe.
     */
    const descTextarea = document.getElementById('description');
    const descCount    = document.getElementById('desc-count');

    // Initialisation au cas où old() a prérempli le champ
    descCount.textContent = descTextarea.value.length;

    descTextarea.addEventListener('input', function () {
        descCount.textContent = this.value.length;
        // Passe en orange si on approche de la limite
        descCount.style.color = this.value.length > 450 ? '#fb923c' : 'var(--text-muted)';
    });

    /**
     * Désactive le bouton submit pendant l'envoi pour éviter
     * les double-soumissions (le parsing Excel peut prendre
     * quelques secondes selon la taille du fichier).
     */
    document.querySelector('form').addEventListener('submit', function () {
        const btn = document.getElementById('btn-submit');
        btn.disabled = true;
        btn.innerHTML = `
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="animation:spin 1s linear infinite;">
                <path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Envoi en cours...
        `;
    });
</script>

{{-- Animation spin pour l'icône de chargement --}}
<style>
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
@endpush