(function () {
    'use strict';

    const cfg = window.DASHBOARD_POLL;
    if (!cfg) { return; }

    const INTERVAL_MS = 30000;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const byId = (id) => document.getElementById(id);
    const whole = (n) => Math.round(n).toLocaleString('en-US');
    const money = (n) => '₱' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) { node.className = className; }
        if (text !== undefined) { node.textContent = text; }
        return node;
    }

    // Counts from the number on screen to the new one. Skipped when the value is
    // unchanged or the person has asked their system for reduced motion.
    function animateNumber(node, to, format) {
        if (!node) { return; }
        const shown = parseFloat(node.textContent.replace(/[^0-9.-]/g, ''));
        const from = Number.isNaN(shown) ? to : shown;

        if (from === to || reduceMotion) {
            node.textContent = format(to);
            return;
        }

        const start = performance.now();
        const step = (now) => {
            const t = Math.min((now - start) / 500, 1);
            node.textContent = format(from + (to - from) * t);
            if (t < 1) { requestAnimationFrame(step); }
        };
        requestAnimationFrame(step);
    }

    function renderStats(d) {
        animateNumber(byId('invTotalProducts'), d.total_products, whole);
        animateNumber(byId('invTotalValue'), d.total_value, money);
        animateNumber(byId('invLowStock'), d.low_stock, whole);
        animateNumber(byId('invOutOfStock'), d.out_of_stock, whole);

        byId('invLowStock').closest('.stat-card').classList.toggle('is-low', d.low_stock > 0);
        byId('invOutOfStock').closest('.stat-card').classList.toggle('is-out', d.out_of_stock > 0);

    }

    function renderAlert(payload) {
        const box = byId('lowStockAlert');
        const items = payload.data;
        const total = payload.meta.total;

        const signature = JSON.stringify([total, items.map((p) => [p.id, p.quantity, p.reorder_level])]);
        if (box.dataset.signature === signature) { return; }
        box.dataset.signature = signature;

        box.replaceChildren();
        if (total === 0) { return; }

        const alert = el('div', 'alert alert-warning d-flex');
        const icon = el('i', 'bi bi-exclamation-triangle-fill fs-4 me-3 flex-shrink-0');
        icon.setAttribute('aria-hidden', 'true');

        const body = el('div');
        body.append(el('strong', '', total + ' item(s) need attention'));

        const list = el('ul', 'mb-0 mt-2 small');
        items.forEach((p) => {
            const out = p.status === 'out-of-stock';
            const link = el('a', 'fw-bold' + (out ? ' text-danger' : ''), p.name);
            link.href = p.url;
            const li = el('li');
            li.append(link, (out ? ': out of stock' : ': ' + p.quantity + ' left') + ' (reorder at ' + p.reorder_level + ')');
            list.append(li);
        });

        if (total > items.length) {
            const more = el('a', '', 'and ' + (total - items.length) + ' more in Inventory');
            more.href = cfg.productsUrl;
            const li = el('li');
            li.append(more);
            list.append(li);
        }

        body.append(list);
        alert.append(icon, body);
        box.append(alert);
    }

    function renderRecent(payload) {
        const body = byId('recentTransactionsBody');
        body.replaceChildren();

        if (payload.data.length === 0) {
            const td = el('td', 'text-center text-muted py-3', 'No movements recorded yet.');
            td.colSpan = 5;
            const tr = el('tr');
            tr.append(td);
            body.append(tr);
            return;
        }

        payload.data.forEach((t) => {
            const badge = el('span', 'badge text-bg-' + t.type_color, t.type_label);
            const typeCell = el('td');
            typeCell.append(badge);

            const tr = el('tr');
            tr.append(
                el('td', '', t.date),
                el('td', '', t.product.name),
                typeCell,
                el('td', 'text-end', (t.change > 0 ? '+' : '') + t.change),
                el('td', 'text-end', t.balance_after === null ? '-' : String(t.balance_after))
            );
            body.append(tr);
        });
    }

    function stamp(date) {
        byId('lastUpdated').textContent = 'Updated ' +
            date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }

    function showError(message) {
        const node = byId('pollError');
        node.textContent = message;
        node.hidden = !message;
    }

    async function getJson(url) {
        const response = await fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (response.status === 401) { throw new Error('session'); }
        if (!response.ok) { throw new Error('http ' + response.status); }
        return response.json();
    }

    let timer = null;
    let inFlight = false;

    async function poll() {
        if (inFlight) { return; }
        inFlight = true;

        try {
            const [stats, low, recent] = await Promise.all([
                getJson(cfg.stats), getJson(cfg.lowStock), getJson(cfg.recent),
            ]);
            renderStats(stats.data);
            renderAlert(low);
            renderRecent(recent);
            stamp(new Date(stats.meta.generated_at));
            showError('');
        } catch (error) {
            if (error.message === 'session') {
                showError('Your session expired. Log in again to keep this page updating.');
                clearInterval(timer);
            } else {
                showError('Could not refresh. Showing the last data and trying again in 30 seconds.');
            }
        } finally {
            inFlight = false;
        }
    }

    stamp(new Date());
    timer = setInterval(() => { if (!document.hidden) { poll(); } }, INTERVAL_MS);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) { poll(); } });
})();