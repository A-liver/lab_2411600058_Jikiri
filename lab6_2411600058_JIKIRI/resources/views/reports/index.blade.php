@extends('layouts.app')

@section('title', 'Reports')
@section('page-title', 'Reports')

@section('content')

<ul class="nav nav-pills report-tabs mb-4" aria-label="Report type">
    @foreach ($reports as $key => $label)
        <li class="nav-item">
            <a class="nav-link {{ $report === $key ? 'active' : '' }}"
               href="{{ route('reports.index', ['report' => $key]) }}"
               @if ($report === $key) aria-current="page" @endif>{{ $label }}</a>
        </li>
    @endforeach
</ul>

@if ($report === 'movement')
    <div class="card filter-card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.index') }}" class="row g-3 align-items-end">
                <input type="hidden" name="report" value="movement">
                <div class="col-md-3">
                    <label for="from" class="form-label">From</label>
                    <input type="date" id="from" name="from" value="{{ $from }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label for="to" class="form-label">To</label>
                    <input type="date" id="to" name="to" value="{{ $to }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary report-submit w-100">Run report</button>
                </div>
            </form>
            @if ($errors->has('to'))
                <p class="text-danger small mt-2 mb-0">{{ $errors->first('to') }}</p>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Movements</div><div class="fs-4">{{ number_format($summary->movements) }}</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Units in</div><div class="fs-4">{{ number_format($summary->units_in) }}</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Units out</div><div class="fs-4">{{ number_format($summary->units_out) }}</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Net adjustments</div><div class="fs-4">{{ sprintf('%+d', $summary->net_adjustment) }}</div></div></div></div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Movements from {{ $from }} to {{ $to }}</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr><th>Date</th><th>Product</th><th>Type</th><th class="text-end">Change</th><th class="text-end">Balance</th><th>Reference</th><th>By</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $t)
                            <tr>
                                <td>{{ $t->created_at->format('Y-m-d H:i') }}</td>
                                <td>{{ $t->product->name }}</td>
                                <td><span class="badge text-bg-{{ $t->type_color }}">{{ $t->type_label }}</span></td>
                                <td class="text-end">{{ sprintf('%+d', $t->delta()) }}</td>
                                <td class="text-end">{{ $t->balance_after ?? '-' }}</td>
                                <td>{{ $t->reference ?? '-' }}</td>
                                <td>{{ $t->user?->name ?? 'System' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No movements in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $rows->links() }}
        </div>
    </div>
@endif

@if ($report === 'low-stock')
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">At or below reorder level ({{ $products->count() }})</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr><th>Product</th><th>Category</th><th class="text-end">On hand</th><th class="text-end">Reorder at</th><th class="text-end">To reach level</th><th>Supplier</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $p)
                            <tr>
                                <td><a href="{{ route('products.show', $p) }}">{{ $p->name }}</a></td>
                                <td>{{ $p->category?->name ?? '-' }}</td>
                                <td class="text-end">{{ $p->quantity }}</td>
                                <td class="text-end">{{ $p->reorder_level }}</td>
                                <td class="text-end">{{ max(0, $p->reorder_level - $p->quantity) }}</td>
                                <td>{{ $p->supplier ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Every product is above its reorder level.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif

@if ($report === 'valuation')
    <div class="row g-3 mb-4">
        <div class="col-6"><div class="card h-100"><div class="card-body"><div class="text-muted small">Total inventory value</div><div class="fs-4">₱{{ number_format($totalValue, 2) }}</div></div></div></div>
        <div class="col-6"><div class="card h-100"><div class="card-body"><div class="text-muted small">Units on hand</div><div class="fs-4">{{ number_format($totalUnits) }}</div></div></div></div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Value by product</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr><th>Product</th><th>Category</th><th class="text-end">Quantity</th><th class="text-end">Unit price</th><th class="text-end">Value</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $p)
                            <tr>
                                <td><a href="{{ route('products.show', $p) }}">{{ $p->name }}</a></td>
                                <td>{{ $p->category?->name ?? '-' }}</td>
                                <td class="text-end">{{ $p->quantity }}</td>
                                <td class="text-end">₱{{ number_format($p->unit_price, 2) }}</td>
                                <td class="text-end">₱{{ number_format($p->quantity * $p->unit_price, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No products yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $products->links() }}
        </div>
    </div>
@endif

@endsection