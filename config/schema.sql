-- ================================================================
-- SKEMA DATABASE APLIKASI VOTING - VOTESPHERE
-- ================================================================
-- 
-- File: schema.sql
-- Fungsi: Membuat struktur database untuk aplikasi voting
-- 
-- TABEL UTAMA:
-- - users: Data pengguna aplikasi
-- - polls: Data polling/survey
-- - poll_options: Pilihan-pilihan dalam setiap poll
-- - votes: Record suara yang diberikan user
-- - comments: Komentar pada polling
-- 
-- FITUR DATABASE:
-- - Foreign key constraints untuk integritas data
-- - Index untuk optimasi performa query
-- - UTF-8 untuk mendukung karakter internasional
-- - Cascade delete untuk konsistensi data
-- 
-- AUTHOR: VoteSphere Team
-- ================================================================

-- ================================================================
-- TABEL USERS - DATA PENGGUNA
-- ================================================================
-- Menyimpan informasi akun pengguna aplikasi

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,        -- ID unik pengguna
    username VARCHAR(50) NOT NULL UNIQUE,          -- Username untuk login (unik)
    email VARCHAR(100) NOT NULL UNIQUE,            -- Email pengguna (unik)
    password_hash VARCHAR(255) NOT NULL,           -- Password yang sudah di-hash
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,      -- Waktu pendaftaran
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- Waktu update terakhir
    
    -- Index untuk optimasi query login
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- TABEL POLLS - DATA POLLING
-- ================================================================
-- Menyimpan informasi polling yang dibuat pengguna

CREATE TABLE polls (
    poll_id INT AUTO_INCREMENT PRIMARY KEY,         -- ID unik polling
    creator_id INT NOT NULL,                        -- ID pembuat polling
    title VARCHAR(255) NOT NULL,                    -- Judul polling
    description TEXT,                               -- Deskripsi polling (opsional)
    end_time TIMESTAMP NULL,                        -- Waktu berakhir polling (NULL = tidak ada batas)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Waktu pembuatan
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- Waktu update
    
    -- Relasi ke tabel users
    FOREIGN KEY (creator_id) REFERENCES users(user_id) ON DELETE CASCADE,
    
    -- Index untuk optimasi query
    INDEX idx_creator (creator_id),                 -- Cari polling berdasarkan pembuat
    INDEX idx_end_time (end_time),                  -- Cari polling berdasarkan waktu berakhir
    INDEX idx_created_at (created_at),              -- Cari polling berdasarkan tanggal dibuat
    INDEX idx_active_polls (end_time, created_at)   -- Composite index untuk polling aktif
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- TABEL POLL_OPTIONS - PILIHAN DALAM POLLING
-- ================================================================
-- Menyimpan opsi/pilihan yang tersedia dalam setiap polling

CREATE TABLE poll_options (
    option_id INT AUTO_INCREMENT PRIMARY KEY,       -- ID unik pilihan
    poll_id INT NOT NULL,                           -- ID polling terkait
    option_text VARCHAR(255) NOT NULL,              -- Teks pilihan
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Waktu pembuatan
    
    -- Relasi ke tabel polls (cascade delete jika poll dihapus)
    FOREIGN KEY (poll_id) REFERENCES polls(poll_id) ON DELETE CASCADE,
    
    -- Index untuk optimasi query pilihan berdasarkan poll
    INDEX idx_poll_id (poll_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- TABEL VOTES - RECORD SUARA PENGGUNA
-- ================================================================
-- Menyimpan suara yang diberikan pengguna pada setiap pilihan

CREATE TABLE votes (
    vote_id INT AUTO_INCREMENT PRIMARY KEY,         -- ID unik vote
    user_id INT NOT NULL,                           -- ID pengguna yang memberikan suara
    option_id INT NOT NULL,                         -- ID pilihan yang dipilih
    voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,   -- Waktu memberikan suara
    
    -- Relasi ke tabel lain (cascade delete)
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (option_id) REFERENCES poll_options(option_id) ON DELETE CASCADE,
    
    -- Constraint: satu user hanya bisa vote satu kali per pilihan
    -- (dalam satu poll, user hanya bisa memilih satu opsi)
    UNIQUE KEY unique_user_option (user_id, option_id),
    
    -- Index untuk optimasi query
    INDEX idx_user_id (user_id),                    -- Cari vote berdasarkan user
    INDEX idx_option_id (option_id),                -- Cari vote berdasarkan pilihan
    INDEX idx_voted_at (voted_at)                   -- Cari vote berdasarkan waktu
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- TABEL COMMENTS - KOMENTAR PADA POLLING
-- ================================================================
-- Menyimpan komentar yang diberikan pengguna pada polling

CREATE TABLE comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,      -- ID unik komentar
    poll_id INT NOT NULL,                           -- ID polling terkait
    user_id INT NOT NULL,                           -- ID pengguna yang berkomentar
    comment_text TEXT NOT NULL,                     -- Isi komentar
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Waktu pembuatan komentar
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- Waktu update
    
    -- Relasi ke tabel lain (cascade delete)
    FOREIGN KEY (poll_id) REFERENCES polls(poll_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    
    -- Index untuk optimasi query
    INDEX idx_poll_id (poll_id),                    -- Cari komentar berdasarkan poll
    INDEX idx_user_id (user_id),                    -- Cari komentar berdasarkan user
    INDEX idx_created_at (created_at)               -- Cari komentar berdasarkan waktu
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- CATATAN IMPLEMENTASI
-- ================================================================
-- 
-- 1. ENGINE=InnoDB: Mendukung foreign key constraints dan transactions
-- 2. utf8mb4_unicode_ci: Mendukung emoji dan karakter Unicode lengkap
-- 3. CASCADE DELETE: Otomatis hapus data terkait jika parent dihapus
-- 4. INDEX: Mempercepat query yang sering digunakan
-- 5. TIMESTAMP: Menggunakan timezone database untuk konsistensi
-- ================================================================
