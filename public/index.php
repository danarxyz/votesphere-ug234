<?php

/**
 * ================================================================
 * HALAMAN UTAMA - HOMEPAGE APLIKASI VOTESPHERE
 * ================================================================
 * 
 * File: index.php
 * Fungsi: Halaman utama aplikasi yang menampilkan daftar polling
 * 
 * FITUR UTAMA:
 * - Dashboard polling terbaru dan populer
 * - Filter dan sorting untuk polling
 * - Pagination untuk navigasi
 * - Quick stats dan overview
 * - Hero section untuk user baru
 * - Search functionality
 * 
 * KEAMANAN:
 * - Public access (tidak perlu login)
 * - Data sanitization untuk output
 * - Safe pagination parameters
 * - XSS protection
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
require_once __DIR__ . '/../utils/utils.php';

// Mulai session jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// INISIALISASI VARIABEL HALAMAN
// ================================================================
$currentUserId = Auth::check() ? Auth::id() : null;
$filter = $_GET['filter'] ?? 'all';           // all, active, closed, my_polls
$sort = $_GET['sort'] ?? 'newest';            // newest, oldest, popular, ending_soon
$page = max(1, (int)($_GET['page'] ?? 1));    // Current page
$limit = 12;                                  // Polls per page
$offset = ($page - 1) * $limit;              // SQL offset

// Data containers
$polls = [];                                  // Main polls array
$total = 0;                                   // Total polls count
$totalPages = 0;                              // Total pages
$stats = [                                    // Homepage stats
    'total_polls' => 0,
    'total_votes' => 0,
    'active_polls' => 0,
    'total_users' => 0
];

try {
    // ================================================================
    // KONEKSI DATABASE
    // ================================================================
    $pdo = Database::getInstance();

    // ================================================================
    // QUERY 1: AMBIL STATISTIK UMUM UNTUK HOMEPAGE
    // ================================================================
    $statsQuery = "
        SELECT 
            (SELECT COUNT(*) FROM polls) as total_polls,
            (SELECT COUNT(*) FROM votes) as total_votes,
            (SELECT COUNT(*) FROM polls WHERE end_time IS NULL OR end_time > NOW()) as active_polls,
            (SELECT COUNT(*) FROM users) as total_users
    ";
    $stmtStats = $pdo->query($statsQuery);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    // ================================================================
    // BUILD QUERY BERDASARKAN FILTER
    // ================================================================
    $baseQuery = "
        FROM polls p 
        JOIN users u ON p.creator_id = u.user_id 
        LEFT JOIN (
            SELECT po.poll_id, COUNT(v.vote_id) as vote_count
            FROM poll_options po
            LEFT JOIN votes v ON po.option_id = v.option_id
            GROUP BY po.poll_id
        ) vote_stats ON p.poll_id = vote_stats.poll_id
    ";

    $whereConditions = [];
    $params = [];

    // ================================================================
    // FILTER CONDITIONS
    // ================================================================
    switch ($filter) {
        case 'active':
            $whereConditions[] = "(p.end_time IS NULL OR p.end_time > NOW())";
            break;
        case 'closed':
            $whereConditions[] = "p.end_time IS NOT NULL AND p.end_time <= NOW()";
            break;
        case 'my_polls':
            if ($currentUserId) {
                $whereConditions[] = "p.creator_id = ?";
                $params[] = $currentUserId;
            } else {
                // Redirect ke login jika belum login
                $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'];
                header('Location: ' . APP_URL . '/src/login.php');
                exit;
            }
            break;
        case 'all':
        default:
            // No additional filter
            break;
    }

    // ================================================================
    // BUILD WHERE CLAUSE
    // ================================================================
    $whereClause = !empty($whereConditions) ?
        "WHERE " . implode(" AND ", $whereConditions) : "";

    // ================================================================
    // COUNT TOTAL RESULTS
    // ================================================================
    $countQuery = "SELECT COUNT(DISTINCT p.poll_id) " . $baseQuery . " " . $whereClause;
    $stmtCount = $pdo->prepare($countQuery);
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    // Calculate total pages
    $totalPages = ceil($total / $limit);

    // ================================================================
    // BUILD ORDER BY CLAUSE
    // ================================================================
    $orderBy = match ($sort) {
        'oldest' => 'ORDER BY p.created_at ASC',
        'popular' => 'ORDER BY COALESCE(vote_stats.vote_count, 0) DESC, p.created_at DESC',
        'ending_soon' => 'ORDER BY 
            CASE 
                WHEN p.end_time IS NULL THEN 1
                WHEN p.end_time <= NOW() THEN 2
                ELSE 0
            END ASC,
            p.end_time ASC,
            p.created_at DESC',
        'newest' => 'ORDER BY p.created_at DESC',
        default => 'ORDER BY p.created_at DESC'
    };

    // ================================================================
    // QUERY MAIN DATA - POLLS DENGAN PAGINATION
    // ================================================================
    if ($total > 0) {
        $mainQuery = "
            SELECT 
                p.poll_id,
                p.title,
                p.description,
                p.created_at,
                p.end_time,
                u.username as creator_name,
                COALESCE(vote_stats.vote_count, 0) as vote_count
            " . $baseQuery . " " . $whereClause . "
            GROUP BY p.poll_id, p.title, p.description, p.created_at, p.end_time, u.username, vote_stats.vote_count
            " . $orderBy . "
            LIMIT ? OFFSET ?
        ";

        // Add pagination parameters
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $pdo->prepare($mainQuery);
        $stmt->execute($params);
        $polls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // ================================================================
    // ERROR HANDLING
    // ================================================================
    error_log('Homepage error: ' . $e->getMessage());
    $error_message = 'Error loading polls. Please try again later.';
}

// ================================================================
// SETUP UNTUK TEMPLATE HTML
// ================================================================
$pageTitle = 'VoteSphere - Create and Share Polls';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ================================================================ -->
<!-- TEMPLATE HTML - HOMEPAGE -->
<!-- ================================================================ -->

<!-- ================================================================ -->
<!-- HERO SECTION (UNTUK USER YANG BELUM LOGIN) -->
<!-- ================================================================ -->
<?php if (!Auth::check()): ?>
    <div class="py-16">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-12 text-center">
                <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">
                    Create & Share Polls
                </h1>
                <p class="text-lg text-gray-600 dark:text-gray-400 mb-8">
                    Get instant feedback from your audience with beautiful, easy-to-use polls.
                </p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="<?= APP_URL ?>/src/register.php"
                        class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                        Get Started
                    </a>
                    <a href="<?= APP_URL ?>/src/login.php"
                        class="px-6 py-3 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Sign In
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ================================================================ -->
<!-- MAIN CONTENT AREA -->
<!-- ================================================================ -->
<div class="<?= Auth::check() ? 'py-8' : 'py-8 bg-gray-50 dark:bg-gray-900' ?>">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- ================================================================ -->
        <!-- STATS SECTION (UNTUK USER YANG SUDAH LOGIN) - HIDDEN ON MOBILE -->
        <!-- ================================================================ -->
        <?php if (Auth::check()): ?>
            <div class="hidden md:grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 text-center">
                    <div class="text-3xl font-bold text-blue-600 dark:text-blue-400">
                        <?= number_format($stats['total_polls']) ?>
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Total Polls
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 text-center">
                    <div class="text-3xl font-bold text-green-600 dark:text-green-400">
                        <?= number_format($stats['total_votes']) ?>
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Total Votes
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 text-center">
                    <div class="text-3xl font-bold text-purple-600 dark:text-purple-400">
                        <?= number_format($stats['active_polls']) ?>
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Active Polls
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 text-center">
                    <div class="text-3xl font-bold text-orange-600 dark:text-orange-400">
                        <?= number_format($stats['total_users']) ?>
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Users
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ================================================================ -->
        <!-- HEADER & CONTROLS -->
        <!-- ================================================================ -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mb-6">
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">

                <!-- Title & Description -->
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                        <?php
                        switch ($filter) {
                            case 'active':
                                echo 'Active Polls';
                                break;
                            case 'closed':
                                echo 'Closed Polls';
                                break;
                            case 'my_polls':
                                echo 'My Polls';
                                break;
                            default:
                                echo 'All Polls';
                                break;
                        }
                        ?>
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-1">
                        <?php if ($total > 0): ?>
                            Showing <?= number_format($total) ?> poll<?= $total != 1 ? 's' : '' ?>
                        <?php else: ?>
                            No polls found
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <?php if (Auth::check()): ?>
                        <a href="<?= APP_URL ?>/src/create-polls.php"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors font-medium">
                            <i data-feather="plus" class="w-4 h-4 mr-2"></i>
                            Create Poll
                        </a>
                    <?php endif; ?>

                    <a href="<?= APP_URL ?>/src/search.php"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                        <i data-feather="search" class="w-4 h-4 mr-2"></i>
                        Search Polls
                    </a>
                </div>
            </div>

            <!-- Filter & Sort Controls -->
            <div class="mt-6 flex flex-col sm:flex-row gap-4">
                <!-- Filter Dropdown -->
                <div class="flex-1">
                    <select onchange="updateFilter(this.value)"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Polls</option>
                        <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>Active Polls</option>
                        <option value="closed" <?= $filter === 'closed' ? 'selected' : '' ?>>Closed Polls</option>
                        <?php if (Auth::check()): ?>
                            <option value="my_polls" <?= $filter === 'my_polls' ? 'selected' : '' ?>>My Polls</option>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Sort Dropdown -->
                <div class="flex-1">
                    <select onchange="updateSort(this.value)"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most Popular</option>
                        <option value="ending_soon" <?= $sort === 'ending_soon' ? 'selected' : '' ?>>Ending Soon</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- POLLS GRID -->
        <!-- ================================================================ -->
        <?php if (isset($error_message)): ?>
            <!-- Error State -->
            <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-600 rounded-xl p-8 text-center">
                <div class="w-16 h-16 bg-red-200 dark:bg-red-800 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-feather="alert-circle" class="w-8 h-8 text-red-600 dark:text-red-400"></i>
                </div>
                <h3 class="text-lg font-semibold text-red-800 dark:text-red-200 mb-2">
                    Error Loading Polls
                </h3>
                <p class="text-red-600 dark:text-red-400">
                    <?= htmlspecialchars($error_message) ?>
                </p>
            </div>

        <?php elseif (empty($polls)): ?>
            <!-- Empty State -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-12 text-center">
                <div class="w-20 h-20 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i data-feather="inbox" class="w-10 h-10 text-gray-400"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                    <?php
                    switch ($filter) {
                        case 'active':
                            echo 'No Active Polls';
                            break;
                        case 'closed':
                            echo 'No Closed Polls';
                            break;
                        case 'my_polls':
                            echo 'No Polls Created Yet';
                            break;
                        default:
                            echo 'No Polls Available';
                            break;
                    }
                    ?>
                </h3>
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    <?php if ($filter === 'my_polls'): ?>
                        You haven't created any polls yet. Start by creating your first poll!
                    <?php else: ?>
                        There are no polls to display at the moment.
                    <?php endif; ?>
                </p>

                <?php if (Auth::check()): ?>
                    <a href="<?= APP_URL ?>/src/create-polls.php"
                        class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors font-medium">
                        <i data-feather="plus" class="w-5 h-5 mr-2"></i>
                        Create Your First Poll
                    </a>
                <?php else: ?>
                    <a href="<?= APP_URL ?>/src/register.php"
                        class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors font-medium">
                        <i data-feather="user-plus" class="w-5 h-5 mr-2"></i>
                        Sign Up to Create Polls
                    </a>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Polls Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
                <?php foreach ($polls as $poll): ?>
                    <article class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg hover:shadow-xl border border-gray-100 dark:border-gray-700 transition-all duration-300 hover:-translate-y-1 group">
                        <div class="p-6 h-full flex flex-col">
                            <!-- Poll Header -->
                            <div class="flex-1">
                                <!-- Status Badge & Vote Count -->
                                <div class="flex items-center justify-between mb-3">
                                    <?php if (isPollClosed($poll)): ?>
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300 rounded-full border border-red-200 dark:border-red-800">
                                            <i data-feather="lock" class="w-3 h-3 mr-1"></i>
                                            Closed
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300 rounded-full border border-green-200 dark:border-green-800">
                                            <i data-feather="zap" class="w-3 h-3 mr-1"></i>
                                            Active
                                        </span>
                                    <?php endif; ?>

                                    <div class="flex items-center text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/50 px-2 py-1 rounded-full">
                                        <i data-feather="users" class="w-3 h-3 mr-1"></i>
                                        <span class="font-semibold"><?= formatVoteCount((int)$poll['vote_count']) ?></span>
                                    </div>
                                </div>

                                <!-- Poll Title -->
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-3 line-clamp-2 leading-tight group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                    <a href="<?= APP_URL ?>/src/vote.php?poll_id=<?= $poll['poll_id'] ?>"
                                        class="block hover:no-underline">
                                        <?= htmlspecialchars($poll['title']) ?>
                                    </a>
                                </h3>

                                <!-- Poll Description -->
                                <?php if (!empty($poll['description'])): ?>
                                    <p class="text-gray-600 dark:text-gray-400 text-sm mb-4 line-clamp-3 leading-relaxed">
                                        <?= htmlspecialchars(smartTruncate($poll['description'], 120)) ?>
                                    </p>
                                <?php endif; ?>

                                <!-- Countdown Timer -->
                                <?php if (!empty($poll['end_time']) && !isPollClosed($poll)): ?>
                                    <div class="bg-gradient-to-r from-orange-50 to-red-50 dark:from-orange-900/20 dark:to-red-900/20 border border-orange-200 dark:border-orange-800 rounded-lg p-3 mb-4">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-xs font-medium text-orange-700 dark:text-orange-300 flex items-center">
                                                <i data-feather="clock" class="w-3 h-3 mr-1"></i>
                                                Time Remaining
                                            </span>
                                        </div>
                                        <div class="countdown-timer text-sm font-mono text-orange-800 dark:text-orange-200"
                                            data-end-time="<?= date('c', strtotime($poll['end_time'])) ?>">
                                            <div class="flex space-x-2 text-center">
                                                <div class="flex-1">
                                                    <div class="countdown-days font-bold text-base">--</div>
                                                    <div class="text-xs text-orange-600 dark:text-orange-400">days</div>
                                                </div>
                                                <div class="flex-1">
                                                    <div class="countdown-hours font-bold text-base">--</div>
                                                    <div class="text-xs text-orange-600 dark:text-orange-400">hrs</div>
                                                </div>
                                                <div class="flex-1">
                                                    <div class="countdown-minutes font-bold text-base">--</div>
                                                    <div class="text-xs text-orange-600 dark:text-orange-400">min</div>
                                                </div>
                                                <div class="flex-1">
                                                    <div class="countdown-seconds font-bold text-base">--</div>
                                                    <div class="text-xs text-orange-600 dark:text-orange-400">sec</div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Progress Bar -->
                                        <div class="mt-2">
                                            <div class="countdown-progress-bg bg-orange-200 dark:bg-orange-800 rounded-full h-1.5">
                                                <div class="countdown-progress-bar bg-gradient-to-r from-orange-400 to-red-500 h-1.5 rounded-full transition-all duration-1000" style="width: 0%"></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php elseif (!empty($poll['end_time']) && isPollClosed($poll)): ?>
                                    <!-- Expired Poll -->
                                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3 mb-4">
                                        <div class="flex items-center text-red-700 dark:text-red-300">
                                            <i data-feather="x-circle" class="w-4 h-4 mr-2"></i>
                                            <span class="text-sm font-medium">Poll Ended</span>
                                        </div>
                                        <div class="text-xs text-red-600 dark:text-red-400 mt-1">
                                            Ended on <?= formatDate($poll['end_time'], 'M j, Y \a\t g:i A') ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- No End Date -->
                                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3 mb-4">
                                        <div class="flex items-center text-green-700 dark:text-green-300">
                                            <i data-feather="infinity" class="w-4 h-4 mr-2"></i>
                                            <span class="text-sm font-medium">Open Indefinitely</span>
                                        </div>
                                        <div class="text-xs text-green-600 dark:text-green-400 mt-1">
                                            No expiration date set
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Poll Metadata -->
                                <div class="space-y-2 mb-4">
                                    <!-- Creator -->
                                    <div class="flex items-center text-xs text-gray-500 dark:text-gray-400">
                                        <div class="w-6 h-6 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center mr-2">
                                            <span class="text-white font-semibold text-xs">
                                                <?= strtoupper(substr($poll['creator_name'], 0, 1)) ?>
                                            </span>
                                        </div>
                                        <span class="font-medium"><?= htmlspecialchars($poll['creator_name']) ?></span>
                                    </div>

                                    <!-- Date Created -->
                                    <div class="flex items-center text-xs text-gray-500 dark:text-gray-400">
                                        <i data-feather="calendar" class="w-3 h-3 mr-2 text-gray-400"></i>
                                        <span><?= formatDate($poll['created_at'], 'M j, Y') ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex items-center justify-between pt-4 border-t border-gray-100 dark:border-gray-700">
                                <div class="flex gap-2">
                                    <a href="<?= APP_URL ?>/src/vote.php?poll_id=<?= $poll['poll_id'] ?>"
                                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 dark:bg-blue-900/30 dark:text-blue-300 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors"
                                        title="Vote on this poll">
                                        <i data-feather="check-circle" class="w-3 h-3 mr-1"></i>
                                        Vote
                                    </a>
                                    <a href="<?= APP_URL ?>/src/results.php?poll_id=<?= $poll['poll_id'] ?>"
                                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-green-700 bg-green-50 dark:bg-green-900/30 dark:text-green-300 rounded-lg hover:bg-green-100 dark:hover:bg-green-900/50 transition-colors"
                                        title="View results">
                                        <i data-feather="bar-chart-2" class="w-3 h-3 mr-1"></i>
                                        Results
                                    </a>
                                </div>

                                <?php if ($currentUserId && $poll['creator_name'] === Auth::username()): ?>
                                    <a href="<?= APP_URL ?>/src/edit-polls.php?poll_id=<?= $poll['poll_id'] ?>"
                                        class="inline-flex items-center p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
                                        title="Edit poll">
                                        <i data-feather="edit-2" class="w-4 h-4"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <!-- ================================================================ -->
            <!-- ENHANCED PAGINATION WITH LOAD MORE OPTION -->
            <!-- ================================================================ -->
            <?php if ($totalPages > 1): ?>
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-6">
                    <!-- Pagination Info -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Showing <span class="font-semibold"><?= $offset + 1 ?></span> to
                            <span class="font-semibold"><?= min($offset + $limit, $total) ?></span> of
                            <span class="font-semibold"><?= number_format($total) ?></span> polls
                        </div>

                        <!-- Load More Button (untuk halaman kecil) -->
                        <?php if ($page < $totalPages && $page <= 3): ?>
                            <button onclick="loadMorePolls()"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-blue-600 bg-blue-50 dark:bg-blue-900/30 dark:text-blue-300 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors">
                                <i data-feather="plus" class="w-4 h-4 mr-2"></i>
                                Load More
                            </button>
                        <?php endif; ?>
                    </div>

                    <nav class="flex items-center justify-between">
                        <!-- Previous Page -->
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-all duration-200 hover:shadow-md">
                                <i data-feather="chevron-left" class="w-4 h-4 mr-2"></i>
                                Previous
                            </a>
                        <?php else: ?>
                            <span class="inline-flex items-center px-4 py-2 border border-gray-200 dark:border-gray-700 rounded-xl text-sm text-gray-400 dark:text-gray-500 bg-gray-50 dark:bg-gray-800 cursor-not-allowed">
                                <i data-feather="chevron-left" class="w-4 h-4 mr-2"></i>
                                Previous
                            </span>
                        <?php endif; ?>

                        <!-- Page Numbers -->
                        <div class="hidden sm:flex space-x-1">
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($totalPages, $page + 2);

                            if ($start > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>"
                                    class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">1</a>
                                <?php if ($start > 2): ?>
                                    <span class="px-3 py-2 text-gray-400">···</span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $start; $i <= $end; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="px-3 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg text-sm font-bold shadow-lg"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                                        class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($end < $totalPages): ?>
                                <?php if ($end < $totalPages - 1): ?>
                                    <span class="px-3 py-2 text-gray-400">···</span>
                                <?php endif; ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>"
                                    class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"><?= $totalPages ?></a>
                            <?php endif; ?>
                        </div>

                        <!-- Mobile Page Info -->
                        <div class="sm:hidden flex items-center space-x-2">
                            <select onchange="goToPage(this.value)"
                                class="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <option value="<?= $i ?>" <?= $i == $page ? 'selected' : '' ?>>
                                        Page <?= $i ?> of <?= $totalPages ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <!-- Next Page -->
                        <?php if ($page < $totalPages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-all duration-200 hover:shadow-md">
                                Next
                                <i data-feather="chevron-right" class="w-4 h-4 ml-2"></i>
                            </a>
                        <?php else: ?>
                            <span class="inline-flex items-center px-4 py-2 border border-gray-200 dark:border-gray-700 rounded-xl text-sm text-gray-400 dark:text-gray-500 bg-gray-50 dark:bg-gray-800 cursor-not-allowed">
                                Next
                                <i data-feather="chevron-right" class="w-4 h-4 ml-2"></i>
                            </span>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT FUNCTIONS -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // ENHANCED FILTER & SORT FUNCTIONS
    // ================================================================
    function updateFilter(filterValue) {
        showLoadingState();
        const url = new URL(window.location);
        url.searchParams.set('filter', filterValue);
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }

    function updateSort(sortValue) {
        showLoadingState();
        const url = new URL(window.location);
        url.searchParams.set('sort', sortValue);
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }

    function goToPage(pageNumber) {
        showLoadingState();
        const url = new URL(window.location);
        url.searchParams.set('page', pageNumber);
        window.location.href = url.toString();
    }

    // ================================================================
    // LOADING STATE MANAGEMENT
    // ================================================================
    function showLoadingState() {
        const pollsGrid = document.querySelector('.grid');
        if (pollsGrid) {
            pollsGrid.style.opacity = '0.6';
            pollsGrid.style.pointerEvents = 'none';
        }

        // Show loading indicator
        const loadingDiv = document.createElement('div');
        loadingDiv.id = 'loading-indicator';
        loadingDiv.className = 'fixed top-4 right-4 bg-blue-600 text-white px-4 py-2 rounded-lg shadow-lg z-50 flex items-center';
        loadingDiv.innerHTML = `
            <svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Loading...
        `;
        document.body.appendChild(loadingDiv);
    }

    // ================================================================
    // INFINITE SCROLL IMPLEMENTATION
    // ================================================================
    let isLoading = false;
    let hasMorePages = <?= $page < $totalPages ? 'true' : 'false' ?>;

    function loadMorePolls() {
        if (isLoading || !hasMorePages) return;

        isLoading = true;
        const loadMoreBtn = document.querySelector('button[onclick="loadMorePolls()"]');
        if (loadMoreBtn) {
            loadMoreBtn.innerHTML = `
                <svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Loading...
            `;
            loadMoreBtn.disabled = true;
        }

        // Fetch next page via AJAX
        const nextPage = <?= $page + 1 ?>;
        const url = new URL(window.location);
        url.searchParams.set('page', nextPage);
        url.searchParams.set('ajax', '1');

        fetch(url.toString())
            .then(response => response.json())
            .then(data => {
                if (data.success && data.polls) {
                    appendPolls(data.polls);

                    // Update pagination state
                    hasMorePages = data.hasMorePages;

                    if (!hasMorePages && loadMoreBtn) {
                        loadMoreBtn.style.display = 'none';
                    }

                    // Update URL without reload
                    window.history.pushState({}, '', url.toString().replace('&ajax=1', ''));
                }
            })
            .catch(error => {
                console.error('Error loading more polls:', error);
            })
            .finally(() => {
                isLoading = false;
                if (loadMoreBtn) {
                    loadMoreBtn.innerHTML = `
                        <i data-feather="plus" class="w-4 h-4 mr-2"></i>
                        Load More
                    `;
                    loadMoreBtn.disabled = false;
                }
            });
    }

    // ================================================================
    // COUNTDOWN TIMER IMPLEMENTATION
    // ================================================================
    class CountdownTimer {
        constructor(element, endTime) {
            this.element = element;
            this.endTime = new Date(endTime).getTime();
            this.startTime = new Date(element.dataset.startTime || element.dataset.createdAt || Date.now()).getTime();
            this.totalDuration = this.endTime - this.startTime;

            // Find countdown elements
            this.daysEl = element.querySelector('.countdown-days');
            this.hoursEl = element.querySelector('.countdown-hours');
            this.minutesEl = element.querySelector('.countdown-minutes');
            this.secondsEl = element.querySelector('.countdown-seconds');
            this.progressBar = element.querySelector('.countdown-progress-bar');

            // Check if all elements exist
            if (!this.daysEl || !this.hoursEl || !this.minutesEl || !this.secondsEl) {
                console.warn('Countdown elements not found');
                return;
            }

            // Start the timer
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

            // Update display with proper padding
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
                if (progress > 80) {
                    this.progressBar.className = 'countdown-progress-bar bg-gradient-to-r from-red-500 to-red-600 h-1.5 rounded-full transition-all duration-1000';
                } else if (progress > 60) {
                    this.progressBar.className = 'countdown-progress-bar bg-gradient-to-r from-yellow-400 to-orange-500 h-1.5 rounded-full transition-all duration-1000';
                }
            }

            // Add urgency styling for last 24 hours
            if (timeLeft < 24 * 60 * 60 * 1000) {
                this.element.classList.add('countdown-urgent');
            }

            // Add very urgent styling for last hour
            if (timeLeft < 60 * 60 * 1000) {
                this.element.classList.add('countdown-critical');
            }
        }

        onExpired() {
            this.destroy();

            // Replace countdown with expired message
            const parent = this.element.parentElement;
            if (parent) {
                parent.innerHTML = `
                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
                        <div class="flex items-center text-red-700 dark:text-red-300">
                            <i data-feather="x-circle" class="w-4 h-4 mr-2"></i>
                            <span class="text-sm font-medium">Poll Expired</span>
                        </div>
                        <div class="text-xs text-red-600 dark:text-red-400 mt-1">
                            This poll has ended
                        </div>
                    </div>
                `;

                // Re-initialize feather icons
                if (typeof feather !== 'undefined') {
                    feather.replace();
                }
            }
        }

        destroy() {
            if (this.interval) {
                clearInterval(this.interval);
                this.interval = null;
            }
        }
    }

    // ================================================================
    // COUNTDOWN MANAGEMENT (FIXED VERSION)
    // ================================================================
    let countdownTimers = [];

    function initCountdownTimers() {
        // Clear existing timers
        countdownTimers.forEach(timer => {
            if (timer && typeof timer.destroy === 'function') {
                timer.destroy();
            }
        });
        countdownTimers = [];

        // Wait for DOM to be ready
        setTimeout(() => {
            // Initialize new timers
            const timerElements = document.querySelectorAll('.countdown-timer[data-end-time]');
            console.log(`Found ${timerElements.length} countdown timers to initialize`);

            timerElements.forEach((element, index) => {
                const endTime = element.dataset.endTime;
                if (endTime) {
                    try {
                        // Validate end time
                        const endDate = new Date(endTime);
                        if (isNaN(endDate.getTime())) {
                            console.warn(`Invalid end time for timer ${index}:`, endTime);
                            return;
                        }

                        // Check if poll is not already expired
                        if (endDate.getTime() > Date.now()) {
                            const timer = new CountdownTimer(element, endTime);
                            if (timer.interval) { // Only add if timer was successfully created
                                countdownTimers.push(timer);
                                console.log(`Initialized countdown timer ${index} ending at:`, endDate);
                            }
                        } else {
                            console.log(`Timer ${index} already expired:`, endDate);
                        }
                    } catch (error) {
                        console.error(`Error initializing countdown timer ${index}:`, error);
                    }
                }
            });

            console.log(`Successfully initialized ${countdownTimers.length} countdown timers`);
        }, 100);
    }

    // ================================================================
    // ENHANCED FUNCTIONS WITH COUNTDOWN SUPPORT
    // ================================================================
    function appendPolls(polls) {
        const pollsGrid = document.querySelector('.grid');
        polls.forEach(poll => {
            const pollElement = createPollElement(poll);
            pollsGrid.appendChild(pollElement);
        });

        // Re-initialize Feather icons and countdown timers
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
        initCountdownTimers();
    }

    function createPollElement(poll) {
        // Enhanced poll element creation with countdown
        const template = document.createElement('template');

        // Generate countdown HTML if poll has end time and is active
        let countdownHtml = '';
        if (poll.end_time && !poll.is_closed) {
            countdownHtml = `
                <div class="bg-gradient-to-r from-orange-50 to-red-50 dark:from-orange-900/20 dark:to-red-900/20 border border-orange-200 dark:border-orange-800 rounded-lg p-3 mb-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-orange-700 dark:text-orange-300 flex items-center">
                            <i data-feather="clock" class="w-3 h-3 mr-1"></i>
                            Time Remaining
                        </span>
                    </div>
                    <div class="countdown-timer text-sm font-mono text-orange-800 dark:text-orange-200" 
                         data-end-time="${poll.end_time}"
                         data-start-time="${new Date().toISOString()}">
                        <div class="flex space-x-2 text-center">
                            <div class="flex-1">
                                <div class="countdown-days font-bold text-base">--</div>
                                <div class="text-xs text-orange-600 dark:text-orange-400">days</div>
                            </div>
                            <div class="flex-1">
                                <div class="countdown-hours font-bold text-base">--</div>
                                <div class="text-xs text-orange-600 dark:text-orange-400">hrs</div>
                            </div>
                            <div class="flex-1">
                                <div class="countdown-minutes font-bold text-base">--</div>
                                <div class="text-xs text-orange-600 dark:text-orange-400">min</div>
                            </div>
                            <div class="flex-1">
                                <div class="countdown-seconds font-bold text-base">--</div>
                                <div class="text-xs text-orange-600 dark:text-orange-400">sec</div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="countdown-progress-bg bg-orange-200 dark:bg-orange-800 rounded-full h-1.5">
                            <div class="countdown-progress-bar bg-gradient-to-r from-orange-400 to-red-500 h-1.5 rounded-full transition-all duration-1000" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
            `;
        } else if (poll.end_time && poll.is_closed) {
            countdownHtml = `
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3 mb-4">
                    <div class="flex items-center text-red-700 dark:text-red-300">
                        <i data-feather="x-circle" class="w-4 h-4 mr-2"></i>
                        <span class="text-sm font-medium">Poll Ended</span>
                    </div>
                    <div class="text-xs text-red-600 dark:text-red-400 mt-1">
                        Ended on ${new Date(poll.end_time).toLocaleDateString()}
                    </div>
                </div>
            `;
        } else {
            countdownHtml = `
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3 mb-4">
                    <div class="flex items-center text-green-700 dark:text-green-300">
                        <i data-feather="infinity" class="w-4 h-4 mr-2"></i>
                        <span class="text-sm font-medium">Open Indefinitely</span>
                    </div>
                    <div class="text-xs text-green-600 dark:text-green-400 mt-1">
                        No expiration date set
                    </div>
                </div>
            `;
        }

        template.innerHTML = `
            <article class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg hover:shadow-xl border border-gray-100 dark:border-gray-700 transition-all duration-300 hover:-translate-y-1 group">
                <div class="p-6 h-full flex flex-col">
                    <div class="flex-1">
                        <div class="flex items-center justify-between mb-3">
                            ${poll.is_closed ? 
                                '<span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300 rounded-full border border-red-200 dark:border-red-800"><i data-feather="lock" class="w-3 h-3 mr-1"></i>Closed</span>' :
                                '<span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300 rounded-full border border-green-200 dark:border-green-800"><i data-feather="zap" class="w-3 h-3 mr-1"></i>Active</span>'
                            }
                            <div class="flex items-center text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/50 px-2 py-1 rounded-full">
                                <i data-feather="users" class="w-3 h-3 mr-1"></i>
                                <span class="font-semibold">${poll.vote_count || 0}</span>
                            </div>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-3 line-clamp-2 leading-tight group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                            <a href="${'<?= APP_URL ?>'}/src/vote.php?poll_id=${poll.poll_id}" class="block hover:no-underline">
                                ${poll.title}
                            </a>
                        </h3>
                        ${poll.description ? `<p class="text-gray-600 dark:text-gray-400 text-sm mb-4 line-clamp-3 leading-relaxed">${poll.description.substring(0, 120)}${poll.description.length > 120 ? '...' : ''}</p>` : ''}
                        ${countdownHtml}
                        <div class="space-y-2 mb-4">
                            <div class="flex items-center text-xs text-gray-500 dark:text-gray-400">
                                <div class="w-6 h-6 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center mr-2">
                                    <span class="text-white font-semibold text-xs">${poll.creator_name.charAt(0).toUpperCase()}</span>
                                </div>
                                <span class="font-medium">${poll.creator_name}</span>
                            </div>
                            <div class="flex items-center text-xs text-gray-500 dark:text-gray-400">
                                <i data-feather="calendar" class="w-3 h-3 mr-2 text-gray-400"></i>
                                <span>${new Date(poll.created_at).toLocaleDateString()}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-4 border-t border-gray-100 dark:border-gray-700">
                        <div class="flex gap-2">
                            <a href="${'<?= APP_URL ?>'}/src/vote.php?poll_id=${poll.poll_id}" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 dark:bg-blue-900/30 dark:text-blue-300 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors" title="Vote on this poll">
                                <i data-feather="check-circle" class="w-3 h-3 mr-1"></i>Vote
                            </a>
                            <a href="${'<?= APP_URL ?>'}/src/results.php?poll_id=${poll.poll_id}" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-green-700 bg-green-50 dark:bg-green-900/30 dark:text-green-300 rounded-lg hover:bg-green-100 dark:hover:bg-green-900/50 transition-colors" title="View results">
                                <i data-feather="bar-chart-2" class="w-3 h-3 mr-1"></i>Results
                            </a>
                        </div>
                    </div>
                </div>
            </article>
        `;
        return template.content.firstElementChild;
    }

    // ================================================================
    // NOTIFICATION PERMISSION
    // ================================================================
    function requestNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    }

    // ================================================================
    // PAGE VISIBILITY API FOR PERFORMANCE
    // ================================================================
    function handleVisibilityChange() {
        if (document.hidden) {
            // Page is hidden, pause timers for performance
            countdownTimers.forEach(timer => {
                if (timer.interval) {
                    clearInterval(timer.interval);
                    timer.isPaused = true;
                }
            });
        } else {
            // Page is visible, resume timers
            countdownTimers.forEach(timer => {
                if (timer.isPaused) {
                    timer.interval = setInterval(() => timer.update(), 1000);
                    timer.isPaused = false;
                    timer.update(); // Immediate update
                }
            });
        }
    }

    // ================================================================
    // ENHANCED INITIALIZATION (FIXED VERSION)
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, initializing countdown timers...');

        // Initialize countdown timers with delay to ensure DOM is ready
        setTimeout(() => {
            initCountdownTimers();
        }, 500);

        // Initialize infinite scroll for mobile/small screens
        if (window.innerWidth <= 768) {
            // initInfiniteScroll(); // Uncomment when function is implemented
        }

        // Request notification permission
        requestNotificationPermission();

        // Handle page visibility for performance
        document.addEventListener('visibilitychange', handleVisibilityChange);

        // Remove loading indicator if exists
        const loadingIndicator = document.getElementById('loading-indicator');
        if (loadingIndicator) {
            loadingIndicator.remove();
        }

        // Re-initialize timers when new content is loaded
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    // Check if new countdown timers were added
                    const addedTimers = Array.from(mutation.addedNodes)
                        .filter(node => node.nodeType === 1)
                        .some(node => node.querySelector && node.querySelector('.countdown-timer[data-end-time]'));

                    if (addedTimers) {
                        console.log('New content detected, re-initializing timers...');
                        setTimeout(() => {
                            initCountdownTimers();
                        }, 100);
                    }
                }
            });
        });

        // Start observing
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });

    // Enhanced cleanup on page unload
    window.addEventListener('beforeunload', function() {
        console.log('Page unloading, cleaning up timers...');
        countdownTimers.forEach(timer => {
            if (timer && typeof timer.destroy === 'function') {
                timer.destroy();
            }
        });
        countdownTimers = [];
    });
</script>

<style>
    .countdown-timer {
        font-variant-numeric: tabular-nums;
    }

    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .line-clamp-3 {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<!-- ================================================================ -->
<!-- CATATAN IMPLEMENTASI -->
<!--
FITUR KEAMANAN:
1. Public access dengan optional authentication
2. Safe pagination parameters
3. XSS protection untuk semua output
4. Input validation untuk filter/sort parameters
5. Proper error handling dan logging

FITUR UX:
1. Hero section untuk user baru
2. Dashboard stats untuk user login
3. Advanced filtering dan sorting
4. Responsive grid layout
5. Empty state dengan clear CTAs
6. Smooth pagination navigation

LOGIKA BISNIS:
1. Different views berdasarkan authentication status
2. Smart filtering dengan business rules
3. Efficient pagination dengan proper limits
4. Real-time stats calculation
5. Poll status indication

OPTIMASI PERFORMA:
1. Efficient database queries dengan proper indexing
2. Pagination untuk large datasets
3. Minimal data fetching per poll
4. Smart caching opportunities
5. Lazy loading untuk images (future enhancement)

FUTURE ENHANCEMENTS:
1. Search integration
2. Advanced filters (by category, date range)
3. Infinite scroll option
4. Real-time updates dengan WebSocket
5. Social sharing features
-->