<?php
require __DIR__ . '/config.php';
$user = requireAuth();
$taskId = (int)($_GET['id'] ?? 0);

// Проверяем, что задача принадлежит текущему пользователю
$stmt = $pdo->prepare("SELECT result_file, file_name FROM tasks WHERE id = ? AND user_id = ?");
$stmt->execute([$taskId, $user['id']]);
$task = $stmt->fetch();

if ($task && $task['result_file']) {
    $path = __DIR__ . '/storage/output/' . $task['result_file'];
    if (file_exists($path)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($task['result_file']) . '"');
        readfile($path);
        exit;
    }
}
http_response_code(404);
echo "Файл не найден";