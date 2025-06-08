<?php

/**
 * ================================================================
 * KOMPONEN HEADER - NAVIGASI UTAMA APLIKASI VOTESPHERE
 * ================================================================
 * 
 * File: header.php
 * Lokasi: /includes/header.php
 * Fungsi: Template header universal untuk semua halaman
 * 
 * FITUR UTAMA:
 * - Navigation bar responsif dengan dark mode support
 * - Search functionality dengan live suggestions
 * - User authentication status handling
 * - Mobile-first design dengan overflow prevention
 * - Toast notification system
 * - Alpine.js state management
 * 
 * DEPENDENCIES:
 * - Tailwind CSS v4 (Browser CDN)
 * - Alpine.js v3
 * - Feather Icons
 * - Toastify.js untuk notifications
 * 
 * RESPONSIVE BREAKPOINTS:
 * - Mobile: < 640px (sm)
 * - Tablet: 640px - 768px (md)
 * - Desktop: > 768px
 * 
 * AUTHOR: VoteSphere Development Team
 * VERSION: 2.1.0
 * LAST_UPDATED: 2025-06-05
 * ================================================================
 */

// ================================================================
// INISIALISASI SESSION & DEPENDENCIES
// ================================================================

// Pastikan session sudah dimulai (avoid double session_start)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load konfigurasi dan dependencies utama
require_once __DIR__ . '/../config/config.php';      // Konfigurasi aplikasi
require_once __DIR__ . '/../utils/utils.php';        // Helper functions
require_once __DIR__ . '/../src/Auth.php';           // Authentication handler

// ================================================================
// SETUP VARIABEL GLOBAL UNTUK TEMPLATE
// ================================================================

/**
 * Mendapatkan data user yang sedang login
 * @var int|null $current_user_id_global - ID user yang login (null jika guest)
 * @var string|null $current_username_global - Username yang login (null jika guest)
 */
$current_user_id_global = Auth::check() ? Auth::id() : null;
$current_username_global = Auth::check() ? Auth::user()['username'] : null;

/**
 * Setup page title dengan format: "VoteSphere - Page Title"
 * @var string $pageTitleGlobal - Title lengkap untuk tag <title>
 */
$pageTitleGlobal = APP_NAME . (isset($pageTitle) && $pageTitle !== '' ? ' - ' . $pageTitle : '');

/**
 * URL paths untuk navigasi - menggunakan APP_URL dari config
 * @var string $indexPath - URL halaman utama
 * @var string $createPollPath - URL halaman create poll
 * @var string $loginPath - URL halaman login
 * @var string $registerPath - URL halaman register  
 * @var string $logoutPath - URL logout handler
 * @var string $searchPath - URL halaman search
 */
$indexPath = APP_URL . '/public/index.php';
$createPollPath = APP_URL . '/src/create-polls.php';
$loginPath = APP_URL . '/src/login.php';
$registerPath = APP_URL . '/src/register.php';
$logoutPath = APP_URL . '/src/logout.php';
$searchPath = APP_URL . '/src/search.php';

?>
<!DOCTYPE html>
<html lang="en" x-data="appState" :class="{ 'dark': darkMode }">

