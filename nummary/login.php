<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    if ($remember) {
        $lifetime = 30 * 24 * 60 * 60; // 30 days
    } else {
        $lifetime = 3 * 60 * 60;       // 3 hours
    }

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();

    require_once 'db.php';

    $stmt = $pdo->prepare("SELECT `uid`, `username`, `pass`, `role` FROM `users` WHERE `username` = :username");
    $stmt->execute([':username' => $username]);
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['pass'])) {
        $_SESSION['uid']      = $user['uid'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = $user['role'];

        header('Location: /index.php');
        exit;
    } else {
        echo "Неверный логин или пароль";
    }
}