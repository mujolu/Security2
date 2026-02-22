-- Flag Resolution System - SQL Schema Reference
-- This document outlines all database tables used in the enhanced flagging system

-- ============================================
-- EXISTING TABLES (MODIFIED)
-- ============================================

-- Posts table (enhanced with flag tracking)
-- NOTE: Run ALTER TABLE statements only if columns don't exist
ALTER TABLE posts ADD COLUMN like_count INT DEFAULT 0 AFTER file_path;
ALTER TABLE posts ADD COLUMN report_count INT DEFAULT 0 AFTER like_count;
ALTER TABLE posts ADD COLUMN reported_status ENUM('none', 'flagged', 'resolved', 'removed') DEFAULT 'none' AFTER report_count;
ALTER TABLE posts ADD INDEX idx_reported_status (reported_status);

-- Example full structure:
/*
CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    file_path VARCHAR(255) NULL,
    like_count INT DEFAULT 0,
    report_count INT DEFAULT 0,
    reported_status ENUM('none', 'flagged', 'resolved', 'removed') DEFAULT 'none',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_posts (user_id),
    INDEX idx_reported_status (reported_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/

-- ============================================
-- NEW TABLES CREATED
-- ============================================

-- Table: post_likes (created in user_artwork.php)
-- Tracks which artists liked which posts (one-to-one per artist)
CREATE TABLE IF NOT EXISTS post_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_post_like (post_id, user_id),
    INDEX idx_post_like_post (post_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Table: post_reports (created in user_artwork.php)
-- Tracks all reports/flags on posts with reason
CREATE TABLE IF NOT EXISTS post_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    reporter_id INT NOT NULL,
    reason VARCHAR(255) NULL DEFAULT 'Unspecified',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_post_report (post_id, reporter_id),
    INDEX idx_post_report_post (post_id),
    INDEX idx_post_report_reporter (reporter_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Table: flag_resolutions (created in user_artwork.php & moderator_flag_review.php)
-- Tracks moderator actions on flagged posts with full audit trail
CREATE TABLE IF NOT EXISTS flag_resolutions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    moderator_id INT NOT NULL,
    action_type ENUM('approved', 'removed', 'warned') DEFAULT 'approved',
    resolution_notes TEXT NULL,
    resolved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_post_resolution (post_id),
    INDEX idx_moderator_resolution (moderator_id),
    INDEX idx_resolution_date (resolved_at),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================
-- ACTIVITY LOGGING TABLES
-- ============================================

-- Table: moderator_activity_logs
-- Automatic recording of all moderator page views and actions
CREATE TABLE IF NOT EXISTS moderator_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    moderator_id INT NOT NULL,
    activity VARCHAR(500) NOT NULL,
    ip_address VARCHAR(15) NULL,
    device VARCHAR(50) NULL,
    os VARCHAR(50) NULL,
    time_in TIMESTAMP NULL,
    time_out TIMESTAMP NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_moderator_id (moderator_id),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Table: admin_activity_logs
-- Automatic recording of all admin page views and actions
CREATE TABLE IF NOT EXISTS admin_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    activity VARCHAR(500) NOT NULL,
    ip_address VARCHAR(15) NULL,
    device VARCHAR(50) NULL,
    os VARCHAR(50) NULL,
    time_in TIMESTAMP NULL,
    time_out TIMESTAMP NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_id (admin_id),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================
-- USEFUL QUERIES
-- ============================================

-- Count pending flags
-- SELECT COUNT(*) as pending_flags FROM posts WHERE reported_status = 'flagged';

-- Get all flagged posts with report details
/*
SELECT 
    p.id, p.user_id, p.title, p.report_count, p.reported_status,
    GROUP_CONCAT(DISTINCT pr.reason SEPARATOR ', ') as reasons,
    COUNT(DISTINCT pr.reporter_id) as reporter_count
FROM posts p
LEFT JOIN post_reports pr ON p.id = pr.post_id
WHERE p.reported_status = 'flagged'
GROUP BY p.id
ORDER BY p.report_count DESC;
*/

-- Get moderator performance stats
/*
SELECT 
    fr.moderator_id,
    COUNT(*) as total_resolutions,
    SUM(CASE WHEN fr.action_type = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN fr.action_type = 'removed' THEN 1 ELSE 0 END) as removed,
    SUM(CASE WHEN fr.action_type = 'warned' THEN 1 ELSE 0 END) as warned
FROM flag_resolutions fr
GROUP BY fr.moderator_id
ORDER BY COUNT(*) DESC;
*/

-- Get specific post's flag history
/*
SELECT 
    fr.id, fr.action_type, fr.resolution_notes, fr.resolved_at,
    u.username as moderator_name
FROM flag_resolutions fr
LEFT JOIN user u ON fr.moderator_id = u.id
WHERE fr.post_id = ?
ORDER BY fr.resolved_at DESC;
*/

-- Get all moderator actions within date range
/*
SELECT 
    ma.activity, ma.timestamp, ma.ip_address, ma.device, ma.os
FROM moderator_activity_logs ma
WHERE ma.moderator_id = ? 
AND ma.timestamp BETWEEN ? AND ?
ORDER BY ma.timestamp DESC;
*/

-- ============================================
-- DATA RELATIONSHIPS
-- ============================================

-- A post can have:
-- - Multiple likes (post_likes: one per artist user)
-- - Multiple reports (post_reports: one per artist user)
-- - Multiple resolution actions (flag_resolutions: multiple moderators can review same post)

-- A moderator:
-- - Creates many flag_resolutions (one or more per flagged post)
-- - Has many moderator_activity_logs entries (one per action/page view)

-- Status progression for a flagged post:
-- none (initial) → flagged (when 1+ reports added)
--    ↓
-- resolved (if moderator approves or warns)
-- removed (if moderator removes content)
--    ↓
-- (can be re-flagged if new reports added after resolution)

-- ============================================
-- NOTES
-- ============================================

-- 1. All tables auto-create on first use in PHP files
-- 2. post_likes and post_reports use UNIQUE constraints to prevent duplicates
-- 3. flag_resolutions tracks every moderator decision for audit trail
-- 4. Activity logs are automatically populated via logPageView() function
-- 5. All timestamps use CURRENT_TIMESTAMP for consistency
-- 6. Indexes added for frequently queried columns (reported_status, moderator_id, timestamp)
-- 7. Foreign keys added where appropriate for referential integrity
