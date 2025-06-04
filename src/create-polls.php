<?php

/**
 * ================================================================
 * HALAMAN BUAT POLLING - FORM PEMBUATAN POLL BARU
 * ================================================================
 * 
 * File: create-polls.php
 * Fungsi: Halaman untuk membuat polling baru dengan opsi-opsi pilihan
 * 
 * FITUR UTAMA:
 * - Form pembuatan poll dengan validasi
 * - Dynamic form untuk menambah/hapus opsi
 * - Optional end time untuk poll
 * - Preview poll sebelum submit
 * - Validation comprehensive untuk semua input
 * 
 * KEAMANAN:
 * - Authentication required (harus login)
 * - Input validation dan sanitization
 * - XSS protection dengan htmlspecialchars
 * - Database transaction untuk konsistensi
 * 
 * AUTHOR: VoteSphere Team
 * VERSION: 1.0
 * ================================================================
 */

// ================================================================
// SETUP AWAL & KONFIGURASI
// ================================================================
require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../config/config.php';

// Mulai session jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// KONTROL AKSES - WAJIB LOGIN
// ================================================================
// Halaman ini hanya bisa diakses oleh user yang sudah login
Auth::requireLogin();

// Ambil ID user yang sedang login
$userId = Auth::id();

// ================================================================
// INISIALISASI VARIABEL FORM
// ================================================================
$errors = [];                   // Array untuk menyimpan error messages
$title_val = '';               // Nilai title untuk repopulate form
$description_val = '';         // Nilai description untuk repopulate form
$end_time_val = '';           // Nilai end_time untuk repopulate form
$options_val = ['', '', ''];  // Default 3 opsi kosong (2 required + 1 extra)

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
    $options = $_POST['options'] ?? [];

    // Simpan nilai untuk repopulate form jika ada error
    $title_val = $title;
    $description_val = $description;
    $end_time_val = $end_time;
    $options_val = $options;

    // ================================================================
    // VALIDASI INPUT - TITLE
    // ================================================================
    if (empty($title)) {
        $errors[] = 'Poll title is required.';
    } elseif (strlen($title) > 255) {
        $errors[] = 'Poll title is too long (max 255 characters).';
    } elseif (strlen($title) < 5) {
        $errors[] = 'Poll title must be at least 5 characters long.';
    }

    // ================================================================
    // VALIDASI INPUT - DESCRIPTION (OPTIONAL)
    // ================================================================
    if (!empty($description) && strlen($description) > 1000) {
        $errors[] = 'Description is too long (max 1000 characters).';
    }

    // ================================================================
    // VALIDASI INPUT - END TIME (OPTIONAL)
    // ================================================================
    $parsed_end_time = null;
    if (!empty($end_time)) {
        try {
            $parsed_end_time = new DateTime($end_time);
            $now = new DateTime();

            // End time harus di masa depan
            if ($parsed_end_time <= $now) {
                $errors[] = 'End time must be in the future.';
            }

            // End time tidak boleh terlalu jauh (misal maksimal 1 tahun)
            $max_end_time = clone $now;
            $max_end_time->add(new DateInterval('P1Y')); // Tambah 1 tahun

            if ($parsed_end_time > $max_end_time) {
                $errors[] = 'End time cannot be more than 1 year from now.';
            }
        } catch (Exception $e) {
            $errors[] = 'Invalid end time format.';
        }
    }

    // ================================================================
    // VALIDASI INPUT - OPTIONS
    // ================================================================
    // Filter opsi yang tidak kosong dan sanitasi
    $valid_options = [];
    foreach ($options as $option) {
        $option = trim($option);
        if (!empty($option)) {
            if (strlen($option) > 255) {
                $errors[] = 'Option text is too long (max 255 characters): ' . htmlspecialchars(substr($option, 0, 50)) . '...';
            } else {
                $valid_options[] = $option;
            }
        }
    }

    // Cek jumlah opsi minimal
    if (count($valid_options) < 2) {
        $errors[] = 'Poll must have at least 2 options.';
    }

    // Cek jumlah opsi maksimal (untuk UX dan performa)
    if (count($valid_options) > 20) {
        $errors[] = 'Poll cannot have more than 20 options.';
    }

    // Cek duplikasi opsi
    if (count($valid_options) !== count(array_unique($valid_options))) {
        $errors[] = 'Duplicate options are not allowed.';
    }

    // ================================================================
    // PROSES PENYIMPANAN JIKA TIDAK ADA ERROR
    // ================================================================
    if (empty($errors)) {
        try {
            $pdo = Database::getInstance();

            // ================================================================
            // DATABASE TRANSACTION - UNTUK KONSISTENSI DATA
            // ================================================================
            $pdo->beginTransaction();

            // ================================================================
            // INSERT DATA POLL KE DATABASE
            // ================================================================
            $stmt = $pdo->prepare("
                INSERT INTO polls (creator_id, title, description, end_time, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");

            // Convert end_time ke format database jika ada
            $db_end_time = $parsed_end_time ? $parsed_end_time->format('Y-m-d H:i:s') : null;

            $stmt->execute([
                $userId,
                $title,
                $description ?: null, // NULL jika description kosong
                $db_end_time
            ]);

            // Ambil ID poll yang baru dibuat
            $pollId = $pdo->lastInsertId();

            // ================================================================
            // INSERT OPSI-OPSI POLL KE DATABASE
            // ================================================================
            $stmtOption = $pdo->prepare("
                INSERT INTO poll_options (poll_id, option_text, created_at) 
                VALUES (?, ?, NOW())
            ");

            foreach ($valid_options as $option) {
                $stmtOption->execute([$pollId, $option]);
            }

            // ================================================================
            // COMMIT TRANSACTION
            // ================================================================
            $pdo->commit();

            // ================================================================
            // REDIRECT KE HALAMAN POLL YANG BARU DIBUAT
            // ================================================================
            $_SESSION['toast'] = [
                'message' => 'Poll created successfully!',
                'type' => 'success'
            ];

            header('Location: ' . APP_URL . '/src/vote.php?poll_id=' . $pollId);
            exit;
        } catch (Exception $e) {
            // ================================================================
            // ERROR HANDLING - ROLLBACK TRANSACTION
            // ================================================================
            $pdo->rollback();
            error_log('Poll creation error: ' . $e->getMessage());
            $errors[] = 'Error creating poll. Please try again.';
        }
    }
}

// ================================================================
// SETUP UNTUK TEMPLATE HTML
// ================================================================
$pageTitle = 'Create New Poll';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ================================================================ -->
<!-- TEMPLATE HTML - FORM PEMBUATAN POLL -->
<!-- ================================================================ -->
<div class="max-w-4xl mx-auto">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8">

        <!-- ================================================================ -->
        <!-- HEADER SECTION -->
        <!-- ================================================================ -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                Create New Poll
            </h1>
            <p class="text-gray-600 dark:text-gray-400">
                Create a new poll and let people vote on your question.
            </p>
        </div>

        <!-- ================================================================ -->
        <!-- ERROR MESSAGES DISPLAY -->
        <!-- ================================================================ -->
        <?php if (!empty($errors)): ?>
            <div class="mb-6 p-4 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-600 rounded-lg">
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
        <!-- MAIN FORM - HAPUS x-data="pollForm()" -->
        <!-- ================================================================ -->
        <form method="POST" action="" class="space-y-6">

            <!-- ============================================================ -->
            <!-- POLL TITLE FIELD -->
            <!-- ============================================================ -->
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Poll Title <span class="text-red-500">*</span>
                </label>
                <input type="text"
                    id="title"
                    name="title"
                    required
                    maxlength="255"
                    value="<?= htmlspecialchars($title_val) ?>"
                    class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400"
                    placeholder="What would you like to ask?">
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Enter a clear and concise question for your poll (5-255 characters)
                </p>
            </div>

            <!-- ============================================================ -->
            <!-- POLL DESCRIPTION FIELD (OPTIONAL) -->
            <!-- ============================================================ -->
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Description (Optional)
                </label>
                <textarea id="description"
                    name="description"
                    rows="3"
                    maxlength="1000"
                    class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400"
                    placeholder="Provide additional context for your poll..."><?= htmlspecialchars($description_val) ?></textarea>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Optional description to provide more context (max 1000 characters)
                </p>
            </div>

            <!-- ============================================================ -->
            <!-- END TIME FIELD (OPTIONAL) -->
            <!-- ============================================================ -->
            <div>
                <label for="end_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    End Time (Optional)
                </label>
                <input type="datetime-local"
                    id="end_time"
                    name="end_time"
                    value="<?= htmlspecialchars($end_time_val) ?>"
                    class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Leave empty for a poll that never expires. Max 1 year from now.
                </p>
            </div>

            <!-- ============================================================ -->
            <!-- POLL OPTIONS SECTION - PERBAIKAN -->
            <!-- ============================================================ -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Poll Options <span class="text-red-500">*</span>
                </label>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    Add the choices people can vote for (minimum 2, maximum 20)
                </p>

                <!-- Dynamic Options Container - PERBAIKAN -->
                <div id="options-container" class="space-y-3">
                    <!-- Static Options yang selalu muncul -->
                    <?php
                    // Pastikan minimal ada 2 opsi untuk ditampilkan
                    if (count($options_val) < 2) {
                        $options_val = ['', '', '']; // Default 3 opsi kosong
                    }
                    ?>

                    <?php foreach ($options_val as $index => $option_value): ?>
                        <div class="option-row flex items-center space-x-3" data-option-index="<?= $index ?>">
                            <div class="flex-1">
                                <input type="text"
                                    name="options[<?= $index ?>]"
                                    value="<?= htmlspecialchars($option_value) ?>"
                                    maxlength="255"
                                    class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400"
                                    placeholder="Option <?= $index + 1 ?> (e.g., Candidate <?= chr(65 + $index) ?>, Choice <?= $index + 1 ?>)"
                                    <?= $index < 2 ? 'required' : '' ?>>
                                <!-- Tambah indicator required untuk 2 opsi pertama -->
                                <?php if ($index < 2): ?>
                                    <div class="text-xs text-gray-500 mt-1">Required</div>
                                <?php endif; ?>
                            </div>

                            <!-- Remove Option Button -->
                            <button type="button"
                                onclick="removeOption(this)"
                                class="remove-option-btn p-2 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 focus:outline-none <?= $index < 2 ? 'invisible' : '' ?>"
                                title="Remove option">
                                <i data-feather="trash-2" class="w-5 h-5"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Add Option Button & Counter - PERBAIKAN WARNA -->
                <div class="flex justify-between items-center pt-3">
                    <button type="button"
                        id="add-option-btn"
                        onclick="addOption()"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200 shadow-sm hover:shadow-md">
                        <i data-feather="plus" class="w-4 h-4 mr-2"></i>
                        Add Option
                    </button>

                    <span id="option-counter" class="text-sm text-gray-500 dark:text-gray-400">
                        <?= count($options_val) ?> / 20 options
                    </span>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- FORM ACTIONS -->
            <!-- ============================================================ -->
            <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-gray-200 dark:border-gray-700">
                <!-- Submit Button -->
                <button type="submit"
                    class="flex-1 sm:flex-none inline-flex justify-center items-center px-6 py-3 border border-transparent rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 font-medium transition-all duration-200">
                    <i data-feather="check" class="w-5 h-5 mr-2"></i>
                    Create Poll
                </button>

                <!-- Cancel Button -->
                <a href="<?= APP_URL ?>/public/index.php"
                    class="flex-1 sm:flex-none inline-flex justify-center items-center px-6 py-3 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 dark:focus:ring-offset-gray-800 font-medium transition-all duration-200">
                    <i data-feather="x" class="w-5 h-5 mr-2"></i>
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT UNTUK DYNAMIC OPTIONS -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // GLOBAL VARIABLES
    // ================================================================
    let optionIndex = Math.max(<?= count($options_val) ?>, 3); // Minimal 3 untuk consistency
    const MAX_OPTIONS = 20;
    const MIN_OPTIONS = 2;

    // ================================================================
    // ADD NEW OPTION FUNCTION
    // ================================================================
    function addOption() {
        if (optionIndex >= MAX_OPTIONS) {
            // Gunakan toast notification instead of alert
            showToast('Maximum 20 options allowed', 'warning');
            return;
        }

        const container = document.getElementById('options-container');
        const newRow = document.createElement('div');
        newRow.className = 'option-row flex items-center space-x-3';
        newRow.setAttribute('data-option-index', optionIndex);

        newRow.innerHTML = `
        <div class="flex-1">
            <input type="text"
                name="options[${optionIndex}]"
                value=""
                maxlength="255"
                class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400"
                placeholder="Option ${optionIndex + 1} (e.g., Candidate ${String.fromCharCode(65 + optionIndex)}, Option ${optionIndex + 1}, etc.)"
                required>
        </div>
        <button type="button"
            onclick="removeOption(this)"
            class="remove-option-btn p-2 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 focus:outline-none"
            title="Remove option">
            <i data-feather="trash-2" class="w-5 h-5"></i>
        </button>
    `;

        container.appendChild(newRow);
        optionIndex++;

        updateUI();

        // Re-initialize Feather icons
        feather.replace();

        // Focus pada input yang baru ditambahkan
        newRow.querySelector('input').focus();
    }

    // ================================================================
    // REMOVE OPTION FUNCTION
    // ================================================================
    function removeOption(button) {
        const currentCount = document.querySelectorAll('.option-row').length;

        if (currentCount <= MIN_OPTIONS) {
            showToast('Minimum 2 options required', 'warning');
            return;
        }

        const row = button.closest('.option-row');
        row.remove();

        updateUI();
    }

    // ================================================================
    // UPDATE UI BASED ON CURRENT STATE
    // ================================================================
    function updateUI() {
        const rows = document.querySelectorAll('.option-row');
        const currentCount = rows.length;

        // Update counter
        document.getElementById('option-counter').textContent = `${currentCount} / ${MAX_OPTIONS} options`;

        // Update Add button state
        const addBtn = document.getElementById('add-option-btn');
        if (currentCount >= MAX_OPTIONS) {
            addBtn.style.display = 'none';
        } else {
            addBtn.style.display = 'inline-flex';
        }

        // Update remove buttons visibility
        rows.forEach((row, index) => {
            const removeBtn = row.querySelector('.remove-option-btn');
            if (index < MIN_OPTIONS) {
                removeBtn.classList.add('invisible');
            } else {
                removeBtn.classList.remove('invisible');
            }
        });

        // Re-index name attributes untuk memastikan consistency
        rows.forEach((row, index) => {
            const input = row.querySelector('input');
            input.name = `options[${index}]`;
            input.placeholder = `Option ${index + 1} (e.g., Candidate ${String.fromCharCode(65 + index)}, Option ${index + 1}, etc.)`;
        });
    }

    // ================================================================
    // INITIALIZE ON PAGE LOAD
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        updateUI();
        feather.replace();

        // Real-time validation untuk title
        const titleInput = document.getElementById('title');
        if (titleInput) {
            titleInput.addEventListener('input', function() {
                const length = this.value.length;
                const counter = document.getElementById('title-counter');
                if (!counter) {
                    // Create counter if not exists
                    const counterEl = document.createElement('div');
                    counterEl.id = 'title-counter';
                    counterEl.className = 'text-xs text-gray-500 mt-1';
                    this.parentNode.appendChild(counterEl);
                }
                document.getElementById('title-counter').textContent =
                    `${length}/255 characters`;
            });
        }
    });

    // Helper function untuk toast notifications
    function showToast(message, type = 'info') {
        // Implementasi toast notification
        // Atau gunakan alert sebagai fallback
        alert(message);
    }
</script>

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
1. Authentication required untuk akses halaman
2. Comprehensive input validation
3. XSS protection dengan htmlspecialchars
4. Database transaction untuk data consistency
5. Error handling yang proper

FITUR UX:
1. Dynamic form untuk add/remove options
2. Real-time validation feedback
3. Form repopulation jika ada error
4. Responsive design untuk mobile
5. Clear visual feedback dan instructions

VALIDASI BUSINESS RULES:
1. Title: required, 5-255 karakter
2. Description: optional, max 1000 karakter
3. End time: optional, harus masa depan, max 1 tahun
4. Options: minimum 2, maksimum 20, tidak boleh duplikat
5. Setiap option: max 255 karakter

OPTIMASI PERFORMA:
1. Database transaction untuk konsistensi
2. Efficient Alpine.js component
3. Minimal DOM manipulation
4. Proper error handling
5. Input sanitization yang tepat
-->