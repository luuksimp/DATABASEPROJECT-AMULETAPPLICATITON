<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_login();
$uid = (int)$me['User_ID'];

// อัปเดตสถานะคำสั่งซื้อ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $oid = (int)($_POST['order_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $st = db()->prepare('SELECT o.*, a.Seller_ID FROM orders o JOIN amulets a ON a.Amulet_ID = o.Amulet_ID WHERE o.Order_ID = ?');
    $st->execute([$oid]);
    $o = $st->fetch();

    if ($o) {
        $isBuyer = (int)$o['Buyer_ID'] === $uid;
        $isSeller = (int)$o['Seller_ID'] === $uid;

        if ($action === 'pay' && $isBuyer && $o['Payment_Status'] === 'รอชำระ') {
            // จำลองการโอนผ่านระบบตัวกลาง (ในระบบจริงให้เชื่อม payment gateway / escrow)
            db()->prepare("UPDATE orders SET Payment_Status = 'โอนเงินผ่านระบบตัวกลาง' WHERE Order_ID = ?")->execute([$oid]);
            flash('บันทึกการชำระเงินผ่านระบบตัวกลางแล้ว');
        } elseif ($action === 'ship' && $isSeller && $o['Payment_Status'] !== 'รอชำระ' && $o['Shipping_Status'] === 'รอดำเนินการ') {
            $track = trim($_POST['tracking'] ?? '');
            if ($track === '') { flash('กรุณากรอกเลขพัสดุ', 'err'); }
            else {
                db()->prepare("UPDATE orders SET Shipping_Status = 'จัดส่งแล้ว', Tracking_Number = ? WHERE Order_ID = ?")->execute([$track, $oid]);
                flash('บันทึกการจัดส่งแล้ว');
            }
        } elseif ($action === 'received' && $isBuyer && $o['Shipping_Status'] === 'จัดส่งแล้ว') {
            $pdo = db();
            $pdo->prepare("UPDATE orders SET Shipping_Status = 'ได้รับของแล้ว' WHERE Order_ID = ?")->execute([$oid]);
            $pdo->prepare("UPDATE amulets SET Stock_Status = 'ปล่อยเช่าแล้ว' WHERE Amulet_ID = ?")->execute([$o['Amulet_ID']]);
            flash('ยืนยันรับของแล้ว');
        } elseif ($action === 'cancel' && $isBuyer && $o['Payment_Status'] === 'รอชำระ' && $o['Shipping_Status'] === 'รอดำเนินการ') {
            $hasReview = db()->prepare('SELECT 1 FROM reviews_disputes WHERE Order_ID = ?');
            $hasReview->execute([$oid]);
            if (!$hasReview->fetch()) {
                db()->prepare('DELETE FROM orders WHERE Order_ID = ?')->execute([$oid]);
                db()->prepare("UPDATE amulets SET Stock_Status = 'พร้อมเช่า' WHERE Amulet_ID = ?")->execute([$o['Amulet_ID']]);
                flash('ยกเลิกคำสั่งซื้อแล้ว');
            }
        }
    }
    redirect('orders.php');
}

$sel = 'SELECT o.*, a.Name AS AmuletName, a.Seller_ID, b.Username AS BuyerName, s.Username AS SellerName,
               (SELECT Review_ID FROM reviews_disputes r WHERE r.Order_ID = o.Order_ID LIMIT 1) AS ReviewID
        FROM orders o
        JOIN amulets a ON a.Amulet_ID = o.Amulet_ID
        JOIN users b ON b.User_ID = o.Buyer_ID
        JOIN users s ON s.User_ID = a.Seller_ID ';
$st = db()->prepare($sel . 'WHERE o.Buyer_ID = ? ORDER BY o.Order_Date DESC');
$st->execute([$uid]);
$bought = $st->fetchAll();
$st = db()->prepare($sel . 'WHERE a.Seller_ID = ? ORDER BY o.Order_Date DESC');
$st->execute([$uid]);
$sold = $st->fetchAll();

