<?php
// ============================================================
//  Keşfet — Berber Dükkanlarını Listele & Filtrele
//  $pdo, $user berber_paneli.php'den gelir
// ============================================================

$districts = $pdo->query('SELECT id, name FROM districts ORDER BY name')->fetchAll();

$filterDistrict = !empty($_GET['district']) ? (int)$_GET['district'] : (int)($user['district_id'] ?? 0);
$search         = trim($_GET['q'] ?? '');

$sql    = "
    SELECT s.*,
           d.name                                   AS district_name,
           u.full_name                              AS owner_name,
           (SELECT COUNT(*) FROM services sv WHERE sv.shop_id = s.id)        AS service_count,
           (SELECT COUNT(*) FROM shop_employees se WHERE se.shop_id = s.id)  AS employee_count
    FROM shops s
    JOIN users u ON s.owner_id = u.id
    LEFT JOIN districts d ON s.district_id = d.id
    WHERE 1=1
";
$params = [];

if ($filterDistrict) {
    $sql .= ' AND s.district_id = ?';
    $params[] = $filterDistrict;
}
if ($search) {
    $sql .= ' AND (s.shop_name LIKE ? OR u.full_name LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}

$sql .= ' ORDER BY s.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$shops = $stmt->fetchAll();
?>

<!-- Arama & Filtre -->
<div class="kesfet-header">
    <div>
        <h2 class="page-title">Berber Keşfet</h2>
        <p class="page-sub">
            <?= $filterDistrict
                ? htmlspecialchars($districts[array_search($filterDistrict, array_column($districts, 'id'))]['name'] ?? '') . ' ilçesindeki berberler'
                : 'Tüm berberler' ?>
            — <?= count($shops) ?> dükkan bulundu
        </p>
    </div>
</div>

<form method="GET" action="musteri_paneli.php" class="filter-bar">
    <input type="hidden" name="page" value="kesfet">
    <div class="filter-search">
        <span class="search-icon">🔍</span>
        <input type="text" name="q" placeholder="Dükkan veya berber adı…" value="<?= htmlspecialchars($search) ?>">
    </div>
    <select name="district" class="filter-select">
        <option value="">Tüm İlçeler</option>
        <?php foreach ($districts as $d): ?>
        <option value="<?= $d['id'] ?>" <?= $filterDistrict == $d['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($d['name']) ?>
        </option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Filtrele</button>
    <?php if ($filterDistrict || $search): ?>
    <a href="musteri_paneli.php?page=kesfet" class="btn btn-ghost btn-sm">Temizle</a>
    <?php endif; ?>
</form>

<!-- Dükkan Kartları -->
<?php if (empty($shops)): ?>
<div class="empty-state" style="margin-top:40px">
    <div class="empty-icon">🏪</div>
    <h2>Bu kriterlere uygun dükkan bulunamadı</h2>
    <p>Farklı bir ilçe veya arama terimi deneyin.</p>
    <a href="musteri_paneli.php?page=kesfet" class="btn btn-primary">Tüm Dükkanlar</a>
</div>
<?php else: ?>

<div class="shop-grid">
    <?php foreach ($shops as $s): ?>
    <div class="shop-card">
        <div class="shop-card-header">
            <div class="shop-card-avatar"><?= mb_strtoupper(mb_substr($s['shop_name'], 0, 1)) ?></div>
            <div class="shop-card-info">
                <div class="shop-card-name"><?= htmlspecialchars($s['shop_name']) ?></div>
                <div class="shop-card-owner">👤 <?= htmlspecialchars($s['owner_name']) ?></div>
            </div>
            <?php if ($s['district_name']): ?>
            <span class="district-tag">📍 <?= htmlspecialchars($s['district_name']) ?></span>
            <?php endif; ?>
        </div>

        <?php if ($s['address']): ?>
        <p class="shop-card-address">📌 <?= htmlspecialchars(mb_substr($s['address'], 0, 80)) ?><?= mb_strlen($s['address']) > 80 ? '…' : '' ?></p>
        <?php endif; ?>

        <div class="shop-card-stats">
            <div class="shop-stat">
                <span class="shop-stat-icon">✂️</span>
                <span><?= $s['service_count'] ?> Hizmet</span>
            </div>
            <div class="shop-stat">
                <span class="shop-stat-icon">👥</span>
                <span><?= $s['employee_count'] + 1 ?> Personel</span>
            </div>
        </div>

        <a href="musteri_paneli.php?page=berber_detay&shop_id=<?= $s['id'] ?>"
           class="btn btn-primary" style="width:100%;justify-content:center;margin-top:4px">
            Randevu Al →
        </a>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>
