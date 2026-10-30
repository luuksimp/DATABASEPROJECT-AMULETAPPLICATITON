<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
    return $pdo;
}

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function redirect(string $url): never { header('Location: ' . $url); exit; }

function current_user(): ?array {
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['uid'])) {
            $st = db()->prepare('SELECT * FROM users WHERE User_ID = ?');
            $st->execute([$_SESSION['uid']]);
            $user = $st->fetch() ?: null;
        }
    }
    return $user;
}

function require_login(): array {
    $u = current_user();
    if (!$u) { flash('กรุณาเข้าสู่ระบบก่อน', 'err'); redirect('login.php'); }
    return $u;
}

function require_seller(): array {
    $u = require_login();
    if (!in_array($u['Role'], ['Seller', 'Admin'], true)) {
        flash('ต้องเป็นบัญชีผู้ขายเท่านั้น', 'err'); redirect('index.php');
    }
    return $u;
}

function flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'][] = [$msg, $type]; }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400); exit('CSRF token ไม่ถูกต้อง กรุณาย้อนกลับและลองใหม่');
    }
}

/** อัปโหลดรูป คืนค่า path แบบ relative หรือ null; โยน Exception ถ้าไฟล์ไม่ผ่าน */
function save_image(array $file): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('อัปโหลดรูปไม่สำเร็จ');
    if ($file['size'] > MAX_UPLOAD_BYTES) throw new RuntimeException('รูปมีขนาดเกิน 3MB');
    $info = @getimagesize($file['tmp_name']);
    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($allowed[$info[2]])) throw new RuntimeException('รองรับเฉพาะไฟล์ JPG, PNG, WEBP');
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    $name = bin2hex(random_bytes(12)) . '.' . $allowed[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name)) throw new RuntimeException('บันทึกรูปไม่สำเร็จ');
    return UPLOAD_URL . $name;
}

function baht($n): string { return '฿' . number_format((float)$n, 0); }

function time_ago(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60) return 'เมื่อสักครู่';
    if ($diff < 3600) return floor($diff / 60) . ' นาทีที่แล้ว';
    if ($diff < 86400) return floor($diff / 3600) . ' ชั่วโมงที่แล้ว';
    if ($diff < 604800) return floor($diff / 86400) . ' วันที่แล้ว';
    return date('d/m/Y', strtotime($dt));
}

function amulet_cover(int $amuletId): ?string {
    $st = db()->prepare('SELECT Image_URL FROM amulet_images WHERE Amulet_ID = ? ORDER BY Is_Primary DESC, Image_ID LIMIT 1');
    $st->execute([$amuletId]);
    return $st->fetchColumn() ?: null;
}

function update_seller_rating(int $sellerId): void {
    $st = db()->prepare(
        'SELECT AVG(r.Rating) FROM reviews_disputes r
         JOIN orders o ON o.Order_ID = r.Order_ID
         JOIN amulets a ON a.Amulet_ID = o.Amulet_ID
         WHERE a.Seller_ID = ? AND r.Rating IS NOT NULL'
    );
    $st->execute([$sellerId]);
    $avg = (float)$st->fetchColumn();
    db()->prepare('UPDATE users SET Rating_Score = ? WHERE User_ID = ?')->execute([round($avg, 2), $sellerId]);
}

/** ดึงโพสต์พร้อมสถิติ ไลก์/โหวต/คอมเมนต์ และ reaction ของผู้ใช้ปัจจุบัน */
function fetch_posts(string $where = '1=1', array $params = [], string $order = 'p.Created_At DESC', int $limit = 20): array {
    $uid = (int)($_SESSION['uid'] ?? 0);
    $sql = "SELECT p.*, u.Username, u.Full_Name, u.Verification_Status,
              a.Name AS AmuletName, a.Price AS AmuletPrice, a.Stock_Status,
              (SELECT COUNT(*) FROM reactions r WHERE r.Post_ID = p.Post_ID AND r.Reaction_Type='like') AS likes,
              (SELECT COUNT(*) FROM reactions r WHERE r.Post_ID = p.Post_ID AND r.Reaction_Type='real') AS reals,
              (SELECT COUNT(*) FROM reactions r WHERE r.Post_ID = p.Post_ID AND r.Reaction_Type='fake') AS fakes,
              (SELECT COUNT(*) FROM comments c WHERE c.Post_ID = p.Post_ID) AS comment_count,
              (SELECT r.Reaction_Type FROM reactions r WHERE r.Post_ID = p.Post_ID AND r.User_ID = ?) AS my_reaction
            FROM posts p
            JOIN users u ON u.User_ID = p.User_ID
            LEFT JOIN amulets a ON a.Amulet_ID = p.Amulet_ID
            WHERE $where ORDER BY $order LIMIT " . (int)$limit;
    $st = db()->prepare($sql);
    $st->execute(array_merge([$uid], $params));
    return $st->fetchAll();
}
