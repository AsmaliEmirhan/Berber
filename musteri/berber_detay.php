<?php
$shopId = (int)($_GET['shop_id'] ?? 0);

if (!$shopId) {
    echo '<div class="max-w-4xl mx-auto px-6 py-20 text-center"><span class="material-symbols-outlined text-6xl text-error mb-4">error</span><h2 class="font-headline font-black text-3xl mb-6">Dükkan bulunamadı</h2>
          <a href="?page=kesfet" class="bg-black text-white px-8 py-3 rounded-xl border-2 border-black font-bold uppercase transition-transform hover:-translate-y-1">Geri Dön</a></div>';
    return;
}

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
    echo '<div class="max-w-4xl mx-auto px-6 py-20 text-center"><span class="material-symbols-outlined text-6xl text-error mb-4">error</span><h2 class="font-headline font-black text-3xl mb-6">Dükkan bulunamadı</h2>
          <a href="?page=kesfet" class="bg-black text-white px-8 py-3 rounded-xl border-2 border-black font-bold uppercase transition-transform hover:-translate-y-1">Geri Dön</a></div>';
    return;
}

$stmt = $pdo->prepare('SELECT * FROM services WHERE shop_id = ? ORDER BY service_name');
$stmt->execute([$shopId]);
$services = $stmt->fetchAll();

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

$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE shop_id = ? AND status = 'tamamlandi'");
$stmt->execute([$shopId]);
$completedCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(id) as review_count FROM reviews WHERE shop_id = ?");
$stmt->execute([$shopId]);
$revStats = $stmt->fetch();
$avgRating = $revStats['avg_rating'] ? round($revStats['avg_rating'], 1) : 0;
$revCount = $revStats['review_count'] ?? 0;

