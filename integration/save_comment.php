<?php

require_once '../auth.php';


$connect = new mysqli('10.0.0.11', 'stat_user', 'statpass123', 'statistic');
if ($connect->connect_error) die("Ошибка БД");

$employee_id = (int)$_POST['employee_id'];
$date = $_POST['date'];
$comment = $_POST['comment'] ?? '';
$role = $_SESSION['user']['role'];
$current_user_id = $_SESSION['user']['id'];

// Безопасность: только сам сотрудник, менеджер или админ
if ($role !== 'manager' && $role !== 'admin' && $employee_id !== $current_user_id) {
    http_response_code(403);
    die("Нет прав");
}

// Проверяем наличие записи
$stmt = $connect->prepare("SELECT id FROM work_schedule WHERE employee_id = ? AND date = ?");
$stmt->bind_param("is", $employee_id, $date);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Обновление
    $stmt = $connect->prepare("UPDATE work_schedule SET comment = ? WHERE employee_id = ? AND date = ?");
    $stmt->bind_param("sis", $comment, $employee_id, $date);
} else {
    // Вставка
    $stmt = $connect->prepare("INSERT INTO work_schedule (employee_id, date, comment) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $employee_id, $date, $comment);
}

$stmt->execute();

