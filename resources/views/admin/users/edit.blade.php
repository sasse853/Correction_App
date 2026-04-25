{{--
    ============================================================
    VUE : admin/users/edit.blade.php
    ------------------------------------------------------------
    Formulaire de modification d'un utilisateur existant.

    Variables transmises par AdminController@editUser :
      - $user  → objet User à modifier (avec ses rôles chargés)
      - $roles → Collection de tous les rôles Spatie

    Soumet vers AdminController@updateUser
    via PUT /admin/users/{user}

    Différences avec create.blade.php :
      - Les champs sont préremplis avec les valeurs actuelles
      - Le mot de passe est FACULTATIF (laissé vide = pas de changement)
      - L'email est unique SAUF pour cet utilisateur lui-même
        (règle 'unique:users,email,{id}' dans le controller)
    ============================================================
--}}

@extends('layouts.app')

@section('title', 'Modifier — ' . $user->name)
@section('page-title', 'Modifier le compte')

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

        {{-- En-tête avec avatar et infos actuelles --}}
        <div style="display:flex; align-items:center; gap:14px; margin-bottom:24px; padding-bottom:20px; border-bottom:1px solid var(--border);">
            <div style="width:44px; height:44px; border-radius:50%; background:linear-gradient(135deg,var(--accent),var(--accent-light)); display:flex; align-items:center; justify-content:center; font-family:'Syne',sans-serif; font-weight:700; font-size:1.1rem; color:white; flex-shrink:0;">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <div>
                <h2 style="font-family:'Syne',sans-serif; font-size:1.05rem; font-weight:700; margin:0 0 3px;">
                    {{ $user->name }}
                </h2>
                <p style="font-size:0.8rem; color:var(--text-muted); margin:0;">
                    Compte créé le {{ $user->created_at->format('d/m/Y') }}
                    @if($user->creator)
                        · par {{ $user->creator->name }}
                    @endif
                </p>
            </div>
            {{-- Badge statut actuel --}}
            @if($user->is_active)
                <span class="badge badge-emerald" style="margin-left:auto;">Actif</span>
            @else
                <span class="badge badge-gray" style="margin-left:auto;">Inactif</span>
            @endif
        </div>

        {{--
            PUT via POST + @method('PUT') car les formulaires HTML
            ne supportent nativement que GET et POST.
            Laravel détecte le champ _method pour router vers PUT.
        --}}
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PUT')

            {{-- ── Nom complet ── --}}
            <div style="margin-bottom:18px;">
                <label for="name">
                    Nom complet <span style="color:var(--danger);">*</span>
                </label>
                <input type="text"
                       name="name"
                       id="name"
                       class="input"
                       {{--
                           old('name') permet de conserver la valeur saisie
                           si la validation échoue et que la page se recharge.
                           Si old() est vide (premier chargement), on affiche
                           la valeur actuelle de l'utilisateur.
                       --}}
                       value="{{ old('name', $user->name) }}"
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
                       value="{{ old('email', $user->email) }}"
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
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}"
                            {{--
                                On pré-sélectionne :
                                - old('role') si une erreur de validation a eu lieu
                                - sinon le rôle actuel de l'utilisateur
                            --}}
                            {{ old('role', $user->getRoleNames()->first()) === $role->name ? 'selected' : '' }}>
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

            {{-- ── Nouveau mot de passe (facultatif) ── --}}
            <div style="margin-bottom:18px;">
                <label for="password">
                    Nouveau mot de passe
                    <span style="font-size:0.75rem; color:var(--text-muted); font-weight:400;">
                        (laisser vide pour ne pas modifier)
                    </span>
                </label>
                <div style="position:relative;">
                    <input type="password"
                           name="password"
                           id="password"
                           class="input"
                           placeholder="Laisser vide pour conserver l'actuel"
                           style="padding-right:44px;">
                    <button type="button"
                            onclick="togglePassword('password', 'eye-password')"
                            style="position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--text-muted); padding:0;">
                        <svg id="eye-password" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
                @error('password')
                    <p style="color:var(--danger); font-size:0.8rem; margin-top:5px;">{{ $message }}</p>
                @enderror
            </div>

            {{-- ── Confirmation nouveau mot de passe ── --}}
            <div style="margin-bottom:28px;">
                <label for="password_confirmation">
                    Confirmer le nouveau mot de passe
                </label>
                <div style="position:relative;">
                    <input type="password"
                           name="password_confirmation"
                           id="password_confirmation"
                           class="input"
                           placeholder="Répétez le nouveau mot de passe"
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
                        <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v14a2 2 0 01-2 2z"/>
                        <polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                    </svg>
                    Enregistrer les modifications
                </button>
            </div>

        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
    function togglePassword(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon  = document.getElementById(iconId);
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        icon.innerHTML = isPassword
            ? `<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`
            : `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
    }
</script>
@endpush