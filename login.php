<?php
session_start();
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход для редактирования</title>
    <style>
        body {
            font-family: 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            background: #eef2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .login-card {
            background: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 380px;
            text-align: center;
        }
        .login-card h2 {
            margin: 0 0 20px;
            color: #1e3a5f;
            font-weight: 500;
            font-size: 24px;
            border-bottom: 2px solid #cbd5e1;
            padding-bottom: 8px;
            display: inline-block;
        }
        .field {
            margin-bottom: 20px;
            text-align: left;
        }
        .field label {
            display: block;
            font-weight: 500;
            margin-bottom: 6px;
            font-size: 14px;
            color: #1e3a5f;
        }
        .field input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
            box-sizing: border-box;
            transition: 0.2s;
        }
        .field input:focus {
            outline: none;
            border-color: #2c7a4d;
            box-shadow: 0 0 0 2px rgba(44,125,77,0.2);
        }
        button {
            background: #2c5f2d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            width: 100%;
            transition: background 0.2s;
        }
        button:hover {
            background: #1f4a20;
        }
        .error-message {
            margin-bottom: 20px;
            padding: 12px;
            background: #ffebee;
            border-left: 4px solid #c62828;
            border-radius: 6px;
            color: #b71c1c;
            font-size: 14px;
            text-align: left;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            font-size: 13px;
            color: #2c5f2d;
            text-decoration: none;
            border-bottom: 1px dashed #2c5f2d;
        }
        .back-link:hover {
            color: #1f4a20;
            border-bottom-style: solid;
        }
    </style>
</head>
<body>
<div class="login-card">
    <h2>Вход для редактирования</h2>
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
            die('Ошибка CSRF-проверки');
        }
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id, password_hash FROM users_lab5 WHERE login = ?");
        $stmt->execute([$_POST['login']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($_POST['pass'], $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['login'] = $_POST['login'];
            header('Location: profile.php');
            exit;
        } else {
            echo '<div class="error-message">Неверный логин или пароль</div>';
        }
    }
    ?>
    <form method="post">
        <div class="field">
            <label>Логин</label>
            <input type="text" name="login" required autofocus>
        </div>
        <div class="field">
            <label>Пароль</label>
            <input type="password" name="pass" required>
        </div>
        <button type="submit">Войти</button>
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
    </form>
    <a href="/project" class="back-link">На главную</a>
</div>
</body>
</html>