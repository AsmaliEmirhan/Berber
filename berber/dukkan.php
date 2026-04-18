<?php
// Çalışanlar bu sayfaya erişemez
if ($userRoleInShop === 'Çalışan') {
    echo '<div class="text-center p-12 text-error font-black text-2xl">Yetkiniz Yok</div>';
    return;
}

// Dükkanı olmayan kullanıcı için her zaman yeni dükkan modunda aç
$isNew = isset($_GET['new']) || !$shop;
$displayShop = $isNew ? null : $shop;

$cities = $pdo->query('SELECT id, name FROM cities ORDER BY name')->fetchAll();

$cityId = $displayShop['city_id'] ?? 0;
$districtId = $displayShop['district_id'] ?? 0;
$districts = [];
if ($cityId) {
    $stmt = $pdo->prepare('SELECT id, name FROM districts WHERE city_id = ? ORDER BY name');
    $stmt->execute([$cityId]);
    $districts = $stmt->fetchAll();
}
?>

<div class="max-w-screen-xl mx-auto px-6 py-6">
    <div class="flex justify-between items-center mb-10">
        <header class="text-center md:text-left">
            <h1 class="text-5xl font-headline font-extrabold text-black tracking-tighter mb-2 italic">Dükkan Ayarları</h1>
            <p class="text-lg text-on-surface-variant font-medium uppercase tracking-widest">
                <?= $displayShop ? 'Dükkan bilgilerinizi güncelleyin.' : 'Yeni dükkanınızı veya şubenizi oluşturun.' ?>
            </p>
        </header>

        <?php if ($shop && !$isNew): ?>
            <a href="?page=dukkan&new=1" class="hidden md:flex bg-secondary text-white px-6 py-3 rounded-xl border-2 border-black font-bold items-center gap-2 hover:-translate-y-1 transition-transform">
                <span class="material-symbols-outlined">add_business</span> YENİ ŞUBE EKLE
            </a>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
        <div class="bg-surface-container-lowest border-4 border-black hand-drawn-border p-8 md:p-12 shadow-[8px_8px_0px_rgba(0,0,0,1)]">
            <h2 class="font-headline font-black text-3xl mb-8 flex items-center gap-3">
                <span class="material-symbols-outlined text-4xl text-secondary" style="font-variation-settings: 'FILL' 1;">storefront</span>
                <?= $displayShop ? 'Şubeyi Düzenle' : 'Yeni Şube Oluştur' ?>
            </h2>

            <form id="shopForm" class="flex flex-col gap-6">
                <input type="hidden" name="is_new" value="<?= $isNew ? '1' : '0' ?>">
                <div>
                    <label class="block font-bold uppercase text-xs tracking-widest mb-2">Dükkan Adı <span class="text-error">*</span></label>
                    <input type="text" name="shop_name" required maxlength="200" placeholder="ör. Ahmet Usta Berberi"
                           value="<?= htmlspecialchars($shop['shop_name'] ?? '') ?>"
                           class="w-full bg-surface border-2 border-black rounded-xl px-4 py-3 font-headline font-bold focus:outline-none focus:border-secondary transition-colors">
                </div>

                <div class="flex gap-4">
                    <div class="flex-1">
                        <label class="block font-bold uppercase text-xs tracking-widest mb-2">İl</label>
                        <div class="relative">
                            <select name="city_id" id="shopCitySelect" class="w-full bg-surface border-2 border-black rounded-xl px-4 py-3 font-headline font-bold focus:outline-none focus:border-secondary transition-colors appearance-none pr-10">
                                <option value="">İl seçin…</option>
                                <?php foreach ($cities as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($cityId == $c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(mb_strtoupper($c['name'])) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="material-symbols-outlined absolute right-4 top-3 text-black pointer-events-none">expand_content</span>
                        </div>
                    </div>
                    <div class="flex-1">
                        <label class="block font-bold uppercase text-xs tracking-widest mb-2">İlçe</label>
                        <div class="relative">
                            <select name="district_id" id="shopDistrictSelect" class="w-full bg-surface border-2 border-black rounded-xl px-4 py-3 font-headline font-bold focus:outline-none focus:border-secondary transition-colors appearance-none pr-10" <?= empty($districts) ? 'disabled' : '' ?>>
                                <option value=""><?= empty($districts) ? 'Önce İl Seçin' : 'İlçe seçin…' ?></option>
                                <?php foreach ($districts as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= ($districtId == $d['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(mb_strtoupper($d['name'])) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="material-symbols-outlined absolute right-4 top-3 text-black pointer-events-none">expand_content</span>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block font-bold uppercase text-xs tracking-widest mb-2">Adres</label>
                    <textarea name="address" rows="3" placeholder="Sokak, mahalle, bina no…"
                              class="w-full bg-surface border-2 border-black rounded-xl px-4 py-3 font-headline font-medium focus:outline-none focus:border-secondary transition-colors resize-none"><?= htmlspecialchars($displayShop['address'] ?? '') ?></textarea>
                </div>

                <div class="mt-4">
                    <button type="submit" class="w-full bg-secondary text-white px-8 py-4 rounded-xl border-2 border-black font-black uppercase text-lg hover:-translate-y-1 transition-transform flex items-center justify-center gap-2" id="shopSaveBtn">
                        <span class="material-symbols-outlined hidden animate-spin" id="btnSpinner">sync</span>
                        <?= $displayShop ? 'DEĞİŞİKLİKLERİ KAYDET' : 'DÜKKANI OLUŞTUR' ?>
                    </button>
                </div>
            </form>
        </div>

        <?php if ($shop): ?>
        <div class="space-y-8">
            <div class="bg-surface-container-high border-4 border-dashed border-black p-8 rotate-1">
                <h3 class="font-headline font-black text-2xl mb-6 text-black flex items-center gap-2">
                    <span class="material-symbols-outlined text-black" style="font-variation-settings: 'FILL' 1;">info</span>
                    Sistem Bilgileri
                </h3>
                
                <div class="space-y-4">
                    <div class="flex justify-between items-center border-b-2 border-black/10 pb-2">
                        <span class="font-bold text-sm uppercase tracking-widest opacity-60">Dükkan ID</span>
                        <span class="font-headline font-black">#<?= $shop['id'] ?></span>
                    </div>
                    <div class="flex justify-between items-center border-b-2 border-black/10 pb-2">
                        <span class="font-bold text-sm uppercase tracking-widest opacity-60">Kayıt Tarihi</span>
                        <span class="font-headline font-black italic"><?= date('d M Y', strtotime($shop['created_at'])) ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="font-bold text-sm uppercase tracking-widest opacity-60">Hizmet Bölgesi</span>
                        <span class="font-headline font-black text-secondary"><?= htmlspecialchars($shop['district_name'] ?? '—') ?></span>
                    </div>
                </div>
            </div>

            <div class="bg-error-container border-4 border-[#a54731] p-8 -rotate-1 rounded-xl">
                <h3 class="font-headline font-black text-2xl mb-2 text-[#742410] flex items-center gap-2">
                    <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">warning</span>
                    Dükkan Durumu
                </h3>
                <p class="text-[#742410] font-medium mb-6">Dükkanınız aktif ve randevu alabiliyor. İşletmeyi dondurmak veya silmek isterseniz müşteri hizmetleri ile iletişime geçin.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const shopCitySelect = document.getElementById('shopCitySelect');
const shopDistrictSelect = document.getElementById('shopDistrictSelect');

if (shopCitySelect && shopDistrictSelect) {
    shopCitySelect.addEventListener('change', async function() {
        shopDistrictSelect.innerHTML = '<option value="">Yükleniyor...</option>';
        shopDistrictSelect.disabled = true;
        if (!this.value) {
            shopDistrictSelect.innerHTML = '<option value="">Önce İl Seçin</option>';
            return;
        }
        try {
            const res = await fetch(`public_api.php?action=get_districts&city_id=${this.value}`);
            const data = await res.json();
            if (data.success) {
                shopDistrictSelect.innerHTML = '<option value="">İlçe Seçin...</option>';
                data.data.forEach(d => {
                    const opt = document.createElement('option');
                    opt.value = d.id;
                    opt.textContent = d.name.toUpperCase();
                    shopDistrictSelect.appendChild(opt);
                });
                shopDistrictSelect.disabled = false;
            } else {
                shopDistrictSelect.innerHTML = '<option value="">Hata Oluştu</option>';
            }
        } catch (err) {
            shopDistrictSelect.innerHTML = '<option value="">Hata Oluştu</option>';
        }
    });
}

document.getElementById('shopForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('shopSaveBtn');
    const spinner = document.getElementById('btnSpinner');
    
    spinner.classList.remove('hidden');
    const fd  = new FormData(this);
    fd.set('action', 'save_shop');
    btn.disabled = true;

    const res  = await fetch('berber/api.php', { method: 'POST', body: fd });
    const data = await res.json();
    showToast(data.success ? 'success' : 'error', data.message);
    
    spinner.classList.add('hidden');
    btn.disabled = false;

    if (data.success) setTimeout(() => location.reload(), 1200);
});
</script>
