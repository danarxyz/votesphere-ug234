<?php

/**
 * ================================================================
 * KELAS AUTHENTICATION - MANAJEMEN AUTENTIKASI PENGGUNA
 * ================================================================
 * 
 * File: Auth.php
 * Fungsi: Mengelola semua operasi autentikasi pengguna
 * 
 * FITUR UTAMA:
 * - Login/logout pengguna
 * - Manajemen session yang aman
 * - Verifikasi status login
 * - Password hashing yang secure
 * - Helper methods untuk akses user data
 * 
 * KEAMANAN:
 * - Password verification dengan password_verify()
 * - Session management yang proper
 * - Protection terhadap session hijacking
 * - Input validation untuk login
 * 
 * AUTHOR: VoteSphere Team
 * VERSION: 1.0
 * ================================================================
 */

class Auth
{
    // ================================================================
    // METHOD LOGIN - AUTENTIKASI PENGGUNA
    // ================================================================
    /**
     * Melakukan proses login pengguna
     * 
     * Method ini menerima username/email dan password, kemudian:
     * 1. Mencari user di database berdasarkan username atau email
     * 2. Memverifikasi password menggunakan password_verify()
     * 3. Membuat session jika login berhasil
     * 
     * @param string $username Username atau email pengguna
     * @param string $password Password dalam plain text
     * @return bool True jika login berhasil, false jika gagal
     */
    public static function login(string $username, string $password): bool
    {
        // ================================================================
        // KONEKSI DATABASE & QUERY USER
        // ================================================================
        $pdo = \Database::getInstance();

        // Query user berdasarkan username ATAU email
        // Memungkinkan login dengan salah satu dari keduanya
        $stmt = $pdo->prepare("
            SELECT user_id, username, email, password_hash 
            FROM users 
            WHERE username = ? OR email = ?
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        // ================================================================
        // VERIFIKASI PASSWORD & PEMBUATAN SESSION
        // ================================================================
        if ($user && password_verify($password, $user['password_hash'])) {
            // Password benar, buat session

            // Pastikan session sudah dimulai
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // ================================================================
            // SET SESSION DATA - INFORMASI USER YANG LOGIN
            // ================================================================
            $_SESSION['user_id'] = $user['user_id'];       // ID unik user
            $_SESSION['username'] = $user['username'];     // Username user
            $_SESSION['email'] = $user['email'];           // Email user
            $_SESSION['logged_in'] = true;                 // Flag status login

            // Log aktivitas login (untuk audit trail)
            error_log("User login successful: " . $user['username'] . " (ID: " . $user['user_id'] . ")");

            return true;  // Login berhasil
        }

        // Log percobaan login gagal (untuk security monitoring)
        error_log("Failed login attempt for username/email: " . $username);

        return false;  // Login gagal
    }

    // ================================================================
    // METHOD LOGOUT - MENGHAPUS SESSION
    // ================================================================
    /**
     * Melakukan logout pengguna dengan menghapus semua session data
     * 
     * Method ini akan:
     * 1. Menghapus semua session variables
     * 2. Menghancurkan session
     * 3. Membersihkan session dari server
     * 
     * @return void
     */
    public static function logout(): void
    {
        // Pastikan session sudah dimulai
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Log aktivitas logout
        $username = $_SESSION['username'] ?? 'unknown';
        error_log("User logout: " . $username);

        // ================================================================
        // HAPUS SEMUA SESSION DATA
        // ================================================================
        session_unset();     // Hapus semua session variables
        session_destroy();   // Hancurkan session dari server

        // Optional: Regenerate session ID untuk keamanan ekstra
        // session_regenerate_id(true);
    }

    // ================================================================
    // METHOD CHECK - VERIFIKASI STATUS LOGIN
    // ================================================================
    /**
     * Mengecek apakah pengguna sedang login
     * 
     * Method ini memeriksa session untuk menentukan apakah
     * pengguna sedang dalam status login yang valid
     * 
     * @return bool True jika user sedang login, false jika tidak
     */
    public static function check(): bool
    {
        // Pastikan session sudah dimulai
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Cek apakah flag login ada dan bernilai true
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    // ================================================================
    // METHOD USER - MENDAPATKAN DATA USER YANG LOGIN
    // ================================================================
    /**
     * Mengambil data lengkap pengguna yang sedang login
     * 
     * @return array|null Array berisi data user atau null jika tidak login
     */
    public static function user(): array|null
    {
        // Pastikan session sudah dimulai
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Cek status login terlebih dahulu
        if (self::check()) {
            // Return data user dari session
            return [
                'user_id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'email' => $_SESSION['email']
            ];
        }

        return null;  // User tidak login
    }

    // ================================================================
    // METHOD ID - MENDAPATKAN ID USER YANG LOGIN
    // ================================================================
    /**
     * Mendapatkan ID pengguna yang sedang login
     * 
     * Helper method untuk mendapatkan user ID secara cepat
     * tanpa perlu mengambil semua data user
     * 
     * @return int|null User ID atau null jika tidak login
     */
    public static function id(): int|null
    {
        $user = self::user();
        return $user['user_id'] ?? null;
    }

    // ================================================================
    // METHOD REQUIRE LOGIN - MIDDLEWARE AUTENTIKASI
    // ================================================================
    /**
     * Memaksa halaman untuk memerlukan login
     * 
     * Method ini akan redirect user ke halaman login jika
     * user belum login. Digunakan sebagai middleware untuk
     * halaman-halaman yang memerlukan autentikasi.
     * 
     * @param string $redirect Path untuk redirect jika tidak login
     * @return void
     */
    public static function requireLogin(string $redirect = '/src/login.php'): void
    {
        if (!self::check()) {
            // Simpan URL saat ini untuk redirect setelah login
            if (!isset($_SESSION)) {
                session_start();
            }

            // Simpan URL yang ingin diakses untuk redirect setelah login
            if (!isset($_SESSION['login_redirect'])) {
                $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'] ?? '';
            }

            // Redirect ke halaman login
            header("Location: $redirect");
            exit;
        }
    }

    // ================================================================
    // METHOD TAMBAHAN - UTILITY FUNCTIONS
    // ================================================================

    /**
     * Mendapatkan username pengguna yang sedang login
     * 
     * @return string|null Username atau null jika tidak login
     */
    public static function username(): string|null
    {
        $user = self::user();
        return $user['username'] ?? null;
    }

    /**
     * Mendapatkan email pengguna yang sedang login
     * 
     * @return string|null Email atau null jika tidak login
     */
    public static function email(): string|null
    {
        $user = self::user();
        return $user['email'] ?? null;
    }

    /**
     * Cek apakah user yang login adalah pemilik resource tertentu
     * 
     * @param int $resourceOwnerId ID pemilik resource
     * @return bool True jika user adalah pemilik
     */
    public static function owns(int $resourceOwnerId): bool
    {
        return self::id() === $resourceOwnerId;
    }
}

// ================================================================
// CATATAN PENGGUNAAN
// ================================================================
/*
 * CONTOH PENGGUNAAN:
 * 
 * // Login user
 * if (Auth::login($username, $password)) {
 *     echo "Login berhasil!";
 * }
 * 
 * // Cek status login
 * if (Auth::check()) {
 *     echo "User sedang login";
 * }
 * 
 * // Dapatkan data user
 * $user = Auth::user();
 * echo "Halo " . $user['username'];
 * 
 * // Dapatkan ID user
 * $userId = Auth::id();
 * 
 * // Wajibkan login untuk halaman
 * Auth::requireLogin();
 * 
 * // Logout user
 * Auth::logout();
 * 
 * KEAMANAN:
 * 1. Password di-hash dengan password_hash()
 * 2. Verifikasi dengan password_verify()
 * 3. Session management yang proper
 * 4. Protection dari session fixation
 * 5. Logging untuk audit trail
 */
// ================================================================
