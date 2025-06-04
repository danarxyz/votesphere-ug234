<?php

/**
 * ================================================================
 * HALAMAN EDIT POLLING - MODIFY EXISTING POLL
 * ================================================================
 * 
 * File: edit-polls.php
 * Fungsi: Halaman untuk mengedit polling yang sudah ada
 * 
 * FITUR UTAMA:
 * - Edit title, description, dan end_time
 * - Add/remove/edit poll options
 * - Validation untuk perubahan yang tidak merusak
 * - Preview changes sebelum save
 * - Restrictions untuk poll yang sudah ada votes
 * 
 * KEAMANAN:
 * - Authentication required
 * - Authorization check (creator only)
 * - Input validation comprehensive
 * - Business rule enforcement
 * 
 * AUTHOR: VoteSphere Team
 * VERSION: 1.0
 * ================================================================
 */

// ================================================================
// SETUP AWAL & KONFIGURASI
// ================================================================
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/Auth.php';

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
$options = [];
$errors = [];
$hasVotes = false;
$totalVotes = 0;

// Form values untuk repopulation
$formData = [
    'title' => '',
    'description' => '',
    'end_time' => '',
    'options' => []
];

try {
    // ================================================================
    // KONEKSI DATABASE & QUERY DATA
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
    // AUTHORIZATION CHECK - HANYA CREATOR YANG BISA EDIT
    // ================================================================
    if ($poll['creator_id'] != $currentUserId) {
        $_SESSION['toast'] = [
            'message' => 'You are not authorized to edit this poll.',
            'type' => 'error'
        ];
        header('Location: ' . APP_URL . '/src/vote.php?poll_id=' . $pollId);
        exit;
    }

    // ================================================================
    // QUERY 2: AMBIL SEMUA OPTIONS DENGAN DETAIL VOTES
    // ================================================================
    $stmtOptions = $pdo->prepare("
        SELECT 
            po.option_id, 
            po.option_text,
            COUNT(v.vote_id) as vote_count
        FROM poll_options po
        LEFT JOIN votes v ON po.option_id = v.option_id
        WHERE po.poll_id = ? 
        GROUP BY po.option_id, po.option_text
        ORDER BY po.option_id ASC
    ");
    $stmtOptions->execute([$pollId]);
    $options = $stmtOptions->fetchAll(PDO::FETCH_ASSOC);

    // ================================================================
    // HITUNG TOTAL VOTES DAN SET FLAGS
    // ================================================================
    $totalVotes = array_sum(array_column($options, 'vote_count'));
    $hasVotes = $totalVotes > 0;

    // ================================================================
    // SET DEFAULT FORM VALUES DARI DATABASE
    // ================================================================
    $formData = [
        'title' => $poll['title'],
        'description' => $poll['description'] ?? '',
        'end_time' => !empty($poll['end_time']) ? 
            date('Y-m-d\TH:i', strtotime($poll['end_time'])) : '',
        'options' => array_column($options, 'option_text') // KUNCI: Ambil text dari database
    ];

} catch (Exception $e) {
    error_log('Edit poll page error: ' . $e->getMessage());
    $_SESSION['toast'] = [
        'message' => 'Error loading poll data.',
        'type' => 'error'
    ];
    header('Location: ' . APP_URL . '/public/index.php');
    exit;
}

// ================================================================
// PROSES FORM SUBMISSION
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ================================================================
    // AMBIL DAN SANITASI INPUT FORM
    // ================================================================
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $end_time = trim($_POST['end_time'] ?? '');
    $new_options = array_filter(array_map('trim', $_POST['options'] ?? []), 'strlen');

    // Update form data untuk repopulate jika ada error
    $formData = [
        'title' => $title,
        'description' => $description,
        'end_time' => $end_time,
        'options' => $new_options
    ];

    // ================================================================
    // VALIDASI INPUT
    // ================================================================
    $errors = validatePollData($title, $description, $end_time, $new_options, $poll);

    // ================================================================
    // VALIDASI BUSINESS RULES UNTUK POLL DENGAN VOTES
    // ================================================================
    if ($hasVotes && empty($errors)) {
        $errors = array_merge($errors, validateVotingRestrictions($options, $new_options));
    }

    // ================================================================
    // PROSES UPDATE JIKA TIDAK ADA ERROR
    // ================================================================
    if (empty($errors)) {
        try {
            updatePoll($pdo, $pollId, $title, $description, $end_time, $new_options, $options, $hasVotes);
            
            $_SESSION['toast'] = [
                'message' => 'Poll updated successfully!',
                'type' => 'success'
            ];

            header('Location: ' . APP_URL . '/src/vote.php?poll_id=' . $pollId);
            exit;

        } catch (Exception $e) {
            error_log('Poll update error: ' . $e->getMessage());
            $errors[] = 'Error updating poll. Please try again.';
        }
    }
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================

/**
 * Validasi data polling
 */
function validatePollData($title, $description, $end_time, $options, $poll) {
    $errors = [];

    // Validasi title
    if (empty($title)) {
        $errors[] = 'Poll title is required.';
    } elseif (strlen($title) > 255) {
        $errors[] = 'Poll title is too long (max 255 characters).';
    } elseif (strlen($title) < 5) {
        $errors[] = 'Poll title must be at least 5 characters long.';
    }

    // Validasi description
    if (!empty($description) && strlen($description) > 1000) {
        $errors[] = 'Description is too long (max 1000 characters).';
    }

    // Validasi end time
    if (!empty($end_time)) {
        try {
            $parsed_end_time = new DateTime($end_time);
            $now = new DateTime();
            $current_end_time = !empty($poll['end_time']) ? new DateTime($poll['end_time']) : null;

            // Jika poll belum berakhir dan end time baru di masa lalu
            if ($parsed_end_time <= $now && (!$current_end_time || $current_end_time > $now)) {
                $errors[] = 'End time must be in the future.';
            }

            // End time tidak boleh terlalu jauh
            $max_end_time = clone $now;
            $max_end_time->add(new DateInterval('P1Y'));

            if ($parsed_end_time > $max_end_time) {
                $errors[] = 'End time cannot be more than 1 year from now.';
            }
        } catch (Exception $e) {
            $errors[] = 'Invalid end time format.';
        }
    }

    // Validasi options
    if (count($options) < 2) {
        $errors[] = 'Poll must have at least 2 options.';
    }

    if (count($options) > 20) {
        $errors[] = 'Poll cannot have more than 20 options.';
    }

    // Check duplicate options
    if (count($options) !== count(array_unique($options))) {
        $errors[] = 'Duplicate options are not allowed.';
    }

    // Check option length
    foreach ($options as $option) {
        if (strlen($option) > 255) {
            $errors[] = 'Option text is too long (max 255 characters): ' . htmlspecialchars(substr($option, 0, 50)) . '...';
            break;
        }
    }

    return $errors;
}

/**
 * Validasi restrictions untuk poll yang sudah ada votes
 */
function validateVotingRestrictions($currentOptions, $newOptions) {
    $errors = [];
    $currentOptionTexts = array_column($currentOptions, 'option_text');
    $removedOptions = array_diff($currentOptionTexts, $newOptions);

    foreach ($removedOptions as $removedOption) {
        // Cari vote count untuk option yang akan dihapus
        foreach ($currentOptions as $option) {
            if ($option['option_text'] === $removedOption && $option['vote_count'] > 0) {
                $errors[] = 'Cannot remove option "' . htmlspecialchars($removedOption) . '" because it has ' . $option['vote_count'] . ' vote(s).';
                break;
            }
        }
    }

    return $errors;
}

/**
 * Update poll data ke database
 */
function updatePoll($pdo, $pollId, $title, $description, $end_time, $newOptions, $currentOptions, $hasVotes) {
    $pdo->beginTransaction();

    try {
        // Update poll data
        $stmt = $pdo->prepare("
            UPDATE polls 
            SET title = ?, description = ?, end_time = ?, updated_at = NOW()
            WHERE poll_id = ?
        ");

        $db_end_time = !empty($end_time) ? 
            (new DateTime($end_time))->format('Y-m-d H:i:s') : null;
        
        $stmt->execute([
            $title,
            $description ?: null,
            $db_end_time,
            $pollId
        ]);

        // Update options
        updatePollOptions($pdo, $pollId, $newOptions, $currentOptions, $hasVotes);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
}

/**
 * Update poll options
 */
function updatePollOptions($pdo, $pollId, $newOptions, $currentOptions, $hasVotes) {
    $currentOptionTexts = array_column($currentOptions, 'option_text');
    
    if ($hasVotes) {
        // Jika ada votes, hanya hapus options yang tidak ada votes
        $removedOptions = array_diff($currentOptionTexts, $newOptions);
        
        foreach ($removedOptions as $removedOption) {
            // Hapus hanya jika tidak ada votes
            $hasVotesForOption = false;
            foreach ($currentOptions as $option) {
                if ($option['option_text'] === $removedOption && $option['vote_count'] > 0) {
                    $hasVotesForOption = true;
                    break;
                }
            }
            
            if (!$hasVotesForOption) {
                $stmt = $pdo->prepare("DELETE FROM poll_options WHERE poll_id = ? AND option_text = ?");
                $stmt->execute([$pollId, $removedOption]);
            }
        }
    } else {
        // Jika belum ada votes, hapus semua options lama
        $stmt = $pdo->prepare("DELETE FROM poll_options WHERE poll_id = ?");
        $stmt->execute([$pollId]);
        $currentOptionTexts = []; // Reset karena semua sudah dihapus
    }

    // Add new options
    $newOptionTexts = array_diff($newOptions, $currentOptionTexts);
    $stmt = $pdo->prepare("INSERT INTO poll_options (poll_id, option_text, created_at) VALUES (?, ?, NOW())");
    
    foreach ($newOptionTexts as $newOption) {
        $stmt->execute([$pollId, $newOption]);
    }
}

// ================================================================
// SETUP UNTUK TEMPLATE HTML
// ================================================================
$pageTitle = 'Edit Poll: ' . htmlspecialchars($poll['title']);
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ================================================================ -->
<!-- TEMPLATE HTML - FORM EDIT POLL -->
<!-- ================================================================ -->
<div class="container mx-auto px-4 py-8 max-w-4xl">

    <!-- ================================================================ -->
    <!-- HEADER SECTION -->
    <!-- ================================================================ -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                    Edit Poll
                </h1>
                <p class="text-gray-600 dark:text-gray-400">
                    Make changes to your poll settings and options.
                </p>
            </div>

            <!-- Poll Stats -->
            <div class="text-right">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">
                    <?= number_format($totalVotes) ?>
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Total Votes
                </div>
            </div>
        </div>

        <!-- Voting Restriction Notice -->
        <?php if ($hasVotes): ?>
            <div class="bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700 rounded-lg p-4">
                <div class="flex items-center">
                    <i data-feather="info" class="w-5 h-5 text-amber-600 dark:text-amber-400 mr-2"></i>
                    <div>
                        <h3 class="font-medium text-amber-800 dark:text-amber-200">
                            Editing Restrictions
                        </h3>
                        <p class="text-sm text-amber-700 dark:text-amber-300 mt-1">
                            This poll has received votes. You cannot remove options that have votes, but you can add new options and modify the title, description, and end time.
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- ERROR MESSAGES -->
    <!-- ================================================================ -->
    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded-lg">
            <div class="flex items-center mb-2">
                <i data-feather="alert-circle" class="w-5 h-5 text-red-600 dark:text-red-400 mr-2"></i>
                <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                    Please fix the following errors:
                </h3>
            </div>
            <ul class="list-disc list-inside text-sm text-red-700 dark:text-red-300 space-y-1">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- EDIT FORM -->
    <!-- ================================================================ -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8">
        <form method="POST" action="" class="space-y-6">

            <!-- Poll Title -->
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Poll Title <span class="text-red-500">*</span>
                </label>
                <input type="text"
                    id="title"
                    name="title"
                    required
                    maxlength="255"
                    value="<?= htmlspecialchars($formData['title']) ?>"
                    class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400"
                    placeholder="What would you like to ask?">
            </div>

            <!-- Poll Description -->
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Description (Optional)
                </label>
                <textarea id="description"
                    name="description"
                    rows="3"
                    maxlength="1000"
                    class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400"
                    placeholder="Provide additional context for your poll..."><?= htmlspecialchars($formData['description']) ?></textarea>
            </div>

            <!-- End Time -->
            <div>
                <label for="end_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    End Time (Optional)
                </label>
                <input type="datetime-local"
                    id="end_time"
                    name="end_time"
                    value="<?= htmlspecialchars($formData['end_time']) ?>"
                    class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Leave empty for a poll that never expires.
                </p>
            </div>

            <!-- Poll Options -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Poll Options <span class="text-red-500">*</span>
                </label>

                <div id="options-container" class="space-y-3">
                    
                    <!-- TAMPILKAN SEMUA OPTIONS EXISTING -->
                    <?php foreach ($formData['options'] as $index => $option): ?>
                        <?php 
                        // Check if this option has votes
                        $optionHasVotes = false;
                        $voteCount = 0;
                        
                        if ($hasVotes) {
                            foreach ($options as $dbOption) {
                                if ($dbOption['option_text'] === $option) {
                                    $optionHasVotes = $dbOption['vote_count'] > 0;
                                    $voteCount = $dbOption['vote_count'];
                                    break;
                                }
                            }
                        }
                        ?>
                        
                        <div class="option-row flex items-center space-x-3">
                            <div class="flex-1">
                                <div class="relative">
                                    <input type="text"
                                        name="options[]"
                                        value="<?= htmlspecialchars($option) ?>"
                                        maxlength="255"
                                        required
                                        <?= $optionHasVotes ? 'readonly' : '' ?>
                                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 <?= $optionHasVotes ? 'bg-gray-100 dark:bg-gray-600 cursor-not-allowed' : '' ?>"
                                        placeholder="Option <?= $index + 1 ?>">
                                    
                                    <?php if ($optionHasVotes): ?>
                                        <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                                            <div class="flex items-center space-x-2">
                                                <span class="text-xs text-blue-600 dark:text-blue-400 font-medium">
                                                    <?= $voteCount ?> vote<?= $voteCount != 1 ? 's' : '' ?>
                                                </span>
                                                <i data-feather="lock" class="w-4 h-4 text-blue-600 dark:text-blue-400"></i>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (!$optionHasVotes): ?>
                                <button type="button"
                                    onclick="removeOption(this)"
                                    class="p-2 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 focus:outline-none"
                                    title="Remove option">
                                    <i data-feather="trash-2" class="w-5 h-5"></i>
                                </button>
                            <?php else: ?>
                                <div class="p-2 text-gray-400" title="Cannot remove - has votes">
                                    <i data-feather="lock" class="w-5 h-5"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <!-- Container untuk options baru yang ditambahkan via JavaScript -->
                    <div id="new-options-container" class="space-y-3">
                        <!-- New options will be added here -->
                    </div>

                    <!-- Add Option Button -->
                    <div class="flex justify-between items-center pt-3">
                        <button type="button"
                            id="add-option-btn"
                            onclick="addOption()"
                            class="inline-flex items-center px-3 py-2 text-sm bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded-lg hover:bg-blue-200 dark:hover:bg-blue-800/50 transition-colors">
                            <i data-feather="plus" class="w-4 h-4 mr-1"></i>
                            Add Option
                        </button>
                        <span id="option-count" class="text-sm text-gray-500 dark:text-gray-400">
                            <?= count($formData['options']) ?> / 20 options
                        </span>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-gray-200 dark:border-gray-700">
                <!-- Save Button -->
                <button type="submit"
                    class="flex-1 sm:flex-none inline-flex justify-center items-center px-6 py-3 border border-transparent rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 font-medium transition-all duration-200">
                    <i data-feather="save" class="w-5 h-5 mr-2"></i>
                    Save Changes
                </button>

                <!-- Cancel Button -->
                <a href="<?= APP_URL ?>/src/vote.php?poll_id=<?= $pollId ?>"
                    class="flex-1 sm:flex-none inline-flex justify-center items-center px-6 py-3 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 dark:focus:ring-offset-gray-800 font-medium transition-all duration-200">
                    <i data-feather="x" class="w-5 h-5 mr-2"></i>
                    Cancel
                </a>

                <!-- Delete Button -->
                <a href="<?= APP_URL ?>/src/delete-poll.php?poll_id=<?= $pollId ?>"
                    onclick="return confirm('Are you sure you want to delete this poll? This action cannot be undone.')"
                    class="flex-1 sm:flex-none inline-flex justify-center items-center px-6 py-3 border border-red-300 dark:border-red-600 rounded-lg text-red-700 dark:text-red-300 bg-white dark:bg-gray-700 hover:bg-red-50 dark:hover:bg-red-900/30 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800 font-medium transition-all duration-200">
                    <i data-feather="trash-2" class="w-5 h-5 mr-2"></i>
                    Delete Poll
                </a>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript for Dynamic Options Management -->
<script>
let optionCount = <?= count($formData['options']) ?>;
const maxOptions = 20;

function addOption() {
    if (optionCount >= maxOptions) {
        alert('Maximum 20 options allowed');
        return;
    }

    optionCount++;
    
    const container = document.getElementById('new-options-container');
    const optionDiv = document.createElement('div');
    optionDiv.className = 'option-row flex items-center space-x-3';
    
    optionDiv.innerHTML = `
        <div class="flex-1">
            <input type="text"
                name="options[]"
                maxlength="255"
                required
                class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400"
                placeholder="Option ${optionCount}">
        </div>
        <button type="button"
            onclick="removeOption(this)"
            class="p-2 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 focus:outline-none"
            title="Remove option">
            <i data-feather="trash-2" class="w-5 h-5"></i>
        </button>
    `;
    
    container.appendChild(optionDiv);
    updateOptionCount();
    
    // Re-initialize feather icons
    feather.replace();
    
    // Focus on new input
    optionDiv.querySelector('input').focus();
}

function removeOption(button) {
    // Count non-readonly options (options that can be removed)
    const editableOptions = document.querySelectorAll('input[name="options[]"]:not([readonly])').length;
    
    if (editableOptions <= 2) {
        alert('Poll must have at least 2 options');
        return;
    }
    
    const optionRow = button.closest('.option-row');
    if (optionRow) {
        optionRow.remove();
        optionCount--;
        updateOptionCount();
    }
}

function updateOptionCount() {
    document.getElementById('option-count').textContent = `${optionCount} / ${maxOptions} options`;
    
    const addBtn = document.getElementById('add-option-btn');
    if (optionCount >= maxOptions) {
        addBtn.style.display = 'none';
    } else {
        addBtn.style.display = 'inline-flex';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateOptionCount();
    feather.replace();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>