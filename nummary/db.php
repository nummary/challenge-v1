<?php 
	$dsn = "mysql:host=localhost;dbname=u3469140_avantura-db;charset=utf8mb4";
	$options = [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
	];

	try {
		$pdo = new PDO($dsn, "u3469140_admin", "yT2iS6sR0frM7aZ4", $options);
	} catch (PDOException $e) {
		die("Ошибка: " . $e->getMessage());
	}