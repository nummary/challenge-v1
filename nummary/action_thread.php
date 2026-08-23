<?php
session_start();
require_once "db.php";

if (isset($_SESSION['uid'])) {

    if (!in_array($_SESSION['role'], ['mod', 'admin'])) {
        die("Доступ запрещен!");
    }

    $action = $_GET['action'] ?? '';
    $tid = (int)($_GET['id'] ?? 0);

    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM threads WHERE `threads`.`tid` = :id");
        $stmt->execute(['id' => $tid]);
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    if ($action === 'pin') {
        $stmt = $pdo->prepare("UPDATE `threads` SET `is_pinned` = NOT `is_pinned` WHERE `tid` = :id");
        $stmt->execute(['id' => $tid]);
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

} else {
    header("Location: index.php");
}

