<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/js/app.js'])
    <script src="https://unpkg.com/@dotlottie/player-component@2.7.12/dist/dotlottie-player.mjs" type="module"></script>

    {{-- Anti-flash : applique le thème AVANT le rendu CSS pour éviter le clignotement --}}
    <script>
        (function () {
            var saved = localStorage.getItem('hub-theme');
            var sys   = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            if ((saved || sys) === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</head>
<body>

<div class="app-wrapper">

    {{-- ===== SIDEBAR ===== --}}
    <aside id="sidebar" class="sidebar" role="navigation" aria-label="Menu principal">
        <nav class="sidebar__nav">

            {{-- Mini profil en haut de sidebar --}}
            <div class="sidebar__profile">
                <div class="sidebar-avatar">
                    @if(auth()->user()->avatarUrl())
                        <img src="{{ auth()->user()->avatarUrl() }}" alt="{{ auth()->user()->name }}" class="sidebar-avatar__img">
                    @else
                        <span class="sidebar-avatar__initials">{{ auth()->user()->initials() }}</span>
                    @endif
                </div>
                <div class="sidebar-avatar__info">
                    <span class="sidebar-avatar__name">{{ auth()->user()->name }}</span>
                    <a href="{{ route('profile.edit') }}" class="sidebar-avatar__link">Modifier le profil</a>
                </div>
            </div>

            <hr class="sidebar__divider">
            <span class="sidebar__section-title">Navigation</span>

            <a href="{{ route('dashboard') }}"
               class="sidebar__link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>
                Mon dashboard
            </a>

            <a href="{{ route('preferences.edit') }}"
               class="sidebar__link {{ request()->routeIs('preferences.*') ? 'is-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="4" y1="6" x2="20" y2="6"/>
                    <line x1="4" y1="12" x2="20" y2="12"/>
                    <line x1="4" y1="18" x2="20" y2="18"/>
                    <circle cx="8" cy="6" r="2" fill="currentColor" stroke="none"/>
                    <circle cx="16" cy="12" r="2" fill="currentColor" stroke="none"/>
                    <circle cx="10" cy="18" r="2" fill="currentColor" stroke="none"/>
                </svg>
                Mes outils
            </a>

            @if(auth()->user()->isAdmin())
                <hr class="sidebar__divider">
                <span class="sidebar__section-title">Administration</span>

                <a href="{{ route('admin.families.index') }}"
                   class="sidebar__link {{ request()->routeIs('admin.families.*') ? 'is-active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="8" height="8" rx="1"/><rect x="14" y="3" width="8" height="8" rx="1"/>
                        <rect x="2" y="14" width="8" height="8" rx="1"/><rect x="14" y="14" width="8" height="8" rx="1"/>
                    </svg>
                    Familles
                </a>

                <a href="{{ route('admin.tools.index') }}"
                   class="sidebar__link {{ request()->routeIs('admin.tools.*') ? 'is-active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                    </svg>
                    Outils
                </a>

                <a href="{{ route('admin.users.index') }}"
                   class="sidebar__link {{ request()->routeIs('admin.users.*') ? 'is-active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    Utilisateurs
                </a>
            @endif
        </nav>

        <div class="sidebar__footer">
            <div class="sidebar-footer__brand">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M3 3h7v7H3zm11 0h7v7h-7zM3 14h7v7H3zm11 0h7v7h-7z"/>
                </svg>
                <span class="sidebar-footer__name">Hub</span>
                <span class="sidebar-footer__version">v1.1</span>
            </div>
            <p class="sidebar-footer__author">par Doko972</p>
            <a href="https://claude.ai" target="_blank" rel="noopener" class="sidebar-footer__collab">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/>
                </svg>
                en coopération avec Claude.ai
            </a>
        </div>
    </aside>

    {{-- ===== OVERLAY MOBILE ===== --}}
    <div id="sidebar-overlay" class="sidebar-overlay"></div>

    {{-- ===== CONTENU PRINCIPAL ===== --}}
    <div class="main-content">

        {{-- NAVBAR --}}
        <header class="navbar">
            <div class="navbar__left">
                {{-- Burger (visible sur mobile/tablette) --}}
                <button id="burger" class="burger" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="sidebar">
                    <span class="burger__line"></span>
                    <span class="burger__line"></span>
                    <span class="burger__line"></span>
                </button>

                <a href="{{ route('dashboard') }}" class="navbar__brand">
                    <dotlottie-player class="brand-lottie brand-lottie--light"
                        src="{{ asset('logo.json') }}"
                        background="transparent"
                        speed="1"
                        style="width: 36px; height: 36px;"
                        autoplay>
                    </dotlottie-player>
                    <dotlottie-player class="brand-lottie brand-lottie--dark"
                        src="{{ asset('logo-dark.json') }}"
                        background="transparent"
                        speed="1"
                        style="width: 36px; height: 36px;"
                        autoplay>
                    </dotlottie-player>
                    Hub
                </a>

                <span class="navbar__page-title">@yield('page-title')</span>
            </div>

            <div class="navbar__right">

                {{-- Bouton Dark / Light mode --}}
                <button id="theme-toggle"
                        class="theme-toggle"
                        aria-label="Passer en mode sombre"
                        title="Passer en mode sombre">
                    {{-- Soleil (visible en mode sombre → revenir au clair) --}}
                    <svg id="theme-icon-sun" class="hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                        <circle cx="12" cy="12" r="5"/>
                        <line x1="12" y1="1" x2="12" y2="3"/>
                        <line x1="12" y1="21" x2="12" y2="23"/>
                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
                        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                        <line x1="1" y1="12" x2="3" y2="12"/>
                        <line x1="21" y1="12" x2="23" y2="12"/>
                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
                        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                    </svg>
                    {{-- Lune (visible en mode clair → passer au sombre) --}}
                    <svg id="theme-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                </button>

                {{-- Menu utilisateur --}}
                <div class="navbar__dropdown">
                    <div class="navbar__user" data-dropdown="user-menu" aria-haspopup="true" aria-expanded="false">
                        {{-- Avatar photo ou initiales --}}
                        @if(auth()->user()->avatarUrl())
                            <img src="{{ auth()->user()->avatarUrl() }}"
                                 alt="{{ auth()->user()->name }}"
                                 class="user-avatar user-avatar--photo">
                        @else
                            <div class="user-avatar">{{ auth()->user()->initials() }}</div>
                        @endif
                        <div>
                            <div class="user-name">{{ auth()->user()->name }}</div>
                            <div class="user-role">{{ auth()->user()->isAdmin() ? 'Administrateur' : 'Utilisateur' }}</div>
                        </div>
                    </div>

                    <div id="user-menu" class="dropdown-menu">
                        <a href="{{ route('dashboard') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                                <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
                            </svg>
                            Mon dashboard
                        </a>
                        <a href="{{ route('profile.edit') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            Mon profil
                        </a>
                        <hr>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                    <polyline points="16 17 21 12 16 7"/>
                                    <line x1="21" y1="12" x2="9" y2="12"/>
                                </svg>
                                Déconnexion
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        {{-- PAGE --}}
        <main class="page-content">

            {{-- Messages flash --}}
            @if(session('success'))
                <div class="alert alert--success">{{ session('success') }}</div>
            @endif
            @if(session('error') || $errors->has('error'))
                <div class="alert alert--error">{{ session('error') ?? $errors->first('error') }}</div>
            @endif

            @yield('content')
        </main>

    </div>{{-- /main-content --}}
</div>{{-- /app-wrapper --}}

</body>
</html>
