<?php
require_once __DIR__ . '/includes/functions.php';
if (current_user()) redirect('index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = trim($_POST['username'] ?? '');
    $st = db()->prepare('SELECT * FROM users WHERE Username = ? OR Email = ?');
    $st->execute([$id, $id]);
    $u = $st->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['Password_Hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['User_ID'];
        redirect('index.php');
    }
    $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
}

$pageTitle = 'เข้าสู่ระบบ';
require __DIR__ . '/includes/header.php';
?>
<div class="card" style="max-width:420px;margin:auto">
  <h1>เข้าสู่ระบบ</h1>
  <?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label>ชื่อผู้ใช้ หรือ อีเมล</label><input type="text" name="username" required>
    <label>รหัสผ่าน</label><input type="password" name="password" required>
    <p><button>เข้าสู่ระบบ</button> <a href="register.php">สมัครสมาชิก</a></p>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
