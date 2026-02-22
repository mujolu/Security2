-- Create image_posts table to store uploaded images with user and post metadata
CREATE TABLE IF NOT EXISTS image_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(9) NOT NULL,
    filename VARCHAR(255) NOT NULL UNIQUE,
    title VARCHAR(255),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (created_at)
);
