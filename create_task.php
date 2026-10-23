<?php
require __DIR__ . '/config.php';
$user = requireAuth();

try {
    if (!isset($_FILES['source'])) {
        jsonResponse(['ok' => false, 'error' => 'Файл не загружен']);
    }

    $fileData = file_get_contents($_FILES['source']['tmp_name']);
    $fileName = basename($_FILES['source']['name']);
    $taskType = $_POST['task_type'] ?? 'excel_to_pdf';
    
    $targetData = null;
    $targetName = null;

    if ($taskType === 'excel_merge') {
        if (!isset($_FILES['target'])) {
            jsonResponse(['ok' => false, 'error' => 'Целевой файл не загружен']);
        }
        $targetData = file_get_contents($_FILES['target']['tmp_name']);
        $targetName = basename($_FILES['target']['name']);
    }

    $stmt = $pdo->prepare("INSERT INTO tasks (user_id, task_type, source_file, file_name, target_file, target_file_name, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bindParam(1, $user['id']);
    $stmt->bindParam(2, $taskType);
    $stmt->bindParam(3, $fileData, PDO::PARAM_LOB);
    $stmt->bindParam(4, $fileName);
    $stmt->bindParam(5, $targetData, PDO::PARAM_LOB);
    $stmt->bindParam(6, $targetName);
    $stmt->execute();

    jsonResponse(['ok' => true, 'task_id' => $pdo->lastInsertId()]);
} catch (Throwable $e) {
    jsonResponse(['ok' => false, 'error' => $e->getMessage()]);
}