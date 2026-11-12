@extends('layouts.app')

@section('title', 'Edit Product')
@section('page-title', 'Edit Product')

@section('content')
<div class="card chart-card mb-4">
    <div class="card-body">
        <form method="POST" action="{{ route('products.update', $product) }}">
            @csrf
            @method('PUT')
            @include('products._form')
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Update Product</button>
                <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<div class="card chart-card mb-4" id="adjust-stock">
    <div class="card-body">
        <h5 class="mb-1">Adjust Stock</h5>
        <p class="text-muted small mb-3">Currently {{ $product->quantity }} in stock. Each adjustment is saved in the stock history with your name.</p>

        <form method="POST" action="{{ route('products.adjust-stock', $product) }}">
            @csrf

            <div class="row g-3">
                <div class="col-md-3">
                    <span class="form-label d-block" id="adjust_type_label">Type</span>
                    <div role="radiogroup" aria-labelledby="adjust_type_label">
                        <div class="form-check">
                            <input class="form-check-input @error('type') is-invalid @enderror" type="radio" name="type" id="adjust_in" value="stock_in" {{ old('type', 'stock_in') === 'stock_in' ? 'checked' : '' }}>
                            <label class="form-check-label" for="adjust_in">Stock In (add units)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input @error('type') is-invalid @enderror" type="radio" name="type" id="adjust_out" value="stock_out" {{ old('type') === 'stock_out' ? 'checked' : '' }}>
                            <label class="form-check-label" for="adjust_out">Stock Out (remove units)</label>
                        </div>
                    </div>
                    @error('type') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-3">
                    <label for="adjust_quantity" class="form-label">Quantity</label>
                    <input type="number" min="1" id="adjust_quantity" name="quantity" value="{{ old('quantity') }}"
                           class="form-control @error('quantity') is-invalid @enderror" required>
                    @error('quantity') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label for="adjust_reference" class="form-label">Reference or reason</label>
                    <input type="text" id="adjust_reference" name="reference" value="{{ old('reference') }}"
                           class="form-control @error('reference') is-invalid @enderror" maxlength="255"
                           placeholder="e.g. Delivery INV-1042, spoilage, kitchen use">
                    @error('reference') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
            </div>

            <button type="submit" class="btn btn-primary mt-3">Save Adjustment</button>
        </form>
    </div>
</div>

<div class="card chart-card">
    <div class="card-body">
        <h6 class="mb-3">Latest Stock Movements</h6>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>Date</th><th>Type</th><th>Qty</th><th>Reference</th><th>By</th></tr></thead>
                <tbody>
                    @forelse ($transactions as $t)
                        <tr>
                            <td>{{ $t->created_at->format('Y-m-d H:i') }}</td>
                            <td>{{ $t->type === 'stock_in' ? 'Stock In' : 'Stock Out' }}</td>
                            <td>{{ $t->quantity }}</td>
                            <td>{{ $t->reference }}</td>
                            <td>{{ $t->user?->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No stock movements recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
