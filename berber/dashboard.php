<?php
// ============================================================
//  Dashboard — İstatistikler & Özet
//  $pdo, $shop, $user değişkenleri berber_paneli.php'den gelir
// ============================================================
?>

<?php if (!$shop): ?>
<!-- Dükkan yok uyarısı -->
<div class="empty-state">
    <div class="empty-icon">🏪</div>
    <h2>Henüz bir dükkan oluşturmadınız</h2>
    <p>Randevu almaya başlamak için önce dükkanınızı oluşturun.</p>
    <a href="berber_paneli.php?page=dukkan" class="btn btn-primary">Dükkan Oluştur</a>
</div>

<?php else:
    $shopId = $shop['id'];

    // ---- İstatistikler ----
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE shop_id = ?');
    $stmt->execute([$shopId]);
    $totalAppointments = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE shop_id = ? AND status = 'bekliyor'");
    $stmt->execute([$shopId]);
    $pendingCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(price_at_that_time),0) FROM appointments WHERE shop_id = ? AND status = 'tamamlandi'");
    $stmt->execute([$shopId]);
    $totalEarnings = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(DISTINCT customer_id) FROM appointments WHERE shop_id = ?');
    $stmt->execute([$shopId]);
    $totalCustomers = (int)$stmt->fetchColumn();

    // ---- Son 7 gün kazanç (grafik) ----
    $stmt = $pdo->prepare("
        SELECT DATE(appointment_time) as day, COALESCE(SUM(price_at_that_time),0) as total
        FROM appointments
        WHERE shop_id = ? AND status = 'tamamlandi'
          AND appointment_time >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(appointment_time)
    ");
    $stmt->execute([$shopId]);
    $rawChart = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $chartLabels = [];
    $chartValues = [];
    for ($i = 6; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-{$i} days"));
        $chartLabels[] = date('d M', strtotime($day));
        $chartValues[] = (float)($rawChart[$day] ?? 0);
    }

    // ---- En popüler hizmetler ----
    $stmt = $pdo->prepare("
        SELECT s.service_name, COUNT(*) as cnt
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        WHERE a.shop_id = ?
        GROUP BY s.service_name
        ORDER BY cnt DESC
        LIMIT 5
    ");
    $stmt->execute([$shopId]);
    $popularServices = $stmt->fetchAll();

    // ---- Yoğun saatler ----
    $stmt = $pdo->prepare("
        SELECT HOUR(appointment_time) as hr, COUNT(*) as cnt
        FROM appointments
        WHERE shop_id = ?
        GROUP BY HOUR(appointment_time)
        ORDER BY cnt DESC
        LIMIT 5
    ");
    $stmt->execute([$shopId]);
    $busyHours = $stmt->fetchAll();

    // ---- Son randevular ----
    $stmt = $pdo->prepare("
        SELECT a.*, u.full_name AS customer_name, sv.service_name,
               e.full_name AS employee_name
        FROM appointments a
        JOIN users  u  ON a.customer_id  = u.id
        JOIN services sv ON a.service_id = sv.id
        JOIN users  e  ON a.employee_id  = e.id
        WHERE a.shop_id = ?
        ORDER BY a.appointment_time DESC
        LIMIT 6
    ");
    $stmt->execute([$shopId]);
    $recent = $stmt->fetchAll();
?>

<!-- Stat Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(139,92,246,.15);color:#a78bfa">📅</div>
        <div class="stat-body">
            <div class="stat-value"><?= number_format($totalAppointments) ?></div>
            <div class="stat-label">Toplam Randevu</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(245,158,11,.15);color:#fbbf24">⏳</div>
        <div class="stat-body">
            <div class="stat-value"><?= $pendingCount ?></div>
            <div class="stat-label">Bekleyen Randevu</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(16,185,129,.15);color:#34d399">💰</div>
        <div class="stat-body">
            <div class="stat-value">₺<?= number_format($totalEarnings, 2) ?></div>
            <div class="stat-label">Toplam Kazanç</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(59,130,246,.15);color:#60a5fa">👤</div>
        <div class="stat-body">
            <div class="stat-value"><?= $totalCustomers ?></div>
            <div class="stat-label">Müşteri Sayısı</div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="charts-row">
    <!-- Kazanç Grafiği -->
    <div class="card chart-card">
        <div class="card-header">
            <h3 class="card-title">Son 7 Gün Kazanç</h3>
        </div>
        <div class="card-body">
            <canvas id="earningsChart" height="220"></canvas>
        </div>
    </div>

    <!-- Popüler Hizmetler -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">En Çok Tercih Edilenler</h3>
        </div>
        <div class="card-body">
            <?php if (empty($popularServices)): ?>
                <p class="text-muted center">Henüz randevu yok.</p>
            <?php else:
                $maxCnt = max(array_column($popularServices, 'cnt'));
                foreach ($popularServices as $sv): ?>
                <div class="bar-item">
                    <div class="bar-label"><?= htmlspecialchars($sv['service_name']) ?></div>
                    <div class="bar-track">
                        <div class="bar-fill" style="width:<?= round($sv['cnt']/$maxCnt*100) ?>%"></div>
                    </div>
                    <div class="bar-count"><?= $sv['cnt'] ?></div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<!-- Yoğun Saatler + Son Randevular Row -->
<div class="charts-row">
    <!-- Yoğun Saatler -->
    <div class="card" style="max-width:340px">
        <div class="card-header">
            <h3 class="card-title">Yoğun Saatler</h3>
        </div>
        <div class="card-body">
            <?php if (empty($busyHours)): ?>
                <p class="text-muted center">Veri yok.</p>
            <?php else: foreach ($busyHours as $bh): ?>
                <div class="hour-item">
                    <span class="hour-badge"><?= str_pad($bh['hr'],2,'0',STR_PAD_LEFT) ?>:00</span>
                    <span class="hour-count"><?= $bh['cnt'] ?> randevu</span>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Son Randevular -->
    <div class="card" style="flex:1">
        <div class="card-header">
            <h3 class="card-title">Son Randevular</h3>
            <a href="berber_paneli.php?page=randevular" class="btn btn-sm btn-ghost">Tümünü Gör →</a>
        </div>
        <div class="card-body p0">
            <?php if (empty($recent)): ?>
                <p class="text-muted center" style="padding:24px">Henüz randevu yok.</p>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr><th>Müşteri</th><th>Hizmet</th><th>Tarih</th><th>Durum</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['customer_name']) ?></td>
                        <td><?= htmlspecialchars($r['service_name']) ?></td>
                        <td><?= date('d M, H:i', strtotime($r['appointment_time'])) ?></td>
                        <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function(){
    const ctx = document.getElementById('earningsChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'Kazanç (₺)',
                data: <?= json_encode($chartValues) ?>,
                backgroundColor: 'rgba(139,92,246,0.5)',
                borderColor: 'rgba(139,92,246,1)',
                borderWidth: 2,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => '₺' + ctx.parsed.y.toFixed(2)
                    }
                }
            },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#9ca3af' } },
                y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#9ca3af', callback: v => '₺'+v } }
            }
        }
    });
})();
</script>

<?php endif; ?>
