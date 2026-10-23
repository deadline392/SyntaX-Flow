<?php
require __DIR__ . '/config.php';
$user = requireAuth();
$isSubscribed = $user['subscription_until'] && strtotime($user['subscription_until']) > time();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['pay'])) {
        $months = (int)$_POST['months'];
        $plan = $_POST['plan'];
        $seats = ($plan === 'corporate') ? (int)($_POST['seats'] ?? 5) : 1;
        $baseDate = $isSubscribed ? strtotime($user['subscription_until']) : time();
        $newDate = date('Y-m-d H:i:s', strtotime("+{$months} months", $baseDate));
        
        $pdo->prepare("UPDATE users SET subscription_until = ?, plan_type = ?, max_seats = ? WHERE id = ?")->execute([$newDate, $plan, $seats, $user['id']]);
        header('Location: /index.php');
        exit;
    }
    if (isset($_POST['cancel_sub'])) {
        $pdo->prepare("UPDATE users SET subscription_until = NULL WHERE id = ?")->execute([$user['id']]);
        header('Location: /index.php');
        exit;
    }
}

$tab = $_GET['tab'] ?? 'excel_excel';
$history = [];
if ($tab === 'history') {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$user['id']]);
    $history = $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>SyntaX Flow</title>
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
            <li><a href="/index.php?tab=excel_excel" class="<?= $tab === 'excel_excel' ? 'active' : '' ?>">🔄 <span>Excel + Excel</span></a></li>
            <li><a href="/index.php?tab=pdf" class="<?= $tab === 'pdf' ? 'active' : '' ?>">📄 <span>Excel + PDF</span></a></li>
            <li><a href="/index.php?tab=1c" class="<?= $tab === '1c' ? 'active' : '' ?>">📊 <span>Excel + 1С</span></a></li>
            <li style="margin-top: 24px;"><a href="/index.php?tab=history" class="<?= $tab === 'history' ? 'active' : '' ?>">📜 <span>История</span></a></li>
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
            <h1>
                <?php 
                if ($tab === 'excel_excel') echo 'Excel + Excel';
                elseif ($tab === 'pdf') echo 'Excel + PDF';
                elseif ($tab === '1c') echo 'Excel + 1С';
                else echo 'История операций';
                ?>
            </h1>
            <a href="/logout.php" class="btn btn-outline">Выйти</a>
        </div>

        <?php if (!$isSubscribed && $tab !== 'history'): ?>
            <div class="card" style="text-align:center; padding: 60px 20px;">
                <h2>Подписка неактивна</h2>
                <p style="margin-bottom:24px; color:var(--text-muted);">Оформите подписку, чтобы использовать конвертер.</p>
                <button onclick="document.getElementById('modal').classList.add('active')" class="btn btn-primary">Оформить подписку</button>
            </div>
        <?php else: ?>
            <?php if ($tab === 'excel_excel'): ?>
                <div class="card">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
                        <h2 style="margin:0;" id="modeTitle">Слияние Excel</h2>
                        <button class="btn btn-outline" onclick="toggleDirection()" style="padding: 8px 16px;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 5px;"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                            <span id="modeBtnText">Реверс</span>
                        </button>
                    </div>
                    <form id="uploadForm" enctype="multipart/form-data">
                        <input type="hidden" name="task_type" id="taskType" value="excel_merge">
                        
                        <div class="grid-2" id="uploadFields">
                            <label class="file-upload">
                                <input type="file" name="source" accept=".xlsx" required>
                                <div class="icon">↓</div>
                                <span>Файл 1 (откуда)</span>
                            </label>
                            <label class="file-upload">
                                <input type="file" name="target" accept=".xlsx" required>
                                <div class="icon">↓</div>
                                <span>Файл 2 (куда)</span>
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%; padding:14px;">Запустить</button>
                    </form>
                    <div id="status" style="margin-top:16px; text-align:center; color:var(--accent-cyan);"></div>
                    <div id="result" style="display:none; margin-top:24px; text-align:center;">
                        <a id="dl" class="btn btn-primary" href="#" style="background:var(--accent-green);">Скачать результат</a>
                    </div>
                </div>

            <?php elseif ($tab === 'pdf'): ?>
                <div class="card">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
                        <h2 style="margin:0;" id="modeTitle">Excel → PDF</h2>
                        <button class="btn btn-outline" onclick="toggleDirection()" style="padding: 8px 16px;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 5px;"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                            <span id="modeBtnText">Реверс</span>
                        </button>
                    </div>
                    <form id="uploadForm" enctype="multipart/form-data">
                        <input type="hidden" name="task_type" id="taskType" value="excel_to_pdf">
                        
                        <div class="grid-2 single" id="uploadFields">
                            <label class="file-upload" id="sourceWrapper">
                                <input type="file" name="source" id="sourceFile" accept=".xlsx" required>
                                <div class="icon">↓</div>
                                <span id="sourceName">Исходный Excel<br><small>Нажмите или перетащите</small></span>
                            </label>
                            <label class="file-upload" id="targetWrapper" style="display:none;">
                                <input type="file" name="target" id="targetFile" accept=".pdf">
                                <div class="icon">↑</div>
                                <span id="targetName">Исходный PDF<br><small>Нажмите или перетащите</small></span>
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%; padding:14px;">Запустить</button>
                    </form>
                    <div id="status" style="margin-top:16px; text-align:center; color:var(--accent-cyan);"></div>
                    <div id="result" style="display:none; margin-top:24px; text-align:center;">
                        <a id="dl" class="btn btn-primary" href="#" style="background:var(--accent-green);">Скачать результат</a>
                    </div>
                </div>

            <?php elseif ($tab === '1c'): ?>
                <div class="card">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
                        <h2 style="margin:0;" id="modeTitle">Excel → 1С</h2>
                        <button class="btn btn-outline" onclick="toggleDirection()" style="padding: 8px 16px;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 5px;"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                            <span id="modeBtnText">Реверс</span>
                        </button>
                    </div>
                    <form id="uploadForm" enctype="multipart/form-data">
                        <input type="hidden" name="task_type" id="taskType" value="excel_to_1c">
                        
                        <div class="grid-2 single" id="uploadFields">
                            <label class="file-upload" id="sourceWrapper">
                                <input type="file" name="source" id="sourceFile" accept=".xlsx" required>
                                <div class="icon">↓</div>
                                <span id="sourceName">Исходный Excel<br><small>Нажмите или перетащите</small></span>
                            </label>
                            <label class="file-upload" id="targetWrapper" style="display:none;">
                                <input type="file" name="target" id="targetFile" accept=".txt">
                                <div class="icon">↑</div>
                                <span id="targetName">Исходный 1С (txt)<br><small>Нажмите или перетащите</small></span>
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%; padding:14px;">Запустить</button>
                    </form>
                    <div id="status" style="margin-top:16px; text-align:center; color:var(--accent-cyan);"></div>
                    <div id="result" style="display:none; margin-top:24px; text-align:center;">
                        <a id="dl" class="btn btn-primary" href="#" style="background:var(--accent-green);">Скачать результат</a>
                    </div>
                </div>

            <?php elseif ($tab === 'history'): ?>
                <div class="card">
                    <?php if (empty($history)): ?>
                        <p style="color:var(--text-muted);">У вас пока нет операций.</p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding:12px; border-bottom:1px solid #1e293b;">Дата</th>
                                        <th style="text-align:left; padding:12px; border-bottom:1px solid #1e293b;">Тип</th>
                                        <th style="text-align:left; padding:12px; border-bottom:1px solid #1e293b;">Статус</th>
                                        <th style="text-align:left; padding:12px; border-bottom:1px solid #1e293b;">Файл</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($history as $item): ?>
                                        <tr>
                                            <td style="padding:12px; border-bottom:1px solid #1e293b;"><?= date('d.m.Y H:i', strtotime($item['created_at'])) ?></td>
                                            <td style="padding:12px; border-bottom:1px solid #1e293b;"><?= $item['task_type'] ?></td>
                                            <td style="padding:12px; border-bottom:1px solid #1e293b;"><?= $item['status'] === 'done' ? '<span style="color:#10b981">Готово</span>' : '<span style="color:#06b6d4">В обработке</span>' ?></td>
                                            <td style="padding:12px; border-bottom:1px solid #1e293b;">
                                                <?php if ($item['status'] === 'done'): ?>
                                                    <a href="/download.php?id=<?= $item['id'] ?>" style="color:#06b6d4;">Скачать</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>

