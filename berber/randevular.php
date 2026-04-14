<?php
if (!$shop):
?>
<div class="max-w-screen-xl mx-auto px-6 py-20 text-center">
    <div class="bg-surface-container-lowest sketch-border p-12 inline-block rotate-1 border-4 border-black">
        <span class="material-symbols-outlined text-8xl text-secondary mb-4 opacity-80" data-icon="store_off">calendar_month</span>
        <h2 class="text-4xl font-headline font-black mb-4">Önce bir dükkan oluşturun</h2>
        <a href="?page=dukkan" class="bg-black text-white px-8 py-3 rounded-xl border-2 border-black font-bold uppercase transition-transform hover:-translate-y-1 inline-block mt-4">Dükkan Oluştur</a>
    </div>
</div>
<?php else:
    // Süresi dolmuş bekleyen randevuları otomatik tamamla
    $pdo->prepare("
        UPDATE appointments
        SET status = 'tamamlandi'
        WHERE shop_id = ?
          AND status = 'bekliyor'
          AND DATE_ADD(appointment_time, INTERVAL total_duration MINUTE) <= NOW()
    ")->execute([$shop['id']]);

    $filter = in_array($_GET['filter'] ?? '', ['bekliyor','tamamlandi','iptal']) ? $_GET['filter'] : 'all';

    $empFilter = "";
    $params = [$shop['id']];
    if ($userRoleInShop === 'Çalışan') {
        $empFilter = " AND a.employee_id = ?";
        $params[] = $_SESSION['user_id'];
    }

    $sql = "
        SELECT a.*, COALESCE(u.full_name, a.walkin_name) AS customer_name,
               COALESCE(u.email, 'Yüz Yüze Müşteri') AS customer_email,
               sv.service_name, sv.price AS service_price,
               e.full_name AS employee_name
        FROM appointments a
        LEFT JOIN users u  ON a.customer_id  = u.id
        JOIN services  sv ON a.service_id   = sv.id
        JOIN users     e  ON a.employee_id  = e.id
        WHERE a.shop_id = ? $empFilter
    ";

    if ($filter !== 'all') {
        $sql .= ' AND a.status = ?';
        $params[] = $filter;
    }

    $sql .= ' ORDER BY a.appointment_time DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();

    // Sayılar
    $countParams = [$shop['id']];
    $countEmpFilter = "";
    if ($userRoleInShop === 'Çalışan') {
        $countEmpFilter = " AND employee_id = ?";
        $countParams[] = $_SESSION['user_id'];
    }
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM appointments WHERE shop_id = ? $countEmpFilter GROUP BY status");
    $stmt->execute($countParams);
    $counts = ['all' => 0, 'bekliyor' => 0, 'tamamlandi' => 0, 'iptal' => 0];
    foreach ($stmt->fetchAll() as $row) {
        $counts[$row['status']] = (int)$row['cnt'];
        $counts['all'] += (int)$row['cnt'];
    }
?>

<div class="max-w-screen-xl mx-auto px-6 py-6">
    <header class="mb-10 text-center md:text-left">
        <h1 class="text-5xl font-headline font-extrabold text-black tracking-tighter mb-2 italic">Gelen Talepler</h1>
        <p class="text-lg text-on-surface-variant font-medium uppercase tracking-widest">Tüm randevularınızı yönetin.</p>
    </header>

    <!-- Filtre Tabları -->
    <div class="flex flex-wrap gap-4 mb-10">
        <?php
        $filters = [
            'all'        => ['label' => 'TÜMÜ',       'colorClasses' => 'text-black border-black'],
            'bekliyor'   => ['label' => 'BEKLEYEN',   'colorClasses' => 'text-[#fbbf24] border-[#fbbf24] bg-[#fbbf24]/10'],
            'tamamlandi' => ['label' => 'TAMAMLANAN', 'colorClasses' => 'text-secondary border-secondary bg-secondary/10'],
            'iptal'      => ['label' => 'İPTAL',      'colorClasses' => 'text-error border-error bg-error/10'],
        ];
        foreach ($filters as $key => $f):
            $isActive = $filter === $key;
        ?>
        <a href="?page=randevular&filter=<?= $key ?>"
           class="flex items-center gap-2 border-2 <?= $isActive ? 'border-black bg-black text-white' : 'border-black bg-surface hover:-translate-y-0.5 transition-transform' ?> px-5 py-2 font-headline font-bold text-sm tracking-widest">
            <?= $f['label'] ?>
            <span class="bg-white text-black px-2 py-0.5 rounded-full text-xs <?= $isActive ? 'opacity-100' : 'opacity-80' ?> border border-black font-black"><?= $counts[$key] ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Randevu Tablosu -->
    <?php if (empty($appointments)): ?>
    <div class="bg-surface-container border-4 border-black border-dashed p-16 text-center rounded-xl rotate-1 opacity-80">
        <span class="material-symbols-outlined text-6xl opacity-30 mb-4 text-black">search_off</span>
        <h2 class="font-headline font-black text-3xl">Kayıt Bulunamadı</h2>
    </div>
    <?php else: ?>

    <div class="bg-surface-container-low border-4 border-black border-collapse sketch-shadow rounded-xl overflow-hidden overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[900px]">
            <thead>
                <tr class="bg-surface-container-lowest text-stone-500 text-xs uppercase tracking-widest font-black border-b-4 border-black">
                    <th class="px-6 py-5 border-r border-black/10">ID</th>
                    <th class="px-6 py-5 border-r border-black/10">Müşteri</th>
                    <th class="px-6 py-5 border-r border-black/10">Detaylar</th>
                    <th class="px-6 py-5 border-r border-black/10">Tarih</th>
                    <th class="px-6 py-5 text-right border-r border-black/10">Ücret</th>
                    <th class="px-6 py-5 text-center border-r border-black/10">Durum</th>
                    <th class="px-6 py-5 text-center">İşlem</th>
                </tr>
            </thead>
            <tbody class="divide-y-2 divide-black/10">
                <?php foreach ($appointments as $a):
                    $statusBg = 'bg-surface-container-highest text-stone-500';
                    $statusLbl = 'BİLİNMİYOR';
                    if ($a['status'] === 'bekliyor') { $statusBg = 'bg-[#fefee5] border-[#fbbf24] text-[#d97706]'; $statusLbl = 'BEKLİYOR'; }
                    else if ($a['status'] === 'tamamlandi') { $statusBg = 'bg-secondary text-white border-black'; $statusLbl = 'TAMAMLANDI'; }
                    else if ($a['status'] === 'iptal') { $statusBg = 'bg-error text-white border-none'; $statusLbl = 'İPTAL'; }

                    // 24 saat kontrolü için appointment_time'ı JS'e güvenli şekilde aktar
                    $apptTimeJs = json_encode($a['appointment_time']);
                    $isWalkin = empty($a['customer_id']);
                ?>
                <tr id="app-<?= $a['id'] ?>" class="hover:bg-white transition-colors">
                    <td class="px-6 py-6 border-r border-black/10 font-bold opacity-30">#<?= $a['id'] ?></td>

                    <td class="px-6 py-6 border-r border-black/10">
                        <div class="font-bold text-lg text-black mb-1"><?= htmlspecialchars($a['customer_name']) ?></div>
                        <div class="font-medium text-xs text-stone-500">
                            <?php if ($isWalkin): ?>
                                <span class="bg-surface-container-highest border border-black px-1.5 py-0.5 rounded text-[10px] font-black uppercase">Yüz Yüze</span>
                            <?php else: ?>
                                <?= htmlspecialchars($a['customer_email']) ?>
                            <?php endif; ?>
                        </div>
                    </td>

                    <td class="px-6 py-6 border-r border-black/10">
                        <div class="text-sm font-bold uppercase tracking-widest mb-1 flex items-center gap-1"><span class="material-symbols-outlined text-sm">content_cut</span> <?= htmlspecialchars($a['service_name']) ?></div>
                        <div class="text-xs font-bold text-stone-500 uppercase tracking-widest flex items-center gap-1"><span class="material-symbols-outlined text-sm">person</span> <?= htmlspecialchars($a['employee_name']) ?></div>
                    </td>

                    <td class="px-6 py-6 border-r border-black/10">
                        <div class="font-headline font-black text-lg"><?= date('d M Y', strtotime($a['appointment_time'])) ?></div>
                        <div class="font-bold text-stone-500 text-sm"><?= date('H:i', strtotime($a['appointment_time'])) ?></div>
                    </td>

                    <td class="px-6 py-6 font-headline font-black text-2xl text-secondary text-right border-r border-black/10">
                        ₺<?= number_format($a['price_at_that_time'], 0) ?>
                    </td>

                    <td class="px-6 py-6 text-center border-r border-black/10">
                        <span class="inline-block px-3 py-1 border-2 border-black rounded text-xs font-black uppercase tracking-widest <?= $statusBg ?>" id="badge-<?= $a['id'] ?>">
                            <?= $statusLbl ?>
                        </span>
                    </td>

                    <td class="px-6 py-6 text-center pb-6">
                        <div class="flex flex-col gap-2 scale-90 md:scale-100 items-center justify-center">
                            <?php if ($a['status'] === 'bekliyor'): ?>
                            <div id="action-<?= $a['id'] ?>">
                                <button class="w-full bg-secondary text-white border-2 border-black px-4 py-2 text-xs font-black uppercase tracking-widest hover:-translate-y-0.5 transition-transform"
                                    onclick="kabul(<?= $a['id'] ?>, <?= $apptTimeJs ?>)">✔ KABUL ET</button>
                            </div>
                            <?php elseif ($a['status'] === 'tamamlandi'): ?>
                            <span class="text-stone-400 font-bold uppercase text-xs">Tamamlandı</span>
                            <?php else: ?>
                            <span class="text-stone-400 font-bold uppercase text-xs">İptal Edildi</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php endif; endif; ?>
</div>

<script>
function kabul(id, appointmentTime) {
    const now = new Date();
    const appt = new Date(appointmentTime.replace(' ', 'T'));
    const hoursLeft = (appt - now) / (1000 * 60 * 60);

    let cancelHtml;
    if (hoursLeft > 24) {
        cancelHtml = `<button class="w-full bg-white text-error border-2 border-black px-3 py-2 text-xs font-black uppercase tracking-widest hover:bg-error hover:text-white transition-colors mt-1"
            onclick="cancelApp(${id})">✕ İptal Et</button>`;
    } else {
        cancelHtml = `<div class="text-[10px] font-bold text-stone-400 text-center mt-1 leading-tight">24 saate az kaldı<br>iptal edilemez</div>`;
    }

    document.getElementById('action-' + id).innerHTML = `
        <div class="flex flex-col gap-1 items-center">
            <span class="text-secondary font-black text-xs uppercase tracking-widest">✔ Kabul Edildi</span>
            ${cancelHtml}
        </div>`;
}

async function cancelApp(id) {
    const fd = new FormData();
    fd.set('action', 'update_appointment');
    fd.set('appointment_id', id);
    fd.set('status', 'iptal');

    const res  = await fetch('berber/api.php', { method: 'POST', body: fd });
    const data = await res.json();
    showToast(data.success ? 'success' : 'error', data.message);

    if (data.success) {
        const badge = document.getElementById('badge-' + id);
        badge.className = 'inline-block px-3 py-1 rounded text-xs font-black uppercase tracking-widest bg-error text-white border-[2px] border-transparent';
        badge.textContent = 'İPTAL';
        document.getElementById('action-' + id).innerHTML =
            `<span class="text-stone-400 font-bold uppercase text-xs">İptal Edildi</span>`;
    }
}
</script>
