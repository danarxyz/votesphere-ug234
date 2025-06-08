<?php

/**
 * ================================================================
 * EXPORT CSV - HASIL VOTING POLLING
 * ================================================================
 * 
 * File: export_csv.php
 * Fungsi: Generate dan download hasil voting dalam format CSV
 * 
 * FITUR UTAMA:
 * - Export hasil voting ke format CSV
 * - Validasi akses dan permissions
 * - Format CSV yang rapi dan readable
 * - Header yang informatif
 * 
 * KEAMANAN:
 * - Authentication required
 * - Authorization check (sama dengan results.php)
 * - Input validation dan sanitization
 * - Proper error handling
 * 
 * AUTHOR: VoteSphere Team
 * VERSION: 1.0
 * ================================================================
 */

// ================================================================
// SETUP AWAL & KONFIGURASI
// ================================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/../src/Auth.php';

// Mulai session jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// KONTROL AKSES - WAJIB LOGIN
// ================================================================
if (!Auth::check()) {
    $_SESSION['toast'] = [
        'message' => 'Please log in to export results.',
        'type' => 'warning'
    ];
    header('Location: ' . APP_URL . '/src/login.php');
    exit;
}

// ================================================================
// VALIDASI INPUT
// ================================================================
$pollId = isset($_GET['poll_id']) ? (int)$_GET['poll_id'] : 0;
if ($pollId <= 0) {
    $_SESSION['toast'] = [
        'message' => 'Invalid poll ID.',
        'type' => 'error'
    ];
    header('Location: ' . APP_URL . '/public/index.php');
    exit;
}

$currentUserId = Auth::id();

try {
    // ================================================================
    // KONEKSI DATABASE & VALIDASI POLLING
    // ================================================================
    $pdo = Database::getInstance();

    // Query data polling
    $stmt = $pdo->prepare("
        SELECT p.*, u.username as creator_name 
        FROM polls p 
        JOIN users u ON p.creator_id = u.user_id 
        WHERE p.poll_id = ?
    ");
    $stmt->execute([$pollId]);
    $poll = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$poll) {
        $_SESSION['toast'] = [
            'message' => 'Poll not found.',
            'type' => 'error'
        ];
        header('Location: ' . APP_URL . '/public/index.php');
        exit;
    }

    // ================================================================
    // VALIDASI HAK AKSES 
    // ================================================================

    // Cek status polling
    $pollClosed = !empty($poll['end_time']) && strtotime($poll['end_time']) <= time();
    $isCreator = ($poll['creator_id'] == $currentUserId);

    // Cek apakah user sudah vote
    $stmtUserVote = $pdo->prepare("
        SELECT 1 FROM votes v 
        JOIN poll_options po ON v.option_id = po.option_id 
        WHERE po.poll_id = ? AND v.user_id = ?
    ");
    $stmtUserVote->execute([$pollId, $currentUserId]);
    $userHasVoted = (bool)$stmtUserVote->fetchColumn();

    // Validasi akses - harus memenuhi salah satu syarat
    if (!$userHasVoted && !$pollClosed && !$isCreator) {
        $_SESSION['toast'] = [
            'message' => 'You must vote first to export results.',
            'type' => 'warning'
        ];
        header('Location: ' . APP_URL . '/src/vote.php?poll_id=' . $pollId);
        exit;
    }

    // ================================================================
    // QUERY HASIL VOTING
    // ================================================================
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
    $totalVotes = 0;
    foreach ($results as $result) {
        $totalVotes += (int)$result['vote_count'];
    }

    // Hitung persentase
    foreach ($results as &$result) {
        $result['vote_count'] = (int)$result['vote_count'];
        $result['percentage'] = $totalVotes > 0 ?
            round(($result['vote_count'] / $totalVotes) * 100, 1) : 0;
    }
    unset($result);

    // ================================================================
    // GENERATE CSV FILE
    // ================================================================

    // Setup filename dengan timestamp
    $filename = 'poll-results-' . $pollId . '-' . date('Y-m-d-H-i-s') . '.csv';

    // Set headers untuk download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');

    // Buka output stream
    $output = fopen('php://output', 'w');

    // Set BOM untuk Unicode support (Excel compatibility)
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // ================================================================
    // TULIS HEADER INFORMASI POLLING
    // ================================================================
    fputcsv($output, ['=== POLL RESULTS EXPORT ===']);
    fputcsv($output, ['Export Date', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Poll ID', $pollId]);
    fputcsv($output, ['Poll Title', $poll['title']]);
    fputcsv($output, ['Poll Creator', $poll['creator_name']]);
    fputcsv($output, ['Poll Created', $poll['created_at']]);

    if (!empty($poll['end_time'])) {
        fputcsv($output, ['Poll End Time', $poll['end_time']]);
        fputcsv($output, ['Poll Status', $pollClosed ? 'Closed' : 'Active']);
    } else {
        fputcsv($output, ['Poll End Time', 'No end time']);
        fputcsv($output, ['Poll Status', 'Active (No expiry)']);
    }

    if (!empty($poll['description'])) {
        fputcsv($output, ['Poll Description', $poll['description']]);
    }

    fputcsv($output, ['Total Votes', $totalVotes]);
    fputcsv($output, []); // Baris kosong

    // ================================================================
    // TULIS HEADER TABEL HASIL
    // ================================================================
    fputcsv($output, ['=== VOTING RESULTS ===']);
    fputcsv($output, ['Rank', 'Option', 'Votes', 'Percentage']);

    // ================================================================
    // TULIS DATA HASIL VOTING
    // ================================================================
    $rank = 1;
    foreach ($results as $result_csv) {
        fputcsv($output, [
            $rank,
            $result_csv['option_text'],
            $result_csv['vote_count'],
            $result_csv['percentage'] . '%'
        ]);
        $rank++;
    }

    // ================================================================
    // TULIS FOOTER INFORMASI
    // ================================================================
    fputcsv($output, []); // Baris kosong
    fputcsv($output, ['=== EXPORT INFO ===']);
    fputcsv($output, ['Exported by', Auth::user()['username']]);
    fputcsv($output, ['Export timestamp', date('Y-m-d H:i:s T')]);
    fputcsv($output, ['Application', APP_NAME]);
    fputcsv($output, ['Version', '1.0']);

    // Tutup stream
    fclose($output);
    exit; // Hentikan eksekusi setelah export

} catch (Exception $e) {
    // ================================================================
    // ERROR HANDLING
    // ================================================================
    error_log('CSV Export error: ' . $e->getMessage());
    $_SESSION['toast'] = [
        'message' => 'Error exporting CSV. Please try again.',
        'type' => 'error'
    ];
    header('Location: ' . APP_URL . '/src/results.php?poll_id=' . $pollId);
    exit;
}
