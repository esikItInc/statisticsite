<?php
require_once '../auth.php';

$connect = new mysqli('10.0.0.93', 'kevingr', '17071992', 'asteriskcdrdb');
if ($connect->connect_error) {
    die("Ошибка подключения к CDR базе: " . $connect->connect_error);
}

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');
$limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 1000) : 10;

$start_safe = $connect->real_escape_string($start);
$end_safe = $connect->real_escape_string($end);

// ⬇️ Только звонки с регистрационного номера (DID)
$filter_did = '3857007';

// Сводка
$total_sql = "
    SELECT COUNT(*) AS total_calls,
           SUM(disposition = 'ANSWERED') AS answered,
           SUM(disposition != 'ANSWERED') AS missed
    FROM cdr
    WHERE calldate BETWEEN '$start_safe 00:00:00' AND '$end_safe 23:59:59'
      AND dstchannel != ''
      AND did = '$filter_did'
";
$total_result = $connect->query($total_sql);
$totals = $total_result->fetch_assoc();

// Типы номеров
$types_sql = "
    SELECT src FROM cdr
    WHERE calldate BETWEEN '$start_safe 00:00:00' AND '$end_safe 23:59:59'
      AND dstchannel != ''
      AND did = '$filter_did'
";
$type_counts = ['city' => 0, 'mobile_79' => 0, 'mobile_89' => 0];
$result = $connect->query($types_sql);
while ($row = $result->fetch_assoc()) {
    $src = preg_replace('/[^0-9]/', '', $row['src']);
    if (preg_match('/^8495|^495/', $src)) {
        $type_counts['city']++;
    } elseif (preg_match('/^79/', $src)) {
        $type_counts['mobile_79']++;
    } elseif (preg_match('/^89/', $src)) {
        $type_counts['mobile_89']++;
    }
}

// График по часам
$graph_data = [];
if (isset($_GET['chart_date'])) {
    $chart_date = $_GET['chart_date'];
    $direction = $_GET['call_type'] ?? 'in'; // in | out

    $time_sql = "
        SELECT HOUR(calldate) AS hour, COUNT(*) AS count
        FROM cdr
        WHERE DATE(calldate) = ?
        AND did = ?
        AND " . ($direction === 'out' ? "dstchannel = ''" : "dstchannel != ''") . "
        GROUP BY hour
    ";

    $stmt = $connect->prepare($time_sql);
    $stmt->bind_param("ss", $chart_date, $filter_did);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $graph_data[(int)$row['hour']] = $row['count'];
    }
}

// Последние звонки
$calls_sql = "
    SELECT calldate, src, dst, duration, disposition
    FROM cdr
    WHERE calldate BETWEEN '$start_safe 00:00:00' AND '$end_safe 23:59:59'
    AND did = '$filter_did'
    ORDER BY calldate DESC
    LIMIT $limit
";
$calls_result = $connect->query($calls_sql);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Аналитика звонков (регистратура)</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: sans-serif; padding: 20px; background: #f4f4f8; color: #333; }
        table { width: 100%; border-collapse: collapse; background: #fff; margin-bottom: 30px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: center; }
        th { background: #e0e0e0; }
        form { margin-bottom: 20px; }
        input[type=date], select { padding: 5px; margin-right: 10px; }
        button { padding: 6px 12px; background: #0078D4; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .summary, .filters { background: #fff; padding: 15px; border-radius: 6px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
        .loader {
            display: none;
            position: fixed;
            left: 50%; top: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255,255,255,0.95);
            padding: 30px;
            border: 2px solid #0078D4;
            border-radius: 8px;
            text-align: center;
            z-index: 9999;
        }
        .loader .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #0078D4;
            border-radius: 50%;
            width: 30px; height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
<a href="/statistic.php" style="display:inline-block; margin-bottom:20px; background:#0078D4; color:#fff; padding:8px 16px; text-decoration:none; border-radius:4px;">🏠 Вернуться на главную</a>
<h2>📞 Аналитика звонков — регистратура </h2>

<form method="get" class="filters" onsubmit="showLoader()">
    <label>С: <input type="date" name="start" value="<?= htmlspecialchars($start) ?>"></label>
    <label>По: <input type="date" name="end" value="<?= htmlspecialchars($end) ?>"></label>
    <label>Отображать:
        <select name="limit">
            <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
            <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
            <option value="500" <?= $limit == 500 ? 'selected' : '' ?>>500</option>
            <option value="1000" <?= $limit == 1000 ? 'selected' : '' ?>>1000</option>
        </select>
    </label>
    <button type="submit">Показать</button>
</form>

<h3>Последние <?= $limit ?> звонков</h3>

<table>
    <thead>
    <tr>
        <th>Дата</th>
        <th>От</th>
        <th>Длительность (сек)</th>
        <th>Статус</th>
    </tr>
    </thead>
    <tbody>
    <?php while ($row = $calls_result->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['calldate']) ?></td>
            <td><?= htmlspecialchars($row['src']) ?></td>
            <td><?= (int)$row['duration'] ?></td>
            <td><?= htmlspecialchars($row['disposition']) ?></td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>

<div class="summary">
    <h3>График по часам</h3>
    <form method="get" onsubmit="showLoader()">
        <input type="date" name="chart_date" required value="<?= htmlspecialchars($_GET['chart_date'] ?? '') ?>">
        <select name="call_type">
            <option value="in" <?= ($_GET['call_type'] ?? '') === 'in' ? 'selected' : '' ?>>Входящие</option>
            <option value="out" <?= ($_GET['call_type'] ?? '') === 'out' ? 'selected' : '' ?>>Исходящие</option>
        </select>
        <button type="submit">Сформировать</button>
    </form>

    <?php if (!empty($_GET['chart_date'])): ?>
        <canvas id="callsChart" height="120"></canvas>
        <script>
            const labels = <?= json_encode(array_map(fn($h) => "$h:00", range(7, 22))) ?>;
            const data = <?= json_encode(array_map(fn($h) => (int)($graph_data[$h] ?? 0), range(7, 22))) ?>;

            const ctx = document.getElementById('callsChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Звонков',
                        data: data,
                        backgroundColor: '#0078D4'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } }
                    }
                }
            });
        </script>
    <?php endif; ?>
</div>

<div class="summary">
    <h3>Сводка</h3>
    <ul>
        <li>Всего звонков: <?= $totals['total_calls'] ?></li>
        <li>Отвечено: <?= $totals['answered'] ?></li>
        <li>Пропущено: <?= $totals['missed'] ?></li>
        <li><b>По типам номеров:</b></li>
        <li>Городские (8495/495): <?= $type_counts['city'] ?></li>
        <li>Сотовые 79...: <?= $type_counts['mobile_79'] ?></li>
        <li>Сотовые 89...: <?= $type_counts['mobile_89'] ?></li>
    </ul>
</div>

<div class="loader" id="loader">
    <div class="spinner"></div>
    <div>Собираем информацию...</div>
</div>

<script>
    function showLoader() {
        document.getElementById('loader').style.display = 'block';
    }
</script>

</body>
</html>



