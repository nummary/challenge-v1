<?php
session_start();

if (isset($_SESSION['uid'])) {
	require_once "db.php";
	
	if (!isset($_GET['id'])) {
		header("Location: profile.php?id={$_SESSION['username']}");
		exit();
	} else {
		$info_stmt = $pdo->prepare("
			SELECT 
			    u.`role`, 
			    u.`dob`, 
			    u.`avatar_url`, 
			    u.`about`, 
			    u.`created_date`,
			    u.`uid`,
			    COUNT(DISTINCT c.`cid`) AS `comments_count`,
			    COUNT(DISTINCT t.`tid`) AS `threads_count`
			FROM `users` u
			LEFT JOIN `comments` c ON c.`uid` = u.`uid`
			LEFT JOIN `threads` t ON t.`uid` = u.`uid`
			WHERE u.`username` = :user
			GROUP BY u.`uid`;
			");
		$info_stmt->execute([
			'user' => $_GET['id']
		]);
		$info = $info_stmt->fetch(PDO::FETCH_ASSOC);

		if (!$info) {
		    echo "Пользователь не найден!";
		    echo '<br><a href="javascript:history.back()"><button>Назад</button></a>';
		    exit();
		}
	}
	
} else {
	header("Location: index.php");
}


?>

<!DOCTYPE html>
<html lang="ru">
<head>
	<meta charset="utf-8">
	<title><?php echo $_GET['id'] ?>'s profile</title>
</head>
<body>
	<h1>Профиль <?php echo $_GET['id'] ?>.</h1>

	<div style="margin-top: 10px; padding: 8px; background: #f8f9fa; border-radius: 4px;">
        <img src="<?php echo $info['avatar_url'] ?>" alt="photo" style="max-width: 180px; max-height: 180px; margin: 5px; border-radius: 4px; vertical-align: middle;">
    </div>

    <?php if ($_SESSION['username'] === $_GET['id']) { ?>
    	<a href="edit_profile.php"><button>✏️ Редактировать профиль</button></a><br>
	<?php } ?>

	<div style="border: 1px solid #ccc; padding: 11px; margin-bottom: 11px;">
        <p>
            <b><?= 'Юзернейм: ' . htmlspecialchars($_GET['id']) . '</br>'?></b>
            <b><?= 'Роль: ' . htmlspecialchars($info['role']) . '</br>' ?></b>
            <b><?= 'Дата рождения: ' . htmlspecialchars($info['dob'] ?? 'Не указана') . '</br>' ?></b>
            <b><?= 'Дата регистрации: ' . htmlspecialchars($info['created_date']) . '</br>' ?></b>
            <b><?= 'Количество комментариев: ' . htmlspecialchars($info['comments_count'] ?? 'Нет комментариев') . '</br>' ?></b>
            <b><?= 'Количество тем: ' . htmlspecialchars($info['threads_count'] ?? 'Нет тем') . '</br>' ?></b>
        </p>
        <div>
            <?= 'О себе: ' . '</br>' . '</br>' . nl2br(htmlspecialchars($info['about']) . '</br>') ?>
    </div>



</body>
</html>