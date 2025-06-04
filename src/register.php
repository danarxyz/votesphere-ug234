<?php

/**
 * ================================================================
 * HALAMAN REGISTRASI - PENDAFTARAN PENGGUNA BARU
 * ================================================================
 * 
 * File: register.php
 * Fungsi: Halaman untuk mendaftarkan akun pengguna baru
 * 
 * FITUR UTAMA:
 * - Form registrasi dengan validasi lengkap
 * - Email uniqueness validation
 * - Username uniqueness validation
 * - Password strength requirements
 * - Automatic login setelah registrasi berhasil
 * 
 * KEAMANAN:
 * - Password hashing dengan password_hash()
 * - Input validation dan sanitization
 * - XSS protection
 * - Unique constraints untuk username dan email
 * 
 * AUTHOR: VoteSphere Team
 * VERSION: 1.0
 * ================================================================
 */

// ================================================================
// SETUP AWAL & KONFIGURASI
// ================================================================
session_start();
require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/utils.php';

// ================================================================
// REDIRECT JIKA SUDAH LOGIN
// ================================================================
// Jika user sudah login, redirect ke homepage
if (Auth::check()) {
    header('Location: ' . APP_URL . '/public/index.php');
    exit;
}

// ================================================================
// INISIALISASI VARIABEL FORM
// ================================================================
$errors = [];                // Array untuk menyimpan error messages
$username_val = '';         // Nilai username untuk repopulate form
$email_val = '';            // Nilai email untuk repopulate form

// ================================================================
// PROSES FORM SUBMISSION
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ================================================================
    // AMBIL DAN SANITASI INPUT FORM
    // ================================================================
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Simpan nilai untuk repopulate form jika ada error
    $username_val = $username;
    $email_val = $email;

    // ================================================================
    // VALIDASI INPUT - USERNAME
    // ================================================================
    if (empty($username)) {
        $errors[] = 'Username is required.';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters long.';
    } elseif (strlen($username) > 50) {
        $errors[] = 'Username is too long (max 50 characters).';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = 'Username can only contain letters, numbers, and underscores.';
    }

    // ================================================================
    // VALIDASI INPUT - EMAIL
    // ================================================================
    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (strlen($email) > 100) {
        $errors[] = 'Email is too long (max 100 characters).';
    }

    // ================================================================
    // VALIDASI INPUT - PASSWORD
    // ================================================================
    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    } elseif (strlen($password) > 255) {
        $errors[] = 'Password is too long (max 255 characters).';
    }

    // Validasi password strength (optional - bisa disesuaikan kebutuhan)
    if (!empty($password)) {
        $hasLower = preg_match('/[a-z]/', $password);
        $hasUpper = preg_match('/[A-Z]/', $password);
        $hasNumber = preg_match('/[0-9]/', $password);

        if (!$hasLower || !$hasUpper || !$hasNumber) {
            $errors[] = 'Password must contain at least one lowercase letter, one uppercase letter, and one number.';
        }
    }

    // ================================================================
    // VALIDASI KONFIRMASI PASSWORD
    // ================================================================
    if (empty($confirm_password)) {
        $errors[] = 'Please confirm your password.';
    } elseif ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    // ================================================================
    // VALIDASI UNIQUENESS - DATABASE CHECK
    // ================================================================
    if (empty($errors)) {
        try {
            $pdo = Database::getInstance();

            // Cek apakah username sudah digunakan
            $stmtCheckUsername = $pdo->prepare("SELECT 1 FROM users WHERE username = ?");
            $stmtCheckUsername->execute([$username]);
            if ($stmtCheckUsername->fetchColumn()) {
                $errors[] = 'Username is already taken. Please choose a different one.';
            }

            // Cek apakah email sudah digunakan
            $stmtCheckEmail = $pdo->prepare("SELECT 1 FROM users WHERE email = ?");
            $stmtCheckEmail->execute([$email]);
            if ($stmtCheckEmail->fetchColumn()) {
                $errors[] = 'Email is already registered. Please use a different email or try logging in.';
            }
        } catch (Exception $e) {
            error_log('Registration uniqueness check error: ' . $e->getMessage());
            $errors[] = 'Error checking username/email availability. Please try again.';
        }
    }

    // ================================================================
    // PROSES REGISTRASI JIKA TIDAK ADA ERROR
    // ================================================================
    if (empty($errors)) {
        try {
            $pdo = Database::getInstance();

            // ================================================================
            // HASH PASSWORD DENGAN SECURITY TINGGI
            // ================================================================
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // ================================================================
            // INSERT USER BARU KE DATABASE
            // ================================================================
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password_hash, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$username, $email, $hashedPassword]);

            // ================================================================
            // AUTO-LOGIN SETELAH REGISTRASI BERHASIL
            // ================================================================
            if (Auth::login($username, $password)) {
                $_SESSION['toast'] = [
                    'message' => 'Registration successful! Welcome to ' . APP_NAME . '!',
                    'type' => 'success'
                ];

                // Redirect ke homepage
                header('Location: ' . APP_URL . '/public/index.php');
                exit;
            } else {
                // Fallback jika auto-login gagal
                $_SESSION['toast'] = [
                    'message' => 'Registration successful! Please log in with your new account.',
                    'type' => 'success'
                ];
                header('Location: ' . APP_URL . '/src/login.php');
                exit;
            }
        } catch (Exception $e) {
            // ================================================================
            // ERROR HANDLING - REGISTRASI GAGAL
            // ================================================================
            error_log('Registration error: ' . $e->getMessage());

            // Cek apakah error karena duplicate key (race condition)
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                if (strpos($e->getMessage(), 'username') !== false) {
                    $errors[] = 'Username is already taken. Please choose a different one.';
                } else {
                    $errors[] = 'Email is already registered. Please use a different email.';
                }
            } else {
                $errors[] = 'Error creating account. Please try again.';
            }
        }
    }
}

