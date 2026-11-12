/* AfriTopup website — small, dependency-free helpers + the quick-pay widget. */
(function () {
    'use strict';

    const $ = (s, c) => (c || document).querySelector(s);
    const $$ = (s, c) => Array.from((c || document).querySelectorAll(s));
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---------- toast ---------- */
    let toastTimer;
    window.siteToast = function (msg) {
        const t = $('#siteToast');
        if (!t) return;
        t.textContent = msg;
        t.classList.add('is-on');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => t.classList.remove('is-on'), 2600);
    };

    /* ---------- header: solid on scroll, hide when scrolling down ---------- */
    const header = $('#siteHeader');
    const toTop = $('#toTop');
    let lastY = window.scrollY;
    function onScroll() {
        const y = window.scrollY;
        if (header) {
            header.classList.toggle('is-scrolled', y > 20);
            header.classList.toggle('is-hidden', y > 500 && y > lastY + 4 && !header.matches(':focus-within'));
            if (y < lastY - 4) header.classList.remove('is-hidden');
        }
        if (toTop) toTop.classList.toggle('is-on', y > 700);
        lastY = y;
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
    if (toTop) toTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' }));

    /* ---------- reveal on scroll + counters ---------- */
    function countUp(el) {
        const end = parseFloat(el.dataset.count || '0');
        if (reduce || !end) { el.textContent = el.dataset.count; return; }
        const dur = 1400, t0 = performance.now();
        const fmt = new Intl.NumberFormat();
        (function tick(t) {
            const p = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - p, 3);
            el.textContent = fmt.format(Math.round(end * e));
            if (p < 1) requestAnimationFrame(tick);
        })(t0);
    }
    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach((en) => {
                if (!en.isIntersecting) return;
                en.target.classList.add('is-in');
                $$('[data-count]', en.target).forEach(countUp);
                if (en.target.hasAttribute('data-count')) countUp(en.target);
                io.unobserve(en.target);
            });
        }, { threshold: .12, rootMargin: '0px 0px -40px 0px' });
        $$('[data-reveal], [data-count]').forEach((el) => io.observe(el));
    } else {
        $$('[data-reveal]').forEach((el) => el.classList.add('is-in'));
        $$('[data-count]').forEach((el) => (el.textContent = el.dataset.count));
    }

    /* ---------- rotating words in the hero ---------- */
    $$('.s-rotator').forEach((r) => {
        const words = $$('span', r);
        if (words.length < 2 || reduce) return;
        let i = 0;
        words[0].classList.add('is-on');
        setInterval(() => {
            words[i].classList.remove('is-on'); words[i].classList.add('is-off');
            const prev = words[i];
            setTimeout(() => prev.classList.remove('is-off'), 600);
            i = (i + 1) % words.length;
            words[i].classList.add('is-on');
        }, 2400);
    });

    /* ---------- copy buttons ---------- */
    $$('[data-copy]').forEach((b) => b.addEventListener('click', () => {
        const target = b.dataset.copy.startsWith('#') ? $(b.dataset.copy) : null;
        const text = target ? target.innerText : b.dataset.copy;
        navigator.clipboard?.writeText(text).then(() => window.siteToast('Copied to clipboard'));
    }));

    /* ---------- tabs (pricing, business) ---------- */
    $$('[data-tabs]').forEach((group) => {
        const btns = $$('[data-tab]', group);
        btns.forEach((b) => b.addEventListener('click', () => {
            btns.forEach((x) => x.classList.toggle('is-active', x === b));
            $$('[data-pane="' + group.dataset.tabs + '"]').forEach((p) => p.hidden = p.dataset.key !== b.dataset.tab);
        }));
    });

    /* ---------- table search (pricing) ---------- */
    $$('[data-filter]').forEach((input) => input.addEventListener('input', () => {
        const q = input.value.trim().toLowerCase();
        $$(input.dataset.filter).forEach((row) => { row.hidden = q && !row.textContent.toLowerCase().includes(q); });
    }));

    /* =====================================================================
       Quick-pay widget
       <div class="qp" data-qp data-only="data"> + <script type="application/json" id="qpData">
       ===================================================================== */
    const box = $('[data-qp]');
    const dataEl = $('#qpData');
    if (!box || !dataEl) return;

    const D = JSON.parse(dataEl.textContent);
    const cat = D.catalogue, sym = cat.symbol || '';
    const PAY = ['airtime', 'data', 'cable', 'electricity', 'education'];
    const QUICK = { airtime: [100, 200, 500, 1000, 2000], electricity: [1000, 2000, 5000, 10000] };
    const only = box.dataset.only || '';
    const cats = cat.categories.filter((c) => (only ? c.key === only : PAY.includes(c.key)));
    const plansCache = {};
    const money = (n) => sym + Number(n || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
    const tile = (s, cls) => s.logo
        ? `<span class="s-tile ${cls || ''}" style="--c:${esc(s.color)}"><img src="${esc(s.logo)}" alt="" loading="lazy"></span>`
        : `<span class="s-tile ${cls || ''}" style="--c:${esc(s.color)};color:${esc(s.ink || '#fff')}">${esc((s.short || s.name).slice(0, 3).toUpperCase())}</span>`;

    if (!cats.length) { box.innerHTML = '<div class="qp-body text-center py-5"><p class="mb-3">Payments are being set up for this country.</p><a class="s-btn" href="' + D.registerUrl + '">Create your account</a></div>'; return; }

    const state = {};   // per category: { service, plan, amount, recipient, meter }
    cats.forEach((c) => (state[c.key] = { service: c.services[0], plan: null, amount: '', recipient: '', meter: 'prepaid' }));
    let current = cats[0].key;

    const copy = {
        airtime: ['Buy airtime', 'Any network, delivered instantly.'],
        data: ['Buy data', 'Pick a bundle — it lands in seconds.'],
        cable: ['Pay for TV', 'DStv, GOtv, StarTimes and more.'],
        electricity: ['Pay electricity', 'Prepaid tokens and postpaid bills.'],
        education: ['Exam PINs & fees', 'WAEC, JAMB and school fees.'],
    };

    box.innerHTML = `
        ${cats.length > 1 ? `<div class="qp-tabs" role="tablist">${cats.map((c) => `
            <button type="button" class="qp-tab" role="tab" data-cat="${c.key}" style="--c:${c.color}" aria-selected="false">
                <i class="${c.icon}"></i><span>${esc(c.key === 'electricity' ? 'Power' : c.key === 'education' ? 'Exams' : c.key === 'cable' ? 'TV' : c.name.replace('Internet ', ''))}</span>
            </button>`).join('')}</div>` : ''}
        <form class="qp-form" novalidate>
            <div class="qp-body">${cats.map((c) => `<div class="qp-pane" data-pane-cat="${c.key}"></div>`).join('')}</div>
            <div class="qp-foot">
                <div class="qp-total"><small data-total-label>You pay</small><strong data-total>${money(0)}</strong></div>
                <button type="submit" class="s-btn s-btn--lg">${D.signedIn ? 'Pay now' : 'Continue'} <i class="ri-arrow-right-line"></i></button>
            </div>
            <div class="qp-secure"><i class="ri-shield-check-fill"></i> PIN-protected · Paid from your ${esc(D.brand)} wallet</div>
        </form>`;

    function paneHtml(c) {
        const st = state[c.key], s = st.service;
        const isPhone = c.key === 'airtime' || c.key === 'data';
        return `
            <h3>${esc(copy[c.key]?.[0] || c.name)}</h3>
            <div class="qp-sub">${esc(copy[c.key]?.[1] || '')}</div>
            <div class="qp-field">
                <div class="qp-label">${c.key === 'electricity' ? 'Distribution company' : c.key === 'cable' ? 'Provider' : c.key === 'education' ? 'Pay for' : 'Network'}</div>
                <div class="qp-nets" role="radiogroup">${c.services.map((x) => `
                    <button type="button" class="qp-net ${x.id === s.id ? 'is-active' : ''}" data-svc="${x.id}" style="--c:${esc(x.color)}" role="radio" aria-checked="${x.id === s.id}">${tile(x)}${esc(x.short)}</button>`).join('')}</div>
            </div>
            ${c.key === 'electricity' ? `
            <div class="qp-field">
                <div class="qp-seg" role="radiogroup">
                    <label><input type="radio" name="meter_${c.key}" value="prepaid" ${st.meter === 'prepaid' ? 'checked' : ''}><span>Prepaid</span></label>
                    <label><input type="radio" name="meter_${c.key}" value="postpaid" ${st.meter === 'postpaid' ? 'checked' : ''}><span>Postpaid</span></label>
                </div>
            </div>` : ''}
            <div class="qp-field">
                <label class="qp-label" for="qpR_${c.key}">${esc(s.recipient_label || c.recipient_label)}</label>
                <div class="qp-input-wrap">
                    <input class="qp-input" id="qpR_${c.key}" data-recipient inputmode="${isPhone || c.key !== 'education' ? 'numeric' : 'text'}" maxlength="20"
                           autocomplete="${isPhone ? 'tel' : 'off'}" placeholder="${isPhone ? '0803 123 4567' : c.key === 'electricity' ? 'e.g. 45012345678' : c.key === 'cable' ? 'e.g. 7012345678' : ''}" value="${esc(st.recipient)}">
                    <span class="qp-detect" data-detect></span>
                </div>
                <div class="qp-error" data-err="recipient"></div>
            </div>
            ${s.needs_product ? `
            <div class="qp-field">
                <div class="qp-label">${c.key === 'cable' ? 'Bouquet' : 'Plan'} <small data-plan-count></small></div>
                <div class="qp-plans" data-plans>${'<div class="qp-skel"></div>'.repeat(4)}</div>
                <div class="qp-error" data-err="plan"></div>
            </div>` : ''}
            ${s.needs_amount ? `
            <div class="qp-field">
                <label class="qp-label" for="qpA_${c.key}">Amount ${s.min ? `<small>${money(s.min)} – ${money(s.max || 0)}</small>` : ''}</label>
                <div class="qp-input-wrap"><span class="qp-prefix">${esc(sym.trim())}</span>
                    <input class="qp-input" id="qpA_${c.key}" data-amount type="number" inputmode="numeric" min="${s.min || 0}" ${s.max ? `max="${s.max}"` : ''} placeholder="0" value="${esc(st.amount)}"></div>
                ${QUICK[c.key] ? `<div class="qp-amounts">${QUICK[c.key].filter((a) => (!s.min || a >= s.min) && (!s.max || a <= s.max)).map((a) => `<button type="button" data-quick="${a}">${money(a)}</button>`).join('')}</div>` : ''}
                <div class="qp-error" data-err="amount"></div>
            </div>` : ''}`;
    }

    function pane(key) { return $(`[data-pane-cat="${key}"]`, box); }

    function render(key) {
        const c = cats.find((x) => x.key === key);
        const p = pane(key);
        p.innerHTML = paneHtml(c);
        if (state[key].service.needs_product) loadPlans(key);
        detect(key);
        total();
    }

    function show(key) {
        current = key;
        $$('.qp-tab', box).forEach((t) => { const on = t.dataset.cat === key; t.classList.toggle('is-active', on); t.setAttribute('aria-selected', on); });
        $$('.qp-pane', box).forEach((p) => p.classList.toggle('is-active', p.dataset.paneCat === key));
        if (!pane(key).innerHTML.trim()) render(key);
        total();
    }

    async function loadPlans(key) {
        const st = state[key], s = st.service, wrap = $('[data-plans]', pane(key));
        if (!wrap) return;
        try {
            if (!plansCache[s.id]) {
                const r = await fetch(D.plansUrl.replace('__ID__', s.id), { headers: { Accept: 'application/json' } });
                plansCache[s.id] = (await r.json()).data || [];
            }
        } catch (e) { plansCache[s.id] = null; }
        if (state[key].service.id !== s.id) return;   // switched meanwhile
        const plans = plansCache[s.id];
        if (!plans) { wrap.innerHTML = '<div class="text-muted small">Could not load plans. <a href="#" data-retry>Try again</a></div>'; return; }
        if (!plans.length) { wrap.innerHTML = '<div class="text-muted small">No plans available right now.</div>'; return; }
        $('[data-plan-count]', pane(key)).textContent = plans.length + ' options';
        wrap.innerHTML = plans.map((pl) => `
            <button type="button" class="qp-plan ${st.plan && st.plan.id === pl.id ? 'is-active' : ''}" data-plan="${pl.id}">
                <b>${esc(pl.name)}</b><span>${esc(pl.label)}</span>${pl.validity && !pl.name.toLowerCase().includes(String(pl.validity).toLowerCase()) ? ` <small>· ${esc(pl.validity)}</small>` : ''}
            </button>`).join('');
    }

    function detect(key) {
        if (key !== 'airtime' && key !== 'data') return;
        const p = pane(key), input = $('[data-recipient]', p), chip = $('[data-detect]', p);
        const digits = (input.value || '').replace(/\D/g, '').replace(/^234/, '0');
        let found = null;
        Object.entries(cat.prefixes || {}).forEach(([net, list]) => { if (list.some((pre) => digits.startsWith(pre))) found = net; });
        if (!found) { chip.classList.remove('is-on'); return; }
        const c = cats.find((x) => x.key === key);
        const svc = c.services.find((x) => x.code === found || x.code === found + '-data' || x.code.startsWith(found + '-'));
        if (!svc) { chip.classList.remove('is-on'); return; }
        chip.innerHTML = tile(svc) + esc(svc.short);
        chip.classList.add('is-on');
        if (state[key].service.id !== svc.id) pickService(key, svc.id, true);
    }

    function pickService(key, id, keepInput) {
        const c = cats.find((x) => x.key === key), st = state[key];
        const svc = c.services.find((x) => x.id === Number(id));
        if (!svc) return;
        const p = pane(key);
        if (keepInput) st.recipient = $('[data-recipient]', p).value;
        const a = $('[data-amount]', p); if (a) st.amount = a.value;
        st.service = svc; st.plan = null;
        const focused = document.activeElement && document.activeElement.matches('[data-recipient]');
        render(key);
        if (focused) { const r = $('[data-recipient]', p); r.focus(); r.setSelectionRange(r.value.length, r.value.length); }
    }

    function total() {
        const st = state[current], el = $('[data-total]', box);
        let v = 0;
        if (st.service.needs_product) v = st.plan ? st.plan.price : 0;
        else v = parseFloat(($('[data-amount]', pane(current)) || {}).value || '0') || 0;
        el.textContent = money(v);
        el.animate && !reduce && el.animate([{ transform: 'scale(1.06)' }, { transform: 'scale(1)' }], { duration: 220 });
    }

    function err(key, field, msg) {
        const e = $(`[data-err="${field}"]`, pane(key));
        if (!e) return;
        e.textContent = msg || '';
        e.classList.toggle('is-on', !!msg);
    }

    /* events (delegated) */
    box.addEventListener('click', (e) => {
        const t = e.target.closest('.qp-tab'); if (t) return show(t.dataset.cat);
        const n = e.target.closest('[data-svc]'); if (n) return pickService(current, n.dataset.svc, true);
        const pl = e.target.closest('[data-plan]');
        if (pl) {
            const st = state[current];
            st.plan = (plansCache[st.service.id] || []).find((x) => String(x.id) === pl.dataset.plan) || null;
            $$('.qp-plan', pane(current)).forEach((b) => b.classList.toggle('is-active', b === pl));
            err(current, 'plan'); total(); return;
        }
        const q = e.target.closest('[data-quick]');
        if (q) {
            const a = $('[data-amount]', pane(current)); a.value = q.dataset.quick;
            $$('[data-quick]', pane(current)).forEach((b) => b.classList.toggle('is-active', b === q));
            err(current, 'amount'); total(); return;
        }
        if (e.target.closest('[data-retry]')) { e.preventDefault(); loadPlans(current); }
    });
    box.addEventListener('input', (e) => {
        if (e.target.matches('[data-recipient]')) { state[current].recipient = e.target.value; err(current, 'recipient'); detect(current); }
        if (e.target.matches('[data-amount]')) { state[current].amount = e.target.value; $$('[data-quick]', pane(current)).forEach((b) => b.classList.toggle('is-active', b.dataset.quick === e.target.value)); err(current, 'amount'); total(); }
    });
    box.addEventListener('change', (e) => { if (e.target.name && e.target.name.startsWith('meter_')) state[current].meter = e.target.value; });

    box.addEventListener('submit', (e) => {
        e.preventDefault();
        const st = state[current], s = st.service, p = pane(current);
        const rec = ($('[data-recipient]', p).value || '').trim();
        let ok = true;
        const digits = rec.replace(/\D/g, '');
        if (!rec) { err(current, 'recipient', 'Please enter the ' + (s.recipient_label || 'number').toLowerCase() + '.'); ok = false; }
        else if ((current === 'airtime' || current === 'data') && (digits.length < 10 || digits.length > 13)) { err(current, 'recipient', 'That phone number looks incomplete.'); ok = false; }
        if (s.needs_product && !st.plan) { err(current, 'plan', 'Choose a ' + (current === 'cable' ? 'bouquet' : 'plan') + '.'); ok = false; }
        if (s.needs_amount) {
            const a = parseFloat(($('[data-amount]', p) || {}).value || '0');
            if (!a) { err(current, 'amount', 'Enter an amount.'); ok = false; }
            else if (s.min && a < s.min) { err(current, 'amount', 'The minimum is ' + money(s.min) + '.'); ok = false; }
            else if (s.max && a > s.max) { err(current, 'amount', 'The maximum is ' + money(s.max) + '.'); ok = false; }
        }
        if (!ok) { const first = $('.qp-error.is-on', p); first && first.closest('.qp-field').querySelector('input, button')?.focus(); return; }

        const f = document.createElement('form');
        f.method = 'POST'; f.action = D.action; f.hidden = true;
        const add = (k, v) => { if (v === null || v === undefined || v === '') return; const i = document.createElement('input'); i.type = 'hidden'; i.name = k; i.value = v; f.appendChild(i); };
        add('_token', D.csrf); add('category', current); add('service', s.code); add('recipient', rec);
        if (s.needs_amount) add('amount', $('[data-amount]', p).value);
        if (st.plan) add('product', st.plan.id);
        if (current === 'electricity') add('meter_type', st.meter);
        document.body.appendChild(f);
        $('button[type=submit]', box).classList.add('is-loading');
        f.submit();
    });

    // Deep links: /#pay-data opens that tab.
    const want = (location.hash.match(/^#pay-(\w+)/) || [])[1];
    show(cats.some((c) => c.key === want) ? want : current);
    $$('[data-open-tab]').forEach((a) => a.addEventListener('click', (e) => {
        const k = a.dataset.openTab;
        if (!cats.some((c) => c.key === k)) return;
        e.preventDefault(); show(k);
        box.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
    }));
})();
