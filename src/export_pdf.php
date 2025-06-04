<?php

/**
 * ================================================================
 * MODUL EKSPOR PDF HASIL POLLING
 * ================================================================
 * 
 * File: export_pdf.php
 * Deskripsi: Modul untuk mengekspor hasil polling ke format PDF
 * Versi: 1.0
 * Tanggal: Juni 2025
 * 
 * FITUR UTAMA:
 * - Ekspor hasil polling ke PDF dengan format yang rapi
 * - Kontrol akses yang ketat (hanya untuk yang berhak)
 * - Tampilan data yang konsisten dengan halaman hasil
 * - Error handling yang komprehensif
 * - Keamanan yang terjamin
 * 
 * PERSYARATAN AKSES:
 * User dapat mengekspor PDF jika memenuhi SALAH SATU kondisi:
 * 1. Pembuat polling (creator)
 * 2. Polling sudah berakhir (end_time tercapai)
 * 3. User sudah memberikan vote
 * 
 * DEPENDENCIES:
 * - mPDF library untuk generate PDF
 * - Database class untuk koneksi database
 * - Auth class untuk autentikasi
 * - Session untuk manajemen state
 * 
 * ================================================================
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/../src/Auth.php';

// ================================================================
// INISIALISASI SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// KONTROL AKSES UTAMA - HARUS LOGIN
// ================================================================
// Cek apakah user sudah login
if (!Auth::check()) {
    $_SESSION['toast'] = ['message' => 'Silakan login terlebih dahulu untuk mengekspor hasil.', 'type' => 'warning'];
    header('Location: ' . APP_URL . '/src/login.php');
    exit;
}

// ================================================================
// VALIDASI INPUT PARAMETER
// ================================================================
// Ambil dan validasi poll ID dari URL parameter
$pollId = isset($_GET['poll_id']) ? (int)$_GET['poll_id'] : 0;
if ($pollId <= 0) {
    $_SESSION['toast'] = ['message' => 'ID polling tidak valid.', 'type' => 'error'];
    header('Location: ' . APP_URL . '/public/index.php');
    exit;
}

// Ambil ID user yang sedang login
$currentUserId = Auth::id();

try {
    // ================================================================
    // KONEKSI DATABASE & QUERY DATA POLLING
    // ================================================================
    $pdo = Database::getInstance();

    // Query untuk mendapatkan data polling beserta nama creator
    $stmt = $pdo->prepare("
        SELECT p.*, u.username as creator_name 
        FROM polls p 
        JOIN users u ON p.creator_id = u.user_id 
        WHERE p.poll_id = ?
    ");
    $stmt->execute([$pollId]);
    $poll = $stmt->fetch(PDO::FETCH_ASSOC);

    // Cek apakah polling ditemukan
    if (!$poll) {
        $_SESSION['toast'] = ['message' => 'Polling tidak ditemukan.', 'type' => 'error'];
        header('Location: ' . APP_URL . '/public/index.php');
        exit;
    }

    // ================================================================
    // PENGECEKAN STATUS POLLING
    // ================================================================
    // Cek apakah polling sudah berakhir
    $pollClosed = !empty($poll['end_time']) && strtotime($poll['end_time']) <= time();
    
    // Cek apakah user adalah pembuat polling
    $isCreator = ($poll['creator_id'] == $currentUserId);

    // Cek apakah user sudah memberikan vote
    $stmtUserVote = $pdo->prepare("
        SELECT 1 FROM votes v 
        JOIN poll_options po ON v.option_id = po.option_id 
        WHERE po.poll_id = ? AND v.user_id = ?
    ");
    $stmtUserVote->execute([$pollId, $currentUserId]);
    $userHasVoted = (bool)$stmtUserVote->fetchColumn();

    // ================================================================
    // KONTROL AKSES KETAT - SAMA SEPERTI results.php
    // ================================================================
    // User dapat mengakses jika:
    // - Sudah vote, ATAU
    // - Polling sudah berakhir, ATAU  
    // - User adalah pembuat polling
    if (!$userHasVoted && !$pollClosed && !$isCreator) {
        $_SESSION['toast'] = ['message' => 'Anda harus vote terlebih dahulu untuk melihat hasil.', 'type' => 'warning'];
        header('Location: ' . APP_URL . '/src/vote.php?poll_id=' . $pollId);
        exit;
    }

    // ================================================================
    // QUERY HASIL VOTING
    // ================================================================
    // Query untuk mendapatkan hasil vote (sama seperti di results.php)
    $stmtResults = $pdo->prepare("
        SELECT 
            o.option_id, 
            o.option_text, 
            (SELECT COUNT(v.vote_id) FROM votes v WHERE v.option_id = o.option_id) as vote_count
        FROM poll_options o
        WHERE o.poll_id = ?
        ORDER BY vote_count DESC, o.option_id ASC
    ");
    $stmtResults->execute([$pollId]);
    $results = $stmtResults->fetchAll(PDO::FETCH_ASSOC);

    // ================================================================
    // KALKULASI STATISTIK
    // ================================================================
    // Hitung total votes
    $totalVotes = 0;
    foreach ($results as $result) {
        $totalVotes += (int)$result['vote_count'];
    }

    // Hitung persentase untuk setiap opsi
    foreach ($results as &$result) {
        $result['vote_count'] = (int)$result['vote_count'];
        $result['percentage'] = $totalVotes > 0 ?
            round(((int)$result['vote_count'] / $totalVotes) * 100, 1) : 0;
    }
    unset($result); // Lepas reference

} catch (Exception $e) {
    // ================================================================
    // ERROR HANDLING - DATABASE ERRORS
    // ================================================================
    error_log('PDF export error: ' . $e->getMessage());
    $_SESSION['toast'] = ['message' => 'Terjadi kesalahan saat mengekspor PDF. Silakan periksa log.', 'type' => 'error'];
    header('Location: ' . APP_URL . '/src/results.php?poll_id=' . $pollId);
    exit;
}

// ================================================================
// GENERATE HTML TEMPLATE UNTUK PDF
// ================================================================
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        /* CSS Styling untuk PDF */
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            line-height: 1.6;
        }
        .header { 
            text-align: center; 
            margin-bottom: 30px; 
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .poll-title { 
            font-size: 24px; 
            font-weight: bold; 
            color: #333; 
            margin-bottom: 10px; 
        }
        .poll-info { 
            font-size: 14px; 
            color: #666; 
            margin-bottom: 5px; 
        }
        .results-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .results-table th, .results-table td { 
            border: 1px solid #ddd; 
            padding: 12px; 
            text-align: left; 
        }
        .results-table th { 
            background-color: #f5f5f5; 
            font-weight: bold; 
            color: #333;
        }
        .percentage { 
            text-align: center; 
            font-weight: bold;
        }
        .vote-count { 
            text-align: center; 
            font-weight: bold;
        }
        .footer { 
            margin-top: 30px; 
            text-align: center; 
            font-size: 12px; 
            color: #888; 
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        .no-votes {
            text-align: center; 
            font-style: italic; 
            color: #666;
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>
    <!-- HEADER SECTION -->
    <div class="header">
        <div class="poll-title">' . htmlspecialchars($poll['title']) . ' - Hasil Polling</div>
        <div class="poll-info">Dibuat oleh: ' . htmlspecialchars($poll['creator_name']) . '</div>
        <div class="poll-info">Total Suara: ' . $totalVotes . '</div>
        <div class="poll-info">Dihasilkan pada: ' . date('d F Y, H:i') . ' WIB</div>';

// Tambahkan deskripsi jika ada
if (!empty($poll['description'])) {
    $html .= '<div class="poll-info">Deskripsi: ' . htmlspecialchars($poll['description']) . '</div>';
}

// Tambahkan waktu berakhir jika ada
if (!empty($poll['end_time'])) {
    $html .= '<div class="poll-info">Berakhir pada: ' . date('d F Y, H:i', strtotime($poll['end_time'])) . ' WIB</div>';
}

$html .= '
    </div>
    
    <!-- TABEL HASIL -->
    <table class="results-table">
        <thead>
            <tr>
                <th>Pilihan</th>
                <th class="vote-count">Jumlah Suara</th>
                <th class="percentage">Persentase</th>
            </tr>
        </thead>
        <tbody>';

// ================================================================
// GENERATE BARIS TABEL HASIL
// ================================================================
if ($totalVotes === 0) {
    // Jika belum ada yang vote
    $html .= '
            <tr>
                <td colspan="3" class="no-votes">Belum ada yang memberikan suara</td>
            </tr>';
} else {
    // Loop untuk setiap opsi polling
    foreach ($results as $result) {
        $html .= '
            <tr>
                <td>' . htmlspecialchars($result['option_text']) . '</td>
                <td class="vote-count">' . $result['vote_count'] . '</td>
                <td class="percentage">' . $result['percentage'] . '%</td>
            </tr>';
    }
}

$html .= '
        </tbody>
    </table>
    
    <!-- FOOTER -->
    <div class="footer">
        Dihasilkan oleh ' . APP_NAME . ' - Aplikasi Voting Online<br>
        Dokumen ini dibuat secara otomatis pada ' . date('d F Y H:i:s') . ' WIB
    </div>
</body>
</html>';

// ================================================================
// GENERATE PDF MENGGUNAKAN mPDF
// ================================================================
try {
    // Konfigurasi mPDF
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',              // Support UTF-8 untuk karakter Indonesia
        'format' => 'A4',               // Ukuran kertas A4
        'margin_left' => 15,            // Margin kiri 15mm
        'margin_right' => 15,           // Margin kanan 15mm
        'margin_top' => 16,             // Margin atas 16mm
        'margin_bottom' => 16,          // Margin bawah 16mm
        'margin_header' => 9,           // Margin header 9mm
        'margin_footer' => 9            // Margin footer 9mm
    ]);

    // Set metadata PDF
    $mpdf->SetTitle(htmlspecialchars($poll['title']) . ' - Hasil Polling');
    $mpdf->SetAuthor(APP_NAME);
    $mpdf->SetCreator(APP_NAME . ' PDF Export');
    $mpdf->SetSubject('Hasil Polling: ' . htmlspecialchars($poll['title']));

    // Write HTML ke PDF
    $mpdf->WriteHTML($html);

    // Generate nama file dengan format: poll-results-{ID}-{tanggal}.pdf
    $filename = 'hasil-polling-' . $pollId . '-' . date('Y-m-d') . '.pdf';
    
    // Output PDF untuk download
    $mpdf->Output($filename, 'D'); // 'D' untuk download langsung

} catch (Exception $e) {
    // ================================================================
    // ERROR HANDLING - PDF GENERATION ERRORS
    // ================================================================
    error_log('PDF generation error: ' . $e->getMessage());
    $_SESSION['toast'] = ['message' => 'Gagal membuat PDF. Silakan coba lagi.', 'type' => 'error'];
    header('Location: ' . APP_URL . '/src/results.php?poll_id=' . $pollId);
    exit;
}

/**
 * ================================================================
 * CATATAN PENGEMBANGAN
 * ================================================================
 * 
 * KEAMANAN:
 * - Input validation untuk poll_id
 * - Escape HTML untuk mencegah XSS
 * - Prepared statements untuk mencegah SQL injection
 * - Session-based authentication
 * - Access control yang ketat
 * 
 * PERFORMA:
 * - Query efisien dengan JOIN
 * - Minimal database calls
 * - Error handling yang proper
 * 
 * MAINTENANCE:
 * - Log error untuk debugging
 * - Consistent error messages
 * - Proper redirect flow
 * 
 * FUTURE IMPROVEMENTS:
 * - Add charts/graphs to PDF
 * - Configurable PDF templates
 * - Batch export multiple polls
 * - Add watermarks/logos
 * - Digital signatures for official reports
 * 
 * ================================================================
 */