<head>
    <!-- ================================================================ -->
    <!-- META TAGS & BASIC SETUP -->
    <!-- ================================================================ -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitleGlobal) ?></title>

    <!-- ================================================================ -->
    <!-- EXTERNAL DEPENDENCIES -->
    <!-- ================================================================ -->

    <!-- Tailwind CSS v4 - Browser CDN untuk development -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <!-- Alpine.js v3 - Reactive JavaScript framework -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Feather Icons - SVG icon library -->
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>

    <!-- Toastify.js - Toast notification library -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

    <!-- ================================================================ -->
    <!-- CUSTOM STYLES - TOASTIFY CUSTOMIZATION -->
    <!-- ================================================================ -->
    <style>
        /**
         * Toast notification styling customization
         * Mengoverride default Toastify styles untuk konsistensi design
         */
        .toastify {
            padding: 12px 20px;
            border-radius: 4px;
            font-family: inherit;
            font-size: 1rem;
            color: #fff;
            background: #333;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.16), 0 3px 6px rgba(0, 0, 0, 0.23);
        }

        .toastify.on {
            opacity: 1;
            transform: translateY(0);
        }

        /* Position variants */
        .toastify-right {
            right: 15px;
        }

        .toastify-left {
            left: 15px;
        }

        .toastify-top {
            top: 15px;
        }

        .toastify-bottom {
            bottom: 15px;
        }

        .toastify-rounded {
            border-radius: 25px;
        }

        /* Toast components */
        .toastify-avatar {
            width: 1.5em;
            height: 1.5em;
            margin: -0.375em 0.5em -0.375em -0.5em;
            border-radius: 2px;
        }

        .toastify-close {
            background: transparent;
            border: 0;
            color: #fff;
            cursor: pointer;
            font-size: 1.25em;
            opacity: 0.8;
            padding: 0 0.25em;
        }

        .toastify-close:hover {
            opacity: 1;
        }

        /**
         * Alpine.js cloak - hide elements until Alpine is loaded
         * Mencegah flash of unstyled content (FOUC)
         */
        [x-cloak] {
            display: none !important;
        }
    </style>

    <!-- ================================================================ -->
    <!-- JAVASCRIPT CONFIGURATION & ALPINE.JS SETUP -->
    <!-- ================================================================ -->
    <script>
        /**
         * ============================================================
         * TAILWIND CSS CONFIGURATION
         * ============================================================
         */
        tailwind.config = {
            darkMode: 'class' // Enable class-based dark mode
        }

        /**
         * ============================================================
         * ALPINE.JS GLOBAL STATE MANAGEMENT
         * ============================================================
         */
        document.addEventListener('alpine:init', () => {
            Alpine.data('appState', () => ({
                /**
                 * Dark Mode State Management
                 * Menggunakan localStorage untuk persistence
                 * Default: system preference atau dark mode
                 */
                darkMode: localStorage.getItem('darkMode') === 'true' ||
                    (!('darkMode' in localStorage) &&
                        window.matchMedia('(prefers-color-scheme: dark)').matches),

                /**
                 * UI State Variables
                 */
                mobileMenuOpen: false, // Mobile hamburger menu state
                profileDropdownOpen: false, // User profile dropdown state

                /**
                 * Search Functionality State
                 */
                searchQuery: '', // Current search input
                searchResults: [], // Array of search results
                searchLoading: false, // Loading state for search
                searchDebounce: null, // Debounce timer for search

                /**
                 * ========================================================
                 * INITIALIZATION METHOD
                 * ========================================================
                 * Dipanggil saat Alpine.js component di-mount
                 */
                init() {
                    // Watch dark mode changes dan sync ke localStorage
                    this.$watch('darkMode', val => {
                        localStorage.setItem('darkMode', val);
                        document.documentElement.classList.toggle('dark', val);
                        // Re-render feather icons setelah mode change
                        if (typeof feather !== 'undefined') feather.replace();
                    });

                    // Set initial dark mode class
                    document.documentElement.classList.toggle('dark', this.darkMode);

                    // Initialize feather icons
                    if (typeof feather !== 'undefined') feather.replace();

                    <?php
                    /**
                     * PHP-to-JavaScript Toast Message Bridge
                     * Menampilkan toast notification dari PHP session
                     */
                    if (isset($_SESSION['toast'])) {
                        $toast = $_SESSION['toast'];
                        echo "this.showToast(" .
                            json_encode($toast['message'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) .
                            ", " . json_encode($toast['type']) . ");";
                        unset($_SESSION['toast']); // Clear after use
                    }
                    ?>
                },

                /**
                 * ========================================================
                 * DARK MODE TOGGLE METHOD
                 * ========================================================
                 * Toggle between light and dark theme
                 */
                toggleDarkMode() {
                    this.darkMode = !this.darkMode;
                },

                /**
                 * ========================================================
                 * TOAST NOTIFICATION METHOD
                 * ========================================================
                 * Menampilkan toast notification dengan berbagai style
                 * 
                 * @param {string} message - Pesan yang akan ditampilkan
                 * @param {string} type - Tipe toast: success|error|info|warning
                 */
                showToast(message, type = 'info') {
                    const colors = {
                        success: {
                            background: "linear-gradient(to right, #00b09b, #96c93d)"
                        },
                        error: {
                            background: "linear-gradient(to right, #ff5f6d, #ffc371)"
                        },
                        info: {
                            background: "linear-gradient(to right, #0072ff, #00c6ff)"
                        },
                        warning: {
                            background: "linear-gradient(to right, #fdc830, #f37335)"
                        }
                    };

                    Toastify({
                        text: message,
                        duration: 3000,
                        close: true,
                        gravity: "top",
                        position: "right",
                        style: colors[type] || colors['info'],
                        escapeMarkup: false
                    }).showToast();
                },

                /**
                 * ========================================================
                 * LIVE SEARCH FUNCTIONALITY
                 * ========================================================
                 * Menangani input search dengan debouncing dan AJAX
                 */
                handleSearchInput() {
                    // Clear previous debounce timer
                    clearTimeout(this.searchDebounce);

                    // Reset jika query terlalu pendek
                    if (this.searchQuery.length < 2) {
                        this.searchResults = [];
                        this.searchLoading = false;
                        return;
                    }

                    // Set loading state
                    this.searchLoading = true;

                    // Debounce AJAX request (300ms delay)
                    this.searchDebounce = setTimeout(() => {
                        fetch(`<?= APP_URL ?>/src/search_api.php?q=${encodeURIComponent(this.searchQuery)}`)
                            .then(res => res.json())
                            .then(data => {
                                this.searchResults = data;
                                this.searchLoading = false;
                            })
                            .catch(() => this.searchLoading = false);
                    }, 300);
                },

                /**
                 * ========================================================
                 * CLEAR SEARCH METHOD
                 * ========================================================
                 * Reset semua search state
                 */
                clearSearch() {
                    this.searchQuery = '';
                    this.searchResults = [];
                    this.searchLoading = false;
                }
            }));
        });

        /**
         * ============================================================
         * DOCUMENT READY - FEATHER ICONS INITIALIZATION
         * ============================================================
         * Ensure feather icons are rendered setelah DOM loaded
         */
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof feather !== 'undefined') feather.replace();
        });
    </script>
