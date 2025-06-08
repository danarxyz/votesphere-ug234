<?php

/**
 * ================================================================
 * HALAMAN HASIL VOTING - VOTING APPLICATION
 * ================================================================
 * 
 * File: results.php
 * Fungsi: Menampilkan hasil voting dari suatu polling
 * 
 * FITUR UTAMA:
 * - Menampilkan hasil voting dengan persentase
 * - Kontrol akses ketat (harus sudah vote/poll tutup/pemilik poll)
 * - Export hasil ke CSV dan PDF
 * - Grafik visualisasi hasil
 * 
 * KEAMANAN:
 * - Validasi login wajib
 * - Verifikasi hak akses sebelum menampilkan hasil
 * - Sanitasi input dan output
 * - Prepared statements untuk query database
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
// Halaman ini hanya bisa diakses oleh user yang sudah login
if (!Auth::check()) {
    $_SESSION['toast'] = [
        'message' => 'Please log in to view results.',
        'type' => 'warning'
    ];
    header('Location: ' . APP_URL . '/src/login.php');
    exit;
}

// ================================================================
// VALIDASI INPUT & INISIALISASI VARIABEL
// ================================================================
// Ambil dan validasi poll ID dari parameter URL
$pollId = isset($_GET['poll_id']) ? (int)$_GET['poll_id'] : 0;
if ($pollId <= 0) {
    $_SESSION['toast'] = [
        'message' => 'Invalid poll ID.',
        'type' => 'error'
    ];
    header('Location: ' . APP_URL . '/public/index.php');
    exit;
}

// Inisialisasi variabel global untuk halaman
$currentUserId = Auth::id();           // ID user yang sedang login
$poll = null;                          // Data polling
$results = [];                         // Hasil voting
$totalVotes = 0;                       // Total suara
$userHasVoted = false;                 // Status apakah user sudah vote
$pollClosed = false;                   // Status apakah poll sudah tutup
$isCreator = false;                    // Status apakah user adalah pembuat poll

try {
    // ================================================================
    // KONEKSI DATABASE & QUERY DATA POLLING
    // ================================================================
    $pdo = Database::getInstance();

    // Query 1: Ambil data polling beserta informasi pembuat
    $stmt = $pdo->prepare("
        SELECT p.*, u.username as creator_name 
        FROM polls p 
        JOIN users u ON p.creator_id = u.user_id 
        WHERE p.poll_id = ?
    ");
    $stmt->execute([$pollId]);
    $poll = $stmt->fetch(PDO::FETCH_ASSOC);

    // Validasi: Pastikan polling ditemukan
    if (!$poll) {
        $_SESSION['toast'] = [
            'message' => 'Poll not found.',
            'type' => 'error'
        ];
        header('Location: ' . APP_URL . '/public/index.php');
        exit;
    }

    // ================================================================
    // PENGECEKAN STATUS POLLING & HAK AKSES
    // ================================================================

    // Cek apakah polling sudah tutup berdasarkan end_time
    $pollClosed = !empty($poll['end_time']) && strtotime($poll['end_time']) <= time();

    // Cek apakah user adalah pembuat polling
    $isCreator = ($poll['creator_id'] == $currentUserId);

    // Query 2: Cek apakah user sudah memberikan suara
    $stmtUserVote = $pdo->prepare("
        SELECT 1 FROM votes v 
        JOIN poll_options po ON v.option_id = po.option_id 
        WHERE po.poll_id = ? AND v.user_id = ?
    ");
    $stmtUserVote->execute([$pollId, $currentUserId]);
    $userHasVoted = (bool)$stmtUserVote->fetchColumn();

    // ================================================================
    // KONTROL AKSES KETAT - VERIFIKASI HAK MELIHAT HASIL
    // ================================================================
    /**
     * ATURAN AKSES HASIL VOTING:
     * 1. User sudah memberikan suara, ATAU
     * 2. Polling sudah tutup, ATAU  
     * 3. User adalah pembuat polling
     * 
     * Jika tidak memenuhi syarat, redirect ke halaman voting
     */
    if (!$userHasVoted && !$pollClosed && !$isCreator) {
        $_SESSION['toast'] = [
            'message' => 'You must vote first to see results.',
            'type' => 'warning'
        ];
        header('Location: ' . APP_URL . '/src/vote.php?poll_id=' . $pollId);
        exit;
    }

    // ================================================================
    // QUERY HASIL VOTING & KALKULASI STATISTIK
    // ================================================================

    /**
     * Query untuk mengambil hasil voting menggunakan correlated subquery
     * Mengapa correlated subquery? Untuk akurasi data yang real-time
     * dan menghindari masalah dengan LEFT JOIN pada data kosong
     */
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
    // KALKULASI STATISTIK HASIL
    // ================================================================

    // Hitung total suara dari semua opsi
    $totalVotes = 0;
    foreach ($results as $result) {
        $totalVotes += (int)$result['vote_count'];
    }

    // Hitung persentase untuk setiap opsi
    foreach ($results as &$result) { // Menggunakan reference (&) untuk modifikasi langsung
        $result['vote_count'] = (int)$result['vote_count'];

        // Kalkulasi persentase dengan pembulatan 1 desimal
        $result['percentage'] = $totalVotes > 0 ?
            round(((int)$result['vote_count'] / $totalVotes) * 100, 1) : 0;
    }
    unset($result); // Hapus reference untuk keamanan

} catch (Exception $e) {
    // ================================================================
    // ERROR HANDLING
    // ================================================================
    error_log('Results page error: ' . $e->getMessage());
    $_SESSION['toast'] = [
        'message' => 'Error loading results. Please try again.',
        'type' => 'error'
    ];
    header('Location: ' . APP_URL . '/public/index.php');
    exit;
}

