<?php
session_start();
require_once "db.php";

if (isset($_SESSION['uid'])) {

	if (isset($_GET['section_id'])) {
	    $section_id = (int)$_GET['section_id']; 
	} else {
	    echo "Упс... Вы не туда попали" . '</br>';
	    ?>
	    <a href="javascript:history.back()"><button>Назад</button></a><br>
	    <a href="index.php"><button>На главную</button></a><br>
	    <?php
	    exit();
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
			header("Location: thread.php?id={$last_tread}");
			exit();
		}
		header("Location: thread.php?id={$last_tread}");
		exit();
	}


} else {

	header("Location: index.php");
	exit();

} ?>


<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Создать тему</title>
</head>
<body>

    <h1>Форум</h1>
    <h2>Создать тему</h2>

    <form action="create_thread.php?section_id=<?php echo $section_id ?>" method="POST" enctype="multipart/form-data">
        <h2>Заголовок темы</h2>
        <input type="text" name="thread_name" placeholder="Заголовок темы" required="true">
        <h2>Текст темы</h2>
        <textarea name="thread_text" rows="5" cols="60" placeholder="Текст темы..."></textarea><br><br>
		<label>Прикрепить файлы:</label><br>
        <input type="file" name="comment_images[]" multiple ><br><br>
        <button type="submit" name="send_comment">Создать тему</button>
    </form>
        


</body>
</html>