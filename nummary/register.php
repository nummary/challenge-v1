<?php
require_once "db.php";

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;

    if (!empty($username) && !empty($email) && !empty($password)) {
        
        $check_user = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `username` = :user OR `email` = :email");
        $check_user->execute(['user' => $username, 'email' => $email]);
        
        $check_req = $pdo->prepare("SELECT COUNT(*) FROM `registration_requests` WHERE `username` = :user OR `email` = :email");
        $check_req->execute(['user' => $username, 'email' => $email]);

        if ($check_user->fetchColumn() > 0 || $check_req->fetchColumn() > 0) {
            $error = "Этот логин или почта уже заняты или находятся на рассмотрении!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO `registration_requests` (`id`, `username`, `email`, `password_hash`, `dob`, `created_at`) 
                VALUES (NULL, :user, :email, :pass, :dob, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([
                'user' => $username,
                'email' => $email,
                'pass' => $hashed_password,
                'dob'  => $dob
            ]);
            $message = "Ваша заявка успешно отправлена! Администратор рассмотрит её в ближайшее время.";
        }
    } else {
        $error = "Заполните все обязательные поля!";
    }
} else {
    header("Location: register.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Статус регистрации — AVANTURA</title>
    <link rel="stylesheet" href="/css/main.css">
    <link rel="stylesheet" href="/css/login.css">
</head>
<body>
    <div class="wrapper">
        <header class="sticky-header">
            <div class="container header-container">
                <a href="index.php" class="logo-link"><span class="logo">AVANTURA</span></a>
            </div>
        </header>
        <div class="header-spacer"></div>

        <main class="auth-wrapper">
            <div class="auth-card">
                <h1>Статус заявки</h1>
                
                <?php if(!empty($message)): ?>
                    <div style="background: #14291e; border: 1px solid #1f4d32; color: #72db9b; padding: 15px; border-radius: 6px; font-size: 14px; margin-bottom: 20px; line-height: 1.5; text-align: center;">
                        <?= $message ?>
                    </div>
                    <div class="auth-footer" style="text-align: center;">
                        <a href="/login.html">Вернуться ко входу</a>
                    </div>
                <?php endif; ?>
                
                <?php if(!empty($error)): ?>
                    <div style="background: #291415; border: 1px solid #4d1f21; color: #db7274; padding: 15px; border-radius: 6px; font-size: 14px; margin-bottom: 20px; text-align: center; font-weight: bold;">
                        <?= $error ?>
                    </div>
                    <div class="auth-footer" style="text-align: center;">
                        <a href="/register.html">Попробовать снова</a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
