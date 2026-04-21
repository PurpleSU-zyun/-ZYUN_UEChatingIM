<?php
/**
 * 用户类
 */
require_once __DIR__ . '/Database.php';

class User {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getUserByUsername($username) {
        return $this->db->fetchOne(
            "SELECT * FROM users WHERE username = ?",
            [$username]
        );
    }

    public function getUserById($id) {
        return $this->db->fetchOne(
            "SELECT * FROM users WHERE id = ?",
            [$id]
        );
    }

    public function createUser($username) {
        try {
            $this->db->query(
                "INSERT INTO users (username, is_admin) VALUES (?, 0)",
                [$username]
            );
            return $this->getUserByUsername($username);
        } catch (Exception $e) {
            return $this->getUserByUsername($username);
        }
    }

    public function getAllUsers() {
        return $this->db->fetchAll("SELECT * FROM users ORDER BY created_at DESC");
    }

    public function isAdmin($username) {
        $user = $this->getUserByUsername($username);
        return $user && $user['is_admin'] == 1;
    }
}
