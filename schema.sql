-- Database schema for SkillPath Builder

-- Users table
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'instructor', 'student') NOT NULL DEFAULT 'student',
  `status` ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
  `first_login` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `email_idx` (`email`),
  INDEX `role_idx` (`role`)
) ENGINE=InnoDB;

-- Roadmaps table
CREATE TABLE `roadmaps` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `instructor_id` INT NOT NULL,
  `price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `status` ENUM('pending', 'approved', 'rejected', 'changed') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`instructor_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `status_idx` (`status`)
) ENGINE=InnoDB;

-- Roadmap Phases table
CREATE TABLE `roadmap_phases` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `roadmap_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `phase_order` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`roadmap_id`) REFERENCES `roadmaps`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Roadmap Videos table
CREATE TABLE `roadmap_videos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `phase_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `video_url` VARCHAR(255) NOT NULL,
  `video_order` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`phase_id`) REFERENCES `roadmap_phases`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Enrollments table
CREATE TABLE `enrollments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `roadmap_id` INT NOT NULL,
  `enrollment_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`roadmap_id`) REFERENCES `roadmaps`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `student_roadmap_unique` (`student_id`, `roadmap_id`)
) ENGINE=InnoDB;

-- Payments table (mock)
CREATE TABLE `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `roadmap_id` INT NOT NULL,
  `amount` DECIMAL(10, 2) NOT NULL,
  `payment_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `transaction_id` VARCHAR(255) NOT NULL UNIQUE,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`roadmap_id`) REFERENCES `roadmaps`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Progress table
CREATE TABLE `progress` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `video_id` INT NOT NULL,
  `completed` BOOLEAN NOT NULL DEFAULT FALSE,
  `completed_at` TIMESTAMP NULL,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`video_id`) REFERENCES `roadmap_videos`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `student_video_unique` (`student_id`, `video_id`)
) ENGINE=InnoDB;

-- Quiz Attempts table
CREATE TABLE `quiz_attempts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `phase_id` INT NOT NULL,
  `score` DECIMAL(5, 2) NOT NULL,
  `passed` BOOLEAN NOT NULL,
  `attempt_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`phase_id`) REFERENCES `roadmap_phases`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Certificates table
CREATE TABLE `certificates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `roadmap_id` INT NOT NULL,
  `issue_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `certificate_url` VARCHAR(255) NOT NULL,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`roadmap_id`) REFERENCES `roadmaps`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Feedback table
CREATE TABLE `feedback` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `roadmap_id` INT NOT NULL,
  `rating` TINYINT NOT NULL CHECK (`rating` >= 1 AND `rating` <= 5),
  `comment` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`roadmap_id`) REFERENCES `roadmaps`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
