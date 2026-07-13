-- ProjectCrew Complete Database Schema & Seed Data
-- Import this file in phpMyAdmin or mysql CLI to set up the database and test users.

SET NAMES utf8mb4;
-- CREATE DATABASE IF NOT EXISTS majorco CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE majorco;

-- --------------------------------------------------------
-- 1. Schema Definitions
-- --------------------------------------------------------

-- Users Table
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  skills JSON NULL,
  avatar_url VARCHAR(255) NULL,
  bio TEXT NULL,
  security_question VARCHAR(255) NULL,
  security_answer_hash VARCHAR(255) NULL,
  last_seen DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Projects Table
CREATE TABLE IF NOT EXISTS projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  start_date DATE NULL,
  due_date DATE NULL,
  status ENUM('planning', 'in-progress', 'completed', 'on-hold') DEFAULT 'planning',
  required_skills JSON NULL,
  visibility ENUM('public', 'private') DEFAULT 'public',
  image_url VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_visibility (visibility),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Project Members Table
CREATE TABLE IF NOT EXISTS project_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  user_id INT NOT NULL,
  role VARCHAR(50) DEFAULT 'member',
  status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_project_user (project_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Project Tasks Table
CREATE TABLE IF NOT EXISTS project_tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  status ENUM('todo', 'in-progress', 'testing', 'done') DEFAULT 'todo',
  assigned_to INT NULL,
  priority VARCHAR(20) DEFAULT 'medium',
  due_date DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_project_status (project_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Project Files Table
CREATE TABLE IF NOT EXISTS project_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  user_id INT NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_size INT NOT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_file_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Connections Table
CREATE TABLE IF NOT EXISTS connections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  partner_id INT NOT NULL,
  status ENUM('pending', 'connected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_connection (user_id, partner_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (partner_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_partner_id (partner_id),
  INDEX idx_status (status),
  INDEX idx_conn_users (user_id, partner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Messages Table
CREATE TABLE IF NOT EXISTS messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sender_id INT NOT NULL,
  receiver_id INT NOT NULL,
  message TEXT NOT NULL,
  is_read BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Posts Table
CREATE TABLE IF NOT EXISTS posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  content TEXT NOT NULL,
  image_url VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Post Likes Table
CREATE TABLE IF NOT EXISTS post_likes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  user_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_like (post_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Post Comments Table
CREATE TABLE IF NOT EXISTS post_comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  user_id INT NOT NULL,
  parent_id INT NULL,
  content TEXT NOT NULL,
  is_pinned BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (parent_id) REFERENCES post_comments(id) ON DELETE CASCADE,
  INDEX idx_post_id (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Ratings Table
CREATE TABLE IF NOT EXISTS user_ratings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  rater_id INT NOT NULL,
  ratee_id INT NOT NULL,
  rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
  review TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (rater_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (ratee_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_rating (project_id, rater_id, ratee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type VARCHAR(50) NOT NULL,
  related_id INT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_un_read (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- 2. Seed Data Injection
-- --------------------------------------------------------

-- Clean existing data
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE post_comments;
TRUNCATE TABLE post_likes;
TRUNCATE TABLE posts;
TRUNCATE TABLE project_members;
TRUNCATE TABLE projects;
TRUNCATE TABLE connections;
TRUNCATE TABLE messages;
TRUNCATE TABLE notifications;
TRUNCATE TABLE user_ratings;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;

-- Users (Hashed passwords compatible with: password123, alex123, sarah123, marcus123, elena123, david123)
-- Security answers: alex123 -> buddy, sarah123 -> oxford, marcus123 -> mumbai, elena123 -> gomez, david123 -> blue
INSERT INTO users (id, first_name, last_name, email, password_hash, skills, avatar_url, bio, security_question, security_answer_hash) VALUES
(1, 'Alex', 'Chen', 'alex@test.com', '$2y$10$w.p.7eK5PZk8Z5gA.O46Fug/6F7rF02Xy4T1J4iVv9JzW4t0vN53S', '["React", "Node.js", "JavaScript"]', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200&h=200&fit=crop', 'Full-stack developer passionate about building scalable web applications.', 'What is your pet\'s name?', '$2y$10$tZ2v1w.9d22x2w/K.19zO.x51zQx2z9Z4v123x7y1z1w2v3u4t5s6'),
(2, 'Sarah', 'Jenkins', 'sarah@test.com', '$2y$10$U2.1v9WzY5Z4x7v8u9z3u.p9Xz8wY7v6u5t4s3r2q1p0o9n8m7l6k', '["UI/UX Design", "Figma", "CSS"]', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=200&h=200&fit=crop', 'UI/UX Designer focusing on clean, user-centric experiences.', 'What is the name of your first school?', '$2y$10$x7y1z1w2v3u4t5s6r7q8p9o0n1m2l3k4j5i6h7g8f9e0d1c2b3a4z'),
(3, 'Marcus', 'Johnson', 'marcus@test.com', '$2y$10$w7v6u5t4s3r2q1p0o9n8m7l6k5j4i3h2g1f0e9d8c7b6a5z4y3x2w', '["Python", "Machine Learning", "Data Analysis"]', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=200&h=200&fit=crop', 'Data Scientist by day, open-source contributor by night.', 'In which city were you born?', '$2y$10$j5i6h7g8f9e0d1c2b3a4z5y6x7w8v9u0t1s2r3q4p5o6n7m8l9k0j'),
(4, 'Elena', 'Rodriguez', 'elena@test.com', '$2y$10$z5y6x7w8v9u0t1s2r3q4p5o6n7m8l9k0j5i6h7g8f9e0d1c2b3a4', '["Digital Marketing", "SEO", "Content Strategy"]', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200&h=200&fit=crop', 'Helping brands grow their digital footprint.', 'What is your mother\'s maiden name?', '$2y$10$t1s2r3q4p5o6n7m8l9k0j5i6h7g8f9e0d1c2b3a4z5y6x7w8v9u0t'),
(5, 'David', 'Kim', 'david@test.com', '$2y$10$p5o6n7m8l9k0j5i6h7g8f9e0d1c2b3a4z5y6x7w8v9u0t1s2r3q4', '["Java", "AWS", "Spring Boot"]', 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=200&h=200&fit=crop', 'Backend engineer who loves cloud architecture and microservices.', 'What is your favorite color?', '$2y$10$f9e0d1c2b3a4z5y6x7w8v9u0t1s2r3q4p5o6n7m8l9k0j5i6h7g8');

-- Connections (Mutual connected status between all users)
INSERT INTO connections (user_id, partner_id, status) VALUES
(1, 2, 'connected'), (1, 3, 'connected'), (1, 4, 'connected'), (1, 5, 'connected'),
(2, 3, 'connected'), (2, 4, 'connected'), (2, 5, 'connected'),
(3, 4, 'connected'), (3, 5, 'connected'),
(4, 5, 'connected');

-- Posts (learning setups and work posts)
INSERT INTO posts (id, user_id, content) VALUES
(1, 1, 'Diving deep into Next.js App Router and Server Actions today! Extremely clean way to handle mutation endpoints. 🚀'),
(2, 1, 'Just upgraded to a dual 27-inch 4K monitor setup. The screen real estate makes multitasking a breeze! 🖥️✨'),
(3, 2, 'Exploring CSS grid layouts and micro-animations in Figma. Micro-interactions can make or break a user interface! 🎨'),
(4, 2, 'My minimalist desk setup with warm white ambient lighting. Keeping the workspace clean keeps the mind clear. 🕯️💻'),
(5, 3, 'Studying transformer models and training parameters on TensorFlow today. Hyperparameter tuning is an art form! 🧠📈'),
(6, 3, 'Loving my Linux workstation setup. Optimized for heavy calculations and data pipeline tasks.'),
(7, 4, 'Analyzing SEO keyword trends and organic growth metrics. Consistency beats intensity when it comes to organic SEO rank! 📊'),
(8, 4, 'Working from a cozy coffee shop setup today. A change of scenery always boosts creative brainstorming!'),
(9, 5, 'Migrating monolith microservices to AWS. Seeing deployment build times drop from 30 minutes to 3 minutes is amazing! ☁️'),
(10, 5, 'Mechanical keyboard built! Custom typing sounds make compiling code feel extremely therapeutic. ⌨️🎵');

-- Likes (Everyone likes everyone else's posts)
INSERT INTO post_likes (post_id, user_id) VALUES
(1, 2), (1, 3), (1, 4), (1, 5),
(2, 2), (2, 3), (2, 4), (2, 5),
(3, 1), (3, 3), (3, 4), (3, 5),
(4, 1), (4, 3), (4, 4), (4, 5),
(5, 1), (5, 2), (5, 4), (5, 5),
(6, 1), (6, 2), (6, 4), (6, 5),
(7, 1), (7, 2), (7, 3), (7, 5),
(8, 1), (8, 2), (8, 3), (8, 5),
(9, 1), (9, 2), (9, 3), (9, 4),
(10, 1), (10, 2), (10, 3), (10, 4);
