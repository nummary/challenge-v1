<?php
session_start();
require_once "db.php";

function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'только что';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' мин. назад';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' ч. назад';
    } else {
        return floor($diff / 86400) . ' дн. назад';
    }
}
    
$stmt = $pdo->query("SELECT `sid`, `name` FROM `sections` WHERE `parent_id` IS NULL");
$razdely = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT 
    s.`sid`, 
    s.`parent_id`, 
    s.`name`, 
    s.`description`,
    COUNT(DISTINCT t.`tid`) AS `topics_count`,
    COUNT(DISTINCT c.`cid`) AS `comments_count`
FROM `sections` s
LEFT JOIN `threads` t ON t.`sid` = s.`sid`
LEFT JOIN `comments` c ON c.`tid` = t.`tid`
WHERE s.`parent_id` IS NOT NULL
GROUP BY s.`sid`");
$podrazdely = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt_thrds = $pdo->prepare("
    SELECT 
        t.`tid`,
        t.`name` AS `thread_name`,
        t.`created_dt` AS `thread_created_dt`,
        COALESCE(u_replier.`username`, u_author.`username`) AS `last_user_name`,
        COALESCE(c_last.`created_dt`, t.`created_dt`) AS `last_activity_dt`
    FROM `threads` t
    LEFT JOIN `users` u_author ON t.`uid` = u_author.`uid`
    LEFT JOIN (
        SELECT c1.*
        FROM `comments` c1
        INNER JOIN (
            SELECT `tid`, MAX(`cid`) AS `max_cid`
            FROM `comments`
            GROUP BY `tid`
        ) c2 ON c1.`cid` = c2.`max_cid`
    ) c_last ON t.`tid` = c_last.`tid`
    LEFT JOIN `users` u_replier ON c_last.`uid` = u_replier.`uid`
    ORDER BY `last_activity_dt` DESC
    LIMIT 10
    ");
$stmt_thrds->execute();
$recent_threads = $stmt_thrds->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Форум — AVANTURA</title>
    <link rel="stylesheet" href="/css/main.css">
</head>
<body>
    <div class="wrapper">
        <?php if (isset($_SESSION['uid'])) { ?>
            
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

            <div class="container hello">
                <h1>Добро пожаловать на форум AVANTURA</h1>
                <p>Для того, чтобы общаться с другими участниками форума, вы должны пройти регистрацию, либо авторизацию.</p>
            </div>

            <div class="news_section container">
                <?php if (!empty($recent_threads)): ?>
                    <div class="forum-section-block compact-table">
                        <table>
                            <thead>
                                <tr>
                                    <th colspan="3">Последние сообщения</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_threads as $th): ?>
                                    <?php $time_ago = timeAgo($th['last_activity_dt']); ?>
                                    <tr>
                                        <td>
                                            <a href="thread.php?id=<?= $th['tid'] ?>">
                                                <b><?= htmlspecialchars($th['thread_name']) ?></b>
                                            </a>
                                        </td>
                                        <td class="time-cell" style="text-align: right; width: 150px;"><?= $time_ago ?></td>
                                        <td class="author-cell" style="text-align: right; width: 180px; padding-right: 20px;">
                                            <a href="profile.php?id=<?= urlencode($th['last_user_name']) ?>">
                                                <b><?= htmlspecialchars($th['last_user_name']) ?></b>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="razdely container">
                <?php foreach ($razdely as $row) {
                    $now = $row['sid']; ?>
                    
                    <div class="forum-section-block">
                        <table>
                            <thead>
                                <tr>
                                    <th><?= htmlspecialchars($row['name']); ?></th>
                                    <th style="width: 120px; text-align: right;">Темы</th>
                                    <th style="width: 120px; text-align: right; padding-right: 25px;">Комменты</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $has_subsections = false;
                                foreach ($podrazdely as $rowy) { 
                                    if ($rowy['parent_id'] == $now) { 
                                        $has_subsections = true; ?>
                                        <tr>
                                            <td>
                                                <a href="/section.php?id=<?= $rowy['sid']; ?>" style="font-weight: bold; font-size: 15px;">
                                                    <?= htmlspecialchars($rowy['name']); ?>
                                                </a>
                                                <?php if(!empty($rowy['description'])): ?>
                                                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                                                        <?= htmlspecialchars($rowy['description']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="stats-cell">
                                                <span><?= $rowy['topics_count']; ?></span>
                                            </td>
                                            <td class="stats-cell" style="padding-right: 25px;">
                                                <span><?= $rowy['comments_count']; ?></span>
                                            </td>
                                        </tr>
                                    <?php } 
                                } 
                                
                                if (!$has_subsections) { ?>
                                    <tr>
                                        <td colspan="3" style="color: var(--text-muted); padding: 15px 20px;">В данном разделе пока нет подразделов</td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>

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
                        <p>Сообщения пользователей являются их субъективным мнением и могут не соответствовать действительности.</p>
                    </div>

                    <div class="footer-right">
                        <a href="#">Обратная связь</a>
                        <a href="#">Условия и правила</a>
                        <a href="#">Политика конфиденциальности</a>
                    </div>
                </div>
            </footer>
                

        <?php } else { ?>
            <div class="container hello">
                <a href="register.html">Регистрация</a> | <a href="login.html">Войти</a>
            </div>
        <?php } ?>
    </div>
</body>
</html>
