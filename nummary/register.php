<?php
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$username = $_POST['username'];
		$email = $_POST['email'];
		$password = $_POST['password'];
		$dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
		$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

		require_once 'db.php';

		$sql = "INSERT INTO `users` (`username`, `email`, `pass`, `role`, `dob`) VALUES (:username, :email, :password, 'user', :dob)";

		$pdo->prepare($sql)->execute([
			'username' => $username,
			'email' => $email,
			'password' => $hashedPassword,
			'dob' => $dob
		]);

		header('Location: /index.php');
	}