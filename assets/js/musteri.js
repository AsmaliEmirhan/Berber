/* ============================================================
   Müşteri Paneli — Randevu Booking Controller
   ============================================================ */
(function () {
    'use strict';

    /* ---- Booking State ---- */
    const B = {
        shopId:       null,
        serviceId:    null,
        serviceName:  null,
        servicePrice: null,
        serviceDur:   null,
        employeeId:   null,
        employeeName: null,
        date:         null,
        time:         null,
    };

    let currentStep = 1;

    /* ============================================================
       openBooking — Randevu modalını aç
    ============================================================ */
    window.openBooking = function (shopId, serviceId, serviceName, servicePrice, serviceDur) {
        B.shopId       = shopId;
        B.serviceId    = serviceId;
        B.serviceName  = serviceName;
        B.servicePrice = servicePrice;
        B.serviceDur   = serviceDur;
        B.employeeId   = null;
        B.employeeName = null;
        B.date         = null;
        B.time         = null;
        currentStep    = 1;

        const tpl = document.getElementById('bookingTemplate').content.cloneNode(true);
        openModal('Randevu Al', tpl);

        // Seçilen hizmet kutusu
        document.getElementById('selectedServiceBox').innerHTML = `
            <div>
                <div class="svc-lbl">Seçilen Hizmet</div>
                <div class="svc-name">✂️ ${serviceName}</div>
            </div>
            <div class="svc-price">₺${parseFloat(servicePrice).toFixed(2)}</div>
        `;

        // Personelleri yükle
        populateEmployees();
    };

    /* ---- Personel dropdown ---- */
    function populateEmployees() {
        const sel = document.getElementById('employeeSelect');
        if (!sel) return;

        const employees = window._shopEmployees || [];

        if (employees.length === 0) {
            sel.innerHTML = '<option value="">Bu dükkanda personel bulunamadı.</option>';
            return;
        }

        sel.innerHTML = '<option value="">Personel seçin…</option>' +
            employees.map(e => `<option value="${e.id}" data-name="${e.full_name}">${e.full_name}</option>`).join('');
    }

    /* ============================================================
       bookingNextStep — Adımlar arası geçiş
    ============================================================ */
    window.bookingNextStep = function (targetStep) {

        // Validation
        if (targetStep === 2) {
            const sel = document.getElementById('employeeSelect');
            if (!sel || !sel.value) {
                showToast('error', 'Lütfen bir personel seçin.');
                return;
            }
            B.employeeId   = parseInt(sel.value);
            B.employeeName = sel.options[sel.selectedIndex].dataset.name;
        }

        if (targetStep === 3) {
            const dateInput = document.getElementById('dateInput');
            if (!dateInput || !dateInput.value) {
                showToast('error', 'Lütfen bir tarih seçin.');
                return;
            }
            B.date = dateInput.value;
            B.time = null;
            loadSlots();

            // Disable next until slot chosen
            const nxtBtn = document.getElementById('slotNextBtn');
            if (nxtBtn) nxtBtn.disabled = true;
        }

        if (targetStep === 4) {
            if (!B.time) {
                showToast('error', 'Lütfen bir saat seçin.');
                return;
            }
            renderSummary();
        }

        // Panel geçişi
        document.getElementById('stepPanel' + currentStep)?.classList.remove('active');
        document.getElementById('stepPanel' + targetStep)?.classList.add('active');

        // Step dot güncelle
        updateStepDots(targetStep);
        currentStep = targetStep;
    };

    function updateStepDots(active) {
        document.querySelectorAll('.step-dot').forEach(dot => {
            const n = parseInt(dot.dataset.step);
            dot.classList.remove('active', 'done');
            if (n < active)  dot.classList.add('done');
            if (n === active) dot.classList.add('active');
        });
        document.querySelectorAll('.step-line').forEach((line, i) => {
            line.classList.toggle('done', i + 1 < active);
        });
    }

    /* ============================================================
       loadSlots — AJAX ile uygun saatleri getir
    ============================================================ */
    async function loadSlots() {
        const container = document.getElementById('slotsContainer');
        if (!container) return;

        container.innerHTML = '<p class="text-muted center">Saatler yükleniyor…</p>';

        const fd = new FormData();
        fd.set('action',      'get_slots');
        fd.set('shop_id',     B.shopId);
        fd.set('employee_id', B.employeeId);
        fd.set('service_id',  B.serviceId);
        fd.set('date',        B.date);

        try {
            const res  = await fetch('musteri/api.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (!data.success) {
                container.innerHTML = `<p class="text-muted center">⚠️ ${data.message}</p>`;
                return;
            }

            renderSlots(data.slots);
        } catch {
            container.innerHTML = '<p class="text-muted center">Sunucuya ulaşılamadı.</p>';
        }
    }

    function renderSlots(slots) {
        const container = document.getElementById('slotsContainer');
        if (!container) return;

        const available = slots.filter(s => s.available);
        if (available.length === 0) {
            container.innerHTML = '<p class="text-muted center" style="padding:20px">Bu tarihte müsait saat bulunmuyor.<br>Lütfen başka bir tarih deneyin.</p>';
            return;
        }

        const grid = document.createElement('div');
        grid.className = 'slots-grid';

        slots.forEach(slot => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = slot.time;
            btn.className = 'slot-btn ' + (slot.available ? 'available' : 'unavailable');
            btn.disabled = !slot.available;

            if (slot.available) {
                btn.addEventListener('click', () => selectSlot(slot.time, btn));
            }

            grid.appendChild(btn);
        });

        container.innerHTML = '';
        container.appendChild(grid);
    }

    function selectSlot(time, btn) {
        // Önceki seçimi kaldır
        document.querySelectorAll('.slot-btn.selected').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        B.time = time;

        const nxtBtn = document.getElementById('slotNextBtn');
        if (nxtBtn) nxtBtn.disabled = false;
    }

    /* ============================================================
       renderSummary — Step 4 özet kartı
    ============================================================ */
    function renderSummary() {
        const el = document.getElementById('bookingSummary');
        if (!el) return;

        const dateFormatted = new Date(B.date + 'T' + B.time + ':00').toLocaleDateString('tr-TR', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        });

        el.innerHTML = `
            <div class="summary-row">
                <span class="summary-key">✂️ Hizmet</span>
                <span class="summary-val">${B.serviceName}</span>
            </div>
            <div class="summary-row">
                <span class="summary-key">👤 Personel</span>
                <span class="summary-val">${B.employeeName}</span>
            </div>
            <div class="summary-row">
                <span class="summary-key">📅 Tarih</span>
                <span class="summary-val">${dateFormatted}</span>
            </div>
            <div class="summary-row">
                <span class="summary-key">🕐 Saat</span>
                <span class="summary-val">${B.time}</span>
            </div>
            <div class="summary-row">
                <span class="summary-key">⏱ Süre</span>
                <span class="summary-val">${B.serviceDur} dakika</span>
            </div>
            <div class="summary-row">
                <span class="summary-key">💰 Ücret</span>
                <span class="summary-price">₺${parseFloat(B.servicePrice).toFixed(2)}</span>
            </div>
        `;
    }

    /* ============================================================
       confirmBooking — Randevuyu kaydet
    ============================================================ */
    window.confirmBooking = async function () {
        const btn     = document.getElementById('confirmBtn');
        const spinner = document.getElementById('confirmSpinner');

        if (!btn || !B.time || !B.date) return;

        btn.disabled = true;
        spinner?.classList.remove('hidden');

        const datetime = B.date + ' ' + B.time + ':00';

        const fd = new FormData();
        fd.set('action',           'book_appointment');
        fd.set('shop_id',          B.shopId);
        fd.set('employee_id',      B.employeeId);
        fd.set('service_id',       B.serviceId);
        fd.set('appointment_time', datetime);

        try {
            const res  = await fetch('musteri/api.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                closeModal();
                showToast('success', data.message);
                // Randevularım'a git
                setTimeout(() => {
                    window.location.href = 'musteri_paneli.php?page=randevularim';
                }, 1500);
            } else {
                showToast('error', data.message);
                btn.disabled = false;
                spinner?.classList.add('hidden');
            }
        } catch {
            showToast('error', 'Sunucuya ulaşılamadı.');
            btn.disabled = false;
            spinner?.classList.add('hidden');
        }
    };

})();
