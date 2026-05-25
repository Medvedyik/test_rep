<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// HTTP Basic Authentication
$adminAuth = false;
if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT password_hash FROM admin WHERE username = ?");
    $stmt->execute([$_SERVER['PHP_AUTH_USER']]);
    $adminRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($adminRow && password_verify($_SERVER['PHP_AUTH_PW'], $adminRow['password_hash'])) {
        $adminAuth = true;
    }
}
if (!$adminAuth) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    echo '<h1>401 Требуется авторизация</h1>';
    exit;
}

// Обработка действий: удаление, редактирование
$pdo = getDB();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
	if (empty($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
		die('Ошибка CSRF-проверки');
	}
    $action = $_POST['action'];
    if ($action === 'delete' && isset($_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];
        // Удаляем пользователя и связанные данные (каскадно)
        $stmt = $pdo->prepare("DELETE FROM users_lab5 WHERE id = ?");
        $stmt->execute([$userId]);
        $message = "Запись удалена.";
    } elseif ($action === 'edit' && isset($_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];
        // Получаем данные из формы редактирования
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

// Получаем все заявки вместе с логинами пользователей и языками
$applications = $pdo->query("
    SELECT a.id, a.user_id, a.fio, a.phone, a.email, a.birth_date, a.gender, a.biography, a.contract_accepted, a.created_at, a.updated_at,
           u.login
    FROM applications_lab5 a
    JOIN users_lab5 u ON a.user_id = u.id
    ORDER BY a.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Для каждой заявки получаем список языков
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

// Если требуется редактирование конкретной записи, загружаем данные для формы
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
        // Получаем языки для этой заявки
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
    <link rel="stylesheet" href="style.css">
    <style>
        .table-wrapper {
            overflow-x: auto;
            margin: 20px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: #f2f2f2;
        }
        .stats {
            margin: 20px 0;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        .message {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
        }
        .edit-form {
            margin-top: 30px;
            padding: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background: #fefefe;
        }
        .edit-form h2 { margin-top: 0; }
        .inline-form { display: inline; }
    </style>
</head>
<body>
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
                            <button type="submit" style="background:none; border:none; color:red; cursor:pointer;">Удалить</button>
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
