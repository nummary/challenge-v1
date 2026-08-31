<?php
session_start();

if (isset($_SESSION['uid'])) {
	require_once "db.php";

	$info_stmt = $pdo->prepare("
		SELECT
			`username`,
			`dob`,
			`about`,
			`avatar_url`
		FROM `users`
		WHERE `uid` = :uid
		");
	$info_stmt->execute(['uid' => $_SESSION['uid']]);
	$info = $info_stmt->fetch(PDO::FETCH_ASSOC);

	if ($_SERVER['REQUEST_METHOD'] === "POST") {
		$destination = null;
		$uploadDir = "upload/avatars/";
		$new_username = $_POST['username'];
		$new_dob = $_POST['dob'];
		$new_about = $_POST['about'];

		if (isset($_FILES['new_avatar_url']) && $_FILES['new_avatar_url']['error'] === UPLOAD_ERR_OK) {
		    $tmpName = $_FILES['new_avatar_url']['tmp_name'];
		    $ext = strtolower(pathinfo($_FILES['new_avatar_url']['name'], PATHINFO_EXTENSION));

		    $newName = uniqid('file_', true) . '.' . $ext;
		    $dest = $uploadDir . $newName;

		    if (move_uploaded_file($tmpName, $dest)) {
		    	$destination = $dest;
	    	}
		}

		if ($destination) {
			$new_info_stmt = $pdo->prepare("UPDATE `users` SET `avatar_url` = :new_avatar, `username` = :user, `dob` = :dob, `about` = :about WHERE `users`.`uid` = :uid");
			$new_info_stmt->execute([
				'new_avatar' => $destination,
				'user' => $new_username,
				'dob' => $new_dob,
				'about' => $new_about,
				'uid' => $_SESSION['uid']
			]);
			$_SESSION['username'] = $new_username;
		} else {
			$new_info_stmt = $pdo->prepare("UPDATE `users` SET `username` = :user, `dob` = :dob, `about` = :about WHERE `users`.`uid` = :uid");
			$new_info_stmt->execute([
				'user' => $new_username,
				'dob' => $new_dob,
				'about' => $new_about,
				'uid' => $_SESSION['uid']
			]);
			$_SESSION['username'] = $new_username;
		}
		header("Location: profile.php");
		exit();
	}

} else {
	header("Location: index.php");
	exit();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
	<meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Редактирование профиля — AVANTURA</title>
    <link rel="stylesheet" href="/css/main.css">
    <link rel="stylesheet" href="/css/edit_profile.css">
</head>
<body>
    <div class="wrapper">

        <header class="sticky-header">
            <div class="container header-container">
                <a href="/index.php" class="logo-link">
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
                        <a href="/profile.php?id=<?= urlencode($_SESSION['username']) ?>" class="username-link">
                            <span class="username-display"><?= htmlspecialchars($_SESSION['username']) ?></span>
                        </a>
                        <form action="/logout.php" method="POST" class="inline-logout-form">
                            <button type="submit" class="logout-door-btn" title="Выйти">🚪</button>
                        </form>
                    </div>
                    <div class="search-wrapper">
                        <input type="text" class="search-bar" placeholder="🔍 Поиск">
                    </div>
                </div>
            </div>
        </header>
        <div class="header-spacer"></div>

        <main class="container edit-page-container" style="flex: 1;">
            
            <div class="edit-title-block">
                <h1>Настройки профиля</h1>
                <div class="breadcrumbs">
                    <a href="/index.php">Главная</a> &gt; 
                    <a href="/profile.php?id=<?= urlencode($info['username']) ?>"><?= htmlspecialchars($info['username']) ?></a> &gt; 
                    <span>Редактирование</span>
                </div>
            </div>

            <div class="edit-box">
                <div class="edit-avatar-zone">
                    <div class="edit-avatar-title">Текущий аватар</div>
                    <div class="edit-preview-box">
                        <?php if (!empty($info['avatar_url']) && file_exists($info['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars($info['avatar_url']) ?>" alt="Avatar">
                        <?php else: ?>
                            <span style="font-size: 14px; color: var(--text-muted);">Нет фото</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="edit-fields-zone">
                    <form action="edit_profile.php" method="POST" enctype="multipart/form-data">
                        
                        <h2>Загрузить новую аватарку:</h2>
                        <input type="file" name="new_avatar_url">
                        
                        <h2>Изменить юзернейм:</h2>
                        <input type="text" name="username" class="edit-input" value="<?= htmlspecialchars($info['username']) ?>" required>
                        
                        <h2>Изменить дату рождения:</h2>
                        <input type="date" name="dob" class="edit-input" value="<?= htmlspecialchars($info['dob']) ?>">
                        
                        <h2>Изменить информацию "О себе":</h2>
                        <textarea name="about" class="edit-textarea" placeholder="Расскажите что-нибудь о себе..."><?= htmlspecialchars($info['about']) ?></textarea>
                        
                        <button type="submit" name="apply_changes" class="btn-save-profile">Сохранить изменения</button>
                    </form>
                </div>

            </div>

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
