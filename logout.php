<?php
session_start();
session_unset();
session_destroy();

// Перенаправление на форму входа
header("Location: /index.html");
exit;
