-- 实时聊天系统数据库

CREATE DATABASE IF NOT EXISTS chat_db;
USE chat_db;

-- 用户表
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 消息表
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    username VARCHAR(50) NOT NULL,
    content TEXT NOT NULL,
    type ENUM('message', 'system', 'announcement', 'kick') DEFAULT 'message',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 管理员账号 (默认: admin / admin123)
INSERT INTO users (username, is_admin) VALUES ('admin', 1) ON DUPLICATE KEY UPDATE is_admin = 1;

-- 示例普通用户
INSERT INTO users (username, is_admin) VALUES ('user1', 0), ('user2', 0) ON DUPLICATE KEY UPDATE username = username;
