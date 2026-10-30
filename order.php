<?php
// สร้างคำสั่งซื้อ — ใช้ transaction + FOR UPDATE กันการซื้อซ้ำพระองค์เดียวกัน
require_once __DIR__ . '/includes/functions.php';
$me = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('market.php');
csrf_check();

$aid = (int)($_POST['amulet_id'] ?? 0);
$pdo = db();
$pdo->beginTransaction();
try {
    $st = $pdo->prepare('SELECT * FROM amulets WHERE Amulet_ID = ? FOR UPDATE');
    $st->execute([$aid]);
    $a = $st->fetch();
    if (!$a || $a['Stock_Status'] !== 'พร้อมเช่า') throw new RuntimeException('พระองค์นี้ไม่พร้อมให้เช่าแล้ว');
    if ((int)$a['Seller_ID'] === (int)$me['User_ID']) throw new RuntimeException('ไม่สามารถเช่าพระของตัวเองได้');

    $pdo->prepare('INSERT INTO orders (Buyer_ID, Amulet_ID, Total_Amount) VALUES (?,?,?)')
        ->execute([$me['User_ID'], $aid, $a['Price']]);
    $oid = (int)$pdo->lastInsertId();
    $pdo->prepare("UPDATE amulets SET Stock_Status = 'จองแล้ว' WHERE Amulet_ID = ?")->execute([$aid]);
    $pdo->commit();
    flash('สร้างคำสั่งซื้อเรียบร้อย กรุณาชำระเงินผ่านระบบตัวกลาง');
    redirect('orders.php#order-' . $oid);
} catch (RuntimeException $ex) {
    $pdo->rollBack();
    flash($ex->getMessage(), 'err');
    redirect('amulet.php?id=' . $aid);
}
