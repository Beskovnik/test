<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

$admin = current_user($pdo);
if (!$admin || $admin['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$type = $input['type'] ?? '';
$id = (int)($input['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid ID']);
    exit;
}

try {
    if ($type === 'user') {
        if ($id === (int)$admin['id']) {
            echo json_encode(['ok' => false, 'message' => 'Ne morete izbrisati samega sebe']);
            exit;
        }
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        echo json_encode(['ok' => true, 'message' => 'Uporabnik izbrisan']);
    } elseif ($type === 'post') {
        // Get file path to delete file
        $stmt = $pdo->prepare('SELECT file_path, thumb_path FROM posts WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $post = $stmt->fetch();

        if ($post) {
            $stmt = $pdo->prepare('DELETE FROM posts WHERE id = :id');
            $stmt->execute([':id' => $id]);

            // Try delete files
            if (file_exists(__DIR__ . '/../../' . $post['file_path'])) {
                @unlink(__DIR__ . '/../../' . $post['file_path']);
            }
            if (file_exists(__DIR__ . '/../../' . $post['thumb_path'])) {
                @unlink(__DIR__ . '/../../' . $post['thumb_path']);
            }
            echo json_encode(['ok' => true, 'message' => 'Objava izbrisana']);
        } else {
            echo json_encode(['ok' => false, 'message' => 'Objava ne obstaja']);
        }
    } else {
        echo json_encode(['ok' => false, 'message' => 'Invalid type']);
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}
