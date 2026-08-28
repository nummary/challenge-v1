<?php
// ОБЯЗАТЕЛЬНО запускаем сессию на первой строчке файла
session_start();
if (isset($_SESSION['uid'])) {
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

} else {
    ?>
    <a href="register.html"><button>Регистрация</button></a><br>
    <a href="login.html"><button>Войти</button></a>
    <?php
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Форум</title>
</head>
<body>

    <h1>Форум</h1>
    <h2>Главная страница форума</h2>
    
    <p>Привет, <b><a href="profile.php?id=<?= $_SESSION['username'] ?>"><?= htmlspecialchars($_SESSION['username']) ?></a></b>!</p>
        
    <?php if (empty($recent_threads)): ?>
        <p>Тем пока нет.</p>
    <?php else: ?>
        <table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f2f2f2; text-align: left;">
                    <th>Название темы</th>
                    <th>Последний ответ</th>
                    <th>Пользователь</th>
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

    <?php
    foreach ($razdely as $row) {
        $now = $row['sid'];
        ?>
        <h1><?php echo $row['name'] . "</br>"?></h1>
        <?php
        foreach ($podrazdely as $rowy) {
            if ($rowy['parent_id'] == $now) {
                ?>
                <h2><a href='/section.php?id=<?php echo $rowy['sid'] ?>'><?php echo $rowy['name'] . "</br>"?></a></h2>
                <?php
            }
        }
    }
    ?>
        
    <form action="logout.php" method="POST">
        <button type="submit">Выйти</button>
    </form>

</body>
</html>