<?php
$filter = in_array($_GET['filter'] ?? '', ['bekliyor','tamamlandi','iptal']) ? $_GET['filter'] : 'all';
$sql = "
    SELECT a.*,
           sh.shop_name,
           sv.service_name,
           e.full_name  AS employee_name,
           d.name       AS district_name,
           r.id         AS review_id
    FROM appointments a
    JOIN shops    sh ON a.shop_id     = sh.id
    JOIN services sv ON a.service_id  = sv.id
    JOIN users    e  ON a.employee_id = e.id
    LEFT JOIN districts d ON sh.district_id = d.id
    LEFT JOIN reviews r ON r.appointment_id = a.id
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

<div class="max-w-screen-xl mx-auto px-6 py-12">
    <header class="mb-12 text-center md:text-left">
        <h1 class="text-6xl font-headline font-extrabold text-black tracking-tighter mb-4 italic">Ajandam</h1>
        <p class="text-lg text-on-surface-variant max-w-md font-medium uppercase tracking-tight">Kişisel bakım takviminiz ve geçmiş işlemleriniz.</p>
    </header>
    
    <!-- Filtre Tabları -->
    <div class="flex flex-wrap gap-4 mb-12">
        <?php foreach ([
            'all'        => ['TÜMÜ', ''],
            'bekliyor'   => ['BEKLEYEN', 'text-secondary border-secondary bg-secondary-container/20'],
            'tamamlandi' => ['TAMAMLANAN', 'text-black border-black bg-surface-container-highest'],
            'iptal'      => ['İPTAL', 'text-error border-error bg-error-container/20'],
        ] as $key => [$label, $colorClasses]): 
            $isActive = $filter === $key;
        ?>
        <a href="?page=randevularim&filter=<?= $key ?>"
           class="flex items-center gap-2 border-2 <?= $isActive ? 'border-black bg-black text-white' : 'border-black bg-surface' ?> px-5 py-2 font-headline font-bold text-sm tracking-widest hover:-translate-y-1 transition-transform">
            <?= $label ?>
            <span class="bg-white text-black px-2 py-0.5 rounded-full text-xs <?= $isActive ? 'opacity-100' : 'opacity-80' ?> border border-black"><?= $counts[$key] ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Liste -->
    <?php if (empty($appointments)): ?>
    <div class="bg-surface-container-lowest sketchy-border p-12 flex flex-col items-center justify-center text-center rotate-1">
        <span class="material-symbols-outlined text-6xl mb-4 text-on-surface-variant opacity-50">calendar_month</span>
        <h2 class="font-headline font-black text-2xl mb-2">Bu kriterde randevu yok</h2>
        <p class="font-body text-on-surface-variant font-medium mb-6">Yeni bir randevu almak için ustanızı hemen seçin.</p>
        <a href="?page=kesfet" class="bg-black text-white px-8 py-3 rounded-xl border-2 border-black font-bold font-headline uppercase hover:-translate-y-1 transition-transform">
            BERBER KEŞFET
        </a>
    </div>
    <?php else: ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <?php foreach ($appointments as $a):
            $isPast    = strtotime($a['appointment_time']) < time();
            $isBekliyor = $a['status'] === 'bekliyor';
            
            $bgClass = '';
            $statusBadge = '';
            $statusLabel = '';
            
            switch($a['status']) {
                case 'bekliyor':
                    $bgClass = 'bg-surface-container-lowest';
                    $statusLabel = 'BEKLEYEN';
                    $statusBadge = 'bg-[#fefee5] border-secondary text-secondary';
                    break;
                case 'tamamlandi':
                    $bgClass = 'bg-surface-container-low opacity-80';
                    $statusLabel = 'TAMAMLANDI';
                    $statusBadge = 'bg-[#e7edb4] border-black text-black';
                    break;
                case 'iptal':
                    $bgClass = 'bg-surface-container-high opacity-60';
                    $statusLabel = 'İPTAL';
                    $statusBadge = 'bg-[#fe8b70] border-[#a54731] text-[#742410]';
                    break;
            }
        ?>
        <div class="sketchy-card <?= $bgClass ?> p-8 flex flex-col gap-6 hover:shadow-[6px_6px_0px_0px_rgba(0,0,0,1)] transition-all group" id="rcard-<?= $a['id'] ?>">
            
            <div class="flex justify-between items-start">
                <div class="flex gap-4 items-center">
                    <div class="w-16 h-16 rounded-full border-2 border-black flex items-center justify-center bg-white shrink-0 overflow-hidden text-2xl font-black">
                        <?= mb_strtoupper(mb_substr($a['employee_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <h3 class="text-xl font-black font-headline text-black italic line-clamp-1"><?= htmlspecialchars($a['shop_name']) ?></h3>
                        <p class="text-on-surface-variant font-medium text-sm line-clamp-1">👤 <?= htmlspecialchars($a['employee_name']) ?></p>
                        <p class="text-on-surface-variant font-bold text-sm mt-1">✂️ <?= htmlspecialchars($a['service_name']) ?></p>
                    </div>
                </div>
                <span id="badgeStatus-<?= $a['id'] ?>" class="px-3 py-1 rounded-md border-2 text-xs font-bold uppercase tracking-tighter whitespace-nowrap <?= $statusBadge ?>"><?= $statusLabel ?></span>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-white p-4 border-2 border-black border-dashed">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="material-symbols-outlined text-xs">calendar_today</span>
                        <span class="text-[10px] uppercase font-bold text-on-surface-variant tracking-widest">Tarih</span>
                    </div>
                    <p class="font-black text-lg"><?= date('d M Y', strtotime($a['appointment_time'])) ?></p>
                </div>
                <div class="bg-white p-4 border-2 border-black border-dashed">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="material-symbols-outlined text-xs">schedule</span>
                        <span class="text-[10px] uppercase font-bold text-on-surface-variant tracking-widest">Saat</span>
                    </div>
                    <p class="font-black text-lg"><?= date('H:i', strtotime($a['appointment_time'])) ?></p>
                </div>
            </div>
            
                <div class="flex items-center justify-between border-t border-black/10 pt-4 mt-auto">
                <div class="font-headline font-black text-xl">₺<?= number_format($a['price_at_that_time'], 2) ?></div>
                <div class="flex gap-2">
                    <?php if ($a['status'] === 'tamamlandi'): ?>
                        <?php if ($a['review_id']): ?>
                        <button disabled class="bg-surface-container-high text-stone-500 border-2 border-transparent px-4 py-2 font-bold uppercase text-xs cursor-not-allowed">
                            Değerlendirildi
                        </button>
                        <?php else: ?>
                        <button class="bg-[#3b82f6]/10 text-[#3b82f6] border-2 border-[#3b82f6] px-4 py-2 font-bold uppercase text-xs hover:bg-[#3b82f6] hover:text-white transition-colors" onclick="openReviewModal(<?= $a['id'] ?>, <?= $a['shop_id'] ?>)">
                            Değerlendir
                        </button>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if ($isBekliyor && !$isPast): ?>
                    <button class="bg-[#fe8b70] text-[#742410] border-2 border-[#a54731] px-4 py-2 font-bold uppercase text-xs hover:bg-[#a54731] hover:text-white transition-colors rc-cancel-btn" onclick="cancelAppointment(<?= $a['id'] ?>)">
                        İptal
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php endif; ?>
</div>

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
        const card = document.getElementById('rcard-' + id);
        const badge = document.getElementById('badgeStatus-' + id);
        const cancelBtn = card.querySelector('.rc-cancel-btn');
        
        // Update styling
        card.classList.remove('bg-surface-container-lowest');
        card.classList.add('bg-surface-container-high', 'opacity-60');
        
        badge.className = 'px-3 py-1 rounded-md border-2 text-xs font-bold uppercase tracking-tighter whitespace-nowrap bg-[#fe8b70] border-[#a54731] text-[#742410]';
        badge.textContent = 'İPTAL';
        
        if(cancelBtn) cancelBtn.remove();
    }
}

let currentReviewAppId = null;
let currentReviewShopId = null;

function openReviewModal(appId, shopId) {
    currentReviewAppId = appId;
    currentReviewShopId = shopId;
    document.getElementById('reviewModal').classList.remove('hidden');
    document.getElementById('reviewRating').value = 5;
    document.getElementById('reviewComment').value = '';
}

function closeReviewModal() {
    document.getElementById('reviewModal').classList.add('hidden');
}

async function submitReview() {
    const rating = document.getElementById('reviewRating').value;
    const comment = document.getElementById('reviewComment').value;
    
    if(!rating) return showToast('error', 'Lütfen yıldız verin!');
    
    const fd = new FormData();
    fd.set('action', 'submit_review');
    fd.set('appointment_id', currentReviewAppId);
    fd.set('shop_id', currentReviewShopId);
    fd.set('rating', rating);
    fd.set('comment', comment);
    
    const res = await fetch('musteri/api.php', { method: 'POST', body: fd });
    const data = await res.json();
    
    showToast(data.success ? 'success' : 'error', data.message);
    if(data.success) {
        closeReviewModal();
        setTimeout(() => location.reload(), 1000);
    }
}
</script>

<!-- Değerlendirme Modalı -->
<div id="reviewModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
    <div class="bg-surface border-4 border-black p-8 max-w-sm w-full mx-auto shadow-[12px_12px_0px_#000] rotate-1">
        <h3 class="font-headline font-black text-2xl mb-2">Puanla & Yorumla</h3>
        <p class="text-sm font-medium text-on-surface-variant mb-6">Ustanızın hizmetinden memnun kaldınız mı?</p>
        
        <div class="space-y-4">
            <div>
                <label class="block font-bold mb-1 text-sm uppercase">Puanınız</label>
                <select id="reviewRating" class="w-full border-2 border-black p-2 font-bold bg-white focus:outline-none">
                    <option value="5">⭐⭐⭐⭐⭐ Mükemmel (5)</option>
                    <option value="4">⭐⭐⭐⭐ Çok İyi (4)</option>
                    <option value="3">⭐⭐⭐ İyi (3)</option>
                    <option value="2">⭐⭐ Vasat (2)</option>
                    <option value="1">⭐ Kötü (1)</option>
                </select>
            </div>
            <div>
                <label class="block font-bold mb-1 text-sm uppercase">Yorumunuz</label>
                <textarea id="reviewComment" rows="3" class="w-full border-2 border-black p-2 font-medium bg-white focus:outline-none" placeholder="Berberiniz hakkında ne düşünüyorsunuz?"></textarea>
            </div>
        </div>
        
        <div class="flex gap-4 mt-6">
            <button onclick="closeReviewModal()" class="flex-1 bg-surface-container border-2 border-black py-3 font-bold uppercase text-sm hover:bg-black hover:text-white transition-colors">Vazgeç</button>
            <button onclick="submitReview()" class="flex-1 bg-secondary text-white border-2 border-black py-3 font-bold uppercase text-sm hover:opacity-90 transition-colors">Gönder</button>
        </div>
    </div>
</div>
