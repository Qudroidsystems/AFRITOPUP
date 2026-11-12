/*
 * AfriTopup support chat — one widget for the customer page and the admin inbox.
 *
 *   new SupportChat(rootEl, {
 *     messagesUrl, sendUrl, typingUrl,     // JSON endpoints
 *     staff: false,                        // viewer is support staff
 *     onMeta(meta), onSent(msg)            // optional hooks
 *   })
 *
 * Polls every 3 s while visible (15 s in a background tab) and immediately on a
 * Reverb "support" poke when window.AfriRealtime is present.
 */
(function () {
    'use strict';

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
    const linkify = (s) => esc(s).replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener nofollow">$1</a>').replace(/\n/g, '<br>');
    const timeOf = (iso) => new Date(iso).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    const dayOf = (iso) => {
        const d = new Date(iso), today = new Date(), y = new Date(); y.setDate(today.getDate() - 1);
        if (d.toDateString() === today.toDateString()) return 'Today';
        if (d.toDateString() === y.toDateString()) return 'Yesterday';
        return d.toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short', year: d.getFullYear() === today.getFullYear() ? undefined : 'numeric' });
    };
    const kb = (n) => n > 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB';
    const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || '';
    const status = { successful: 'ok', processing: 'wait', pending: 'wait', failed: 'bad', refunded: 'muted' };

    class SupportChat {
        constructor(root, opts) {
            this.root = root;
            this.o = Object.assign({ staff: false, pollMs: 3000, idleMs: 15000 }, opts);
            this.list = root.querySelector('[data-chat-list]');
            this.form = root.querySelector('[data-chat-form]');
            this.input = root.querySelector('[data-chat-input]');
            this.file = root.querySelector('[data-chat-file]');
            this.preview = root.querySelector('[data-chat-preview]');
            this.typingEl = root.querySelector('[data-chat-typing]');
            this.sendBtn = root.querySelector('[data-chat-send]');
            this.lastId = 0; this.firstId = null; this.readUpTo = 0; this.hasMore = true;
            this.pending = null; this.txnRef = null; this.busy = false; this.lastTypingSent = 0;
            this.seenIds = new Set();
            this.bind();
            this.load();
        }

        /* ---------- loading ---------- */
        async fetchJson(url) {
            const r = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const j = await r.json();
            return j.data || j;
        }

        url(params) {
            const u = new URL(this.o.messagesUrl, location.href);
            Object.entries(params).forEach(([k, v]) => v !== null && v !== undefined && u.searchParams.set(k, v));
            return u.toString();
        }

        async load() {
            try {
                const d = await this.fetchJson(this.url({ seen: 1 }));
                this.list.innerHTML = '';
                this.append(d.messages, true);
                this.meta(d.conversation);
                this.hasMore = d.messages.length >= 40;
                this.scrollBottom(false);
            } catch (e) {
                this.list.innerHTML = '<div class="ch-empty">Could not load the chat. <a href="#" data-retry>Try again</a></div>';
            }
            this.schedule();
        }

        async poll() {
            if (this.polling) return;
            this.polling = true;
            try {
                const visible = document.visibilityState === 'visible';
                const d = await this.fetchJson(this.url({ after: this.lastId || 0, seen: visible ? 1 : 0 }));
                const nearBottom = this.isNearBottom();
                if (d.messages.length) {
                    this.append(d.messages);
                    if (nearBottom || d.messages.some((m) => m.mine)) this.scrollBottom(true);
                    else this.showNewPill();
                }
                this.meta(d.conversation);
            } catch (e) { /* offline: try again next tick */ }
            this.polling = false;
        }

        schedule() {
            clearTimeout(this.timer);
            const ms = document.visibilityState === 'visible' ? this.o.pollMs : this.o.idleMs;
            this.timer = setTimeout(async () => { await this.poll(); this.schedule(); }, ms);
        }

        async older() {
            if (!this.hasMore || this.loadingOlder || !this.firstId) return;
            this.loadingOlder = true;
            const h = this.list.scrollHeight;
            try {
                const d = await this.fetchJson(this.url({ before: this.firstId }));
                this.hasMore = d.messages.length >= 40;
                this.prepend(d.messages);
                this.list.scrollTop = this.list.scrollHeight - h;
            } catch (e) {}
            this.loadingOlder = false;
        }

        /* ---------- rendering ---------- */
        bubble(m) {
            if (m.type === 'event') return `<div class="ch-event" data-id="${m.id}"><span>${linkify(m.body)}</span></div>`;
            const a = m.attachment, t = m.transaction;
            let inner = '';
            if (t) inner += `<a class="ch-txn" href="${this.o.txnUrl ? this.o.txnUrl.replace('__REF__', encodeURIComponent(t.reference)) : '#'}" ${this.o.txnUrl ? '' : 'onclick="return false"'}>
                <span class="ch-txn-ic"><i class="ri-receipt-line"></i></span><span><b>${esc(t.title || 'Payment')}</b><small>${esc(t.reference)} · ${esc(t.amount)}</small></span>
                <em class="ch-st ch-st--${status[t.status] || 'muted'}">${esc(t.status)}</em></a>`;
            if (a && a.image) inner += `<a class="ch-img" href="${esc(a.url)}" target="_blank" rel="noopener"><img src="${esc(a.url)}" alt="${esc(a.name)}" loading="lazy"></a>`;
            else if (a) inner += `<a class="ch-file" href="${esc(a.url)}" target="_blank" rel="noopener"><i class="ri-file-pdf-2-line"></i><span><b>${esc(a.name)}</b><small>${kb(a.size || 0)}</small></span></a>`;
            if (m.body) inner += `<div class="ch-text">${linkify(m.body)}</div>`;
            const who = !m.mine && m.sender ? `<div class="ch-who">${esc(m.sender)}</div>` : '';
            return `<div class="ch-row ${m.mine ? 'is-mine' : ''} ${m.from_staff ? 'is-staff' : ''}" data-id="${m.id}" data-day="${dayOf(m.created_at)}">
                <div class="ch-bubble">${who}${inner}<div class="ch-meta">${timeOf(m.created_at)}${m.mine ? '<i class="ch-tick ri-check-line"></i>' : ''}</div></div></div>`;
        }

        withDays(html, msgs, prevDay) {
            let out = '', day = prevDay;
            msgs.forEach((m) => {
                const d = dayOf(m.created_at);
                if (d !== day) { out += `<div class="ch-day"><span>${d}</span></div>`; day = d; }
                out += this.bubble(m);
            });
            return out;
        }

        append(msgs, initial) {
            msgs = msgs.filter((m) => !this.seenIds.has(m.id));
            if (!msgs.length) return;
            msgs.forEach((m) => this.seenIds.add(m.id));
            this.list.querySelector('.ch-empty')?.remove();
            const lastRow = [...this.list.querySelectorAll('[data-day]')].pop();
            this.list.insertAdjacentHTML('beforeend', this.withDays('', msgs, lastRow ? lastRow.dataset.day : null));
            this.lastId = Math.max(this.lastId, ...msgs.map((m) => m.id));
            if (this.firstId === null) this.firstId = msgs[0].id;
            if (!initial) this.list.querySelectorAll('.ch-row:not(.is-in)').forEach((r) => requestAnimationFrame(() => r.classList.add('is-in')));
            else this.list.querySelectorAll('.ch-row').forEach((r) => r.classList.add('is-in'));
            this.paintTicks();
        }

        prepend(msgs) {
            msgs = msgs.filter((m) => !this.seenIds.has(m.id));
            if (!msgs.length) return;
            msgs.forEach((m) => this.seenIds.add(m.id));
            // drop the first day separator; it's re-added if the day continues
            const firstSep = this.list.querySelector('.ch-day');
            const firstDay = this.list.querySelector('[data-day]')?.dataset.day;
            let html = this.withDays('', msgs, null);
            if (firstSep && dayOf(msgs[msgs.length - 1].created_at) === firstDay) firstSep.remove();
            this.list.insertAdjacentHTML('afterbegin', html);
            this.firstId = Math.min(this.firstId, ...msgs.map((m) => m.id));
            this.list.querySelectorAll('.ch-row').forEach((r) => r.classList.add('is-in'));
            this.paintTicks();
        }

        meta(c) {
            if (!c) return;
            this.readUpTo = c.read_up_to || this.readUpTo;
            this.paintTicks();
            if (this.typingEl) {
                this.typingEl.hidden = !c.typing;
                if (c.typing) this.typingEl.querySelector('span').textContent = (this.o.staff ? c.typing : c.typing + ' from support') + ' is typing';
            }
            this.o.onMeta && this.o.onMeta(c);
        }

        paintTicks() {
            this.list.querySelectorAll('.ch-row.is-mine').forEach((r) => {
                const read = +r.dataset.id <= this.readUpTo;
                const t = r.querySelector('.ch-tick');
                if (t) { t.className = 'ch-tick ' + (read ? 'ri-check-double-line is-read' : 'ri-check-line'); t.title = read ? 'Read' : 'Sent'; }
            });
        }

        isNearBottom() { return this.list.scrollHeight - this.list.scrollTop - this.list.clientHeight < 120; }

        scrollBottom(smooth) {
            this.list.scrollTo({ top: this.list.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
            this.root.querySelector('[data-chat-new]')?.setAttribute('hidden', '');
        }

        showNewPill() { this.root.querySelector('[data-chat-new]')?.removeAttribute('hidden'); }

        /* ---------- sending ---------- */
        setFile(f) {
            if (!f) { this.pending = null; this.renderPreview(); return; }
            if (f.size > 5 * 1048576) return window.siteToast ? siteToast('Files can be up to 5 MB.') : alert('Files can be up to 5 MB.');
            if (!/^image\/(jpeg|png|webp|gif)$|^application\/pdf$/.test(f.type)) return window.siteToast ? siteToast('Attach a photo or a PDF.') : alert('Attach a photo or a PDF.');
            this.pending = f;
            this.renderPreview();
            this.input.focus();
        }

        setTransaction(ref, label) {
            this.txnRef = ref;
            this.txnLabel = label;
            this.renderPreview();
            this.input.focus();
        }

        renderPreview() {
            if (!this.preview) return;
            let html = '';
            if (this.txnRef) html += `<span class="ch-chip"><i class="ri-receipt-line"></i>${esc(this.txnLabel || this.txnRef)}<button type="button" data-clear="txn" aria-label="Remove">×</button></span>`;
            if (this.pending) {
                const img = this.pending.type.startsWith('image/') ? `<img src="${URL.createObjectURL(this.pending)}" alt="">` : '<i class="ri-file-pdf-2-line"></i>';
                html += `<span class="ch-chip">${img}${esc(this.pending.name)}<button type="button" data-clear="file" aria-label="Remove">×</button></span>`;
            }
            this.preview.innerHTML = html;
            this.preview.hidden = !html;
            this.updateSend();
        }

        updateSend() {
            const has = this.input.value.trim() || this.pending || this.txnRef;
            this.sendBtn.disabled = !has || this.busy;
        }

        async send() {
            const body = this.input.value.trim();
            if ((!body && !this.pending && !this.txnRef) || this.busy) return;
            this.busy = true; this.updateSend();
            const fd = new FormData();
            if (body) fd.append('body', body);
            if (this.pending) fd.append('attachment', this.pending);
            if (this.txnRef) fd.append('transaction', this.txnRef);

            // optimistic bubble
            const tmpId = 'tmp' + Date.now();
            const tmp = { id: tmpId, type: 'text', mine: true, body, created_at: new Date().toISOString(),
                attachment: this.pending ? { name: this.pending.name, size: this.pending.size, image: this.pending.type.startsWith('image/'), url: URL.createObjectURL(this.pending) } : null };
            this.list.querySelector('.ch-empty')?.remove();
            this.list.insertAdjacentHTML('beforeend', this.bubble(tmp).replace('class="ch-row', 'class="ch-row is-in is-sending'));
            this.scrollBottom(true);
            const saved = { body, file: this.pending, txn: this.txnRef, label: this.txnLabel };
            this.input.value = ''; this.autosize(); this.pending = null; this.txnRef = null; this.renderPreview();

            try {
                const r = await fetch(this.o.sendUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
                const j = await r.json().catch(() => ({}));
                if (!r.ok) throw new Error(j.message || (j.errors && Object.values(j.errors)[0][0]) || 'Message not sent.');
                const m = (j.data || j).message;
                this.list.querySelector(`[data-id="${tmpId}"]`)?.remove();
                this.append([m]);
                this.scrollBottom(true);
                this.o.onSent && this.o.onSent(m);
            } catch (e) {
                const el = this.list.querySelector(`[data-id="${tmpId}"]`);
                if (el) { el.classList.remove('is-sending'); el.classList.add('is-failed'); el.querySelector('.ch-meta').innerHTML = 'Not sent · <a href="#" data-resend>Retry</a>'; el._saved = saved; }
                window.siteToast && siteToast(e.message || 'Message not sent.');
            }
            this.busy = false; this.updateSend();
        }

        autosize() {
            this.input.style.height = 'auto';
            this.input.style.height = Math.min(this.input.scrollHeight, 140) + 'px';
        }

        typing() {
            if (!this.o.typingUrl || Date.now() - this.lastTypingSent < 3000) return;
            this.lastTypingSent = Date.now();
            fetch(this.o.typingUrl, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } }).catch(() => {});
        }

        /* ---------- events ---------- */
        bind() {
            this.form.addEventListener('submit', (e) => { e.preventDefault(); this.send(); });
            this.input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey && !e.isComposing && window.matchMedia('(hover: hover)').matches) { e.preventDefault(); this.send(); }
            });
            this.input.addEventListener('input', () => { this.autosize(); this.updateSend(); if (this.input.value.trim()) this.typing(); });
            this.input.addEventListener('paste', (e) => {
                const f = [...(e.clipboardData?.files || [])][0];
                if (f) { e.preventDefault(); this.setFile(f); }
            });
            this.file && this.file.addEventListener('change', () => { this.setFile(this.file.files[0]); this.file.value = ''; });
            this.root.addEventListener('dragover', (e) => { if ([...e.dataTransfer.types].includes('Files')) { e.preventDefault(); this.root.classList.add('is-drop'); } });
            this.root.addEventListener('dragleave', (e) => { if (e.target === this.root) this.root.classList.remove('is-drop'); });
            this.root.addEventListener('drop', (e) => { e.preventDefault(); this.root.classList.remove('is-drop'); const f = e.dataTransfer.files[0]; if (f) this.setFile(f); });
            this.root.addEventListener('click', (e) => {
                const c = e.target.closest('[data-clear]');
                if (c) { c.dataset.clear === 'file' ? (this.pending = null) : (this.txnRef = null); this.renderPreview(); }
                if (e.target.closest('[data-retry]')) { e.preventDefault(); this.load(); }
                if (e.target.closest('[data-chat-new]')) this.scrollBottom(true);
                const rs = e.target.closest('[data-resend]');
                if (rs) {
                    e.preventDefault();
                    const row = rs.closest('.ch-row'), s = row._saved; row.remove();
                    this.input.value = s.body || ''; this.pending = s.file; this.txnRef = s.txn; this.txnLabel = s.label; this.renderPreview(); this.send();
                }
                const pick = e.target.closest('[data-attach-txn]');
                if (pick) { e.preventDefault(); this.setTransaction(pick.dataset.attachTxn, pick.dataset.label); }
            });
            this.list.addEventListener('scroll', () => {
                if (this.list.scrollTop < 60) this.older();
                if (this.isNearBottom()) this.root.querySelector('[data-chat-new]')?.setAttribute('hidden', '');
            }, { passive: true });
            document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') { this.poll(); this.schedule(); } });
            // Instant updates when websockets are on.
            window.addEventListener('afri:support', () => this.poll());
            this.updateSend();
        }
    }

    window.SupportChat = SupportChat;
})();