</head>

<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col" x-cloak>

    <!-- ================================================================ -->
    <!-- NAVIGATION BAR - STICKY HEADER -->
    <!-- ================================================================ -->
    <nav class="bg-white dark:bg-gray-800 shadow-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-14 sm:h-16">

                <!-- ================================================================ -->
                <!-- LOGO SECTION -->
                <!-- ================================================================ -->
                <div class="flex-shrink-0">
                    <a href="<?= htmlspecialchars($indexPath) ?>"
                        class="flex items-center text-lg sm:text-xl font-bold text-blue-600 dark:text-blue-400">
                        <!-- Icon: bar-chart-2 dari Feather Icons -->
                        <i data-feather="bar-chart-2" class="w-5 h-5 sm:w-6 sm:h-6 mr-1 sm:mr-2"></i>

                        <!-- Responsive logo text -->
                        <span class="hidden xs:block"><?= APP_NAME ?></span>
                        <span class="block xs:hidden">VoteSphere</span>
                    </a>
                </div>

                <!-- ================================================================ -->
                <!-- DESKTOP NAVIGATION MENU (Hidden di mobile) -->
                <!-- ================================================================ -->
                <div class="hidden md:flex items-center space-x-6">
                    <!-- Home Link -->
                    <a href="<?= htmlspecialchars($indexPath) ?>"
                        class="text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium">
                        Home
                    </a>

                    <!-- Create Poll Link (hanya untuk authenticated users) -->
                    <?php if (Auth::check()): ?>
                        <a href="<?= htmlspecialchars($createPollPath) ?>"
                            class="text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium">
                            Create Poll
                        </a>
                    <?php endif; ?>
                </div>

                <!-- ================================================================ -->
                <!-- RIGHT SIDE CONTROLS -->
                <!-- ================================================================ -->
                <div class="flex items-center space-x-1 sm:space-x-2">

                    <!-- ================================================================ -->
                    <!-- SEARCH BAR (Desktop Only) -->
                    <!-- ================================================================ -->
                    <div class="hidden sm:block relative" @click.away="searchResults = []">
                        <!-- Search Form -->
                        <form action="<?= htmlspecialchars($searchPath) ?>" method="get"
                            class="flex items-center" autocomplete="off">
                            <div class="relative">
                                <!-- Search Icon -->
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i data-feather="search" class="h-4 w-4 text-gray-400"></i>
                                </div>

                                <!-- Search Input -->
                                <input type="search"
                                    id="search"
                                    name="q"
                                    placeholder="Search polls..."
                                    x-model="searchQuery"
                                    @input="handleSearchInput"
                                    @focus="if(searchQuery.length >=2) searchResults.length > 0 ? null : handleSearchInput()"
                                    class="block w-40 lg:w-56 pl-9 pr-3 py-2 border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-sm placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-sm"
                                    autocomplete="off">
                            </div>
                        </form>

                        <!-- Live Search Results Dropdown -->
                        <div x-show="searchQuery.length >= 2 && (searchResults.length > 0 || searchLoading)"
                            class="absolute mt-1 w-full lg:w-72 rounded-md shadow-lg bg-white dark:bg-gray-700 ring-1 ring-black dark:ring-gray-600 ring-opacity-5 overflow-y-auto max-h-60 z-50"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95">
                            <ul class="py-1">
                                <!-- Loading State -->
                                <template x-if="searchLoading">
                                    <li class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">Loading...</li>
                                </template>

                                <!-- No Results State -->
                                <template x-if="!searchLoading && searchResults.length === 0 && searchQuery.length >= 2">
                                    <li class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">No results found.</li>
                                </template>

                                <!-- Search Results -->
                                <template x-for="result in searchResults" :key="result.poll_id">
                                    <li>
                                        <a :href="`<?= APP_URL ?>/src/vote.php?poll_id=${result.poll_id}`"
                                            class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 truncate"
                                            x-text="result.title"></a>
                                    </li>
                                </template>

                                <!-- View All Results Link -->
                                <template x-if="!searchLoading && searchResults.length > 0">
                                    <li>
                                        <a :href="`<?= htmlspecialchars($searchPath) ?>?q=${encodeURIComponent(searchQuery)}`"
                                            class="block px-4 py-2 text-sm font-medium text-center text-blue-600 dark:text-blue-400 hover:bg-gray-100 dark:hover:bg-gray-600">
                                            View all results
                                        </a>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>

                    <!-- ================================================================ -->
                    <!-- MOBILE SEARCH BUTTON -->
                    <!-- ================================================================ -->
                    <a href="<?= htmlspecialchars($searchPath) ?>"
                        class="sm:hidden p-2 rounded-full text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none"
                        title="Search">
                        <i data-feather="search" class="h-5 w-5"></i>
                    </a>

                    <!-- ================================================================ -->
                    <!-- DARK MODE TOGGLE BUTTON -->
                    <!-- ================================================================ -->
                    <button @click="toggleDarkMode()"
                        class="p-2 rounded-full text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-100 dark:focus:ring-offset-gray-800 focus:ring-indigo-500"
                        title="Toggle dark mode">
                        <!-- Moon icon (show di light mode) -->
                        <i data-feather="moon" class="h-4 w-4 sm:h-5 sm:w-5 block dark:hidden"></i>
                        <!-- Sun icon (show di dark mode) -->
                        <i data-feather="sun" class="h-4 w-4 sm:h-5 sm:w-5 hidden dark:block"></i>
                    </button>

                    <!-- ================================================================ -->
                    <!-- AUTHENTICATED USER SECTION -->
                    <!-- ================================================================ -->
                    <?php if (Auth::check()): ?>
                        <!-- User Profile Dropdown -->
                        <div class="relative" x-data="{ profileDropdownOpen: false }" @click.away="profileDropdownOpen = false">
                            <!-- User Avatar Button -->
                            <button @click="profileDropdownOpen = !profileDropdownOpen"
                                type="button"
                                class="bg-white dark:bg-gray-800 flex text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-100 dark:focus:ring-offset-gray-800 focus:ring-indigo-500"
                                id="user-menu-button"
                                aria-expanded="false"
                                aria-haspopup="true">
                                <span class="sr-only">Open user menu</span>
                                <img class="h-7 w-7 sm:h-8 sm:w-8 rounded-full"
                                    src="<?= htmlspecialchars(get_user_avatar_url($current_user_id_global, $current_username_global)) ?>"
                                    alt="User avatar">
                            </button>

                            <!-- Dropdown Menu -->
                            <div x-show="profileDropdownOpen"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="transform opacity-0 scale-95"
                                x-transition:enter-end="transform opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="transform opacity-100 scale-100"
                                x-transition:leave-end="transform opacity-0 scale-95"
                                class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white dark:bg-gray-700 ring-1 ring-black dark:ring-gray-600 ring-opacity-5 focus:outline-none z-50"
                                role="menu"
                                aria-orientation="vertical"
                                aria-labelledby="user-menu-button"
                                tabindex="-1">

                                <!-- User Info Header -->
                                <span class="block px-4 py-2 text-xs text-gray-500 dark:text-gray-400">
                                    Signed in as <?= htmlspecialchars($current_username_global) ?>
                                </span>

                                <!-- Mobile-only Create Poll Link -->
                                <div class="md:hidden border-t border-gray-200 dark:border-gray-600 mt-1 pt-1">
                                    <a href="<?= htmlspecialchars($createPollPath) ?>"
                                        class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600"
                                        role="menuitem">
                                        Create Poll
                                    </a>
                                </div>

                                <!-- Sign Out Link -->
                                <a href="<?= htmlspecialchars($logoutPath) ?>"
                                    class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600"
                                    role="menuitem"
                                    tabindex="-1"
                                    id="user-menu-item-2">
                                    Sign out
                                </a>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- ================================================================ -->
                        <!-- GUEST USER SECTION -->
                        <!-- ================================================================ -->
                        <div class="flex items-center space-x-1">
                            <!-- Login Button -->
                            <a href="<?= htmlspecialchars($loginPath) ?>"
                                class="text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 px-2 sm:px-3 py-2 rounded-md text-xs sm:text-sm font-medium">
                                Login
                            </a>

                            <!-- Register Button -->
                            <a href="<?= htmlspecialchars($registerPath) ?>"
                                class="text-white bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 px-2 sm:px-3 py-2 rounded-md text-xs sm:text-sm font-medium">
                                Register
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- ================================================================ -->
    <!-- MAIN CONTENT AREA START -->
    <!-- ================================================================ -->
    <main class="flex-grow container mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- 
        ============================================================
        KONTEN UTAMA HALAMAN AKAN DI-INCLUDE DI SINI
        ============================================================
        
        Setiap halaman yang menggunakan header ini akan menampilkan
        kontennya di dalam tag <main> ini.
        
        STRUKTUR HALAMAN:
        1. Header (file ini) - Navigation & global functionality
        2. Main Content (dari halaman individual)
        3. Footer (dari footer.php) - Copyright & links
        
        BEST PRACTICES:
        - Gunakan container classes yang konsisten
        - Maintain responsive spacing
        - Pastikan accessibility dengan proper semantic HTML
        - Test di berbagai ukuran layar
        ============================================================
        -->