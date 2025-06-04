<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/utils.php';
require_once __DIR__ . '/../src/Auth.php';

$current_user_id_global = Auth::check() ? Auth::id() : null;
$current_username_global = Auth::check() ? Auth::user()['username'] : null;

$pageTitleGlobal = APP_NAME . (isset($pageTitle) && $pageTitle !== '' ? ' - ' . $pageTitle : '');

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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitleGlobal) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <style>
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

        [x-cloak] {
            display: none !important;
        }
    </style>
    <script>
        tailwind.config = {
            darkMode: 'class'
        }

        document.addEventListener('alpine:init', () => {
            Alpine.data('appState', () => ({
                darkMode: localStorage.getItem('darkMode') === 'true' || (!('darkMode' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches),
                mobileMenuOpen: false,
                profileDropdownOpen: false,
                searchQuery: '',
                searchResults: [],
                searchLoading: false,
                searchDebounce: null,
                init() {
                    this.$watch('darkMode', val => {
                        localStorage.setItem('darkMode', val);
                        document.documentElement.classList.toggle('dark', val);
                        if (typeof feather !== 'undefined') feather.replace();
                    });
                    document.documentElement.classList.toggle('dark', this.darkMode);
                    if (typeof feather !== 'undefined') feather.replace();

                    <?php
                    if (isset($_SESSION['toast'])) {
                        $toast = $_SESSION['toast'];
                        echo "this.showToast(" . json_encode($toast['message'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ", " . json_encode($toast['type']) . ");";
                        unset($_SESSION['toast']);
                    }
                    ?>
                },
                toggleDarkMode() {
                    this.darkMode = !this.darkMode;
                },
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
                handleSearchInput() {
                    clearTimeout(this.searchDebounce);
                    if (this.searchQuery.length < 2) {
                        this.searchResults = [];
                        this.searchLoading = false;
                        return;
                    }
                    this.searchLoading = true;
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
                clearSearch() {
                    this.searchQuery = '';
                    this.searchResults = [];
                    this.searchLoading = false;
                }
            }));
        });
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof feather !== 'undefined') feather.replace();
        });
    </script>
</head>

<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col" x-cloak>
    <!-- Consistent Navbar -->
    <nav class="bg-white dark:bg-gray-800 shadow-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <!-- Logo -->
                    <a href="<?= htmlspecialchars($indexPath) ?>" class="flex-shrink-0 flex items-center text-xl font-bold text-blue-600 dark:text-blue-400">
                        <i data-feather="bar-chart-2" class="mr-2"></i> <?= APP_NAME ?>
                    </a>
                </div>
                <div class="hidden md:flex items-center space-x-4">
                    <!-- Menu -->
                    <a href="<?= htmlspecialchars($indexPath) ?>" class="text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium">Home</a>
                    <?php if (Auth::check()): ?>
                        <a href="<?= htmlspecialchars($createPollPath) ?>" class="text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium">Create Poll</a>
                    <?php endif; ?>
                </div>
                <div class="flex items-center">
                    <!-- Search Bar -->
                    <div class="relative ml-2" @click.away="searchResults = []">
                        <form action="<?= htmlspecialchars($searchPath) ?>" method="get" class="flex items-center" autocomplete="off">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i data-feather="search" class="h-5 w-5 text-gray-400"></i>
                                </div>
                                <input type="search" id="search" name="q" placeholder="Search polls..."
                                    x-model="searchQuery" @input="handleSearchInput" @focus="if(searchQuery.length >=2) searchResults.length > 0 ? null : handleSearchInput()"
                                    class="block w-full sm:w-48 md:w-64 pl-10 pr-3.5 py-2.5 border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-sm placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                    autocomplete="off">
                            </div>
                        </form>
                        <div x-show="searchQuery.length >= 2 && (searchResults.length > 0 || searchLoading)"
                            class="absolute mt-1 w-full sm:w-64 md:w-72 rounded-md shadow-lg bg-white dark:bg-gray-700 ring-1 ring-black dark:ring-gray-600 ring-opacity-5 overflow-y-auto max-h-60 z-50"
                            x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
                            <ul class="py-1">
                                <template x-if="searchLoading">
                                    <li class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">Loading...</li>
                                </template>
                                <template x-if="!searchLoading && searchResults.length === 0 && searchQuery.length >= 2">
                                    <li class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">No results found.</li>
                                </template>
                                <template x-for="result in searchResults" :key="result.poll_id">
                                    <li>
                                        <a :href="`<?= APP_URL ?>/src/vote.php?poll_id=${result.poll_id}`"
                                            class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 truncate"
                                            x-text="result.title"></a>
                                    </li>
                                </template>
                                <template x-if="!searchLoading && searchResults.length > 0">
                                    <li><a :href="`<?= htmlspecialchars($searchPath) ?>?q=${encodeURIComponent(searchQuery)}`" class="block px-4 py-2 text-sm font-medium text-center text-blue-600 dark:text-blue-400 hover:bg-gray-100 dark:hover:bg-gray-600">View all results</a></li>
                                </template>
                            </ul>
                        </div>
                    </div>

                    <button @click="toggleDarkMode()" class="ml-2 p-2 rounded-full text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-100 dark:focus:ring-offset-gray-800 focus:ring-indigo-500" title="Toggle dark mode">
                        <i data-feather="moon" class="h-5 w-5 block dark:hidden"></i>
                        <i data-feather="sun" class="h-5 w-5 hidden dark:block"></i>
                    </button>

                    <?php if (Auth::check()): ?>
                        <div class="ml-3 relative" x-data="{ profileDropdownOpen: false }" @click.away="profileDropdownOpen = false">
                            <button @click="profileDropdownOpen = !profileDropdownOpen" type="button" class="bg-white dark:bg-gray-800 flex text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-100 dark:focus:ring-offset-gray-800 focus:ring-indigo-500" id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                                <span class="sr-only">Open user menu</span>
                                <img class="h-8 w-8 rounded-full" src="<?= htmlspecialchars(get_user_avatar_url($current_user_id_global, $current_username_global)) ?>" alt="User avatar">
                            </button>
                            <div x-show="profileDropdownOpen" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95" class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white dark:bg-gray-700 ring-1 ring-black dark:ring-gray-600 ring-opacity-5 focus:outline-none z-50" role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button" tabindex="-1">
                                <span class="block px-4 py-2 text-xs text-gray-500 dark:text-gray-400">Signed in as <?= htmlspecialchars($current_username_global) ?></span>
                                <!-- <a href="#" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600" role="menuitem" tabindex="-1" id="user-menu-item-0">Your Profile</a> -->
                                <a href="<?= htmlspecialchars($logoutPath) ?>" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600" role="menuitem" tabindex="-1" id="user-menu-item-2">Sign out</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="<?= htmlspecialchars($loginPath) ?>" class="ml-2 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium">Login</a>
                        <a href="<?= htmlspecialchars($registerPath) ?>" class="ml-2 text-white bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 px-3 py-2 rounded-md text-sm font-medium">Register</a>
                    <?php endif; ?>
                    <div class="md:hidden ml-2">
                        <button @click="mobileMenuOpen = !mobileMenuOpen" type="button" class="bg-white dark:bg-gray-800 inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500" aria-controls="mobile-menu" aria-expanded="false">
                            <span class="sr-only">Open main menu</span>
                            <i data-feather="menu" class="block h-6 w-6" x-show="!mobileMenuOpen"></i>
                            <i data-feather="x" class="hidden h-6 w-6" x-show="mobileMenuOpen"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Mobile menu -->
        <div x-show="mobileMenuOpen" class="md:hidden" id="mobile-menu" @click.away="mobileMenuOpen = false" x-cloak>
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
                <a href="<?= htmlspecialchars($indexPath) ?>" class="block text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-md text-base font-medium">Home</a>
                <?php if (Auth::check()): ?>
                    <a href="<?= htmlspecialchars($createPollPath) ?>" class="block text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-md text-base font-medium">Create Poll</a>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($loginPath) ?>" class="block text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-md text-base font-medium">Login</a>
                    <a href="<?= htmlspecialchars($registerPath) ?>" class="block text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-md text-base font-medium">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content Area Start -->
    <main class="flex-grow container mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Konten utama halaman di sini, tanpa search bar lagi -->