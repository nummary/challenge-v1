<?php
session_start();

if (isset($_SESSION['uid'])):
    require_once "db.php";
        
    if (isset($_GET['id'])) {
        // Превращаем входящую строку строго в целое число
        $section_id = (int)$_GET['id']; 
    } else {
        $section_id = 1; // Значение по умолчанию, если id не передан
    } 

    $parentid_stmt = $pdo->prepare("SELECT `parent_id` FROM `sections` WHERE `sid` = :id");
    $parentid_stmt->execute(['id' => $section_id]);
    $parent_id = $parentid_stmt->fetchColumn();

    $section_name_stmt = $pdo->prepare("SELECT `name` FROM `sections` WHERE `sid` = :pid");
    $section_name_stmt->execute(['pid' => $parent_id]);
    $section_name = $section_name_stmt->fetchColumn();

    $stmt = $pdo->query("SELECT 
        t.`tid`, 
        t.`name`, 
        t.`created_dt`, 
        u_author.`username` AS `author_name`,
        COUNT(c.`cid`) AS `comments_count`,
        MAX(c.`created_dt`) AS `last_comment_dt`,
        u_last.`username` AS `last_commenter_name`
        FROM `threads` t
        LEFT JOIN `users` u_author ON t.`uid` = u_author.`uid`
        LEFT JOIN `comments` c ON t.`tid` = c.`tid`
        LEFT JOIN `comments` c_last ON c_last.`cid` = (
        SELECT MAX(`cid`) FROM `comments` WHERE `tid` = t.`tid`
        )
        LEFT JOIN `users` u_last ON c_last.`uid` = u_last.`uid`
        WHERE t.`sid` = $section_id
        GROUP BY t.`tid`");
    $thrds = $stmt->fetchAll(PDO::FETCH_ASSOC);

else:
    ?>
    <a href="register.html"><button>Регистрация</button></a><br>
    <a href="login.html"><button>Войти</button></a>
    <?php
endif;

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Форум</title>
</head>
<body>

    <h1>Форум</h1>
    <h2><?php echo $section_name ?></h2>

    <p>Привет, <b><?= htmlspecialchars($_SESSION['username']) ?></b>!</p>

    <a href="create_thread.php?section_id=<?php echo $section_id ?>"><button>+ Создать тему</button></a><br>

    <?php
    echo '</br>';
    foreach ($thrds as $row) {
        // Если комментариев еще нет, пишем 'Нет ответов'
        $last_user = $row['last_commenter_name'] ? htmlspecialchars($row['last_commenter_name']) : 'Нет ответов';
        $last_time = $row['last_comment_dt'] ? $row['last_comment_dt'] : '-';
    
        echo "<a href='thread.php?id=" . $row['tid'] . "'>" . htmlspecialchars($row['name']) . "</a>";
        echo " | Автор: " . htmlspecialchars($row['author_name']);
        echo " | Ответов: " . $row['comments_count'];
        echo " | Последнее от: " . $last_user . " (" . $last_time . ")</br>";
    }
    echo '</br>';
    ?>

    <form action="logout.php" method="POST">
        <button type="submit">Выйти</button>
    </form>
    <form action="index.php" method="POST">
        <button type="submit">На главную</button>
    </form>
        



</body>
</html>