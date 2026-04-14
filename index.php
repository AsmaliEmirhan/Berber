<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (!empty($_SESSION['role'])) {
    header('Location: ' . ($_SESSION['role'] === 'berber' ? 'berber_paneli.php' : 'musteri_paneli.php'));
    exit;
}

try {
    $cities = getPDO()->query('SELECT id, name FROM cities ORDER BY name')->fetchAll();
} catch (Exception) {
    $cities = [];
}

$view = $_GET['view'] ?? 'landing';
$role = $_GET['role'] ?? 'musteri';
?>
<!DOCTYPE html>
<html lang="tr" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berber Randevu - Dijital Defter</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&family=Work+Sans:wght@100..900&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            "colors": {
              "primary-fixed": "#e2e2e2", "surface-container-highest": "#e7edb4", "primary-container": "#e2e2e2", "error": "#a54731", "surface-container": "#f4f7ce", "on-background": "#363b12", "on-tertiary-container": "#5c5c5c", "inverse-primary": "#ffffff", "primary-fixed-dim": "#d4d4d4", "on-tertiary-fixed": "#494949", "secondary-container": "#94f990", "inverse-surface": "#0e0f03", "secondary-fixed": "#94f990", "surface-bright": "#fefee5", "secondary-dim": "#00671a", "secondary-fixed-dim": "#86eb83", "inverse-on-surface": "#9e9e88", "on-surface-variant": "#63683a", "outline": "#7f8454", "outline-variant": "#b8bd88", "tertiary": "#646464", "on-secondary-container": "#006017", "surface-container-lowest": "#ffffff", "error-container": "#fe8b70", "secondary": "#00751f", "on-error-container": "#742410", "on-secondary-fixed-variant": "#006b1b", "on-tertiary-fixed-variant": "#666666", "on-surface": "#363b12", "surface-container-low": "#fafcda", "surface-tint": "#5e5e5e", "primary": "#5e5e5e", "primary-dim": "#525252", "on-primary": "#f8f8f8", "on-secondary": "#ffffff", "on-tertiary": "#ffffff", "on-primary-fixed-variant": "#5b5b5b", "surface": "#fefee5", "on-error": "#ffffff", "on-primary-fixed": "#3f3f3f", "tertiary-container": "#f3f3f3", "on-secondary-fixed": "#004a10", "on-primary-container": "#525252", "surface-container-high": "#eef2c1", "error-dim": "#5c1202", "surface-variant": "#e7edb4", "tertiary-fixed": "#f3f3f3", "background": "#fefee5", "tertiary-fixed-dim": "#e5e5e5", "tertiary-dim": "#585858", "surface-dim": "#e1e8a7"
            },
            "fontFamily": {
              "headline": ["Plus Jakarta Sans"], "body": ["Work Sans"], "label": ["Work Sans"]
            }
          }
        }
      }
    </script>
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; display: inline-block; vertical-align: middle; }
        .sketch-border { border: 2px solid #000000; box-shadow: 3px 3px 0px 0px #000000; }
        .sketch-border-heavy { border: 3px solid #000000; box-shadow: 6px 6px 0px 0px #000000; }
        .paper-grain, .ink-grain {
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
            opacity: 0.03; pointer-events: none;
        }
        .sketch-input { border: none; border-bottom: 2px solid #000000; background: transparent; transition: all 0.2s ease; }
        .sketch-input:focus { outline: none; border-bottom-width: 4px; padding-bottom: 2px; }
        .sketch-input-ye-ol { width: 100%; background: transparent; border-top: 0; border-left: 0; border-right: 0; border-bottom: 2px solid #000000; outline: none; }
        .sketch-input-ye-ol:focus { border-color: #00751f; border-width: 0 0 2px 0; box-shadow: none; }
        /* Auth specific styles for JS form */
        #alertBox { display: none; padding: 10px; margin-bottom: 16px; border-radius: 4px; font-weight: 500; text-align: center; }
        #alertBox.show { display: block; }
        #alertBox.error { background-color: #fe8b70; color: #742410; border: 2px solid #a54731; }
        #alertBox.success { background-color: #94f990; color: #006017; border: 2px solid #00751f; }
    </style>
</head>
<body class="bg-surface font-body text-on-surface selection:bg-secondary-container min-h-screen flex flex-col relative overflow-x-hidden">
<div class="fixed inset-0 paper-grain z-50"></div>

<?php if ($view === 'landing'): ?>
<!-- ================== LANDING VIEW ================== -->
<header class="bg-[#fefee5] border-b-2 border-black w-full px-6 py-4 z-40 relative">
    <div class="flex justify-between items-center w-full max-w-screen-2xl mx-auto font-['Plus_Jakarta_Sans'] tracking-tight">
        <div class="flex items-center gap-4">
            <button id="mobileMenuBtn" class="md:hidden p-2 border-2 border-black rounded-lg hover:bg-black hover:text-white transition-colors">
                <span class="material-symbols-outlined">menu</span>
            </button>
            <a href="?view=landing" class="block flex-shrink-0">
                <img src="assets/img/logo.png" alt="Berber Randevu Logo" class="h-16 md:h-20 w-auto object-contain">
            </a>
        </div>
        <nav class="hidden md:flex gap-8 items-center">
            <a class="text-stone-600 font-medium hover:text-black hover:-translate-y-0.5 transition-transform" href="#">Berberler</a>
            <a class="text-stone-600 font-medium hover:text-black hover:-translate-y-0.5 transition-transform" href="#">Hizmetler</a>
        </nav>
        <div class="flex items-center gap-4">
            <a href="?view=login" class="bg-secondary text-white px-6 py-2 rounded-xl sketch-border font-bold hover:-translate-y-0.5 active:scale-95 transition-all outline-none">Giriş Yap</a>
        </div>
    </div>
</header>

<!-- Mobile Navigation Menu -->
<div id="mobileMenuOverlay" class="fixed inset-0 bg-black/50 z-[100] hidden opacity-0 transition-opacity duration-300"></div>
<aside id="mobileMenu" class="fixed top-0 left-0 bottom-0 w-[280px] bg-surface z-[101] border-r-4 border-black -translate-x-full transition-transform duration-300 ease-in-out p-6 flex flex-col">
    <div class="flex justify-between items-center mb-10">
        <img src="assets/img/logo.png" alt="Logo" class="h-12 w-auto">
        <button id="closeMenuBtn" class="p-2 border-2 border-black rounded-lg">
            <span class="material-symbols-outlined">close</span>
        </button>
    </div>
    <nav class="flex flex-col gap-6 font-headline">
        <a href="#" class="text-2xl font-black italic border-b-2 border-black pb-2">Berberler</a>
        <a href="#" class="text-2xl font-black italic border-b-2 border-black pb-2">Hizmetler</a>
        <a href="?view=login" class="text-2xl font-black italic text-secondary border-b-2 border-secondary pb-2">Giriş Yap</a>
    </nav>
    <div class="mt-auto pt-10 border-t-2 border-black/10">
        <p class="text-xs font-label uppercase tracking-widest text-stone-500">Berber Randevu - Dijital Defter</p>
    </div>
</aside>
<main class="relative flex-grow flex flex-col items-center justify-center py-20 px-4">
    <!-- Background Sketch Ornaments -->
    <div class="absolute top-20 -left-12 opacity-10 pointer-events-none rotate-[-15deg]"><span class="material-symbols-outlined text-[240px]" data-icon="content_cut">content_cut</span></div>
    <div class="absolute bottom-40 -right-16 opacity-10 pointer-events-none rotate-[12deg]"><span class="material-symbols-outlined text-[280px]" data-icon="history_edu">history_edu</span></div>

    <section class="max-w-screen-xl mx-auto flex flex-col items-center text-center relative z-10 w-full">
        <h1 class="font-headline text-5xl md:text-7xl font-extrabold tracking-tighter text-black mb-8 leading-tight italic">
            Dijital <span class="underline decoration-secondary decoration-4 underline-offset-8">Berber Defteri</span>
        </h1>
        <p class="text-xl text-on-surface-variant max-w-2xl mb-16 font-medium">Modern berberler ve müşteriler için dijital randevu platformu.</p>
        
        <div class="grid md:grid-cols-2 gap-8 w-full max-w-4xl">
            <!-- Customer -->
            <div class="group relative bg-surface-container-lowest p-10 rounded-xl sketch-border transition-all shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] hover:-translate-y-2 hover:rotate-1">
                <div class="absolute -top-10 -right-6 group-hover:scale-110 transition-transform"><span class="material-symbols-outlined text-7xl text-secondary">person_search</span></div>
                <h2 class="font-headline text-3xl font-black mb-4">Müşteri</h2>
                <p class="text-stone-600 mb-8 font-medium">Size en yakın berberi bulun, portfolyosunu inceleyin ve anında randevu alın.</p>
                <a href="?view=register&role=musteri" class="block w-full bg-black text-white text-center py-4 rounded-lg text-lg font-bold uppercase tracking-widest hover:bg-secondary transition-colors outline-none cursor-pointer">Randevu Al</a>
            </div>
            <!-- Barber -->
            <div class="group relative bg-surface-container-lowest p-10 rounded-xl sketch-border transition-all shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] hover:-translate-y-2 hover:-rotate-1">
                <div class="absolute -top-10 -left-6 group-hover:scale-110 transition-transform"><span class="material-symbols-outlined text-7xl text-black">storefront</span></div>
                <h2 class="font-headline text-3xl font-black mb-4">Berber</h2>
                <p class="text-stone-600 mb-8 font-medium">Randevularınızı yönetin, portfolyonuzu sergileyin ve müşteri sadakatini artırın.</p>
                <a href="?view=register&role=berber" class="block w-full border-4 border-black text-center py-4 rounded-lg text-lg font-bold uppercase tracking-widest hover:bg-black hover:text-white transition-all outline-none cursor-pointer">Dükkanını Aç</a>
            </div>
        </div>
    </section>
</main>

<?php elseif ($view === 'login'): ?>
<!-- ================== LOGIN VIEW ================== -->
<header class="w-full flex p-6 absolute top-0 left-0 z-50">
    <a href="?view=landing" class="text-black font-bold uppercase tracking-widest text-sm flex items-center gap-2 hover:text-secondary transition-colors"><span class="material-symbols-outlined">arrow_back</span> ANA SAYFA</a>
</header>
<main class="flex-grow flex flex-col items-center justify-center px-6 relative z-10 w-full h-full">
    <div class="absolute top-20 left-10 md:left-24 opacity-20 -rotate-12 select-none pointer-events-none"><span class="material-symbols-outlined text-[120px]" data-icon="content_cut">content_cut</span></div>
    
    <header class="mb-12 text-center mt-12 md:mt-0 flex flex-col items-center">
        <a href="?view=landing" class="inline-block mb-4">
            <img src="assets/img/logo.png" alt="Berber Randevu Logo" class="h-24 md:h-32 w-auto object-contain">
        </a>
        <p class="font-label text-sm uppercase tracking-widest text-primary">Dijital Defter Girişi</p>
    </header>
    
    <div class="w-full max-w-md bg-surface-container-lowest p-8 md:p-12 sketch-border rounded-xl -rotate-1 transform">
        <div id="alertBox">
            <span class="alert-msg"></span>
        </div>
        
        <form id="authForm" class="space-y-10" novalidate onsubmit="return false;">
            <input type="hidden" name="action" value="login">
            
            <div class="flex bg-surface-container rounded-lg p-1 border-2 border-black mb-6">
                <button type="button" class="role-btn active flex-1 py-2 rounded font-bold text-sm uppercase text-black" data-role="musteri">Müşteri</button>
                <button type="button" class="role-btn flex-1 py-2 rounded font-bold text-sm uppercase text-black opacity-60 hover:opacity-100" data-role="berber">Berber</button>
            </div>
            <input type="hidden" name="role" id="roleInput" value="musteri">

            <div class="relative">
                <label class="block font-label text-xs uppercase tracking-widest font-black mb-2 px-1">E-Posta</label>
                <div class="flex items-center">
                    <span class="material-symbols-outlined absolute left-0 text-primary">alternate_email</span>
                    <input name="email" class="w-full pl-8 py-2 sketch-input font-headline text-lg placeholder:text-outline-variant/40" placeholder="ornek@mail.com" type="email" required/>
                </div>
            </div>
            
            <div class="relative">
                <label class="block font-label text-xs uppercase tracking-widest font-black mb-2 px-1">Parola</label>
                <div class="flex items-center">
                    <span class="material-symbols-outlined absolute left-0 text-primary">lock_open</span>
                    <input name="password" class="w-full pl-8 py-2 sketch-input font-headline text-lg placeholder:text-outline-variant/40" placeholder="••••••••" type="password" required/>
                </div>
            </div>
            
            <div class="pt-4">
                <button id="submitBtn" class="w-full bg-secondary text-white font-headline font-bold text-xl py-4 sketch-border rounded-xl flex items-center justify-center gap-3 hover:-translate-y-1 hover:rotate-1 active:translate-y-0 transition-all cursor-pointer" type="submit">
                    <span>Giriş Yap</span>
                    <span class="material-symbols-outlined">login</span>
                </button>
            </div>
        </form>
        
        <div class="mt-12 flex flex-col items-center gap-4 border-t-2 border-black/5 pt-8">
            <a class="font-label text-sm uppercase tracking-widest text-primary hover:text-black hover:italic transition-all" href="#">Şifremi Unuttum</a>
            <div class="flex items-center gap-2">
                <span class="text-xs font-label text-outline uppercase tracking-widest">Hesabın yok mu?</span>
                <a class="font-headline font-black text-black border-b-2 border-secondary pb-0.5 hover:bg-secondary-container transition-colors" href="?view=register" id="switchToReg">ÜYE OL</a>
            </div>
        </div>
    </div>
</main>

<?php elseif ($view === 'register'): ?>
<!-- ================== REGISTER VIEW ================== -->
<header class="w-full flex justify-between p-6 absolute top-0 left-0 z-50">
    <a href="?view=landing" class="text-black font-bold uppercase tracking-widest text-sm flex items-center gap-2 hover:text-secondary transition-colors"><span class="material-symbols-outlined">arrow_back</span> Vazgeç</a>
</header>
<main class="flex-grow flex items-center justify-center px-4 py-20 relative overflow-hidden w-full">
    <div class="max-w-4xl w-full grid md:grid-cols-2 bg-surface-container-lowest sketch-border-heavy relative overflow-hidden">
        <!-- Identity -->
        <div class="bg-surface-container-high p-12 flex flex-col justify-center border-b-2 md:border-b-0 md:border-r-2 border-black relative">
            <div class="mb-8 rotate-[-2deg]">
                <h1 class="font-headline text-5xl font-black text-black leading-tight tracking-tighter">Deftere<br/><span id="regTitleWord"><?= $role === 'berber' ? 'Dükkan Aç' : 'Kaydolun.' ?></span></h1>
                <p class="mt-4 text-on-surface-variant font-medium text-lg"><?= $role === 'berber' ? 'İşletmeni yönet, randevularını kolayca takip et.' : 'En iyi berberler, en tarz tıraşlar ve hızlı randevu için yerinizi ayırtın.' ?></p>
            </div>
            <!-- Hand drawn circle decoration -->
            <div class="absolute top-8 right-8 w-16 h-16 border-2 border-black rounded-full opacity-20 border-dashed animate-spin-slow"></div>
        </div>
        
        <!-- Registration Form -->
        <div class="p-10 md:p-12 bg-surface-container-lowest">
            <div class="mb-10">
                <h2 class="text-2xl font-bold font-headline text-black flex items-center gap-2">Yeni Üyelik <span class="material-symbols-outlined text-secondary" style="font-variation-settings: 'FILL' 1;">edit_square</span></h2>
            </div>
            
            <div id="alertBox"><span class="alert-msg"></span></div>
            
            <form id="authForm" class="space-y-6" novalidate onsubmit="return false;">
                <input type="hidden" name="action" value="register">
                <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($role) ?>">
                
                <div class="flex bg-surface-container rounded-lg p-1 border-2 border-black mb-6">
                    <button type="button" class="role-btn <?= $role === 'musteri' ? 'active' : 'opacity-60' ?> flex-1 py-1.5 rounded font-bold text-sm uppercase text-black hover:opacity-100" data-role="musteri">Müşteri</button>
                    <button type="button" class="role-btn <?= $role === 'berber' ? 'active bg-black text-white' : 'opacity-60' ?> flex-1 py-1.5 rounded font-bold text-sm uppercase hover:opacity-100" data-role="berber">Berber</button>
                </div>
            
                <div class="flex gap-4">
                    <div class="space-y-1 flex-1">
                        <label class="font-label text-xs font-bold uppercase tracking-widest text-on-surface-variant">Ad</label>
                        <input name="first_name" class="sketch-input-ye-ol py-2 text-lg" placeholder="Ahmet" type="text" required/>
                    </div>
                    <div class="space-y-1 flex-1">
                        <label class="font-label text-xs font-bold uppercase tracking-widest text-on-surface-variant">Soyad</label>
                        <input name="last_name" class="sketch-input-ye-ol py-2 text-lg" placeholder="Yılmaz" type="text" required/>
                    </div>
                </div>
                
                <div class="space-y-1">
                    <label class="font-label text-xs font-bold uppercase tracking-widest text-on-surface-variant">E-posta</label>
                    <input name="email" class="sketch-input-ye-ol py-2 text-lg" placeholder="ahmet@email.com" type="email" required/>
                </div>
                
                <div class="space-y-1">
                    <label class="font-label text-xs font-bold uppercase tracking-widest text-on-surface-variant">Parola</label>
                    <input name="password" class="sketch-input-ye-ol py-2 text-lg" placeholder="••••••••" type="password" required/>
                </div>
                
                <div class="flex gap-4" id="locationWrapper" style="<?= $role === 'berber' ? 'display:none;' : '' ?>">
                    <div class="space-y-1 flex-1">
                        <label class="font-label text-xs font-bold uppercase tracking-widest text-on-surface-variant">İl</label>
                        <select name="city_id" id="citySelect" class="sketch-input-ye-ol py-2 text-lg appearance-none bg-transparent w-full">
                            <option value="">Seçiniz (Opsiyonel)</option>
                            <?php foreach ($cities as $c): ?>
                                <option value="<?= htmlspecialchars($c['id']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="space-y-1 flex-1" id="districtWrapper">
                        <label class="font-label text-xs font-bold uppercase tracking-widest text-on-surface-variant">İlçe</label>
                        <select name="district_id" id="districtSelect" class="sketch-input-ye-ol py-2 text-lg appearance-none bg-transparent w-full" disabled>
                            <option value="">Önce İl Seçin</option>
                        </select>
                    </div>
                </div>
                
                <div class="pt-4">
                    <button id="submitBtn" class="w-full bg-secondary text-white py-4 sketch-border hover:translate-x-1 hover:translate-y-1 hover:shadow-none transition-all duration-75 font-bold text-lg flex items-center justify-center gap-3 cursor-pointer" type="submit">
                        Üye Ol <span class="material-symbols-outlined">arrow_forward</span>
                    </button>
                </div>
                <div class="text-center pt-4">
                    <p class="text-sm text-on-surface-variant">
                        Zaten üye misiniz? <a class="text-black font-bold underline decoration-secondary decoration-2 hover:text-secondary transition-colors" href="?view=login">Giriş Yap</a>
                    </p>
                </div>
            </form>
            <div class="absolute bottom-0 right-0 w-8 h-8 bg-surface-container-high border-t-2 border-l-2 border-black"></div>
        </div>
    </div>
</main>
<?php endif; ?>

<footer class="bg-surface-container-low border-t-4 border-black w-full mt-auto relative z-20">
    <div class="flex flex-col md:flex-row justify-between items-center w-full px-8 py-8 gap-6 max-w-screen-2xl mx-auto font-label text-sm uppercase tracking-widest">
        <div class="font-black text-black">Berber Defteri</div>
        <div class="flex flex-wrap justify-center gap-6">
            <a class="text-stone-500 hover:text-black italic transition-colors" href="#">Hakkımızda</a>
            <a class="text-stone-500 hover:text-black italic transition-colors" href="#">İletişim</a>
        </div>
        <div class="text-stone-500 normal-case opacity-70 tracking-normal">© 2024 Berber Randevu - Dijital Defter</div>
    </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile Menu Logic
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const closeMenuBtn = document.getElementById('closeMenuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');

    function toggleMenu() {
        if (!mobileMenu) return;
        mobileMenu.classList.toggle('-translate-x-full');
        mobileMenuOverlay.classList.toggle('hidden');
        setTimeout(() => mobileMenuOverlay.classList.toggle('opacity-0'), 10);
        document.body.style.overflow = mobileMenu.classList.contains('-translate-x-full') ? '' : 'hidden';
    }

    if(mobileMenuBtn) mobileMenuBtn.onclick = toggleMenu;
    if(closeMenuBtn) closeMenuBtn.onclick = toggleMenu;
    if(mobileMenuOverlay) mobileMenuOverlay.onclick = toggleMenu;

    const roleBtns = document.querySelectorAll('.role-btn');
    const roleInput = document.getElementById('roleInput');
    const locationWrapper = document.getElementById('locationWrapper');
    const citySelect = document.getElementById('citySelect');
    const districtSelect = document.getElementById('districtSelect');
    const regTitleWord = document.getElementById('regTitleWord');
    const switchToReg = document.getElementById('switchToReg');
    
    // Switch Role logic
    roleBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const role = this.dataset.role;
            if (roleInput) roleInput.value = role;
            
            roleBtns.forEach(b => {
                b.classList.remove('active', 'bg-black', 'text-white');
                b.classList.add('opacity-60', 'text-black');
            });
            this.classList.add('active');
            this.classList.remove('opacity-60');
            
            // Register ui changes
            if(locationWrapper) {
                this.classList.add('bg-black', 'text-white');
                this.classList.remove('text-black');
                if (role === 'berber') {
                    locationWrapper.style.display = 'none';
                    if(regTitleWord) regTitleWord.textContent = 'Dükkan Aç';
                } else {
                    locationWrapper.style.display = 'flex';
                    if(regTitleWord) regTitleWord.textContent = 'Kaydolun.';
                }
            } else {
                // Login ui changes
                this.classList.add('bg-white', 'sketch-border');
                if (switchToReg) switchToReg.href = '?view=register&role=' + role;
            }
        });
    });

    if (citySelect && districtSelect) {
        citySelect.addEventListener('change', async function() {
            districtSelect.innerHTML = '<option value="">Yükleniyor...</option>';
            districtSelect.disabled = true;
            if (!this.value) {
                districtSelect.innerHTML = '<option value="">Önce İl Seçin</option>';
                return;
            }
            try {
                const res = await fetch(`public_api.php?action=get_districts&city_id=${this.value}`);
                const data = await res.json();
                if (data.success) {
                    districtSelect.innerHTML = '<option value="">İlçe Seçiniz</option>';
                    data.data.forEach(d => {
                        districtSelect.innerHTML += `<option value="${d.id}">${d.name}</option>`;
                    });
                    districtSelect.disabled = false;
                } else {
                    districtSelect.innerHTML = '<option value="">Hata Oluştu</option>';
                }
            } catch (err) {
                districtSelect.innerHTML = '<option value="">Hata Oluştu</option>';
            }
        });
    }

    const form = document.getElementById('authForm');
    const alertBox = document.getElementById('alertBox');
    const alertMsg = document.querySelector('.alert-msg');
    const submitBtn = document.getElementById('submitBtn');

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            alertBox.classList.remove('show', 'error', 'success');
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.7';
            
            const fd = new FormData(form);
            
            try {
                const res = await fetch('auth_handler.php', { method: 'POST', body: fd });
                const data = await res.json();

                alertBox.classList.add('show', data.success ? 'success' : 'error');
                alertMsg.textContent = data.message;
                
                if (data.success) {
                    setTimeout(() => window.location.href = data.redirect, 1000);
                } else {
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                }
            } catch (err) {
                alertBox.classList.add('show', 'error');
                alertMsg.textContent = 'Sunucuya ulaşılamadı.';
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
            }
        });
    }
});
</script>
</body>
</html>
