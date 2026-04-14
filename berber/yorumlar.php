<?php
// Müşteri Yorumları (Patron Panel)
if ($userRoleInShop !== 'Patron') {
    echo "<div class='p-8'><h2 class='text-2xl font-bold font-headline'>Bu sayfayı görüntüleme yetkiniz yok.</h2></div>";
    return;
}

$stmt = $pdo->prepare("
    SELECT r.*, u.full_name as customer_name, s.shop_name 
    FROM reviews r
    JOIN users u ON r.customer_id = u.id
    JOIN shops s ON r.shop_id = s.id
    WHERE s.owner_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$user['id']]);
$reviews = $stmt->fetchAll();

$isPlus = (int)$user['is_plus'];
?>
<div class="max-w-screen-xl mx-auto py-8">
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-4xl font-headline font-black italic flex items-center gap-2">
            <span class="material-symbols-outlined text-secondary text-5xl" style="font-variation-settings: 'FILL' 1;">reviews</span>
            Müşteri Yorumları
        </h1>
        <?php if (!$isPlus): ?>
        <a href="?page=plus_al" class="bg-gradient-to-r from-yellow-400 to-yellow-600 text-white px-6 py-2 rounded-full font-bold uppercase shadow-lg shadow-yellow-500/30 hover:scale-105 transition-transform flex items-center gap-1"><span class="material-symbols-outlined text-sm">stars</span> Plus'a Geç</a>
        <?php endif; ?>
    </div>

    <?php if (empty($reviews)): ?>
    <div class="bg-surface-container-lowest sketchy-border p-12 text-center text-on-surface-variant">
        <span class="material-symbols-outlined text-6xl mb-4 opacity-50">speaker_notes_off</span>
        <h2 class="font-headline font-black text-2xl mb-2">Henüz yorum yok</h2>
        <p class="font-medium">Müşterileriniz işlemlerden sonra size puan verdiklerinde burada listelenecektir.</p>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($reviews as $rev): ?>
        <div class="bg-white border-4 border-black p-6 rounded-2xl hover:-translate-y-1 transition-transform shadow-[6px_6px_0px_0px_rgba(0,0,0,1)] relative overflow-hidden flex flex-col">
            <div class="flex items-center justify-between border-b-2 border-black/10 pb-4 mb-4">
                <div>
                    <h3 class="font-black text-lg"><?= htmlspecialchars($rev['customer_name']) ?></h3>
                    <p class="text-xs font-bold text-stone-500"><?= date('d M Y, H:i', strtotime($rev['created_at'])) ?></p>
                </div>
                <div class="flex text-[#fbbf24] drop-shadow-[1px_1px_0px_#000]">
                    <?php for($i=1; $i<=5; $i++): ?>
                        <span class="material-symbols-outlined text-lg" <?= $i <= $rev['rating'] ? 'style="font-variation-settings:\'FILL\' 1"' : '' ?>>star</span>
                    <?php endfor; ?>
                </div>
            </div>
            
            <div class="flex-grow relative">
                <?php if ($isPlus): ?>
                    <?php if (trim($rev['comment'])): ?>
                        <p class="font-medium text-stone-700 italic">"<?= nl2br(htmlspecialchars($rev['comment'])) ?>"</p>
                    <?php else: ?>
                        <p class="font-medium text-stone-400 italic text-sm">Bu değerlendirmede yorum bulunmuyor.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="font-medium text-stone-700 italic blur-sm select-none">"Bu metni okuyabilmek için Plus pakete geçmeniz gerekmektedir."</p>
                    <div class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-white/40">
                        <span class="material-symbols-outlined text-4xl text-black drop-shadow-[1px_1px_0_#fff]">lock</span>
                        <a href="?page=plus_al" class="bg-black text-white text-[10px] font-bold px-2 py-1 mt-1 uppercase tracking-widest text-center shadow-lg hover:scale-105 pointer-events-auto">Plus Paket Gerektirir</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
