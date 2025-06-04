<?php
$pageTitle = 'Search Results';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../database/Database.php';
// utils.php is already included by header.php

$q = trim($_GET['q'] ?? '');
$results = [];
$total = 0;

if ($q !== '') {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("
        SELECT p.*, u.username as creator_name,
            (SELECT COUNT(*) FROM votes v JOIN poll_options o ON v.option_id = o.option_id WHERE o.poll_id = p.poll_id) as vote_count
        FROM polls p
        JOIN users u ON p.creator_id = u.user_id
        WHERE p.title LIKE ? OR p.description LIKE ?
        ORDER BY p.created_at DESC
        LIMIT 50
    ");
    $searchTerm = '%' . $q . '%';
    $stmt->execute([$searchTerm, $searchTerm]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = count($results); // Use count($results) as $stmt->rowCount() might not be reliable for SELECT with all DB drivers
}
?>

<main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">Search Results</h1>
    <form action="<?= APP_URL ?>/src/search.php" method="get" class="mb-8">
        <div class="flex items-center gap-2">
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search polls by title or description..." autofocus
                class="w-full md:flex-1 rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-200 py-2.5 px-3.5 placeholder-gray-400 dark:placeholder-gray-500 text-base transition" />
            <button type="submit" class="inline-flex items-center px-4 py-2.5 rounded-md bg-blue-600 hover:bg-blue-700 text-white font-medium shadow-sm transition focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                <i data-feather="search" class="w-4 h-4 mr-2"></i> Search
            </button>
        </div>
    </form>

    <?php if ($q === ''): ?>
        <div class="text-gray-500 dark:text-gray-400 text-center py-12">
            Enter a keyword to search for polls.
        </div>
    <?php elseif ($total === 0): ?>
        <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-xl shadow p-6">
            <i data-feather="search" class="w-16 h-16 mx-auto text-gray-400 dark:text-gray-500 mb-4"></i>
            <h3 class="text-xl font-medium text-gray-700 dark:text-gray-200 mb-2">No Polls Found</h3>
            <p class="text-gray-500 dark:text-gray-400">
                No polls found matching "<span class="font-semibold"><?= htmlspecialchars($q) ?></span>". Try a different keyword.
            </p>
        </div>
    <?php else: ?>
        <p class="mb-6 text-gray-700 dark:text-gray-300">Found <?= $total ?> poll<?= $total > 1 ? 's' : '' ?> matching "<span class="font-semibold"><?= htmlspecialchars($q) ?></span>".</p>
        <div class="grid gap-6 md:grid-cols-1 lg:grid-cols-2">
            <?php foreach ($results as $poll): ?>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-200 dark:border-gray-700 hover:shadow-2xl transition-all duration-300">
                    <div class="p-6">
                        <div class="flex justify-between items-start mb-3">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white leading-tight">
                                <a href="<?= APP_URL ?>/src/vote.php?poll_id=<?= $poll['poll_id'] ?>" class="hover:text-blue-600 dark:hover:text-blue-400 transition-colors line-clamp-2">
                                    <?= htmlspecialchars($poll['title']) ?>
                                </a>
                            </h3>
                            <span class="flex-shrink-0 px-3 py-1 text-xs font-semibold rounded-full 
                                <?= isPollClosed($poll) ? 'bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-200' : 'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-200' ?>">
                                <?= isPollClosed($poll) ? 'Closed' : 'Active' ?>
                            </span>
                        </div>
                        <?php if (!empty($poll['description'])): ?>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3 line-clamp-2">
                                <?= htmlspecialchars($poll['description']) ?>
                            </p>
                        <?php endif; ?>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">
                            By <span class="font-medium text-blue-600 dark:text-blue-400"><?= htmlspecialchars($poll['creator_name']) ?></span>
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">
                            Created: <?= formatDate($poll['created_at']) ?>
                        </p>
                        <?php if (!empty($poll['end_time'])): ?>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                                <?= isPollClosed($poll) ? 'Ended' : 'Ends' ?>: <?= formatDate($poll['end_time']) ?>
                            </p>
                        <?php endif; ?>
                        <div class="flex items-center text-sm text-gray-500 dark:text-gray-400 mb-4">
                            <i data-feather="bar-chart-2" class="w-4 h-4 mr-2 text-blue-500"></i>
                            <?= htmlspecialchars($poll['vote_count']) ?> vote<?= $poll['vote_count'] != 1 ? 's' : '' ?>
                        </div>
                        <a href="<?= APP_URL ?>/src/vote.php?poll_id=<?= $poll['poll_id'] ?>" class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-gray-100 dark:focus:ring-offset-gray-900 focus:ring-blue-500 transition-all hover:shadow-md active:scale-95">
                            <i data-feather="check-square" class="w-4 h-4 mr-2"></i>
                            <?= isPollClosed($poll) ? 'View Results' : 'Vote Now' ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>