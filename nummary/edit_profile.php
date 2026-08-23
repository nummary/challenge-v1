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
		$new_avatar_url = $_FILES['new_avatar_url'];
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
<html>
<head>
	<meta charset="utf-8">
	<title>Редактирование профиля <?php echo $_SESSION['username'] ?></title>
</head>
<body>
	<h1>Редактирование профиля <?php echo $_SESSION['username'] ?></h1>

	<div style="margin-top: 10px; padding: 8px; background: #f8f9fa; border-radius: 4px;">
        <img src="<?php echo $info['avatar_url'] ?>" alt="photo" style="max-width: 180px; max-height: 180px; margin: 5px; border-radius: 4px; vertical-align: middle;">
    </div>

    <div style="border: 1px solid #ccc; padding: 11px; margin-bottom: 11px;">
        <p>
	        <form action="edit_profile.php" method="POST" enctype="multipart/form-data">
	            <h2>Прикрепить фото:</h2>
	            <input type="file" name="new_avatar_url" multiple><br><br>
		        <h2>Изменить юзернейм</h2>
		        <input type="text" name="username" value="<?php echo $info['username'] ?>">
		        <h2>Изменить дату рождения</h2>
		        <input type="date" name="dob" value="<?php echo $info['dob'] ?>">
		        <h2>Изменить "О себе"</h2>
	            <textarea name="about" rows="5" cols="60"><?php echo $info['about'] ?></textarea><br><br>
	            <button type="submit" name="apply_changes">Сохранить изменения</button>
	        </form>
        </p>
        <div>
            <?php nl2br(htmlspecialchars($info['about']) . '</br>') ?>
    </div>

</body>
</html>