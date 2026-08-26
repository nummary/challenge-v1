<?php
session_start();
require_once "db.php";

if (isset($_SESSION['uid'])) {

    if (!isset($_GET['id'])) {
        die("Тема не найдена!");
    }
    $thread_id = (int)$_GET['id'];

    $stmt = $pdo->prepare("
        SELECT t.`tid`, t.`name`, t.`created_dt`, u.`username` AS `author_name`
        FROM `threads` t
        LEFT JOIN `users` u ON t.`uid` = u.`uid`
        WHERE t.`tid` = :tid
    ");
    $stmt->execute(['tid' => $thread_id]);
    $thread = $stmt->fetch(PDO::FETCH_ASSOC);

    $section_id_stmt = $pdo->prepare("SELECT `sid` FROM `threads` WHERE `tid` = :tid");
    $section_id_stmt->execute(['tid' => $thread_id]);
    $section_id = $section_id_stmt->fetchColumn();

    if (!$thread) {
        die("Тема не существует!");
    }

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;

    $limit = 10;
    $offset = ($page - 1) * $limit;

    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM `comments` WHERE `tid` = :tid");
    $count_stmt->execute(['tid' => $thread_id]);
    $total_comments = $count_stmt->fetchColumn();

    $total_pages = ceil($total_comments / $limit);

    $comments_stmt = $pdo->prepare("
        SELECT 
            c.`cid`, 
            c.`text`, 
            c.`created_dt`, 
            u.`username` AS `commenter_name`,
            GROUP_CONCAT(CONCAT(ci.`path`, '::', COALESCE(ci.`original_name`, 'Файл')) SEPARATOR '||') AS `files_data`
        FROM `comments` c
        LEFT JOIN `users` u ON c.`uid` = u.`uid`
        LEFT JOIN `comment_images` ci ON c.`cid` = ci.`cid`
        WHERE c.`tid` = :tid
        GROUP BY c.`cid`
        ORDER BY c.`cid` ASC
        LIMIT :limit OFFSET :offset
    ");
    $comments_stmt->bindValue(':tid', $thread_id, PDO::PARAM_INT);
    $comments_stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $comments_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $comments_stmt->execute();

    $comments = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === "POST") {
        $comment_text = $_POST['comment_text'];

        $comment_text_stmt = $pdo->prepare("
            INSERT INTO `comments` (`cid`, `tid`, `uid`, `text`, `created_dt`) VALUES (NULL, :tid, :uid, :comment_text, CURRENT_TIMESTAMP)
            ");
        $comment_text_stmt->execute([
            'tid' => $thread_id,
            'uid' => $_SESSION['uid'],
            'comment_text' => $comment_text
            ]);
        $last_comment_id = $pdo->lastInsertId();

        if (!empty($_FILES['comment_images']['name'][0])) {
            $uploadDir = 'uploads/comments/';

            $imgStmt = $pdo->prepare("INSERT INTO `comment_images` (`cid`, `path`, `original_name`) VALUES (:cid, :path, :orig_name)");

            foreach ($_FILES['comment_images']['tmp_name'] as $key => $tmpName) {
                if ($_FILES['comment_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $fileName = $_FILES['comment_images']['name'][$key];
                    $originalName = $_FILES['comment_images']['name'][$key];
                    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                    $newName = uniqid('file_', true) . '.' . $ext;
                    $destination = $uploadDir . $newName;

                    if (move_uploaded_file($tmpName, $destination)) {
                        $imgStmt->execute([
                            'cid'  => $last_comment_id,
                            'path' => $destination,
                            'orig_name' => $originalName
                        ]);
                    }
                }
            }
            header("Location: thread.php?id={$thread_id}&page={$total_pages}");
            exit;
        }
        header("Location: thread.php?id={$thread_id}&page={$total_pages}");
        exit;
    }


    

} else {

    header("Location: index.php");
    exit;
    
} ?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($thread['name']) ?></title>
</head>
<body>

    <?php if (isset($_SESSION['uid'])): ?>

        <a href="index.php">Главная</a> | <a href="section.php?id=<?= $section_id ?>">Назад</a>
        <hr>

        <!-- Кнопки управления темой (вверху страницы) -->
        <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['mod', 'admin'])): ?>
            <div style="background: #ffebe8; padding: 10px; margin-bottom: 15px;">
                <b>Панель модератора:</b>
                <a href="action_thread.php?action=pin&id=<?= $thread_id ?>">[📌 Закрепить / Открепить]</a>
                <a href="action_thread.php?action=delete&id=<?= $thread_id ?>" onclick="return confirm('Удалить тему?')">[❌ Удалить тему]</a>
            </div>
        <?php endif; ?>

        <!-- Заголовок темы -->
        <h1><?= htmlspecialchars($thread['name']) ?></h1>
        <p>Автор темы: <b><?= htmlspecialchars($thread['author_name'] ?? 'Аноним') ?></b> | Дата создания: <?= $thread['created_dt'] ?></p>
        <hr>
        
            <!-- Кнопки постраничной навигации -->
        <?php if ($total_pages > 1): ?>
            <div style="margin-top: 20px;">
                Страницы: 
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <b>[<?= $i ?>]</b>
                    <?php else: ?>
                        <a href="thread.php?id=<?= $thread_id ?>&page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

        <!-- Список сообщений / комментариев -->
        <h2>Сообщения:</h2>

        <?php if (empty($comments)): ?>
            <p>В этой теме пока нет сообщений.</p>
        <?php else: ?>
            <?php foreach ($comments as $index => $comment): ?>
                <div style="border: 1px solid #ccc; padding: 10px; margin-bottom: 10px;">
                    <p>
                        <b><?= htmlspecialchars($index + 1 . ' --- ' . $comment['cid'] . ' --- ' . $comment['commenter_name']) ?></b> 
                        <small>(<?= $comment['created_dt'] ?>)</small>
                    </p>
                    <div>
                        <?= nl2br(htmlspecialchars($comment['text'])) ?>
                    </div>

                    <div>
                        <?php
                        $all_votes = ['127827','128514','127876','128169','129505','128077','128078'];

                        $reactions_stmt = $pdo->prepare("
                            SELECT `vote`, COUNT(*) AS `total`
                            FROM `reactions`
                            WHERE `cid` = :cid
                            GROUP BY `vote`
                        ");
                        $reactions_stmt->execute(['cid' => $comment['cid']]);
                        $counts = $reactions_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

                        foreach ($all_votes as $vote_code) {
                            $total = isset($counts[$vote_code]) ? $counts[$vote_code] : 0;
                            
                            echo '<span style="margin-right: 8px;">';
                            echo '[';
                            echo '<a href="reaction.php?cid=' . $comment['cid'] . '&vote=' . urlencode($vote_code) . '">&#' . $vote_code . '</a>';
                            echo ' | ' . $total;
                            echo ']';
                            echo '</span>';
                        }
                        ?>
                    </div>

                    <?php if (!empty($comment['files_data'])): ?>
                        <div style="margin-top: 10px; padding: 8px; background: #f8f9fa; border-radius: 4px;">
                            <?php 
                            $filesList = explode('||', $comment['files_data']);
                            foreach ($filesList as $fileItem): 
                                list($filePath, $fileName) = explode('::', $fileItem);
                                $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                                $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                            ?>

                                <?php if ($isImage): ?>
                                    <!-- Картинка: выводим предпросмотр -->
                                    <a href="<?= htmlspecialchars($filePath) ?>" target="_blank">
                                        <img src="<?= htmlspecialchars($filePath) ?>" alt="photo" style="max-width: 180px; max-height: 180px; margin: 5px; border-radius: 4px; vertical-align: middle;">
                                    </a>
                                <?php else: ?>
                                    <!-- Документ / Код / Архив: выводим ссылку с оригинальным названием -->
                                    <div style="margin: 5px 0;">
                                        📄 <a href="<?= htmlspecialchars($filePath) ?>" download="<?= htmlspecialchars($fileName) ?>">
                                            <b><?= htmlspecialchars($fileName) ?></b>
                                        </a>
                                    </div>
                                <?php endif; ?>



                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
            <!-- Кнопки постраничной навигации -->
        <?php if ($total_pages > 1): ?>
            <div style="margin-top: 20px;">
                Страницы: 
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <b>[<?= $i ?>]</b>
                    <?php else: ?>
                        <a href="thread.php?id=<?= $thread_id ?>&page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

        <form action="thread.php?id=<?= $thread_id ?>" method="POST" enctype="multipart/form-data">
            <textarea name="comment_text" rows="5" cols="60" placeholder="Напишите ваш ответ..."></textarea><br><br>
            <label>Прикрепить файлы:</label><br>
            <input type="file" name="comment_images[]" multiple><br><br>
            <button type="submit" name="send_comment">Отправить ответ</button>
        </form>

    <?php else:

        header("Location: index.php");

    endif; ?>
</body>
</html>