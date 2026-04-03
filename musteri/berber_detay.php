<?php
// ============================================================
//  Berber Detay — Dükkan Bilgisi & Randevu Akışı
// ============================================================

$shopId = (int)($_GET['shop_id'] ?? 0);

if (!$shopId) {
    echo '<div class="empty-state"><div class="empty-icon">⚠️</div><h2>Dükkan bulunamadı</h2>
          <a href="musteri_paneli.php?page=kesfet" class="btn btn-primary">Geri Dön</a></div>';
    return;
}

// Dükkan bilgisi
$stmt = $pdo->prepare("
    SELECT s.*, d.name AS district_name, u.full_name AS owner_name, u.email AS owner_email
    FROM shops s
    JOIN users u ON s.owner_id = u.id
    LEFT JOIN districts d ON s.district_id = d.id
    WHERE s.id = ?
");
$stmt->execute([$shopId]);
$shop = $stmt->fetch();

if (!$shop) {
    echo '<div class="empty-state"><div class="empty-icon">⚠️</div><h2>Dükkan bulunamadı</h2>
          <a href="musteri_paneli.php?page=kesfet" class="btn btn-primary">Geri Dön</a></div>';
    return;
}

// Hizmetler
$stmt = $pdo->prepare('SELECT * FROM services WHERE shop_id = ? ORDER BY service_name');
$stmt->execute([$shopId]);
$services = $stmt->fetchAll();

// Çalışanlar (sahip dahil)
$stmt = $pdo->prepare("
    SELECT u.id, u.full_name
    FROM users u WHERE u.id = (SELECT owner_id FROM shops WHERE id = ?)
    UNION
    SELECT u.id, u.full_name
    FROM users u JOIN shop_employees se ON se.employee_id = u.id WHERE se.shop_id = ?
    ORDER BY full_name
");
$stmt->execute([$shopId, $shopId]);
$employees = $stmt->fetchAll();

// Tamamlanan randevu sayısı (sosyal kanıt)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE shop_id = ? AND status = 'tamamlandi'");
$stmt->execute([$shopId]);
$completedCount = (int)$stmt->fetchColumn();
?>

<!-- Geri butonu -->
<div style="margin-bottom:20px">
    <a href="musteri_paneli.php?page=kesfet" class="btn btn-ghost btn-sm">← Geri</a>
</div>

<!-- Dükkan Hero -->
<div class="shop-hero card">
    <div class="card-body">
        <div class="shop-hero-inner">
            <div class="shop-hero-avatar"><?= mb_strtoupper(mb_substr($shop['shop_name'], 0, 1)) ?></div>
            <div class="shop-hero-info">
                <h2 class="shop-hero-name"><?= htmlspecialchars($shop['shop_name']) ?></h2>
                <div class="shop-hero-meta">
                    <?php if ($shop['district_name']): ?>
                    <span>📍 <?= htmlspecialchars($shop['district_name']) ?></span>
                    <?php endif; ?>
                    <?php if ($shop['address']): ?>
                    <span>🏠 <?= htmlspecialchars($shop['address']) ?></span>
                    <?php endif; ?>
                    <span>👤 <?= htmlspecialchars($shop['owner_name']) ?></span>
                </div>
            </div>
            <div class="shop-hero-stats">
                <div class="hero-stat">
                    <div class="hero-stat-val"><?= count($services) ?></div>
                    <div class="hero-stat-lbl">Hizmet</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-val"><?= count($employees) ?></div>
                    <div class="hero-stat-lbl">Personel</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-val"><?= $completedCount ?></div>
                    <div class="hero-stat-lbl">Tamamlanan</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hizmetler -->
<div class="section-title">✂️ Sunulan Hizmetler</div>

<?php if (empty($services)): ?>
<div class="empty-state">
    <div class="empty-icon">✂️</div>
    <h2>Bu dükkan henüz hizmet eklememiş</h2>
</div>
<?php else: ?>

<div class="services-grid">
    <?php foreach ($services as $s): ?>
    <div class="service-card">
        <div class="service-card-body">
            <div class="service-name"><?= htmlspecialchars($s['service_name']) ?></div>
            <div class="service-meta">
                <span class="service-duration">⏱ <?= $s['duration_minutes'] ?> dk</span>
            </div>
        </div>
        <div class="service-card-right">
            <div class="service-price">₺<?= number_format($s['price'], 2) ?></div>
            <button class="btn btn-primary btn-sm"
                onclick="openBooking(<?= $shopId ?>, <?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['service_name'])) ?>', <?= $s['price'] ?>, <?= $s['duration_minutes'] ?>)">
                Randevu Al
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<!-- Çalışanlar -->
<?php if (!empty($employees)): ?>
<div class="section-title" style="margin-top:28px">👥 Personelimiz</div>
<div class="employees-row">
    <?php foreach ($employees as $emp): ?>
    <div class="employee-chip">
        <div class="mini-avatar"><?= mb_strtoupper(mb_substr($emp['full_name'], 0, 1)) ?></div>
        <?= htmlspecialchars($emp['full_name']) ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Randevu Booking Modal Şablonu -->
<template id="bookingTemplate">
    <div class="booking-wrapper">

        <!-- Step indicator -->
        <div class="step-indicator">
            <div class="step-dot active" data-step="1">1</div>
            <div class="step-line"></div>
            <div class="step-dot" data-step="2">2</div>
            <div class="step-line"></div>
            <div class="step-dot" data-step="3">3</div>
            <div class="step-line"></div>
            <div class="step-dot" data-step="4">4</div>
        </div>
        <div class="step-labels">
            <span>Personel</span><span>Tarih</span><span>Saat</span><span>Onay</span>
        </div>

        <!-- Step 1: Personel -->
        <div class="step-panel active" id="stepPanel1">
            <div class="selected-service-box" id="selectedServiceBox"></div>
            <div class="field" style="margin-top:16px">
                <label>Personel Seçin <span class="required">*</span></label>
                <select id="employeeSelect" class="booking-select">
                    <option value="">Personel yükleniyor…</option>
                </select>
            </div>
            <div class="step-actions">
                <span></span>
                <button class="btn btn-primary" onclick="bookingNextStep(2)">Devam →</button>
            </div>
        </div>

        <!-- Step 2: Tarih -->
        <div class="step-panel" id="stepPanel2">
            <div class="field">
                <label>Randevu Tarihi <span class="required">*</span></label>
                <input type="date" id="dateInput" class="booking-input"
                       min="<?= date('Y-m-d') ?>"
                       max="<?= date('Y-m-d', strtotime('+30 days')) ?>">
            </div>
            <div class="step-actions">
                <button class="btn btn-ghost" onclick="bookingNextStep(1)">← Geri</button>
                <button class="btn btn-primary" onclick="bookingNextStep(3)">Devam →</button>
            </div>
        </div>

        <!-- Step 3: Saat -->
        <div class="step-panel" id="stepPanel3">
            <p class="booking-hint">Müsait saati seçin</p>
            <div id="slotsContainer" class="slots-container">
                <p class="text-muted center">Tarih ve personel seçtikten sonra saatler yüklenir.</p>
            </div>
            <div class="step-actions">
                <button class="btn btn-ghost" onclick="bookingNextStep(2)">← Geri</button>
                <button class="btn btn-primary" id="slotNextBtn" disabled onclick="bookingNextStep(4)">Devam →</button>
            </div>
        </div>

        <!-- Step 4: Onay -->
        <div class="step-panel" id="stepPanel4">
            <div class="booking-summary" id="bookingSummary"></div>
            <div class="step-actions">
                <button class="btn btn-ghost" onclick="bookingNextStep(3)">← Geri</button>
                <button class="btn btn-primary" id="confirmBtn" onclick="confirmBooking()">
                    <span class="spinner-sm hidden" id="confirmSpinner"></span>
                    ✓ Randevuyu Onayla
                </button>
            </div>
        </div>

    </div>
</template>

<script>
// Mevcut dükkan çalışanlarını önceden yükle
window._shopEmployees = <?= json_encode($employees) ?>;
</script>
