<?php

/**
 * ================================================================
 * UTILITY FUNCTIONS - FUNGSI PEMBANTU APLIKASI
 * ================================================================
 * 
 * File: utils.php
 * Fungsi: Menyediakan fungsi-fungsi pembantu yang digunakan di seluruh aplikasi
 * 
 * FITUR UTAMA:
 * - Formatting tanggal dengan timezone handling
 * - Avatar URL generation
 * - Poll status checking
 * - Utility functions untuk UI
 * 
 * KEAMANAN:
 * - Timezone handling yang aman
 * - Input validation pada parameter
 * - Error handling untuk format tanggal
 * 
 * AUTHOR: VoteSphere Team
 * VERSION: 1.0
 * ================================================================
 */

// ================================================================
// FUNGSI AVATAR - GENERATE URL AVATAR PENGGUNA
// ================================================================
/**
 * Menghasilkan URL avatar untuk pengguna menggunakan layanan UI Avatars
 * 
 * Layanan ui-avatars.com menyediakan avatar dengan inisial nama pengguna
 * sebagai fallback jika pengguna tidak memiliki foto profil
 * 
 * @param int|string $userId ID pengguna (untuk konsistensi, meski tidak digunakan)
 * @param string $username Nama pengguna untuk inisial avatar
 * @return string URL avatar yang siap digunakan
 */
function get_user_avatar_url(int|string $userId, string $username = 'User'): string
{
    // ================================================================
    // KONFIGURASI AVATAR
    // ================================================================
    $baseUrl = 'https://ui-avatars.com/api/';
    $params = [
        'name' => $username,           // Nama untuk inisial
        'background' => 'random',      // Warna background random
        'color' => 'fff',             // Warna teks putih
        'size' => '128',              // Ukuran avatar 128x128px
        'font-size' => '0.6',         // Ukuran font relatif
        'rounded' => 'true'           // Avatar berbentuk bulat
    ];

    // Build query string dengan encoding yang proper
    $queryString = http_build_query($params);

    return $baseUrl . '?' . $queryString;
}

// ================================================================
// FUNGSI FORMAT TANGGAL - TIMEZONE AWARE DATE FORMATTING
// ================================================================
/**
 * Format tanggal dengan handling timezone yang proper
 * 
 * Fungsi ini mengonversi tanggal dari timezone database ke format
 * yang user-friendly dengan timezone detection
 * 
 * @param string|null $dateString String tanggal dari database
 * @param string $format Format output tanggal (PHP date format)
 * @return string Tanggal yang sudah diformat atau 'N/A' jika null
 */
if (!function_exists('formatDate')) {
    function formatDate(?string $dateString, string $format = 'D, M j, Y, g:i A T'): string
    {
        // ================================================================
        // VALIDASI INPUT
        // ================================================================
        if (!$dateString || empty(trim($dateString))) {
            return 'N/A';
        }

        try {
            // ================================================================
            // PARSING TANGGAL DENGAN TIMEZONE DATABASE
            // ================================================================
            // Buat DateTime object dengan timezone database
            $date = new DateTime($dateString, new DateTimeZone(APP_DB_TIMEZONE));

            // Format sesuai permintaan
            return $date->format($format);
        } catch (Exception $e) {
            // ================================================================
            // ERROR HANDLING - FALLBACK KE STRING ASLI
            // ================================================================
            error_log("Date formatting error for '$dateString': " . $e->getMessage());

            // Return string asli jika parsing gagal
            return $dateString;
        }
    }
}

// ================================================================
// FUNGSI STATUS POLLING - CEK APAKAH POLL SUDAH TUTUP
// ================================================================
/**
 * Mengecek apakah polling sudah tutup berdasarkan end_time
 * 
 * Fungsi ini mendukung input dalam bentuk:
 * - Array (data poll lengkap)
 * - String (end_time langsung)
 * - null (poll tanpa batas waktu)
 * 
 * @param array|string|null $poll Data poll atau end_time string
 * @return bool True jika poll sudah tutup, false jika masih aktif
 */
