<?php

require_once '../auth.php';


// Подключение к CDR базе Asterisk
$connect = new mysqli('10.0.0.93', 'kevingr', '17071992', 'asteriskcdrdb');
if ($connect->connect_error) {
    die("Ошибка подключения к CDR базе: " . $connect->connect_error);
}

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');

$start_safe = $connect->real_escape_string($start);
$end_safe = $connect->real_escape_string($end);

// Получаем общее количество входящих звонков
$total_sql = "
    SELECT COUNT(*) AS total_calls,
           SUM(disposition = 'ANSWERED') AS answered,
           SUM(disposition != 'ANSWERED') AS missed
    FROM cdr
    WHERE calldate BETWEEN '$start_safe 00:00:00' AND '$end_safe 23:59:59'
      AND dstchannel != ''
";
$total_result = $connect->query($total_sql);
$totals = $total_result->fetch_assoc();

// Получаем детализацию звонков
$calls_sql = "
    SELECT calldate, src, dst, duration, disposition, recordingfile
    FROM cdr
    WHERE calldate BETWEEN '$start_safe 00:00:00' AND '$end_safe 23:59:59'
    ORDER BY calldate DESC
    LIMIT 100
";

$calls_result = $connect->query($calls_sql);

if (!$calls_result) {
    die("Ошибка SQL-запроса: " . $connect->error);
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Аналитика звонков</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background: #f5f5f5; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: center; }
        th { background: #e0e0e0; }
        input[type=date] { padding: 5px; }
    </style>
</head>
<body>
    <h2>📞 Аналитика звонков</h2>

    <form method="get">
        <label>С: <input type="date" name="start" value="<?= htmlspecialchars($start) ?>"></label>
        <label>По: <input type="date" name="end" value="<?= htmlspecialchars($end) ?>"></label>
        <button type="submit">Показать</button>
    </form>

    <h3>Сводка</h3>
    <ul>
        <li>Всего звонков: <?= $totals['total_calls'] ?></li>
        <li>Отвечено: <?= $totals['answered'] ?></li>
        <li>Пропущено: <?= $totals['missed'] ?></li>
    </ul>

    <h3>Последние 100 звонков</h3>
    <table>
        <thead>
            <tr>
                <th>Дата</th>
                <th>От</th>
                <th>Кому</th>
                <th>Длительность (сек)</th>
                <th>Статус</th>
                <th>Запись</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $calls_result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['calldate']) ?></td>
                    <td><?= htmlspecialchars($row['src']) ?></td>
                    <td><?= htmlspecialchars($row['dst']) ?></td>
                    <td><?= (int)$row['duration'] ?></td>
                    <td><?= htmlspecialchars($row['disposition']) ?></td>
                    <td>
                        <?php if (!empty($row['filename'])): ?>
                            <audio controls>
                                <source src="/calls/<?= htmlspecialchars($row['filename']) ?>" type="audio/mpeg">
                                Ваш браузер не поддерживает аудио.
                            </audio>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
