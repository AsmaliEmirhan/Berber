/* ============================================================
   Auth Page — Vanilla JS Controller
   ============================================================ */

(function () {
    'use strict';

    /* ---- State ---- */
    let activeRole = 'musteri';   // 'musteri' | 'berber'
    let activeMode = 'login';     // 'login'   | 'register'

    /* ---- DOM refs ---- */
    const slider       = document.getElementById('tabsSlider');
    const tabMusteri   = document.getElementById('tabMusteri');
    const tabBerber    = document.getElementById('tabBerber');
    const modeLogin    = document.getElementById('modeLogin');
    const modeRegister = document.getElementById('modeRegister');
    const formTitle    = document.getElementById('formTitle');
    const formSubtitle = document.getElementById('formSubtitle');
    const alertBox     = document.getElementById('alertBox');
    const authForm     = document.getElementById('authForm');
    const submitBtn    = document.getElementById('submitBtn');
    const btnText      = document.getElementById('btnText');

    /* Conditional fields */
    const rowName      = document.getElementById('rowName');
    const fieldDistrict = document.getElementById('fieldDistrict');

    /* ---- Tab switching ---- */
    function setRole(role) {
        activeRole = role;
        slider.classList.toggle('berber', role === 'berber');
        tabMusteri.classList.toggle('active', role === 'musteri');
        tabBerber.classList.toggle('active',  role === 'berber');
        updateFormCopy();
        hideAlert();
    }

    tabMusteri.addEventListener('click', () => setRole('musteri'));
    tabBerber.addEventListener('click',  () => setRole('berber'));

    /* ---- Mode switching ---- */
    function setMode(mode) {
        activeMode = mode;
        modeLogin.classList.toggle('active',    mode === 'login');
        modeRegister.classList.toggle('active', mode === 'register');

        const isRegister = mode === 'register';
        rowName.classList.toggle('hidden', !isRegister);
        fieldDistrict.classList.toggle('hidden', !isRegister || activeRole === 'berber');

        // Toggle required attrs
        document.getElementById('firstName').required = isRegister;
        document.getElementById('lastName').required  = isRegister;

        updateFormCopy();
        hideAlert();
    }

    modeLogin.addEventListener('click',    () => setMode('login'));
    modeRegister.addEventListener('click', () => setMode('register'));

    function updateFormCopy() {
        const isRegister = activeMode === 'register';
        const role = activeRole === 'musteri' ? 'Müşteri' : 'Berber';

        if (isRegister) {
            formTitle.textContent    = `${role} Hesabı Oluştur`;
            formSubtitle.textContent = 'Birkaç saniyede kayıt ol, randevularını yönet.';
            btnText.textContent      = 'Kayıt Ol';
        } else {
            formTitle.textContent    = `${role} Girişi`;
            formSubtitle.textContent = 'Hesabına giriş yap ve devam et.';
            btnText.textContent      = 'Giriş Yap';
        }

        // District field: only visible for musteri register
        if (isRegister && activeRole === 'musteri') {
            fieldDistrict.classList.remove('hidden');
        } else {
            fieldDistrict.classList.add('hidden');
        }
    }

    /* ---- Password toggle ---- */
    document.querySelectorAll('.toggle-pass').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = btn.previousElementSibling;
            const isPass = input.type === 'password';
            input.type = isPass ? 'text' : 'password';
            btn.innerHTML = isPass
                ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
                : '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
        });
    });

    /* ---- Alert helpers ---- */
    function showAlert(type, message) {
        alertBox.className = `alert show ${type}`;
        alertBox.querySelector('.alert-msg').textContent = message;
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    function hideAlert() {
        alertBox.classList.remove('show', 'error', 'success');
    }

    /* ---- Loading state ---- */
    function setLoading(on) {
        submitBtn.disabled = on;
        submitBtn.classList.toggle('loading', on);
    }

    /* ---- Form submit ---- */
    authForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideAlert();
        setLoading(true);

        const fd = new FormData(authForm);
        fd.set('action', activeMode);
        fd.set('role',   activeRole);

        try {
            const res  = await fetch('auth_handler.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                showAlert('success', data.message);
                setTimeout(() => { window.location.href = data.redirect; }, 900);
            } else {
                showAlert('error', data.message);
                setLoading(false);
            }
        } catch {
            showAlert('error', 'Sunucuya ulaşılamadı. Lütfen tekrar deneyin.');
            setLoading(false);
        }
    });

    /* ---- Init ---- */
    setRole('musteri');
    setMode('login');
})();
