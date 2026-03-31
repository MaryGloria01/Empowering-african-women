-- EAW Platform — MySQL Database Schema
-- Run this once in Hostinger phpMyAdmin on database: u532384244_EAW_2026

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ── Users ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name    VARCHAR(80)  NOT NULL,
    last_name     VARCHAR(80)  NOT NULL,
    email         VARCHAR(191) NOT NULL UNIQUE,
    phone         VARCHAR(40)  DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('student','tutor','tutor-pending','admin') NOT NULL DEFAULT 'student',
    is_verified   TINYINT(1)   NOT NULL DEFAULT 0,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Enrollments ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS enrollments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    course_slug VARCHAR(80)  NOT NULL,
    enrolled_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_enrollment (user_id, course_slug),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Lesson Progress ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS progress (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    course_slug  VARCHAR(80)  NOT NULL,
    lesson_id    VARCHAR(80)  NOT NULL,
    completed_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_progress (user_id, course_slug, lesson_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Certificates ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS certificates (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    course_slug  VARCHAR(80)  NOT NULL,
    issued_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cert_code    VARCHAR(64)  NOT NULL UNIQUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tutor Applications ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tutor_applications (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED DEFAULT NULL,
    first_name   VARCHAR(80)  NOT NULL,
    last_name    VARCHAR(80)  NOT NULL,
    email        VARCHAR(191) NOT NULL,
    phone        VARCHAR(40)  DEFAULT NULL,
    expertise    VARCHAR(255) DEFAULT NULL,
    bio          TEXT         DEFAULT NULL,
    status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    submitted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Settings (key/value store) ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS settings (
    key_name  VARCHAR(80)  NOT NULL PRIMARY KEY,
    value     TEXT         NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin email (password is set via login.php using env default)
INSERT IGNORE INTO settings (key_name, value)
VALUES ('admin_email', 'admin@empoweringafricanwomen.com');

-- ── Referral columns (run if table already exists) ──────────────────────────
ALTER TABLE users ADD COLUMN IF NOT EXISTS referral_code VARCHAR(16) NULL UNIQUE AFTER role;
ALTER TABLE users ADD COLUMN IF NOT EXISTS referred_by VARCHAR(16) NULL AFTER referral_code;

-- ── Admin Audit Log ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admin_audit_log (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action       VARCHAR(80)  NOT NULL,
    detail       TEXT         DEFAULT NULL,
    performed_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_performed_at (performed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Update admin email to match portal credential
INSERT INTO settings (key_name, value) VALUES ('admin_email', 'ogechi@eaw.admin')
ON DUPLICATE KEY UPDATE value = 'ogechi@eaw.admin';

-- ── Certificate metadata columns (run if table already exists) ─────────────
ALTER TABLE certificates ADD COLUMN IF NOT EXISTS title VARCHAR(255) DEFAULT NULL AFTER course_slug;
ALTER TABLE certificates ADD COLUMN IF NOT EXISTS score VARCHAR(20)  DEFAULT NULL AFTER title;
