<?php
require __DIR__ . '/config.php';
if (!empty($_SESSION['user_id'])) { header('Location: /index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $company = trim($_POST['company'] ?? 'Моя компания');

    if (empty($email) || empty($password)) {
        $error = 'Заполните Email и пароль';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Генерация уникального кода приглашения
        do {
            $newInviteCode = bin2hex(random_bytes(16));
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE invite_code = ?");
            $checkStmt->execute([$newInviteCode]);
        } while ($checkStmt->fetch());

        $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, company_name, invite_code, role) VALUES (?, ?, ?, ?, 'owner')");
        try {
            $stmt->execute([$email, $hash, $company, $newInviteCode]);
            $_SESSION['user_id'] = $pdo->lastInsertId();
            header('Location: /index.php');
            exit;
        } catch (Exception $e) {
            $error = 'Email уже занят';
        }
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Регистрация - SyntaX Flow</title>
    <link rel="stylesheet" href="/static/styles/variables.css">
    <link rel="stylesheet" href="/static/styles/base.css">
    <link rel="stylesheet" href="/static/styles/components.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <h2>Регистрация</h2>
            <?php if ($error): ?>
                <p style="color: #ef4444; text-align: center; margin-bottom: 16px;"><?= $error ?></p>
            <?php endif; ?>
            <form method="POST">
                <div class="input-group">
                    <label>Название компании (Login)</label>
                    <input type="text" name="company" placeholder="Например: БФУ им. Канта">
                </div>
                <div class="input-group">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>
                <div class="input-group">
                    <label>Пароль</label>
                    <input type="password" name="password" id="pass" required>
                    <span class="toggle-password" onclick="document.getElementById('pass').type = document.getElementById('pass').type === 'password' ? 'text' : 'password';">👁️</span>
                </div>
                <button type="submit" class="btn btn-primary">Создать аккаунт</button>
            </form>
            <a href="/login.php" class="auth-link">Войти</a>
        </div>
    </div>
</body>
</html>