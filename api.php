<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once 'config.php';
require_once 'functions.php';
session_start();

$isLoggedIn = false;
$userId = null;
if (!empty($_SESSION['user_id'])) {
    $isLoggedIn = true;
    $userId = $_SESSION['user_id'];
}

$input = file_get_contents('php://input');
$data = null;
if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $data = json_decode($input, true);
} elseif (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'xml') !== false) {
    $xml = simplexml_load_string($input);
    $data = $xml ? json_decode(json_encode($xml), true) : null;
}
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request format (JSON or XML expected)']);
    exit;
}

$name = trim($data['name'] ?? $data['fio'] ?? '');
$phone = trim($data['phone'] ?? $data['tel'] ?? '');
$email = trim($data['email'] ?? '');
$biography = trim($data['message'] ?? $data['comment'] ?? $data['biography'] ?? '');

// ---- Неавторизованный пользователь: создание новой заявки ----
if (!$isLoggedIn) {
    if (!$name || !$phone || !$email) {
        http_response_code(400);
        echo json_encode(['error' => 'Обязательные поля: name, phone, email']);
        exit;
    }
    $defaults = [
        'birth_date' => '2000-01-01',
        'gender' => 'male',
        'languages' => ['JavaScript'],
        'contract' => 1
    ];
    $errors = validateFormData($name, $phone, $email, $defaults['birth_date'],
                               $defaults['gender'], $defaults['languages'],
                               $biography, $defaults['contract']);
    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode(['errors' => $errors]);
        exit;
    }
    try {
        $creds = saveNewApplication($name, $phone, $email, $defaults['birth_date'],
                                    $defaults['gender'], $defaults['languages'],
                                    $biography, $defaults['contract']);
        $profileUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://')
                    . $_SERVER['HTTP_HOST']
                    . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/')
                    . '/index.html'; // страница с формой
        echo json_encode([
            'success' => true,
            'login' => $creds['login'],
            'password' => $creds['pass'],
            'profile_url' => $profileUrl
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка БД']);
    }
    exit;
}

// ---- Авторизованный пользователь: обновление данных ----
if (!$name || !$phone || !$email) {
    http_response_code(400);
    echo json_encode(['error' => 'Для обновления нужны name, phone, email']);
    exit;
}
$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM applications_lab5 WHERE user_id = ?");
$stmt->execute([$userId]);
$current = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$current) {
    http_response_code(404);
    echo json_encode(['error' => 'Заявка не найдена']);
    exit;
}
// Получаем текущие языки пользователя
$stmtLang = $pdo->prepare("SELECT pl.name FROM application_languages_lab5 al
                           JOIN programming_languages_lab5 pl ON al.language_id = pl.id
                           WHERE al.application_id = ?");
$stmtLang->execute([$current['id']]);
$languages = $stmtLang->fetchAll(PDO::FETCH_COLUMN);

$errors = validateFormData($name, $phone, $email, $current['birth_date'],
                           $current['gender'], $languages, $biography,
                           $current['contract_accepted']);
if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['errors' => $errors]);
    exit;
}
try {
    updateApplication($userId, $name, $phone, $email, $current['birth_date'],
                      $current['gender'], $languages, $biography,
                      $current['contract_accepted']);
    echo json_encode(['success' => true, 'message' => 'Данные обновлены']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка БД']);
}