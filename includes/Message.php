<?php
/**
 * 消息类
 */
require_once __DIR__ . '/Database.php';

class Message {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function saveMessage($username, $content, $type = 'message', $userId = null) {
        $this->db->query(
            "INSERT INTO messages (user_id, username, content, type) VALUES (?, ?, ?, ?)",
            [$userId, $username, $content, $type]
        );
        return $this->db->getConnection()->lastInsertId();
    }

    public function getRecentMessages($limit = 100) {
        return $this->db->fetchAll(
            "SELECT * FROM messages ORDER BY created_at DESC LIMIT ?",
            [$limit]
        );
    }

    public function getAllMessages() {
        return $this->db->fetchAll(
            "SELECT * FROM messages ORDER BY created_at ASC"
        );
    }

    public function getMessagesByUser($username) {
        return $this->db->fetchAll(
            "SELECT * FROM messages WHERE username = ? ORDER BY created_at DESC",
            [$username]
        );
    }

    public function clearMessages() {
        $this->db->query("DELETE FROM messages");
    }
}
