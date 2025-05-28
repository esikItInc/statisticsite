<?php
require_once 'auth.php';
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Статистика</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .nav-menu {
            margin-left: 40px; /* Регулируй значение по вкусу */
        }

        .top-header {
            font-size: 0.9rem;
            background-color: #f8f9fa;
            border-bottom: 1px solid #ddd;
        }
        .main-nav {
            padding: 1rem 0;
            background-color: white;
        }
        .nav-link {
            font-weight: 500;
            color: #004b87 !important;
        }
        .nav-link:hover {
            color: #007bff !important;
        }
        .logo {
            height: 100px;
        }
        .welcome-text {
            margin-top: 60px;
            text-align: center;
            padding: 60px 20px;
            font-size: 1.25rem;
            color: #333;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        .footer {
            position: fixed;
            bottom: 10px;
            right: 20px;
            font-size: 0.8rem;
            color: #999;
            opacity: 0.6;
        }
    </style>
</head>
<body>

<!-- Верхняя строка с контактами -->
<div class="top-header py-1">
    <div class="container d-flex justify-content-between">
        <div>
            г. Екатеринбург, ул. Гагарина, 28
        </div>
        <div>
            <?= isset($_SESSION['user']) ? 'Пользователь: ' . htmlspecialchars($_SESSION['user']['login']) : '' ?>
        </div>
        <div>
            <a href="mailto:support@yourdomain.local" class="text-dark">admin@pr-clinica.ru</a>
        </div>
    </div>
</div>

<!-- Основная навигация -->
<nav class="main-nav">
    <div class="container d-flex flex-wrap align-items-center justify-content-between">
        <!-- Лого -->
        <a href="https://pr-clinica.ru/" class="d-flex align-items-center mb-2 mb-lg-0 text-dark text-decoration-none">
            <img src="img/Logo1.jpg" alt="Логотип" class="logo">
        </a>

        <!-- Навигация -->
        <ul class="nav me-auto nav-menu">
            <?php if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin'): ?>
                <li class="nav-item"><a href="integration/1c.php" class="nav-link px-2">1С</a></li>
            <?php endif; ?>
            <?php if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin'): ?>
                <li class="nav-item"><a href="integration/asterisk.php" class="nav-link px-2">ТЕЛЕФОНИЯ</a></li>
            <?php endif; ?>
            <?php if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin'): ?>
                <li class="nav-item"><a href="integration/metrika.php" class="nav-link px-2">МЕТРИКА</a></li>
            <?php endif; ?>
            <li class="nav-item"><a href="integration/work_schedule.php" class="nav-link px-2">РАСПИСАНИЕ</a></li>
            <?php if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin'): ?>
                <li class="nav-item"><a href="integration/user_admin.php" class="nav-link px-2 text-danger">УПРАВЛЕНИЕ ПОЛЬЗОВАТЕЛЯМИ</a></li>
            <?php endif; ?>
        </ul>

        <!-- Поиск и выход -->
        <div class="d-flex align-items-center">
            <form class="me-2 d-none d-md-block">
                <input type="search" class="form-control form-control-sm" placeholder="Поиск...">
            </form>
            <?php if (isset($_SESSION['user'])): ?>
                <a href="logout.php" class="btn btn-outline-primary btn-sm">Выход</a>
            <?php else: ?>
                <a href="index.html" class="btn btn-primary btn-sm">Вход</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Добро пожаловать -->
<div class="welcome-text">
    <p><strong>Добро пожаловать на портал статистики!</strong></p>
    <p>Здесь вы найдёте актуальную информацию по основным сервисам компании — 1С, контроле смен, телефонии и веб-аналитике. Этот инструмент помогает получить общее представление о ключевых показателях фирмы.</p>
</div>

<!-- Футер -->
<div class="footer">
    development from Малахов Е.Г
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
