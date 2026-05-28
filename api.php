<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once 'config.php';
require_once 'functions.php';

// Сессию не трогаем – она не влияет на создание заявки
// session_start(); // можно закомментировать, если не нужна

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
$birth_date = trim($data['birth_date'] ?? '');
$gender = $data['gender'] ?? '';
$languages = $data['languages'] ?? [];
$biography = trim($data['message'] ?? $data['comment'] ?? $data['biography'] ?? '');
$contract = isset($data['contract']) ? (int)$data['contract'] : 0;

// Валидация обязательных полей
if (!$name || !$phone || !$email || !$birth_date || !$gender || empty($languages)) {
    http_response_code(400);
    echo json_encode(['error' => 'Обязательные поля: name, phone, email, birth_date, gender, languages']);
    exit;
}

// Проверка корректности данных
$errors = validateFormData($name, $phone, $email, $birth_date, $gender, $languages, $biography, $contract);
if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['errors' => $errors]);
    exit;
}

try {
    $creds = saveNewApplication($name, $phone, $email, $birth_date, $gender, $languages, $biography, $contract);
    $profileUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://')
                . $_SERVER['HTTP_HOST']
                . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/')
                . '/profile.php';
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