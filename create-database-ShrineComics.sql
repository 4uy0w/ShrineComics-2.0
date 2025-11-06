DROP DATABASE IF EXISTS ShrineComics;

CREATE DATABASE IF NOT EXISTS ShrineComics;

USE ShrineComics;

CREATE TABLE IF NOT EXISTS users(
	user_id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
	username VARCHAR(512) NOT NULL UNIQUE,
	password VARCHAR(512) NOT NULL,
	email VARCHAR(512) NOT NULL UNIQUE,
	address VARCHAR(512) NULL,
	photo_profile VARCHAR(512) NULL,
	telephone_number VARCHAR(512) NULL UNIQUE,
	point INT NULL,
	role ENUM('writer','reader','admin'),
    status ENUM('LOGIN','LOGOUT','SUSPEND'),
	join_date DATE NULL
);
CREATE TABLE IF NOT EXISTS comic(
	comic_id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
	comic_title VARCHAR(512) NOT NULL UNIQUE,
	comic_writer VARCHAR(512) NOT NULL, CONSTRAINT fk_comic_writer FOREIGN KEY (comic_writer) REFERENCES users(username) ON UPDATE CASCADE ON DELETE CASCADE,
	comic_chapter INT NULL,
	comic_banner VARCHAR(512),
	comic_genre VARCHAR(512),
	comic_comment TEXT
);
CREATE TABLE IF NOT EXISTS chapter(
	chapter_id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
	chapter_name VARCHAR(512) NOT NULL UNIQUE,
	chapter_comic VARCHAR(512) NOT NULL, CONSTRAINT fk_chapter_comic FOREIGN KEY (chapter_comic) REFERENCES comic(comic_title) ON UPDATE CASCADE ON DELETE CASCADE,
	chapter_page INT NULL,
	chapter_price INT NULL,
	chapter_writer VARCHAR(512) NOT NULL, CONSTRAINT fk_chapter_writer FOREIGN KEY (chapter_writer) REFERENCES comic(comic_writer) ON UPDATE CASCADE ON DELETE CASCADE,
	chapter_release_date DATE NULL,
	chapter_number INT NULL,
	chapter_status ENUM("upload","pending")
);
CREATE TABLE IF NOT EXISTS chapter_page(
	chapter_page_id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
	chapter_page_number INT NULL,
	chapter_page_image VARCHAR(512) NULL,
	chapter_page_chapter VARCHAR(512) NOT NULL, CONSTRAINT fk_page_chapter_chapter FOREIGN KEY (chapter_page_chapter) REFERENCES chapter(chapter_name) ON UPDATE CASCADE ON DELETE CASCADE,
	chapter_page_writer VARCHAR(512) NOT NULL, CONSTRAINT fk_page_chapter_writer FOREIGN KEY (chapter_page_writer) REFERENCES chapter(chapter_writer) ON UPDATE CASCADE ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS comment(
	comment_id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
	comment_sender_name VARCHAR(512) NOT NULL, CONSTRAINT fk_comment_sender_name FOREIGN KEY (comment_sender_name) REFERENCES users(username) ON UPDATE CASCADE ON DELETE CASCADE,
	comment_sender_email VARCHAR(512) NOT NULL, CONSTRAINT fk_comment_sender_email FOREIGN KEY (comment_sender_email) REFERENCES users(email) ON UPDATE CASCADE ON DELETE CASCADE,
	comment_sender_text TEXT,
	comment_comic_name VARCHAR(512) NOT NULL, CONSTRAINT fk_comment_comic_name FOREIGN KEY (comment_comic_name) REFERENCES comic(comic_title) ON UPDATE CASCADE ON DELETE CASCADE,
	comment_comic_writer VARCHAR(512) NOT NULL, CONSTRAINT fk_comment_comic_writer FOREIGN KEY (comment_comic_writer) REFERENCES comic(comic_writer) ON UPDATE CASCADE ON DELETE CASCADE,
	comment_comic_dest VARCHAR(512) NOT NULL, CONSTRAINT fk_comment_comic_dest FOREIGN KEY (comment_comic_dest) REFERENCES comic(comic_title) ON UPDATE CASCADE ON DELETE CASCADE
);
-- Tambah kolom created_at dan status ke tabel comment
ALTER TABLE comment 
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN status ENUM('pending','approved','rejected') DEFAULT 'pending';

-- Tambah index untuk performa
CREATE INDEX idx_comment_comic ON comment(comment_comic_name, status);
CREATE INDEX idx_comment_created ON comment(created_at);

CREATE TABLE IF NOT EXISTS transactions(
	transaction_id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
	transaction_reader INT NOT NULL, CONSTRAINT fk_transaction_reader FOREIGN KEY (transaction_reader) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE,
	transaction_writer INT NOT NULL, CONSTRAINT fk_transaction_writer FOREIGN KEY (transaction_writer) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE,
	transaction_comic INT NOT NULL, CONSTRAINT fk_transaction_comic FOREIGN KEY (transaction_comic) REFERENCES comic(comic_id) ON UPDATE CASCADE ON DELETE CASCADE,
	transaction_chapter INT NOT NULL, CONSTRAINT fk_transaction_chapter FOREIGN KEY (transaction_chapter) REFERENCES chapter(chapter_id) ON UPDATE CASCADE ON DELETE CASCADE,
	transaction_point INT NULL,
	transaction_date DATE,
	transaction_status ENUM("success","failed","pending")
);
-- Hapus tabel library lama (jika ada)
DROP TABLE IF EXISTS library;

-- Buat tabel user_library baru
CREATE TABLE user_library (
    user_library_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    chapter_id INT NOT NULL,
    comic_id INT NOT NULL,
    purchase_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    transaction_id INT,
    
    -- Foreign Key Constraints
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (chapter_id) REFERENCES chapter(chapter_id) ON DELETE CASCADE,
    FOREIGN KEY (comic_id) REFERENCES comic(comic_id) ON DELETE CASCADE,
    FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id) ON DELETE SET NULL,
    
    -- Unique constraint - satu user hanya bisa punya satu chapter sekali
    UNIQUE KEY unique_user_chapter (user_id, chapter_id),
    
    -- Index untuk performa query
    INDEX idx_user_id (user_id),
    INDEX idx_chapter_id (chapter_id),
    INDEX idx_comic_id (comic_id),
    INDEX idx_purchase_date (purchase_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS point_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    telephone VARCHAR(20) NOT NULL,
    point_amount INT NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    additional_notes TEXT,
    request_date DATETIME NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    processed_date DATETIME NULL,
    processed_by INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS comment_replies (
    reply_id INT AUTO_INCREMENT PRIMARY KEY,
    comment_id INT NOT NULL,
    user_id INT NOT NULL,
    username VARCHAR(255) NOT NULL,
    reply_text TEXT NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'approved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (comment_id) REFERENCES comment(comment_id) ON DELETE CASCADE
);

ALTER TABLE comment 
ADD COLUMN is_approved BOOLEAN DEFAULT TRUE,
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE comment ADD COLUMN comment_likes INT DEFAULT 0;

CREATE TABLE comment_likes (
    like_id INT NOT NULL AUTO_INCREMENT,
    comment_id INT NOT NULL,
    user_id INT NOT NULL,
    username VARCHAR(255) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (like_id),
    CONSTRAINT fk_comment_id FOREIGN KEY (comment_id) REFERENCES comment(comment_id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- Buat index
CREATE INDEX idx_comment_likes_comment_id ON comment_likes(comment_id);
CREATE UNIQUE INDEX idx_comment_likes_user_comment ON comment_likes(comment_id, user_id);

INSERT INTO users (username,password,email,address,telephone_number,point,role,status,join_date) VALUES ("admin","admin1234#","admin@admin.com","Jalan Ngawi Kulon no.20","123-456-678",0,"admin","LOGOUT",CURDATE());
