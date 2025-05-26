<?php require_once '../auth.php';

$connect = new mysqli('10.0.0.11', 'stat_user', 'statpass123', 'statistic');
if ($connect->connect_error) die("Ошибка подключения");

if ($_SESSION['user']['role'] !== 'admin') {
    header("Location: work_schedule.php");
    exit;
}

// Удаление пользователя
if (isset($_GET['delete_user'])) {
    $id = (int)$_GET['delete_user'];
    if ($id !== $_SESSION['user']['id']) {
        $stmt = $connect->prepare("UPDATE users SET is_deleted = 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    header("Location: user_admin.php?view=users");
    exit;
}

// Удаление сотрудника
if (isset($_GET['delete_employee'])) {
    $id = (int)$_GET['delete_employee'];
    $stmt = $connect->prepare("DELETE FROM employees WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: user_admin.php?view=employees");
    exit;
}

// Получение данных
$departments = $connect->query("SELECT id, name FROM departments ORDER BY name");
$positions = $connect->query("SELECT id, name FROM positions ORDER BY name");

$errors = [];
$edit_user = null;
$edit_employee = null;

$view = $_GET['view'] ?? 'users';
$edit_id = $_GET['edit'] ?? null;
$is_edit_user = $view === 'users' && $edit_id;
$is_edit_employee = $view === 'employees' && $edit_id;

if ($is_edit_user) {
    $stmt = $connect->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_user = $stmt->get_result()->fetch_assoc();
}

if ($is_edit_employee) {
    $stmt = $connect->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_employee = $stmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($view === 'users') {
        $login = trim($_POST['login']);
        $role = $_POST['role'];
        $department_id = $_POST['department_id'] ?: null;

        if (empty($login) || !in_array($role, ['user', 'manager', 'admin'])) {
            $errors[] = "Введите логин и выберите корректную роль.";
        }

        if (empty($errors)) {
            if ($is_edit_user) {
                $stmt = $connect->prepare("UPDATE users SET login = ?, role = ?, department_id = ? WHERE id = ?");
                $stmt->bind_param("ssii", $login, $role, $department_id, $edit_id);
                $stmt->execute();
            } else {
                $password = $_POST['password'];
                if (empty($password)) $errors[] = "Введите пароль.";
                $password_hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $connect->prepare("INSERT INTO users (login, password, role, department_id) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sssi", $login, $password_hashed, $role, $department_id);
                $stmt->execute();
            }
            header("Location: user_admin.php?view=users");
            exit;
        }
    }

    if ($view === 'employees') {
        $full_name = trim($_POST['full_name']);
        $department_id = $_POST['department_id'] ?: null;
        $position_id = $_POST['position_id'] ?: null;

        if (empty($full_name)) {
            $errors[] = "Введите ФИО сотрудника.";
        }

        if (empty($errors)) {
            if ($is_edit_employee) {
                $stmt = $connect->prepare("UPDATE employees SET full_name = ?, department_id = ?, position_id = ? WHERE id = ?");
                $stmt->bind_param("siii", $full_name, $department_id, $position_id, $edit_id);
                $stmt->execute();
            } else {
                $stmt = $connect->prepare("INSERT INTO employees (full_name, department_id, position_id) VALUES (?, ?, ?)");
                $stmt->bind_param("sii", $full_name, $department_id, $position_id);
                $stmt->execute();
            }

            header("Location: user_admin.php?view=employees");
            exit;
        }
    }
}

$users = $connect->query("
    SELECT users.*, departments.name AS department_name 
    FROM users 
    LEFT JOIN departments ON users.department_id = departments.id 
    WHERE users.is_deleted = 0
    ORDER BY users.login
");

$employees = $connect->query("
    SELECT employees.*, departments.name AS department_name, positions.name AS position_name 
    FROM employees 
    LEFT JOIN departments ON employees.department_id = departments.id 
    LEFT JOIN positions ON employees.position_id = positions.id
    ORDER BY employees.full_name
");
?>
<!-- HTML-разметка остается без изменений -->

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Администрирование</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <h2 class="mb-4">Администрирование</h2>
    <a href="/statistic.php" class="btn btn-primary mb-3">🏠 Вернуться на главную</a>

    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $view === 'users' ? 'active' : '' ?>" href="?view=users">Пользователи</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $view === 'employees' ? 'active' : '' ?>" href="?view=employees">Сотрудники</a>
        </li>
    </ul>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?>
                <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <?= $is_edit_user || $is_edit_employee ? 'Редактировать' : 'Добавить' ?> <?= $view === 'users' ? 'пользователя' : 'сотрудника' ?>
        </div>
        <div class="card-body">
            <form method="post">
                <?php if ($view === 'users'): ?>
                    <div class="mb-3">
                        <label class="form-label">Логин:</label>
                        <input type="text" name="login" class="form-control" required value="<?= htmlspecialchars($edit_user['login'] ?? '') ?>">
                    </div>
                    <?php if (!$is_edit_user): ?>
                        <div class="mb-3">
                            <label class="form-label">Пароль:</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Роль:</label>
                        <select name="role" class="form-select" required>
                            <option value="user" <?= ($edit_user['role'] ?? '') === 'user' ? 'selected' : '' ?>>Пользователь</option>
                            <option value="manager" <?= ($edit_user['role'] ?? '') === 'manager' ? 'selected' : '' ?>>Менеджер</option>
                            <option value="admin" <?= ($edit_user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Администратор</option>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="mb-3">
                        <label class="form-label">ФИО:</label>
                        <input type="text" name="full_name" class="form-control" required value="<?= htmlspecialchars($edit_employee['full_name'] ?? '') ?>">
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label">Отдел:</label>
                    <select name="department_id" class="form-select">
                        <option value="">— не выбрано —</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>"
                                <?= (($edit_user['department_id'] ?? null) === $dept['id'] || ($edit_employee['department_id'] ?? null) === $dept['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($view === 'employees'): ?>
                    <div class="mb-3">
                        <label class="form-label">Должность:</label>
                        <select name="position_id" class="form-select">
                            <option value="">— не выбрано —</option>
                            <?php foreach ($positions as $pos): ?>
                                <option value="<?= $pos['id'] ?>" <?= (($edit_employee['position_id'] ?? null) == $pos['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pos['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-success"><?= $is_edit_user || $is_edit_employee ? 'Сохранить' : 'Добавить' ?></button>
                <?php if ($is_edit_user || $is_edit_employee): ?>
                    <a href="user_admin.php?view=<?= $view ?>" class="btn btn-secondary">Отмена</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-secondary text-white">Список <?= $view === 'users' ? 'пользователей' : 'сотрудников' ?></div>
        <div class="card-body p-0">
            <table class="table table-striped table-bordered mb-0">
                <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th><?= $view === 'users' ? 'Логин' : 'ФИО' ?></th>
                    <?php if ($view === 'users'): ?>
                        <th>Роль</th>
                    <?php else: ?>
                        <th>Должность</th>
                    <?php endif; ?>
                    <th>Отдел</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($view === 'users'): ?>
                    <?php while ($user = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><?= htmlspecialchars($user['login']) ?></td>
                            <td><?= $user['role'] ?></td>
                            <td><?= htmlspecialchars($user['department_name'] ?? '—') ?></td>
                            <td>
                                <?php if ($user['id'] != $_SESSION['user']['id']): ?>
                                    <a href="?view=users&edit=<?= $user['id'] ?>" class="btn btn-sm btn-warning">Редактировать</a>
                                    <a href="?view=users&delete_user=<?= $user['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Удалить пользователя?')">Удалить</a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <?php while ($emp = $employees->fetch_assoc()): ?>
                        <tr>
                            <td><?= $emp['id'] ?></td>
                            <td><?= htmlspecialchars($emp['full_name']) ?></td>
                            <td><?= htmlspecialchars($emp['position_name'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($emp['department_name'] ?? '—') ?></td>
                            <td>
                                <a href="?view=employees&edit=<?= $emp['id'] ?>" class="btn btn-sm btn-warning">Редактировать</a>
                                <a href="?view=employees&delete_employee=<?= $emp['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Удалить сотрудника?')">Удалить</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>

