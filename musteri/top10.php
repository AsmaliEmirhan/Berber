<?php
$cities = $pdo->query('SELECT id, name FROM cities ORDER BY name')->fetchAll();

$filterCity = isset($_GET['city']) ? (int)$_GET['city'] : 0;
$filterDistrict = isset($_GET['district']) ? (int)$_GET['district'] : 0;

$districts = [];
if ($filterCity) {
    $stmt = $pdo->prepare('SELECT id, name FROM districts WHERE city_id = ? ORDER BY name');
    $stmt->execute([$filterCity]);
    $districts = $stmt->fetchAll();
}


$top10Sql = "
    SELECT s.*, 
           u.full_name as owner_name, 
           d.name as district_name,
           AVG(r.rating) as avg_rating,
           COUNT(DISTINCT r.id) as review_count
    FROM shops s
    JOIN users u ON s.owner_id = u.id
    LEFT JOIN districts d ON s.district_id = d.id
    LEFT JOIN reviews r ON r.shop_id = s.id
    WHERE 1=1 ";

$params = [];
if ($filterCity) {
    $top10Sql .= " AND s.city_id = ? ";
    $params[] = $filterCity;
}
if ($filterDistrict) {
    $top10Sql .= " AND s.district_id = ? ";
    $params[] = $filterDistrict;
}


$top10Sql .= " GROUP BY s.id HAVING review_count > 0 ORDER BY avg_rating DESC, review_count DESC, s.created_at DESC LIMIT 50 "; 

$stmt = $pdo->prepare($top10Sql);
$stmt->execute($params);
$topShops = $stmt->fetchAll();

