<?php
require __DIR__ . '/config.php';
$user = requireAuth();
$isSubscribed = $user['subscription_until'] && strtotime($user['subscription_until']) > time();
$isCorporate = ($user['plan_type'] ?? 'personal') === 'corporate';
$isEmployee = ($user['role'] ?? 'owner') === 'employee';

$joinMessage = '';

// Обработка оплаты подписки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay'])) {
    $months = (int)$_POST['months'];
    $plan = $_POST['plan'];
    $seats = ($plan === 'corporate') ? (int)($_POST['seats'] ?? 5) : 1;
    $baseDate = $isSubscribed ? strtotime($user['subscription_until']) : time();
    $newDate = date('Y-m-d H:i:s', strtotime("+{$months} months", $baseDate));
    
    $pdo->prepare("UPDATE users SET subscription_until = ?, plan_type = ?, max_seats = ? WHERE id = ?")->execute([$newDate, $plan, $seats, $user['id']]);
    header('Location: /profile.php');
    exit;
}

// Обработка отмены подписки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_sub'])) {
    $pdo->prepare("UPDATE users SET subscription_until = NULL WHERE id = ?")->execute([$user['id']]);
    header('Location: /profile.php');
    exit;
}

// Обработка удаления сотрудника (только для владельца)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_employee'])) {
    $empId = (int)$_POST['remove_employee'];
    // Проверяем, что этот сотрудник принадлежит текущему владельцу
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND company_owner_id = ?");
    $stmt->execute([$empId, $user['id']]);
    header('Location: /profile.php');
    exit;
}

// Обработка ввода кода приглашения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_code'])) {
    $code = trim($_POST['join_code']);
    
    $stmt = $pdo->prepare("SELECT id, company_name, subscription_until, plan_type, max_seats FROM users WHERE invite_code = ? AND role = 'owner'");
    $stmt->execute([$code]);
    $owner = $stmt->fetch();
    
    if ($owner) {
        $ownerIsSubscribed = $owner['subscription_until'] && strtotime($owner['subscription_until']) > time();
        if (!$ownerIsSubscribed) {
            $joinMessage = 'У владельца этой компании нет активной корпоративной подписки.';
        } else {
            $countStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM users WHERE company_owner_id = ?");
            $countStmt->execute([$owner['id']]);
            $currentEmployees = $countStmt->fetch()['cnt'];
            $maxSeats = $owner['max_seats'] ?? 5;
            
            if ($currentEmployees >= $maxSeats) {
                $joinMessage = 'Достигнут лимит сотрудников. Попросите владельца расширить пакет.';
            } else {
                $pdo->prepare("UPDATE users SET company_owner_id = ?, role = 'employee', company_name = ?, subscription_until = ?, plan_type = ? WHERE id = ?")
                    ->execute([$owner['id'], $owner['company_name'], $owner['subscription_until'], $owner['plan_type'], $user['id']]);
                $pdo->prepare("UPDATE users SET invite_code = NULL WHERE id = ?")->execute([$user['id']]);
                header('Location: /profile.php');
                exit;
            }
        }
    } else {
        $joinMessage = 'Код недействителен или не существует.';
    }
}

// Обработка загрузки аватарки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $uploadDir = __DIR__ . '/static/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $fileExt = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
    if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'svg', 'webp'])) {
        $fileName = 'avatar_' . $user['id'] . '.' . $fileExt;
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $fileName)) {
            $pdo->prepare("UPDATE users SET logo_url = ? WHERE id = ?")->execute([$fileName, $user['id']]);
            $user['logo_url'] = $fileName;
        }
    }
}