<?php include __DIR__ . '/templates/modal.php'; ?>

<script>
let currentTab = '<?= $tab ?>';
let isReversed = false;

function toggleDirection() {
    isReversed = !isReversed;
    const title = document.getElementById('modeTitle');
    const taskType = document.getElementById('taskType');
    const uploadFields = document.getElementById('uploadFields');
    const sourceFile = document.getElementById('sourceFile');
    const targetWrapper = document.getElementById('targetWrapper');
    const targetFile = document.getElementById('targetFile');
    
    if (currentTab === 'pdf') {
        if (isReversed) {
            title.innerText = 'PDF → Excel';
            taskType.value = 'pdf_to_excel';
            sourceFile.accept = '.pdf';
            document.getElementById('sourceName').innerHTML = 'Исходный PDF<br><small>Нажмите или перетащите</small>';
            if (targetWrapper) targetWrapper.style.display = 'none';
            uploadFields.classList.add('single');
        } else {
            title.innerText = 'Excel → PDF';
            taskType.value = 'excel_to_pdf';
            sourceFile.accept = '.xlsx';
            document.getElementById('sourceName').innerHTML = 'Исходный Excel<br><small>Нажмите или перетащите</small>';
            if (targetWrapper) targetWrapper.style.display = 'none';
            uploadFields.classList.add('single');
        }
    } 
    else if (currentTab === '1c') {
        if (isReversed) {
            title.innerText = '1С → Excel';
            taskType.value = '1c_to_excel';
            sourceFile.accept = '.txt';
            document.getElementById('sourceName').innerHTML = 'Исходный 1С (txt)<br><small>Нажмите или перетащите</small>';
            if (targetWrapper) targetWrapper.style.display = 'none';
            uploadFields.classList.add('single');
        } else {
            title.innerText = 'Excel → 1С';
            taskType.value = 'excel_to_1c';
            sourceFile.accept = '.xlsx';
            document.getElementById('sourceName').innerHTML = 'Исходный Excel<br><small>Нажмите или перетащите</small>';
            if (targetWrapper) targetWrapper.style.display = 'none';
            uploadFields.classList.add('single');
        }
    }
    else if (currentTab === 'excel_excel') {
        if (isReversed) {
            title.innerText = 'Разделение Excel';
            taskType.value = 'excel_split';
        } else {
            title.innerText = 'Слияние Excel';
            taskType.value = 'excel_merge';
        }
    }
}

document.getElementById('sourceFile')?.addEventListener('change', function(e) {
    if (this.files.length > 0) {
        document.getElementById('sourceName').innerHTML = `<strong style="color:var(--accent-green)">${this.files[0].name}</strong><br><small>Файл выбран</small>`;
        this.parentElement.style.borderColor = 'var(--accent-green)';
    }
});

document.getElementById('uploadForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const status = document.getElementById('status');
    status.textContent = 'Загрузка...';
    
    const res = await fetch('/create_task.php', { method: 'POST', body: new FormData(e.target) });
    const data = await res.json();
    
    if (data.ok) {
        status.textContent = 'В очереди...';
        const interval = setInterval(async () => {
            const r = await fetch('/check_status.php?id=' + data.task_id);
            const st = await r.json();
            if (st.status === 'done') {
                clearInterval(interval);
                status.textContent = 'Готово!';
                document.getElementById('result').style.display = 'block';
                document.getElementById('dl').href = '/download.php?id=' + data.task_id;
            } else if (st.status === 'error') {
                clearInterval(interval);
                status.textContent = 'Ошибка: ' + st.error_message;
            }
        }, 1000);
    } else {
        status.textContent = data.error;
    }
});
</script>
</body>
</html>