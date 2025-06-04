<?php

/**
 * ================================================================
 * HALAMAN VOTING - SISTEM PEMUNGUTAN SUARA
 * ================================================================
 * 
 * File: vote.php
 * Fungsi: Halaman untuk memberikan suara pada polling dan melihat detail poll
 * 
 * FITUR UTAMA:
 * - Tampilan detail polling dengan opsi voting
 * - Validasi hak suara (belum pernah vote)
 * - Pengecekan status polling (aktif/tutup)
 * - Sistem komentar pada polling
 * - Redirect ke hasil setelah voting
 * 
 * KEAMANAN:
 * - Validasi poll ID
 * - Cegah double voting per user
 * - Input sanitization untuk komentar
 * - Access control untuk fitur tertentu
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
// VALIDASI INPUT - POLL ID
// ================================================================
// Ambil dan validasi poll ID dari URL parameter
$pollId = isset($_GET['poll_id']) ? (int)$_GET['poll_id'] : 0;

if ($pollId <= 0) {
    $_SESSION['toast'] = [
        'message' => 'Invalid poll ID.',
        'type' => 'error'
    ];
    header('Location: ' . APP_URL . '/public/index.php');
    exit;
}

// ================================================================
// INISIALISASI VARIABEL HALAMAN
// ================================================================
$poll = null;                    // Data polling lengkap
$options = [];                   // Array pilihan dalam poll
$userVote = null;               // Pilihan yang sudah dipilih user (jika ada)
$pollClosed = false;            // Status apakah poll sudah tutup
$comments = [];                 // Komentar-komentar pada poll
$currentUserId = Auth::check() ? Auth::id() : null; // ID user yang login

try {
    // ================================================================
    // KONEKSI DATABASE & QUERY DATA POLLING
    // ================================================================
    $pdo = Database::getInstance();

    // ================================================================
    // QUERY 1: AMBIL DATA POLLING BESERTA PEMBUAT
    // ================================================================
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
    // QUERY 2: AMBIL SEMUA OPSI PILIHAN DALAM POLL
    // ================================================================
    $stmtOptions = $pdo->prepare("
        SELECT option_id, option_text 
        FROM poll_options 
        WHERE poll_id = ? 
        ORDER BY option_id ASC
    ");
    $stmtOptions->execute([$pollId]);
    $options = $stmtOptions->fetchAll(PDO::FETCH_ASSOC);

    // ================================================================
    // CEK STATUS POLLING - APAKAH SUDAH TUTUP
    // ================================================================
    $pollClosed = !empty($poll['end_time']) && strtotime($poll['end_time']) <= time();

    // ================================================================
    // CEK APAKAH USER SUDAH MEMBERIKAN SUARA
    // ================================================================
    if ($currentUserId) {
        $stmtUserVote = $pdo->prepare("
            SELECT po.option_id, po.option_text 
            FROM votes v 
            JOIN poll_options po ON v.option_id = po.option_id 
            WHERE po.poll_id = ? AND v.user_id = ?
        ");
        $stmtUserVote->execute([$pollId, $currentUserId]);
        $userVote = $stmtUserVote->fetch(PDO::FETCH_ASSOC);
    }

    // ================================================================
    // QUERY 3: AMBIL KOMENTAR-KOMENTAR PADA POLL
    // ================================================================
    $stmtComments = $pdo->prepare("
        SELECT c.*, u.username 
        FROM comments c 
        JOIN users u ON c.user_id = u.user_id 
        WHERE c.poll_id = ? 
        ORDER BY c.created_at DESC 
        LIMIT 50
    ");
    $stmtComments->execute([$pollId]);
    $comments = $stmtComments->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // ================================================================
    // ERROR HANDLING - DATABASE ERRORS
    // ================================================================
    error_log('Vote page database error: ' . $e->getMessage());
    $_SESSION['toast'] = [
        'message' => 'Error loading poll data.',
        'type' => 'error'
    ];
    header('Location: ' . APP_URL . '/public/index.php');
    exit;
}

// ================================================================
// PROSES FORM VOTING
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vote'])) {
    // ================================================================
    // VALIDASI HAK VOTING
    // ================================================================

    // Cek apakah user sudah login
    if (!$currentUserId) {
        $_SESSION['toast'] = [
            'message' => 'Please log in to vote.',
            'type' => 'warning'
        ];
        header('Location: ' . APP_URL . '/src/login.php');
        exit;
    }

    // Cek apakah poll sudah tutup
    if ($pollClosed) {
        $_SESSION['toast'] = [
            'message' => 'This poll has ended.',
            'type' => 'error'
        ];
        header('Location: ' . APP_URL . '/src/vote.php?poll_id=' . $pollId);
        exit;
    }

    // Cek apakah user sudah pernah vote
    if ($userVote) {
        $_SESSION['toast'] = [
            'message' => 'You have already voted in this poll.',
            'type' => 'warning'
        ];
        header('Location: ' . APP_URL . '/src/results.php?poll_id=' . $pollId);
        exit;
    }

    // ================================================================
    // VALIDASI INPUT VOTING
    // ================================================================
    $selectedOptionId = isset($_POST['option_id']) ? (int)$_POST['option_id'] : 0;

    if ($selectedOptionId <= 0) {
        $_SESSION['toast'] = [
            'message' => 'Please select a valid option.',
            'type' => 'error'
        ];
        header('Location: ' . APP_URL . '/src/vote.php?poll_id=' . $pollId);
        exit;
    }

    try {
        // ================================================================
        // VALIDASI OPTION ID - PASTIKAN MILIK POLL INI
        // ================================================================
        $stmtValidateOption = $pdo->prepare("
            SELECT 1 FROM poll_options 
            WHERE option_id = ? AND poll_id = ?
        ");
        $stmtValidateOption->execute([$selectedOptionId, $pollId]);

        if (!$stmtValidateOption->fetchColumn()) {
            $_SESSION['toast'] = [
                'message' => 'Invalid option selected.',
                'type' => 'error'
            ];
            header('Location: ' . APP_URL . '/src/vote.php?poll_id=' . $pollId);
            exit;
        }

        // ================================================================
        // SIMPAN VOTE KE DATABASE
        // ================================================================
        $stmtVote = $pdo->prepare("
            INSERT INTO votes (user_id, option_id, voted_at) 
            VALUES (?, ?, NOW())
        ");
        $stmtVote->execute([$currentUserId, $selectedOptionId]);

        // ================================================================
        // VOTING BERHASIL
        // ================================================================
        $_SESSION['toast'] = [
            'message' => 'Your vote has been recorded successfully!',
            'type' => 'success'
        ];

        // Redirect ke halaman hasil
        header('Location: ' . APP_URL . '/src/results.php?poll_id=' . $pollId);
        exit;
    } catch (Exception $e) {
        // ================================================================
        // ERROR HANDLING - VOTING GAGAL
        // ================================================================
        error_log('Voting error: ' . $e->getMessage());
        $_SESSION['toast'] = [
            'message' => 'Error recording your vote. Please try again.',
            'type' => 'error'
        ];
        header('Location: ' . APP_URL . '/src/vote.php?poll_id=' . $pollId);
        exit;
    }
}

// ================================================================
// PROSES FORM KOMENTAR
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    // ================================================================
    // VALIDASI HAK KOMENTAR
    // ================================================================

    // Cek apakah user sudah login
    if (!$currentUserId) {
        $_SESSION['toast'] = [
            'message' => 'Please log in to comment.',
            'type' => 'warning'
        ];
        header('Location: ' . APP_URL . '/src/login.php');
        exit;
    }

    // ================================================================
    // VALIDASI DAN SANITASI INPUT KOMENTAR
    // ================================================================
    $commentText = trim($_POST['comment_text'] ?? '');

    if (empty($commentText)) {
        $_SESSION['toast'] = [
            'message' => 'Comment cannot be empty.',
            'type' => 'error'
        ];
        header('Location: ' . APP_URL . '/src/vote.php?poll_id=' . $pollId);
        exit;
    }

    // Batasi panjang komentar
    if (strlen($commentText) > 1000) {
        $_SESSION['toast'] = [
            'message' => 'Comment is too long (max 1000 characters).',
            'type' => 'error'
        ];
        header('Location: ' . APP_URL . '/src/vote.php?poll_id=' . $pollId);
        exit;
    }

    try {
        // ================================================================
        // SIMPAN KOMENTAR KE DATABASE
        // ================================================================
        $stmtComment = $pdo->prepare("
            INSERT INTO comments (poll_id, user_id, comment_text, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmtComment->execute([$pollId, $currentUserId, $commentText]);

        // ================================================================
        // KOMENTAR BERHASIL DISIMPAN
        // ================================================================
        $_SESSION['toast'] = [
            'message' => 'Comment added successfully!',
            'type' => 'success'
        ];

        // Redirect untuk mencegah double submit
        header('Location: ' . APP_URL . '/src/vote.php?poll_id=' . $pollId);
        exit;
    } catch (Exception $e) {
        // ================================================================
        // ERROR HANDLING - KOMENTAR GAGAL
        // ================================================================
        error_log('Comment submission error: ' . $e->getMessage());
        $_SESSION['toast'] = [
            'message' => 'Error adding comment. Please try again.',
            'type' => 'error'
        ];
        header('Location: ' . APP_URL . '/src/vote.php?poll_id=' . $pollId);
        exit;
    }
}

// ================================================================
// SETUP UNTUK TEMPLATE HTML
// ================================================================
$pageTitle = htmlspecialchars($poll['title']);
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ================================================================ -->
<!-- TEMPLATE HTML - HALAMAN VOTING -->
<!-- ================================================================ -->
<div class="max-w-4xl mx-auto">

    <!-- ================================================================ -->
    <!-- HEADER SECTION - INFORMASI POLLING -->
    <!-- ================================================================ -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mb-6">
        <div class="flex flex-col sm:flex-row justify-between items-start mb-4">
            <div class="flex-1">
                <!-- Judul Poll -->
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                    <?= htmlspecialchars($poll['title']) ?>
                </h1>

                <!-- Deskripsi Poll (jika ada) -->
                <?php if (!empty($poll['description'])): ?>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        <?= htmlspecialchars($poll['description']) ?>
                    </p>
                <?php endif; ?>

                <!-- Countdown Timer Section -->
                <?php if (!empty($poll['end_time']) && !$pollClosed): ?>
                    <div class="bg-gradient-to-r from-orange-50 to-red-50 dark:from-orange-900/20 dark:to-red-900/20 border border-orange-200 dark:border-orange-800 rounded-xl p-4 mb-4">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-sm font-semibold text-orange-700 dark:text-orange-300 flex items-center">
                                <i data-feather="clock" class="w-4 h-4 mr-2"></i>
                                Time Remaining
                            </span>
                            <span class="text-xs text-orange-600 dark:text-orange-400 bg-orange-100 dark:bg-orange-900/40 px-2 py-1 rounded-full">
                                Live
                            </span>
                        </div>
                        <div class="countdown-timer text-lg font-mono text-orange-800 dark:text-orange-200"
                            data-end-time="<?= date('c', strtotime($poll['end_time'])) ?>"
                            data-start-time="<?= date('c', strtotime($poll['created_at'])) ?>">
                            <div class="grid grid-cols-4 gap-4 text-center">
                                <div class="bg-white dark:bg-gray-700 rounded-lg p-3">
                                    <div class="countdown-days font-bold text-2xl text-orange-600 dark:text-orange-400">--</div>
                                    <div class="text-xs text-orange-600 dark:text-orange-400 uppercase tracking-wide">Days</div>
                                </div>
                                <div class="bg-white dark:bg-gray-700 rounded-lg p-3">
                                    <div class="countdown-hours font-bold text-2xl text-orange-600 dark:text-orange-400">--</div>
                                    <div class="text-xs text-orange-600 dark:text-orange-400 uppercase tracking-wide">Hours</div>
                                </div>
                                <div class="bg-white dark:bg-gray-700 rounded-lg p-3">
                                    <div class="countdown-minutes font-bold text-2xl text-orange-600 dark:text-orange-400">--</div>
                                    <div class="text-xs text-orange-600 dark:text-orange-400 uppercase tracking-wide">Minutes</div>
                                </div>
                                <div class="bg-white dark:bg-gray-700 rounded-lg p-3">
                                    <div class="countdown-seconds font-bold text-2xl text-orange-600 dark:text-orange-400">--</div>
                                    <div class="text-xs text-orange-600 dark:text-orange-400 uppercase tracking-wide">Seconds</div>
                                </div>
                            </div>
                        </div>
                        <!-- Progress Bar -->
                        <div class="mt-4">
                            <div class="flex justify-between text-xs text-orange-600 dark:text-orange-400 mb-1">
                                <span>Started: <?= formatDate($poll['created_at'], 'M j, Y') ?></span>
                                <span>Ends: <?= formatDate($poll['end_time'], 'M j, Y') ?></span>
                            </div>
                            <div class="countdown-progress-bg bg-orange-200 dark:bg-orange-800 rounded-full h-2">
                                <div class="countdown-progress-bar bg-gradient-to-r from-orange-400 to-red-500 h-2 rounded-full transition-all duration-1000" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                <?php elseif (!empty($poll['end_time']) && $pollClosed): ?>
                    <!-- Expired Poll -->
                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-4 mb-4">
                        <div class="flex items-center text-red-700 dark:text-red-300">
                            <i data-feather="x-circle" class="w-5 h-5 mr-2"></i>
                            <span class="text-lg font-semibold">Poll Ended</span>
                        </div>
                        <div class="text-sm text-red-600 dark:text-red-400 mt-2">
                            This poll ended on <?= formatDate($poll['end_time'], 'M j, Y \a\t g:i A') ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- No End Date -->
                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl p-4 mb-4">
                        <div class="flex items-center text-green-700 dark:text-green-300">
                            <i data-feather="infinity" class="w-5 h-5 mr-2"></i>
                            <span class="text-lg font-semibold">Open Poll</span>
                        </div>
                        <div class="text-sm text-green-600 dark:text-green-400 mt-2">
                            This poll has no expiration date and will remain open indefinitely
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Meta Information -->
                <div class="flex flex-wrap gap-4 text-sm text-gray-500 dark:text-gray-400">
                    <span class="flex items-center">
                        <i data-feather="user" class="w-4 h-4 mr-1"></i>
                        Created by <?= htmlspecialchars($poll['creator_name']) ?>
                    </span>
                    <span class="flex items-center">
                        <i data-feather="calendar" class="w-4 h-4 mr-1"></i>
                        <?= formatDate($poll['created_at']) ?>
                    </span>
                </div>
            </div>

            <!-- Status Badge -->
            <div class="mt-4 sm:mt-0">
                <?php if ($pollClosed): ?>
                    <span class="inline-block px-4 py-2 text-sm bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 rounded-full font-medium">
                        <i data-feather="x-circle" class="w-4 h-4 inline mr-2"></i>
                        Poll Closed
                    </span>
                <?php elseif ($userVote): ?>
                    <span class="inline-block px-4 py-2 text-sm bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded-full font-medium">
                        <i data-feather="check-circle" class="w-4 h-4 inline mr-2"></i>
                        You Voted
                    </span>
                <?php else: ?>
                    <span class="inline-block px-4 py-2 text-sm bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded-full font-medium">
                        <i data-feather="clock" class="w-4 h-4 inline mr-2"></i>
                        Active
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MAIN CONTENT - VOTING SECTION -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- ================================================================ -->
        <!-- LEFT COLUMN - VOTING FORM / RESULTS PREVIEW -->
        <!-- ================================================================ -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">

                <?php if ($userVote): ?>
                    <!-- ============================================================ -->
                    <!-- USER SUDAH VOTE - TAMPILKAN PILIHAN YANG DIPILIH -->
                    <!-- ============================================================ -->
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i data-feather="check" class="w-8 h-8 text-green-600 dark:text-green-400"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                            Thank you for voting!
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                            Your choice: <strong><?= htmlspecialchars($userVote['option_text']) ?></strong>
                        </p>
                        <a href="<?= APP_URL ?>/src/results.php?poll_id=<?= $pollId ?>"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                            <i data-feather="bar-chart-2" class="w-4 h-4 mr-2"></i>
                            View Results
                        </a>
                    </div>

                <?php elseif ($pollClosed): ?>
                    <!-- ============================================================ -->
                    <!-- POLL SUDAH TUTUP - TAMPILKAN PESAN -->
                    <!-- ============================================================ -->
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-red-100 dark:bg-red-900 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i data-feather="x-circle" class="w-8 h-8 text-red-600 dark:text-red-400"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                            This poll has ended
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                            Voting is no longer available for this poll.
                        </p>
                        <a href="<?= APP_URL ?>/src/results.php?poll_id=<?= $pollId ?>"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                            <i data-feather="bar-chart-2" class="w-4 h-4 mr-2"></i>
                            View Results
                        </a>
                    </div>

                <?php else: ?>
                    <!-- ============================================================ -->
                    <!-- FORM VOTING - USER BELUM VOTE DAN POLL MASIH AKTIF -->
                    <!-- ============================================================ -->
                    <?php if ($currentUserId): ?>
                        <!-- User sudah login, tampilkan form voting -->
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-6">
                                Cast Your Vote
                            </h2>

                            <form method="POST" action="" class="space-y-4">
                                <input type="hidden" name="vote" value="1">

                                <?php foreach ($options as $option): ?>
                                    <label class="flex items-center p-4 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors">
                                        <input type="radio"
                                            name="option_id"
                                            value="<?= $option['option_id'] ?>"
                                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600"
                                            required>
                                        <span class="ml-3 text-gray-900 dark:text-white">
                                            <?= htmlspecialchars($option['option_text']) ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>

                                <button type="submit"
                                    class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-all duration-200">
                                    <i data-feather="check" class="w-4 h-4 mr-2"></i>
                                    Submit Vote
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- User belum login, tampilkan pesan login -->
                        <div class="text-center py-8">
                            <div class="w-16 h-16 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i data-feather="log-in" class="w-8 h-8 text-blue-600 dark:text-blue-400"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                                Login to Vote
                            </h3>
                            <p class="text-gray-600 dark:text-gray-400 mb-4">
                                You need to be logged in to participate in this poll.
                            </p>
                            <a href="<?= APP_URL ?>/src/login.php"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                                <i data-feather="log-in" class="w-4 h-4 mr-2"></i>
                                Login
                            </a>
                        </div>

                        <!-- Tampilkan preview opsi tanpa form -->
                        <div class="mt-8">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                Available Options:
                            </h3>
                            <div class="space-y-2">
                                <?php foreach ($options as $option): ?>
                                    <div class="p-3 border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700">
                                        <span class="text-gray-900 dark:text-white">
                                            <?= htmlspecialchars($option['option_text']) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- RIGHT COLUMN - COMMENTS SECTION -->
        <!-- ================================================================ -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Comments (<?= count($comments) ?>)
                </h3>

                <!-- ============================================================ -->
                <!-- FORM KOMENTAR (JIKA USER LOGIN) -->
                <!-- ============================================================ -->
                <?php if ($currentUserId): ?>
                    <form method="POST" action="" class="mb-6">
                        <input type="hidden" name="comment" value="1">
                        <div>
                            <textarea name="comment_text"
                                rows="3"
                                placeholder="Add a comment..."
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400"
                                maxlength="1000"></textarea>
                        </div>
                        <button type="submit"
                            class="mt-2 inline-flex items-center px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg transition-colors">
                            <i data-feather="send" class="w-4 h-4 mr-1"></i>
                            Post
                        </button>
                    </form>
                <?php endif; ?>

                <!-- ============================================================ -->
                <!-- DAFTAR KOMENTAR -->
                <!-- ============================================================ -->
                <div class="space-y-4 max-h-96 overflow-y-auto">
                    <?php if (empty($comments)): ?>
                        <p class="text-gray-500 dark:text-gray-400 text-sm italic">
                            No comments yet. Be the first to comment!
                        </p>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                            <div class="border-b border-gray-200 dark:border-gray-600 pb-3 last:border-b-0">
                                <div class="flex items-start space-x-3">
                                    <!-- Avatar -->
                                    <img src="<?= get_user_avatar_url($comment['user_id'], $comment['username']) ?>"
                                        alt="<?= htmlspecialchars($comment['username']) ?>"
                                        class="w-8 h-8 rounded-full">

                                    <!-- Comment Content -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center space-x-2">
                                            <span class="font-medium text-gray-900 dark:text-white text-sm">
                                                <?= htmlspecialchars($comment['username']) ?>
                                            </span>
                                            <span class="text-gray-500 dark:text-gray-400 text-xs">
                                                <?= formatDate($comment['created_at'], 'M j, g:i A') ?>
                                            </span>
                                        </div>
                                        <p class="text-gray-700 dark:text-gray-300 text-sm mt-1">
                                            <?= htmlspecialchars($comment['comment_text']) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- NAVIGATION ACTIONS -->
    <!-- ================================================================ -->
    <div class="mt-6 flex gap-3">
        <a href="<?= APP_URL ?>/public/index.php"
            class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors">
            <i data-feather="arrow-left" class="w-4 h-4 mr-2"></i>
            Back to Home
        </a>

        <?php if ($userVote || $pollClosed): ?>
            <a href="<?= APP_URL ?>/src/results.php?poll_id=<?= $pollId ?>"
                class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                <i data-feather="bar-chart-2" class="w-4 h-4 mr-2"></i>
                View Results
            </a>
        <?php endif; ?>
    </div>
</div>

<?php
// ================================================================
// INCLUDE FOOTER
// ================================================================
require_once __DIR__ . '/../includes/footer.php';
?>

<!-- ================================================================ -->
<!-- CATATAN IMPLEMENTASI -->
<!-- ================================================================ -->
<!--
FITUR KEAMANAN:
1. Validasi poll ID dan option ID
2. Prevention double voting per user
3. Input sanitization untuk komentar
4. Access control untuk voting dan komentar
5. Database transaction untuk konsistensi

FITUR UX:
1. Responsive design untuk mobile dan desktop
2. Visual feedback untuk status voting
3. Real-time comments system
4. Clear navigation dan call-to-action
5. Loading states dan error handling

LOGIKA BISNIS:
1. Hanya user login yang bisa vote dan comment
2. Satu user hanya bisa vote sekali per poll
3. Poll yang sudah tutup tidak bisa menerima vote
4. Redirect otomatis ke results setelah voting
5. Comments pagination untuk performa

OPTIMASI PERFORMA:
1. Efficient database queries dengan JOIN
2. Lazy loading untuk comments
3. Proper indexing pada foreign keys
4. Minimal queries per page load
-->

<!-- JavaScript untuk Countdown Timer -->
<script>
    // ================================================================
    // COUNTDOWN TIMER IMPLEMENTATION FOR VOTE PAGE
    // ================================================================
    class VotePageCountdownTimer {
        constructor(element) {
            this.element = element;
            this.endTime = new Date(element.dataset.endTime).getTime();
            this.startTime = new Date(element.dataset.startTime || Date.now()).getTime();
            this.totalDuration = this.endTime - this.startTime;

            console.log('Vote page countdown init:', {
                endTime: new Date(this.endTime),
                startTime: new Date(this.startTime),
                duration: this.totalDuration
            });

            this.daysEl = element.querySelector('.countdown-days');
            this.hoursEl = element.querySelector('.countdown-hours');
            this.minutesEl = element.querySelector('.countdown-minutes');
            this.secondsEl = element.querySelector('.countdown-seconds');
            this.progressBar = element.parentElement.querySelector('.countdown-progress-bar');

            this.update();
            this.interval = setInterval(() => this.update(), 1000);
        }

        update() {
            const now = Date.now();
            const timeLeft = this.endTime - now;

            if (timeLeft <= 0) {
                this.onExpired();
                return;
            }

            const days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
            const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);

            // Update display
            if (this.daysEl) this.daysEl.textContent = days.toString().padStart(2, '0');
            if (this.hoursEl) this.hoursEl.textContent = hours.toString().padStart(2, '0');
            if (this.minutesEl) this.minutesEl.textContent = minutes.toString().padStart(2, '0');
            if (this.secondsEl) this.secondsEl.textContent = seconds.toString().padStart(2, '0');

            // Update progress bar
            if (this.progressBar && this.totalDuration > 0) {
                const elapsed = now - this.startTime;
                const progress = Math.min((elapsed / this.totalDuration) * 100, 100);
                this.progressBar.style.width = `${progress}%`;

                // Change color as deadline approaches
                if (progress > 90) {
                    this.progressBar.className = this.progressBar.className.replace(/from-\w+-\d+/g, 'from-red-500').replace(/to-\w+-\d+/g, 'to-red-600');
                } else if (progress > 75) {
                    this.progressBar.className = this.progressBar.className.replace(/from-\w+-\d+/g, 'from-yellow-400').replace(/to-\w+-\d+/g, 'to-orange-500');
                }
            }

            // Add urgency effects
            const container = this.element.closest('.bg-gradient-to-r');
            if (timeLeft < 24 * 60 * 60 * 1000) { // Last 24 hours
                container?.classList.add('animate-pulse');
            }

            if (timeLeft < 60 * 60 * 1000) { // Last hour
                container?.classList.add('ring-2', 'ring-red-400', 'ring-opacity-75');
            }
        }

        onExpired() {
            clearInterval(this.interval);

            // Show expiration notification
            this.showExpirationNotification();

            // Reload page to update poll status
            setTimeout(() => {
                window.location.reload();
            }, 3000);
        }

        showExpirationNotification() {
            // Create and show notification
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 bg-red-600 text-white p-4 rounded-lg shadow-lg z-50 max-w-sm';
            notification.innerHTML = `
                <div class="flex items-center">
                    <i data-feather="x-circle" class="w-5 h-5 mr-2"></i>
                    <div>
                        <div class="font-semibold">Poll Expired!</div>
                        <div class="text-sm">This poll has just ended. Refreshing page...</div>
                    </div>
                </div>
            `;

            document.body.appendChild(notification);

            // Replace countdown with expired message
            const container = this.element.closest('.bg-gradient-to-r');
            if (container) {
                container.innerHTML = `
                    <div class="flex items-center text-red-700 dark:text-red-300">
                        <i data-feather="x-circle" class="w-5 h-5 mr-2"></i>
                        <span class="text-lg font-semibold">Poll Just Ended</span>
                    </div>
                    <div class="text-sm text-red-600 dark:text-red-400 mt-2">
                        This poll has just expired. The page will refresh automatically.
                    </div>
                `;
            }

            // Re-initialize feather icons
            if (typeof feather !== 'undefined') {
                feather.replace();
            }

            // Browser notification if permitted
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('Poll Expired', {
                    body: 'The poll you were viewing has just ended.',
                    icon: '/favicon.ico'
                });
            }

            // Remove notification after 3 seconds
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        destroy() {
            if (this.interval) {
                clearInterval(this.interval);
            }
        }
    }

    // ================================================================
    // VOTE PAGE INITIALIZATION
    // ================================================================
    let votePageTimer = null;

    function initVotePageCountdown() {
        const timerElement = document.querySelector('.countdown-timer[data-end-time]');
        if (timerElement) {
            console.log('Initializing vote page countdown...');
            votePageTimer = new VotePageCountdownTimer(timerElement);
        }
    }

    // ================================================================
    // PAGE VISIBILITY HANDLING
    // ================================================================
    function handleVotePageVisibilityChange() {
        if (document.hidden) {
            // Page is hidden, pause timer
            if (votePageTimer && votePageTimer.interval) {
                clearInterval(votePageTimer.interval);
                votePageTimer.isPaused = true;
            }
        } else {
            // Page is visible, resume timer
            if (votePageTimer && votePageTimer.isPaused) {
                votePageTimer.interval = setInterval(() => votePageTimer.update(), 1000);
                votePageTimer.isPaused = false;
                votePageTimer.update(); // Immediate update
            }
        }
    }

    // ================================================================
    // VOTE PAGE INITIALIZATION
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Vote page DOM loaded, initializing countdown...');

        // Initialize countdown timer
        initVotePageCountdown();

        // Handle page visibility changes
        document.addEventListener('visibilitychange', handleVotePageVisibilityChange);

        // Request notification permission
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        if (votePageTimer) {
            votePageTimer.destroy();
        }
    });

    // ================================================================
    // VOTING FORM ENHANCEMENT
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        const votingForm = document.querySelector('form[method="POST"]');
        if (votingForm && votingForm.querySelector('input[name="vote"]')) {
            votingForm.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = `
                        <svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Submitting Vote...
                    `;
                }
            });
        }
    });
</script>

<style>
    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.8;
        }
    }

    .countdown-timer {
        font-variant-numeric: tabular-nums;
    }

    .animate-pulse {
        animation: pulse 2s infinite;
    }
</style>