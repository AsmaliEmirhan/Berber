<?php
session_start();

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'berber') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

$pdo = getPDO();

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$stmt = $pdo->prepare('SELECT s.*, d.name as district_name FROM shops s LEFT JOIN districts d ON s.district_id = d.id WHERE s.owner_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$ownedShops = $stmt->fetchAll();

$stmt = $pdo->prepare('
    SELECT s.*, d.name as district_name 
    FROM shop_employees se 
    JOIN shops s ON se.shop_id = s.id 
    LEFT JOIN districts d ON s.district_id = d.id 
    WHERE se.employee_id = ?
');
$stmt->execute([$_SESSION['user_id']]);
$employedShops = $stmt->fetchAll();

$allShops = [];
foreach ($ownedShops as $s) {
    $s['_role'] = 'Patron';
    $allShops[$s['id']] = $s;
}
foreach ($employedShops as $s) {
    if (!isset($allShops[$s['id']])) {
        $s['_role'] = 'Ã‡alÄ±ÅŸan';
        $allShops[$s['id']] = $s;
    }
}

$shop = null;
$userRoleInShop = null;
if (!empty($allShops)) {
    $activeShopId = $_SESSION['active_shop_id'] ?? array_key_first($allShops);
    if (!isset($allShops[$activeShopId])) {
        $activeShopId = array_key_first($allShops);
    }
    $_SESSION['active_shop_id'] = $activeShopId;
    $shop = $allShops[$activeShopId] ?? null;
    $userRoleInShop = $shop ? $shop['_role'] : null;
}

$allowedPages = ['dashboard', 'dukkan', 'hizmetler', 'calisanlar', 'randevular', 'analiz', 'istatistik', 'yorumlar', 'plus'];
$page = in_array($_GET['page'] ?? '', $allowedPages) ? $_GET['page'] : 'dashboard';

// Enforce Employee Restrictions
if ($userRoleInShop === 'Ã‡alÄ±ÅŸan') {
    $restricted = ['istatistik', 'dukkan', 'calisanlar', 'yorumlar'];
    if (in_array($page, $restricted)) {
        $page = 'dashboard';
    }
}

$pendingBadge = 0;
if ($shop) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE shop_id = ? AND status = 'bekliyor'");
    $stmt->execute([$shop['id']]);
    $pendingBadge = (int)$stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="tr" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berber Paneli â€” Berber Randevu</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&family=Work+Sans:wght@100..900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            "colors": {
              "primary-fixed": "#e2e2e2", "surface-container-highest": "#e7edb4", "primary-container": "#e2e2e2", "error": "#a54731", "surface-container": "#f4f7ce", "on-background": "#363b12", "on-tertiary-container": "#5c5c5c", "inverse-primary": "#ffffff", "primary-fixed-dim": "#d4d4d4", "on-tertiary-fixed": "#494949", "secondary-container": "#94f990", "inverse-surface": "#0e0f03", "secondary-fixed": "#94f990", "surface-bright": "#fefee5", "secondary-dim": "#00671a", "secondary-fixed-dim": "#86eb83", "inverse-on-surface": "#9e9e88", "on-surface-variant": "#63683a", "outline": "#7f8454", "outline-variant": "#b8bd88", "tertiary": "#646464", "on-secondary-container": "#006017", "surface-container-lowest": "#ffffff", "error-container": "#fe8b70", "secondary": "#00751f", "on-error-container": "#742410", "on-secondary-fixed-variant": "#006b1b", "on-tertiary-fixed-variant": "#666666", "on-surface": "#363b12", "surface-container-low": "#fafcda", "surface-tint": "#5e5e5e", "primary": "#5e5e5e", "primary-dim": "#525252", "on-primary": "#f8f8f8", "on-secondary": "#ffffff", "on-tertiary": "#ffffff", "on-primary-fixed-variant": "#5b5b5b", "surface": "#fefee5", "on-error": "#ffffff", "on-primary-fixed": "#3f3f3f", "tertiary-container": "#f3f3f3", "on-secondary-fixed": "#004a10", "on-primary-container": "#525252", "surface-container-high": "#eef2c1", "error-dim": "#5c1202", "surface-variant": "#e7edb4", "tertiary-fixed": "#f3f3f3", "background": "#fefee5"
            },
            "fontFamily": {
              "headline": ["Plus Jakarta Sans"], "body": ["Work Sans"], "label": ["Work Sans"]
            }
          }
        }
      }
    </script>
    <style>
        .hand-drawn-border { border: 2px solid #000; border-radius: 255px 15px 225px 15px/15px 225px 15px 255px; }
        .sketch-shadow { box-shadow: 6px 6px 0px 0px rgba(0,0,0,1); }
        .rotated-sketch { transform: rotate(-1deg); }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; vertical-align:middle; display:inline-block; }
        body { background-color: #fefee5; font-family: 'Work Sans', sans-serif; overflow-x:hidden; }
        .ink-texture { background-image: radial-gradient(#363b12 0.5px, transparent 0.5px); background-size: 24px 24px; opacity: 0.03; pointer-events:none; }
        
        /* Toast & Modal */
        .toast { position: fixed; bottom: 20px; right: 20px; padding: 15px 25px; border-radius: 8px; background: white; border: 2px solid #000; font-weight: bold; transform:translateY(150%); transition:transform 0.3s; z-index:9999; box-shadow: 4px 4px 0px #000; display:flex; align-items:center; justify-content:center; gap:10px; }
        .toast.show { transform:translateY(0); }
        .toast.success { background-color: #94f990; color: #006017; border-color: #00751f; }
        .toast.error { background-color: #fe8b70; color: #742410; border-color: #a54731; }
        
        .modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(2px); z-index:999; display:flex; align-items:center; justify-content:center; opacity:0; pointer-events:none; transition:opacity 0.2s; }
        .modal-backdrop.open { opacity:1; pointer-events:auto; }
        .modal-box { background:#fefee5; border:3px solid #000; box-shadow:6px 6px 0px #000; border-radius:12px; width:90%; max-width:500px; max-height:90vh; overflow-y:auto; transform:rotate(-1deg); }

        /* Mobile Menu */
        #mobileMenu { position: fixed; top: 0; left: 0; bottom: 0; width: 280px; background: #fefee5; border-right: 4px solid #000; z-index: 1000; transform: translateX(-100%); transition: transform 0.3s ease-in-out; }
        #mobileMenu.open { transform: translateX(0); }
        #mobileMenuOverlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 999; display: none; }
        #mobileMenuOverlay.show { display: block; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="bg-surface text-on-background font-body selection:bg-secondary-container min-h-screen flex flex-col relative">
<div class="fixed inset-0 ink-texture z-0"><<!-- TopNavBar -->
<nav class="bg-[#fefee5] w-full border-b-2 border-black sticky top-0 z-50">
    <div class="flex justify-between items-center w-full px-6 py-4 max-w-screen-2xl mx-auto font-['Plus_Jakarta_Sans'] tracking-tight">
        <div class="flex items-center gap-4">
            <button id="hamburgerBtn" class="lg:hidden p-2 border-2 border-black rounded-lg hover:bg-black hover:text-white transition-colors">
                <span class="material-symbols-outlined">menu</span>
            </button>
            <a href="index.php" class="block flex-shrink-0 hover:opacity-80 transition-opacity cursor-pointer">
                <img src="assets/img/logo.png" alt="Berber Randevu Logo" class="h-10 md:h-16 w-auto object-contain">
            </a>
        </div>
        
        <!-- Desktop Navbar Links -->
        <div class="hidden lg:flex items-center space-x-6">
            <a href="?page=dashboard" class="relative text-black font-black pb-1 hover:-translate-y-0.5 transition-transform border-b-4 <?= $page==='dashboard'?'border-black':'border-transparent hover:border-black/50 text-stone-600' ?>">Panel</a>
            
            <?php if ($userRoleInShop !== 'Ã‡alÄ±ÅŸan'): ?>
                <a href="?page=dukkan" class="relative text-black font-black pb-1 hover:-translate-y-0.5 transition-transform border-b-4 <?= $page==='dukkan'?'border-black':'border-transparent hover:border-black/50 text-stone-600' ?>">DÃ¼kkan</a>
            <?php endif; ?>

            <a href="?page=hizmetler" class="relative text-black font-black pb-1 hover:-translate-y-0.5 transition-transform border-b-4 <?= $page==='hizmetler'?'border-black':'border-transparent hover:border-black/50 text-stone-600' ?>">Hizmetler</a>
            
            <?php if ($userRoleInShop !== 'Ã‡alÄ±ÅŸan'): ?>
                <?php if ($user['is_plus']): ?>
                <a href="?page=calisanlar" class="relative text-black font-black pb-1 hover:-translate-y-0.5 transition-transform border-b-4 <?= $page==='calisanlar'?'border-black':'border-transparent hover:border-black/50 text-stone-600' ?>">Ã‡alÄ±ÅŸanlar</a>
                <?php else: ?>
                <div title="Sadece Plus Ãœyelerine Ã–zel" class="relative text-stone-400 font-black pb-1 cursor-not-allowed">Ã‡alÄ±ÅŸanlar â­</div>
                <?php endif; ?>
            <?php endif; ?>
            
            <a href="?page=randevular" class="relative text-black font-black pb-1 hover:-translate-y-0.5 transition-transform border-b-4 <?= $page==='randevular'?'border-black':'border-transparent hover:border-black/50 text-stone-600' ?>">
                Randevular
                <?php if($pendingBadge > 0): ?>
                    <span class="absolute -top-3 -right-4 bg-secondary text-white text-[10px] px-1.5 py-0.5 rounded-full z-10"><?= $pendingBadge ?></span>
                <?php endif; ?>
            </a>

            <?php if ($userRoleInShop !== 'Ã‡alÄ±ÅŸan'): ?>
                <a href="?page=yorumlar" class="relative text-black font-black pb-1 hover:-translate-y-0.5 transition-transform border-b-4 <?= $page==='yorumlar'?'border-black':'border-transparent hover:border-black/50 text-stone-600' ?>">Yorumlar</a>

                <?php if ($user['is_plus']): ?>
                <a href="?page=istatistik" class="relative text-black font-black pb-1 hover:-translate-y-0.5 transition-transform border-b-4 <?= $page==='istatistik'?'border-black':'border-transparent hover:border-black/50 text-stone-600' ?>">Ä°statistikler</a>
                <?php else: ?>
                <div title="Sadece Plus Ãœyelerine Ã–zel" class="relative text-stone-400 font-black pb-1 cursor-not-allowed">Ä°statistikler â­</div>
                <?php endif; ?>

                <?php if ($user['is_plus']): ?>
                <a href="?page=analiz" class="relative text-black font-black pb-1 hover:-translate-y-0.5 transition-transform border-b-4 <?= $page==='analiz'?'border-black':'border-transparent hover:border-black/50 text-stone-600' ?>">YÃ¼z YÃ¼ze</a>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($userRoleInShop !== 'Ã‡alÄ±ÅŸan'): ?>
            <a href="?page=plus" class="relative font-black pb-1 hover:-translate-y-0.5 transition-transform border-b-4 <?= $page==='plus' ? 'border-secondary text-secondary' : ($user['is_plus'] ? 'border-transparent text-secondary hover:border-secondary/50' : 'border-transparent text-stone-600 hover:border-black/50') ?>">
                <?= $user['is_plus'] ? 'â­ Plus' : 'â­ Plus\'a GeÃ§' ?>
            </a>
            <?php endif; ?>
        </div>
        
        <div class="flex items-center gap-4">
            <?php if (!empty($allShops)): ?>
                <div class="hidden sm:flex flex-col items-end mr-4 border-r-2 border-black/10 pr-4">
                    <select class="bg-transparent font-black text-sm uppercase cursor-pointer outline-none hover:text-secondary appearance-none" onchange="switchActiveShop(this.value)">
                        <?php foreach($allShops as $s): ?>
                            <option class="text-black bg-[#fefee5]" value="<?= $s['id'] ?>" <?= $shop && $shop['id'] == $s['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(mb_strtoupper($s['shop_name'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="text-[10px] font-bold tracking-widest <?= $userRoleInShop === 'Patron' ? 'text-secondary' : 'text-stone-500' ?>">
                        Ãœnvan: <?= $userRoleInShop ?>
                    </span>
                </div>
            <?php endif; ?>
            <a href="logout.php" class="bg-black text-white px-4 md:px-6 py-2 hand-drawn-border font-bold hover:-translate-y-0.5 active:scale-95 transition-all text-sm uppercase">Ã‡Ä±kÄ±ÅŸ</a>
        </div>
    </div>
</nav>

<!-- Mobile Dropdown Menu (Drawer) -->
<div id="mobileMenuOverlay" class="fixed inset-0 bg-black/50 z-[998] hidden opacity-0 transition-opacity duration-300"></div>
<div id="mobileMenu" class="fixed top-0 left-0 h-full w-[80%] max-w-[300px] bg-[#fefee5] border-r-4 border-black z-[999] -translate-x-full transition-transform duration-300 flex flex-col">
    <div class="p-6 border-b-2 border-black flex justify-between items-center">
        <span class="font-headline font-black text-xl italic">NAVIGASYON</span>
        <button id="closeMenuBtn" class="text-2xl font-bold">&times;</button>
    </div>
    <div class="flex-grow overflow-y-auto p-4 flex flex-col gap-2">
        <?php if (!empty($allShops)): ?>
            <div class="sm:hidden mb-4 p-4 border-2 border-black bg-surface-container-low rounded-xl">
                <p class="text-[10px] font-bold uppercase tracking-widest mb-2 opacity-50">DÃ¼kkan DeÄŸiÅŸtir</p>
                <select class="w-full bg-transparent font-black text-sm uppercase outline-none" onchange="switchActiveShop(this.value)">
                    <?php foreach($allShops as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $shop && $shop['id'] == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars(mb_strtoupper($s['shop_name'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <a href="?page=dashboard" class="p-4 font-black flex items-center gap-3 border-2 border-transparent hover:border-black hover:bg-surface-container transition-all <?= $page==='dashboard'?'bg-black text-white':'' ?>">
            <span class="material-symbols-outlined">dashboard</span> Panel
        </a>
        <?php if ($userRoleInShop !== 'Ã‡alÄ±ÅŸan'): ?>
            <a href="?page=dukkan" class="p-4 font-black flex items-center gap-3 border-2 border-transparent hover:border-black hover:bg-surface-container transition-all <?= $page==='dukkan'?'bg-black text-white':'' ?>">
                <span class="material-symbols-outlined">storefront</span> DÃ¼kkan
            </a>
        <?php endif; ?>
        <a href="?page=hizmetler" class="p-4 font-black flex items-center gap-3 border-2 border-transparent hover:border-black hover:bg-surface-container transition-all <?= $page==='hizmetler'?'bg-black text-white':'' ?>">
            <span class="material-symbols-outlined">content_cut</span> Hizmetler
        </a>
        <a href="?page=randevular" class="p-4 font-black flex items-center gap-3 border-2 border-transparent hover:border-black hover:bg-surface-container transition-all <?= $page==='randevular'?'bg-black text-white':'' ?>">
            <span class="material-symbols-outlined">calendar_month</span> Randevular
        </a>
        <?php if ($userRoleInShop !== 'Ã‡alÄ±ÅŸan'): ?>
            <a href="?page=yorumlar" class="p-4 font-black flex items-center gap-3 border-2 border-transparent hover:border-black hover:bg-surface-container transition-all <?= $page==='yorumlar'?'bg-black text-white':'' ?>">
                <span class="material-symbols-outlined">reviews</span> Yorumlar
            </a>
            <?php if ($user['is_plus']): ?>
                <a href="?page=calisanlar" class="p-4 font-black flex items-center gap-3 border-2 border-transparent hover:border-black hover:bg-surface-container transition-all <?= $page==='calisanlar'?'bg-black text-white':'' ?>">
                    <span class="material-symbols-outlined">badge</span> Ã‡alÄ±ÅŸanlar
                </a>
                <a href="?page=istatistik" class="p-4 font-black flex items-center gap-3 border-2 border-transparent hover:border-black hover:bg-surface-container transition-all <?= $page==='istatistik'?'bg-black text-white':'' ?>">
                    <span class="material-symbols-outlined">analytics</span> Ä°statistikler
                </a>
                <a href="?page=analiz" class="p-4 font-black flex items-center gap-3 border-2 border-transparent hover:border-black hover:bg-surface-container transition-all <?= $page==='analiz'?'bg-black text-white':'' ?>">
                    <span class="material-symbols-outlined">monitoring</span> YÃ¼z YÃ¼ze
                </a>
            <?php endif; ?>
            <a href="?page=plus" class="p-4 font-black flex items-center gap-3 border-2 border-transparent hover:border-black hover:bg-surface-container transition-all <?= $page==='plus'?'bg-secondary text-white':'text-secondary' ?>">
                <span class="material-symbols-outlined">star</span> <?= $user['is_plus'] ? 'Plus Ãœye' : 'Plus\'a GeÃ§' ?>
            </a>
        <?php endif; ?>
    </div>
    <div class="p-6 border-t-2 border-black">
        <a href="logout.php" class="block w-full text-center bg-black text-white py-3 font-bold uppercase sketch-border">Çıkış Yap</a>
    </div>
</div>

<main class="flex-grow w-full relative z-10 py-6">
    <?php include __DIR__ . "/berber/{$page}.php"; ?>
</main>

<footer class="bg-[#fafcda] w-full border-t-4 border-black mt-12 relative z-20">
    <div class="flex flex-col md:flex-row justify-between items-center w-full px-8 py-10 gap-6 font-['Work_Sans'] text-sm uppercase tracking-widest max-w-screen-2xl mx-auto">
        <div class="font-black text-black text-lg">Berber Randevu</div>
        <div class="flex flex-wrap justify-center gap-6">
            <a class="text-stone-500 hover:text-black hover:italic transition-colors" href="#">HakkÄ±mÄ±zda</a>
            <a class="text-stone-500 hover:text-black hover:italic transition-colors" href="#">DesteÄŸe UlaÅŸÄ±n</a>
        </div>
        <div class="text-black">Â© 2024 Berber YÃ¶netim Paneli</div>
    </div>
</footer>

<!-- Toast -->
<div class="toast" id="toast">
    <span class="material-symbols-outlined" id="toastIcon">info</span>
    <span id="toastMsg"></span>
</div>

<!-- Modal Base -->
<div class="modal-backdrop" id="modalBackdrop">
    <div class="modal-box" id="modal">
        <div class="p-4 border-b-2 border-black flex justify-between items-center bg-surface-container-highest">
            <h3 class="font-headline font-black text-xl italic" id="modalTitle"></h3>
            <button id="modalClose" class="text-black font-bold hover:text-secondary text-2xl leading-none">&times;</button>
        </div>
        <div class="p-6" id="modalBody"></div>
    </div>
</div>

<script src="assets/js/panel.js"></script>
<script>
async function switchActiveShop(shopId) {
    const fd = new FormData();
    fd.set('action', 'switch_shop');
    fd.set('shop_id', shopId);
    await fetch('berber/api.php', { method: 'POST', body: fd });
    location.reload();
}

// Mobile Menu Logic
const hamburgerBtn = document.getElementById('hamburgerBtn');
const closeMenuBtn = document.getElementById('closeMenuBtn');
const mobileMenu = document.getElementById('mobileMenu');
const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');

function openMenu() {
    mobileMenu.classList.remove('-translate-x-full');
    mobileMenuOverlay.classList.remove('hidden');
    setTimeout(() => {
        mobileMenuOverlay.classList.add('opacity-100');
    }, 10);
    document.body.style.overflow = 'hidden';
}

function closeMenu() {
    mobileMenu.classList.add('-translate-x-full');
    mobileMenuOverlay.classList.remove('opacity-100');
    setTimeout(() => {
        mobileMenuOverlay.classList.add('hidden');
    }, 300);
    document.body.style.overflow = '';
}

if(hamburgerBtn) hamburgerBtn.onclick = openMenu;
if(closeMenuBtn) closeMenuBtn.onclick = closeMenu;
if(mobileMenuOverlay) mobileMenuOverlay.onclick = closeMenu;
</script>
</body>
</html>
