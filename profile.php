<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Проверка авторизации
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$userId = $_SESSION['user_id'];
$pdo = getDB();

// Получаем данные пользователя
$stmt = $pdo->prepare("
    SELECT a.*, u.login 
    FROM applications_lab5 a 
    JOIN users_lab5 u ON a.user_id = u.id 
    WHERE a.user_id = ?
");
$stmt->execute([$userId]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$userData) {
    die('Данные не найдены');
}

// Получаем языки пользователя
$stmtLang = $pdo->prepare("
    SELECT pl.name FROM application_languages_lab5 al
    JOIN programming_languages_lab5 pl ON al.language_id = pl.id
    WHERE al.application_id = ?
");
$stmtLang->execute([$userData['id']]);
$userLang = $stmtLang->fetchAll(PDO::FETCH_COLUMN);

$message = '';
$errorMessage = '';

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (empty($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        die('Ошибка CSRF-проверки');
    }
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
            $message = "Данные успешно обновлены!";
            // Обновляем данные в переменной для отображения
            $userData['fio'] = $fio;
            $userData['phone'] = $phone;
            $userData['email'] = $email;
            $userData['birth_date'] = $birth_date;
            $userData['gender'] = $gender;
            $userData['contract_accepted'] = $contract;
            $userData['biography'] = $biography;
            $userLang = $languages;
        } catch (PDOException $e) {
            $errorMessage = "Ошибка БД: " . $e->getMessage();
        }
    } else {
        $errorMessage = "Ошибки валидации: " . implode(', ', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактирование заявки</title>
    <style>
        body {
            font-family: 'Segoe UI', Roboto, sans-serif;
            background: #eef2f5;
            margin: 0;
            padding: 20px;
            color: #1e2a3a;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            padding: 20px 25px;
        }
        h1 {
            font-size: 24px;
            color: #0f3b2c;
            border-bottom: 2px solid #cbd5e1;
            padding-bottom: 8px;
            margin-top: 0;
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
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            background: white;
            box-sizing: border-box;
        }
        .field select[multiple] {
            height: 120px;
        }
        .field input[type="radio"] {
            width: auto;
            margin-right: 6px;
        }
        .field .radio-group {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        button {
            background: #2c5f2d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: 0.2s;
        }
        button:hover {
            background: #1f4a20;
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
        .error {
            border-left-color: #c62828;
            background: #ffebee;
            color: #b71c1c;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            background: #eef2f5;
            padding: 6px 14px;
            border-radius: 30px;
            color: #2c5f2d;
            text-decoration: none;
            border: 1px solid #cbd5e1;
        }
        .back-link:hover {
            background: #e2e8f0;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Редактирование заявки</h1>
    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
        <div class="message error"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

        <div class="field">
            <label>ФИО *</label>
            <input type="text" name="fio" value="<?= htmlspecialchars($userData['fio']) ?>" required>
        </div>
        <div class="field">
            <label>Телефон *</label>
            <input type="tel" name="phone" value="<?= htmlspecialchars($userData['phone']) ?>" required>
        </div>
        <div class="field">
            <label>Email *</label>
            <input type="email" name="email" value="<?= htmlspecialchars($userData['email']) ?>" required>
        </div>
        <div class="field">
            <label>Дата рождения *</label>
            <input type="date" name="birth_date" value="<?= htmlspecialchars($userData['birth_date']) ?>" required>
        </div>
        <div class="field">
            <label>Пол *</label>
            <div class="radio-group">
                <label><input type="radio" name="gender" value="male" <?= $userData['gender'] === 'male' ? 'checked' : '' ?>> Мужской</label>
                <label><input type="radio" name="gender" value="female" <?= $userData['gender'] === 'female' ? 'checked' : '' ?>> Женский</label>
            </div>
        </div>
        <div class="field">
            <label>Любимые языки программирования *</label>
            <select name="languages[]" multiple size="6">
                <?php
                $allowed = $allowedLanguages ?? [
                    'Pascal','C','C++','JavaScript','PHP','Python',
                    'Java','Haskel','Clojure','Prolog','Scala','Go'
                ];
                foreach ($allowed as $lang): ?>
                    <option value="<?= htmlspecialchars($lang) ?>" <?= in_array($lang, $userLang) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($lang) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>Биография</label>
            <textarea name="biography" rows="5"><?= htmlspecialchars($userData['biography']) ?></textarea>
        </div>
        <div class="field">
            <label><input type="checkbox" name="contract" value="1" <?= $userData['contract_accepted'] ? 'checked' : '' ?>> Я ознакомлен с контрактом *</label>
        </div>
        <button type="submit">Сохранить изменения</button>
    </form>
    <a href="/project/" class="back-link">← Вернуться на сайт</a>
</div>
</body>
</html>