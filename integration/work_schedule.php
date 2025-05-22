<?php
session_start();

// Подключение к БД
$connect = new mysqli('10.0.0.11', 'stat_user', 'statpass123', 'statistic');
if ($connect->connect_error) die("Ошибка подключения");

$role = $_SESSION['user']['role'];
$is_manager = ($role === 'manager');
$is_admin = ($role === 'admin');
$current_user_id = $_SESSION['user']['id'];

// Получение параметров
$start = $_GET['start'] ?? date('Y-m-01');
$end   = $_GET['end'] ?? date('Y-m-t');

if ($is_manager || $is_admin) {
    $department_id = $_GET['department_id'] ?? '';
    $department_id = is_numeric($department_id) ? (int)$department_id : null;
} else {
    $department_id = null;
}

// Проверка корректности даты
$start_date = new DateTime($start);
$end_date = new DateTime($end);
$dates = [];
$date_error = false;

if ($start_date > $end_date) {
    $date_error = true;
} else {
    while ($start_date <= $end_date) {
        $dates[] = $start_date->format('Y-m-d');
        $start_date->modify('+1 day');
    }
}

// Получение списка пользователей
if ($is_manager || $is_admin) {
    if ($department_id) {
        $stmt = $connect->prepare("SELECT id, login FROM users WHERE department_id = ? ORDER BY login");
        $stmt->bind_param("i", $department_id);
        $stmt->execute();
        $users = $stmt->get_result();
    } else {
        $users = $connect->query("SELECT id, login FROM users ORDER BY login");
    }
} else {
    $stmt = $connect->prepare("SELECT id, login FROM users WHERE id = ?");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $users = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Табель — <?= !$date_error && count($dates) ? strftime('%B %Y', strtotime($dates[0])) : '' ?></title>
    <style>
        table { border-collapse: collapse; width: 100%; font-size: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: center; }
        th.sticky, td.sticky { position: sticky; left: 0; background: #f0f0f0; z-index: 1; }
        td.editable { background: #f9f9f9; cursor: pointer; }
    </style>
</head>
<body>

<h2>
    <?php if ($date_error): ?>
        <span style="color:red;">Некорректно выбраны даты</span>
    <?php else: ?>
        Табель — <?= strftime('%B %Y', strtotime($dates[0])) ?>
    <?php endif; ?>
</h2>

<!-- Форма фильтрации -->
<form method="get">
    Период:
    <input type="date" name="start" value="<?= htmlspecialchars($_GET['start'] ?? '') ?>">
    —
    <input type="date" name="end" value="<?= htmlspecialchars($_GET['end'] ?? '') ?>">

    <?php if ($is_manager || $is_admin): ?>
        Отдел:
        <select name="department_id">
            <option value="">Все</option>
            <?php
            $departments = $connect->query("SELECT id, name FROM departments ORDER BY name");
            while ($dept = $departments->fetch_assoc()):
                $selected = ($_GET['department_id'] ?? '') == $dept['id'] ? 'selected' : '';
                ?>
                <option value="<?= $dept['id'] ?>" <?= $selected ?>><?= htmlspecialchars($dept['name']) ?></option>
            <?php endwhile; ?>
        </select>
    <?php endif; ?>
    <button type="submit">Показать</button>
</form>

<?php if (!$date_error): ?>
    <table>
        <tr>
            <th class="sticky">Сотрудник</th>
            <?php foreach ($dates as $date): ?>
                <th><?= date('d.m', strtotime($date)) ?></th>
            <?php endforeach; ?>
        </tr>

        <?php while ($user = $users->fetch_assoc()): ?>
            <tr>
                <td class="sticky"><?= htmlspecialchars($user['login']) ?></td>
                <?php foreach ($dates as $date):
                    $comment = '';
                    $stmt = $connect->prepare("SELECT comment FROM work_schedule WHERE user_id = ? AND date = ?");
                    $stmt->bind_param("is", $user['id'], $date);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        $comment = $row['comment'];
                    }
                    $stmt->close();
                    ?>
                    <td class="editable" data-user="<?= $user['id'] ?>" data-date="<?= $date ?>">
                        <?= htmlspecialchars($comment ?? '') ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endwhile; ?>
    </table>
<?php endif; ?>

<!-- JS-редактирование -->
<script>
    document.querySelectorAll('.editable').forEach(cell => {
        const currentUserId = <?= json_encode($_SESSION['user']['id']) ?>;
        const userRole = <?= json_encode($_SESSION['user']['role']) ?>;
        const cellUserId = parseInt(cell.dataset.user);
        const cellDate = cell.dataset.date;

        const canEdit = (userRole === 'admin' || userRole === 'manager' || cellUserId === currentUserId);
        if (!canEdit) return;

        cell.addEventListener('click', () => {
            const original = cell.textContent.trim();
            const input = document.createElement('input');
            input.type = 'text';
            input.value = original;
            input.style.width = '100%';

            cell.innerHTML = '';
            cell.appendChild(input);
            input.focus();

            const saveComment = () => {
                const comment = input.value;

                fetch('save_comment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `user_id=${cellUserId}&date=${cellDate}&comment=${encodeURIComponent(comment)}`
                }).then(() => {
                    cell.textContent = comment;
                });
            };

            input.addEventListener('blur', saveComment);

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    input.blur();
                }
            });
        });
    });
</script>

</body>
</html>

