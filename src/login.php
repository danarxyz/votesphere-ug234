<?php

/**
 * ================================================================
 * HALAMAN LOGIN - AUTENTIKASI PENGGUNA
 * ================================================================
 * 
 * File: login.php
 * Fungsi: Halaman login untuk autentikasi pengguna
 * 
 * FITUR UTAMA:
 * - Form login dengan validasi
 * - Support login dengan username atau email
 * - Redirect handling setelah login
 * - Toast notifications untuk feedback
 * - Dark mode support
 * 
 * KEAMANAN:
 * - Redirect jika sudah login
 * - Password verification yang aman
 * - Session management
 * - Input validation dan sanitization
 * 
 * AUTHOR: VoteSphere Team
 * VERSION: 1.0
 * ================================================================
 */

// ================================================================
// SETUP AWAL & KONFIGURASI
// ================================================================
session_start(); // Session harus dimulai paling awal
require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/utils.php';

// ================================================================
// REDIRECT JIKA SUDAH LOGIN
// ================================================================
/**
 * Jika user sudah login, redirect ke:
 * 1. URL yang disimpan di session (jika user mencoba akses halaman protected)
 * 2. Homepage sebagai fallback
 */
if (Auth::check()) {
    // Ambil redirect URL dari session jika ada
    $redirect_url = $_SESSION['login_redirect'] ?? APP_URL . '/public/index.php';
    unset($_SESSION['login_redirect']); // Hapus setelah digunakan
    header('Location: ' . $redirect_url);
    exit;
}

// ================================================================
// INISIALISASI VARIABEL FORM
// ================================================================
$error = '';                // Error message untuk form
$username_val = '';         // Nilai username untuk repopulate form

// ================================================================
// PROSES FORM LOGIN
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ================================================================
    // AMBIL DAN SANITASI INPUT
    // ================================================================
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $username_val = $username; // Simpan untuk repopulate form jika error

    // ================================================================
    // VALIDASI INPUT DASAR
    // ================================================================
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        // ================================================================
        // PROSES AUTENTIKASI
        // ================================================================
        if (Auth::login($username, $password)) {
            // ================================================================
            // LOGIN BERHASIL
            // ================================================================

            // Set toast notification untuk halaman tujuan
            $_SESSION['toast'] = [
                'message' => 'Login successful! Welcome back.',
                'type' => 'success'
            ];

            // Tentukan URL redirect
            $redirect_url = $_SESSION['login_redirect'] ?? APP_URL . '/public/index.php';
            unset($_SESSION['login_redirect']); // Hapus setelah digunakan

            // Redirect ke halaman tujuan
            header('Location: ' . $redirect_url);
            exit;
        } else {
            // ================================================================
            // LOGIN GAGAL
            // ================================================================
            $error = 'Invalid username or password';
        }
    }
}

// ================================================================
// SETUP UNTUK TEMPLATE
// ================================================================
$pageTitle = 'Login'; // Untuk page title
?>
<!DOCTYPE html>
<html lang="en" x-data="appState" :class="{ 'dark': darkMode }">

<head>
    <!-- ================================================================ -->
    <!-- HEAD SECTION - META, STYLES, SCRIPTS -->
    <!-- ================================================================ -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle . ' - ' . APP_NAME) ?></title>

    <!-- CSS Frameworks dan Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

    <!-- Custom Styles -->
    <style>
        /* Hide elements until Alpine.js loads */
        [x-cloak] {
            display: none !important;
        }
    </style>

    <!-- Tailwind Configuration -->
    <script>
        tailwind.config = {
            darkMode: 'class'
        }
    </script>
</head>

