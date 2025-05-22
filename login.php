<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $connect = new mysqli('10.0.0.11', 'stat_user', 'statpass123', 'statistic');

    if ($connect->connect_error) {
        echo "<h1>❌ Ошибка подключения к базе данных.</h1>";
        exit;
    }

    $login = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($login) || empty($password)) {
        echo "<h1>❌ Логин и пароль обязательны.</h1>";
        exit;
    }

    $stmt = $connect->prepare("SELECT * FROM users WHERE login = ?");
    $stmt->bind_param("s", $login);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Проверка пароля с использованием password_verify
        if (password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user;
            header("Location: statistic.php");
            exit;
        } else {
            echo "<h1>❌ Неверный пароль.</h1>";
            exit;
        }
    } else {
        echo "<h1>❌ Пользователь не найден.</h1>";
        exit;
    }

    $stmt->close();
    $connect->close();
} else {
    header("Location: index.html");
    exit;
}

