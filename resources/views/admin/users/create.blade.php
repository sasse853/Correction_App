{{--
    ============================================================
    VUE : admin/users/create.blade.php
    ------------------------------------------------------------
    Formulaire de création d'un nouvel utilisateur.

    Variables transmises par AdminController@createUser :
      - $roles → Collection de tous les rôles Spatie
                 (pour le menu déroulant de sélection)

    Soumet vers AdminController@storeUser
    via POST /admin/users
    ============================================================
--}}

@extends('layouts.app')

@section('title', 'Créer un utilisateur')
@section('page-title', 'Nouvel utilisateur')

@section('content')

<a href="{{ route('admin.users.index') }}"
   style="display:inline-flex; align-items:center; gap:6px; font-size:0.8rem; color:var(--text-muted); text-decoration:none; margin-bottom:24px;">
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <path d="M19 12H5M12 5l-7 7 7 7"/>
    </svg>
    Retour à la liste
</a>

<div style="max-width:600px;">
    <div class="card">

        <h2 style="font-family:'Syne',sans-serif; font-size:1.1rem; font-weight:700; margin:0 0 6px;">
            Créer un nouveau compte
        </h2>
        <p style="font-size:0.85rem; color:var(--text-muted); margin:0 0 24px;">
            Le nouveau compte sera actif immédiatement après création.
        </p>

        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf

            {{-- ── Nom complet ── --}}
            <div style="margin-bottom:18px;">
                <label for="name">
                    Nom complet <span style="color:var(--danger);">*</span>
                </label>
                <input type="text"
                       name="name"
                       id="name"
                       class="input {{ $errors->has('name') ? 'border-red' : '' }}"
                       value="{{ old('name') }}"
                       placeholder="Ex: Marie Dupont"
                       maxlength="100"
                       required>
                @error('name')
                    <p style="color:var(--danger); font-size:0.8rem; margin-top:5px;">{{ $message }}</p>
                @enderror
            </div>

            {{-- ── Adresse email ── --}}
            <div style="margin-bottom:18px;">
                <label for="email">
                    Adresse email <span style="color:var(--danger);">*</span>
                </label>
                <input type="email"
                       name="email"
                       id="email"
                       class="input"
                       value="{{ old('email') }}"
                       placeholder="marie.dupont@entreprise.com"
                       maxlength="150"
                       required>
                @error('email')
                    <p style="color:var(--danger); font-size:0.8rem; margin-top:5px;">{{ $message }}</p>
                @enderror
            </div>

            {{-- ── Rôle ── --}}
            <div style="margin-bottom:18px;">
                <label for="role">
                    Rôle <span style="color:var(--danger);">*</span>
                </label>
                <select name="role" id="role" class="input" required>
                    <option value="">Sélectionner un rôle...</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}" {{ old('role') === $role->name ? 'selected' : '' }}>
                            {{-- Affichage lisible du rôle --}}
                            @if($role->name === 'employe')   Employé
                            @elseif($role->name === 'superieur') Supérieur
                            @elseif($role->name === 'admin') Administrateur
                            @else {{ ucfirst($role->name) }}
                            @endif
                        </option>
                    @endforeach
                </select>
                @error('role')
                    <p style="color:var(--danger); font-size:0.8rem; margin-top:5px;">{{ $message }}</p>
                @enderror
            </div>

            {{-- ── Mot de passe ── --}}
            <div style="margin-bottom:18px;">
                <label for="password">
                    Mot de passe <span style="color:var(--danger);">*</span>
                </label>
                <div style="position:relative;">
                    <input type="password"
                           name="password"
                           id="password"
                           class="input"
                           placeholder="Minimum 8 caractères"
                           required
                           style="padding-right:44px;">
                    {{-- Bouton toggle visibilité mot de passe --}}
                    <button type="button"
                            onclick="togglePassword('password', 'eye-password')"
                            style="position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--text-muted); padding:0;">
                        <svg id="eye-password" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
                {{--
                    Règles de complexité du mot de passe affichées clairement
                    pour que l'admin ne soit pas surpris par les erreurs de validation.
                    Ces règles correspondent exactement à Password::min(8)->mixedCase()->numbers()->symbols()
                    défini dans AdminController@storeUser.
                --}}
                <div style="margin-top:8px; display:flex; flex-wrap:wrap; gap:6px;">
                    @foreach(['8 caractères min.', 'Majuscule', 'Chiffre', 'Symbole (!@#...)'] as $rule)
                        <span style="font-size:0.72rem; padding:2px 8px; background:rgba(255,255,255,0.04); border:1px solid var(--border); border-radius:20px; color:var(--text-muted);">
                            {{ $rule }}
                        </span>
                    @endforeach
                </div>
                @error('password')
                    <p style="color:var(--danger); font-size:0.8rem; margin-top:5px;">{{ $message }}</p>
                @enderror
            </div>

            {{-- ── Confirmation du mot de passe ── --}}
            <div style="margin-bottom:28px;">
                <label for="password_confirmation">
                    Confirmer le mot de passe <span style="color:var(--danger);">*</span>
                </label>
                <div style="position:relative;">
                    <input type="password"
                           name="password_confirmation"
                           id="password_confirmation"
                           class="input"
                           placeholder="Répétez le mot de passe"
                           required
                           style="padding-right:44px;">
                    <button type="button"
                            onclick="togglePassword('password_confirmation', 'eye-confirm')"
                            style="position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--text-muted); padding:0;">
                        <svg id="eye-confirm" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div style="display:flex; gap:12px; justify-content:flex-end;">
                <a href="{{ route('admin.users.index') }}" class="btn-ghost">
                    Annuler
                </a>
                <button type="submit" class="btn-primary">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                        <circle cx="8.5" cy="7" r="4"/>
                        <line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>
                    </svg>
                    Créer le compte
                </button>
            </div>

        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
    /**
     * Bascule la visibilité d'un champ mot de passe.
     * @param {string} inputId  - ID de l'input password
     * @param {string} iconId   - ID de l'icône SVG à changer
     */
    function togglePassword(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon  = document.getElementById(iconId);
        const isPassword = input.type === 'password';

        input.type = isPassword ? 'text' : 'password';

        // Change l'icône : œil ouvert → œil barré et vice-versa
        icon.innerHTML = isPassword
            ? `<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`
            : `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
    }
</script>
@endpush