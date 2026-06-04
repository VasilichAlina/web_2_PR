<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ПОДКЛЮЧЕНИЕ К БД - ПРОВЕРЬ ЭТИ ДАННЫЕ!!!
$DB_HOST = 'localhost';
$DB_NAME = 'u82560';
$DB_USER = 'u82560';
$DB_PASS = '3961962';  // ЕСЛИ НЕ ПОДХОДИТ - ПОМЕНЯЙ!

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// ПОЛУЧАЕМ МЕТОД И ПУТЬ
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '/';

// ФУНКЦИЯ АВТОРИЗАЦИИ
function authenticate($pdo) {
    if (!isset($_SERVER['PHP_AUTH_USER'])) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM camellia_clients WHERE login = ?");
    $stmt->execute([$_SERVER['PHP_AUTH_USER']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($_SERVER['PHP_AUTH_PW'], $user['password_hash'])) {
        unset($user['password_hash']);
        return $user;
    }
    return null;
}

// РЕГИСТРАЦИЯ (POST)
if ($method === 'POST' && $path === '/register') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Валидация
    if (empty($data['fullname']) || empty($data['phone']) || empty($data['email'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => ['fullname', 'phone', 'email']]);
        exit;
    }
    
    // Генерируем логин и пароль
    $login = 'camellia_' . rand(10000, 99999);
    $password = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Сохраняем в БД
    $stmt = $pdo->prepare("INSERT INTO camellia_clients (login, password_hash, fullname, phone, email, session_type, location_type, preferred_studio, preferred_date, message) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $login, $hash,
        $data['fullname'],
        $data['phone'],
        $data['email'],
        $data['session_type'] ?? null,
        $data['location_type'] ?? null,
        $data['preferred_studio'] ?? null,
        $data['preferred_date'] ?? null,
        $data['message'] ?? null
    ]);
    
    echo json_encode([
        'success' => true,
        'login' => $login,
        'password' => $password
    ]);
    exit;
}

// ПОЛУЧИТЬ ПРОФИЛЬ (GET)
if ($method === 'GET' && $path === '/profile') {
    $user = authenticate($pdo);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    echo json_encode(['success' => true, 'user' => $user]);
    exit;
}

// ОБНОВИТЬ ПРОФИЛЬ (PUT)
if ($method === 'PUT' && $path === '/profile') {
    $user = authenticate($pdo);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $pdo->prepare("UPDATE camellia_clients SET fullname=?, phone=?, email=?, session_type=?, location_type=?, preferred_studio=?, preferred_date=?, message=? WHERE id=?");
    $stmt->execute([
        $data['fullname'] ?? $user['fullname'],
        $data['phone'] ?? $user['phone'],
        $data['email'] ?? $user['email'],
        $data['session_type'] ?? $user['session_type'],
        $data['location_type'] ?? $user['location_type'],
        $data['preferred_studio'] ?? $user['preferred_studio'],
        $data['preferred_date'] ?? $user['preferred_date'],
        $data['message'] ?? $user['message'],
        $user['id']
    ]);
    
    // Получаем обновлённые данные
    $stmt = $pdo->prepare("SELECT * FROM camellia_clients WHERE id = ?");
    $stmt->execute([$user['id']]);
    $updated = $stmt->fetch(PDO::FETCH_ASSOC);
    unset($updated['password_hash']);
    
    echo json_encode(['success' => true, 'user' => $updated]);
    exit;
}

// Если ничего не подошло
http_response_code(404);
echo json_encode(['error' => 'Not found']);
?>
