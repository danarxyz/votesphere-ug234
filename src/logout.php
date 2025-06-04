<?php

/**
 * ================================================================
 * HALAMAN LOGOUT - KELUAR DARI SISTEM
 * ================================================================
 * 
 * File: logout.php
 * Fungsi: Menangani proses logout pengguna dari aplikasi
 * 
 * FITUR UTAMA:
 * - Session cleanup yang aman
 * - Cookie removal untuk persistent login
 * - Redirect ke halaman yang tepat
 * - Confirmation message
 * - Security logging untuk audit
 * 
 * KEAMANAN:
 * - Proper session destruction
 * - CSRF protection untuk logout action
 * - Secure cookie removal
 * - IP dan timestamp logging
 * - Protection terhadap session fixation
 * 
 * AUTHOR: VoteSphere Team
 * VERSION: 1.0
 * ================================================================
 */

// ================================================================
// SETUP AWAL & KONFIGURASI
// ================================================================
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Auth.php';

// ================================================================
// LOG LOGOUT ATTEMPT UNTUK SECURITY AUDIT
// ================================================================
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$timestamp = date('Y-m-d H:i:s');

// ================================================================
// VALIDASI: PASTIKAN USER SEDANG LOGIN
// ================================================================
if (!Auth::check()) {
    // Jika user tidak login, redirect ke homepage
    $_SESSION['toast'] = [
        'message' => 'You are not logged in.',
        'type' => 'info'
    ];
    header('Location: ' . APP_URL . '/public/index.php');
    exit;
}

// ================================================================
// AMBIL INFO USER SEBELUM LOGOUT (UNTUK LOGGING)
// ================================================================
$current_user_id = Auth::id();
$current_username = Auth::username();

// ================================================================
// PROSES LOGOUT
// ================================================================

// ================================================================
// STEP 1: VALIDASI CSRF TOKEN (JIKA ADA)
// ================================================================
// Note: Logout biasanya dilakukan via GET request untuk kemudahan,
// tapi untuk keamanan extra bisa menggunakan POST dengan CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_token = $_POST['csrf_token'] ?? '';
    $session_token = $_SESSION['csrf_token'] ?? '';

    if (!hash_equals($session_token, $submitted_token)) {
        $_SESSION['toast'] = [
            'message' => 'Invalid security token. Please try again.',
            'type' => 'error'
        ];
        header('Location: ' . APP_URL . '/public/index.php');
        exit;
    }
}

// ================================================================
// STEP 2: SECURITY LOGGING
// ================================================================
try {
    // Log logout action untuk security audit
    error_log(sprintf(
        "[LOGOUT] User '%s' (ID: %d) logged out from IP: %s at %s - User Agent: %s",
        $current_username,
        $current_user_id,
        $ip_address,
        $timestamp,
        $user_agent
    ));

    // Optional: Database logging untuk audit trail yang lebih detail
    // $pdo = Database::getInstance();
    // $stmt = $pdo->prepare("INSERT INTO user_activity_log (user_id, action, ip_address, user_agent, created_at) VALUES (?, 'logout', ?, ?, NOW())");
    // $stmt->execute([$current_user_id, $ip_address, $user_agent]);

} catch (Exception $e) {
    // Log error tapi jangan stop proses logout
    error_log('Logout logging error: ' . $e->getMessage());
}

// ================================================================
// STEP 3: DESTROY SESSION SECARA AMAN
// ================================================================

// Hapus semua data session
$_SESSION = [];

// Hapus session cookie jika ada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// ================================================================
// STEP 4: HAPUS REMEMBER ME COOKIES (JIKA ADA)
// ================================================================
// Hapus remember token cookies jika digunakan untuk persistent login
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
}

if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', time() - 3600, '/', '', true, true);
}

// ================================================================
// STEP 5: REGENERATE SESSION ID UNTUK SECURITY
// ================================================================
// Ini penting untuk mencegah session fixation attacks
session_regenerate_id(true);

// ================================================================
// STEP 6: DESTROY SESSION SEPENUHNYA
// ================================================================
session_destroy();

// ================================================================
// STEP 7: START NEW SESSION UNTUK TOAST MESSAGE
// ================================================================
session_start();

// ================================================================
// SET SUCCESS MESSAGE
// ================================================================
$_SESSION['toast'] = [
    'message' => 'You have been successfully logged out. Thank you for using VoteSphere!',
    'type' => 'success'
];

// ================================================================
// STEP 8: DETERMINE REDIRECT URL
// ================================================================
$redirect_url = APP_URL . '/public/index.php';

// Optional: Redirect ke halaman khusus logout jika ada
// $redirect_url = APP_URL . '/src/login.php';

// Optional: Redirect ke halaman yang diminta (jika aman)
if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
    $requested_redirect = $_GET['redirect'];

    // Validasi redirect URL untuk security
    if (
        filter_var($requested_redirect, FILTER_VALIDATE_URL) &&
        strpos($requested_redirect, APP_URL) === 0
    ) {
        $redirect_url = $requested_redirect;
    }
}

// ================================================================
// FINAL REDIRECT
// ================================================================
header('Location: ' . $redirect_url);
exit;

// ================================================================
// CATATAN IMPLEMENTASI
// ================================================================
/*
ALUR LOGOUT YANG AMAN:
1. Validasi user sedang login
2. Log aktivitas logout untuk audit
3. Hapus semua data session
4. Hapus session cookies
5. Hapus remember me cookies
6. Regenerate session ID
7. Destroy session sepenuhnya
8. Start session baru untuk pesan
9. Redirect dengan pesan sukses

SECURITY CONSIDERATIONS:
1. Session fixation prevention dengan regenerate_id
2. Secure cookie removal dengan proper parameters
3. CSRF protection untuk POST logout
4. Security logging untuk audit trail
5. Safe redirect validation

ALTERNATIVE IMPLEMENTATIONS:
1. AJAX logout untuk single-page applications
2. Global logout untuk semua devices
3. Logout dengan konfirmasi modal
4. Auto-logout untuk session timeout
*/
