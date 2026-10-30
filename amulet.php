<?php
require_once __DIR__ . '/includes/functions.php';
$id = (int)($_GET['id'] ?? 0);

$st = db()->prepare(
    'SELECT a.*, c.Category_Name, u.User_ID AS SellerUID, u.Username, u.Full_Name, u.Verification_Status, u.Rating_Score
     FROM amulets a JOIN categories c ON c.Category_ID = a.Category_ID JOIN users u ON u.User_ID = a.Seller_ID
     WHERE a.Amulet_ID = ?'
);
$st->execute([$id]);
$a = $st->fetch();
if (!$a) { http_response_code(404); exit('ไม่พบพระเครื่อง'); }

$imgs = db()->prepare('SELECT * FROM amulet_images WHERE Amulet_ID = ? ORDER BY Is_Primary DESC, Image_ID');
$imgs->execute([$id]);
$images = $imgs->fetchAll();

$pageTitle = $a['Name'];
require __DIR__ . '/includes/header.php';
$isOwner = $me && (int)$me['User_ID'] === (int)$a['SellerUID'];
?>
<div class="card">
  <h1><?= e($a['Name']) ?></h1>
  <?php if ($images): ?>
    <img class="main-img" src="<?= e($images[0]['Image_URL']) ?>" alt="">
    <?php if (count($images) > 1): ?>
      <div class="gallery" style="margin-top:8px">
        <?php foreach (array_slice($images, 1) as $im): ?><a href="<?= e($im['Image_URL']) ?>" target="_blank"><img src="<?= e($im['Image_URL']) ?>" alt=""></a><?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <p class="price" style="font-size:1.5rem"><?= baht($a['Price']) ?>
    <span class="badge <?= $a['Stock_Status'] === 'พร้อมเช่า' ? 'green' : 'gray' ?>"><?= e($a['Stock_Status']) ?></span></p>

  <table>
    <tr><th>หมวดหมู่</th><td><?= e($a['Category_Name']) ?></td></tr>
    <tr><th>พระเกจิ/ผู้สร้าง</th><td><?= e($a['Monk_Creator'] ?: '-') ?></td></tr>
    <tr><th>วัด</th><td><?= e($a['Temple'] ?: '-') ?></td></tr>
    <tr><th>ปีที่สร้าง (พ.ศ.)</th><td><?= $a['Year_Created'] ? (int)$a['Year_Created'] : '-' ?></td></tr>
    <tr><th>เนื้อพระ</th><td><?= e($a['Material'] ?: '-') ?></td></tr>
    <tr><th>สภาพ</th><td><?= e($a['Condition_Desc'] ?: '-') ?></td></tr>
    <tr><th>ใบรับรอง</th><td><?= e($a['Certificate_Info'] ?: 'ไม่มี') ?></td></tr>
    <tr><th>เงื่อนไขรับประกัน</th><td><?= e($a['Guarantee_Policy'] ?: '-') ?></td></tr>
  </table>
  <?php if ($a['Description']): ?><h2>รายละเอียด</h2><p><?= nl2br(e($a['Description'])) ?></p><?php endif; ?>

  <p class="muted">ผู้ขาย: <strong><?= e($a['Full_Name']) ?></strong> @<?= e($a['Username']) ?>
    <?php if ($a['Verification_Status'] === 'ยืนยันแล้ว'): ?><span class="badge green">ยืนยันตัวตนแล้ว</span><?php endif; ?>
    · คะแนน <?= number_format((float)$a['Rating_Score'], 2) ?>/5</p>

  <?php if ($a['Stock_Status'] === 'พร้อมเช่า' && !$isOwner): ?>
    <?php if ($me): ?>
      <form method="post" action="order.php" onsubmit="return confirm('ยืนยันการเช่าบูชาพระองค์นี้?')">
        <?= csrf_field() ?><input type="hidden" name="amulet_id" value="<?= $id ?>">
        <button>เช่าบูชาเลย</button>
      </form>
    <?php else: ?>
      <a class="btn" href="login.php">เข้าสู่ระบบเพื่อเช่าบูชา</a>
    <?php endif; ?>
  <?php endif; ?>
  <?php if ($isOwner): ?><p class="muted">นี่คือพระของคุณ</p><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
