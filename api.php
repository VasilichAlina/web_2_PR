<?php
// api.php - бэкенд для фотостудии Camellia
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Настройки БД - ЗАМЕНИ НА СВОИ!
$DB_HOST = 'localhost';
$DB_NAME = 'u82560';     // твоя БД
$DB_USER = 'u82560';     // твой пользователь
$DB_PASS = '3961962';    // твой пароль

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Получаем метод и путь
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '/';

// Функция проверки авторизации
function authenticate($pdo) {
    if (!isset($_SERVER['PHP_AUTH_USER'])) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM camellia_clients WHERE login = ?");
    $stmt->execute([$_SERVER['PHP_AUTH_USER']]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($_SERVER['PHP_AUTH_PW'], $user['password_hash'])) {
        unset($user['password_hash']);
        return $user;
    }
    return null;
}

// Обработка запросов
switch ($method) {
    case 'POST':
        if ($path === '/register') {
            // РЕГИСТРАЦИЯ (неавторизованный)
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Простая валидация
            $errors = [];
            if (empty($data['fullname'])) $errors[] = 'fullname required';
            if (empty($data['phone'])) $errors[] = 'phone required';
            if (empty($data['email'])) $errors[] = 'email required';
            
            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit;
            }
            
            // Генерируем логин и пароль
            $login = 'camellia_' . rand(10000, 99999);
            $password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
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
                'password' => $password,
                'profile_url' => "/profile.html?login=" . urlencode($login)
            ]);
        }
        break;
        
    case 'PUT':
        if ($path === '/profile') {
            // ОБНОВЛЕНИЕ ПРОФИЛЯ (только авторизованные)
            $user = authenticate($pdo);
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Обновляем всё, кроме логина и пароля
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
            $updatedUser = $stmt->fetch();
            unset($updatedUser['password_hash']);
            
            echo json_encode(['success' => true, 'user' => $updatedUser]);
        }
        break;
        
    case 'GET':
        if ($path === '/profile') {
            // ПОЛУЧИТЬ ПРОФИЛЬ (только авторизованные)
            $user = authenticate($pdo);
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                exit;
            }
            echo json_encode(['success' => true, 'user' => $user]);
        }
        break;
        
    case 'OPTIONS':
        // Для CORS
        http_response_code(200);
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
}