$pageTitle = 'คำสั่งซื้อ';
require __DIR__ . '/includes/header.php';
?>
<h1>คำสั่งซื้อของฉัน (ผู้ซื้อ)</h1>
<?php if (!$bought): ?><div class="card muted">ยังไม่มีคำสั่งซื้อ</div><?php endif; ?>
<?php foreach ($bought as $o): ?>
  <div class="card" id="order-<?= (int)$o['Order_ID'] ?>">
    <strong>#<?= (int)$o['Order_ID'] ?> · <a href="amulet.php?id=<?= (int)$o['Amulet_ID'] ?>"><?= e($o['AmuletName']) ?></a></strong>
    <div class="muted">ผู้ขาย @<?= e($o['SellerName']) ?> · <?= e($o['Order_Date']) ?></div>
    <p><span class="price"><?= baht($o['Total_Amount']) ?></span>
      <span class="badge"><?= e($o['Payment_Status']) ?></span>
      <span class="badge"><?= e($o['Shipping_Status']) ?></span>
      <?php if ($o['Tracking_Number']): ?><span class="muted">เลขพัสดุ: <?= e($o['Tracking_Number']) ?></span><?php endif; ?></p>
    <div class="actions">
      <?php if ($o['Payment_Status'] === 'รอชำระ'): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['Order_ID'] ?>"><input type="hidden" name="action" value="pay"><button>ชำระเงินผ่านระบบตัวกลาง</button></form>
        <form method="post" onsubmit="return confirm('ยกเลิกคำสั่งซื้อ?')"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['Order_ID'] ?>"><input type="hidden" name="action" value="cancel"><button>ยกเลิก</button></form>
      <?php endif; ?>
      <?php if ($o['Shipping_Status'] === 'จัดส่งแล้ว'): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['Order_ID'] ?>"><input type="hidden" name="action" value="received"><button>ยืนยันได้รับของแล้ว</button></form>
      <?php endif; ?>
      <?php if ($o['Payment_Status'] !== 'รอชำระ' && !$o['ReviewID']): ?>
        <a class="btn ghost small" href="review.php?order_id=<?= (int)$o['Order_ID'] ?>">รีวิว / แจ้งปัญหา</a>
      <?php elseif ($o['ReviewID']): ?><span class="badge green">ส่งรีวิว/แจ้งเรื่องแล้ว</span><?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>

<?php if (in_array($me['Role'], ['Seller', 'Admin'], true)): ?>
<h1>คำสั่งซื้อที่ได้รับ (ผู้ขาย)</h1>
<?php if (!$sold): ?><div class="card muted">ยังไม่มีคำสั่งซื้อ</div><?php endif; ?>
<?php foreach ($sold as $o): ?>
  <div class="card">
    <strong>#<?= (int)$o['Order_ID'] ?> · <?= e($o['AmuletName']) ?></strong>
    <div class="muted">ผู้ซื้อ @<?= e($o['BuyerName']) ?> · <?= e($o['Order_Date']) ?></div>
    <p><span class="price"><?= baht($o['Total_Amount']) ?></span>
      <span class="badge"><?= e($o['Payment_Status']) ?></span>
      <span class="badge"><?= e($o['Shipping_Status']) ?></span>
      <?php if ($o['Tracking_Number']): ?><span class="muted">เลขพัสดุ: <?= e($o['Tracking_Number']) ?></span><?php endif; ?></p>
    <?php if ($o['Payment_Status'] !== 'รอชำระ' && $o['Shipping_Status'] === 'รอดำเนินการ'): ?>
      <form method="post" class="actions"><?= csrf_field() ?>
        <input type="hidden" name="order_id" value="<?= (int)$o['Order_ID'] ?>"><input type="hidden" name="action" value="ship">
        <input type="text" name="tracking" placeholder="เลขพัสดุ" style="max-width:240px" required>
        <button>บันทึกการจัดส่ง</button>
      </form>
    <?php elseif ($o['Payment_Status'] === 'รอชำระ'): ?><span class="muted">รอผู้ซื้อชำระเงิน</span><?php endif; ?>
  </div>
<?php endforeach; endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
