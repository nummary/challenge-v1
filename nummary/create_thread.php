<?php
session_start();
require_once "db.php";

if (isset($_SESSION['uid'])) {

	if (isset($_GET['section_id'])) {
	    // Превращаем входящую строку строго в целое число
	    $section_id = (int)$_GET['section_id']; 
	} else {
	    echo "Упс... Вы не туда попали" . '</br>';
	    ?>
	    <a href="javascript:history.back()"><button>Назад</button></a><br>
	    <a href="index.php"><button>На главную</button></a><br>
	    <?php 
	}

	if ($_SERVER['REQUEST_METHOD'] === "POST") {
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
		header("Location: thread.php?id={$last_tread}");
		exit();
	}




} else { ?>
	<a href="register.html"><button>Регистрация</button></a><br>
    <a href="login.html"><button>Войти</button></a>
<?php } ?>

<!-- <?php $stmt = $pdo->query("SELECT 
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

foreach ($thrds as $row) {
    // Если комментариев еще нет, пишем 'Нет ответов'
    $last_user = $row['last_commenter_name'] ? htmlspecialchars($row['last_commenter_name']) : 'Нет ответов';
    $last_time = $row['last_comment_dt'] ? $row['last_comment_dt'] : '-';

    echo "<a href='thread.php?id=" . $row['tid'] . "'>" . htmlspecialchars($row['name']) . "</a>";
    echo " | Автор: " . htmlspecialchars($row['author_name']);
    echo " | Ответов: " . $row['comments_count'];
    echo " | Последнее от: " . $last_user . " (" . $last_time . ")<br>";
}

?> -->

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Создать тему</title>
</head>
<body>

    <h1>Форум</h1>
    <h2>Создать тему</h2>

    <form action="create_thread.php?section_id=<?php echo $section_id ?>" method="POST">
        <h2>Заголовок темы</h2>
        <input type="text" name="thread_name" placeholder="Заголовок темы" required="true">
        <h2>Текст темы</h2>
        <textarea name="thread_text" rows="5" cols="60" placeholder="Текст темы..."></textarea><br><br>
		<label>Прикрепить файлы:</label><br>
        <input type="file" name="comment_images[]" multiple><br><br>
        <button type="submit" name="send_comment">Создать тему</button>
    </form>
        


</body>
</html>