// ================================================================
// SETUP UNTUK TEMPLATE HTML
// ================================================================
$pageTitle = 'Register';
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
        [x-cloak] {
            display: none !important;
        }

        /* Custom password strength indicator */
        .password-strength {
            height: 4px;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .strength-weak {
            background-color: #ef4444;
        }

        .strength-medium {
            background-color: #f59e0b;
        }

        .strength-strong {
            background-color: #10b981;
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

                passwordStrength: 0, // 0: weak, 1: medium, 2: strong
                showPassword: false,

                // ================================================================
                // INITIALIZATION
                // ================================================================
                init() {
                    // Dark mode watcher
                    this.$watch('darkMode', val => {
                        localStorage.setItem('darkMode', val);
                        document.documentElement.classList.toggle('dark', val);
                    });

                    // Set initial dark mode
                    document.documentElement.classList.toggle('dark', this.darkMode);

                    // Initialize Feather icons
                    feather.replace();

                    // Show session toast if any
                    <?php if (isset($_SESSION['toast'])): ?>
                        this.showToast(
                            <?= json_encode($_SESSION['toast']['message']) ?>,
                            <?= json_encode($_SESSION['toast']['type']) ?>
                        );
                        <?php unset($_SESSION['toast']); ?>
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
                 * Check password strength
                 */
                checkPasswordStrength(password) {
                    let score = 0;

                    // Length check
                    if (password.length >= 6) score++;

                    // Character variety checks
                    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
                    if (/[0-9]/.test(password)) score++;
                    if (/[^a-zA-Z0-9]/.test(password)) score++; // Special characters

                    // Bonus for longer passwords
                    if (password.length >= 12) score++;

                    // Normalize to 0-2 scale
                    this.passwordStrength = Math.min(Math.floor(score / 2), 2);
                },

                /**
                 * Get password strength text
                 */
                getPasswordStrengthText() {
                    switch (this.passwordStrength) {
                        case 0:
                            return 'Weak';
                        case 1:
                            return 'Medium';
                        case 2:
                            return 'Strong';
                        default:
                            return '';
                    }
                },

                /**
                 * Get password strength class
                 */
                getPasswordStrengthClass() {
                    switch (this.passwordStrength) {
                        case 0:
                            return 'strength-weak';
                        case 1:
                            return 'strength-medium';
                        case 2:
                            return 'strength-strong';
                        default:
                            return '';
                    }
                },

                /**
                 * Toggle password visibility
                 */
                togglePasswordVisibility() {
                    this.showPassword = !this.showPassword;
                },

                /**
                 * Show toast notification
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
                        gravity: "top",
                        position: "right",
                        style: colors[type] || colors['info']
                    }).showToast();
                }
            };
        }
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
        <!-- MAIN CONTENT - REGISTRATION FORM -->
        <!-- ================================================================ -->
        <main class="flex-grow flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 p-8 sm:p-10 rounded-xl shadow-2xl w-full max-w-md">

                <!-- ================================================================ -->
                <!-- HEADER SECTION -->
                <!-- ================================================================ -->
                <div class="text-center mb-8">
                    <h1 class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">
                        Join <?= APP_NAME ?>
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">
                        Create your account to start creating and participating in polls.
                    </p>
                </div>

                <!-- ================================================================ -->
                <!-- ERROR MESSAGES DISPLAY -->
                <!-- ================================================================ -->
                <?php if (!empty($errors)): ?>
                    <div class="mb-6 p-4 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-600 rounded-lg">
                        <div class="flex items-center mb-2">
                            <i data-feather="alert-circle" class="w-5 h-5 text-red-600 dark:text-red-400 mr-2"></i>
                            <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                                Please fix the following errors:
                            </h3>
                        </div>
                        <ul class="list-disc list-inside text-sm text-red-700 dark:text-red-300 space-y-1">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- ================================================================ -->
                <!-- REGISTRATION FORM -->
                <!-- ================================================================ -->
                <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="space-y-6">

                    <!-- Username Field -->
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Username
                        </label>
                        <input id="username"
                            name="username"
                            type="text"
                            autocomplete="username"
                            required
                            minlength="3"
                            maxlength="50"
                            pattern="[a-zA-Z0-9_]+"
                            value="<?= htmlspecialchars($username_val) ?>"
                            class="appearance-none relative block w-full px-3 py-3 border border-gray-300 dark:border-gray-600 placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm bg-white dark:bg-gray-700 transition-colors"
                            placeholder="Choose a unique username">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            3-50 characters, letters, numbers, and underscores only
                        </p>
                    </div>

                    <!-- Email Field -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Email Address
                        </label>
                        <input id="email"
                            name="email"
                            type="email"
                            autocomplete="email"
                            required
                            maxlength="100"
                            value="<?= htmlspecialchars($email_val) ?>"
                            class="appearance-none relative block w-full px-3 py-3 border border-gray-300 dark:border-gray-600 placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm bg-white dark:bg-gray-700 transition-colors"
                            placeholder="Enter your email address">
                    </div>

                    <!-- Password Field with Strength Indicator -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Password
                        </label>
                        <div class="relative">
                            <input id="password"
                                name="password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="new-password"
                                required
                                minlength="6"
                                @input="checkPasswordStrength($event.target.value)"
                                class="appearance-none relative block w-full px-3 py-3 pr-10 border border-gray-300 dark:border-gray-600 placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm bg-white dark:bg-gray-700 transition-colors"
                                placeholder="Create a strong password">

                            <!-- Password Visibility Toggle -->
                            <button type="button"
                                @click="togglePasswordVisibility()"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center focus:outline-none">
                                <i data-feather="eye" x-show="!showPassword" class="w-5 h-5 text-gray-400 hover:text-gray-600"></i>
                                <i data-feather="eye-off" x-show="showPassword" class="w-5 h-5 text-gray-400 hover:text-gray-600"></i>
                            </button>
                        </div>

                        <!-- Password Strength Indicator -->
                        <div class="mt-2">
                            <div class="flex items-center space-x-2">
                                <div class="flex-1 password-strength" :class="getPasswordStrengthClass()"></div>
                                <span class="text-xs font-medium"
                                    :class="{
                                          'text-red-600 dark:text-red-400': passwordStrength === 0,
                                          'text-yellow-600 dark:text-yellow-400': passwordStrength === 1,
                                          'text-green-600 dark:text-green-400': passwordStrength === 2
                                      }"
                                    x-text="getPasswordStrengthText()"></span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Use at least 6 characters with uppercase, lowercase, and numbers
                            </p>
                        </div>
                    </div>

                    <!-- Confirm Password Field -->
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Confirm Password
                        </label>
                        <input id="confirm_password"
                            name="confirm_password"
                            type="password"
                            autocomplete="new-password"
                            required
                            class="appearance-none relative block w-full px-3 py-3 border border-gray-300 dark:border-gray-600 placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm bg-white dark:bg-gray-700 transition-colors"
                            placeholder="Confirm your password">
                    </div>

                    <!-- Submit Button -->
                    <div>
                        <button type="submit"
                            class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800 transition-all duration-200 ease-in-out transform hover:scale-105 active:scale-95">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <i data-feather="user-plus" class="h-5 w-5 text-indigo-500 group-hover:text-indigo-400"></i>
                            </span>
                            Create Account
                        </button>
                    </div>
                </form>

                <!-- ================================================================ -->
                <!-- LOGIN LINK -->
                <!-- ================================================================ -->
                <div class="mt-8 text-center">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Already have an account?
                        <a href="<?= APP_URL ?>/src/login.php"
                            class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300 transition-colors">
                            Sign in here
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
        // Initialize Feather icons
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
1. Password hashing dengan PASSWORD_DEFAULT
2. Input validation yang comprehensive
3. Uniqueness validation untuk username dan email
4. XSS protection dengan htmlspecialchars
5. Race condition handling untuk duplicate entries

FITUR UX:
1. Password strength indicator real-time
2. Password visibility toggle
3. Form repopulation jika ada error
4. Responsive design
5. Dark mode support
6. Auto-login setelah registrasi berhasil

VALIDASI BUSINESS RULES:
1. Username: 3-50 karakter, alphanumeric + underscore
2. Email: valid email format, max 100 karakter
3. Password: min 6 karakter, harus ada uppercase, lowercase, number
4. Password confirmation: harus sama dengan password
5. Uniqueness: username dan email harus unik

OPTIMASI PERFORMA:
1. Efficient database queries
2. Client-side validation untuk UX
3. Server-side validation untuk security
4. Proper error handling dan logging
-->