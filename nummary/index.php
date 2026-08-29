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
$stmt = $pdo->query("SELECT `sid`, `parent_id`, `name`, `description`  FROM `sections` WHERE `parent_id` IS NOT NULL");
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
    <title>Форум</title>
    <link rel="stylesheet" href="css/main.css">
</head>
<body>
    <div class="wrapper">
        <?php if (isset($_SESSION['uid'])) { ?>
            <header class="container">
                <span class="logo">AVANTURA</span>
                <nav>
                    <ul>
                        <li><a href="#">Форумы</a></li>
                        <li><a href="#">Пользователи</a></li>
                        <li><a href="#">Группы</a></li>
                        <li><a href="#">Сайт</a></li>
                    </ul>
                </nav>
            </header>
            <div class="hello container">
                <h1>Добро пожаловать на форум AVANTURA</h1>
                <h7>Для того, чтобы общаться с другими участниками форума, нужно не иметь хищный взгляд</h7>
            </div>
            <div class="news_section container">
                <div class="news_info">
                    <?php if (empty($recent_threads)): ?>
                        <p>Тем пока нет.</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Последние сообщения</th>
                                    <th></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_threads as $th): ?>
                                    <?php $time_ago = timeAgo($th['last_activity_dt']); ?>
                                    <tr>
                                        <!-- Название темы -->
                                        <td>
                                            <a href="thread.php?id=<?= $th['tid'] ?>">
                                                <b><?= htmlspecialchars($th['thread_name']) ?></b>
                                            </a>
                                            <br>
                                        </td>

                                        <!-- Время -->
                                        <td><?= $time_ago ?></td>

                                        <!-- Юзернейм -->
                                        <td><b><a href="profile.php?id=<?php echo $th['last_user_name'] ?>"><?php echo $th['last_user_name'] ?></a></b></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>


            <div class="razdely container">
                <div class="razdely_info">
                    <?php foreach ($razdely as $row) {
                        $now = $row['sid']; ?>
                        
                        <table class="table">
                            <thead>
                                <tr>
                                    <!-- Название раздела на всю ширину (3 колонки) -->
                                    <th colspan="3">
                                        <?php echo $row['name']; ?>
                                    </th>
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
                                                <a href="/section.php?id=<?php echo $rowy['sid']; ?>">
                                                    <?php echo $rowy['name']; ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?php echo isset($rowy['topics_count']) ? $rowy['topics_count'] : 0; ?>
                                            </td>
                                            <td>
                                                <?php echo isset($rowy['comments_count']) ? $rowy['comments_count'] : 0; ?>
                                            </td>
                                        </tr>
                                    <?php } 
                                } 
                                
                                if (!$has_subsections) { ?>
                                    <tr>
                                        <td colspan="3">В данном разделе пока нет подразделов</td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    <?php } ?>
                </div>
            </div>
                
            <form action="logout.php" method="POST">
                <button type="submit">Выйти</button>
            </form>
        <?php } else { ?>
            <a href="registwer.html"><button>Регистрация</button></a><br>
            <a href="login.html"><button>Войти</button></a>
        <?php } ?>
    </div>
</body>
</html>