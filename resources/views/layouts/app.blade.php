<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#362ad7">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Hub">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/icon-192x192.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/icon-192x192.png">
    <title>@yield('title', 'Dashboard') — Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <meta name="presence-ping" content="{{ route('presence.ping') }}">
    @vite(['resources/js/app.js'])

    @include('partials.theme-boot')
    @include('partials.sidebar-boot')
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
               class="sidebar__link {{ request()->routeIs('preferences.edit') ? 'is-active' : '' }}">
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

            <a href="{{ route('preferences.theme') }}"
               class="sidebar__link {{ request()->routeIs('preferences.theme') ? 'is-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="13.5" cy="6.5" r="1.5" fill="currentColor" stroke="none"/>
                    <circle cx="17.5" cy="10.5" r="1.5" fill="currentColor" stroke="none"/>
                    <circle cx="8.5" cy="7.5" r="1.5" fill="currentColor" stroke="none"/>
                    <circle cx="6.5" cy="12.5" r="1.5" fill="currentColor" stroke="none"/>
                    <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125 0-.926.746-1.688 1.688-1.688h1.996c3.078 0 5.543-2.465 5.543-5.543C22 6.012 17.52 2 12 2z"/>
                </svg>
                Thème
            </a>

            <a href="{{ route('messages.index') }}"
               class="sidebar__link {{ request()->routeIs('messages.*') ? 'is-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                </svg>
                Messagerie
                @php $unreadTotal = \App\Services\Unread::totalFor(auth()->id()); @endphp
                <span class="sidebar__badge"
                      data-unread-badge
                      data-unread-url="{{ route('messages.unread') }}"
                      data-unread-interval="{{ (int) config('messaging.unread_poll_seconds', 20) }}"
                      @if(!$unreadTotal) hidden @endif>{{ $unreadTotal > 99 ? '99+' : $unreadTotal }}</span>
            </a>

            <a href="{{ route('profile.edit') }}"
               class="sidebar__link {{ request()->routeIs('profile.*') ? 'is-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                Mon profil
            </a>

            <hr class="sidebar__divider">

            <div class="sidebar__section" data-sidebar-section="tools">
                <button type="button"
                        class="sidebar__section-toggle"
                        data-sidebar-toggle="tools"
                        aria-expanded="true"
                        aria-controls="sidebar-section-tools">
                    <span class="sidebar__section-title">Outils</span>
                    <svg class="sidebar__section-chevron" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </button>

                <div class="sidebar__section-body" id="sidebar-section-tools">

            <a href="{{ route('tools.background-remover') }}"
               class="sidebar__link {{ request()->routeIs('tools.background-remover*') ? 'is-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="m15 4-8.5 8.5a3 3 0 0 0 4.24 4.24L19 8.24"/>
                    <path d="M3 21h18"/>
                    <path d="m17.5 2.5 4 4"/>
                </svg>
                Suppresseur d'arrière-plan
            </a>

            <a href="{{ route('tools.image-converter') }}"
               class="sidebar__link {{ request()->routeIs('tools.image-converter*') ? 'is-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                    <path d="m3 9 5-5 4 4 3-3 6 6"/>
                    <circle cx="8.5" cy="8.5" r="1.5" fill="currentColor" stroke="none"/>
                </svg>
                Convertisseur d'images
            </a>

            <a href="{{ route('tools.qr-code') }}"
               class="sidebar__link {{ request()->routeIs('tools.qr-code*') ? 'is-active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="3" height="3"/>
                    <rect x="18" y="14" width="3" height="3"/><rect x="14" y="18" width="3" height="3"/>
                    <rect x="18" y="18" width="3" height="3"/>
                </svg>
                Générateur de QR Code
            </a>

                </div>
            </div>

            @if(auth()->user()->isAdmin())
                <hr class="sidebar__divider">

                <div class="sidebar__section" data-sidebar-section="admin">
                    <button type="button"
                            class="sidebar__section-toggle"
                            data-sidebar-toggle="admin"
                            aria-expanded="true"
                            aria-controls="sidebar-section-admin">
                        <span class="sidebar__section-title">Administration</span>
                        <svg class="sidebar__section-chevron" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>

                    <div class="sidebar__section-body" id="sidebar-section-admin">

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

                <a href="{{ route('admin.assignments.index') }}"
                   class="sidebar__link {{ request()->routeIs('admin.assignments.*') ? 'is-active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <polyline points="16 11 18 13 22 9"/>
                    </svg>
                    Assignation
                </a>

                <a href="{{ route('admin.logs.index') }}"
                   class="sidebar__link {{ request()->routeIs('admin.logs.*') ? 'is-active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                        <line x1="10" y1="9"  x2="8" y2="9"/>
                    </svg>
                    Journaux
                </a>

                    </div>
                </div>
            @endif

            @include('partials.sidebar-presence')
        </nav>

        <div class="sidebar__footer">
            <div class="sidebar-footer__brand">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M3 3h7v7H3zm11 0h7v7h-7zM3 14h7v7H3zm11 0h7v7h-7z"/>
                </svg>
                <span class="sidebar-footer__name">Hub</span>
                <span class="sidebar-footer__version">v2.1.1</span>
            </div>
            <p class="sidebar-footer__author">par Doko972</p>
            <a href="https://claude.ai" target="_blank" rel="noopener" class="sidebar-footer__collab">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/>
                </svg>
                Développé avec Amour
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
                    <lottie-player class="brand-lottie brand-lottie--light"
                        src="{{ asset('logo.json') }}"
                        background="transparent"
                        speed="1"
                        style="width: 36px; height: 36px;"
                        autoplay>
                    </lottie-player>
                    <lottie-player class="brand-lottie brand-lottie--dark"
                        src="{{ asset('logo-dark.json') }}"
                        background="transparent"
                        speed="1"
                        style="width: 36px; height: 36px;"
                        autoplay>
                    </lottie-player>
                    Hub
                </a>

                <span class="navbar__page-title">@yield('page-title')</span>
            </div>

            <div class="navbar__right">

                {{-- Bouton Installer l'app (PWA) — affiché uniquement si disponible --}}
                <button id="btn-install-pwa" class="btn-install-pwa" style="display:none;" title="Installer l'application">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 15V3m0 12-4-4m4 4 4-4"/>
                        <path d="M2 17l.621 2.485A2 2 0 0 0 4.561 21H19.44a2 2 0 0 0 1.94-1.515L22 17"/>
                    </svg>
                    <span>Installer</span>
                </button>

                {{-- Sélecteur de thème --}}
                <div class="navbar__dropdown">
                    <button id="theme-toggle"
                            class="theme-toggle"
                            data-dropdown="theme-menu"
                            aria-haspopup="true"
                            aria-expanded="false"
                            aria-label="Changer de thème"
                            title="Changer de thème">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <circle cx="13.5" cy="6.5" r="1.5" fill="currentColor" stroke="none"/>
                            <circle cx="17.5" cy="10.5" r="1.5" fill="currentColor" stroke="none"/>
                            <circle cx="8.5" cy="7.5" r="1.5" fill="currentColor" stroke="none"/>
                            <circle cx="6.5" cy="12.5" r="1.5" fill="currentColor" stroke="none"/>
                            <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125 0-.926.746-1.688 1.688-1.688h1.996c3.078 0 5.543-2.465 5.543-5.543C22 6.012 17.52 2 12 2z"/>
                        </svg>
                    </button>

                    <div id="theme-menu" class="dropdown-menu dropdown-menu--themes">
                        @foreach(config('themes.available') as $key => $theme)
                            <button type="button"
                                    class="theme-option"
                                    data-theme-choice="{{ $key }}"
                                    aria-pressed="false">
                                <span class="theme-option__swatch" aria-hidden="true">
                                    @foreach($theme['swatch'] as $color)
                                        <span style="background: {{ $color }}"></span>
                                    @endforeach
                                </span>
                                <span class="theme-option__label">{{ $theme['label'] }}</span>
                                <svg class="theme-option__check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                            </button>
                        @endforeach

                        <hr>
                        <a href="{{ route('preferences.theme') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6h.09A1.65 1.65 0 0 0 10.6 3.09V3a2 2 0 0 1 4 0v.09A1.65 1.65 0 0 0 16.11 4.6a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 20.4 9v.09A1.65 1.65 0 0 0 21.91 10.6H22a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                            </svg>
                            Tous les thèmes
                        </a>
                    </div>
                </div>

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
            @yield('content')
        </main>

    </div>{{-- /main-content --}}
</div>{{-- /app-wrapper --}}

{{-- ===== CHATBOT BUBBLE ===== --}}
<button id="chatbot-bubble"
        class="chatbot-bubble"
        aria-label="Ouvrir le chatbot Cortex IA"
        title="Cortex IA – Assistant chatbot">
    <lottie-player
        src="{{ asset('chatbot-bubble.json') }}"
        background="transparent"
        speed="1"
        style="width: 44px; height: 44px; pointer-events: none;"
        loop
        autoplay>
    </lottie-player>
</button>

{{-- ===== CHATBOT PANEL ===== --}}
<div class="chatbot-overlay" id="chatbot-overlay">
    <div class="chatbot-window" id="chatbot-window">

        <div class="chatbot-window__header">
            <div class="chatbot-window__title">
                {{-- <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm1 14.93V15a1 1 0 0 0-2 0v1.93A8 8 0 0 1 4.07 9H6a1 1 0 0 0 0-2H4.07A8 8 0 0 1 11 2.07V4a1 1 0 0 0 2 0V2.07A8 8 0 0 1 19.93 9H18a1 1 0 0 0 0 2h1.93A8 8 0 0 1 13 16.93z"/>
                </svg> --}}
                ChatBot
            </div>
            <div class="chatbot-window__actions">
                <button class="chatbot-action-btn" id="chatbot-expand" title="Agrandir">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polyline points="15 3 21 3 21 9"/>
                        <polyline points="9 21 3 21 3 15"/>
                        <line x1="21" y1="3" x2="14" y2="10"/>
                        <line x1="3" y1="21" x2="10" y2="14"/>
                    </svg>
                </button>
                <button class="chatbot-action-btn" id="chatbot-open-tab" title="Ouvrir dans un onglet">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                        <polyline points="15 3 21 3 21 9"/>
                        <line x1="10" y1="14" x2="21" y2="3"/>
                    </svg>
                </button>
                <button class="chatbot-action-btn" id="chatbot-close" title="Fermer">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
        </div>

        <div class="chatbot-window__body">
            <div class="chatbot-loading" id="chatbot-loading">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                </svg>
                Chargement…
            </div>
            <iframe class="chatbot-frame" id="chatbot-frame" src="" title="Cortex IA"></iframe>
        </div>

    </div>
</div>
{{-- /CHATBOT PANEL --}}

{{-- Données flash pour les toasts --}}
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    window.__hubFlash = {
        success: @json(session('success')),
        error:   @json(session('error')),
        status:  @json(session('status')),
        warning: @json(session('warning')),
    };
</script>

@stack('scripts')

<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    }

    // PWA — bouton d'installation
    let deferredInstallPrompt = null;
    const btnInstallPwa = document.getElementById('btn-install-pwa');

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredInstallPrompt = e;
        btnInstallPwa.style.display = 'flex';
    });

    btnInstallPwa.addEventListener('click', async () => {
        if (!deferredInstallPrompt) return;
        deferredInstallPrompt.prompt();
        const { outcome } = await deferredInstallPrompt.userChoice;
        if (outcome === 'accepted') {
            btnInstallPwa.style.display = 'none';
        }
        deferredInstallPrompt = null;
    });

    window.addEventListener('appinstalled', () => {
        btnInstallPwa.style.display = 'none';
        deferredInstallPrompt = null;
    });
</script>
</body>
</html>
