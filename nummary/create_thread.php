<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['uid'])) {
    header("Location: index.php");
    exit();
}

$show_error = false;

if (isset($_GET['section_id'])) {
    $section_id = (int)$_GET['section_id']; 
    
    $sect_stmt = $pdo->prepare("SELECT `name` FROM `sections` WHERE `sid` = :sid");
    $sect_stmt->execute(['sid' => $section_id]);
    $current_section_name = $sect_stmt->fetchColumn();
    
    if (!$current_section_name) {
        $show_error = true;
    }
} else {
    $show_error = true;
}

if (!$show_error && $_SERVER['REQUEST_METHOD'] === "POST") {
    $thread_name = $_POST['thread_name'];
    $thread_text = $_POST["thread_text"];

    $thread_stmt = $pdo->prepare("
        INSERT INTO `threads` (`tid`, `sid`, `uid`, `name`, `created_dt`) VALUES (NULL, :sid, :uid, :name, CURRENT_TIMESTAMP)
    ");
    $thread_stmt->execute([
        'sid' => $section_id,
        'uid' => $_SESSION['uid'],
        'name' => $thread_name
    ]);
    $last_tread = $pdo->lastInsertId();

    $comment_stmt = $pdo->prepare("
        INSERT INTO `comments` (`cid`, `tid`, `uid`, `text`, `created_dt`) VALUES (NULL, :tid, :uid, :text, CURRENT_TIMESTAMP)
    ");
    $comment_stmt->execute([
        'tid' => $last_tread,
        'uid' => $_SESSION['uid'],
        'text' => $thread_text
    ]);
    $last_comment_id = $pdo->lastInsertId();

    if (!empty($_FILES['comment_images']['name'][0])) {
        $uploadDir = 'uploads/comments/';
        $ImgStmt = $pdo->prepare("INSERT INTO `comment_images` (`cid`, `path`, `original_name`) VALUES (:lastcid, :path, :orig_name)");

        foreach ($_FILES['comment_images']['tmp_name'] as $key => $tmpName) {
            if ($_FILES['comment_images']['error'][$key] === UPLOAD_ERR_OK) {
                $fileName = $_FILES['comment_images']['name'][$key];
                $origName = $_FILES['comment_images']['name'][$key];
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                $newName = uniqid('file_', true) . "." . $ext;
                $destination = $uploadDir . $newName;

                if (move_uploaded_file($tmpName, $destination)) {
                    $ImgStmt->execute([
                        'lastcid' => $last_comment_id,
                        'path' => $destination,
                        'orig_name' => $origName
                    ]);
                }
            }
        }
    }
    header("Location: thread.php?id={$last_tread}");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Создать тему — AVANTURA</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/create_thread.css">
</head>
<body>
    <div class="wrapper">

        <header class="sticky-header">
            <div class="container header-container">
                <a href="index.php" class="logo-link">
                    <span class="logo">AVANTURA</span>
                </a>
                
                <nav>
                    <ul>
                        <li><a href="#">Что нового?</a></li>
                        <li><a href="#">Пользователи</a></li>
                    </ul>
                </nav>
                
                <div class="header-right">
                    <div class="user-panel">
                        <a href="#" class="header-icon-btn" title="Уведомления">🔔</a>
                        <a href="#" class="header-icon-btn" title="Сообщения">💬</a>
                        <a href="profile.php?id=<?= urlencode($_SESSION['username']) ?>" class="username-link">
                            <span class="username-display"><?= htmlspecialchars($_SESSION['username']) ?></span>
                        </a>
                        <form action="logout.php" method="POST" class="inline-logout-form">
                            <button type="submit" class="logout-door-btn" title="Выйти">🚪</button>
                        </form>
                    </div>
                    <div class="search-wrapper">
                        <form action="search.php" method="GET">
                            <input type="text" name="q" class="search-bar" placeholder="🔍 Поиск">
                        </form>
                    </div>
                </div>
            </div>
        </header>
        <div class="header-spacer"></div>

        <main class="container" style="flex: 1;">
            
            <?php if ($show_error): ?>
                <div class="error-box">
                    <p>Упс... Похоже, вы не туда попали или раздел не указан.</p>
                    <div class="btn-error-group">
                        <a href="javascript:history.back()"><button class="btn-error-back">Назад</button></a>
                        <a href="index.php"><button class="btn-error-back" style="background-color: var(--color-accent);">На главную</button></a>
                    </div>
                </div>
            <?php else: ?>
                <div class="create-title-block">
                    <h1>Создать новую тему</h1>
                    <div class="breadcrumbs">
                        <a href="index.php">Главная</a> &gt; 
                        <a href="section.php?id=<?= $section_id ?>"><?= htmlspecialchars($current_section_name ?? 'Раздел') ?></a> &gt; 
                        <span>Новая тема</span>
                    </div>
                </div>

                <div class="create-box">
                    <form action="create_thread.php?section_id=<?= $section_id ?>" method="POST" enctype="multipart/form-data">
                        
                        <h2>Заголовок темы</h2>
                        <input type="text" name="thread_name" class="create-input" placeholder="Введите емкое и понятное название темы..." required>
                        
                        <h2>Текст темы / Первое сообщение</h2>
                        <textarea name="thread_text" class="create-textarea" placeholder="Опишите суть вашей темы подробно..."></textarea>
                        
                        <div class="file-upload-section">
                            <label>Прикрепить файлы к теме:</label>
                            <input type="file" name="comment_images[]" multiple>
                        </div>
                        
                        <button type="submit" name="send_comment" class="btn-create">Создать тему</button>
                    </form>
                </div>
            <?php endif; ?>

        </main>

        <footer class="footer">
            <div class="container footer-container">
                <div class="footer-left">
                    <span class="footer-badge">AVANTURA</span>
                    <div class="social-icons">
                        <a href="#" class="soc-vk">🌐</a>
                        <a href="#" class="soc-tg">✈️</a>
                        <a href="#" class="soc-yt">📺</a>
                        <a href="#" class="soc-tt">🎵</a>
                        <a href="#" class="soc-inst">📸</a>
                        <a href="#" class="soc-dc">💬</a>
                        <a href="#" class="soc-rad">📻</a>
                    </div>
                </div>
                <div class="footer-center">
                    <p>Администрация не несёт ответственности за контент (текстовый, фото, видео и пр.), размещённый пользователями.</p>
                </div>
                <div class="footer-right">
                    <a href="#">Обратная связь</a>
                    <a href="#">Условия и правила</a>
                </div>
            </div>
        </footer>

    </div>
</body>
</html>
