-- schema.sql — MySQL schema for the LMS Admin sample
CREATE DATABASE IF NOT EXISTS lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lms;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  role ENUM('admin','teacher','student') NOT NULL DEFAULT 'student',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  password_hash VARCHAR(255) NOT NULL
);

INSERT INTO users (name, email, role, status, last_login, created_at, updated_at, password_hash)
VALUES
('System Administrator', 'admin@demo.com', 'admin', 'active', NOW(), NOW(), NOW(), 
  -- password: admin123
  '$2y$10$kKxE5Bqv1mWg1O4S3mYlQe0bF0n6rH2vVQk6Q2b6n3F0qXq9Xc8n2');
