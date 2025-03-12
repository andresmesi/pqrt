-- db.sql
-- This SQL script sets up the database schema for the application.

-- Set SQL mode and time zone settings.
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Create the "categories" table.
CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create the "posts" table.
CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `thread_id` int(11) DEFAULT 0,
  `category_id` int(11) DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `last_bump` datetime DEFAULT current_timestamp(),
  `name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Anonymous',
  `mail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add primary keys and indexes to the "categories" table.
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_name` (`name`);

-- Add primary keys and indexes to the "posts" table.
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_comment` (`comment`(255)),
  ADD KEY `idx_thread_id` (`thread_id`),
  ADD KEY `idx_mail` (`mail`),
  ADD KEY `idx_last_bump` (`last_bump`);

-- Modify the "id" columns to auto-increment.
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- Add a foreign key constraint linking posts to categories.
ALTER TABLE `posts`
  ADD CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

COMMIT;