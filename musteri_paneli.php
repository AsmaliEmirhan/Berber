<?php
session_start();

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'musteri') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/config/db.php';
$pdo = getPDO();

$stmt = $pdo->prepare('SELECT u.*, d.name AS district_name FROM users u LEFT JOIN districts d ON u.district_id = d.id WHERE u.id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$allowedPages = ['kesfet', 'berber_detay', 'randevularim', 'top10'];
$page = in_array($_GET['page'] ?? '', $allowedPages) ? $_GET['page'] : 'kesfet';

$activeNav = ($page === 'berber_detay') ? 'kesfet' : $page;
?>
<!DOCTYPE html>
<html lang="tr" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Müşteri Paneli — Berber Randevu</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&family=Work+Sans:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet"/>
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
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; vertical-align: middle; display:inline-block; }
        .sketchy-border { border: 2px solid #000; box-shadow: 4px 4px 0px #000; }
        .sketchy-border-sm { border: 2px solid #000; box-shadow: 2px 2px 0px #000; }
        .sketchy-card { border: 2px solid #000000; transform: rotate(-0.5deg); }
        .sketchy-card:nth-child(even) { transform: rotate(0.5deg); }
        body { background-color: #fefee5; font-family: 'Work Sans', sans-serif; }
        .ink-texture { background-image: radial-gradient(#363b12 0.5px, transparent 0.5px); background-size: 24px 24px; opacity: 0.03; pointer-events: none; }
        .asymmetric-tilt-left { transform: rotate(-1deg); }
        .asymmetric-tilt-right { transform: rotate(1deg); }
        /* Toast Styles */
        .toast { position: fixed; bottom: 20px; right: 20px; padding: 15px 25px; border-radius: 8px; background: white; border: 2px solid #000; font-weight: bold; transform:translateY(150%); transition:transform 0.3s; z-index:9999; box-shadow: 4px 4px 0px #000; display:flex; align-items:center; gap:10px; }
        .toast.show { transform:translateY(0); }
        .toast.success { background-color: #94f990; color: #006017; border-color: #00751f; }
        .toast.error { background-color: #fe8b70; color: #742410; border-color: #a54731; }
        
        /* Modal Base */
        .modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(2px); z-index:999; display:flex; align-items:center; justify-content:center; opacity:0; pointer-events:none; transition:opacity 0.2s; }
        .modal-backdrop.open { opacity:1; pointer-events:auto; }
        .modal-box { background:#fefee5; border:3px solid #000; box-shadow:6px 6px 0px #000; border-radius:12px; width:90%; max-width:500px; max-height:90vh; overflow-y:auto; transform:rotate(-1deg); }
    </style>
</head>
<body class="bg-surface font-body text-on-background min-h-screen flex flex-col relative overflow-x-hidden">
<div class="fixed inset-0 ink-texture z-0"></div>

<header class="bg-[#fefee5] w-full border-b-2 border-black sticky top-0 z-50">
    <nav class="flex justify-between items-center w-full px-6 py-4 max-w-screen-2xl mx-auto font-['Plus_Jakarta_Sans'] tracking-tight">
        <div class="flex items-center gap-8">
            <a href="index.php" class="block flex-shrink-0 hover:opacity-80 transition-opacity cursor-pointer">
                <img src="assets/img/logo.png" alt="Berber Randevu Logo" class="h-12 md:h-16 w-auto object-contain">
            </a>
            <div class="hidden md:flex gap-6 items-center">
                <a href="?page=kesfet" class="text-black font-black pb-1 hover:-translate-y-0.5 hover:rotate-1 transition-transform border-b-4 <?= $activeNav==='kesfet' ? 'border-black' : 'border-transparent text-stone-600' ?>">Berberler / Keşfet</a>
                <a href="?page=randevularim" class="text-black font-black pb-1 hover:-translate-y-0.5 hover:rotate-1 transition-transform border-b-4 <?= $activeNav==='randevularim' ? 'border-black' : 'border-transparent text-stone-600' ?>">Randevularım</a>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <div class="text-sm font-bold sketch-border px-3 py-1 bg-surface-container-lowest hidden sm:inline-block">
                Hoş Geldin, <span class="text-secondary"><?= htmlspecialchars(explode(' ', $user['full_name'])[0]) ?></span>
            </div>
            <a href="logout.php" class="bg-black text-white px-4 py-2 rounded-xl border-2 border-black font-bold hover:-translate-y-0.5 active:scale-95 transition-all text-sm">
                Çıkış Yap
            </a>
        </div>
    </nav>
</header>

<main class="flex-grow w-full relative z-10">
    <?php include __DIR__ . "/musteri/{$page}.php"; ?>
</main>

<footer class="bg-[#fafcda] w-full border-t-4 border-black mt-20 relative z-20">
    <div class="flex flex-col md:flex-row justify-between items-center w-full px-8 py-10 gap-6 max-w-screen-2xl mx-auto font-['Work_Sans'] text-sm uppercase tracking-widest">
        <div class="font-black text-black text-lg italic tracking-tighter">Berber Defteri</div>
        <div class="flex flex-wrap justify-center gap-8 font-medium">
            <a class="text-stone-500 hover:text-black hover:underline transition-colors block" href="#">Hakkımızda</a>
            <a class="text-stone-500 hover:text-black hover:underline transition-colors block" href="#">S.S.S.</a>
        </div>
        <div class="text-stone-500 text-xs normal-case tracking-normal">
            © 2024 Berber Defteri. El çizimi ile özenle hazırlanmıştır.
        </div>
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

<script src="assets/js/musteri.js?v=2"></script>
<script>
    // Minimal global js hooks if needed over from panel.js
    function showToast(type, msg) {
        const t = document.getElementById('toast');
        t.className = 'toast show ' + type;
        document.getElementById('toastIcon').textContent = type==='success' ? 'check_circle' : 'error';
        document.getElementById('toastMsg').textContent = msg;
        setTimeout(()=> t.classList.remove('show'), 3000);
    }
    
    // Modal
    const modalBackdrop = document.getElementById('modalBackdrop');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody  = document.getElementById('modalBody');

    window.openModal = function(title, content) {
        if (!modalBackdrop) return;
        modalTitle.textContent = title;
        modalBody.innerHTML = '';
        if (typeof content === 'string') modalBody.innerHTML = content;
        else modalBody.appendChild(content);
        modalBackdrop.classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    window.closeModal = function() {
        if (!modalBackdrop) return;
        modalBackdrop.classList.remove('open');
        document.body.style.overflow = '';
        setTimeout(() => { modalBody.innerHTML = ''; }, 300);
    };

    if (modalBackdrop) {
        document.getElementById('modalClose').onclick = closeModal;
        modalBackdrop.onclick = (e) => { if (e.target === modalBackdrop) closeModal(); };
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modalBackdrop.classList.contains('open')) closeModal();
        });
    }
</script>
</body>
</html>