$title = "Türkiye Geneli Top 10";
if ($filterCity) {
    $cityName = '';
    foreach($cities as $c) {
        if((int)$c['id'] === $filterCity) {
            $cityName = mb_strtoupper($c['name']);
            break;
        }
    }
    
    $title = $cityName . " İLİ EN İYİ 10";
    
    if ($filterDistrict) {
        $districtName = '';
        foreach($districts as $d) {
            if((int)$d['id'] === $filterDistrict) {
                $districtName = mb_strtoupper($d['name']);
                break;
            }
        }
        if ($districtName) {
            $title = $districtName . " İLÇESİ EN İYİ 10 (" . $cityName . ")";
        }
    }
}
?>
<div class="max-w-screen-xl mx-auto px-6 py-12 relative">
    <div class="mb-12 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
        <a href="?page=kesfet" class="inline-flex items-center gap-2 font-bold uppercase text-sm border-b-2 border-black pb-0.5 hover:text-error hover:border-error transition-colors">
            <span class="material-symbols-outlined text-lg">arrow_back</span> Keşfet'e Dön
        </a>
        
        <!-- TOP 10 Filtreleme Formu -->
        <form method="GET" action="musteri_paneli.php" class="flex flex-wrap gap-4 items-center bg-surface-container-highest p-4 rounded-xl border-2 border-black w-full md:w-auto">
            <input type="hidden" name="page" value="top10">
            
            <select name="city" id="top10CitySelect" onchange="if(this.form.district) { this.form.district.value=''; } this.form.submit();" class="bg-white border-2 border-black rounded-lg px-4 py-2 font-headline font-bold focus:outline-none focus:border-secondary transition-colors appearance-none pr-8">
                <option value="">TÜRKİYE GENELİ</option>
                <?php foreach ($cities as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $filterCity == $c['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars(mb_strtoupper($c['name'])) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <?php if ($filterCity): ?>
            <select name="district" onchange="this.form.submit()" class="bg-white border-2 border-black rounded-lg px-4 py-2 font-headline font-bold focus:outline-none focus:border-secondary transition-colors appearance-none pr-8">
                <option value="">TÜM İLÇELER</option>
                <?php foreach ($districts as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $filterDistrict == $d['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars(mb_strtoupper($d['name'])) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
        </form>
    </div>

    <header class="mb-20 text-center relative">
        <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg h-32 bg-[#fbbf24] blur-[100px] opacity-20 pointer-events-none"></div>
        <h1 class="font-headline font-black text-4xl md:text-6xl text-black uppercase tracking-tight relative inline-flex items-center justify-center gap-4 text-center z-10 w-full mb-4">
            <span class="material-symbols-outlined text-5xl md:text-7xl text-[#fbbf24] drop-shadow-[2px_2px_0_#000]" style="font-variation-settings: 'FILL' 1;">workspace_premium</span>
            <span class="drop-shadow-[2px_2px_0_rgba(0,0,0,0.1)]"><?= $title ?></span>
        </h1>
        <p class="font-body text-xl font-medium mt-2 text-on-surface-variant max-w-2xl mx-auto">
            Tamamen gerçek müşteri deneyimleri ve bırakılan yıldızlama puanlarına göre hesaplanmış en prestijli sıralama ekranı.
        </p>
    </header>

    <?php if (empty($topShops)): ?>
    <div class="bg-surface-container-lowest border-4 border-black p-12 text-center rotate-1 mb-8 shadow-[8px_8px_0px_#000]">
        <span class="material-symbols-outlined text-6xl mb-4 opacity-40">sentiment_dissatisfied</span>
        <h2 class="font-headline font-black text-3xl mb-2">Sıralama Bulunamadı</h2>
        <p class="font-medium text-lg text-stone-600">Bu arama kriterine uygun, henüz yeterince puan almış dükkan bulunmuyor.</p>
    </div>
    <?php else: ?>
    <div class="flex flex-col gap-6 max-w-4xl mx-auto">
        <?php foreach ($topShops as $idx => $ts): 
            $rank = $idx + 1;
            
            $bgClass = "bg-white";
            $badgeColor = "bg-stone-800 text-white";
            
            if ($rank === 1) {
                // Gold
                $bgClass = "bg-gradient-to-r from-yellow-50 to-yellow-200 border-yellow-600";
                $badgeColor = "bg-yellow-400 text-black";
            } elseif ($rank === 2) {
                // Silver
                $bgClass = "bg-gradient-to-r from-slate-50 to-slate-200 border-slate-500";
                $badgeColor = "bg-slate-300 text-black";
            } elseif ($rank === 3) {
                // Bronze
                $bgClass = "bg-gradient-to-r from-orange-50 to-orange-200 border-orange-700";
                $badgeColor = "bg-orange-600 text-white";
            }
        ?>
        <a href="?page=berber_detay&shop_id=<?= $ts['id'] ?>" class="flex items-center gap-4 md:gap-8 p-4 md:p-6 <?= $bgClass ?> border-4 border-black rounded-2xl hover:-translate-y-1 transition-all shadow-[6px_6px_0_#000] hover:shadow-[10px_10px_0_#000] relative overflow-hidden group">
            
            <?php if ($rank <= 3): ?>
            <div class="absolute -right-8 -top-8 opacity-20 rotate-12 pointer-events-none transition-transform group-hover:rotate-[24deg] group-hover:scale-110">
                <span class="material-symbols-outlined text-[150px]" style="font-variation-settings: 'FILL' 1;">workspace_premium</span>
            </div>
            <?php endif; ?>

            <!-- Ranking Medal -->
            <div class="<?= $badgeColor ?> w-14 h-14 md:w-20 md:h-20 shrink-0 rounded-full border-4 border-black flex items-center justify-center font-headline font-black text-2xl md:text-3xl shadow-inner z-10 italic">
                #<?= $rank ?>
            </div>

            <div class="flex-grow z-10">
                <h3 class="font-headline font-black text-2xl md:text-4xl uppercase mb-1 drop-shadow-[1px_1px_0_rgba(0,0,0,0.1)]"><?= htmlspecialchars($ts['shop_name']) ?></h3>
                <div class="flex flex-wrap items-center gap-3 text-xs md:text-sm font-bold uppercase tracking-widest text-[#525252] mb-1">
                    <?php if ($ts['district_name']): ?>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">location_on</span> <?= htmlspecialchars($ts['district_name']) ?></span>
                    <?php endif; ?>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">person</span> <?= htmlspecialchars($ts['owner_name']) ?></span>
                </div>
            </div>

            <div class="hidden md:flex flex-col justify-center items-end shrink-0 z-10 bg-black text-[#fbbf24] px-6 py-4 rounded-xl border-2 border-black transform rotate-2 group-hover:rotate-0 transition-transform">
                <div class="flex items-center gap-2">
                    <span class="font-black text-4xl leading-none drop-shadow-[1px_1px_0_#fff]"><?= number_format($ts['avg_rating'], 1) ?></span>
                    <span class="material-symbols-outlined text-4xl" style="font-variation-settings: 'FILL' 1;">star</span>
                </div>
                <div class="text-stone-300 text-[10px] font-medium tracking-widest uppercase mt-2 text-right w-full border-t border-stone-700 pt-1"><?= $ts['review_count'] ?> değerlendirme</div>
            </div>
            
            <!-- Mobile Rating -->
            <div class="md:hidden flex flex-col items-center bg-black text-[#fbbf24] px-3 py-2 rounded-lg border-2 border-black z-10">
                <div class="flex items-center gap-1">
                    <span class="font-black text-xl"><?= number_format($ts['avg_rating'], 1) ?></span>
                    <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
                </div>
                <div class="text-white text-[8px] uppercase tracking-widest opacity-80 mt-1"><?= $ts['review_count'] ?> oy</div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
