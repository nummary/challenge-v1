<?php
session_start();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Форум</title>
</head>
<<body>

    <h1>Форум</h1>
    <h2>Главная страница форума</h2>

    <?php if (isset($_SESSION['uid'])): ?>
        
        <p>Привет, <b><?= htmlspecialchars($_SESSION['username']) ?></b>!</p>
        <?php
        
        require_once "db.php";
        
        if (isset($_GET['id'])) {
            // Превращаем входящую строку строго в целое число
            $section_id = (int)$_GET['id']; 
        } else {
            $section_id = 1; // Значение по умолчанию, если id не передан
        }
        
        $stmt = $pdo->query("SELECT `sid`, `name` FROM `sections` WHERE `parent_id` = $section_id");
        
        $podrazdely = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($podrazdely as $row) {
            $now = $row['sid'];
            echo $row['name'] . "<br>";
        }
        ?>
        
        <form action="logout.php" method="POST">
            <button type="submit">Выйти</button>
        </form>
        <form action="index.php" method="POST">
            <button type="submit">На главную</button>
        </form>
        

    <?php else: ?>
        
        <a href="register.html"><button>Регистрация</button></a><br>
        <a href="login.html"><button>Войти</button></a>

    <?php endif; ?>

</body>
</html>