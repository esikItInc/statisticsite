<?php
session_start();

$connect = new mysqli('10.0.0.11', 'stat_user', 'statpass123', 'statistic');
if ($connect->connect_error) die("Ошибка подключения к БД");

$role = $_SESSION['user']['role'];
$current_user_id = $_SESSION['user']['id'];
$is_manager = ($role === 'manager');
$is_admin = ($role === 'admin');

// Даты
$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-t');

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

// Отдел
$department_id = null;
if ($is_admin || $is_manager) {
    $department_id = isset($_GET['department_id']) && is_numeric($_GET['department_id']) ? (int)$_GET['department_id'] : null;
}

// Получение сотрудников (с должностью и отделом)
if ($is_admin || $is_manager) {
    if ($department_id) {
        $stmt = $connect->prepare("
            SELECT e.id, e.full_name, e.department_id, p.name AS position
            FROM employees e
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE e.department_id = ?
            ORDER BY e.full_name
        ");
        $stmt->bind_param("i", $department_id);
        $stmt->execute();
        $employees = $stmt->get_result();
    } else {
        $employees = $connect->query("
            SELECT e.id, e.full_name, e.department_id, p.name AS position
            FROM employees e
            LEFT JOIN positions p ON e.position_id = p.id
            ORDER BY e.full_name
        ");
    }
} else {
    $stmt = $connect->prepare("
        SELECT e.id, e.full_name, e.department_id, p.name AS position
        FROM employees e
        LEFT JOIN positions p ON e.position_id = p.id
        WHERE e.id = ?
    ");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $employees = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Табель — <?= !$date_error && count($dates) ? strftime('%B %Y', strtotime($dates[0])) : '' ?></title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 20px; background: #f5f7fa; color: #333; }
        table { border-collapse: collapse; width: 100%; font-size: 13px; background: #fff; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: center; }
        th.sticky, td.sticky { position: sticky; left: 0; background: #f1f1f1; z-index: 3; }
        th { position: sticky; top: 0; background: #f0f0f0; }
        td.editable { background: #fcfcfc; cursor: pointer; }
        td.editable:hover { background: #eef5ff; }
        td[data-comment="О"] { background-color: #fdecea; }
        td[data-comment="Б"] { background-color: #fef4d2; }
        td[data-comment="П"] { background-color: #d6f5e3; }
        td[data-comment="Д"] { background-color: #e2f0fb; }
        td.today { border: 2px solid #0078D4; background: #e7f3ff !important; }
        form { margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        label { display: flex; flex-direction: column; font-size: 13px; }
        input[type="date"], select { padding: 6px; font-size: 14px; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 8px 16px; background-color: #0078D4; color: white; border: none; border-radius: 4px; }
        .btn-back { display:inline-block; margin-bottom:20px; background:#0078D4; color:#fff; padding:8px 16px; text-decoration:none; border-radius:4px; }
    </style>
</head>
<body>

<a href="/statistic.php" class="btn-back">🏠 Вернуться на главную</a>

<h2><?= $date_error ? 'Некорректно выбраны даты' : 'Табель — ' . strftime('%B %Y', strtotime($dates[0])) ?></h2>

<form method="get">
    <label>
        Период начала:
        <input type="date" name="start" value="<?= htmlspecialchars($_GET['start'] ?? '') ?>">
    </label>

    <label>
        Период окончания:
        <input type="date" name="end" value="<?= htmlspecialchars($_GET['end'] ?? '') ?>">
    </label>

    <?php if ($is_admin || $is_manager): ?>
        <label>
            Отдел:
            <select name="department_id">
                <option value="">Все</option>
                <?php
                //Выборка
                $departments = $connect->query("SELECT id, name FROM departments ORDER BY name");
                while ($dept = $departments->fetch_assoc()):
                    $selected = ($_GET['department_id'] ?? '') == $dept['id'] ? 'selected' : '';
                    ?>
                    <option value="<?= $dept['id'] ?>" <?= $selected ?>><?= htmlspecialchars($dept['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </label>
    <?php endif; ?>

    <button type="submit">Показать</button>
</form>

<?php if (!$date_error): ?>
    <table>
        <thead>
        <tr>
            <th class="sticky">Сотрудник<br><small>(должность)</small></th>
            <?php foreach ($dates as $date): ?>
                <th><?= date('d.m', strtotime($date)) ?></th>
            <?php endforeach; ?>
        </tr>
        </thead>
        <tbody>
        <?php while ($emp = $employees->fetch_assoc()): ?>
            <tr>
                <td class="sticky">
                    <?= htmlspecialchars($emp['full_name']) ?><br>
                    <small style="color:gray;"><?= htmlspecialchars($emp['position'] ?? '—') ?></small>
                </td>
                <?php
                $emp_department = $emp['department_id'] ?? 0;
                foreach ($dates as $date):
                    $comment = '';
                    $stmt = $connect->prepare("SELECT comment FROM work_schedule WHERE employee_id = ? AND date = ?");
                    if ($stmt) {
                        $stmt->bind_param("is", $emp['id'], $date);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        if ($row = $res->fetch_assoc()) {
                            $comment = $row['comment'];
                        }
                        $stmt->close();
                    }

                    $today_class = ($date === date('Y-m-d')) ? 'today' : '';
                    ?>
                    <td class="editable <?= $today_class ?>"
                        data-user="<?= $emp['id'] ?>"
                        data-date="<?= $date ?>"
                        data-department="<?= $emp_department ?>"
                        data-comment="<?= htmlspecialchars($comment) ?>"
                        title="<?= htmlspecialchars($emp['position'] ?? '') ?>">
                        <?= htmlspecialchars($comment ?? '') ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
<?php endif; ?>

<script>
    document.querySelectorAll('.editable').forEach(cell => {
        const currentUserId = <?= json_encode($current_user_id) ?>;
        const userRole = <?= json_encode($role) ?>;
        const cellUserId = parseInt(cell.dataset.user);
        const cellDate = cell.dataset.date;
        const departmentId = cell.dataset.department || 0;
        const canEdit = (userRole === 'admin' || userRole === 'manager' || currentUserId === cellUserId);
        if (!canEdit) return;

        cell.addEventListener('click', async () => {
            if (cell.querySelector('select')) return;

            const original = cell.textContent.trim();
            const userId = cell.dataset.user;

            const response = await fetch('get_time_templates.php?department_id=' + departmentId);
            const templates = await response.json();

            const select = document.createElement('select');
            const defaultOption = document.createElement('option');
            defaultOption.text = '—';
            defaultOption.value = '';
            select.appendChild(defaultOption);

            templates.forEach(template => {
                const option = document.createElement('option');
                option.value = template.time_range;
                option.text = template.time_range;
                if (template.time_range === original) option.selected = true;
                select.appendChild(option);
            });

            cell.innerHTML = '';
            cell.appendChild(select);
            select.focus();

            select.addEventListener('change', () => {
                const comment = select.value;
                fetch('save_comment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `employee_id=${userId}&date=${cellDate}&comment=${encodeURIComponent(comment)}`
                }).then(() => {
                    cell.textContent = comment;
                    cell.dataset.comment = comment;
                });
            });

            select.addEventListener('change', () => select.blur());
        });
    });
</script>

</body>
</html>



