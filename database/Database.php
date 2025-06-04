<?php

/**
 * ================================================================
 * KELAS DATABASE CONNECTION - SINGLETON PATTERN
 * ================================================================
 * 
 * File: Database.php
 * Fungsi: Mengelola koneksi database menggunakan PDO
 * 
 * FITUR UTAMA:
 * - Singleton pattern untuk satu instance koneksi
 * - Lazy loading koneksi database
 * - Error handling yang comprehensive
 * - Konfigurasi PDO yang optimal untuk keamanan
 * 
 * KEAMANAN:
 * - Prepared statements default
 * - Error mode exception untuk debugging
 * - Disable emulated prepares untuk keamanan
 * 
 * AUTHOR: VoteSphere Team
 * VERSION: 1.0
 * ================================================================
 */

class Database
{
    // ================================================================
    // PROPERTI KELAS
    // ================================================================
    private static ?PDO $instance = null;    // Instance tunggal PDO (Singleton)

    // ================================================================
    // KONSTRUKTOR PRIVATE - MENCEGAH INSTANSIASI LANGSUNG
    // ================================================================
    private function __construct()
    {
        // Konstruktor private untuk mencegah pembuatan instance langsung
        // Hanya bisa diakses melalui getInstance()
    }

    private function __clone()
    {
        // Mencegah cloning object untuk mempertahankan singleton
    }

    // ================================================================
    // METHOD UTAMA - GET INSTANCE (SINGLETON PATTERN)
    // ================================================================
    /**
     * Mendapatkan instance koneksi database (Singleton Pattern)
     * 
     * Pattern Singleton memastikan hanya ada satu koneksi database
     * di seluruh aplikasi, menghemat resource dan mencegah konflik
     * 
     * @return PDO Instance koneksi database
     * @throws RuntimeException Jika koneksi gagal
     */
    public static function getInstance(): PDO
    {
        // Cek apakah instance sudah ada
        if (self::$instance === null) {
            // ================================================================
            // LOAD KONFIGURASI DATABASE
            // ================================================================
            $config = require __DIR__ . '/db_config.php';

            // Build Data Source Name (DSN) untuk MySQL
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";

            try {
                // ================================================================
                // BUAT KONEKSI PDO DENGAN KONFIGURASI OPTIMAL
                // ================================================================
                self::$instance = new PDO(
                    $dsn,                    // Data Source Name
                    $config['user'],         // Username database
                    $config['pass'],         // Password database
                    [
                        // ================================================================
                        // KONFIGURASI PDO UNTUK KEAMANAN & PERFORMA
                        // ================================================================

                        // Error handling: Lempar exception untuk semua error
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

                        // Default fetch mode: Return associative array
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                        // Disable emulated prepares untuk keamanan ekstra
                        // Menggunakan real prepared statements dari MySQL
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );

                // Log sukses koneksi (hanya untuk development)
                error_log("Database connection established successfully");
            } catch (PDOException $e) {
                // ================================================================
                // ERROR HANDLING - KONEKSI GAGAL
                // ================================================================

                // Log error detail untuk debugging (jangan expose ke user)
                error_log("Database connection failed: " . $e->getMessage());

                // Throw exception dengan pesan user-friendly
                throw new RuntimeException("Connection failed: " . $e->getMessage());
            }
        }

        return self::$instance;
    }

    // ================================================================
    // METHOD TAMBAHAN - UTILITY FUNCTIONS
    // ================================================================

    /**
     * Test koneksi database
     * 
     * @return bool True jika koneksi berhasil
     */
    public static function testConnection(): bool
    {
        try {
            $pdo = self::getInstance();
            $stmt = $pdo->query("SELECT 1");
            return $stmt !== false;
        } catch (Exception $e) {
            error_log("Database test connection failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Tutup koneksi database (untuk cleanup)
     * 
     * @return void
     */
    public static function closeConnection(): void
    {
        self::$instance = null;
        error_log("Database connection closed");
    }
}

// ================================================================
// CATATAN PENGGUNAAN
// ================================================================
/*
 * CARA PENGGUNAAN:
 * 
 * // Dapatkan koneksi database
 * $pdo = Database::getInstance();
 * 
 * // Gunakan untuk query
 * $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
 * $stmt->execute([$userId]);
 * $user = $stmt->fetch();
 * 
 * KEUNTUNGAN SINGLETON PATTERN:
 * 1. Satu koneksi untuk seluruh aplikasi
 * 2. Hemat memory dan resource
 * 3. Konsistensi konfigurasi database
 * 4. Mudah untuk testing dan mocking
 */
// ================================================================
