<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_seller();

$cats = db()->query('SELECT * FROM categories ORDER BY Category_ID')->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $f = fn($k) => trim($_POST[$k] ?? '');
    $name = $f('name');
    $catId = (int)($_POST['category_id'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $year = (int)($_POST['year'] ?? 0) ?: null;

    if ($name === '') $errors[] = 'กรุณากรอกชื่อพระเครื่อง';
    if ($price <= 0) $errors[] = 'ราคาต้องมากกว่า 0';
    $chk = db()->prepare('SELECT 1 FROM categories WHERE Category_ID = ?'); $chk->execute([$catId]);
    if (!$chk->fetch()) $errors[] = 'กรุณาเลือกหมวดหมู่';

    $saved = [];
    if (!$errors) {
        $files = $_FILES['images'] ?? null;
        if ($files && is_array($files['name'])) {
            $count = min(count($files['name']), 8);
            for ($i = 0; $i < $count; $i++) {
                try {
                    $path = save_image([
                        'name' => $files['name'][$i], 'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i], 'size' => $files['size'][$i],
                    ]);
                    if ($path) $saved[] = $path;
                } catch (RuntimeException $ex) { $errors[] = $ex->getMessage(); }
            }
        }
        if (!$saved && !$errors) $errors[] = 'กรุณาแนบรูปพระเครื่องอย่างน้อย 1 รูป';
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO amulets (Seller_ID, Name, Category_ID, Monk_Creator, Temple, Year_Created, Material, Condition_Desc, Price, Certificate_Info, Guarantee_Policy, Description)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([$me['User_ID'], $name, $catId, $f('monk'), $f('temple'), $year, $f('material'), $f('condition'), $price, $f('certificate'), $f('guarantee'), $f('description')]);
            $aid = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO amulet_images (Amulet_ID, Image_URL, Is_Primary) VALUES (?,?,?)');
            foreach ($saved as $i => $path) $ins->execute([$aid, $path, $i === 0 ? 1 : 0]);

            // สร้างโพสต์ประเภท "ลงขาย" ขึ้นฟีดอัตโนมัติ
            $pdo->prepare('INSERT INTO posts (User_ID, Amulet_ID, Post_Type, Content) VALUES (?,?,?,?)')
                ->execute([$me['User_ID'], $aid, 'ลงขาย', 'ลงขายพระใหม่: ' . $name]);
            $pdo->commit();
            flash('ลงขายพระเรียบร้อย');
            redirect('amulet.php?id=' . $aid);
        } catch (Throwable $ex) {
            $pdo->rollBack();
            $errors[] = 'บันทึกไม่สำเร็จ กรุณาลองใหม่';
        }
    }
}

$pageTitle = 'ลงขายพระเครื่อง';
require __DIR__ . '/includes/header.php';
?>
<div class="card" style="max-width:720px;margin:auto">
  <h1>ลงขายพระเครื่อง</h1>
  <?php foreach ($errors as $er): ?><div class="flash err"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?>
    <label>ชื่อพระเครื่อง *</label><input type="text" name="name" value="<?= e($_POST['name'] ?? '') ?>" required>
    <div class="row">
      <div><label>หมวดหมู่ *</label>
        <select name="category_id"><?php foreach ($cats as $c): ?><option value="<?= (int)$c['Category_ID'] ?>"><?= e($c['Category_Name']) ?></option><?php endforeach; ?></select></div>
      <div><label>ราคาเช่าบูชา (บาท) *</label><input type="number" name="price" min="1" step="1" required></div>
    </div>
    <div class="row">
      <div><label>พระเกจิอาจารย์ผู้สร้าง</label><input type="text" name="monk"></div>
      <div><label>วัดที่ออก</label><input type="text" name="temple"></div>
    </div>
    <div class="row">
      <div><label>ปี พ.ศ. ที่สร้าง</label><input type="number" name="year" min="1" max="3000"></div>
      <div><label>เนื้อพระ</label><input type="text" name="material" placeholder="เนื้อผง, ทองแดง, นวโลหะ"></div>
    </div>
    <div class="row">
      <div><label>สภาพพระ</label><input type="text" name="condition" placeholder="สวยแชมป์, ผ่านการใช้, บูชาบนหิ้ง"></div>
      <div><label>ใบรับรองพระแท้</label><input type="text" name="certificate" placeholder="ระบุสถาบัน หรือเว้นว่างถ้าไม่มี"></div>
    </div>
    <label>เงื่อนไขการรับประกัน</label><input type="text" name="guarantee" placeholder="เช่น รับประกันแท้ คืนเงินเต็มจำนวนใน 7 วัน">
    <label>รายละเอียด / ตำหนิ / ประวัติ</label><textarea name="description"></textarea>
    <label>รูปภาพ (สูงสุด 8 รูป — รูปแรกเป็นปก: หน้า หลัง ขอบ)</label>
    <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" required>
    <p><button>ลงขาย</button></p>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
