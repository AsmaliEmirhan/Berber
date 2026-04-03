<?php
// ============================================================
//  Randevularım — Müşteri'nin Randevuları
// ============================================================

$filter = in_array($_GET['filter'] ?? '', ['bekliyor','tamamlandi','iptal']) ? $_GET['filter'] : 'all';

$sql = "
    SELECT a.*,
           sh.shop_name,
           sv.service_name,
           e.full_name  AS employee_name,
           d.name       AS district_name
    FROM appointments a
    JOIN shops    sh ON a.shop_id     = sh.id
    JOIN services sv ON a.service_id  = sv.id
    JOIN users    e  ON a.employee_id = e.id
    LEFT JOIN districts d ON sh.district_id = d.id
    WHERE a.customer_id = ?
";
$params = [$_SESSION['user_id']];

if ($filter !== 'all') {
    $sql .= ' AND a.status = ?';
    $params[] = $filter;
}
$sql .= ' ORDER BY a.appointment_time DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

// Sayılar
$stmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM appointments WHERE customer_id = ? GROUP BY status");
$stmt->execute([$_SESSION['user_id']]);
$counts = ['all' => 0, 'bekliyor' => 0, 'tamamlandi' => 0, 'iptal' => 0];
foreach ($stmt->fetchAll() as $row) {
    $counts[$row['status']] = (int)$row['cnt'];
    $counts['all'] += (int)$row['cnt'];
}
?>

<div class="page-header">
    <div>
        <h2 class="page-title">Randevularım</h2>
        <p class="page-sub">Tüm randevularınızı görüntüleyin ve yönetin.</p>
    </div>
    <a href="musteri_paneli.php?page=kesfet" class="btn btn-primary">+ Yeni Randevu</a>
</div>

<!-- Filtre Tabları -->
<div class="filter-tabs">
    <?php foreach ([
        'all'        => ['Tümü', ''],
        'bekliyor'   => ['Bekleyen', 'gold'],
        'tamamlandi' => ['Tamamlanan', 'green'],
        'iptal'      => ['İptal', 'red'],
    ] as $key => [$label, $color]): ?>
    <a href="musteri_paneli.php?page=randevularim&filter=<?= $key ?>"
       class="filter-tab <?= $filter === $key ? 'active' : '' ?> <?= $color ?>">
        <?= $label ?> <span class="filter-count"><?= $counts[$key] ?></span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Liste -->
<?php if (empty($appointments)): ?>
<div class="empty-state" style="margin-top:32px">
    <div class="empty-icon">📅</div>
    <h2>Bu kategoride randevu yok</h2>
    <p>Yeni bir randevu almak için berberleri keşfedin.</p>
    <a href="musteri_paneli.php?page=kesfet" class="btn btn-primary">Berber Keşfet</a>
</div>
<?php else: ?>

<div class="randevu-list">
    <?php foreach ($appointments as $a):
        $isPast    = strtotime($a['appointment_time']) < time();
        $isBekliyor = $a['status'] === 'bekliyor';
    ?>
    <div class="randevu-card <?= $a['status'] ?>" id="rcard-<?= $a['id'] ?>">

        <div class="randevu-card-left">
            <div class="randevu-date-box">
                <div class="rdate-day"><?= date('d', strtotime($a['appointment_time'])) ?></div>
                <div class="rdate-month"><?= date('M', strtotime($a['appointment_time'])) ?></div>
            </div>
            <div class="randevu-time">
                🕐 <?= date('H:i', strtotime($a['appointment_time'])) ?>
            </div>
        </div>

        <div class="randevu-card-body">
            <div class="randevu-shop-name"><?= htmlspecialchars($a['shop_name']) ?></div>
            <div class="randevu-meta">
                <span>✂️ <?= htmlspecialchars($a['service_name']) ?></span>
                <span>👤 <?= htmlspecialchars($a['employee_name']) ?></span>
                <?php if ($a['district_name']): ?>
                <span>📍 <?= htmlspecialchars($a['district_name']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="randevu-card-right">
            <div class="randevu-price">₺<?= number_format($a['price_at_that_time'], 2) ?></div>
            <span class="badge badge-<?= $a['status'] ?>" id="rbadge-<?= $a['id'] ?>">
                <?= ['bekliyor'=>'Bekliyor','tamamlandi'=>'Tamamlandı','iptal'=>'İptal'][$a['status']] ?>
            </span>
            <?php if ($isBekliyor && !$isPast): ?>
            <button class="btn btn-danger btn-sm" style="margin-top:8px"
                onclick="cancelAppointment(<?= $a['id'] ?>)">
                İptal Et
            </button>
            <?php endif; ?>
        </div>

    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<script>
async function cancelAppointment(id) {
    if (!confirm('Bu randevuyu iptal etmek istediğinizden emin misiniz?')) return;

    const fd = new FormData();
    fd.set('action', 'cancel_appointment');
    fd.set('appointment_id', id);

    const res  = await fetch('musteri/api.php', { method: 'POST', body: fd });
    const data = await res.json();
    showToast(data.success ? 'success' : 'error', data.message);

    if (data.success) {
        const badge = document.getElementById('rbadge-' + id);
        badge.className = 'badge badge-iptal';
        badge.textContent = 'İptal';
        document.getElementById('rcard-' + id).classList.replace('bekliyor', 'iptal');
        // İptal butonunu kaldır
        const btn = document.querySelector(`#rcard-${id} .btn-danger`);
        btn?.remove();
    }
}
</script>
