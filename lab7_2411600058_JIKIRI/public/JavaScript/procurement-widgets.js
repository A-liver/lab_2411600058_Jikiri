/**
 * Procurement widgets: refreshes the four dashboard widgets from the API every 30 seconds.
 */
(function () {
    'use strict';

    const API = window.PROCUREMENT_API;
    if (!API) return;

    const POLL_INTERVAL_MS = 30000;
    const $ = (id) => document.getElementById(id);
    const money = (n) => '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));

    async function getJson(url) {
        const res = await fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (res.status === 401 || res.status === 419) { window.location.reload(); throw new Error('Session expired'); }
        if (!res.ok) throw new Error(`HTTP ${res.status} for ${url}`);
        return res.json();
    }

    function renderPending(p) {
        $('wPendingCount').textContent = p.count;
        $('wPendingValue').textContent = money(p.total_value);

        const parts = [];
        if (p.awaiting_final > 0) parts.push(`${p.awaiting_final} awaiting admin approval`);
        if (p.oldest_days !== null) parts.push(`oldest waiting ${p.oldest_days} day(s)`);
        $('wPendingExtra').textContent = parts.length ? parts.join(' · ') : 'Nothing waiting';
    }

    function renderRecent(rows) {
        $('wRecentBody').innerHTML = rows.length ? rows.map((o) => `
            <tr>
                <td><a href="${esc(o.url)}">${esc(o.reference)}</a></td>
                <td>${esc(o.supplier)}</td>
                <td><span class="badge bg-${esc(o.status_color)} ${o.status_color === 'warning' ? 'text-dark' : ''}">${esc(o.status_label)}</span></td>
                <td class="text-end">${money(o.total)}</td>
            </tr>`).join('')
            : '<tr><td colspan="4" class="text-center text-muted py-3">No purchase orders yet.</td></tr>';
    }

    function renderPerformance(rows) {
        $('wPerformanceBody').innerHTML = rows.length ? rows.map((s) => {
            const color = s.on_time_pct >= 90 ? 'success' : (s.on_time_pct >= 70 ? 'warning' : 'danger');
            return `
            <tr>
                <td><a href="${esc(s.url)}">${esc(s.name)}</a></td>
                <td><div class="progress" style="height:8px"><div class="progress-bar bg-${color}" style="width:${s.on_time_pct}%"></div></div></td>
                <td class="text-end">${s.on_time_pct}%</td>
                <td class="text-end">${s.orders}</td>
                <td class="text-end">${money(s.spent)}</td>
            </tr>`;
        }).join('')
            : '<tr><td colspan="5" class="text-center text-muted py-3">No completed deliveries yet.</td></tr>';
    }

    function renderReorder(r) {
        const badge = $('wReorderCount');
        badge.textContent = r.count;
        badge.className = 'badge bg-' + (r.count > 0 ? 'danger' : 'success');

        $('wReorderBody').innerHTML = r.items.length ? r.items.map((i) => `
            <li class="list-group-item">
            <a href="${esc(i.url)}" class="fw-semibold">${esc(i.name)}</a>${i.in_draft > 0 ? ' <span class="badge bg-info text-dark">Draft ready</span>' : ''}
                <div class="text-muted">Stock ${i.stock} / reorder at ${i.reorder_point} · order ${i.quantity} · ${esc(i.supplier)}</div>
            </li>`).join('')
            : '<li class="list-group-item text-muted">Every product is above its reorder point.</li>';

        const more = r.count - r.items.length;
        $('wReorderFooter').innerHTML =
            (more > 0 ? `+ ${more} more. ` : '') +
            (r.without_supplier > 0 ? `${r.without_supplier} product(s) have no active supplier. ` : '') +
            (r.drafted > 0 ? `${r.drafted} already in a draft order. ` : '') +
            '<a href="/purchase-orders?status=draft">Draft orders</a>';
    }

    let inFlight = false;

    async function refresh() {
        if (inFlight) return;
        inFlight = true;
        try {
            const [pending, recent, performance, reorder] = await Promise.all([
                getJson(API.pending), getJson(API.recent), getJson(API.performance), getJson(API.reorder),
            ]);
            renderPending(pending.data);
            renderRecent(recent.data);
            renderPerformance(performance.data);
            renderReorder(reorder.data);
        } catch (err) {
            console.error('Procurement widgets refresh failed:', err);   // keep the old numbers on screen
        } finally {
            inFlight = false;
        }
    }

    setInterval(() => { if (!document.hidden) refresh(); }, POLL_INTERVAL_MS);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
})();