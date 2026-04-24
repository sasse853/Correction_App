<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — DataCorrection</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --bg-main:    #0f1117;
            --bg-card:    #181c27;
            --accent:     #4f7cff;
            --accent-light: #7b9fff;
            --text-main:  #e8eaf0;
            --text-muted: #7a8099;
            --border:     #252a3a;
            --danger:     #ef4444;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            /* Grille décorative en fond */
            background-image:
                linear-gradient(rgba(79,124,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(79,124,255,0.03) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }

        /* Logo centré en haut */
        .login-logo {
            text-align: center;
            margin-bottom: 36px;
        }

        .login-logo .icon {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
            box-shadow: 0 0 30px rgba(79,124,255,0.3);
        }

        .login-logo h1 {
            font-family: 'Syne', sans-serif;
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, var(--text-main), var(--text-muted));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .login-logo p {
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-top: 4px;
        }

        /* Card du formulaire */
        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 32px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 7px;
            letter-spacing: 0.3px;
        }

        .input {
            width: 100%;
            background: var(--bg-main);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 11px 14px;
            color: var(--text-main);
            font-size: 0.9rem;
            font-family: 'DM Sans', sans-serif;
            transition: border-color 0.18s, box-shadow 0.18s;
            outline: none;
        }

        .input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(79,124,255,0.12);
        }

        .input::placeholder { color: var(--text-muted); }

        /* Erreur sur un champ */
        .input.error { border-color: var(--danger); }

        .field-error {
            color: #f87171;
            font-size: 0.78rem;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Checkbox "Se souvenir" */
        .remember-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
        }

        .remember-row input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--accent);
            cursor: pointer;
        }

        .remember-row label {
            margin: 0;
            font-size: 0.85rem;
            color: var(--text-muted);
            cursor: pointer;
            font-weight: 400;
        }

        /* Bouton connexion */
        .btn-login {
            width: 100%;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 13px;
            font-size: 0.95rem;
            font-weight: 600;
            font-family: 'Syne', sans-serif;
            cursor: pointer;
            transition: all 0.18s ease;
            letter-spacing: 0.2px;
        }

        .btn-login:hover {
            background: #3d6be0;
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(79,124,255,0.25);
        }

        .btn-login:active { transform: translateY(0); }

        /* Alerte d'erreur globale */
        .alert-error {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.25);
            border-radius: 10px;
            padding: 11px 14px;
            color: #f87171;
            font-size: 0.85rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Pied de page */
        .login-footer {
            text-align: center;
            margin-top: 20px;
            color: var(--text-muted);
            font-size: 0.78rem;
        }
    </style>
</head>
<body>

<div class="login-wrapper">

    {{-- Logo --}}
    <div class="login-logo">
        <div class="icon">
            <svg width="26" height="26" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        </div>
        <h1>DataCorrection</h1>
        <p>Plateforme de gestion et correction de données</p>
    </div>

    {{-- Card formulaire --}}
    <div class="login-card">

        {{-- Message d'erreur global (mauvais identifiants, compte désactivé) --}}
        @if($errors->any())
            <div class="alert-error">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                {{ $errors->first('email') }}
            </div>
        @endif

        {{-- Message de déconnexion --}}
        @if(session('success'))
            <div style="background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.25);border-radius:10px;padding:11px 14px;color:#4ade80;font-size:0.85rem;margin-bottom:20px;display:flex;align-items:center;gap:8px;">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ session('success') }}
            </div>
        @endif

        {{-- Formulaire de connexion --}}
        {{--
            On envoie les données en POST vers la route 'login.post'
            Le token CSRF @csrf protège contre les attaques cross-site
        --}}
        <form method="POST" action="{{ route('login.post') }}">
            @csrf

            {{-- Champ Email --}}
            <div class="form-group">
                <label for="email">Adresse email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="input {{ $errors->has('email') ? 'error' : '' }}"
                    value="{{ old('email') }}"
                    placeholder="vous@exemple.com"
                    autocomplete="email"
                    autofocus
                    required
                >
            </div>

            {{-- Champ Mot de passe --}}
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="input"
                    placeholder="••••••••"
                    autocomplete="current-password"
                    required
                >
            </div>

            {{-- Se souvenir de moi --}}
            <div class="remember-row">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Se souvenir de moi</label>
            </div>

            {{-- Bouton de connexion --}}
            <button type="submit" class="btn-login">
                Se connecter
            </button>

        </form>
    </div>

    {{-- Pied de page --}}
    <div class="login-footer">
        © {{ date('Y') }} DataCorrection — Accès réservé aux utilisateurs autorisés
    </div>

</div>

</body>
</html>
