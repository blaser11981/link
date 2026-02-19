<?php
// src/Controllers/AuthController.php
declare(strict_types=1);

namespace VariuxLink\Controllers;

use VariuxLink\Models\User;

class AuthController
{
    public function showLogin(): void
    {
        require __DIR__ . '/../../views/login.php';
    }

    public function login(): void
    {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $user = User::findByUsername($username);
        if ($user && password_verify($password, $user->password)) {
            $_SESSION['user_id'] = $user->id;
            header('Location: /dashboard');
            exit;
        }

        // Error
        $_SESSION['error'] = 'Invalid credentials';
        header('Location: /login');
        exit;
    }

    public function logout(): void
    {
        session_destroy();
        header('Location: /login');
        exit;
    }
}