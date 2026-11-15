@extends('layouts.app')

@section('title', 'Transactions')
@section('page-title', 'Stock Transactions')

@section('content')

<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('transactions.index') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="product_id" class="form-label">Product</label>
                <select id="product_id" name="product_id" class="form-select">
                    <option value="">All products</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected((int) request('product_id') === $p->id)>{{ $p->name }} ({{ $p->sku }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="type" class="form-label">Type</label>
                <select id="type" name="type" class="form-select">
                    <option value="">All types</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(request('type') === $type)>{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="from" class="form-label">From</label>
                <input type="date" id="from" name="from" class="form-control" value="{{ request('from') }}">
            </div>
            <div class="col-md-2">
                <label for="to" class="form-label">To</label>
                <input type="date" id="to" name="to" class="form-control" value="{{ request('to') }}">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">Filter</button>
                <a href="{{ route('transactions.index') }}" class="btn btn-outline-secondary flex-fill">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Movements <span class="text-muted small">({{ $transactions->total() }})</span></h5>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('transactions.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Record Movement
            </a>
            @can('manage-inventory')
                <a href="{{ route('adjustments.create') }}" class="btn btn-outline-primary">Stock Count</a>
            @endcan
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th class="text-end">Quantity</th>
                        <th class="text-end">Balance</th>
                        <th>Reference</th>
                        <th>User</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $t)
                        <tr>
                            <td>{{ $t->created_at->format('Y-m-d H:i') }}</td>
                            <td>
                                @if ($t->product->trashed())
                                    {{ $t->product->name }} <span class="text-muted small">(deleted)</span>
                                @else
                                    <a href="{{ route('products.show', $t->product) }}">{{ $t->product->name }}</a>
                                @endif
                            </td>
                            <td><span class="badge text-bg-{{ $t->type_color }}">{{ $t->type_label }}</span></td>
                            <td class="text-end">{{ sprintf('%+d', $t->delta()) }}</td>
                            <td class="text-end">{{ $t->balance_after ?? '-' }}</td>
                            <td>{{ $t->reference ?? '-' }}</td>
                            <td>{{ $t->user?->name ?? 'System' }}</td>
                            <td class="text-end">
                                <a href="{{ route('transactions.show', $t) }}" class="btn btn-sm btn-outline-primary" title="View" aria-label="View transaction {{ $t->id }}"><i class="bi bi-eye" aria-hidden="true"></i></a>
                                @can('manage-inventory')
                                    <form method="POST" action="{{ route('transactions.destroy', $t) }}" class="d-inline"
                                          data-confirm="Reverse transaction #{{ $t->id }}? A new opposite entry will be recorded."
                                          onsubmit="return confirm(this.dataset.confirm);">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Reverse" aria-label="Reverse transaction {{ $t->id }}"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No transactions match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $transactions->links() }}
    </div>
</div>

@endsection