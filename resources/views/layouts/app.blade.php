<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"> 
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'DataCorrection') — Plateforme de Correction</title>

    {{-- Google Fonts : Syne (titres) + DM Sans (corps) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    {{-- Vite compile Tailwind + app.css --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* ── Variables globales ───────────────────────────── */
        :root {
            --bg-main:    #0f1117;
            --bg-card:    #181c27;
            --bg-sidebar: #13161f;
            --accent:     #4f7cff;
            --accent-light: #7b9fff;
            --success:    #22c55e;
            --warning:    #f59e0b;
            --danger:     #ef4444;
            --text-main:  #e8eaf0;
            --text-muted: #7a8099;
            --border:     #252a3a;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-main);
        }

        h1, h2, h3, .font-display {
            font-family: 'Syne', sans-serif;
        }

        /* ── Sidebar ─────────────────────────────────────── */
        .sidebar {
            width: 260px;
            min-height: 100vh;
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border);
            position: fixed;
            top: 0; left: 0;
            display: flex;
            flex-direction: column;
            z-index: 40;
        }

        .sidebar-logo {
            padding: 28px 24px 20px;
            border-bottom: 1px solid var(--border);
        }

        .sidebar-logo span {
            font-family: 'Syne', sans-serif;
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 20px;
            margin: 3px 10px;
            border-radius: 10px;
            color: var(--text-muted);
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.18s ease;
        }

        .nav-item:hover {
            background: rgba(79, 124, 255, 0.1);
            color: var(--accent-light);
        }

        .nav-item.active {
            background: rgba(79, 124, 255, 0.15);
            color: var(--accent-light);
            border-left: 3px solid var(--accent);
            padding-left: 17px;
        }

        .nav-section-title {
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--text-muted);
            padding: 18px 24px 6px;
        }

        /* ── Topbar ──────────────────────────────────────── */
        .topbar {
            margin-left: 260px;
            height: 64px;
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            position: sticky;
            top: 0;
            z-index: 30;
        }

        /* ── Main content ────────────────────────────────── */
        .main-content {
            margin-left: 260px;
            padding: 32px 28px;
            min-height: calc(100vh - 64px);
        }

        /* ── Cards ───────────────────────────────────────── */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 24px;
        }

        /* ── Badges statut ───────────────────────────────── */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        .badge-yellow  { background: rgba(245,158,11,0.15);  color: #fbbf24; }
        .badge-blue    { background: rgba(79,124,255,0.15);   color: #7b9fff; }
        .badge-orange  { background: rgba(249,115,22,0.15);   color: #fb923c; }
        .badge-green   { background: rgba(34,197,94,0.15);    color: #4ade80; }
        .badge-emerald { background: rgba(16,185,129,0.15);   color: #34d399; }
        .badge-red     { background: rgba(239,68,68,0.15);    color: #f87171; }
        .badge-gray    { background: rgba(122,128,153,0.15);  color: #9ca3af; }

        /* ── Boutons ─────────────────────────────────────── */
        .btn-primary {
            background: var(--accent);
            color: #fff;
            padding: 9px 20px;
            border-radius: 9px;
            font-size: 0.875rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.18s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }
        .btn-primary:hover { background: #3d6be0; transform: translateY(-1px); }

        .btn-success {
            background: rgba(34,197,94,0.15);
            color: #4ade80;
            border: 1px solid rgba(34,197,94,0.3);
            padding: 9px 20px;
            border-radius: 9px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.18s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }
        .btn-success:hover { background: rgba(34,197,94,0.25); }

        .btn-danger {
            background: rgba(239,68,68,0.15);
            color: #f87171;
            border: 1px solid rgba(239,68,68,0.3);
            padding: 9px 20px;
            border-radius: 9px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.18s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }
        .btn-danger:hover { background: rgba(239,68,68,0.25); }

        .btn-ghost {
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border);
            padding: 9px 20px;
            border-radius: 9px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.18s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }
        .btn-ghost:hover { color: var(--text-main); border-color: #3a4060; }

        /* ── Inputs ──────────────────────────────────────── */
        .input {
            width: 100%;
            background: var(--bg-main);
            border: 1px solid var(--border);
            border-radius: 9px;
            padding: 10px 14px;
            color: var(--text-main);
            font-size: 0.875rem;
            font-family: 'DM Sans', sans-serif;
            transition: border-color 0.18s;
            outline: none;
        }
        .input:focus { border-color: var(--accent); }
        .input::placeholder { color: var(--text-muted); }

        label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 6px;
            letter-spacing: 0.3px;
        }

        /* ── Tables ──────────────────────────────────────── */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            text-align: left;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--text-muted);
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
        }
        tbody td {
            padding: 13px 16px;
            font-size: 0.875rem;
            border-bottom: 1px solid var(--border);
            color: var(--text-main);
        }
        tbody tr:hover { background: rgba(255,255,255,0.02); }
        tbody tr:last-child td { border-bottom: none; }

        /* ── Alertes flash ───────────────────────────────── */
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: rgba(34,197,94,0.12);  border: 1px solid rgba(34,197,94,0.25);  color: #4ade80; }
        .alert-error   { background: rgba(239,68,68,0.12);  border: 1px solid rgba(239,68,68,0.25);  color: #f87171; }
        .alert-warning { background: rgba(245,158,11,0.12); border: 1px solid rgba(245,158,11,0.25); color: #fbbf24; }

        /* ── Notification bell ───────────────────────────── */
        .notif-dot {
            width: 8px; height: 8px;
            background: var(--danger);
            border-radius: 50%;
            position: absolute;
            top: 0; right: 0;
        }
    </style>
</head>
<body>

{{-- ═══════════════════════════════════════════════════════
     SIDEBAR
     ═══════════════════════════════════════════════════════ --}}
<aside class="sidebar">

    <div class="sidebar-logo">
        <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:32px;height:32px;background:linear-gradient(135deg,#4f7cff,#7b9fff);border-radius:8px;display:flex;align-items:center;justify-content:center;">
                <svg width="18" height="18" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24">
                    <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <span>DataCorrection</span>
        </div>
        <div style="margin-top:8px;font-size:0.72rem;color:var(--text-muted);letter-spacing:0.5px;">
            {{ auth()->user()->getRoleLabel() }} — {{ auth()->user()->name }}
        </div>
    </div>

    <nav style="flex:1;padding:12px 0;overflow-y:auto;">

        {{-- ── ADMIN ─────────────────────────────────── --}}
        @role('admin')
        <div class="nav-section-title">Administration</div>

        <a href="{{ route('admin.dashboard') }}"
           class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
            </svg>
            Tableau de bord
        </a>

        <a href="{{ route('admin.users.index') }}"
           class="nav-item {{ request()->routeIs('admin.users*') ? 'active' : '' }}">
            <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
            </svg>
            Utilisateurs
        </a>

        <a href="{{ route('admin.submissions.index') }}"
           class="nav-item {{ request()->routeIs('admin.submissions*') ? 'active' : '' }}">
            <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Tous les dossiers
        </a>

        {{--
            Audit Logs : accessible à l'admin UNIQUEMENT.
            Supprimé du bloc supérieur — un supérieur ne doit pas
            avoir accès à l'historique de connexion des autres utilisateurs.
        --}}
        <a href="{{ route('audit.index') }}"
           class="nav-item {{ request()->routeIs('audit*') ? 'active' : '' }}">
            <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            Audit Logs
        </a>
        @endrole

        {{-- ── SUPÉRIEUR ──────────────────────────────── --}}
        @role('superieur')
        <div class="nav-section-title">Révision</div>

        <a href="{{ route('superieur.dashboard') }}"
           class="nav-item {{ request()->routeIs('superieur.dashboard') ? 'active' : '' }}">
            <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
            </svg>
            Tableau de bord
        </a>

        <a href="{{ route('superieur.submissions.index') }}"
           class="nav-item {{ request()->routeIs('superieur.submissions.index') ? 'active' : '' }}">
            <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Tous les dossiers
        </a>

        {{--
            PAS de lien Audit Logs ici — le supérieur n'a pas
            accès au journal d'audit. Restreint à l'admin uniquement.
        --}}
        @endrole

        {{-- ── EMPLOYÉ ─────────────────────────────────── --}}
        @role('employe')
        <div class="nav-section-title">Mes dossiers</div>

        <a href="{{ route('employe.dashboard') }}"
           class="nav-item {{ request()->routeIs('employe.dashboard') ? 'active' : '' }}">
            <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
            </svg>
            Tableau de bord
        </a>

        <a href="{{ route('employe.submissions.create') }}"
           class="nav-item {{ request()->routeIs('employe.submissions.create') ? 'active' : '' }}">
            <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M12 4v16m8-8H4"/>
            </svg>
            Nouveau dossier
        </a>

        <a href="{{ route('employe.submissions.index') }}"
           class="nav-item {{ request()->routeIs('employe.submissions.index') ? 'active' : '' }}">
            <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Mes dossiers
        </a>
        @endrole

    </nav>

    <div style="padding:16px;border-top:1px solid var(--border);">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="nav-item" style="width:100%;border:none;background:none;cursor:pointer;text-align:left;">
                <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Déconnexion
            </button>
        </form>
    </div>
</aside>

{{-- ═══════════════════════════════════════════════
     TOPBAR
     ═══════════════════════════════════════════════ --}}
<header class="topbar">
    <h1 style="font-size:1.05rem;font-weight:700;font-family:'Syne',sans-serif;letter-spacing:-0.3px;">
        @yield('page-title', 'Tableau de bord')
    </h1>

    <div style="display:flex;align-items:center;gap:16px;">
        <div style="position:relative;">
            <button style="background:var(--bg-main);border:1px solid var(--border);border-radius:9px;width:38px;height:38px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--text-muted);">
                <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/>
                </svg>
            </button>
            @if(auth()->user()->unreadNotifications->count() > 0)
                <span class="notif-dot"></span>
            @endif
        </div>

        <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:36px;height:36px;background:linear-gradient(135deg,var(--accent),var(--accent-light));border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-weight:700;font-size:0.85rem;color:white;">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
            <div style="line-height:1.3;">
                <div style="font-size:0.8rem;font-weight:600;">{{ auth()->user()->name }}</div>
                <div style="font-size:0.7rem;color:var(--text-muted);">{{ auth()->user()->getRoleLabel() }}</div>
            </div>
        </div>
    </div>
</header>

{{-- ═══════════════════════════════════════════════
     CONTENU PRINCIPAL
     ═══════════════════════════════════════════════ --}}
<main class="main-content">

    @if(session('success'))
        <div class="alert alert-success">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    @if(session('warning'))
        <div class="alert alert-warning">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            {{ session('warning') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-error">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <div>
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        </div>
    @endif

    @yield('content')

    {{-- Zone pour les scripts spécifiques à chaque page --}}
    @stack('scripts')

</main>

</body>
</html>