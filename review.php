<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_login();
$oid = (int)($_GET['order_id'] ?? $_POST['order_id'] ?? 0);

$st = db()->prepare(
    'SELECT o.*, a.Name AS AmuletName, a.Seller_ID FROM orders o JOIN amulets a ON a.Amulet_ID = o.Amulet_ID
     WHERE o.Order_ID = ? AND o.Buyer_ID = ?'
);
$st->execute([$oid, $me['User_ID']]);
$o = $st->fetch();
if (!$o || $o['Payment_Status'] === 'รอชำระ') { flash('ไม่พบคำสั่งซื้อที่รีวิวได้', 'err'); redirect('orders.php'); }

$dup = db()->prepare('SELECT 1 FROM reviews_disputes WHERE Order_ID = ?');
$dup->execute([$oid]);
if ($dup->fetch()) { flash('คำสั่งซื้อนี้ส่งรีวิวแล้ว', 'err'); redirect('orders.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    $dispute = $_POST['dispute'] ?? 'ไม่มี';
    if (!in_array($dispute, ['ไม่มี', 'แจ้งพระเก๊', 'ไม่ได้รับของ'], true)) $dispute = 'ไม่มี';
    if ($rating < 1 || $rating > 5) $errors[] = 'กรุณาให้คะแนน 1-5 ดาว';

    if (!$errors) {
        db()->prepare('INSERT INTO reviews_disputes (Order_ID, Rating, Comment, Dispute_Status) VALUES (?,?,?,?)')
            ->execute([$oid, $rating, $comment, $dispute]);
        update_seller_rating((int)$o['Seller_ID']);
        flash($dispute === 'ไม่มี' ? 'ขอบคุณสำหรับรีวิว' : 'ส่งเรื่องร้องเรียนแล้ว ทีมงานจะตรวจสอบ');
        redirect('orders.php');
    }
}

$pageTitle = 'รีวิว / แจ้งปัญหา';
require __DIR__ . '/includes/header.php';
?>
<div class="card" style="max-width:560px;margin:auto">
  <h1>รีวิว: <?= e($o['AmuletName']) ?></h1>
  <?php foreach ($errors as $er): ?><div class="flash err"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" class="stack">
    <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $oid ?>">
    <label>คะแนน</label>
    <select name="rating">
      <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= str_repeat('★', $i) . str_repeat('☆', 5 - $i) ?> (<?= $i ?>)</option><?php endfor; ?>
    </select>
    <label>ข้อความรีวิว</label><textarea name="comment"></textarea>
    <label>แจ้งปัญหา (ถ้ามี)</label>
    <select name="dispute"><option value="ไม่มี">ไม่มีปัญหา</option><option value="แจ้งพระเก๊">แจ้งพระเก๊</option><option value="ไม่ได้รับของ">ไม่ได้รับของ</option></select>
    <p><button>ส่ง</button></p>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