function isPollClosed(array|string|null $poll): bool
{
    // ================================================================
    // EKSTRAKSI END_TIME DARI BERBAGAI FORMAT INPUT
    // ================================================================
    $endTime = null;

    if (is_array($poll)) {
        // Input berupa array data poll
        $endTime = $poll['end_time'] ?? null;
    } elseif (is_string($poll)) {
        // Input berupa string end_time langsung
        $endTime = $poll;
    }
    // Jika null atau format lain, $endTime tetap null

    // ================================================================
    // VALIDASI - POLL TANPA BATAS WAKTU
    // ================================================================
    if (empty($endTime)) {
        // Poll tanpa end_time berarti tidak pernah tutup
        return false;
    }

    try {
        // ================================================================
        // PARSING TANGGAL DAN PERBANDINGAN WAKTU
        // ================================================================

        // Parse end_time dengan timezone database
        $endDateTime = new DateTime($endTime, new DateTimeZone(APP_DB_TIMEZONE));

        // Dapatkan waktu sekarang dalam UTC untuk perbandingan yang akurat
        $now = new DateTime('now', new DateTimeZone('UTC'));

        // Konversi end_time ke UTC untuk perbandingan apples-to-apples
        $endDateTime->setTimezone(new DateTimeZone('UTC'));

        // ================================================================
        // PERBANDINGAN WAKTU
        // ================================================================
        // Poll tutup jika end_time sudah lewat
        return $endDateTime < $now;
    } catch (Exception $e) {
        // ================================================================
        // ERROR HANDLING - LOG ERROR DAN RETURN SAFE DEFAULT
        // ================================================================
        error_log("Error in isPollClosed for end_time '$endTime': " . $e->getMessage());

        // Jika terjadi error parsing, anggap poll masih aktif (safe default)
        return false;
    }
}

// ================================================================
// FUNGSI HELPER TAMBAHAN - UTILITY FUNCTIONS
// ================================================================

/**
 * Membersihkan dan memvalidasi input teks
 * 
 * @param string $input Input teks dari user
 * @param int $maxLength Panjang maksimum yang diizinkan
 * @return string Teks yang sudah dibersihkan
 */
function sanitizeText(string $input, int $maxLength = 1000): string
{
    // Trim whitespace
    $input = trim($input);

    // Remove HTML tags untuk keamanan
    $input = strip_tags($input);

    // Batasi panjang
    if (strlen($input) > $maxLength) {
        $input = substr($input, 0, $maxLength);
    }

    return $input;
}

/**
 * Format angka voting untuk display yang user-friendly
 * 
 * @param int $count Jumlah votes
 * @return string Format yang mudah dibaca (misal: "1.2K votes")
 */
function formatVoteCount(int $count): string
{
    if ($count < 1000) {
        return $count . ' vote' . ($count != 1 ? 's' : '');
    } elseif ($count < 1000000) {
        return round($count / 1000, 1) . 'K votes';
    } else {
        return round($count / 1000000, 1) . 'M votes';
    }
}

/**
 * Generate random color untuk charts atau UI elements
 * 
 * @return string Hex color code
 */
function generateRandomColor(): string
{
    $colors = [
        '#3B82F6',
        '#EF4444',
        '#10B981',
        '#F59E0B',
        '#8B5CF6',
        '#EC4899',
        '#06B6D4',
        '#84CC16'
    ];

    return $colors[array_rand($colors)];
}

/**
 * Truncate teks dengan elipsis yang smart (tidak memotong kata)
 * 
 * @param string $text Teks yang akan dipotong
 * @param int $length Panjang maksimum
 * @param string $suffix Suffix yang ditambahkan (default: '...')
 * @return string Teks yang sudah dipotong
 */
function smartTruncate(string $text, int $length = 100, string $suffix = '...'): string
{
    if (strlen($text) <= $length) {
        return $text;
    }

    // Potong di posisi yang tidak memecah kata
    $truncated = substr($text, 0, $length);
    $lastSpace = strrpos($truncated, ' ');

    if ($lastSpace !== false) {
        $truncated = substr($truncated, 0, $lastSpace);
    }

    return $truncated . $suffix;
}

// ================================================================
// CATATAN PENGGUNAAN
// ================================================================
/*
 * CONTOH PENGGUNAAN UTILITY FUNCTIONS:
 * 
 * // Format tanggal
 * echo formatDate($poll['created_at']); // "Mon, Dec 25, 2023, 10:30 AM UTC"
 * 
 * // Cek status poll
 * if (isPollClosed($poll)) {
 *     echo "Poll sudah tutup";
 * }
 * 
 * // Generate avatar
 * $avatarUrl = get_user_avatar_url($userId, $username);
 * 
 * // Format vote count
 * echo formatVoteCount(1250); // "1.3K votes"
 * 
 * // Truncate teks
 * echo smartTruncate($longDescription, 150);
 * 
 * BEST PRACTICES:
 * 1. Selalu validasi input sebelum processing
 * 2. Gunakan try-catch untuk operasi yang bisa gagal
 * 3. Provide fallback values yang aman
 * 4. Log errors untuk debugging
 */
// ================================================================
