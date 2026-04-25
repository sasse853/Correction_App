{{--
    ============================================================
    VUE : admin/users/index.blade.php
    ------------------------------------------------------------
    Liste paginée de tous les utilisateurs avec filtres.

    Variables transmises par AdminController@users :
      - $users → LengthAwarePaginator (paginé par 15)
                 chaque user a la relation roles chargée
      - $roles → Collection de tous les rôles Spatie
                 (pour le menu déroulant de filtre)

    Filtres disponibles via GET :
      ?role=employe
      ?status=active | inactive
      ?search=nom_ou_email
    ============================================================
--}}

@extends('layouts.app')

@section('title', 'Gestion des utilisateurs')
@section('page-title', 'Utilisateurs')

@section('content')

{{-- ──────────────────────────────────────────────────────────
     EN-TÊTE : Titre + bouton créer
     ────────────────────────────────────────────────────────── --}}
<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px;">
    <div>
        <h2 style="font-family:'Syne',sans-serif; font-size:1.3rem; font-weight:700; margin:0 0 4px;">
            Gestion des utilisateurs
        </h2>
        <p style="font-size:0.85rem; color:var(--text-muted); margin:0;">
            Créez, modifiez et gérez les accès à l'application
        </p>
    </div>
    <a href="{{ route('admin.users.create') }}" class="btn-primary">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Créer un utilisateur
    </a>
</div>

{{-- ──────────────────────────────────────────────────────────
     BLOC FILTRES
     ────────────────────────────────────────────────────────── --}}
<div class="card" style="margin-bottom:20px;">
    <form method="GET" action="{{ request()->url() }}">
        <div style="display:grid; grid-template-columns:1fr 1fr 2fr auto; gap:12px; align-items:flex-end;">

            {{-- Filtre par rôle --}}
            <div>
                <label for="role">Rôle</label>
                <select name="role" id="role" class="input">
                    <option value="">Tous les rôles</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}" {{ request('role') == $role->name ? 'selected' : '' }}>

                            {{ ucfirst($role->name) }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Filtre par statut --}}
            <div>
                <label for="status">Statut</label>
                <select name="status" id="status" class="input">
                    <option value="">Tous</option>
                    <option value="active"   {{ request('status') == 'active'   ? 'selected' : '' }}>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>
                </select>
            </div>

            {{-- Recherche par nom ou email --}}
            <div>
                <label for="search">Recherche</label>
                <input type="text"
                       name="search"
                       id="search"
                       class="input"
                       placeholder="Nom ou adresse email..."
                       value="{{ request('search') }}">
            </div>

            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn-primary">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                    </svg>
                    Filtrer
                </button>
                <a href="{{ request()->url() }}" class="btn-ghost">✕</a>
            </div>

        </div>
    </form>
</div>

{{-- ──────────────────────────────────────────────────────────
     TABLEAU DES UTILISATEURS
     ────────────────────────────────────────────────────────── --}}
<div class="card">

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
        <h3 style="font-family:'Syne',sans-serif; font-size:1rem; font-weight:700; margin:0;">
            Comptes utilisateurs
        </h3>
        <span style="font-size:0.8rem; color:var(--text-muted);">
            {{ $users->total() }} utilisateur(s)
        </span>
    </div>

    @if($users->isEmpty())
        <div style="text-align:center; padding:48px 20px; color:var(--text-muted);">
            <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24" style="margin:0 auto 12px; opacity:0.3;">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
            </svg>
            <p style="font-size:0.9rem; margin:0;">Aucun utilisateur trouvé.</p>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Utilisateur</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th>Créé le</th>
                        <th>Créé par</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        <tr style="{{ ! $user->is_active ? 'opacity:0.6;' : '' }}">

                            {{-- Avatar + nom + email --}}
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    {{-- Avatar avec initiale --}}
                                    <div style="width:34px; height:34px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-family:'Syne',sans-serif; font-weight:700; font-size:0.85rem; color:white;
                                                background:{{ $user->is_active ? 'linear-gradient(135deg,var(--accent),var(--accent-light))' : 'var(--border)' }};">
                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <p style="font-size:0.875rem; font-weight:600; margin:0;">
                                            {{ $user->name }}
                                            {{-- Badge "C'est moi" pour que l'admin se repère --}}
                                            @if($user->id === auth()->id())
                                                <span style="font-size:0.7rem; color:var(--accent); margin-left:4px;">(vous)</span>
                                            @endif
                                        </p>
                                        <p style="font-size:0.76rem; color:var(--text-muted); margin:0;">
                                            {{ $user->email }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            {{-- Rôle via Spatie --}}
                            <td>
                                @php
                                    $roleName = $user->getRoleNames()->first();
                                    $roleColor = match($roleName) {
                                        'admin'     => 'badge-red',
                                        'superieur' => 'badge-blue',
                                        'employe'   => 'badge-green',
                                        default     => 'badge-gray',
                                    };
                                @endphp
                                @if($roleName)
                                    <span class="badge {{ $roleColor }}">
                                        {{ $user->getRoleLabel() }}
                                    </span>
                                @else
                                    <span style="font-size:0.8rem; color:var(--text-muted);">—</span>
                                @endif
                            </td>

                            {{-- Statut actif / inactif --}}
                            <td>
                                @if($user->is_active)
                                    <span class="badge badge-emerald">Actif</span>
                                @else
                                    <span class="badge badge-gray">Inactif</span>
                                @endif
                            </td>

                            {{-- Date de création --}}
                            <td style="font-size:0.8rem; color:var(--text-muted); white-space:nowrap;">
                                {{ $user->created_at->format('d/m/Y') }}
                            </td>

                            {{-- Admin créateur du compte --}}
                            <td style="font-size:0.8rem; color:var(--text-muted);">
                                {{ $user->creator?->name ?? '—' }}
                            </td>

                            {{-- Actions : modifier + activer/désactiver --}}
                            <td>
                                <div style="display:flex; align-items:center; gap:8px;">

                                    {{-- Bouton Modifier --}}
                                    <a href="{{ route('admin.users.edit', $user) }}"
                                       class="btn-ghost"
                                       style="padding:5px 10px; font-size:0.78rem;">
                                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                        Modifier
                                    </a>

                                    {{--
                                        Bouton activer/désactiver.
                                        Désactivé si c'est le compte de l'admin lui-même
                                        (on ne peut pas se bloquer son propre accès).
                                        Le controller gère aussi cette sécurité côté serveur.
                                    --}}
                                    @if($user->id !== auth()->id())
                                        <form method="POST"
                                              action="{{ route('admin.users.toggle', $user) }}"
                                              style="display:inline;">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    style="padding:5px 10px; font-size:0.78rem; border-radius:7px; border:1px solid; cursor:pointer; font-family:inherit; transition:all 0.18s;
                                                           {{ $user->is_active
                                                               ? 'background:rgba(239,68,68,0.1); color:#f87171; border-color:rgba(239,68,68,0.3);'
                                                               : 'background:rgba(34,197,94,0.1); color:#4ade80; border-color:rgba(34,197,94,0.3);' }}"
                                                    onclick="return confirm('{{ $user->is_active ? 'Désactiver' : 'Réactiver' }} le compte de {{ $user->name }} ?')">
                                                {{ $user->is_active ? 'Désactiver' : 'Réactiver' }}
                                            </button>
                                        </form>
                                    @endif

                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div style="margin-top:20px; display:flex; justify-content:center;">
                {{ $users->links() }}
            </div>
        @endif

    @endif

</div>

@endsection