// Получаем список сотрудников
$employees = [];
if ($isCorporate && $user['role'] === 'owner' && $isSubscribed) {
    $stmt = $pdo->prepare("SELECT id, email, last_seen FROM users WHERE company_owner_id = ?");
    $stmt->execute([$user['id']]);
    $employees = $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Личный кабинет - SyntaX Flow</title>
    <link rel="stylesheet" href="/static/styles/variables.css">
    <link rel="stylesheet" href="/static/styles/base.css">
    <link rel="stylesheet" href="/static/styles/components.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="dashboard-layout">
    <aside class="sidebar">
        <div class="sidebar-logo">SyntaX <span>Flow</span></div>
        <ul class="nav-list">
            <li><a href="/index.php">🔄 <span>Конвертер</span></a></li>
            <li><a href="/index.php?tab=history">📜 <span>История</span></a></li>
        </ul>
        <div class="sidebar-bottom">
            <div class="sidebar-profile" onclick="window.location.href='/profile.php'">
                <?php if (!empty($user['logo_url']) && file_exists(__DIR__ . '/static/uploads/' . $user['logo_url'])): ?>
                    <img src="/static/uploads/<?= $user['logo_url'] ?>" class="avatar-circle" style="object-fit: cover;">
                <?php else: ?>
                    <div class="avatar-circle"><?= mb_substr($user['company_name'] ?: $user['email'], 0, 1) ?></div>
                <?php endif; ?>
                <span><?= htmlspecialchars($user['company_name'] ?: $user['email']) ?></span>
            </div>
        </div>
    </aside>

    <main class="content-area">
        <div class="topbar">
            <h1>Личный кабинет</h1>
            <a href="/logout.php" class="btn btn-outline">Выйти</a>
        </div>

        <div class="grid-2" style="grid-template-columns: 1fr 1fr; gap: 24px;">
            <div class="card">
                <h2>Ваша подписка</h2>
                <?php if ($isSubscribed): ?>
                    <p style="margin-bottom: 8px;">Статус: <span style="color: var(--accent-green); font-weight: bold;">Активна</span></p>
                    <p style="margin-bottom: 8px;">Тариф: <strong><?= $isCorporate ? 'Корпоративный' : 'Личный' ?></strong></p>
                    <p style="margin-bottom: 24px;">Действует до: <strong><?= date('d.m.Y H:i', strtotime($user['subscription_until'])) ?></strong></p>
                    <form method="POST" onsubmit="return confirm('Отменить подписку?');">
                        <button type="submit" name="cancel_sub" class="btn btn-danger" style="width: 100%;">Отменить подписку</button>
                    </form>
                <?php else: ?>
                    <p style="margin-bottom: 24px; color: #ef4444;">Подписка неактивна</p>
                    <button onclick="document.getElementById('modal').classList.add('active')" class="btn btn-primary" style="width: 100%;">Оформить подписку</button>
                <?php endif; ?>

                <?php if (!$isCorporate || !$isSubscribed): ?>
                    <hr style="border-color: var(--border-color); margin: 24px 0;">
                    <h3 style="margin-bottom: 12px;">Присоединиться к команде</h3>
                    <?php if ($joinMessage): ?>
                        <p style="color: #ef4444; margin-bottom: 12px; font-size: 0.9rem;"><?= $joinMessage ?></p>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="input-group">
                            <input type="text" name="join_code" placeholder="Введите код приглашения" required>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Присоединиться</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>Настройки профиля</h2>
                <form method="POST" enctype="multipart/form-data">
                    <label class="file-upload" style="margin-bottom: 16px; min-height: 80px; padding: 20px;">
                        <input type="file" name="avatar" accept="image/*">
                        <div class="icon">📷</div>
                        <span>Загрузить логотип<br><small>Нажмите или перетащите</small></span>
                    </label>
                    <button type="submit" class="btn btn-outline" style="width: 100%;">Сохранить логотип</button>
                </form>
            </div>
        </div>

        <?php if ($isCorporate && $user['role'] === 'owner' && $isSubscribed): ?>
            <div class="card" style="margin-top: 24px;">
                <h2>Управление командой</h2>
                <p style="color: var(--text-muted); margin-bottom: 16px;">Код приглашения:</p>
                <div style="display:flex; flex-direction:column; gap:12px; margin-bottom: 24px;">
                    <div style="background: rgba(0,0,0,0.3); padding: 16px; border-radius: 8px; font-size: 1.1rem; font-weight: bold; letter-spacing: 2px; color: var(--accent-purple); border: 1px dashed var(--accent-purple); text-align: center; word-break: break-all;">
                        <?= $user['invite_code'] ?? 'Код не сгенерирован' ?>
                    </div>
                    <button class="btn btn-primary" onclick="navigator.clipboard.writeText('<?= $user['invite_code'] ?? '' ?>'); this.textContent='Скопировано!'">Скопировать код</button>
                </div>

                <h3 style="margin-bottom: 16px;">Сотрудники (<?= count($employees) ?>)</h3>
                <?php if (empty($employees)): ?>
                    <p style="color: var(--text-muted);">Пока никто не присоединился.</p>
                <?php else: ?>
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr>
                                <th style="text-align:left; padding:12px; border-bottom:1px solid #1e293b;">Email</th>
                                <th style="text-align:left; padding:12px; border-bottom:1px solid #1e293b;">Статус</th>
                                <th style="text-align:right; padding:12px; border-bottom:1px solid #1e293b;">Действие</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $emp): ?>
                                <tr>
                                    <td style="padding:12px; border-bottom:1px solid #1e293b;"><?= htmlspecialchars($emp['email']) ?></td>
                                    <td style="padding:12px; border-bottom:1px solid #1e293b;">
                                        <?= (time() - strtotime($emp['last_seen']) < 300) ? '<span style="color:#10b981">● Онлайн</span>' : '<span style="color:#94a3b8">Офлайн</span>' ?>
                                    </td>
                                    <td style="padding:12px; border-bottom:1px solid #1e293b; text-align:right;">
                                        <form method="POST" onsubmit="return confirm('Удалить сотрудника?');">
                                            <input type="hidden" name="remove_employee" value="<?= $emp['id'] ?>">
                                            <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 0.8rem; width: auto;">Удалить</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php include __DIR__ . '/templates/modal.php'; ?>
</body>
</html>