<?php
require_once '../auth.php';

$connect = new mysqli('10.0.0.11', 'stat_user', 'statpass123', 'statistic');
if ($connect->connect_error) {
    http_response_code(500);
    die("Ошибка подключения к БД");
}

$employee_id = isset($_POST['employee_id']) ? (int)$_POST['employee_id'] : 0;
$date = $_POST['date'] ?? '';
$comment = trim($_POST['comment'] ?? '');

$role = $_SESSION['user']['role'];
$current_user_id = $_SESSION['user']['id'];
$current_department_id = $_SESSION['user']['department_id'] ?? null;

// Проверка на обязательные данные
if (!$employee_id || !$date) {
    http_response_code(400);
    die("Некорректные данные");
}

// Админ — всегда может
if ($role === 'admin') {
    $canEdit = true;
}
// Менеджер — только сотрудников своего отдела
elseif ($role === 'manager') {
    $stmt = $connect->prepare("SELECT department_id FROM employees WHERE id = ?");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $emp = $res->fetch_assoc();
    $canEdit = ($emp && $emp['department_id'] == $current_department_id);
}
// Пользователь — только себя
else {
    $canEdit = ($employee_id === $current_user_id);
}

// Проверка прав
if (!$canEdit) {
    http_response_code(403);
    die("Нет прав на редактирование");
}

// Проверка наличия записи
$stmt = $connect->prepare("SELECT id FROM work_schedule WHERE employee_id = ? AND date = ?");
$stmt->bind_param("is", $employee_id, $date);
$stmt->execute();
$res = $stmt->get_result();

if ($res && $res->num_rows > 0) {
    // Обновление
    $stmt = $connect->prepare("UPDATE work_schedule SET comment = ? WHERE employee_id = ? AND date = ?");
    $stmt->bind_param("sis", $comment, $employee_id, $date);
} else {
    // Вставка
    $stmt = $connect->prepare("INSERT INTO work_schedule (employee_id, date, comment) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $employee_id, $date, $comment);
}

if (!$stmt->execute()) {
    http_response_code(500);
    die("Ошибка при сохранении");
}
