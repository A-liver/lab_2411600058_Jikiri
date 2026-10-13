@extends('layouts.app')

@section('title', $product->name)
@section('page-title', $product->name)

@section('content')
@php $status = $product->stock_status; @endphp

<div class="row g-3">
    <div class="col-md-8">
        <div class="card chart-card">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">SKU</dt><dd class="col-sm-9">{{ $product->sku }}</dd>
                    <dt class="col-sm-3">Category</dt><dd class="col-sm-9">{{ $product->category }}</dd>
                    <dt class="col-sm-3">Description</dt><dd class="col-sm-9">{{ $product->description ?: '—' }}</dd>
                    <dt class="col-sm-3">Quantity</dt><dd class="col-sm-9">{{ $product->quantity }} (reorder at {{ $product->reorder_level }})</dd>
                    <dt class="col-sm-3">Unit Price</dt><dd class="col-sm-9">${{ number_format($product->unit_price, 2) }}</dd>
                    <dt class="col-sm-3">Supplier</dt><dd class="col-sm-9">{{ $product->supplier ?: '—' }}</dd>
                    <dt class="col-sm-3">Status</dt>
                    <dd class="col-sm-9">
                        @if ($status === 'in-stock') <span class="badge bg-success">In Stock</span>
                        @elseif ($status === 'low-stock') <span class="badge bg-warning text-dark">Low Stock</span>
                        @else <span class="badge bg-danger">Out of Stock</span>
                        @endif
                    </dd>
                </dl>
            </div>
        </div>

        @if (isset($transactions) && $transactions->isNotEmpty())
        <div class="card chart-card mt-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Transaction History</h6>
                    <div class="d-flex gap-2">
                        <a href="{{ route('transactions.create', ['product_id' => $product->id]) }}" class="btn btn-sm btn-primary">+ Record Movement</a>
                        @can('manage-stock')
                            <a href="{{ route('adjustments.create', ['product_id' => $product->id]) }}" class="btn btn-sm btn-outline-warning">Adjust Stock</a>
                        @endcan
                    </div>
                </div>

                <form method="GET" action="{{ route('products.show', $product) }}" class="row g-2 align-items-end mb-3">
                    <div class="col-auto">
                        <label for="from" class="form-label small mb-0">From</label>
                        <input type="date" id="from" name="from" class="form-control form-control-sm" value="{{ request('from') }}">
                    </div>
                    <div class="col-auto">
                        <label for="to" class="form-label small mb-0">To</label>
                        <input type="date" id="to" name="to" class="form-control form-control-sm" value="{{ request('to') }}">
                    </div>
                    <div class="col-auto d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                        <a href="{{ route('products.show', $product) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Balance</th>
                                <th>Reference</th>
                                <th>User</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($transactions as $t)
                                @php $signed = $t->signed_quantity; @endphp
                                <tr>
                                    <td><a href="{{ route('transactions.show', $t) }}">{{ $t->created_at->format('Y-m-d H:i') }}</a></td>
                                    <td>
                                        <span class="badge bg-{{ $t->type_color }} {{ $t->type_color === 'warning' ? 'text-dark' : '' }}">{{ $t->type_label }}</span>
                                    </td>
                                    <td class="text-end fw-bold {{ $signed < 0 ? 'text-danger' : 'text-success' }}">
                                        {{ $signed > 0 ? '+' : '' }}{{ $signed }}
                                    </td>
                                    <td class="text-end">{{ $t->balance_after ?? '—' }}</td>
                                    <td>{{ $t->reference_number ?: '—' }}</td>
                                    <td>{{ $t->user->name ?? 'System' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-3">No transactions for this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">{{ $transactions->links() }}</div>
            </div>
        </div>
        @endif
    </div>

    <div class="col-md-4">
        <div class="card chart-card">
            <div class="card-body d-flex flex-column gap-2">
                <a href="{{ route('products.edit', $product) }}" class="btn btn-primary">Edit</a>
                <form method="POST" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('Delete {{ $product->name }}?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger w-100">Delete</button>
                </form>
                <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Back to Inventory</a>
            </div>
        </div>
    </div>
</div>
@endsection