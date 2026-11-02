@php $po = $purchaseOrder ?? null; @endphp

@if ($errors->has('items'))
    <div class="alert alert-danger">{{ $errors->first('items') }}</div>
@endif

<div class="row g-3">
    <div class="col-md-4">
        <label for="supplier_id" class="form-label">Supplier</label>
        <select id="supplier_id" name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror" required>
            <option value="">-- Select a supplier --</option>
            @foreach ($suppliers as $s)
                <option value="{{ $s->id }}" @selected(old('supplier_id', $po->supplier_id ?? request('supplier_id')) == $s->id)>{{ $s->name }}</option>
            @endforeach
        </select>
        @error('supplier_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div id="supplierInfo" class="form-text"></div>
    </div>
    <div class="col-md-3">
        <label for="order_date" class="form-label">Order Date</label>
        <input type="date" id="order_date" name="order_date"
               value="{{ old('order_date', $po?->order_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
               class="form-control @error('order_date') is-invalid @enderror" required>
        @error('order_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label for="expected_delivery_date" class="form-label">Expected Delivery</label>
        <input type="date" id="expected_delivery_date" name="expected_delivery_date"
               value="{{ old('expected_delivery_date', $po?->expected_delivery_date?->format('Y-m-d')) }}"
               class="form-control @error('expected_delivery_date') is-invalid @enderror">
        @error('expected_delivery_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-2">
        <label for="tax_rate" class="form-label">Tax Rate (%)</label>
        <input type="number" id="tax_rate" name="tax_rate" min="0" max="100" step="0.01"
               value="{{ old('tax_rate', $po->tax_rate ?? 0) }}"
               class="form-control @error('tax_rate') is-invalid @enderror" required>
        @error('tax_rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<h6 class="mt-4 mb-2">Line Items</h6>
<div class="table-responsive">
    <table class="table table-sm align-middle" id="itemsTable">
        <thead>
            <tr>
                <th style="width:42%">Product</th>
                <th style="width:15%">Quantity</th>
                <th style="width:18%">Unit Price ($)</th>
                <th class="text-end" style="width:15%">Line Total</th>
                <th style="width:10%"></th>
            </tr>
        </thead>
        <tbody id="itemRows"></tbody>
        <tfoot>
            <tr><td colspan="3" class="text-end">Subtotal</td><td class="text-end" id="subtotalCell">$0.00</td><td></td></tr>
            <tr><td colspan="3" class="text-end">Tax</td><td class="text-end" id="taxCell">$0.00</td><td></td></tr>
            <tr class="fw-bold"><td colspan="3" class="text-end">Total</td><td class="text-end" id="totalCell">$0.00</td><td></td></tr>
        </tfoot>
    </table>
</div>
<div class="d-flex justify-content-between align-items-center mb-3">
    <button type="button" id="addRow" class="btn btn-sm btn-outline-primary">+ Add Line Item</button>
    <small id="minWarning" class="text-muted"></small>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <label for="terms" class="form-label">Terms</label>
        <textarea id="terms" name="terms" rows="3" maxlength="1000" class="form-control @error('terms') is-invalid @enderror">{{ old('terms', $po->terms ?? '') }}</textarea>
        @error('terms') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="notes" class="form-label">Notes</label>
        <textarea id="notes" name="notes" rows="3" maxlength="1000" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $po->notes ?? '') }}</textarea>
        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

@push('scripts')
<script>
(function () {
    const cfg = {
        catalog: @json($catalog),
        meta: @json($supplierMeta),
        items: @json(old('items', $initialItems)),
    };

    const supplierEl = document.getElementById('supplier_id');
    const orderDateEl = document.getElementById('order_date');
    const expectedEl = document.getElementById('expected_delivery_date');
    const termsEl = document.getElementById('terms');
    const taxEl = document.getElementById('tax_rate');
    const tbody = document.getElementById('itemRows');
    const infoEl = document.getElementById('supplierInfo');
    const warnEl = document.getElementById('minWarning');
    const form = supplierEl.closest('form');

    let rowIndex = 0;
    let currentSupplier = supplierEl.value;

    const money = (n) => '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const products = () => cfg.catalog[supplierEl.value] || [];

    function addDays(dateStr, days) {
        const d = new Date(dateStr + 'T00:00:00');
        d.setDate(d.getDate() + Number(days));
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function makeInput(type, name, extra = {}) {
        const input = document.createElement('input');
        input.type = type;
        input.name = name;
        input.className = 'form-control form-control-sm';
        Object.assign(input, extra);
        return input;
    }

    function addRow(item = null) {
        if (!supplierEl.value) {
            alert('Select a supplier first.');
            return;
        }

        const idx = rowIndex++;
        const tr = document.createElement('tr');

        const select = document.createElement('select');
        select.name = `items[${idx}][product_id]`;
        select.className = 'form-select form-select-sm';
        select.required = true;
        select.add(new Option('-- Select product --', ''));
        products().forEach((p) => select.add(new Option(`${p.name} (${p.sku}), stock ${p.stock}`, p.id)));

        const qty = makeInput('number', `items[${idx}][quantity]`, { min: 1, step: 1, required: true });
        const price = makeInput('number', `items[${idx}][unit_price]`, { min: 0, step: 0.01, required: true });
        const lineTotal = document.createElement('td');
        lineTotal.className = 'text-end line-total';
        lineTotal.textContent = '$0.00';

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-sm btn-outline-danger';
        remove.textContent = 'Remove';
        remove.addEventListener('click', () => { tr.remove(); refreshOptions(); recalc(); });

        const cells = [select, qty, price].map((el) => {
            const td = document.createElement('td');
            td.appendChild(el);
            return td;
        });
        const removeCell = document.createElement('td');
        removeCell.className = 'text-end';
        removeCell.appendChild(remove);
        tr.append(cells[0], cells[1], cells[2], lineTotal, removeCell);
        tbody.appendChild(tr);

        function applyProduct(useDefaults) {
            const p = products().find((x) => String(x.id) === select.value);
            if (!p) return;
            qty.min = p.min_qty;
            qty.step = p.pack_size;
            if (useDefaults) {
                price.value = p.unit_cost.toFixed(2);
                if (!qty.value) qty.value = p.min_qty;
            }
        }

        select.addEventListener('change', () => { applyProduct(true); refreshOptions(); recalc(); });
        qty.addEventListener('input', recalc);
        price.addEventListener('input', recalc);

        if (item) {                                  // restoring saved or old() values
            select.value = item.product_id;
            qty.value = item.quantity;
            price.value = item.unit_price;
            applyProduct(false);
        }
        refreshOptions();
        recalc();
    }

    // A product can only be chosen once: disable it in the other rows.
    function refreshOptions() {
        const selects = [...tbody.querySelectorAll('select')];
        const used = selects.map((s) => s.value).filter(Boolean);
        selects.forEach((s) => {
            [...s.options].forEach((o) => { o.disabled = o.value !== '' && o.value !== s.value && used.includes(o.value); });
        });
    }

    function recalc() {
        let subtotal = 0;
        tbody.querySelectorAll('tr').forEach((tr) => {
            const q = parseFloat(tr.querySelector('input[name$="[quantity]"]').value) || 0;
            const p = parseFloat(tr.querySelector('input[name$="[unit_price]"]').value) || 0;
            tr.querySelector('.line-total').textContent = money(q * p);
            subtotal += q * p;
        });

        const tax = Math.round(subtotal * (parseFloat(taxEl.value) || 0)) / 100;
        document.getElementById('subtotalCell').textContent = money(subtotal);
        document.getElementById('taxCell').textContent = money(tax);
        document.getElementById('totalCell').textContent = money(subtotal + tax);

        const meta = cfg.meta[supplierEl.value];
        if (meta && meta.minimum_order_amount > 0) {
            const below = subtotal < meta.minimum_order_amount;
            warnEl.className = below ? 'text-danger' : 'text-success';
            warnEl.textContent = `Supplier minimum order: ${money(meta.minimum_order_amount)}` + (below ? ' (not reached yet)' : ' (reached)');
        } else {
            warnEl.textContent = '';
        }
    }

    supplierEl.addEventListener('change', () => {
        if (tbody.children.length && !confirm('Changing the supplier clears the line items. Continue?')) {
            supplierEl.value = currentSupplier;
            return;
        }
        tbody.innerHTML = '';
        currentSupplier = supplierEl.value;

        const meta = cfg.meta[supplierEl.value];
        if (meta) {
            infoEl.textContent = `Lead time ${meta.lead_time_days} days, ${meta.payment_terms}`;
            if (orderDateEl.value && (!expectedEl.value || expectedEl.dataset.auto === '1')) {
                expectedEl.value = addDays(orderDateEl.value, meta.lead_time_days);
                expectedEl.dataset.auto = '1';
            }
            if (!termsEl.value.trim() || termsEl.dataset.auto === '1') {
                termsEl.value = `Payment terms: ${meta.payment_terms}`;
                termsEl.dataset.auto = '1';
            }
        } else {
            infoEl.textContent = '';
        }
        recalc();
    });

    expectedEl.addEventListener('input', () => { delete expectedEl.dataset.auto; });
    termsEl.addEventListener('input', () => { delete termsEl.dataset.auto; });
    taxEl.addEventListener('input', recalc);
    document.getElementById('addRow').addEventListener('click', () => addRow());

    form.addEventListener('submit', (e) => {
        if (!tbody.children.length) {
            e.preventDefault();
            alert('Add at least one line item.');
        }
    });

    // Initial state (edit page, or the form coming back after a validation error)
    const meta0 = cfg.meta[supplierEl.value];
    if (meta0) infoEl.textContent = `Lead time ${meta0.lead_time_days} days, ${meta0.payment_terms}`;
    Object.values(cfg.items || {}).forEach((item) => addRow(item));
    if (!tbody.children.length && supplierEl.value) addRow();
    recalc();
})();
</script>
@endpush