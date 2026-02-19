<?php
// src/Controllers/AuthController.php

declare(strict_types=1);

namespace VariuxLink\Controllers;

use VariuxLink\Database;
use VariuxLink\Models\User;  // assuming you have a User model

class AuthController
{
    public function showLoginForm(): void
    {
        require __DIR__ . '/../../views/login.php';
    }

    public function handleLogin(): void
{
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $_SESSION['error'] = 'Email and password are required.';
        header('Location: /login');
        exit;
    }

    $db = Database::getInstance();

    $stmt = $db->prepare('
        SELECT id, email, password_hash, role 
        FROM users 
        WHERE email = ? AND is_active = 1
    ');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
// just before password_verify(...)
error_log("Login attempt - email: '$email'");
error_log("Found user: " . ($user ? 'yes' : 'no'));
if ($user) {
    error_log("Hash stored: " . $user['password_hash']);
    error_log("Verify result: " . (password_verify($password, $user['password_hash']) ? 'true' : 'false'));
}
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role']  = $user['role'];

        // Optional: update last_login_at
        $db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')
           ->execute([$user['id']]);

        header('Location: /dashboard');
        exit;
    }

    $_SESSION['error'] = 'Invalid email or password.';
    header('Location: /login');
    exit;
}

    public function logout(): void
    {
        session_destroy();
        header('Location: /login');
        exit;
    }

    private function findUserByEmail(string $email): ?array
    {
        $db = Database::getInstance();

        $stmt = $db->prepare('SELECT id, email, password_hash, role FROM users WHERE email = ? AND is_active = 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        return $user ?: null;
    }
}