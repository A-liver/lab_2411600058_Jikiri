@extends('layouts.app')

@section('title', 'Stock Count')
@section('page-title', 'Stock Count Adjustment')

@section('content')

<div class="card mb-4">
    <div class="card-body">
        <form method="POST" action="{{ route('adjustments.store') }}">
            @csrf
            <input type="hidden" name="system_quantity" id="system_quantity" value="{{ old('system_quantity') }}">

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="product_id" class="form-label">Product</label>
                    <select id="product_id" name="product_id" class="form-select @error('product_id') is-invalid @enderror" required>
                        <option value="">Choose a product</option>
                        @foreach ($products as $p)
                            <option value="{{ $p->id }}" @selected((int) old('product_id') === $p->id)>{{ $p->name }} ({{ $p->sku }})</option>
                        @endforeach
                    </select>
                    @error('product_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-3">
                    <label for="system_display" class="form-label">System quantity</label>
                    <input type="text" id="system_display" class="form-control" readonly>
                    @error('system_quantity') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-3">
                    <label for="physical_count" class="form-label">Physical count</label>
                    <input type="number" id="physical_count" name="physical_count" min="0" step="1"
                           value="{{ old('physical_count') }}"
                           class="form-control @error('physical_count') is-invalid @enderror" required>
                    @error('physical_count') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <p class="mb-0" id="difference" aria-live="polite"></p>
                </div>

                <div class="col-md-6">
                    <label for="reason" class="form-label">Reason</label>
                    <select id="reason" name="reason" class="form-select @error('reason') is-invalid @enderror" required>
                        <option value="">Choose a reason</option>
                        @foreach ($reasons as $key => $label)
                            <option value="{{ $key }}" @selected(old('reason') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('reason') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea id="notes" name="notes" rows="3" maxlength="1000"
                              class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                    @error('notes') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary" id="submitBtn" disabled>Record Adjustment</button>
                <a href="{{ route('transactions.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    const urlTemplate = @json(route('api.products.quantity', ['product' => '__ID__'], false));
    const select = document.getElementById('product_id');
    const system = document.getElementById('system_quantity');
    const display = document.getElementById('system_display');
    const physical = document.getElementById('physical_count');
    const diff = document.getElementById('difference');
    const submit = document.getElementById('submitBtn');
    let latest = 0;

    function refresh() {
        const sys = parseInt(system.value, 10);
        const phys = parseInt(physical.value, 10);
        submit.disabled = Number.isNaN(sys);

        if (Number.isNaN(sys) || Number.isNaN(phys)) { diff.textContent = ''; return; }

        const d = phys - sys;
        diff.textContent = d === 0
            ? 'No difference. Nothing to adjust.'
            : 'Difference: ' + (d > 0 ? '+' : '') + d + ' (stock will ' + (d > 0 ? 'go up' : 'go down') + ').';
    }

    async function loadQuantity() {
        const ticket = ++latest; // ignore answers that arrive after the person changed product again
        system.value = '';
        display.value = '';
        refresh();
        if (!select.value) { return; }

        display.value = 'Loading...';
        try {
            const response = await fetch(urlTemplate.replace('__ID__', select.value), {
                headers: { 'Accept': 'application/json' }, credentials: 'same-origin',
            });
            if (!response.ok) { throw new Error('http ' + response.status); }
            const payload = await response.json();
            if (ticket !== latest) { return; }
            system.value = payload.data.quantity;
            display.value = payload.data.quantity;
        } catch (error) {
            if (ticket === latest) { display.value = 'Could not load. Reload the page.'; }
        }
        refresh();
    }

    select.addEventListener('change', loadQuantity);
    physical.addEventListener('input', refresh);
    if (select.value) { loadQuantity(); }
})();
</script>
@endpush