// ================================================================
// SETUP UNTUK TEMPLATE HTML
// ================================================================
$pageTitle = htmlspecialchars($poll['title']) . ' - Results';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ================================================================ -->
<!-- TEMPLATE HTML - HALAMAN HASIL VOTING -->
<!-- ================================================================ -->
<div class="max-w-4xl mx-auto">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">

        <!-- ============================================================ -->
        <!-- HEADER SECTION - Informasi Polling & Actions -->
        <!-- ============================================================ -->
        <div class="flex flex-col sm:flex-row justify-between items-start mb-6">
            <div>
                <!-- Judul Polling -->
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                    <?= htmlspecialchars($poll['title']) ?> - Results
                </h1>

                <!-- Informasi Meta Polling -->
                <div class="text-sm text-gray-500 dark:text-gray-400 space-x-4">
                    <span>By <?= htmlspecialchars($poll['creator_name']) ?></span>
                    <span>Total Votes: <?= $totalVotes ?></span>
                </div>

                <!-- Indikator Alasan Akses -->
                <div class="mt-2">
                    <?php if ($pollClosed): ?>
                        <span class="inline-block px-2 py-1 text-xs bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 rounded">
                            Poll Closed
                        </span>
                    <?php elseif ($userHasVoted): ?>
                        <span class="inline-block px-2 py-1 text-xs bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded">
                            You Voted
                        </span>
                    <?php elseif ($isCreator): ?>
                        <span class="inline-block px-2 py-1 text-xs bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded">
                            Poll Creator
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tombol Export -->
            <div class="flex gap-2 mt-4 sm:mt-0">
                <!-- Export CSV - Update URL -->
                <a href="<?= APP_URL ?>/src/export_csv.php?poll_id=<?= $pollId ?>"
                    class="inline-flex items-center px-3 py-2 bg-green-600 hover:bg-green-700 text-white text-sm rounded-lg transition-colors">
                    <i data-feather="download" class="w-4 h-4 mr-2"></i>
                    CSV
                </a>
                <!-- Export PDF -->
                <a href="<?= APP_URL ?>/src/export_pdf.php?poll_id=<?= $pollId ?>"
                    class="inline-flex items-center px-3 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg transition-colors">
                    <i data-feather="file-text" class="w-4 h-4 mr-2"></i>
                    PDF
                </a>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- HASIL VOTING - Main Content -->
        <!-- ============================================================ -->
        <?php if ($totalVotes === 0): ?>
            <!-- State Kosong: Belum Ada Yang Vote -->
            <div class="text-center py-12">
                <i data-feather="bar-chart-2" class="w-16 h-16 mx-auto text-gray-400 dark:text-gray-500 mb-4"></i>
                <p class="text-gray-500 dark:text-gray-400">No votes yet.</p>
            </div>
        <?php else: ?>
            <!-- Tampilkan Hasil Voting -->
            <div class="space-y-3">
                <?php foreach ($results as $result_display): ?>
                    <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                        <!-- Nama Opsi -->
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="font-medium text-gray-900 dark:text-white">
                                <?= htmlspecialchars($result_display['option_text']) ?>
                            </h3>
                            <div class="text-right">
                                <span class="text-lg font-bold text-gray-900 dark:text-white">
                                    <?= $result_display['vote_count'] ?> vote<?= $result_display['vote_count'] != 1 ? 's' : '' ?>
                                </span>
                                <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">
                                    (<?= $result_display['percentage'] ?>%)
                                </span>
                            </div>
                        </div>

                        <!-- Progress Bar Visual -->
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                            <div class="bg-blue-600 dark:bg-blue-500 h-3 rounded-full transition-all duration-500 ease-out"
                                style="width: <?= $result_display['percentage'] ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- ============================================================ -->
        <!-- NAVIGASI - Tombol Kembali -->
        <!-- ============================================================ -->
        <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700 flex gap-3">
            <!-- Kembali ke Halaman Voting -->
            <a href="<?= APP_URL ?>/src/vote.php?poll_id=<?= $pollId ?>"
                class="inline-flex items-center px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg transition-colors">
                <i data-feather="arrow-left" class="w-4 h-4 mr-2"></i>
                Back to Poll
            </a>
            <!-- Kembali ke Homepage -->
            <a href="<?= APP_URL ?>/public/index.php"
                class="inline-flex items-center px-3 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm rounded-lg transition-colors">
                <i data-feather="home" class="w-4 h-4 mr-2"></i>
                Home
            </a>
        </div>
    </div>
</div>

<?php
// ================================================================
// INCLUDE FOOTER
// ================================================================
require_once __DIR__ . '/../includes/footer.php';
?>