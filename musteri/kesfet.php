<?php
$cities = $pdo->query('SELECT id, name FROM cities ORDER BY name')->fetchAll();

$filterCity     = isset($_GET['city'])     ? (int)$_GET['city']     : 0;
$filterDistrict = isset($_GET['district']) ? (int)$_GET['district'] : 0;
$search         = trim($_GET['q'] ?? '');

$districts = [];
if ($filterCity) {
    $stmt = $pdo->prepare('SELECT id, name FROM districts WHERE city_id = ? ORDER BY name');
    $stmt->execute([$filterCity]);
    $districts = $stmt->fetchAll();
}

$sql    = "
    SELECT s.*,
           d.name                              AS district_name,
           u.full_name                         AS owner_name,
           COUNT(DISTINCT sv.id)               AS service_count,
           COUNT(DISTINCT se.employee_id)      AS employee_count,
           AVG(r.rating)                       AS avg_rating,
           COUNT(DISTINCT r.id)                AS review_count
    FROM shops s
    JOIN users u ON s.owner_id = u.id
    LEFT JOIN districts d ON s.district_id = d.id
    LEFT JOIN services sv ON sv.shop_id = s.id
    LEFT JOIN shop_employees se ON se.shop_id = s.id
    LEFT JOIN users emp ON se.employee_id = emp.id
    LEFT JOIN reviews r ON r.shop_id = s.id
    WHERE 1=1
";
$params = [];

