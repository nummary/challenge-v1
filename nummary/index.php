<?php
// ОБЯЗАТЕЛЬНО запускаем сессию на первой строчке файла
session_start();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Форум</title>
</head>
<body>

    <h1>Форум</h1>
    <h2>Главная страница форума</h2>

    <?php if (isset($_SESSION['uid'])): ?>
        
        <p>Привет, <b><a href="profile.php?id=<?= $_SESSION['username'] ?>"><?= htmlspecialchars($_SESSION['username']) ?></a></b>!</p>
        <?php
        
        require_once "db.php";
        
        $stmt = $pdo->query("SELECT `sid`, `name` FROM `sections` WHERE `parent_id` IS NULL");
        $razdely = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->query("SELECT `sid`, `parent_id`, `name`, `description`  FROM `sections` WHERE `parent_id` IS NOT NULL");
        $podrazdely = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($razdely as $row) {
            $now = $row['sid'];
            ?>
            <h1><?php echo $row['name'] . "</br>"?></h1>
            <?php
            foreach ($podrazdely as $rowy) {
                if ($rowy['parent_id'] == $now) {
                    ?>
                    <h2><a href='/section.php?id=<?php echo $rowy['sid'] ?>'><?php echo $rowy['name'] . "</br>"?></a></h2>
                    <?php
                }
            }
        }
        ?>
        
        <form action="logout.php" method="POST">
            <button type="submit">Выйти</button>
        </form>

    <?php else: ?>
        
        <a href="register.html"><button>Регистрация</button></a><br>
        <a href="login.html"><button>Войти</button></a>

    <?php endif; ?>

</body>
</html>