<?php
require_once __DIR__ . '/functions.php';
$me = current_user();
$pageTitle = $pageTitle ?? SITE_NAME;
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> | <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
  <div class="wrap bar">
    <a class="brand" href="index.php">🪷 <?= e(SITE_NAME) ?></a>
    <nav>
      <a href="index.php">ฟีด</a>
      <a href="market.php">ตลาดพระ</a>
      <?php if ($me): ?>
        <?php if (in_array($me['Role'], ['Seller','Admin'], true)): ?>
          <a href="amulet_add.php">ลงขายพระ</a>
        <?php endif; ?>
        <a href="post_create.php">โพสต์</a>
        <a href="orders.php">คำสั่งซื้อ</a>
        <span class="who"><?= e($me['Username']) ?> (<?= e($me['Role']) ?>)</span>
        <a href="logout.php">ออก</a>
      <?php else: ?>
        <a href="login.php">เข้าสู่ระบบ</a>
        <a class="btn small" href="register.php">สมัครสมาชิก</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="wrap">
<?php foreach ($_SESSION['flash'] ?? [] as [$msg, $type]): ?>
  <div class="flash <?= $type === 'err' ? 'err' : 'ok' ?>"><?= e($msg) ?></div>
<?php endforeach; unset($_SESSION['flash']); ?>
