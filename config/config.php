<?php

/**
 * ================================================================
 * KONFIGURASI APLIKASI - SETTINGS & CONSTANTS
 * ================================================================
 * 
 * File: config.php
 * Fungsi: Mengatur konfigurasi global aplikasi
 * 
 * FITUR UTAMA:
 * - Timezone configuration
 * - Database timezone settings
 * - Dynamic APP_URL detection
 * - Application constants
 * 
 * KEAMANAN:
 * - Secure path detection
 * - Environment-aware configuration
 * - Proper timezone handling
 * 
 * AUTHOR: VoteSphere Team
 * VERSION: 1.0
 * ================================================================
 */

// ================================================================
    // LOAD ENVIRONMENT VARIABLES
// ================================================================
function loadEnv($path)
{
    if (!file_exists($path)) {
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Skip comments
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, '"\'');

        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }
    return true;
}

// Load .env file
loadEnv(__DIR__ . '/../.env');

// ================================================================
// APPLICATION CONSTANTS DARI ENVIRONMENT
// ================================================================
define('APP_NAME', $_ENV['APP_NAME'] ?? 'VoteSphere');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'production');
define('DEBUG', filter_var($_ENV['DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN));

// ================================================================
// TIMEZONE CONFIGURATION
// ================================================================
$timezone = $_ENV['APP_TIMEZONE'] ?? 'Asia/Makassar';
date_default_timezone_set($timezone);
define('APP_DB_TIMEZONE', $_ENV['DB_TIMEZONE'] ?? $timezone);

// ================================================================
// DYNAMIC APP_URL DETECTION
// ================================================================
if (!defined('APP_URL')) {
    // Gunakan dari environment jika ada, atau deteksi otomatis
    if (!empty($_ENV['APP_URL'])) {
        define('APP_URL', rtrim($_ENV['APP_URL'], '/'));
    } else {
        // Fallback ke deteksi otomatis seperti sebelumnya
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $project_root_fs = dirname(__DIR__);
        $document_root_fs = $_SERVER['DOCUMENT_ROOT'];

        $web_path_segment = '';
        $normalized_doc_root = rtrim($document_root_fs, DIRECTORY_SEPARATOR);

        if (strpos($project_root_fs, $normalized_doc_root) === 0) {
            $web_path_segment = substr($project_root_fs, strlen($normalized_doc_root));
        }

        $web_path_segment = str_replace(DIRECTORY_SEPARATOR, '/', $web_path_segment);
        if (!empty($web_path_segment) && $web_path_segment[0] !== '/') {
            $web_path_segment = '/' . $web_path_segment;
        }

        define('APP_URL', rtrim($protocol . "://" . $host . $web_path_segment, '/'));
    }
}

// ================================================================
// SESSION CONFIGURATION
// ================================================================
if (APP_ENV === 'production') {
    ini_set('session.cookie_secure', $_ENV['SESSION_SECURE'] ?? '1');
    ini_set('session.cookie_httponly', $_ENV['SESSION_HTTPONLY'] ?? '1');
    ini_set('session.cookie_samesite', 'Strict');
}

// ================================================================
// ERROR REPORTING BASED ON ENVIRONMENT
// ================================================================
if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// ================================================================
// FILE UPLOAD CONFIGURATION
// ================================================================
define('MAX_UPLOAD_SIZE', (int)($_ENV['MAX_UPLOAD_SIZE'] ?? 2048) * 1024); // Convert to bytes
define('UPLOAD_PATH', __DIR__ . '/../' . ($_ENV['UPLOAD_PATH'] ?? 'uploads/'));

/**
 * ================================================================
 * KONFIGURASI APLIKASI - KEAMANAN
 * ================================================================
 */

// ================================================================
// SECURE PATH DETECTION
// ================================================================
if (APP_ENV === 'production') {
    // Hanya izinkan akses melalui APP_URL yang telah ditentukan
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($request_uri, '/..') !== false || realpath($_SERVER['SCRIPT_FILENAME']) !== realpath($_SERVER['DOCUMENT_ROOT'] . $request_uri)) {
        // Akses tidak sah, kirim 403 Forbidden
        header('HTTP/1.0 403 Forbidden');
        exit('403 Forbidden');
    }
}

// ================================================================
// SECURITY HEADERS
// ================================================================
if (APP_ENV === 'production') {
    // Contoh penambahan security headers
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header('Content-Security-Policy: default-src \'self\'');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
}

// ================================================================
// CATATAN DEPLOYMENT
// ================================================================
/*
 * CONTOH KONFIGURASI UNTUK BERBAGAI ENVIRONMENT:
 * 
 * DEVELOPMENT (localhost):
 * - APP_URL: http://localhost:8000
 * - DEBUG: true
 * - Error reporting: enabled
 * 
 * STAGING:
 * - APP_URL: https://staging.example.com/voting-app
 * - DEBUG: false
 * - Error reporting: disabled
 * - HTTPS: enforced
 * 
 * PRODUCTION:
 * - APP_URL: https://voting.example.com
 * - DEBUG: false
 * - Error reporting: disabled
 * - HTTPS: enforced
 * - Security headers: enabled
 * 
 * TIPS DEPLOYMENT:
 * 1. Gunakan environment variables untuk sensitive data
 * 2. Set proper file permissions (644 untuk files, 755 untuk directories)
 * 3. Disable directory listing di web server
 * 4. Gunakan HTTPS di production
 * 5. Set proper CSP headers
 */
// ================================================================
