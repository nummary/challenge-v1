<?php
session_start();
require_once "db.php";

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
    SELECT c.`cid`, c.`text`, c.`created_dt`, u.`username` AS `commenter_name`
    FROM `comments` c
    LEFT JOIN `users` u ON c.`uid` = u.`uid`
    WHERE c.`tid` = :tid
    ORDER BY c.`cid` ASC
    LIMIT :limit OFFSET :offset
");
// Привязываем параметры с типом INT для корректной работы LIMIT/OFFSET
$comments_stmt->bindValue(':tid', $thread_id, PDO::PARAM_INT);
$comments_stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$comments_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$comments_stmt->execute();

$comments = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($thread['name']) ?></title>
</head>
<body>

    <a href="index.php">Главная</a> | <a href="javascript:history.back()">Назад</a>
    <hr>

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
                    <b><?= htmlspecialchars($comment['commenter_name'] ?? 'Удаленный пользователь') ?></b> 
                    <small>(<?= $comment['created_dt'] ?>)</small>
                </p>
                <div>
                    <?= nl2br(htmlspecialchars($comment['text'])) ?>
                </div>
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

</body>
</html>