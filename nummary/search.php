<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['uid'])) {
    header("Location: login.html");
    exit();
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];

if (!empty($query)) {
    $stmt = $pdo->prepare("
        SELECT 
            t.`tid`, 
            t.`name` AS `thread_name`, 
            c.`text` AS `comment_preview`, 
            u.`username` AS `author_name`, 
            c.`created_dt`
        FROM `comments` c
        LEFT JOIN `threads` t ON c.`tid` = t.`tid`
        LEFT JOIN `users` u ON c.`uid` = u.`uid`
        WHERE t.`name` LIKE :query_title OR c.`text` LIKE :query_text
        ORDER BY c.`cid` DESC
        LIMIT 30
    ");
    $stmt->execute([
        'query_title' => '%' . $query . '%',
        'query_text'  => '%' . $query . '%'
    ]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Поиск: <?= htmlspecialchars($query) ?> — AVANTURA</title>
    <link rel="stylesheet" href="/css/main.css">
    <link rel="stylesheet" href="/css/search.css">
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
                        <li><a href="#">What's new?</a></li>
                        <li><a href="#">Members</a></li>
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
                            <input type="text" name="q" class="search-bar" placeholder="🔍 Поиск" value="<?= htmlspecialchars($query) ?>">
                        </form>
                    </div>
                </div>
            </div>
        </header>
        <div class="header-spacer"></div>

        <main class="container search-wrapper-page" style="flex: 1; padding-top: 20px;">
            
            <div class="search-title-block">
                <h1>Результаты поиска</h1>
                <p class="search-subtitle">По запросу: <strong style="color: #fff;">«<?= htmlspecialchars($query) ?>»</strong> найденных совпадений: <?= count($results) ?></p>
            </div>

            <div class="forum-section-block">
                <table>
                    <thead>
                        <tr>
                            <th>Найдено в темах / Сообщение</th>
                            <th style="width: 200px; text-align: right; padding-right: 25px;">Автор / Дата</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($results)): ?>
                            <tr>
                                <td colspan="2" style="color: var(--text-muted); padding: 40px; text-align: center;">
                                    Ничего не найдено. Попробуйте изменить поисковый запрос.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($results as $row): ?>
                                <tr>
                                    <td>
                                        <a href="thread.php?id=<?= $row['tid'] ?>" class="search-result-thread-link">
                                            <b><?= htmlspecialchars($row['thread_name']) ?></b>
                                        </a>
                                        <div class="search-text-preview">
                                            <?= nl2br(htmlspecialchars(mb_strimwidth($row['comment_preview'], 0, 160, "..."))) ?>
                                        </div>
                                    </td>
                                    
                                    <td class="stats-cell" style="width: 200px; text-align: right; padding-right: 25px; vertical-align: middle;">
                                        <span><a href="profile.php?id=<?= urlencode($row['author_name']) ?>"><?= htmlspecialchars($row['author_name']) ?></a></span>
                                        <div style="font-size: 11px; margin-top: 4px; color: var(--text-muted);"><?= $row['created_dt'] ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>

        <!-- ФУТЕР -->
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
                    <p>Администрация не несёт ответственности за контент, размещённый пользователями.</p>
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
