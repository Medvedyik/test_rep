<?php
require_once 'config.php';

// Список допустимых языков
$allowedLanguages = [
    'Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python',
    'Java', 'Haskel', 'Clojure', 'Prolog', 'Scala', 'Go'
];

// Валидация данных формы
function validateFormData($fio, $phone, $email, $birth_date, $gender, $languages, $biography, $contract) {
    global $allowedLanguages;
    $errors = [];

    if (empty($fio)) {
        $errors['fio'] = 'Заполните ФИО.';
    } elseif (strlen($fio) > 150) {
        $errors['fio'] = 'ФИО не должно превышать 150 символов.';
    } elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $fio)) {
        $errors['fio'] = 'ФИО должно содержать только буквы, пробелы и дефисы.';
    }

    if (empty($phone)) {
        $errors['phone'] = 'Заполните телефон.';
    } elseif (!preg_match('/^[\d\s\(\)\+\-]+$/', $phone)) {
        $errors['phone'] = 'Телефон содержит недопустимые символы.';
    }

    if (empty($email)) {
        $errors['email'] = 'Заполните e-mail.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный e-mail.';
    }

    if (empty($birth_date)) {
        $errors['birth_date'] = 'Укажите дату рождения.';
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $birth_date);
        if (!$date || $date->format('Y-m-d') !== $birth_date) {
            $errors['birth_date'] = 'Неверный формат даты.';
        } elseif ($date > new DateTime()) {
            $errors['birth_date'] = 'Дата не может быть в будущем.';
        }
    }

    if (!in_array($gender, ['male', 'female'])) {
        $errors['gender'] = 'Выберите пол.';
    }

    if (empty($languages)) {
        $errors['languages'] = 'Выберите хотя бы один язык.';
    } else {
        foreach ($languages as $lang) {
            if (!in_array($lang, $allowedLanguages)) {
                $errors['languages'] = 'Выбран недопустимый язык.';
                break;
            }
        }
    }

    if (strlen($biography) > 500) {
        $errors['biography'] = 'Биография не должна превышать 500 символов.';
    }

    if (!$contract) {
        $errors['contract'] = 'Необходимо ознакомиться с контрактом.';
    }

    return $errors;
}

// Сохранение новой заявки (создание пользователя)
function saveNewApplication($fio, $phone, $email, $birth_date, $gender, $languages, $biography, $contract) {
    $pdo = getDB();
    // Генерация логина и пароля
    $login = 'user_' . bin2hex(random_bytes(5));
    $plainPassword = substr(bin2hex(random_bytes(5)), 0, 10);
    $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);

    $pdo->beginTransaction();
    try {
        // Создаём пользователя
        $stmt = $pdo->prepare("INSERT INTO users_lab5 (login, password_hash) VALUES (?, ?)");
        $stmt->execute([$login, $passwordHash]);
        $userId = $pdo->lastInsertId();

        // Создаём заявку
        $stmt = $pdo->prepare("
            INSERT INTO applications_lab5 (user_id, fio, phone, email, birth_date, gender, biography, contract_accepted)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $fio, $phone, $email, $birth_date, $gender, $biography, $contract]);
        $appId = $pdo->lastInsertId();

        // Вставляем языки
        $stmtLang = $pdo->prepare("
            INSERT INTO application_languages_lab5 (application_id, language_id)
            VALUES (?, (SELECT id FROM programming_languages_lab5 WHERE name = ?))
        ");
        foreach ($languages as $lang) {
            $stmtLang->execute([$appId, $lang]);
        }

        $pdo->commit();
        return ['login' => $login, 'pass' => $plainPassword];
    } catch (PDOException $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Обновление существующей заявки (для авторизованного пользователя или администратора)
function updateApplication($userId, $fio, $phone, $email, $birth_date, $gender, $languages, $biography, $contract) {
    $pdo = getDB();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            UPDATE applications_lab5 
            SET fio = ?, phone = ?, email = ?, birth_date = ?, gender = ?, biography = ?, contract_accepted = ?, updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([$fio, $phone, $email, $birth_date, $gender, $biography, $contract, $userId]);

        // Получаем id заявки
        $appId = $pdo->query("SELECT id FROM applications_lab5 WHERE user_id = $userId")->fetchColumn();
        // Удаляем старые языки и вставляем новые
        $pdo->prepare("DELETE FROM application_languages_lab5 WHERE application_id = ?")->execute([$appId]);
        $stmtLang = $pdo->prepare("
            INSERT INTO application_languages_lab5 (application_id, language_id)
            VALUES (?, (SELECT id FROM programming_languages_lab5 WHERE name = ?))
        ");
        foreach ($languages as $lang) {
            $stmtLang->execute([$appId, $lang]);
        }
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        throw $e;
    }
}
?>
