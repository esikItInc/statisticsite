<?php
header('Content-Type: application/json');

$connect = new mysqli('10.0.0.11', 'stat_user', 'statpass123', 'statistic');
if ($connect->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка подключения к базе данных']);
    exit;
}

$department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;

// Поддержка шаблонов отдела и общих шаблонов (NULL)
$stmt = $connect->prepare("
    SELECT time_range 
    FROM time_templates 
    WHERE department_id = ? OR department_id IS NULL
    ORDER BY department_id DESC
");

$stmt->bind_param("i", $department_id);
$stmt->execute();
$result = $stmt->get_result();

$templates = [];
while ($row = $result->fetch_assoc()) {
    $templates[] = ['time_range' => $row['time_range']];
}

echo json_encode($templates);