if ($filterCity) {
    $sql .= ' AND s.city_id = ?';
    $params[] = $filterCity;
}
if ($filterDistrict) {
    $sql .= ' AND s.district_id = ?';
    $params[] = $filterDistrict;
}
if ($search) {
    $sql .= ' AND (s.shop_name LIKE ? OR u.full_name LIKE ? OR emp.full_name LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sort = $_GET['sort'] ?? 'newest';
if ($sort === 'top_rated') {
    $sql .= ' GROUP BY s.id, d.name, u.full_name ORDER BY avg_rating DESC, s.created_at DESC';
} else {
    $sql .= ' GROUP BY s.id, d.name, u.full_name ORDER BY s.created_at DESC';
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$shops = $stmt->fetchAll();
?>

<div class="max-w-screen-xl mx-auto px-6 py-8 relative">
    <!-- Filter Header Section -->
    <section class="mb-12">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <h1 class="font-headline font-extrabold text-5xl md:text-7xl text-black leading-tight mb-4 italic">
                    Ustanı <span class="underline decoration-secondary decoration-4">Keşfet</span>
                </h1>
                <p class="font-body text-xl text-on-surface-variant max-w-xl">
                    Şehrin en iyi berberleri, senin için tek bir defterde toplandı. Hemen randevunu al.
                </p>
            </div>
        </div>
        
        <form method="GET" action="musteri_paneli.php" class="mt-8 flex flex-wrap gap-4 items-center">
            <input type="hidden" name="page" value="kesfet">
            
            <div class="relative flex-grow max-w-md">
                <input class="w-full bg-surface-container-lowest border-2 border-black rounded-xl px-4 py-3 focus:outline-none focus:border-secondary font-headline font-bold uppercase transition-colors" placeholder="Dükkan veya berber ara..." type="text" name="q" value="<?= htmlspecialchars($search) ?>"/>
                <span class="material-symbols-outlined absolute right-4 top-3 text-black">search</span>
            </div>
            
            <select name="city" id="kesfetCitySelect" class="bg-surface-container-lowest border-2 border-black rounded-xl px-4 py-3 font-headline font-bold focus:outline-none focus:border-secondary transition-colors appearance-none pr-8">
                <option value="">TÜM İLLER</option>
                <?php foreach ($cities as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $filterCity == $c['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars(mb_strtoupper($c['name'])) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select name="district" id="kesfetDistrictSelect" class="bg-surface-container-lowest border-2 border-black rounded-xl px-4 py-3 font-headline font-bold focus:outline-none focus:border-secondary transition-colors appearance-none pr-8">
                <option value=""><?= empty($districts) ? 'Önce İl Seçin' : 'TÜM İLÇELER' ?></option>
                <?php foreach ($districts as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $filterDistrict == $d['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars(mb_strtoupper($d['name'])) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select name="sort" class="bg-surface border-2 border-black rounded-xl px-4 py-3 font-headline font-bold focus:outline-none focus:border-secondary transition-colors appearance-none pr-8">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Yeniden Eskiye</option>
                <option value="top_rated" <?= $sort === 'top_rated' ? 'selected' : '' ?>>En Yüksek Puanlı</option>
            </select>
            
            <button type="submit" class="bg-black text-white px-8 py-3 rounded-xl border-2 border-black font-bold font-headline uppercase hover:-translate-y-1 active:translate-y-0 transition-transform cursor-pointer">
                Filtrele
            </button>
            
            <?php if ($filterCity || $filterDistrict || $search): ?>
                <a href="?page=top10&city=<?= $filterCity ?>&district=<?= $filterDistrict ?>&q=<?= urlencode($search) ?>" class="bg-[#fbbf24] text-black px-6 py-3 rounded-xl border-2 border-black font-bold font-headline uppercase hover:-translate-y-1 active:translate-y-0 transition-transform flex items-center gap-2 drop-shadow-[2px_2px_0_#000]">
                    <span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1;">star</span> En İyiler
                </a>
                <a href="?page=kesfet" class="text-sm font-bold underline px-4 hover:text-error">TEMİZLE</a>
            <?php endif; ?>
        </form>
    </section>

    <!-- Search Results Grid -->
    <div class="grid grid-cols-1 md:grid-cols-12 gap-8 items-start">
        <div class="md:col-span-8 flex flex-col gap-8">
            
            <?php if (empty($shops)): ?>
            <div class="bg-surface-container-lowest sketchy-border p-10 text-center flex flex-col items-center">
                <span class="material-symbols-outlined text-6xl text-secondary mb-4">search_off</span>
                <h2 class="font-headline font-black text-2xl mb-2">Dükkan Bulunamadı</h2>
                <p class="font-body text-on-surface-variant font-medium">Bu kriterlere uygun bir berber yok. Lütfen aramanızı değiştirin.</p>
            </div>
            <?php else: ?>
            
                <?php foreach ($shops as $s): ?>
                <div class="bg-surface-container-lowest sketchy-border p-6 flex flex-col sm:flex-row gap-6 relative overflow-hidden group">
                    <div class="w-24 h-24 sm:w-32 sm:h-32 bg-[#e7edb4] rounded-xl border-2 border-black flex items-center justify-center shrink-0 overflow-hidden relative">
                        <span class="font-headline font-black text-5xl opacity-40"><?= mb_strtoupper(mb_substr($s['shop_name'], 0, 1)) ?></span>
                    </div>
                    
                    <div class="flex-grow flex flex-col justify-between">
                        <div>
                            <div class="flex justify-between items-start">
                                <h2 class="font-headline font-black text-3xl text-black"><?= htmlspecialchars($s['shop_name']) ?></h2>
                                
                                <div class="flex items-center gap-1 bg-surface-container-highest px-2 py-1 border border-black text-xs font-bold whitespace-nowrap text-[#fbbf24]">
                                    <span class="text-black"><?= $s['avg_rating'] ? number_format($s['avg_rating'], 1) : 'Yeni' ?></span>
                                    <span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1;">star</span>
                                </div>
                            </div>
                            <p class="font-body text-on-surface-variant mt-1 italic font-medium">Sahibi: <?= htmlspecialchars($s['owner_name']) ?></p>
                        </div>
                        
                        <div class="mt-6 flex flex-wrap gap-3">
                            <button class="sketchy-border-sm bg-white px-4 py-2 flex items-center gap-2 font-bold text-sm pointer-events-none">
                                <span class="material-symbols-outlined text-lg">content_cut</span>
                                <?= $s['service_count'] ?> Hizmet
                            </button>
                            <button class="sketchy-border-sm bg-white px-4 py-2 flex items-center gap-2 font-bold text-sm pointer-events-none">
                                <span class="material-symbols-outlined text-lg">groups</span>
                                <?= $s['employee_count'] + 1 ?> Personel
                            </button>
                            <?php if ($s['district_name']): ?>
                            <button class="sketchy-border-sm bg-white px-4 py-2 flex items-center gap-2 font-bold text-sm pointer-events-none">
                                <span class="material-symbols-outlined text-lg">location_on</span>
                                <?= htmlspecialchars($s['district_name']) ?>
                            </button>
                            <?php endif; ?>
                            
                            <a href="?page=berber_detay&shop_id=<?= $s['id'] ?>" class="bg-secondary text-white px-6 py-2 rounded-lg border-2 border-black font-black flex-grow sm:flex-grow-0 hover:-translate-y-1 transition-transform text-center cursor-pointer ml-auto">
                                İNCELE & RANDEVU
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
            <?php endif; ?>
        </div>
        
        <!-- Sidebar Info -->
        <div class="md:col-span-4 sticky top-28 space-y-8 hidden md:block">
            <div class="bg-surface-container-low sketchy-border p-6 rotate-1">
                <h3 class="font-headline font-black text-xl mb-4 border-b-2 border-black pb-2">BİLİYOR MUYDUNUZ?</h3>
                <?php
                $barberFacts = include __DIR__ . '/../config/facts.php';
                $dailyFact = $barberFacts[array_rand($barberFacts)];
                ?>
                <p class="font-body font-medium leading-relaxed italic text-on-surface-variant text-sm">
                    <?= $dailyFact ?>
                </p>
                <div class="flex items-center gap-2 mt-4 text-xs font-bold text-black border-t-2 border-dashed border-black/20 pt-4">
                    <span class="material-symbols-outlined text-secondary" style="font-variation-settings: 'FILL' 1;">bolt</span> Sistemde <?= count($shops) ?> dükkan aktif.
                </div>
            </div>
            
            <div class="border-4 border-black border-dashed p-8 text-center -rotate-2 bg-surface">
                <span class="material-symbols-outlined text-5xl mb-4 text-secondary" style="font-variation-settings: 'FILL' 1;">star</span>
                <h4 class="font-headline font-black text-2xl">PUAN SİSTEMİ</h4>
                <p class="text-sm font-medium mt-2 text-on-surface-variant">Çok yakında her tıraştan puan kazanıp, ücretsiz hizmet alabileceksiniz!</p>
            </div>
        </div>
    </div>
</div>

<script>
const kesfetCitySelect = document.getElementById('kesfetCitySelect');
const kesfetDistrictSelect = document.getElementById('kesfetDistrictSelect');

if (kesfetCitySelect && kesfetDistrictSelect) {
    kesfetCitySelect.addEventListener('change', async function() {
        kesfetDistrictSelect.innerHTML = '<option value="">Yükleniyor...</option>';
        kesfetDistrictSelect.disabled = true;
        if (!this.value) {
            kesfetDistrictSelect.innerHTML = '<option value="">Önce İl Seçin</option>';
            return;
        }
        try {
            const res = await fetch(`public_api.php?action=get_districts&city_id=${this.value}`);
            const data = await res.json();
            if (data.success) {
                kesfetDistrictSelect.innerHTML = '<option value="">TÜM İLÇELER</option>';
                data.data.forEach(d => {
                    const opt = document.createElement('option');
                    opt.value = d.id;
                    opt.textContent = d.name.toUpperCase();
                    kesfetDistrictSelect.appendChild(opt);
                });
                kesfetDistrictSelect.disabled = false;
            } else {
                kesfetDistrictSelect.innerHTML = '<option value="">Hata Oluştu</option>';
            }
        } catch (err) {
            kesfetDistrictSelect.innerHTML = '<option value="">Hata Oluştu</option>';
        }
    });
}
</script>