<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 antialiased" x-init="init()">
    <!-- ================================================================ -->
    <!-- ALPINE.JS STATE MANAGEMENT -->
    <!-- ================================================================ -->
    <script>
        function appState() {
            return {
                // ================================================================
                // STATE PROPERTIES
                // ================================================================
                darkMode: localStorage.getItem('darkMode') === 'true' ||
                    (!('darkMode' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches),

                // ================================================================
                // INITIALIZATION
                // ================================================================
                init() {
                    // Watch untuk perubahan dark mode
                    this.$watch('darkMode', val => {
                        localStorage.setItem('darkMode', val);
                        document.documentElement.classList.toggle('dark', val);
                    });

                    // Set initial dark mode state
                    document.documentElement.classList.toggle('dark', this.darkMode);

                    // Initialize Feather icons
                    feather.replace();

                    // ================================================================
                    // SHOW ERROR TOAST JIKA ADA
                    // ================================================================
                    <?php if (!empty($error)): ?>
                        this.showToast('<?= addslashes($error) ?>', 'error');
                    <?php endif; ?>

                    // ================================================================
                    // SHOW SESSION TOAST JIKA ADA
                    // ================================================================
                    <?php if (isset($_SESSION['toast_login_page'])): ?>
                        this.showToast(
                            '<?= addslashes($_SESSION['toast_login_page']['message']) ?>',
                            '<?= addslashes($_SESSION['toast_login_page']['type']) ?>'
                        );
                        <?php unset($_SESSION['toast_login_page']); ?>
                    <?php endif; ?>
                },

                // ================================================================
                // METHODS
                // ================================================================

                /**
                 * Toggle dark mode
                 */
                toggleDarkMode() {
                    this.darkMode = !this.darkMode;
                },

                /**
                 * Show toast notification
                 * 
                 * @param {string} message - Pesan yang ditampilkan
                 * @param {string} type - Tipe toast (success, error, info, warning)
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
                            background: "linear-gradient(to right, #ffb347, #ffcc33)"
                        }
                    };

                    Toastify({
                        text: message,
                        duration: 4000,
                        close: true,
                        gravity: "top", // top, bottom
                        position: "right", // left, center, right
                        style: colors[type] || colors['info']
                    }).showToast();
                }
            };
        }

        // ================================================================
        // SESSION TOAST HANDLER SETELAH ALPINE READY
        // ================================================================
        document.addEventListener('alpine:initialized', () => {
            <?php if (isset($_SESSION['toast'])): ?>
                Alpine.store('appState').showToast(
                    <?= json_encode($_SESSION['toast']['message']) ?>,
                    <?= json_encode($_SESSION['toast']['type']) ?>
                );
                <?php unset($_SESSION['toast']); ?>
            <?php endif; ?>
        });
    </script>

    <!-- ================================================================ -->
    <!-- MAIN APPLICATION LAYOUT -->
    <!-- ================================================================ -->
    <div id="app" class="flex flex-col min-h-screen">

        <!-- ================================================================ -->
        <!-- NAVIGATION BAR -->
        <!-- ================================================================ -->
        <nav class="bg-white dark:bg-gray-800 shadow-md sticky top-0 z-40">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <!-- Logo/Brand -->
                    <div class="flex items-center">
                        <a href="<?= APP_URL ?>/public/index.php"
                            class="flex-shrink-0 flex items-center text-xl font-bold text-indigo-600 dark:text-indigo-400">
                            <i data-feather="check-square" class="w-6 h-6 mr-2"></i>
                            <?= APP_NAME ?>
                        </a>
                    </div>

                    <!-- Right Side Actions -->
                    <div class="flex items-center space-x-3">
                        <!-- Dark Mode Toggle -->
                        <button @click="toggleDarkMode()"
                            class="p-2 rounded-full text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-100 dark:focus:ring-offset-gray-800 focus:ring-indigo-500"
                            title="Toggle dark mode">
                            <i data-feather="moon" class="w-5 h-5 dark:hidden"></i>
                            <i data-feather="sun" class="w-5 h-5 hidden dark:inline"></i>
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <!-- ================================================================ -->
        <!-- MAIN CONTENT - LOGIN FORM -->
        <!-- ================================================================ -->
        <main class="flex-grow flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 p-8 sm:p-10 rounded-xl shadow-2xl w-full max-w-md">

                <!-- ================================================================ -->
                <!-- HEADER SECTION -->
                <!-- ================================================================ -->
                <div class="text-center mb-8">
                    <h1 class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">
                        Welcome Back!
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">
                        Login to access your polls and cast your votes.
                    </p>
                </div>

                <!-- ================================================================ -->
                <!-- LOGIN FORM -->
                <!-- ================================================================ -->
                <form method="POST"
                    action="<?= htmlspecialchars($_SERVER['PHP_SELF']) .
                                (isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : (isset($_SESSION['login_redirect']) ? '?redirect=' . urlencode($_SESSION['login_redirect']) : '')) ?>"
                    class="space-y-6">

                    <!-- Username/Email Field -->
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Username or Email
                        </label>
                        <input id="username"
                            name="username"
                            type="text"
                            autocomplete="username"
                            required
                            value="<?= htmlspecialchars($username_val) ?>"
                            class="appearance-none relative block w-full px-3 py-3 border border-gray-300 dark:border-gray-600 placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm bg-white dark:bg-gray-700 transition-colors"
                            placeholder="Enter your username or email">
                    </div>

                    <!-- Password Field -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Password
                        </label>
                        <input id="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="appearance-none relative block w-full px-3 py-3 border border-gray-300 dark:border-gray-600 placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm bg-white dark:bg-gray-700 transition-colors"
                            placeholder="Enter your password">
                    </div>

                    <!-- Submit Button -->
                    <div>
                        <button type="submit"
                            class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800 transition-all duration-200 ease-in-out transform hover:scale-105 active:scale-95">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <i data-feather="log-in" class="h-5 w-5 text-indigo-500 group-hover:text-indigo-400"></i>
                            </span>
                            Sign In
                        </button>
                    </div>
                </form>

                <!-- ================================================================ -->
                <!-- REGISTER LINK -->
                <!-- ================================================================ -->
                <div class="mt-8 text-center">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Don't have an account?
                        <a href="<?= APP_URL ?>/src/register.php"
                            class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300 transition-colors">
                            Sign up here
                        </a>
                    </p>
                </div>
            </div>
        </main>

        <!-- ================================================================ -->
        <!-- FOOTER -->
        <!-- ================================================================ -->
        <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 mt-auto">
            <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    &copy; <?= date("Y"); ?> <?= APP_NAME; ?>. All rights reserved.
                </p>
            </div>
        </footer>
    </div>

    <!-- ================================================================ -->
    <!-- SCRIPTS INITIALIZATION -->
    <!-- ================================================================ -->
    <script>
        // Initialize Feather icons on DOM load
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
        });

        // Re-initialize icons after Alpine updates
        document.addEventListener('alpine:updated', function() {
            feather.replace();
        });
    </script>
</body>

</html>

<!-- ================================================================ -->
<!-- CATATAN IMPLEMENTASI -->
<!-- ================================================================ -->
<!--
FITUR KEAMANAN:
1. Session management yang proper
2. Input validation dan sanitization
3. Password verification menggunakan Auth class
4. Redirect protection

FITUR UX:
1. Form repopulation jika ada error
2. Toast notifications untuk feedback
3. Dark mode support
4. Responsive design
5. Loading states dan transitions

STRUKTUR KODE:
1. Separation of concerns (logic, presentation)
2. Consistent error handling
3. Proper comment documentation
4. Alpine.js untuk interactivity
5. Tailwind CSS untuk styling

DEPLOYMENT CONSIDERATIONS:
1. CSRF protection (bisa ditambahkan)
2. Rate limiting (implementasi server-side)
3. HTTPS enforcement untuk production
4. Session security settings
-->