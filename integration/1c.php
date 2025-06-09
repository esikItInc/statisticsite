<?php
ini_set("soap.wsdl_cache_enabled", "0");

require_once '../auth.php'; // если не нужен доступ сессии — можно удалить

// Простой метод для проверки связи (аналог HelloWorld)
function HelloWorld() {
    return "Привет из PHP SOAP!";
}

// Метод для приёма данных от 1С
function AddSchedule($date, $time_start, $time_end, $cabinet_number, $specialist_name) {
    // Подключение к БД
    $connect = new mysqli('10.0.0.11', 'stat_user', 'statpass123', 'statistic');
    if ($connect->connect_error) {
        return "Ошибка БД: " . $connect->connect_error;
    }

    $stmt = $connect->prepare("
        INSERT INTO schedule_import (date, time_start, time_end, cabinet_number, specialist_name)
        VALUES (?, ?, ?, ?, ?)
    ");
    if (!$stmt) return "Ошибка подготовки запроса: " . $connect->error;

    $stmt->bind_param("sssss", $date, $time_start, $time_end, $cabinet_number, $specialist_name);
    if ($stmt->execute()) {
        return "Успешно добавлено";
    } else {
        return "Ошибка при добавлении: " . $stmt->error;
    }
}

$server = new SoapServer(null, [
    'uri' => "http://statistic2/integration/1c.php"
]);

$server->addFunction(["HelloWorld", "AddSchedule"]);

$server->handle();

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>
<body>
<a href="/statistic.php" style="display:inline-block; margin-bottom:20px; background:#0078D4; color:#fff; padding:8px 16px; text-decoration:none; border-radius:4px;">🏠 Вернуться на главную</a>
</body>
</html>
