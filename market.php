<?php
$pageTitle = 'ตลาดพระเครื่อง';
require __DIR__ . '/includes/header.php';

$cats = db()->query('SELECT * FROM categories ORDER BY Category_ID')->fetchAll();
$q = trim($_GET['q'] ?? '');
$cat = (int)($_GET['cat'] ?? 0);

$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(a.Name LIKE ? OR a.Monk_Creator LIKE ? OR a.Temple LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($cat) { $where[] = 'a.Category_ID = ?'; $params[] = $cat; }

$st = db()->prepare(
    'SELECT a.*, c.Category_Name, u.Username, u.Verification_Status, u.Rating_Score,
            (SELECT Image_URL FROM amulet_images i WHERE i.Amulet_ID = a.Amulet_ID ORDER BY i.Is_Primary DESC, i.Image_ID LIMIT 1) AS cover
     FROM amulets a
     JOIN categories c ON c.Category_ID = a.Category_ID
     JOIN users u ON u.User_ID = a.Seller_ID
     WHERE ' . implode(' AND ', $where) . ' ORDER BY a.Created_At DESC LIMIT 60'
);
$st->execute($params);
$items = $st->fetchAll();
?>
<h1>ตลาดพระเครื่อง</h1>
<form class="filter" method="get">
  <input type="text" name="q" value="<?= e($q) ?>" placeholder="ค้นหาชื่อพระ / เกจิ / วัด">
  <select name="cat">
    <option value="0">ทุกหมวดหมู่</option>
    <?php foreach ($cats as $c): ?>
      <option value="<?= (int)$c['Category_ID'] ?>" <?= $cat === (int)$c['Category_ID'] ? 'selected' : '' ?>><?= e($c['Category_Name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button>ค้นหา</button>
</form>

<?php if (!$items): ?><div class="card muted">ไม่พบพระเครื่อง</div><?php endif; ?>
<div class="grid">
<?php foreach ($items as $a): ?>
  <a class="item" href="amulet.php?id=<?= (int)$a['Amulet_ID'] ?>" style="color:inherit;text-decoration:none">
    <?php if ($a['cover']): ?><img src="<?= e($a['cover']) ?>" alt=""><?php else: ?><div class="noimg">ไม่มีรูป</div><?php endif; ?>
    <div class="body">
      <strong><?= e($a['Name']) ?></strong>
      <div class="muted"><?= e($a['Category_Name']) ?><?= $a['Temple'] ? ' · ' . e($a['Temple']) : '' ?></div>
      <div class="price"><?= baht($a['Price']) ?></div>
      <span class="badge <?= $a['Stock_Status'] === 'พร้อมเช่า' ? 'green' : 'gray' ?>"><?= e($a['Stock_Status']) ?></span>
      <?php if ($a['Certificate_Info']): ?><span class="badge">มีใบรับรอง</span><?php endif; ?>
    </div>
  </a>
<?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
