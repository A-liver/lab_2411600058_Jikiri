@extends('layouts.app')

@section('title', 'Record Movement')
@section('page-title', 'Record Stock Movement')

@section('content')

<div class="card mb-4">
    <div class="card-body">
        <form method="POST" action="{{ route('transactions.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="product_id" class="form-label">Product</label>
                    <select id="product_id" name="product_id" class="form-select @error('product_id') is-invalid @enderror" required>
                        <option value="">Choose a product</option>
                        @foreach ($products as $p)
                            <option value="{{ $p->id }}" data-stock="{{ $p->quantity }}"
                                @selected((int) old('product_id', request('product_id')) === $p->id)>
                                {{ $p->name }} ({{ $p->sku }})
                            </option>
                        @endforeach
                    </select>
                    @error('product_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <span class="form-label d-block" id="type_label">Type</span>
                    <div role="radiogroup" aria-labelledby="type_label">
                        @foreach ($types as $type)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="type" id="type_{{ $type }}"
                                       value="{{ $type }}" @checked(old('type', 'stock_in') === $type)>
                                <label class="form-check-label" for="type_{{ $type }}">{{ ucwords(str_replace('_', ' ', $type)) }}</label>
                            </div>
                        @endforeach
                    </div>
                    @error('type') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-4">
                    <label for="quantity" class="form-label">Quantity</label>
                    <input type="number" id="quantity" name="quantity" min="1" step="1" value="{{ old('quantity') }}"
                           class="form-control @error('quantity') is-invalid @enderror" required>
                    <div id="stockHint" class="form-text" aria-live="polite"></div>
                    @error('quantity') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-8">
                    <label for="reference" class="form-label">Reference number</label>
                    <input type="text" id="reference" name="reference" maxlength="255" value="{{ old('reference') }}"
                           class="form-control @error('reference') is-invalid @enderror" placeholder="e.g. PO-1042 or SO-2210">
                    @error('reference') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea id="notes" name="notes" rows="3" maxlength="1000"
                              class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                    @error('notes') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Record Movement</button>
                <a href="{{ route('transactions.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    const product = document.getElementById('product_id');
    const qty = document.getElementById('quantity');
    const hint = document.getElementById('stockHint');

    function refresh() {
        const opt = product.options[product.selectedIndex];
        const stock = opt && opt.dataset.stock !== undefined ? parseInt(opt.dataset.stock, 10) : NaN;
        const type = document.querySelector('input[name="type"]:checked')?.value;

        if (Number.isNaN(stock)) {
            hint.textContent = '';
            qty.removeAttribute('max');
            return;
        }

        hint.textContent = 'Current stock: ' + stock;

        if (type === 'stock_out') {
            qty.max = stock;
            hint.textContent += ' (maximum you can remove)';
        } else {
            qty.removeAttribute('max');
        }
    }

    product.addEventListener('change', refresh);
    document.querySelectorAll('input[name="type"]').forEach(r => r.addEventListener('change', refresh));
    refresh();
})();
</script>
@endpush