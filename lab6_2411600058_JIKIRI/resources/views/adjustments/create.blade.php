@extends('layouts.app')

@section('title', 'Adjust Stock')
@section('page-title', 'Stock Adjustment')

@section('content')
<div class="row">
    <div class="col-lg-7">
        <div class="alert alert-info small">
            Use this when the physical count does not match the system (damaged goods, theft/loss,
            counting errors). The system calculates the difference and records an adjustment.
        </div>

        <div class="card chart-card">
            <div class="card-body">
                <form method="POST" action="{{ route('adjustments.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="product_id" class="form-label">Product</label>
                        <select id="product_id" name="product_id"
                                class="form-select @error('product_id') is-invalid @enderror" required>
                            <option value="">-- Select a product --</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}" @selected(old('product_id', $selectedProduct) == $p->id)>
                                    {{ $p->name }} ({{ $p->sku }})
                                </option>
                            @endforeach
                        </select>
                        @error('product_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="system_quantity" class="form-label">Current System Quantity</label>
                            <input type="text" id="system_quantity" class="form-control" readonly
                                   placeholder="Select a product">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="physical_count" class="form-label">Physical Count</label>
                            <input type="number" id="physical_count" name="physical_count" min="0" step="1"
                                   class="form-control @error('physical_count') is-invalid @enderror"
                                   value="{{ old('physical_count') }}" required>
                            @error('physical_count') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div id="diffPreview" class="form-text mb-3"></div>

                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for Adjustment</label>
                        <select id="reason" name="reason" class="form-select @error('reason') is-invalid @enderror" required>
                            <option value="">-- Select a reason --</option>
                            @foreach ($reasons as $value => $label)
                                <option value="{{ $value }}" @selected(old('reason') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-4">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea id="notes" name="notes" rows="3" maxlength="1000" class="form-control">{{ old('notes') }}</textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Record Adjustment</button>
                        <a href="{{ route('transactions.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const PRODUCT_API = @json(url('/api/products'));

    const productEl  = document.getElementById('product_id');
    const systemEl   = document.getElementById('system_quantity');
    const physicalEl = document.getElementById('physical_count');
    const diffEl     = document.getElementById('diffPreview');

    // Current system quantity is loaded via AJAX from the API built in step 5.
    async function loadSystemQuantity() {
        const id = productEl.value;

        if (!id) {
            systemEl.value = '';
            delete systemEl.dataset.value;
            updateDifference();
            return;
        }

        systemEl.value = 'Loading...';
        try {
            const res = await fetch(`${PRODUCT_API}/${id}`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);

            const json = await res.json();
            systemEl.value = json.data.quantity;
            systemEl.dataset.value = json.data.quantity;
        } catch (err) {
            console.error(err);
            systemEl.value = 'Could not load';
            delete systemEl.dataset.value;
        }
        updateDifference();
    }

    // Live preview only; the server recalculates the real difference on submit.
    function updateDifference() {
        const system   = parseInt(systemEl.dataset.value, 10);
        const physical = parseInt(physicalEl.value, 10);

        if (Number.isNaN(system) || Number.isNaN(physical)) {
            diffEl.textContent = '';
            return;
        }

        const diff = physical - system;
        diffEl.className = 'form-text mb-3 fw-bold ' +
            (diff < 0 ? 'text-danger' : diff > 0 ? 'text-success' : 'text-muted');
        diffEl.textContent = diff === 0
            ? 'No difference, nothing to adjust.'
            : `Difference: ${diff > 0 ? '+' : ''}${diff} (stock will ${diff > 0 ? 'increase' : 'decrease'})`;
    }

    productEl.addEventListener('change', loadSystemQuantity);
    physicalEl.addEventListener('input', updateDifference);

    if (productEl.value) loadSystemQuantity();   // pre-selected or old() value
</script>
@endpush