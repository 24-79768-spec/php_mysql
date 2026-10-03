-- Movie / Series Watchlist database
-- Import with phpMyAdmin (Import tab) or: mysql -u root -p < app_db.sql

CREATE DATABASE IF NOT EXISTS watchlist_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE watchlist_db;

DROP TABLE IF EXISTS movies;

CREATE TABLE movies (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title     VARCHAR(150) NOT NULL,
  genre     VARCHAR(50)  NOT NULL,
  rating    TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = not watched yet, 1-10 = watched and rated',
  image_url VARCHAR(255) NULL DEFAULT NULL COMMENT 'Movie poster image URL',
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO movies (title, genre, rating, image_url) VALUES
  ('Inception',              'Sci-Fi',    9,  'https://images.unsplash.com/photo-1517604931442-7e0c8ed2963c?auto=format&fit=crop&w=900&q=80'),
  ('Parasite',               'Thriller',  NULL, 'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=900&q=80'),
  ('Spirited Away',          'Animation', NULL, 'https://images.unsplash.com/photo-1517479149777-5f3b1511d5ad?auto=format&fit=crop&w=900&q=80'),
  ('Breaking Bad',           'Drama',     10, 'https://images.unsplash.com/photo-1504384308090-c894fdcc538d?auto=format&fit=crop&w=900&q=80'),
  ('The Grand Budapest Hotel','Comedy',   NULL, 'https://images.unsplash.com/photo-1516280440614-37939bbacd81?auto=format&fit=crop&w=900&q=80');
