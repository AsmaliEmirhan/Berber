<?php
// ============================================================
//  Randevular Yönetimi
// ============================================================
if (!$shop):
?>
<div class="empty-state">
    <div class="empty-icon">📅</div>
    <h2>Önce bir dükkan oluşturun</h2>
    <a href="berber_paneli.php?page=dukkan" class="btn btn-primary">Dükkan Oluştur</a>
</div>
<?php else:
    $filter = in_array($_GET['filter'] ?? '', ['bekliyor','tamamlandi','iptal']) ? $_GET['filter'] : 'all';

    $sql = "
        SELECT a.*, u.full_name AS customer_name, u.email AS customer_email,
               sv.service_name, sv.price AS service_price,
               e.full_name AS employee_name
        FROM appointments a
        JOIN users     u  ON a.customer_id  = u.id
        JOIN services  sv ON a.service_id   = sv.id
        JOIN users     e  ON a.employee_id  = e.id
        WHERE a.shop_id = ?
    ";
    $params = [$shop['id']];

    if ($filter !== 'all') {
        $sql .= ' AND a.status = ?';
        $params[] = $filter;
    }

    $sql .= ' ORDER BY a.appointment_time DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();

    // Sayılar
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM appointments WHERE shop_id = ? GROUP BY status");
    $stmt->execute([$shop['id']]);
    $counts = ['all' => 0, 'bekliyor' => 0, 'tamamlandi' => 0, 'iptal' => 0];
    foreach ($stmt->fetchAll() as $row) {
        $counts[$row['status']] = (int)$row['cnt'];
        $counts['all'] += (int)$row['cnt'];
    }
?>

<div class="page-header">
    <div>
        <h2 class="page-title">Randevularım</h2>
        <p class="page-sub">Gelen randevuları görüntüleyin ve yönetin.</p>
    </div>
</div>

<!-- Filtre Tabları -->
<div class="filter-tabs">
    <?php
    $filters = [
        'all'        => ['label' => 'Tümü',       'color' => ''],
        'bekliyor'   => ['label' => 'Bekleyen',   'color' => 'gold'],
        'tamamlandi' => ['label' => 'Tamamlanan', 'color' => 'green'],
        'iptal'      => ['label' => 'İptal',      'color' => 'red'],
    ];
    foreach ($filters as $key => $f): ?>
    <a href="berber_paneli.php?page=randevular&filter=<?= $key ?>"
       class="filter-tab <?= $filter === $key ? 'active' : '' ?> <?= $f['color'] ?>">
        <?= $f['label'] ?>
        <span class="filter-count"><?= $counts[$key] ?></span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Randevu Tablosu -->
<?php if (empty($appointments)): ?>
<div class="empty-state" style="margin-top:24px">
    <div class="empty-icon">📅</div>
    <h2>Bu kategoride randevu yok</h2>
</div>
<?php else: ?>

<div class="card" style="margin-top:16px">
    <div class="card-body p0">
        <table class="data-table randevu-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Müşteri</th>
                    <th>Hizmet</th>
                    <th>Personel</th>
                    <th>Tarih & Saat</th>
                    <th>Ücret</th>
                    <th>Durum</th>
                    <th style="width:160px">İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $a): ?>
                <tr id="app-<?= $a['id'] ?>">
                    <td class="text-muted">#<?= $a['id'] ?></td>
                    <td>
                        <div>
                            <div><?= htmlspecialchars($a['customer_name']) ?></div>
                            <div class="text-muted" style="font-size:.75rem"><?= htmlspecialchars($a['customer_email']) ?></div>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($a['service_name']) ?></td>
                    <td><?= htmlspecialchars($a['employee_name']) ?></td>
                    <td>
                        <div><?= date('d M Y', strtotime($a['appointment_time'])) ?></div>
                        <div class="text-muted" style="font-size:.78rem"><?= date('H:i', strtotime($a['appointment_time'])) ?></div>
                    </td>
                    <td class="price-cell">₺<?= number_format($a['price_at_that_time'], 2) ?></td>
                    <td><span class="badge badge-<?= $a['status'] ?>" id="badge-<?= $a['id'] ?>"><?= statusLabel($a['status']) ?></span></td>
                    <td>
                        <div class="action-btns">
                            <?php if ($a['status'] === 'bekliyor'): ?>
                            <button class="btn btn-sm btn-success"
                                onclick="updateApp(<?= $a['id'] ?>, 'tamamlandi')">✓ Tamam</button>
                            <button class="btn btn-sm btn-danger"
                                onclick="updateApp(<?= $a['id'] ?>, 'iptal')">✕ İptal</button>
                            <?php elseif ($a['status'] === 'tamamlandi'): ?>
                            <span class="text-muted" style="font-size:.8rem">Tamamlandı</span>
                            <?php else: ?>
                            <span class="text-muted" style="font-size:.8rem">İptal edildi</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; endif; ?>

<script>
async function updateApp(id, status) {
    const labels = { tamamlandi: 'tamamlandı', iptal: 'iptal edildi' };
    if (!confirm(`Randevu ${labels[status]} olarak işaretlensin mi?`)) return;

    const fd = new FormData();
    fd.set('action', 'update_appointment');
    fd.set('appointment_id', id);
    fd.set('status', status);

    const res  = await fetch('berber/api.php', { method: 'POST', body: fd });
    const data = await res.json();
    showToast(data.success ? 'success' : 'error', data.message);

    if (data.success) {
        // Badge güncelle
        const badge = document.getElementById('badge-' + id);
        const classMap = { tamamlandi: 'badge-tamamlandi', iptal: 'badge-iptal' };
        const labelMap = { tamamlandi: 'Tamamlandı', iptal: 'İptal' };
        badge.className = 'badge ' + classMap[status];
        badge.textContent = labelMap[status];

        // Action butonlarını temizle
        const row = document.getElementById('app-' + id);
        row.querySelector('.action-btns').innerHTML = `<span class="text-muted" style="font-size:.8rem">${labelMap[status]}</span>`;
    }
}
</script>

<?php
function statusLabel(string $s): string {
    return match($s) {
        'bekliyor'   => 'Bekliyor',
        'tamamlandi' => 'Tamamlandı',
        'iptal'      => 'İptal',
        default      => $s
    };
}
?>
