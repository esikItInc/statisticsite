<?php
session_start();
$connect = new mysqli('10.0.0.11', 'stat_user', 'statpass123', 'statistic');
if ($connect->connect_error) die("Ошибка подключения");

// Только для администратора
if ($_SESSION['user']['role'] !== 'admin') {
    header("Location: work_schedule.php");
    exit;
}

// Удаление пользователя через prepared statement
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id !== $_SESSION['user']['id']) {
        $stmt = $connect->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    header("Location: user_admin.php");
    exit;
}

// Получение отделов
$departments = $connect->query("SELECT id, name FROM departments ORDER BY name");

// Добавление нового пользователя
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $department_id = $_POST['department_id'] ?: null;

    // Проверка на пустые значения
    if (empty($login) || empty($password) || !in_array($role, ['user', 'manager', 'admin'])) {
        $errors[] = "Пожалуйста, заполните все поля корректно.";
    }

    // Проверка уникальности логина
    $check = $connect->prepare("SELECT id FROM users WHERE login = ?");
    $check->bind_param("s", $login);
    $check->execute();
    $check_result = $check->get_result();
    if ($check_result->num_rows > 0) {
        $errors[] = "Пользователь с таким логином уже существует.";
    }

    if (empty($errors)) {
        $password_hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $connect->prepare("INSERT INTO users (login, password, role, department_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $login, $password_hashed, $role, $department_id);
        $stmt->execute();
        header("Location: user_admin.php");
        exit;
    }
}

// Получение всех пользователей
$users = $connect->query("
    SELECT users.*, departments.name AS department_name 
    FROM users 
    LEFT JOIN departments ON users.department_id = departments.id 
    ORDER BY users.login
");
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Администрирование пользователей</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <h2 class="mb-4">Администрирование пользователей</h2>

    <!-- Форма добавления -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">Добавить пользователя</div>
        <div class="card-body">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $e): ?>
                        <div><?= htmlspecialchars($e) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Логин:</label>
                    <input type="text" name="login" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Пароль:</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Роль:</label>
                    <select name="role" class="form-select" required>
                        <option value="user">Пользователь</option>
                        <option value="manager">Менеджер</option>
                        <option value="admin">Администратор</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Отдел:</label>
                    <select name="department_id" class="form-select">
                        <option value="">— не выбрано —</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-success">Добавить</button>
            </form>
        </div>
    </div>

    <!-- Таблица пользователей -->
    <div class="card">
        <div class="card-header bg-secondary text-white">Список пользователей</div>
        <div class="card-body p-0">
            <table class="table table-striped table-bordered mb-0">
                <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Логин</th>
                    <th>Роль</th>
                    <th>Отдел</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                <?php while ($user = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td><?= htmlspecialchars($user['login']) ?></td>
                        <td><?= $user['role'] ?></td>
                        <td><?= htmlspecialchars($user['department_name'] ?? '—') ?></td>
                        <td>
                            <?php if ($user['id'] != $_SESSION['user']['id']): ?>
                                <a href="?delete=<?= $user['id'] ?>" class="btn btn-sm btn-danger"
                                   onclick="return confirm('Удалить пользователя?')">Удалить</a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