$stmt = $pdo->prepare("
    SELECT r.rating, r.comment, r.created_at, u.full_name 
    FROM reviews r 
    JOIN users u ON r.customer_id = u.id 
    WHERE r.shop_id = ? 
    ORDER BY r.created_at DESC
");
$stmt->execute([$shopId]);
$reviews = $stmt->fetchAll();
?>

<div class="max-w-screen-xl mx-auto px-6 py-12">
    <!-- Geri butonu -->
    <div class="mb-8">
        <a href="?page=kesfet" class="inline-flex items-center gap-2 font-bold uppercase text-sm border-b-2 border-black pb-0.5 hover:text-secondary hover:border-secondary transition-colors">
            <span class="material-symbols-outlined text-lg">arrow_back</span> Geri
        </a>
    </div>

    <!-- Dükkan Hero -->
    <div class="bg-surface-container-lowest sketchy-border p-8 md:p-12 flex flex-col md:flex-row gap-8 items-center md:items-stretch group mb-12">
        <div class="w-32 h-32 md:w-48 md:h-48 bg-[#e7edb4] rounded-full border-4 border-black flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform overflow-hidden relative">
            <span class="font-headline font-black text-7xl md:text-8xl opacity-40"><?= mb_strtoupper(mb_substr($shop['shop_name'], 0, 1)) ?></span>
        </div>
        
        <div class="flex-grow flex flex-col justify-center text-center md:text-left">
            <h1 class="font-headline font-black text-4xl md:text-6xl text-black italic mb-2"><?= htmlspecialchars($shop['shop_name']) ?></h1>
            <div class="flex flex-wrap items-center justify-center md:justify-start gap-4 text-sm font-bold uppercase tracking-widest text-on-surface-variant mb-6">
                <!-- Stars -->
                <div class="flex items-center gap-1 text-[#fbbf24] drop-shadow-[1px_1px_0px_#000]">
                    <span class="font-black text-lg"><?= $avgRating > 0 ? number_format($avgRating, 1) : 'Yeni' ?></span>
                    <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">star</span>
                    <span class="text-xs text-on-surface-variant ml-1 font-bold">(<?= $revCount ?>)</span>
                </div>
                
                <?php if ($shop['district_name']): ?>
                <span class="flex items-center gap-1"><span class="material-symbols-outlined text-lg">location_on</span> <?= htmlspecialchars($shop['district_name']) ?></span>
                <?php endif; ?>
                <span class="flex items-center gap-1"><span class="material-symbols-outlined text-lg">person</span> <?= htmlspecialchars($shop['owner_name']) ?></span>
            </div>
            <?php if ($shop['address']): ?>
                <p class="font-medium bg-surface-container-high border-2 border-black border-dashed px-4 py-2 inline-block max-w-lg mb-6">
                    <?= htmlspecialchars($shop['address']) ?>
                </p>
            <?php endif; ?>
            
            <div class="flex flex-wrap gap-8 justify-center md:justify-start mt-auto items-end">
                <div class="text-center">
                    <div class="font-black text-2xl"><?= count($services) ?></div>
                    <div class="text-xs font-bold uppercase tracking-widest text-[#5e5e5e]">Hizmet</div>
                </div>
                <div class="text-center">
                    <div class="font-black text-2xl"><?= count($employees) ?></div>
                    <div class="text-xs font-bold uppercase tracking-widest text-[#5e5e5e]">Personel</div>
                </div>
                <div class="text-center">
                    <div class="font-black text-2xl text-secondary"><?= $completedCount ?></div>
                    <div class="text-xs font-bold uppercase tracking-widest text-secondary">Tamamlanan</div>
                </div>
                <?php if (!empty($services)): ?>
                <button onclick="openBooking(<?= $shopId ?>)"
                    class="bg-secondary text-white px-8 py-3 rounded-xl border-2 border-black font-black uppercase text-sm flex items-center gap-2 hover:-translate-y-1 transition-transform drop-shadow-[4px_4px_0px_rgba(0,0,0,1)] hover:drop-shadow-none">
                    <span class="material-symbols-outlined">calendar_month</span> RANDEVU AL
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Decorative element -->
        <span class="material-symbols-outlined text-8xl absolute top-4 right-4 text-black opacity-10 pointer-events-none rotate-12">storefront</span>
    </div>

    <!-- Hizmetler -->
    <header class="mb-8 text-center md:text-left">
        <h2 class="font-headline font-black text-4xl italic text-black uppercase">Sunulan Hizmetler</h2>
        <p class="font-body font-medium text-on-surface-variant uppercase tracking-widest">Size uygun olanı seçiniz</p>
    </header>

    <?php if (empty($services)): ?>
    <div class="bg-surface-container-low sketchy-border p-12 text-center flex flex-col items-center rotate-1 mb-12">
        <span class="material-symbols-outlined text-6xl opacity-40 mb-4">content_cut</span>
        <h3 class="font-headline font-black text-2xl">Bu dükkan henüz hizmet eklememiş.</h3>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-16">
        <?php foreach ($services as $index => $s): ?>
        <?php 
            // Alternate rotations slightly
            $tilt = ($index % 2 === 0) ? 'asymmetric-tilt-left' : 'asymmetric-tilt-right';
        ?>
        <div class="group relative bg-[#fefee5] border-4 border-black rounded-xl p-8 flex items-center justify-between overflow-hidden <?= $tilt ?>">
            <div class="flex flex-col items-start z-10 w-full">
                <span class="font-headline text-3xl font-black text-black mb-1 line-clamp-1 break-all w-full pr-12"><?= htmlspecialchars($s['service_name']) ?></span>
                <p class="font-bold text-on-surface-variant flex items-center gap-2 mb-4">
                    <span class="material-symbols-outlined text-sm">schedule</span> <?= $s['duration_minutes'] ?> Dakika
                </p>
                <div class="font-headline font-black text-2xl">₺<?= number_format($s['price'], 2) ?></div>
            </div>
            <!-- Decorative icon -->
            <div class="absolute top-1/2 right-4 -translate-y-1/2 opacity-10">
                <span class="material-symbols-outlined text-[8rem]">content_cut</span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Çalışanlar -->
    <?php if (!empty($employees)): ?>
    <div class="mb-16">
        <h2 class="font-headline font-black text-3xl italic text-black uppercase mb-6 text-center md:text-left border-b-4 border-black inline-block pb-1">Personelimiz</h2>
        <div class="flex flex-wrap gap-4 justify-center md:justify-start">
            <?php foreach ($employees as $emp): ?>
            <div class="bg-white border-2 border-black flex items-center gap-3 px-4 py-3 rounded-full hover:bg-surface-container-highest transition-colors cursor-default drop-shadow-[2px_2px_0px_rgba(0,0,0,1)]">
                <div class="w-10 h-10 bg-black text-white rounded-full flex items-center justify-center font-bold text-lg">
                    <?= mb_strtoupper(mb_substr($emp['full_name'], 0, 1)) ?>
                </div>
                <span class="font-bold uppercase tracking-widest text-sm pr-2"><?= htmlspecialchars($emp['full_name']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Yorumlar -->
    <div class="mb-12">
        <h2 class="font-headline font-black text-3xl italic text-black uppercase mb-6 text-center md:text-left border-b-4 border-black inline-block pb-1">Müşteri Yorumları</h2>
        
        <?php if (empty($reviews)): ?>
            <div class="bg-surface-container-lowest sketchy-border p-8 text-center text-on-surface-variant font-medium">
                Bu dükkan için henüz yorum yapılmamış. İlk değerlendiren siz olun!
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php foreach ($reviews as $rev): 
                    // Hide name logic (e.g. "Emirhan Yıldırım" -> "E****** Y******")
                    $nameParts = explode(' ', $rev['full_name']);
                    $hiddenName = [];
                    foreach ($nameParts as $part) {
                        $len = mb_strlen($part);
                        if ($len > 1) {
                            $hiddenName[] = mb_substr($part, 0, 1) . str_repeat('*', $len - 1);
                        } else {
                            $hiddenName[] = $part;
                        }
                    }
                    $hiddenNameStr = implode(' ', $hiddenName);
                ?>
                <div class="bg-white border-4 border-black rounded-xl p-6 hover:-translate-y-1 transition-transform shadow-[6px_6px_0px_0px_rgba(0,0,0,1)]">
                    <div class="flex justify-between items-start border-b-2 border-black/10 pb-4 mb-4">
                        <div>
                            <div class="font-black text-lg uppercase tracking-tight"><?= htmlspecialchars($hiddenNameStr) ?></div>
                            <div class="text-xs font-bold text-stone-400 mt-1"><?= date('d M Y, H:i', strtotime($rev['created_at'])) ?></div>
                        </div>
                        <div class="flex text-[#fbbf24] drop-shadow-[1px_1px_0px_#000]">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <span class="material-symbols-outlined text-lg" <?= $i <= $rev['rating'] ? 'style="font-variation-settings:\'FILL\' 1"' : '' ?>>star</span>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php if ($rev['comment']): ?>
                        <p class="font-medium text-stone-700 italic">"<?= nl2br(htmlspecialchars($rev['comment'])) ?>"</p>
                    <?php else: ?>
                        <p class="font-medium text-stone-400 italic text-sm">Puan bırakıldı, yorum yazılmadı.</p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Randevu Booking Modal Şablonu -->
<template id="bookingTemplate">
    <div class="w-full h-full flex flex-col font-['Work_Sans']">

        <!-- Step indicator -->
        <div class="flex items-center justify-between px-4 py-4 border-b-2 border-black bg-surface-container-low mb-6">
            <div class="flex flex-col items-center">
                <div class="step-dot w-8 h-8 rounded-full border-2 border-black bg-white flex items-center justify-center font-bold font-headline transition-colors text-sm z-10" data-step="1">1</div>
                <span class="text-[9px] font-bold uppercase mt-1">Hizmet</span>
            </div>
            <div class="step-line h-1 flex-grow bg-black/20 mx-1"></div>
            <div class="flex flex-col items-center">
                <div class="step-dot w-8 h-8 rounded-full border-2 border-black bg-white flex items-center justify-center font-bold font-headline transition-colors text-sm z-10" data-step="2">2</div>
                <span class="text-[9px] font-bold uppercase mt-1">Ekstralar</span>
            </div>
            <div class="step-line h-1 flex-grow bg-black/20 mx-1"></div>
            <div class="flex flex-col items-center">
                <div class="step-dot w-8 h-8 rounded-full border-2 border-black bg-white flex items-center justify-center font-bold font-headline transition-colors text-sm z-10" data-step="3">3</div>
                <span class="text-[9px] font-bold uppercase mt-1">Personel</span>
            </div>
            <div class="step-line h-1 flex-grow bg-black/20 mx-1"></div>
            <div class="flex flex-col items-center">
                <div class="step-dot w-8 h-8 rounded-full border-2 border-black bg-white flex items-center justify-center font-bold font-headline transition-colors text-sm z-10" data-step="4">4</div>
                <span class="text-[9px] font-bold uppercase mt-1">Tarih</span>
            </div>
            <div class="step-line h-1 flex-grow bg-black/20 mx-1"></div>
            <div class="flex flex-col items-center">
                <div class="step-dot w-8 h-8 rounded-full border-2 border-black bg-white flex items-center justify-center font-bold font-headline transition-colors text-sm z-10" data-step="5">5</div>
                <span class="text-[9px] font-bold uppercase mt-1">Saat</span>
            </div>
            <div class="step-line h-1 flex-grow bg-black/20 mx-1"></div>
            <div class="flex flex-col items-center">
                <div class="step-dot w-8 h-8 rounded-full border-2 border-black bg-white flex items-center justify-center font-bold font-headline transition-colors text-sm z-10" data-step="6">6</div>
                <span class="text-[9px] font-bold uppercase mt-1">Onay</span>
            </div>
        </div>

        <div class="flex-grow px-6 relative" style="min-height: 300px;">

            <!-- Step 1: Hizmet Seç -->
            <div class="step-panel active absolute inset-x-6 top-0 hidden opacity-0 transition-opacity duration-200" id="stepPanel1">
                <label class="block font-bold uppercase text-xs tracking-widest mb-3 px-1">Hizmet Seçin <span class="text-error">*</span></label>
                <div id="servicesContainer" class="flex flex-col gap-2 max-h-[220px] overflow-y-auto pr-1">
                    <!-- JS ile doldurulacak -->
                </div>
                <div class="flex justify-end mt-8">
                    <button id="serviceNextBtn" disabled
                        class="bg-black text-white px-8 py-3 rounded-xl border-2 border-black font-bold uppercase hover:-translate-y-1 transition-transform disabled:opacity-40 disabled:cursor-not-allowed"
                        onclick="bookingNextStep(2)">Devam &rarr;</button>
                </div>
            </div>

            <!-- Step 2: Ekstralar -->
            <div class="step-panel absolute inset-x-6 top-0 hidden opacity-0 transition-opacity duration-200" id="stepPanel2">
                <div id="selectedServiceBox" class="bg-surface-container-highest border-2 border-black border-dashed p-4 mb-6 flex justify-between items-center rounded-xl"></div>

                <div class="mb-6">
                    <label class="block font-bold uppercase text-xs tracking-widest mb-2 px-1">Bu hizmetin yanına şunları da ekleyin:</label>
                    <div id="extraServicesContainer" class="flex flex-col gap-2 max-h-[120px] overflow-y-auto pr-2 pb-2">
                        <!-- Javascript ile yüklenecek -->
                    </div>
                </div>

                <div class="flex justify-between items-center mt-6">
                    <div class="font-bold text-sm">Toplam: <span id="cumulPriceDur" class="text-secondary font-black">---</span></div>
                    <div class="flex gap-2">
                        <button class="border-2 border-black px-6 py-3 rounded-xl font-bold uppercase hover:bg-black hover:text-white transition-colors" onclick="bookingNextStep(1)">&larr; Geri</button>
                        <button class="bg-black text-white px-8 py-3 rounded-xl border-2 border-black font-bold uppercase hover:-translate-y-1 transition-transform" onclick="bookingNextStep(3)">Devam &rarr;</button>
                    </div>
                </div>
            </div>

            <!-- Step 3: Personel -->
            <div class="step-panel absolute inset-x-6 top-0 hidden opacity-0 transition-opacity duration-200" id="stepPanel3">
                <div class="mb-6">
                    <label class="block font-bold uppercase text-xs tracking-widest mb-2 px-1">Personel Seçin <span class="text-error">*</span></label>
                    <select id="employeeSelect" class="w-full bg-surface-container-lowest border-2 border-black rounded-lg px-4 py-3 font-headline font-bold focus:outline-none focus:border-secondary transition-colors appearance-none pr-8">
                        <option value="">Personel yükleniyor…</option>
                    </select>
                </div>

                <div class="flex justify-between mt-8">
                    <button class="border-2 border-black px-6 py-3 rounded-xl font-bold uppercase hover:bg-black hover:text-white transition-colors" onclick="bookingNextStep(2)">&larr; Geri</button>
                    <button class="bg-black text-white px-8 py-3 rounded-xl border-2 border-black font-bold uppercase hover:-translate-y-1 transition-transform" onclick="bookingNextStep(4)">Devam &rarr;</button>
                </div>
            </div>

            <!-- Step 4: Tarih -->
            <div class="step-panel absolute inset-x-6 top-0 hidden opacity-0 transition-opacity duration-200" id="stepPanel4">
                <div class="mb-6">
                    <label class="block font-bold uppercase text-xs tracking-widest mb-2 px-1">Randevu Tarihi <span class="text-error">*</span></label>
                    <input type="date" id="dateInput" class="w-full bg-surface-container-lowest border-2 border-black rounded-lg px-4 py-3 font-headline font-bold focus:outline-none focus:border-secondary transition-colors"
                           min="<?= date('Y-m-d') ?>"
                           max="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                </div>
                <div class="flex justify-between mt-8">
                    <button class="border-2 border-black px-6 py-3 rounded-xl font-bold uppercase hover:bg-black hover:text-white transition-colors" onclick="bookingNextStep(3)">&larr; Geri</button>
                    <button class="bg-black text-white px-8 py-3 rounded-xl border-2 border-black font-bold uppercase hover:-translate-y-1 transition-transform" onclick="bookingNextStep(5)">Devam &rarr;</button>
                </div>
            </div>

            <!-- Step 5: Saat -->
            <div class="step-panel absolute inset-x-6 top-0 hidden opacity-0 transition-opacity duration-200" id="stepPanel5">
                <p class="font-bold text-sm text-center mb-4 text-on-surface-variant uppercase tracking-widest">MÜSAİT BİR SAATE TIKLAYIN</p>

                <div id="slotsContainer" class="max-h-[140px] overflow-y-auto mb-4 border-2 border-black border-dashed p-4 rounded-xl bg-surface-container">
                    <p class="font-bold text-center opacity-50">Tarih ve personel seçtikten sonra saatler yüklenir.</p>
                </div>

                <div class="bg-[#fefee5] border-2 border-black p-4 rounded-xl mt-2">
                    <label class="block font-bold uppercase text-xs tracking-widest mb-2 px-1 text-secondary">Manuel Saat Gir</label>
                    <div class="flex gap-4">
                        <input type="time" id="manualTimeInput" class="flex-grow bg-white border-2 border-black rounded-lg px-4 py-2 font-headline font-bold focus:outline-none" min="09:00" max="19:00">
                        <button class="bg-black text-white px-4 py-2 rounded-lg font-bold hover:bg-secondary transition-colors text-sm uppercase" onclick="selectManualTime()">Seç</button>
                    </div>
                </div>

                <div class="flex justify-between mt-6">
                    <button class="border-2 border-black px-6 py-3 rounded-xl font-bold uppercase hover:bg-black hover:text-white transition-colors" onclick="bookingNextStep(4)">&larr; Geri</button>
                    <button class="bg-black text-white px-8 py-3 rounded-xl border-2 border-black font-bold uppercase disabled:opacity-50 disabled:cursor-not-allowed hover:-translate-y-1 transition-transform" id="slotNextBtn" disabled onclick="bookingNextStep(6)">Devam &rarr;</button>
                </div>
            </div>

            <!-- Step 6: Onay -->
            <div class="step-panel absolute inset-x-6 top-0 hidden opacity-0 transition-opacity duration-200" id="stepPanel6">
                <div id="bookingSummary" class="bg-surface-container-highest border-2 border-black p-6 rounded-xl space-y-3 font-medium"></div>

                <div class="flex justify-between mt-8">
                    <button class="border-2 border-black px-6 py-3 rounded-xl font-bold uppercase hover:bg-black hover:text-white transition-colors" onclick="bookingNextStep(5)">&larr; Geri</button>
                    <button class="bg-secondary text-white px-8 py-3 rounded-xl border-2 border-black font-bold font-headline uppercase hover:-translate-y-1 transition-transform flex items-center gap-2 drop-shadow-[4px_4px_0px_rgba(0,0,0,1)] hover:drop-shadow-none" id="confirmBtn" onclick="confirmBooking()">
                        <span class="material-symbols-outlined hidden animate-spin" id="confirmSpinner">sync</span>
                        ✔ Onayla
                    </button>
                </div>
            </div>

        </div>
    </div>
    <style>
        .step-panel.active { display: block !important; opacity: 1 !important; z-index: 20; position: relative; }
        .step-dot.active { background-color: #000; color: #fff; }
        .step-dot.done { background-color: #e2e2e2; border-color: #000; color: #000; }
        .step-line.done { background-color: #000; }
        .slots-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
        .slot-btn { border: 2px solid #000; background: #fff; padding: 8px; border-radius: 8px; font-weight: bold; }
        .slot-btn.available:hover { background: #e7edb4; }
        .slot-btn.unavailable { opacity: 0.4; cursor: not-allowed; }
        .slot-btn.selected { background: #000; color: #fff; }
        .summary-row { display: flex; justify-content: space-between; border-bottom: 1px dashed #000; padding-bottom: 8px; }
        .summary-price { font-size: 1.25rem; font-weight: 800; }
        .service-select-btn.selected-service { background: #000 !important; color: #fff !important; }
        .service-select-btn.selected-service .svc-price { color: #a3e635; }
    </style>
</template>

<script>
window._shopEmployees = <?= json_encode($employees) ?>;
window._shopServices  = <?= json_encode($services) ?>;
</script>
