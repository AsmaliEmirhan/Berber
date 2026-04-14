<?php
if (!$shop): ?>
<div class="max-w-screen-xl mx-auto px-6 py-20 text-center">
    <div class="bg-surface-container-lowest sketch-border p-12 inline-block -rotate-1 border-4 border-black">
        <span class="material-symbols-outlined text-8xl text-error mb-4 opacity-80" data-icon="store_off">store_off</span>
        <h2 class="text-4xl font-headline font-black mb-4">Henüz bir dükkan oluşturmadınız</h2>
        <p class="text-xl text-on-surface-variant mb-8 font-medium">Randevu almaya başlamak için dükkan ayarlarınızı yapılandırmalısınız.</p>
        <a href="?page=dukkan" class="bg-black text-white px-8 py-3 rounded-xl border-2 border-black font-bold uppercase transition-transform hover:-translate-y-1">Dükkanı Oluştur</a>
    </div>
</div>
<?php else:
    $shopId = $shop['id'];

    $empFilterAlias = "";
    $empFilterPlain = "";
    $paramsAlias = [$shopId];
    $paramsPlain = [$shopId];
    
    if ($userRoleInShop === 'Çalışan') {
        $empFilterAlias = " AND a.employee_id = ?";
        $empFilterPlain = " AND employee_id = ?";
        $paramsAlias[] = $_SESSION['user_id'];
        $paramsPlain[] = $_SESSION['user_id'];
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE shop_id = ? $empFilterPlain");
    $stmt->execute($paramsPlain);
    $totalAppointments = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE shop_id = ? AND status = 'bekliyor' $empFilterPlain");
    $stmt->execute($paramsPlain);
    $pendingCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(price_at_that_time),0) FROM appointments WHERE shop_id = ? AND status = 'tamamlandi' $empFilterPlain");
    $stmt->execute($paramsPlain);
    $totalEarnings = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT customer_id) FROM appointments WHERE shop_id = ? $empFilterPlain");
    $stmt->execute($paramsPlain);
    $totalCustomers = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT DATE(appointment_time) as day, COALESCE(SUM(price_at_that_time),0) as total
        FROM appointments
        WHERE shop_id = ? AND status = 'tamamlandi'
          AND appointment_time >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
          $empFilterPlain
        GROUP BY DATE(appointment_time)
    ");
    $stmt->execute($paramsPlain);
    $rawChart = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $chartLabels = [];
    $chartValues = [];
    for ($i = 6; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-{$i} days"));
        $chartLabels[] = date('d M', strtotime($day));
        $chartValues[] = (float)($rawChart[$day] ?? 0);
    }

    $stmt = $pdo->prepare("
        SELECT s.service_name, COUNT(*) as cnt
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        WHERE a.shop_id = ? $empFilterAlias
        GROUP BY s.service_name
        ORDER BY cnt DESC
        LIMIT 5
    ");
    $stmt->execute($paramsAlias);
    $popularServices = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT HOUR(appointment_time) as hr, COUNT(*) as cnt
        FROM appointments
        WHERE shop_id = ? $empFilterPlain
        GROUP BY HOUR(appointment_time)
        ORDER BY cnt DESC
        LIMIT 5
    ");
    $stmt->execute($paramsPlain);
    $busyHours = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT a.*, COALESCE(u.full_name, a.walkin_name) AS customer_name, sv.service_name,
               e.full_name AS employee_name
        FROM appointments a
        LEFT JOIN users u ON a.customer_id = u.id
        JOIN services sv ON a.service_id = sv.id
        JOIN users  e  ON a.employee_id  = e.id
        WHERE a.shop_id = ? $empFilterAlias
        ORDER BY a.appointment_time DESC
        LIMIT 6
    ");
    $stmt->execute($paramsAlias);
    $recent = $stmt->fetchAll();

    $workerEarnings = [];
    if ($userRoleInShop === 'Patron') {
        $stmt = $pdo->prepare("
            SELECT e.full_name, COALESCE(SUM(a.price_at_that_time),0) as earned
            FROM appointments a
            JOIN users e ON a.employee_id = e.id
            WHERE a.shop_id = ? AND a.status = 'tamamlandi'
            GROUP BY a.employee_id
            ORDER BY earned DESC
        ");
        $stmt->execute([$shopId]);
        $workerEarnings = $stmt->fetchAll();
    }
?>

<div class="max-w-screen-xl mx-auto px-6 py-6 overflow-hidden">

    <!-- Header Section -->
    <div class="mb-12 relative z-10 pt-4">
        <div class="inline-block bg-surface-container-highest px-4 py-1 rotated-sketch mb-4 border-2 border-black">
            <span class="font-headline font-bold uppercase tracking-widest text-xs">Genel Durum</span>
        </div>
        <h1 class="text-5xl md:text-7xl font-headline font-extrabold tracking-tighter text-black mb-4">
            Merhaba, <span class="italic underline decoration-secondary"><?= htmlspecialchars(explode(' ', $user['full_name'])[0]) ?> Usta</span>
        </h1>
        <p class="text-xl text-on-surface-variant max-w-2xl font-medium">İşletmenizin dijital defterine hoş geldin. Dükkanın durumu aşağıdaya listelendi.</p>
    </div>

    <!-- Stats Bento Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
        <!-- Radevu Card -->
        <div class="bg-surface-container-lowest border-4 border-black hand-drawn-border p-8 hover:translate-x-1 hover:-translate-y-1 hover:shadow-[6px_6px_0px_0px_rgba(0,0,0,1)] transition-all">
            <div class="flex justify-between items-start mb-4">
                <span class="material-symbols-outlined text-secondary text-5xl" style="font-variation-settings: 'FILL' 1;">calendar_month</span>
            </div>
            <h3 class="text-stone-500 font-bold text-sm uppercase tracking-widest mb-1">Toplam Randevu</h3>
            <div class="text-4xl font-headline font-black"><?= number_format($totalAppointments) ?></div>
        </div>

        <!-- Bekleyen Card -->
        <div class="bg-surface-container-lowest border-4 border-black hand-drawn-border p-8 hover:translate-x-1 hover:-translate-y-1 hover:shadow-[6px_6px_0px_0px_rgba(0,0,0,1)] transition-all">
            <div class="flex justify-between items-start mb-4">
                <span class="material-symbols-outlined text-[#fbbf24] text-5xl" style="font-variation-settings: 'FILL' 1;">pending_actions</span>
                <?php if($pendingCount > 0): ?>
                <span class="text-xs font-black bg-[#fefee5] border-2 border-black px-2 py-1 rotate-3">YENİ</span>
                <?php endif; ?>
            </div>
            <h3 class="text-stone-500 font-bold text-sm uppercase tracking-widest mb-1">Bekleyen İşlem</h3>
            <div class="text-4xl font-headline font-black"><?= $pendingCount ?></div>
        </div>

        <!-- Kazanç Card -->
        <div class="bg-surface-container-highest border-4 border-black border-dashed p-8 hover:-translate-y-1 transition-transform rotate-1">
            <div class="flex justify-between items-start mb-4">
                <span class="material-symbols-outlined text-secondary text-5xl" style="font-variation-settings: 'FILL' 1;">payments</span>
            </div>
            <h3 class="text-on-secondary-container font-black text-sm uppercase tracking-widest mb-1">Toplam Kazanç</h3>
            <div class="text-4xl font-headline font-black text-secondary">₺<?= number_format($totalEarnings, 0) ?></div>
        </div>

        <!-- Müşteri Card -->
        <div class="bg-surface-container-lowest border-4 border-black hand-drawn-border p-8 hover:-translate-x-1 hover:-translate-y-1 hover:shadow-[-6px_6px_0px_0px_rgba(0,0,0,1)] transition-all">
            <div class="flex justify-between items-start mb-4">
                <span class="material-symbols-outlined text-secondary text-5xl" style="font-variation-settings: 'FILL' 1;">groups</span>
            </div>
            <h3 class="text-stone-500 font-bold text-sm uppercase tracking-widest mb-1">Cari Müşteri</h3>
            <div class="text-4xl font-headline font-black"><?= number_format($totalCustomers) ?></div>
        </div>
    </div>

    <!-- Charts & Tables Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start mb-12">
        
        <!-- Kazanç Grafiği (Col Span 2) -->
        <div class="lg:col-span-2 bg-surface-container-lowest border-4 border-black sketch-shadow p-6 rounded-2xl">
            <div class="flex justify-between items-center mb-6">
                <h3 class="font-headline font-black text-2xl uppercase">Son 7 Günlük Kazanç</h3>
                <span class="material-symbols-outlined bg-[#fefee5] border-2 border-black p-1">show_chart</span>
            </div>
            <div class="h-64 relative w-full">
                <canvas id="earningsChart"></canvas>
            </div>
        </div>

        <!-- Popüler Hizmetler -->
        <div class="bg-surface-container-low border-4 border-black sketch-shadow p-6 rounded-2xl -rotate-1">
            <h3 class="font-headline font-black text-2xl uppercase mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-secondary">local_fire_department</span>
                Popüler İşlemler
            </h3>
            
            <?php if (empty($popularServices)): ?>
                <div class="text-center py-8 opacity-50 font-bold">Veri Yok.</div>
            <?php else:
                $maxCnt = max(array_column($popularServices, 'cnt'));
                foreach ($popularServices as $sv): 
                    $percent = round($sv['cnt']/$maxCnt*100);
            ?>
                <div class="mb-4">
                    <div class="flex justify-between text-sm font-bold uppercase mb-1">
                        <span><?= htmlspecialchars($sv['service_name']) ?></span>
                        <span><?= $sv['cnt'] ?> İşlem</span>
                    </div>
                    <div class="h-4 bg-white border-2 border-black rounded-lg overflow-hidden relative">
                        <div class="absolute top-0 left-0 h-full bg-black" style="width: <?= $percent ?>%"></div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Yoğun saatler ve Son randevular -->
    <div class="grid grid-cols-1 xl:grid-cols-4 gap-8 items-start mb-20">
        
        <div class="xl:col-span-1 border-4 border-black border-dashed p-6 bg-surface rotate-1">
            <h3 class="font-headline font-black text-xl uppercase mb-6 flex gap-2 items-center">
                <span class="material-symbols-outlined text-secondary">schedule</span> Yoğun Saatler
            </h3>
            <div class="space-y-4">
                <?php if (empty($busyHours)): ?>
                    <p class="font-bold opacity-50 text-center py-4">Veri yok</p>
                <?php else: foreach ($busyHours as $bh): ?>
                    <div class="flex justify-between items-center bg-white border-2 border-black px-4 py-3 hover:-translate-y-0.5 transition-transform">
                        <span class="font-headline font-black text-lg"><?= str_pad($bh['hr'],2,'0',STR_PAD_LEFT) ?>:00</span>
                        <span class="text-xs uppercase font-bold tracking-widest bg-surface-container px-2 py-1 border border-black"><?= $bh['cnt'] ?> Randevu</span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Ledger Table Section (Son Randevular) -->
        <div class="xl:col-span-3 bg-surface-container-low rounded-2xl overflow-hidden border-2 border-black outline outline-2 outline-offset-2 outline-black bg-surface-container-lowest">
            <div class="p-6 border-b-2 border-black flex justify-between items-center bg-surface-container-highest">
                <h2 class="text-2xl font-headline font-black italic">Son İşlemler</h2>
                <a href="?page=randevular" class="btn border-2 border-black bg-white px-4 py-2 text-xs font-bold uppercase hover:bg-black hover:text-white transition-colors">Tümünü Yönet</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[600px]">
                    <thead>
                        <tr class="bg-surface-container-low text-on-surface-variant text-xs uppercase tracking-widest font-black border-b-2 border-black">
                            <th class="px-6 py-4">MÜŞTERİ</th>
                            <th class="px-6 py-4">HİZMET</th>
                            <th class="px-6 py-4">TARİH</th>
                            <th class="px-6 py-4 text-center">DURUM</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y-2 divide-black/10">
                        <?php if (empty($recent)): ?>
                        <tr><td colspan="4" class="px-6 py-8 text-center font-bold opacity-50">Kayıt Bulunamadı.</td></tr>
                        <?php else: foreach ($recent as $r): 
                            $statusBg = 'bg-surface-container-low text-stone-500';
                            $statusLbl = mb_strtoupper($r['status']);
                            
                            if ($r['status'] === 'bekliyor') { $statusBg = 'bg-[#fefee5] border-secondary text-secondary'; $statusLbl = 'BEKLİYOR'; }
                            else if ($r['status'] === 'onaylandi') {
                                $statusBg = 'bg-[#3b82f6]/10 border-[#3b82f6] text-[#3b82f6]';
                                if (time() >= strtotime($r['appointment_time'])) {
                                    $statusLbl = 'İŞLEMDE';
                                } else {
                                    $statusLbl = 'KABUL EDİLDİ';
                                }
                            }
                            else if ($r['status'] === 'tamamlandi') { $statusBg = 'bg-secondary text-white border-transparent'; $statusLbl = 'TAMAMLANDI'; }
                            else if ($r['status'] === 'iptal') { $statusBg = 'bg-error text-white border-transparent'; $statusLbl = 'İPTAL'; }
                        ?>
                        <tr class="hover:bg-surface-container-lowest transition-colors">
                            <td class="px-6 py-4 font-bold"><?= htmlspecialchars($r['customer_name']) ?></td>
                            <td class="px-6 py-4 font-medium"><?= htmlspecialchars($r['service_name']) ?></td>
                            <td class="px-6 py-4 font-medium italic"><?= date('d M, H:i', strtotime($r['appointment_time'])) ?></td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-3 py-1 border-2 border-black rounded text-xs font-black uppercase tracking-widest <?= $statusBg ?>">
                                    <?= $statusLbl ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- İşçi Kazançları (Sadece Patron Görünümü) -->
    <?php if ($userRoleInShop === 'Patron'): ?>
    <div class="mb-20">
        <h2 class="text-3xl font-headline font-black italic mb-6 flex items-center gap-2">
            <span class="material-symbols-outlined text-secondary text-4xl" style="font-variation-settings: 'FILL' 1;">badge</span>
            Personel Hasılatları
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (empty($workerEarnings)): ?>
                <div class="col-span-full border-4 border-dashed border-black p-8 text-center bg-surface-container-lowest sketch-shadow">
                    <p class="font-bold text-on-surface-variant font-medium">Henüz tamamlanmış personel kesimi bulunmuyor.</p>
                </div>
            <?php else: foreach ($workerEarnings as $we): ?>
                <div class="bg-white border-4 border-black p-6 rounded-2xl hover:-translate-y-1 hover:shadow-[6px_6px_0_#000] transition-all">
                    <div class="flex items-center gap-4 mb-4 border-b-2 border-black/10 pb-4">
                        <div class="w-12 h-12 bg-black text-white rounded-full flex items-center justify-center font-black text-2xl border-2 border-black shrink-0">
                            <?= mb_strtoupper(mb_substr($we['full_name'],0,1)) ?>
                        </div>
                        <h4 class="font-headline font-bold text-lg uppercase leading-tight italic"><?= htmlspecialchars($we['full_name']) ?></h4>
                    </div>
                    <div>
                        <span class="text-xs font-black tracking-widest uppercase text-stone-500 block mb-1">Dükkana Kazandırdığı</span>
                        <div class="text-4xl font-headline font-black text-[#006017]">
                            ₺<?= number_format($we['earned'], 0, '', '.') ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <?php endif; ?>

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
                backgroundColor: '#000000',
                borderColor: '#000000',
                borderWidth: 2,
                borderRadius: 4,
                hoverBackgroundColor: '#00751f'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#fff',
                    titleColor: '#000',
                    bodyColor: '#000',
                    borderColor: '#000',
                    borderWidth: 2,
                    callbacks: {
                        label: ctx => '₺' + ctx.parsed.y.toFixed(0)
                    }
                }
            },
            scales: {
                x: { 
                    grid: { color: 'rgba(0,0,0,0.1)', dashed: [5, 5] }, 
                    ticks: { color: '#000', font: { weight: 'bold' } } 
                },
                y: {
                    grid: { color: 'rgba(0,0,0,0.1)' },
                    ticks: { color: '#000', font: { weight: 'bold' }, callback: v => '₺'+v, stepSize: 50 },
                    beginAtZero: true,
                    suggestedMax: 50
                }
            }
        }
    });
})();
</script>
<?php endif; ?>
