<?php
session_start();

if (isset($_SESSION['uid'])):
    require_once "db.php";
        
    if (isset($_GET['id'])) {
        $section_id = (int)$_GET['id']; 
    } else {
        $section_id = 1;
    } 

    $parentid_stmt = $pdo->prepare("SELECT `name`, `parent_id` FROM `sections` WHERE `sid` = :id");
    $parentid_stmt->execute(['id' => $section_id]);
    $parent_stmt = $parentid_stmt->fetch(PDO::FETCH_ASSOC);

    $section_name_stmt = $pdo->prepare("SELECT `name` FROM `sections` WHERE `sid` = :pid");
    $section_name_stmt->execute(['pid' => $parent_stmt['parent_id']]);
    $section_name = $section_name_stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT 
        t.`tid`, 
        t.`name`, 
        t.`created_dt`, 
        t.`is_pinned`,
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
        WHERE t.`sid` = :sid
        GROUP BY t.`tid`
        ORDER BY t.is_pinned DESC, t.tid DESC");
        
    $stmt->execute(['sid' => $section_id]);
    $thrds = $stmt->fetchAll(PDO::FETCH_ASSOC);

else:
    header("Location: index.php");
    exit();
endif; 
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($parent_stmt['name']) ?> — AVANTURA</title>
    <link rel="stylesheet" href="css/main.css">
    <style>
        .section-title-block {
            margin-top: 50px;
            margin-bottom: 20px;
        }
        .section-title-block h1 {
            font-size: 24px;
            color: #fff;
            margin-bottom: 5px;
        }
        .breadcrumbs {
            font-size: 14px;
            color: var(--text-muted);
        }
        .breadcrumbs a {
            color: var(--text-muted);
        }
        .breadcrumbs a:hover {
            color: #fff;
        }
        .section-control-panel {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 15px;
        }
        .btn-create-thread {
            background-color: #5c6b73; /* Серый цвет кнопок из макета */
            color: #fff;
            border: none;
            padding: 8px 18px;
            font-family: inherit;
            font-size: 14px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        .btn-create-thread:hover {
            background-color: var(--color-accent);
        }
    </style>
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
            
            <div class="section-title-block">
                <h1><?= htmlspecialchars($parent_stmt['name']) ?></h1>
                <div class="breadcrumbs">
                    <a href="index.php"><?= htmlspecialchars($section_name) ?></a> &gt; <span><?= htmlspecialchars($parent_stmt['name']) ?></span>
                </div>
            </div>
            
            <div class="section-control-panel">
                <a href="create_thread.php?section_id=<?= $section_id ?>">
                    <button class="btn-create-thread">Создать тему</button>
                </a>
            </div>

            <div class="forum-section-block">
                <table>
                    <tbody>
                        <?php if (empty($thrds)): ?>
                            <tr>
                                <td style="color: var(--text-muted); padding: 40px; text-align: center;">
                                    В этом подразделе тем пока нет.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $has_pinned_header = false;
                            $has_regular_header = false;

                            foreach ($thrds as $row): 
                                $last_user = $row['last_commenter_name'] ? htmlspecialchars($row['last_commenter_name']) : 'Нет ответов';
                                $last_time = $row['last_comment_dt'] ? $row['last_comment_dt'] : '-';
                                
                                if ($row['is_pinned'] == 1 && !$has_pinned_header) {
                                    echo '<tr class="table-subheader"><td colspan="3">📌 Закрепленные темы</td></tr>';
                                    $has_pinned_header = true;
                                }
                                
                                if ($row['is_pinned'] == 0 && !$has_regular_header) {
                                    echo '<tr class="table-subheader"><td colspan="3">💬 Обычные темы</td></tr>';
                                    $has_regular_header = true;
                                }
                            ?>
                                <tr>
                                    <td style="padding-left: 25px;">
                                        <a href="thread.php?id=<?= $row['tid'] ?>" style="font-size: 15px; color: var(--text-main);">
                                            <?= htmlspecialchars($row['name']) ?>
                                        </a>
                                    </td>
                                    
                                    <td class="stats-cell" style="text-align: right; width: 150px; color: var(--text-muted);">
                                        <span class="comment-count-text">Комменты: <?= $row['comments_count'] ?></span>
                                    </td>
                                    
                                    <td class="stats-cell" style="width: 280px; text-align: right; padding-right: 25px;">
                                        <?php if ($row['last_commenter_name']): ?>
                                            <span style="font-size: 13px; color: var(--text-main); display: inline;">
                                                <a href="profile.php?id=<?= urlencode($row['last_commenter_name']) ?>"><?= $last_user ?></a>
                                            </span>
                                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;"><?= $last_time ?></div>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 13px;">Нет ответов</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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
                    <p>Администрация не несёт ответственности за контент (текстовый, photo, видео и пр.), размещённый пользователями.</p>
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
