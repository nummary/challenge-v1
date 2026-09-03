<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['uid']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$users_count = $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
$threads_count = $pdo->query("SELECT COUNT(*) FROM `threads`")->fetchColumn();
$comments_count = $pdo->query("SELECT COUNT(*) FROM `comments`")->fetchColumn();
$requests_count = $pdo->query("SELECT COUNT(*) FROM `registration_requests`")->fetchColumn();

$message = '';
$error = '';

if (isset($_GET['action']) && $_GET['action'] === 'delete_section' && isset($_GET['sec_id'])) {
    $sec_id = (int)$_GET['sec_id'];
    
    $check_main = $pdo->prepare("SELECT `parent_id` FROM `sections` WHERE `sid` = :id");
    $check_main->execute(['id' => $sec_id]);
    $sec_type = $check_main->fetch(PDO::FETCH_ASSOC);

    if ($sec_type) {
        if ($sec_type['parent_id'] === null) {
            $del_subs = $pdo->prepare("DELETE FROM `sections` WHERE `parent_id` = :id");
            $del_subs->execute(['id' => $sec_id]);
        }
        
        $del_main = $pdo->prepare("DELETE FROM `sections` WHERE `sid` = :id");
        $del_main->execute(['id' => $sec_id]);
        
        $message = "Раздел успешно удален из структуры форума.";
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'approve' && isset($_GET['req_id'])) {
    $req_id = (int)$_GET['req_id'];
    $req_stmt = $pdo->prepare("SELECT * FROM `registration_requests` WHERE `id` = :id");
    $req_stmt->execute(['id' => $req_id]);
    $request_data = $req_stmt->fetch(PDO::FETCH_ASSOC);

    if ($request_data) {
        $ins_user = $pdo->prepare("
            INSERT INTO `users` (`uid`, `username`, `email`, `pass`, `role`, `dob`, `created_date`) 
            VALUES (NULL, :user, :email, :pass, 'user', :dob, CURRENT_TIMESTAMP)
        ");
        $ins_user->execute([
            'user' => $request_data['username'],
            'email' => $request_data['email'],
            'pass' => $request_data['password_hash'],
            'dob' => $request_data['dob']
        ]);

        $del_req = $pdo->prepare("DELETE FROM `registration_requests` WHERE `id` = :id");
        $del_req->execute(['id' => $req_id]);
        $message = "Заявка «" . htmlspecialchars($request_data['username']) . "» одобрена!";
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'reject' && isset($_GET['req_id'])) {
    $req_id = (int)$_GET['req_id'];
    $del_req = $pdo->prepare("DELETE FROM `registration_requests` WHERE `id` = :id");
    $del_req->execute(['id' => $req_id]);
    $message = "Заявка отклонена.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_user'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role = $_POST['role'];

        if (!empty($username) && !empty($email) && !empty($password)) {
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `username` = :user OR `email` = :email");
            $check_stmt->execute(['user' => $username, 'email' => $email]);
            
            if ($check_stmt->fetchColumn() > 0) {
                $error = "Пользователь с таким логином или почтой уже существует!";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $insert_stmt = $pdo->prepare("
                    INSERT INTO `users` (`uid`, `username`, `email`, `pass`, `role`, `created_date`) 
                    VALUES (NULL, :user, :email, :pass, :role, CURRENT_TIMESTAMP)
                ");
                $insert_stmt->execute(['user' => $username, 'email' => $email, 'pass' => $hashed_password, 'role' => $role]);
                $message = "Пользователь успешно создан!";
            }
        } else {
            $error = "Заполните все обязательные поля!";
        }
    }

    if (isset($_POST['create_main_section'])) {
        $sec_name = trim($_POST['sec_name']);
        if (!empty($sec_name)) {
            $ins_sec = $pdo->prepare("INSERT INTO `sections` (`sid`, `parent_id`, `name`, `description`) VALUES (NULL, NULL, :name, NULL)");
            $ins_sec->execute(['name' => $sec_name]);
            $message = "Главный раздел успешно создан!";
        } else {
            $error = "Введите название раздела!";
        }
    }

    if (isset($_POST['create_sub_section'])) {
        $sub_name = trim($_POST['sub_name']);
        $parent_id = (int)$_POST['parent_id'];
        $desc = trim($_POST['description']);

        if (!empty($sub_name) && $parent_id > 0) {
            $ins_sub = $pdo->prepare("INSERT INTO `sections` (`sid`, `parent_id`, `name`, `description`) VALUES (NULL, :parent, :name, :desc)");
            $ins_sub->execute(['parent' => $parent_id, 'name' => $sub_name, 'desc' => !empty($desc) ? $desc : null]);
            $message = "Подраздел добавлен!";
        } else {
            $error = "Заполните все поля!";
        }
    }
}

// --- БЛОК 1: Обработка изменения роли (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    $target_uid = (int)$_POST['target_uid'];
    $new_role = $_POST['new_role'];
    $allowed_roles = ['user', 'moderator', 'admin'];

    if ($target_uid > 0 && in_array($new_role, $allowed_roles)) {
        if ($target_uid === (int)$_SESSION['uid'] && $new_role !== 'admin') {
            $error = "Вы не можете понизить в роли самого себя!";
        } else {
            $update_role = $pdo->prepare("UPDATE `users` SET `role` = :role WHERE `uid` = :uid");
            $update_role->execute(['role' => $new_role, 'uid' => $target_uid]);
            $message = "Роль пользователя успешно обновлена!";
        }
    } else {
        $error = "Некорректные данные для смены роли!";
    }
}

// --- БЛОК 2: Получение списка пользователей (с учетом поиска) ---
// Проверяем поиск как в GET (когда нажали кнопку поиска), так и в POST (когда поменяли роль внутри поиска)
$search_query = '';
if (isset($_GET['user_search'])) {
    $search_query = trim($_GET['user_search']);
} elseif (isset($_POST['user_search_hidden'])) {
    $search_query = trim($_POST['user_search_hidden']);
}

if (!empty($search_query)) {
    // Если мы искали пользователя, выводим результаты поиска
    $search_stmt = $pdo->prepare("SELECT `uid`, `username`, `email`, `role` FROM `users` WHERE `username` LIKE :search ORDER BY `uid` DESC");
    $search_stmt->execute(['search' => "%$search_query%"]);
    $users_list = $search_stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Если поиска нет, выводим последние 10, как обычно
    $users_list = $pdo->query("SELECT `uid`, `username`, `email`, `role` FROM `users` ORDER BY `uid` DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
}



$requests_list = $pdo->query("SELECT `id`, `username`, `email`, `dob` FROM `registration_requests` ORDER BY `id` ASC")->fetchAll(PDO::FETCH_ASSOC);

$main_sections = $pdo->query("SELECT `sid`, `name` FROM `sections` WHERE `parent_id` IS NULL ORDER BY `sid` ASC")->fetchAll(PDO::FETCH_ASSOC);
$all_sub_sections = $pdo->query("SELECT s1.`sid`, s1.`name`, s2.`name` AS `parent_name` FROM `sections` s1 LEFT JOIN `sections` s2 ON s1.`parent_id` = s2.`sid` WHERE s1.`parent_id` IS NOT NULL ORDER BY s1.`parent_id` ASC, s1.`sid` ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель администратора — AVANTURA</title>
    <link rel="stylesheet" href="/css/main.css">
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body>
    <div class="wrapper">

        <header class="sticky-header">
            <div class="container header-container">
                <a href="index.php" class="logo-link">
                    <span class="logo">AVANTURA <span style="font-size: 12px; color: var(--color-primary);">ADMIN</span></span>
                </a>
                <div class="header-right">
                    <div class="user-panel">
                        <a href="profile.php?id=<?= urlencode($_SESSION['username']) ?>" class="username-link">
                            <span class="username-display">👑 <?= htmlspecialchars($_SESSION['username']) ?></span>
                        </a>
                        <form action="logout.php" method="POST" class="inline-logout-form">
                            <button type="submit" class="logout-door-btn" title="Выйти">🚪</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>
        <div class="header-spacer"></div>

        <div class="container" style="margin-top: 10px; margin-bottom: -10px;">
            <?php if(!empty($message)): ?>
                <div class="admin-alert success"><?= $message ?></div>
            <?php endif; ?>
            <?php if(!empty($error)): ?>
                <div class="admin-alert danger"><?= $error ?></div>
            <?php endif; ?>
        </div>

        <main class="container admin-container" style="flex: 1;">
            
            <aside class="admin-sidebar">
                <button class="sidebar-link active" onclick="openAdminTab(event, 'adm-dashboard')">📊 Сводка панели</button>
                <button class="sidebar-link" onclick="openAdminTab(event, 'adm-requests')">📩 Заявки (<?= $requests_count ?>)</button>
                <button class="sidebar-link" onclick="openAdminTab(event, 'adm-users')">👥 Пользователи</button>
                <button class="sidebar-link" onclick="openAdminTab(event, 'adm-sections')">📂 Разделы форума</button>
                <a href="index.php" class="sidebar-link" style="margin-top: auto; border: 1px dashed rgba(107,156,255,0.2); text-align:center;">← На форум</a>
            </aside>

            <section class="admin-content">
                
                <div id="adm-dashboard" class="admin-tab-content active">
                    <div class="admin-stats-grid">
                        <div class="stat-box">
                            <h3>Заявки на вход</h3>
                            <div class="stat-number" style="color: var(--color-primary);"><?= $requests_count ?></div>
                        </div>
                        <div class="stat-box">
                            <h3>Всего участников</h3>
                            <div class="stat-number"><?= $users_count ?></div>
                        </div>
                        <div class="stat-box">
                            <h3>Всего тем / ответов</h3>
                            <div class="stat-number" style="font-size: 22px; padding-top: 6px;"><?= $threads_count ?> / <?= $comments_count ?></div>
                        </div>
                    </div>
                    
                    <div class="admin-card" style="margin-top: 20px;">
                        <h2>Добро пожаловать в панель управления!</h2>
                        <p style="color: var(--text-muted); font-size: 14px; line-height: 1.6;">Форум успешно переведен в приватный режим. Все новые заявки с формы регистрации будут отображаться во вкладке «Заявки», где вы сможете подтвердить или отклонить доступ.</p>
                    </div>
                </div>

                <div id="adm-requests" class="admin-tab-content">
                    <div class="admin-card">
                        <h2>Заявки на регистрацию</h2>
                        <div class="admin-table-wrapper">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Логин</th>
                                        <th>Почта (E-mail)</th>
                                        <th>Дата рождения</th>
                                        <th style="width: 200px; text-align: right;">Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($requests_list)): ?>
                                        <tr>
                                            <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 30px;">Новых заявок на регистрацию нет.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($requests_list as $req): ?>
                                            <tr>
                                                <td><b><?= htmlspecialchars($req['username']) ?></b></td>
                                                <td><?= htmlspecialchars($req['email']) ?></td>
                                                <td><?= !empty($req['dob']) ? $req['dob'] : 'Не указана' ?></td>
                                                <td style="text-align: right;">
                                                    <a href="admin.php?action=approve&req_id=<?= $req['id'] ?>" class="footer-btn" style="background: #14291e; border-color: #1f4d32; color: #72db9b; padding: 4px 10px; border-radius: 4px; font-size: 12px; margin-right: 5px;">Одобрить</a>
                                                    <a href="admin.php?action=reject&req_id=<?= $req['id'] ?>" class="footer-btn" style="background: #291415; border-color: #4d1f21; color: #db7274; padding: 4px 10px; border-radius: 4px; font-size: 12px;" onclick="return confirm('Отклонить заявку?')">Удалить</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="adm-users" class="admin-tab-content">
                    <div class="admin-two-col-layout">
                        <div class="admin-card">
                            <h2>Создать аккаунт напрямую</h2>
                            <form action="admin.php" method="POST">
                                <label class="admin-label">Логин / Юзернейм</label>
                                <input type="text" name="username" class="admin-input" placeholder="username" required>
                                <label class="admin-label">Почта (E-mail)</label>
                                <input type="email" name="email" class="admin-input" placeholder="email@example.com" required>
                                <label class="admin-label">Пароль</label>
                                <input type="password" name="password" class="admin-input" placeholder="••••••••" required>
                                <label class="admin-label">Группа / Роль</label>
                                <select name="role" class="admin-input">
                                    <option value="user">Пользователь</option>
                                    <option value="moderatorerator">Модератор</option>
                                    <option value="admin">Администратор</option>
                                </select>
                                <button type="submit" name="create_user" class="btn-admin-submit">Зарегистрировать</button>
                            </form>
                        </div>

                        <div class="admin-card">
                            <h2>Последние участники</h2>
                            
                            <!-- Форма поиска пользователя -->
                            <form action="admin.php" method="GET" style="margin-bottom: 15px; display: flex; gap: 10px;">
                                <!-- Передаем скрытый параметр, чтобы оставаться на вкладке пользователей в некоторых системах -->
                                <input type="text" name="user_search" class="admin-input" placeholder="Введите логин пользователя..." value="<?= htmlspecialchars($search_query) ?>" style="margin: 0;">
                                <button type="submit" class="btn-admin-submit" style="width: auto; margin: 0; padding: 0 15px;">Найти</button>
                                <?php if (!empty($search_query)): ?>
                                    <a href="admin.php" class="footer-btn" style="background: #222; border-color: #444; padding: 8px 12px; border-radius: 4px; font-size: 13px; text-decoration: none; color: #fff;">Сбросить</a>
                                <?php endif; ?>
                            </form>

                            <div class="admin-table-wrapper">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Логин</th>
                                            <th>Роль</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($users_list)): ?>
                                            <tr>
                                                <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 20px;">Пользователи не найдены.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach($users_list as $u): ?>
                                                <tr>
                                                    <td>#<?= $u['uid'] ?></td>
                                                    <td><b><a href="profile.php?id=<?= urlencode($u['username']) ?>"><?= htmlspecialchars($u['username']) ?></a></b></td>
                                                    <td>
                                                        <!-- Форма быстрой смены роли -->
                                                        <form action="admin.php" method="POST" style="margin: 0; display: inline-block;">
                                                            <input type="hidden" name="target_uid" value="<?= $u['uid'] ?>">
                                                            <input type="hidden" name="change_role" value="1">
                                                            
                                                            <!-- Передаем текущий поисковый запрос, чтобы страница не сбрасывалась -->
                                                            <input type="hidden" name="user_search_hidden" value="<?= htmlspecialchars($search_query) ?>">
                                                            
                                                            <select name="new_role" class="admin-input" style="padding: 2px 6px; font-size: 12px; width: auto; margin: 0; height: auto;" onchange="this.form.submit()">
                                                                <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>Пользователь</option>
                                                                <option value="moderator" <?= $u['role'] === 'moderator' ? 'selected' : '' ?>>Модератор</option>
                                                                <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Администратор</option>
                                                            </select>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="adm-sections" class="admin-tab-content">
                    <div class="admin-two-col-layout">
                        <div class="admin-card">
                            <h2>Создать Главный раздел</h2>
                            <form action="admin.php" method="POST">
                                <label class="admin-label">Название раздела</label>
                                <input type="text" name="sec_name" class="admin-input" placeholder="Например: Раздел 1" required>
                                <button type="submit" name="create_main_section" class="btn-admin-submit" style="background-color: var(--bg-table-header);">Создать раздел</button>
                            </form>
                        </div>

                        <div class="admin-card">
                            <h2>Создать Подраздел</h2>
                            <form action="admin.php" method="POST">
                                <label class="admin-label">Родительский раздел</label>
                                <select name="parent_id" class="admin-input" required>
                                    <option value="" disabled selected>-- Выберите главный раздел --</option>
                                    <?php foreach($main_sections as $ms): ?>
                                        <option value="<?= $ms['sid'] ?>"><?= htmlspecialchars($ms['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label class="admin-label">Название подраздела</label>
                                <input type="text" name="sub_name" class="admin-input" placeholder="Например: Подраздел 1" required>
                                <label class="admin-label">Описание подраздела</label>
                                <input type="text" name="description" class="admin-input" placeholder="Текст-описание">
                                <button type="submit" name="create_sub_section" class="btn-admin-submit">Добавить подраздел</button>
                            </form>
                        </div>
                    </div>

                    <div class="admin-two-col-layout" style="margin-top: 25px;">
                        
                        <div class="admin-card">
                            <h2>Текущие Главные разделы</h2>
                            <div class="admin-table-wrapper">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Название</th>
                                            <th style="width: 100px; text-align: right;">Действие</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($main_sections)): ?>
                                            <tr><td colspan="2" style="color: var(--text-muted);">Разделов нет</td></tr>
                                        <?php else: ?>
                                            <?php foreach($main_sections as $ms): ?>
                                                <tr>
                                                    <td><b><?= htmlspecialchars($ms['name']) ?></b></td>
                                                    <td style="text-align: right;">
                                                        <a href="admin.php?action=delete_section&sec_id=<?= $ms['sid'] ?>" class="footer-btn" style="background: #291415; border-color: #4d1f21; color: #db7274; padding: 3px 8px; border-radius: 4px; font-size: 11px;" onclick="return confirm('ВНИМАНИЕ: Удаление главного раздела полностью сотрет все привязанные к нему подразделы! Продолжить?')">Удалить</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="admin-card">
                            <h2>Текущие Подразделы</h2>
                            <div class="admin-table-wrapper">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Подраздел</th>
                                            <th>Где находится</th>
                                            <th style="width: 100px; text-align: right;">Действие</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($all_sub_sections)): ?>
                                            <tr><td colspan="3" style="color: var(--text-muted);">Подразделов нет</td></tr>
                                        <?php else: ?>
                                            <?php foreach($all_sub_sections as $sub): ?>
                                                <tr>
                                                    <td><b><?= htmlspecialchars($sub['name']) ?></b></td>
                                                    <td style="color: var(--text-muted); font-size: 12px;"><?= htmlspecialchars($sub['parent_name']) ?></td>
                                                    <td style="text-align: right;">
                                                        <a href="admin.php?action=delete_section&sec_id=<?= $sub['sid'] ?>" class="footer-btn" style="background: #291415; border-color: #4d1f21; color: #db7274; padding: 3px 8px; border-radius: 4px; font-size: 11px;" onclick="return confirm('Вы уверены, что хотите удалить этот подраздел?')">Удалить</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>

            </section>
        </main>

        <footer class="footer">
            <div class="container" style="text-align: center; color: var(--text-muted);">
                Панель управления AVANTURA Engine © 2026
            </div>
        </footer>

    </div>

    <script>
        function openAdminTab(evt, tabId) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("admin-tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            tablinks = document.getElementsByClassName("sidebar-link");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }
            document.getElementById(tabId).classList.add("active");
            if (evt) {
                evt.currentTarget.classList.add("active");
            }
        }
        document.addEventListener("DOMContentLoaded", function() {
            <?php if (isset($_POST['change_role']) || isset($_GET['user_search'])): ?>
                var userBtn = document.querySelector(".sidebar-link[onclick*='adm-users']");
                if (userBtn) {
                    openAdminTab({ currentTarget: userBtn }, 'adm-users');
                }
            <?php endif; ?>
        });
    </script>
</body>
</html>
