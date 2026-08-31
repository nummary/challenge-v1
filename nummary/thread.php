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

    $crumbs_stmt = $pdo->prepare("
        SELECT s1.`name` AS `sub_name`, s2.`name` AS `parent_name` 
        FROM `sections` s1
        LEFT JOIN `sections` s2 ON s1.`parent_id` = s2.`sid`
        WHERE s1.`sid` = :sid
    ");
    $crumbs_stmt->execute(['sid' => $section_id]);
    $crumbs = $crumbs_stmt->fetch(PDO::FETCH_ASSOC);

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
            u.`role`,
            u.`avatar_url`,
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

        if (!empty($_FILES['comment_images']['name'])) {
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
        }
        header("Location: thread.php?id={$thread_id}&page={$total_pages}");
        exit;
    }
} else {
    header("Location: index.php");
    exit;
} 
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($thread['name']) ?> — AVANTURA</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/thread.css">
</head>
<body>
    <div class="wrapper">

        <header class="sticky-header">
            <div class="container header-container">
                <a href="index.php" class="logo-link">
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
                        <a href="profile.php?id=<?= urlencode($_SESSION['username']) ?>" class="username-link">
                            <span class="username-display"><?= htmlspecialchars($_SESSION['username']) ?></span>
                        </a>
                        <form action="logout.php" method="POST" class="inline-logout-form">
                            <button type="submit" class="logout-door-btn" title="Выйти">🚪</button>
                        </form>
                    </div>
                    <div class="search-wrapper">
                        <form action="search.php" method="GET">
                            <input type="text" name="q" class="search-bar" placeholder="🔍 Поиск">
                        </form>
                    </div>
                </div>
            </div>
        </header>
        <div class="header-spacer"></div>

        <main class="container" style="flex: 1;">
            
            <div class="thread-title-block">
                <h1><?= htmlspecialchars($thread['name']) ?></h1>
                <div class="breadcrumbs">
                    <a href="index.php">Главная</a> &gt; 
                    <a href="index.php"><?= htmlspecialchars($crumbs['parent_name'] ?? 'Раздел') ?></a> &gt; 
                    <a href="section.php?id=<?= $section_id ?>"><?= htmlspecialchars($crumbs['sub_name'] ?? 'Подраздел') ?></a> &gt; 
                    <span><?= htmlspecialchars($thread['name']) ?></span>
                </div>
            </div>

            <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['mod', 'admin'])): ?>
                <div class="mod-panel">
                    <span>⚙️ Панель модератора:</span>
                    <a href="action_thread.php?action=pin&id=<?= $thread_id ?>">📌 Закрепить / Открепить</a>
                    <a href="action_thread.php?action=delete&id=<?= $thread_id ?>" onclick="return confirm('Удалить тему?')">❌ Удалить тему</a>
                </div>
            <?php endif; ?>

            <div class="thread-control-panel">
                <a href="section.php?id=<?= $section_id ?>">
                    <button class="btn-back">← Назад к разделу</button>
                </a>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="pagination-item active"><?= $i ?></span>
                            <?php else: ?>
                                <a href="thread.php?id=<?= $thread_id ?>&page=<?= $i ?>" class="pagination-item"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="comments-section">
                <?php if (empty($comments)): ?>
                    <div class="comment-block">
                        <div class="comment-body" style="text-align: center; color: var(--text-muted); width: 100%;">
                            В этой теме пока нет сообщений. Будьте первым, кто оставит ответ!
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($comments as $index => $comment): 
                        $global_index = $offset + $index + 1; 
                        
                        $user_role = !empty($comment['role']) ? htmlspecialchars($comment['role']) : 'Пользователь';
                    ?>
                        <div class="comment-block">
                            
                            <div class="comment-author-zone">
                                <div class="author-avatar-box">
                                    <?php if (!empty($comment['avatar_url']) && file_exists($comment['avatar_url'])): ?>
                                        <img src="<?= htmlspecialchars($comment['avatar_url']) ?>" alt="Avatar">
                                    <?php else: ?>
                                        <img src="/uploads/avatars/default.png" alt="Avatar">
                                    <?php endif; ?>
                                </div>
                                
                                <a href="profile.php?id=<?= urlencode($comment['commenter_name']) ?>" class="author-username-btn">
                                    <?= htmlspecialchars($comment['commenter_name']) ?>
                                </a>
                                
                                <div class="author-role-badge">
                                    <?= $user_role ?>
                                </div>
                            </div>

                            <div class="comment-content-zone">
                                <div>
                                    <div class="comment-top-meta">
                                        <span><?= $comment['created_dt'] ?></span>
                                        <span class="msg-number">⚙️ #<?= $global_index ?></span>
                                    </div>

                                    <div class="comment-text-content">
                                        <?= nl2br(htmlspecialchars($comment['text'])) ?>
                                    </div>
                                    
                                    <?php if (!empty($comment['files_data'])): ?>
                                        <div class="comment-attached-files" style="margin-top: 20px; padding-top: 15px; border-top: 1px dashed rgba(107, 156, 255, 0.1);">
                                            <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 10px;">📎 Прикрепленные файлы:</div>
                                            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                                                <?php 
                                                $files = explode('||', $comment['files_data']);
                                                foreach ($files as $file):
                                                    $file_parts = explode('::', $file);
                                                    if (count($file_parts) === 2):
                                                        $file_path = $file_parts[0];
                                                        $file_name = $file_parts[1];
                                                        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                                                        
                                                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                                                            <a href="<?= htmlspecialchars($file_path) ?>" target="_blank" style="border: none !important;">
                                                                <img src="<?= htmlspecialchars($file_path) ?>" alt="<?= htmlspecialchars($file_name) ?>" style="max-width: 150px; max-height: 150px; border-radius: 6px; border: 1px solid rgba(107, 156, 255, 0.2); transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.03)'" onmouseout="this.style.transform='scale(1)'">
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="<?= htmlspecialchars($file_path) ?>" target="_blank" class="footer-btn" style="font-size: 13px; display: inline-flex; align-items: center; gap: 5px;">
                                                                📄 <?= htmlspecialchars($file_name) ?>
                                                            </a>
                                                        <?php endif;
                                                    endif;
                                                endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="reactions-container">
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
                                        ?>
                                        <div class="reaction-badge">
                                            <a href="reaction.php?cid=<?= $comment['cid'] ?>&vote=<?= urlencode($vote_code) ?>">
                                                &#<?= $vote_code ?>;
                                            </a>
                                            <span class="reaction-count"><?= $total ?></span>
                                        </div>
                                        <?php
                                    }
                                    ?>
                                </div>

                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="reply-box">
                <h3>Оставить ответ</h3>
                <form action="thread.php?id=<?= $thread_id ?>" method="POST" enctype="multipart/form-data">
                    <textarea name="comment_text" class="reply-textarea" placeholder="Напишите ваше сообщение..." required></textarea>
                    
                    <div class="file-input-wrapper">
                        <label style="display: block; margin-bottom: 5px;">Прикрепить изображения:</label>
                        <input type="file" name="comment_images[]" multiple style="color: var(--text-muted);">
                    </div>
                    
                    <button type="submit" class="btn-reply">Отправить ответ</button>
                </form>
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