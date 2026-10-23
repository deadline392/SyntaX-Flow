<?php
require __DIR__ . '/config.php';
if (!empty($_SESSION['user_id'])) { header('Location: /index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        header('Location: /index.php');
        exit;
    }
    $error = 'Неверный email или пароль';
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Вход - SyntaX Flow</title>
    <link rel="stylesheet" href="/static/styles/variables.css">
    <link rel="stylesheet" href="/static/styles/base.css">
    <link rel="stylesheet" href="/static/styles/components.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <h2>Вход</h2>
            <?php if ($error): ?>
                <p style="color: #ef4444; text-align: center; margin-bottom: 16px;"><?= $error ?></p>
            <?php endif; ?>
            <form method="POST">
                <div class="input-group">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>
                <div class="input-group">
                    <label>Пароль</label>
                    <input type="password" name="password" id="pass" required>
                    <span class="toggle-password" onclick="document.getElementById('pass').type = document.getElementById('pass').type === 'password' ? 'text' : 'password';">👁️</span>
                </div>
                <button type="submit" class="btn btn-primary">Войти</button>
            </form>
            <a href="/register.php" class="auth-link">Регистрация</a>
        </div>
    </div>
</body>
</html>