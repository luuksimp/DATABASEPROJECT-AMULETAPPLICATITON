<?php
// กดไลก์ / โหวตแท้ / โหวตเก๊ — 1 คนต่อ 1 โพสต์ กดซ้ำชนิดเดิมเพื่อยกเลิก กดชนิดอื่นเพื่อเปลี่ยน
require_once __DIR__ . '/includes/functions.php';
$me = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
csrf_check();

$postId = (int)($_POST['post_id'] ?? 0);
$type = $_POST['type'] ?? '';
if (!in_array($type, ['like', 'real', 'fake'], true)) redirect('index.php');

$st = db()->prepare('SELECT 1 FROM posts WHERE Post_ID = ?');
$st->execute([$postId]);
if (!$st->fetch()) redirect('index.php');

$st = db()->prepare('SELECT Reaction_Type FROM reactions WHERE Post_ID = ? AND User_ID = ?');
$st->execute([$postId, $me['User_ID']]);
$existing = $st->fetchColumn();

if ($existing === $type) {
    db()->prepare('DELETE FROM reactions WHERE Post_ID = ? AND User_ID = ?')->execute([$postId, $me['User_ID']]);
} elseif ($existing) {
    db()->prepare('UPDATE reactions SET Reaction_Type = ?, Created_At = NOW() WHERE Post_ID = ? AND User_ID = ?')
        ->execute([$type, $postId, $me['User_ID']]);
} else {
    db()->prepare('INSERT INTO reactions (Post_ID, User_ID, Reaction_Type) VALUES (?,?,?)')
        ->execute([$postId, $me['User_ID'], $type]);
}

// กัน open redirect: รับเฉพาะ path ภายในเว็บ
$back = $_POST['back'] ?? 'index.php';
if (!preg_match('#^[A-Za-z0-9_./?=&%\#-]+$#', $back) || str_starts_with($back, '//') || str_contains($back, '://')) {
    $back = 'index.php';
}
redirect($back);
