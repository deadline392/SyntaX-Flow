<?php
require __DIR__ . '/config.php';
$user = requireAuth();

if ($user['plan_type'] !== 'corporate' || $user['role'] !== 'owner') {
    header('Location: /index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT email, last_seen FROM users WHERE company_owner_id = ?");
$stmt->execute([$user['id']]);
$employees = $stmt->fetchAll();
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Команда - SyntaX Flow</title>
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
            <li><a href="/index.php?tab=converter">🔄 <span>Конвертер</span></a></li>
            <li><a href="/index.php?tab=history">📜 <span>История</span></a></li>
            <li><a href="/team.php" class="active">👥 <span>Команда</span></a></li>
        </ul>
        <div class="sidebar-bottom">
            <div class="sidebar-profile" onclick="document.getElementById('modal').classList.add('active')">
                <div class="avatar-circle"><?= mb_substr($user['company_name'], 0, 1) ?></div>
                <span><?= htmlspecialchars($user['company_name']) ?></span>
            </div>
        </div>
    </aside>

    <main class="content-area">
        <div class="topbar">
            <h1>Управление командой</h1>
            <a href="/logout.php" class="btn btn-outline">Выйти</a>
        </div>

        <div class="card">
            <h2>Код приглашения</h2>
            <p style="color:var(--text-muted); margin-bottom:16px;">Отправьте этот код сотрудникам, чтобы они могли присоединиться.</p>
            
            <div style="display:flex; flex-direction:column; gap:12px;">
                <div style="background: rgba(0,0,0,0.3); padding: 20px; border-radius: 8px; font-size: 1.2rem; font-weight: bold; letter-spacing: 2px; color: var(--accent-purple); border: 1px dashed var(--accent-purple); text-align: center; word-break: break-all;">
                    <?= $user['invite_code'] ?? 'Код не сгенерирован' ?>
                </div>
                <button class="btn btn-primary" onclick="navigator.clipboard.writeText('<?= $user['invite_code'] ?? '' ?>'); this.textContent='Скопировано!'">Скопировать код</button>
            </div>
        </div>

        <div class="card" style="margin-top: 24px;">
            <h2>Сотрудники (<?= count($employees) ?>)</h2>
            <?php if (empty($employees)): ?>
                <p style="color:var(--text-muted);">Пока никто не присоединился.</p>
            <?php else: ?>
                <table style="width:100%; border-collapse:collapse; margin-top: 16px;">
                    <thead>
                        <tr>
                            <th style="text-align:left; padding:12px; border-bottom:1px solid #1e293b;">Email</th>
                            <th style="text-align:left; padding:12px; border-bottom:1px solid #1e293b;">Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td style="padding:12px; border-bottom:1px solid #1e293b;"><?= htmlspecialchars($emp['email']) ?></td>
                                <td style="padding:12px; border-bottom:1px solid #1e293b;">
                                    <?= (time() - strtotime($emp['last_seen']) < 300) ? '<span style="color:#10b981">● Онлайн</span>' : '<span style="color:#94a3b8">Офлайн</span>' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>