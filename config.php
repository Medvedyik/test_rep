<?php
ini_set('display_errors', 0);
error_reporting(0);

$db_host = 'localhost';
$db_user = 'u82258';
$db_pass = '7574471';
$db_name = 'u82258';

function getDB() {
    global $db_host, $db_user, $db_pass, $db_name;
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die('Ошибка подключения к БД');
        }
    }
    return $pdo;
}

function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
