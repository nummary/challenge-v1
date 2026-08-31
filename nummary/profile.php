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
            ?>
            <!DOCTYPE html>
            <html lang="ru">
            <head>
                <meta charset="UTF-8">
                <title>Ошибка — AVANTURA</title>
                <link rel="stylesheet" href="/css/main.css">
                <link rel="stylesheet" href="/css/profile.css">
            </head>
            <body>
                <div class="wrapper">
                    <header class="sticky-header">
                        <div class="container header-container">
                            <a href="/index.php" class="logo-link"><span class="logo">AVANTURA</span></a>
                        </div>
                    </header>
                    <div class="header-spacer"></div>
                    <main class="container">
                        <div class="profile-error-box">
                            <p>Пользователь не найден!</p>
                            <a href="javascript:history.back()"><button class="btn-error-back" style="background-color:#5c6b73; color:#fff; border:none; padding:10px 20px; border-radius:6px; font-family:inherit; cursor:pointer;">Назад</button></a>
                        </div>
                    </main>
                </div>
            </body>
            </html>
            <?php
            exit();
        }

        $reacts_stmt = $pdo->prepare("SELECT COUNT(`vote`) AS `react_count` FROM `reactions` WHERE `uid` = :uid");
        $reacts_stmt->execute(['uid' => $info['uid']]);
        $reactions = $reacts_stmt->fetch(PDO::FETCH_ASSOC);

        $user_comments_stmt = $pdo->prepare("
            SELECT c.`text`, c.`created_dt`, t.`tid`, t.`name` AS `thread_name`
            FROM `comments` c
            LEFT JOIN `threads` t ON c.`tid` = t.`tid`
            WHERE c.`uid` = :uid
            ORDER BY c.`cid` DESC
            LIMIT 10
        ");
        $user_comments_stmt->execute(['uid' => $info['uid']]);
        $user_comments = $user_comments_stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Профиль <?= htmlspecialchars($_GET['id']) ?> — AVANTURA</title>
    <link rel="stylesheet" href="/css/main.css">
    <link rel="stylesheet" href="/css/profile.css">
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
                        <form action="search.php" method="GET">
                            <input type="text" name="q" class="search-bar" placeholder="🔍 Поиск">
                        </form>
                    </div>
                </div>
            </div>
        </header>
        <div class="header-spacer"></div>

        <main class="container profile-wrapper" style="flex: 1;">
            
            <div class="profile-card">
                <div class="profile-left-zone">
                    <div class="profile-avatar-box">
                        <?php if (!empty($info['avatar_url']) && file_exists($info['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars($info['avatar_url']) ?>" alt="Avatar">
                        <?php else: ?>
                            <img src="/uploads/avatars/default.png" alt="Avatar">
                        <?php endif; ?>
                    </div>
                </div>

                <div class="profile-right-zone">
                    <div class="profile-main-meta">
                        <h1><?= htmlspecialchars($_GET['id']) ?></h1>
                        <div class="profile-role-badge">
                            <?= !empty($info['role']) ? htmlspecialchars($info['role']) : 'Пользователь' ?>
                        </div>
                        
                        <div class="profile-about-box">
                            <div class="profile-about-title">О себе</div>
                            <div class="profile-about-text">
                                <?= !empty($info['about']) ? nl2br(htmlspecialchars($info['about'])) : 'Здесь пока ничего не написано.' ?>
                            </div>
                        </div>
                    </div>

                    <div class="profile-meta-row">
                        <div class="meta-badge-item stats-badge">
                            <div class="stats-label">сообщений / реакции</div>
                            <div class="stats-numbers"><?= $info['comments_count'] ?? 0 ?> / <?= $reactions['react_count'] ?? 0 ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($_SESSION['username'] === $_GET['id']): ?>
                <a href="/edit_profile.php" style="border: none !important;">
                    <button class="btn-edit-profile">✏️ Редактировать профиль</button>
                </a>
            <?php endif; ?>

            <div class="profile-tabs-nav">
                <button class="profile-tab-btn active" onclick="openTab(event, 'tab-activity')">Активность</button>
                <button class="profile-tab-btn" onclick="openTab(event, 'tab-info')">Информация</button>
                <button class="profile-tab-btn" onclick="openTab(event, 'tab-custom')">Тоже будет что-то</button>
            </div>
            
            <div class="profile-tabs-content">
                
                <div id="tab-activity" class="tab-content-item active">
                    <h3 style="font-size: 16px; margin-bottom: 15px; color: #fff;">Последние сообщения пользователя:</h3>
                    <?php if (empty($user_comments)): ?>
                        <p style="color: var(--text-muted); font-size: 14px;">Пользователь ещё не оставлял сообщений на форуме.</p>
                    <?php else: ?>
                        <div class="profile-activity-list">
                            <?php foreach ($user_comments as $comment): ?>
                                <div class="profile-activity-card">
                                    <div class="activity-meta">
                                        Ответ в теме: <a href="thread.php?id=<?= $comment['tid'] ?>"><b><?= htmlspecialchars($comment['thread_name']) ?></b></a>
                                        <span style="float: right; color: var(--text-muted);"><?= $comment['created_dt'] ?></span>
                                    </div>
                                    <div class="activity-text">
                                        <?= nl2br(htmlspecialchars(mb_strimwidth($comment['text'], 0, 150, "..."))) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="tab-info" class="tab-content-item">
                    <div class="profile-details-grid">
                        <div class="profile-detail-row">
                            <div class="profile-detail-label">Дата регистрации</div>
                            <div class="profile-detail-value"><?= date("d.m.Y в H:i", strtotime($info['created_date'])) ?></div>
                        </div>
                        <div class="profile-detail-row">
                            <div class="profile-detail-label">Дата рождения</div>
                            <div class="profile-detail-value"><?= !empty($info['dob']) ? date("d.m.Y", strtotime($info['dob'])) : 'Не указана' ?></div>
                        </div>
                        <div class="profile-detail-row">
                            <div class="profile-detail-label">Дополнительно</div>
                            <div class="profile-detail-value" style="color: var(--text-muted);">Будет добавлено позже...</div>
                        </div>
                    </div>
                </div>

                <div id="tab-custom" class="tab-content-item">
                    <p style="color: var(--text-muted); font-size: 14px;">Здесь потом можно будет разместить любую другую кастомную информацию (например, подпись, контакты или награды).</p>
                </div>

            </div>

        </main>

        <footer class="footer">
            <div class="container footer-container">
                <div class="footer-left">
                    <button class="footer-btn">⤢</button>
                    <span class="footer-badge">Samp-Rp</span>
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

    <script>
    function openTab(evt, tabId) {
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tab-content-item");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].classList.remove("active");
        }
        tablinks = document.getElementsByClassName("profile-tab-btn");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].classList.remove("active");
        }
        document.getElementById(tabId).classList.add("active");
        evt.currentTarget.classList.add("active");
    }
    </script>
</body>
</html>
