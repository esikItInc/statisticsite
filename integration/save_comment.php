<?php
session_start();
$connect = new mysqli('10.0.0.11', 'stat_user', 'statpass123', 'statistic');
if ($connect->connect_error) die("Ошибка БД");

$user_id = (int)$_POST['user_id'];
$date = $_POST['date'];
$comment = $_POST['comment'] ?? '';
$role = $_SESSION['user']['role'];
$current_user_id = $_SESSION['user']['id'];

if ($role !== 'manager' && $user_id !== $current_user_id) {
    http_response_code(403);
    die("Нет прав");
}

$stmt = $connect->prepare("SELECT id FROM work_schedule WHERE user_id = ? AND date = ?");
$stmt->bind_param("is", $user_id, $date);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $stmt = $connect->prepare("UPDATE work_schedule SET comment = ? WHERE user_id = ? AND date = ?");
    $stmt->bind_param("sis", $comment, $user_id, $date);
} else {
    $stmt = $connect->prepare("INSERT INTO work_schedule (user_id, date, comment) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $date, $comment);
}

$stmt->execute();

