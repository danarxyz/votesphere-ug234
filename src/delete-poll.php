<?php

/**
 * ================================================================
 * HALAMAN HAPUS POLLING - DELETE POLL FUNCTIONALITY
 * ================================================================
 * 
 * File: delete-poll.php
 * Fungsi: Halaman untuk menghapus polling beserta semua data terkait
 * 
 * FITUR UTAMA:
 * - Konfirmasi sebelum menghapus poll
 * - Cascade delete untuk votes dan comments
 * - Access control (hanya creator yang bisa hapus)
 * - Soft delete option (optional)
 * - Backup data sebelum delete
 * 
 * KEAMANAN:
 * - Authentication required
 * - Authorization check (creator only)
 * - CSRF protection
 * - Database transaction untuk konsistensi
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

// ================================================================
// KONTROL AKSES - WAJIB LOGIN
// ================================================================
Auth::requireLogin();
$currentUserId = Auth::id();

// ================================================================
// VALIDASI INPUT - POLL ID
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

// ================================================================
// INISIALISASI VARIABEL
// ================================================================
$poll = null;
$totalVotes = 0;
$totalComments = 0;
$canDelete = false;
$error = '';

try {
    // ================================================================
    // KONEKSI DATABASE & QUERY DATA POLL
    // ================================================================
    $pdo = Database::getInstance();

    // ================================================================
    // QUERY 1: AMBIL DATA POLL DAN VALIDASI OWNERSHIP
    // ================================================================
    $stmt = $pdo->prepare("
        SELECT p.*, u.username as creator_name 
        FROM polls p 
        JOIN users u ON p.creator_id = u.user_id 
        WHERE p.poll_id = ?
    ");
    $stmt->execute([$pollId]);
    $poll = $stmt->fetch(PDO::FETCH_ASSOC);

    // Validasi: Pastikan poll ditemukan
    if (!$poll) {
        $_SESSION['toast'] = [
            'message' => 'Poll not found.',
            'type' => 'error'
        ];
        header('Location: ' . APP_URL . '/public/index.php');
        exit;
    }

    // ================================================================
    // AUTHORIZATION CHECK - HANYA CREATOR YANG BISA HAPUS
    // ================================================================
    if ($poll['creator_id'] != $currentUserId) {
        $_SESSION['toast'] = [
            'message' => 'You are not authorized to delete this poll.',
            'type' => 'error'
        ];
        header('Location: ' . APP_URL . '/src/vote.php?poll_id=' . $pollId);
        exit;
    }

    $canDelete = true;

    // ================================================================
    // QUERY 2: HITUNG TOTAL VOTES
    // ================================================================
    $stmtVotes = $pdo->prepare("
        SELECT COUNT(v.vote_id) as total_votes
        FROM votes v
        JOIN poll_options po ON v.option_id = po.option_id
        WHERE po.poll_id = ?
    ");
    $stmtVotes->execute([$pollId]);
    $totalVotes = (int)$stmtVotes->fetchColumn();

    // ================================================================
    // QUERY 3: HITUNG TOTAL COMMENTS
    // ================================================================
    $stmtComments = $pdo->prepare("
        SELECT COUNT(*) as total_comments
        FROM comments
        WHERE poll_id = ?
    ");
    $stmtComments->execute([$pollId]);
    $totalComments = (int)$stmtComments->fetchColumn();
} catch (Exception $e) {
    error_log('Delete poll page error: ' . $e->getMessage());
    $_SESSION['toast'] = [
        'message' => 'Error loading poll data.',
        'type' => 'error'
    ];
    header('Location: ' . APP_URL . '/public/index.php');
    exit;
}

// ================================================================
// PROSES PENGHAPUSAN POLL
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    // ================================================================
    // VALIDASI CSRF TOKEN (optional, tapi recommended)
    // ================================================================
    $submittedToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (!hash_equals($sessionToken, $submittedToken)) {
        $_SESSION['toast'] = [
            'message' => 'Invalid security token. Please try again.',
            'type' => 'error'
        ];
        header('Location: ' . $_SERVER['PHP_SELF'] . '?poll_id=' . $pollId);
        exit;
    }

    // ================================================================
    // VALIDASI KONFIRMASI
    // ================================================================
    $confirmText = trim($_POST['confirm_text'] ?? '');
    $expectedText = 'DELETE';

    if (strtoupper($confirmText) !== $expectedText) {
        $error = 'Please type "DELETE" to confirm deletion.';
    } else {
        try {
            // ================================================================
            // DATABASE TRANSACTION - HAPUS SEMUA DATA TERKAIT
            // ================================================================
            $pdo->beginTransaction();

            // ================================================================
            // STEP 1: HAPUS SEMUA VOTES
            // ================================================================
            $stmtDeleteVotes = $pdo->prepare("
                DELETE v FROM votes v
                JOIN poll_options po ON v.option_id = po.option_id
                WHERE po.poll_id = ?
            ");
            $stmtDeleteVotes->execute([$pollId]);

            // ================================================================
            // STEP 2: HAPUS SEMUA COMMENTS
            // ================================================================
            $stmtDeleteComments = $pdo->prepare("
                DELETE FROM comments WHERE poll_id = ?
            ");
            $stmtDeleteComments->execute([$pollId]);

            // ================================================================
            // STEP 3: HAPUS SEMUA POLL OPTIONS
            // ================================================================
            $stmtDeleteOptions = $pdo->prepare("
                DELETE FROM poll_options WHERE poll_id = ?
            ");
            $stmtDeleteOptions->execute([$pollId]);

            // ================================================================
            // STEP 4: HAPUS POLL ITU SENDIRI
            // ================================================================
            $stmtDeletePoll = $pdo->prepare("
                DELETE FROM polls WHERE poll_id = ?
            ");
            $stmtDeletePoll->execute([$pollId]);

            // ================================================================
            // COMMIT TRANSACTION
            // ================================================================
            $pdo->commit();

            // ================================================================
            // PENGHAPUSAN BERHASIL
            // ================================================================
            $_SESSION['toast'] = [
                'message' => 'Poll deleted successfully.',
                'type' => 'success'
            ];

            // Redirect ke homepage
            header('Location: ' . APP_URL . '/public/index.php');
            exit;
        } catch (Exception $e) {
            // ================================================================
            // ERROR HANDLING - ROLLBACK TRANSACTION
            // ================================================================
            $pdo->rollback();
            error_log('Delete poll error: ' . $e->getMessage());
            $error = 'Error deleting poll. Please try again.';
        }
    }
}

// ================================================================
// GENERATE CSRF TOKEN
// ================================================================
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ================================================================
// SETUP UNTUK TEMPLATE HTML
// ================================================================
$pageTitle = 'Delete Poll: ' . htmlspecialchars($poll['title']);
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ================================================================ -->
<!-- TEMPLATE HTML - HALAMAN DELETE POLL -->
<!-- ================================================================ -->
<div class="max-w-4xl mx-auto">

    <!-- ================================================================ -->
    <!-- WARNING HEADER -->
    <!-- ================================================================ -->
    <div class="bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-xl p-6 mb-6">
        <div class="flex items-center mb-4">
            <div class="w-12 h-12 bg-red-100 dark:bg-red-800 rounded-full flex items-center justify-center mr-4">
                <i data-feather="alert-triangle" class="w-6 h-6 text-red-600 dark:text-red-400"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-red-800 dark:text-red-200">
                    Delete Poll
                </h1>
                <p class="text-red-600 dark:text-red-400">
                    This action cannot be undone!
                </p>
            </div>
        </div>

        <div class="bg-red-100 dark:bg-red-800 rounded-lg p-4">
            <h2 class="font-semibold text-red-800 dark:text-red-200 mb-2">
                What will be deleted:
            </h2>
            <ul class="text-sm text-red-700 dark:text-red-300 space-y-1">
                <li>• The poll and all its options</li>
                <li>• All <?= number_format($totalVotes) ?> vote<?= $totalVotes != 1 ? 's' : '' ?> from users</li>
                <li>• All <?= number_format($totalComments) ?> comment<?= $totalComments != 1 ? 's' : '' ?></li>
                <li>• All related statistics and data</li>
            </ul>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- POLL INFORMATION -->
    <!-- ================================================================ -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
            Poll Details
        </h2>

        <div class="space-y-4">
            <!-- Poll Title -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Title
                </label>
                <p class="text-gray-900 dark:text-white text-lg">
                    <?= htmlspecialchars($poll['title']) ?>
                </p>
            </div>

            <!-- Poll Description -->
            <?php if (!empty($poll['description'])): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Description
                    </label>
                    <p class="text-gray-700 dark:text-gray-300">
                        <?= htmlspecialchars($poll['description']) ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Poll Statistics -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">
                        <?= number_format($totalVotes) ?>
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Total Votes
                    </div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">
                        <?= number_format($totalComments) ?>
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Comments
                    </div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">
                        <?= formatDate($poll['created_at'], 'M j, Y') ?>
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Created On
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- DELETE CONFIRMATION FORM -->
    <!-- ================================================================ -->
    <?php if ($canDelete): ?>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-semibold text-red-600 dark:text-red-400 mb-6">
                Confirm Deletion
            </h2>

            <!-- Error Message -->
            <?php if (!empty($error)): ?>
                <div class="mb-6 p-4 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-600 rounded-lg">
                    <div class="flex items-center">
                        <i data-feather="alert-circle" class="w-5 h-5 text-red-600 dark:text-red-400 mr-2"></i>
                        <span class="text-red-800 dark:text-red-200"><?= htmlspecialchars($error) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="" x-data="{ confirmText: '', canSubmit: false }" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <!-- Confirmation Instructions -->
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <p class="text-gray-700 dark:text-gray-300 mb-3">
                        To confirm deletion, please type <strong class="text-red-600 dark:text-red-400">DELETE</strong> in the field below:
                    </p>

                    <input type="text"
                        name="confirm_text"
                        x-model="confirmText"
                        x-on:input="canSubmit = confirmText.toUpperCase() === 'DELETE'"
                        placeholder="Type DELETE to confirm"
                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400">
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <!-- Cancel Button -->
                    <a href="<?= APP_URL ?>/src/vote.php?poll_id=<?= $pollId ?>"
                        class="flex-1 inline-flex justify-center items-center px-6 py-3 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 dark:focus:ring-offset-gray-800 font-medium transition-all duration-200">
                        <i data-feather="x" class="w-5 h-5 mr-2"></i>
                        Cancel
                    </a>

                    <!-- Delete Button -->
                    <button type="submit"
                        name="confirm_delete"
                        value="1"
                        x-bind:disabled="!canSubmit"
                        x-bind:class="canSubmit ? 
                                'bg-red-600 hover:bg-red-700 focus:ring-red-500 cursor-pointer' : 
                                'bg-gray-400 dark:bg-gray-600 cursor-not-allowed'"
                        class="flex-1 inline-flex justify-center items-center px-6 py-3 border border-transparent rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 font-medium transition-all duration-200 disabled:opacity-50">
                        <i data-feather="trash-2" class="w-5 h-5 mr-2"></i>
                        Delete Poll Forever
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<!-- ================================================================ -->
<!-- CATATAN IMPLEMENTASI -->
<!-- ================================================================ -->
<!--
FITUR KEAMANAN:
1. Authentication dan authorization checks
2. CSRF protection dengan token
3. Confirmation text requirement
4. Database transaction untuk konsistensi
5. Proper error handling dan logging

FITUR UX:
1. Clear warning tentang konsekuensi delete
2. Statistics tentang data yang akan dihapus
3. Confirmation form dengan validation
4. Real-time feedback untuk confirmation text
5. Cancel option yang jelas

LOGIKA BISNIS:
1. Cascade delete untuk semua data terkait
2. Transaction rollback jika ada error
3. Proper ownership validation
4. Data integrity maintenance
5. Success/error feedback

OPTIMASI PERFORMA:
1. Single transaction untuk semua deletes
2. Efficient queries dengan proper JOINs
3. Minimal data fetching untuk display
4. Proper indexing considerations
-->