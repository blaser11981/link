<?php
// src/Models/User.php
declare(strict_types=1);

namespace VariuxLink\Models;

use VariuxLink\Database;
use PDO;

class User
{
    public int $id;
    public string $username;
    public string $password; // Hashed

    public static function findByUsername(string $username): ?self
    {
        $stmt = Database::getInstance()->prepare('SELECT * FROM users WHERE username = :username');
        $stmt->execute(['username' => $username]);
        $data = $stmt->fetch();
        if ($data) {
            $user = new self();
            $user->id = (int) $data['id'];
            $user->username = $data['username'];
            $user->password = $data['password'];
            return $user;
        }
        return null;
    }

    public static function create(string $username, string $password): void
    {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = Database::getInstance()->prepare('INSERT INTO users (username, password) VALUES (:username, :password)');
        $stmt->execute(['username' => $username, 'password' => $hashed]);
    }
}