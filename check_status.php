<?php
require __DIR__ . '/config.php';
$user = requireAuth();
$taskId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT status, result_file, error_message FROM tasks WHERE id = ? AND user_id = ?");
$stmt->execute([$taskId, $user['id']]);
$task = $stmt->fetch();

jsonResponse($task ?: ['status' => 'not_found']);