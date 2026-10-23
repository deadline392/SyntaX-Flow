<?php
require __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;

if (file_exists(__DIR__ . '/.env')) {
    Dotenv::createImmutable(__DIR__)->load();
}

session_start();

try {
    $dsn = "mysql:host=" . ($_ENV['DB_HOST'] ?? 'db') . ";dbname=" . ($_ENV['DB_NAME'] ?? 'excel_ai') . ";charset=utf8mb4";
    $pdo = new PDO($dsn, $_ENV['DB_USER'] ?? 'root', $_ENV['DB_PASS'] ?? 'deadline392');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка БД: " . $e->getMessage());
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function requireAuth(): array {
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        session_destroy();
        header('Location: /login.php');
        exit;
    }

    // Если сотрудник, подтягиваем данные подписки от владельца
    if ($user['role'] === 'employee' && !empty($user['company_owner_id'])) {
        $stmtOwner = $pdo->prepare("SELECT subscription_until, plan_type, max_seats FROM users WHERE id = ?");
        $stmtOwner->execute([$user['company_owner_id']]);
        $owner = $stmtOwner->fetch();
        
        $user['subscription_until'] = $owner['subscription_until'];
        $user['plan_type'] = $owner['plan_type'];
        $user['max_seats'] = $owner['max_seats'];
    }

    $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")->execute([$user['id']]);
    
    return $user;
}