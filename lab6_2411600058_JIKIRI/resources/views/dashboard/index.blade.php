@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted small" id="lastUpdated"></span>
    <span class="text-danger small" id="pollError" role="status" hidden></span>
</div>

<div class="row mb-4">
    <div class="col-6 col-md-3 mb-3">
        <a class="stat-link h-100" href="{{ route('products.index') }}">
            <div class="card stat-card text-center h-100">
                <div class="card-body py-3">
                    <h6 class="card-title text-muted">Total Products</h6>
                    <p class="stat-figure text-primary" id="invTotalProducts">{{ $totalProducts }}</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <a class="stat-link h-100" href="{{ route('reports.index', ['report' => 'valuation']) }}">
            <div class="card stat-card text-center h-100">
                <div class="card-body py-3">
                    <h6 class="card-title text-muted">Inventory Value</h6>
                    <p class="stat-figure text-primary" id="invTotalValue">₱{{ number_format($totalValue, 2) }}</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <a class="stat-link h-100" href="{{ route('products.index', ['stock_status' => 'low-stock']) }}">
            <div class="card stat-card text-center h-100 {{ $lowStockCount > 0 ? 'is-low' : '' }}">
                <div class="card-body py-3">
                    <h6 class="card-title">Low Stock</h6>
                    <p class="stat-figure stat-low" id="invLowStock">{{ $lowStockCount }}</p>
                    <span class="stat-hint">View items</span>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <a class="stat-link h-100" href="{{ route('products.index', ['stock_status' => 'out-of-stock']) }}">
            <div class="card stat-card text-center h-100 {{ $outOfStockCount > 0 ? 'is-out' : '' }}">
                <div class="card-body py-3">
                    <h6 class="card-title">Out of Stock</h6>
                    <p class="stat-figure text-danger" id="invOutOfStock">{{ $outOfStockCount }}</p>
                    <span class="stat-hint">View items</span>
                </div>
            </div>
        </a>
    </div>
</div>

<div id="lowStockAlert">
    @if ($attentionTotal > 0)
        <div class="alert alert-warning d-flex">
            <i class="bi bi-exclamation-triangle-fill fs-4 me-3 flex-shrink-0" aria-hidden="true"></i>
            <div>
                <strong>{{ $attentionTotal }} item(s) need attention</strong>
                <ul class="mb-0 mt-2 small">
                    @foreach ($attentionProducts as $p)
                        <li>
                            <a href="{{ route('products.show', $p) }}" class="fw-bold {{ $p->isOutOfStock() ? 'text-danger' : '' }}">{{ $p->name }}</a>:
                            {{ $p->isOutOfStock() ? 'out of stock' : $p->quantity . ' left' }} (reorder at {{ $p->reorder_level }})
                        </li>
                    @endforeach
                    @if ($attentionTotal > $attentionProducts->count())
                        <li><a href="{{ route('products.index') }}">and {{ $attentionTotal - $attentionProducts->count() }} more in Inventory</a></li>
                    @endif
                </ul>
            </div>
        </div>
    @endif
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        @include('dashboard._breakdown', ['title' => 'Stock by Category', 'rows' => $stockByCategory, 'filter' => 'category', 'label' => 'Category'])
    </div>
    <div class="col-lg-6">
        @include('dashboard._breakdown', ['title' => 'Stock by Brand', 'rows' => $stockByBrand, 'filter' => 'brand', 'label' => 'Brand'])
    </div>
</div>


<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Recent Movements</h5>
        <a href="{{ route('transactions.index') }}">View all</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th class="text-end">Change</th>
                        <th class="text-end">Balance</th>
                    </tr>
                </thead>
                <tbody id="recentTransactionsBody">
                    @forelse ($recentTransactions as $t)
                        <tr>
                            <td>{{ $t->created_at->format('Y-m-d H:i') }}</td>
                            <td>{{ $t->product->name }}</td>
                            <td>
                                <span class="badge text-bg-{{ $t->type_color }}">
                                    {{ $t->type_label }}
                                </span>
                            </td>
                            <td class="text-end">{{ sprintf('%+d', $t->delta()) }}</td>
                            <td class="text-end">{{ $t->balance_after ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3">
                                No movements recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
    window.DASHBOARD_POLL = {
        stats: @json(route('api.dashboard.stats', [], false)),
        lowStock: @json(route('api.products.low-stock', [], false)),
        recent: @json(route('api.transactions.recent', [], false)),
        productsUrl: @json(route('products.index', [], false)),
    };
</script>
<script src="{{ asset('JavaScript/dashboard-poll.js') }}"></script>
@endpush

@endsection