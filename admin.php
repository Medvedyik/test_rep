<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

$adminAuth = false;
$error = '';

// Проверка сессии
if (!empty($_SESSION['admin_logged_in'])) {
    $adminAuth = true;
}

// Обработка POST-формы входа
if (!$adminAuth && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auth_login'])) {
    $login = $_POST['auth_login'];
    $password = $_POST['auth_pass'];
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT password_hash FROM admin WHERE username = ?");
    $stmt->execute([$login]);
    $adminRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($adminRow && password_verify($password, $adminRow['password_hash'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_login'] = $login;
        $adminAuth = true;
        // Редирект, чтобы убрать POST-данные
        header('Location: ' . $_SERVER['SCRIPT_NAME']);
        exit;
    } else {
        $error = 'Неверный логин или пароль';
    }
}

// Если не авторизован – показываем форму входа
if (!$adminAuth) {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>Вход администратора</title>
        <style>
            body {
                font-family: 'Segoe UI', Roboto, sans-serif;
                background: #eef2f5;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }
            .login-card {
                background: white;
                padding: 30px;
                border-radius: 12px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                width: 320px;
                text-align: center;
            }
            .login-card h2 {
                margin: 0 0 20px;
                color: #1e3a5f;
                font-weight: 500;
            }
            .login-card input {
                width: 100%;
                padding: 10px;
                margin: 8px 0 16px;
                border: 1px solid #cbd5e1;
                border-radius: 6px;
                font-size: 14px;
                box-sizing: border-box;
            }
            .login-card button {
                width: 100%;
                background: #2c5f2d;
                color: white;
                padding: 10px;
                border: none;
                border-radius: 6px;
                font-size: 16px;
                cursor: pointer;
            }
            .login-card button:hover {
                background: #1f4a20;
            }
            .error {
                color: #c62828;
                margin-bottom: 15px;
                font-size: 14px;
            }
        </style>
    </head>
    <body>
        <div class="login-card">
            <h2>Административная панель</h2>
            <?php if ($error): ?>
                <div class="error"><?= h($error) ?></div>
            <?php endif; ?>
            <form method="post">
                <label>Логин</label>
                <input type="text" name="auth_login" required autofocus>
                <label>Пароль</label>
                <input type="password" name="auth_pass" required>
                <button type="submit">Войти</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ---------- Администратор авторизован – основная логика ----------
$pdo = getDB();

// Обработка удаления и редактирования
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (empty($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        die('Ошибка CSRF-проверки');
    }
    $action = $_POST['action'];
    if ($action === 'delete' && isset($_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];
        $stmt = $pdo->prepare("DELETE FROM users_lab5 WHERE id = ?");
        $stmt->execute([$userId]);
        $message = "Запись удалена.";
    } elseif ($action === 'edit' && isset($_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];
        $fio = trim($_POST['fio']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $birth_date = $_POST['birth_date'];
        $gender = $_POST['gender'];
        $languages = $_POST['languages'] ?? [];
        $biography = trim($_POST['biography']);
        $contract = isset($_POST['contract']) ? 1 : 0;

        $errors = validateFormData($fio, $phone, $email, $birth_date, $gender, $languages, $biography, $contract);
        if (empty($errors)) {
            try {
                updateApplication($userId, $fio, $phone, $email, $birth_date, $gender, $languages, $biography, $contract);
                $message = "Данные обновлены.";
            } catch (PDOException $e) {
                $errorMessage = "Ошибка БД: " . $e->getMessage();
            }
        } else {
            $errorMessage = "Ошибки валидации: " . implode(', ', $errors);
        }
    }
}

// Получение всех заявок
$applications = $pdo->query("
    SELECT a.id, a.user_id, a.fio, a.phone, a.email, a.birth_date, a.gender, a.biography, a.contract_accepted, a.created_at, a.updated_at,
           u.login
    FROM applications_lab5 a
    JOIN users_lab5 u ON a.user_id = u.id
    ORDER BY a.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($applications as &$app) {
    $stmt = $pdo->prepare("
        SELECT pl.name FROM application_languages_lab5 al
        JOIN programming_languages_lab5 pl ON al.language_id = pl.id
        WHERE al.application_id = ?
    ");
    $stmt->execute([$app['id']]);
    $langs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $app['languages'] = implode(', ', $langs);
}
unset($app);

// Статистика по языкам
$stats = $pdo->query("
    SELECT pl.name, COUNT(al.application_id) as cnt
    FROM programming_languages_lab5 pl
    LEFT JOIN application_languages_lab5 al ON pl.id = al.language_id
    GROUP BY pl.id
    ORDER BY cnt DESC, pl.name
")->fetchAll(PDO::FETCH_ASSOC);

// Данные для редактирования
$editData = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare("
        SELECT a.*, u.login FROM applications_lab5 a
        JOIN users_lab5 u ON a.user_id = u.id
        WHERE a.user_id = ?
    ");
    $stmt->execute([$editId]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($editData) {
        $stmtLang = $pdo->prepare("
            SELECT pl.name FROM application_languages_lab5 al
            JOIN programming_languages_lab5 pl ON al.language_id = pl.id
            WHERE al.application_id = ?
        ");
        $stmtLang->execute([$editData['id']]);
        $editData['languages'] = $stmtLang->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Панель администратора</title>
    <style>
        body {
            font-family: 'Segoe UI', Roboto, sans-serif;
            background: #eef2f5;
            margin: 0;
            padding: 20px;
            color: #1e2a3a;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            padding: 20px 25px;
        }
        h1, h2 {
            font-weight: 500;
            border-bottom: 2px solid #cbd5e1;
            padding-bottom: 8px;
            margin-top: 0;
        }
        h1 { font-size: 24px; color: #0f3b2c; }
        h2 { font-size: 20px; margin: 20px 0 15px; }
        .stats {
            background: #f8fafc;
            border-left: 4px solid #2c7a4d;
            padding: 12px 18px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .stats ul {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            list-style: none;
            padding: 0;
            margin: 8px 0 0;
        }
        .stats li {
            background: white;
            padding: 6px 14px;
            border-radius: 30px;
            border: 1px solid #d1dbe8;
            font-size: 14px;
        }
        .table-wrapper {
            overflow-x: auto;
            margin: 20px 0;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            min-width: 1200px;
        }
        th, td {
            padding: 10px 12px;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background-color: #f1f5f9;
            font-weight: 600;
            color: #1e3a5f;
            border-bottom: 2px solid #cbd5e1;
        }
        tr:hover td {
            background-color: #fafcff;
        }
        .message {
            padding: 10px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #2e7d32;
            background: #e8f5e9;
            color: #1b5e20;
        }
        .message.error {
            border-left-color: #c62828;
            background: #ffebee;
            color: #b71c1c;
        }
        .edit-form {
            margin-top: 30px;
            padding: 20px;
            border: 1px solid #cfdde6;
            border-radius: 12px;
            background: #f9fbfd;
        }
        .field {
            margin-bottom: 16px;
        }
        .field label {
            display: block;
            font-weight: 500;
            margin-bottom: 5px;
            font-size: 14px;
            color: #1e3a5f;
        }
        .field input, .field select, .field textarea {
            width: 100%;
            max-width: 500px;
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            background: white;
        }
        .field select[multiple] {
            height: 120px;
        }
        .field input[type="radio"] {
            width: auto;
            margin-right: 6px;
        }
        button, a.back-link {
            background: #2c5f2d;
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        button:hover {
            background: #1f4a20;
        }
        .edit-form a {
            background: #6c757d;
            color: white;
            padding: 8px 18px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            margin-left: 10px;
        }
        .edit-form a:hover {
            background: #5a6268;
        }
        .inline-form button {
            background: none;
            color: #c62828;
            padding: 0;
            font-size: 13px;
            text-decoration: underline;
            border: none;
        }
        .inline-form button:hover {
            color: #b71c1c;
            background: none;
            text-decoration: none;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            background: #eef2f5;
            padding: 6px 14px;
            border-radius: 30px;
            color: #2c5f2d;
            text-decoration: none;
            border: 1px solid #cbd5e1;
        }
        .back-link:hover {
            background: #e2e8f0;
            text-decoration: none;
        }
        a {
            color: #2c5f2d;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div style="margin-bottom: 20px;">
    <a href="/project/" class="back-link">← Вернуться на сайт</a>
    <a href="?logout=1" style="float:right;">Выйти</a>
</div>

<div class="container">
    <h1>Администрирование анкет</h1>
    <?php if (isset($message)): ?>
        <div class="message"><?= h($message) ?></div>
    <?php endif; ?>
    <?php if (isset($errorMessage)): ?>
        <div class="message error"><?= h($errorMessage) ?></div>
    <?php endif; ?>

    <div class="stats">
        <h2>Статистика по языкам программирования</h2>
        <ul>
            <?php foreach ($stats as $stat): ?>
                <li><?= h($stat['name']) ?>: <?= (int)$stat['cnt'] ?> пользователей</li>
            <?php endforeach; ?>
        </ul>
    </div>

    <h2>Список заявок</h2>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>ID</th><th>Логин</th><th>ФИО</th><th>Телефон</th><th>Email</th><th>Дата рождения</th><th>Пол</th><th>Языки</th><th>Биография</th><th>Контракт</th><th>Создана</th><th>Обновлена</th><th>Действия</th></tr>
            </thead>
            <tbody>
            <?php foreach ($applications as $app): ?>
            <tr>
                <td><?= $app['id'] ?></td>
                <td><?= h($app['login']) ?></td>
                <td><?= h($app['fio']) ?></td>
                <td><?= h($app['phone']) ?></td>
                <td><?= h($app['email']) ?></td>
                <td><?= h($app['birth_date']) ?></td>
                <td><?= $app['gender'] === 'male' ? 'Мужской' : 'Женский' ?></td>
                <td><?= h($app['languages']) ?></td>
                <td><?= h($app['biography']) ?></td>
                <td><?= $app['contract_accepted'] ? 'Да' : 'Нет' ?></td>
                <td><?= $app['created_at'] ?></td>
                <td><?= $app['updated_at'] ?></td>
                <td>
                    <a href="admin.php?edit=<?= $app['user_id'] ?>">Редактировать</a><br>
                    <form class="inline-form" method="post" onsubmit="return confirm('Удалить запись?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" value="<?= $app['user_id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <button type="submit">Удалить</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($editData): ?>
        <div class="edit-form">
            <h2>Редактирование заявки пользователя <?= h($editData['login']) ?></h2>
            <form method="post">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" value="<?= $editData['user_id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <div class="field">
                    <label>ФИО *</label>
                    <input type="text" name="fio" value="<?= h($editData['fio']) ?>" required>
                </div>
                <div class="field">
                    <label>Телефон *</label>
                    <input type="tel" name="phone" value="<?= h($editData['phone']) ?>" required>
                </div>
                <div class="field">
                    <label>Email *</label>
                    <input type="email" name="email" value="<?= h($editData['email']) ?>" required>
                </div>
                <div class="field">
                    <label>Дата рождения *</label>
                    <input type="date" name="birth_date" value="<?= h($editData['birth_date']) ?>" required>
                </div>
                <div class="field">
                    <label>Пол *</label>
                    <label><input type="radio" name="gender" value="male" <?= $editData['gender'] === 'male' ? 'checked' : '' ?>> Мужской</label>
                    <label><input type="radio" name="gender" value="female" <?= $editData['gender'] === 'female' ? 'checked' : '' ?>> Женский</label>
                </div>
                <div class="field">
                    <label>Любимые языки программирования *</label>
                    <select name="languages[]" multiple size="6">
                        <?php global $allowedLanguages; foreach ($allowedLanguages as $lang): ?>
                            <option value="<?= h($lang) ?>" <?= in_array($lang, $editData['languages']) ? 'selected' : '' ?>><?= h($lang) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Биография</label>
                    <textarea name="biography" rows="5"><?= h($editData['biography']) ?></textarea>
                </div>
                <div class="field">
                    <label><input type="checkbox" name="contract" value="1" <?= $editData['contract_accepted'] ? 'checked' : '' ?>> Контракт ознакомлен *</label>
                </div>
                <button type="submit">Сохранить изменения</button>
                <a href="admin.php">Отмена</a>
            </form>
        </div>
    <?php endif; ?>
</div>
</body>
</html>