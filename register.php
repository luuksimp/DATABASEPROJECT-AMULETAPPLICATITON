<?php
require_once __DIR__ . '/includes/functions.php';
if (current_user()) redirect('index.php');

$errors = [];
$old = ['username' => '', 'email' => '', 'phone' => '', 'full_name' => '', 'role' => 'Buyer'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old = [
        'username'  => trim($_POST['username'] ?? ''),
        'email'     => trim($_POST['email'] ?? ''),
        'phone'     => trim($_POST['phone'] ?? ''),
        'full_name' => trim($_POST['full_name'] ?? ''),
        'role'      => ($_POST['role'] ?? '') === 'Seller' ? 'Seller' : 'Buyer',
    ];
    $pass = $_POST['password'] ?? '';

    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $old['username'])) $errors[] = 'ชื่อผู้ใช้ต้องเป็น a-z, 0-9, _ ความยาว 3-30 ตัว';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'อีเมลไม่ถูกต้อง';
    if ($old['full_name'] === '') $errors[] = 'กรุณากรอกชื่อ-นามสกุล';
    if (strlen($pass) < 8) $errors[] = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';

    if (!$errors) {
        $st = db()->prepare('SELECT 1 FROM users WHERE Username = ? OR Email = ?');
        $st->execute([$old['username'], $old['email']]);
        if ($st->fetch()) $errors[] = 'ชื่อผู้ใช้หรืออีเมลนี้ถูกใช้แล้ว';
    }
    if (!$errors) {
        db()->prepare('INSERT INTO users (Username, Password_Hash, Email, Phone, Role, Full_Name) VALUES (?,?,?,?,?,?)')
            ->execute([$old['username'], password_hash($pass, PASSWORD_DEFAULT), $old['email'], $old['phone'], $old['role'], $old['full_name']]);
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)db()->lastInsertId();
        flash('สมัครสมาชิกสำเร็จ ยินดีต้อนรับ');
        redirect('index.php');
    }
}

$pageTitle = 'สมัครสมาชิก';
require __DIR__ . '/includes/header.php';
?>
<div class="card" style="max-width:520px;margin:auto">
  <h1>สมัครสมาชิก</h1>
  <?php foreach ($errors as $er): ?><div class="flash err"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label>ชื่อผู้ใช้</label><input type="text" name="username" value="<?= e($old['username']) ?>" required>
    <label>ชื่อ-นามสกุล</label><input type="text" name="full_name" value="<?= e($old['full_name']) ?>" required>
    <label>อีเมล</label><input type="email" name="email" value="<?= e($old['email']) ?>" required>
    <label>เบอร์โทร</label><input type="text" name="phone" value="<?= e($old['phone']) ?>">
    <label>รหัสผ่าน (อย่างน้อย 8 ตัว)</label><input type="password" name="password" required>
    <label>ประเภทบัญชี</label>
    <select name="role">
      <option value="Buyer" <?= $old['role'] === 'Buyer' ? 'selected' : '' ?>>ผู้ซื้อ / ผู้เช่าบูชา</option>
      <option value="Seller" <?= $old['role'] === 'Seller' ? 'selected' : '' ?>>ผู้ขาย</option>
    </select>
    <p><button>สมัครสมาชิก</button> <a href="login.php">มีบัญชีแล้ว?</a></p>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
