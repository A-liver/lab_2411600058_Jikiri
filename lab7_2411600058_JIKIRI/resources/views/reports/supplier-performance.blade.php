@extends('layouts.app')

@section('title', 'Supplier Performance')
@section('page-title', 'Supplier Performance Report')

@section('content')
@php
    $tabs = [
        'movement' => ['label' => 'Stock Movement', 'url' => route('reports.movement')],
        'low-stock' => ['label' => 'Low Stock', 'url' => route('reports.lowStock')],
        'valuation' => ['label' => 'Inventory Valuation', 'url' => route('reports.valuation')],
        'suppliers' => ['label' => 'Supplier Performance', 'url' => route('reports.suppliers')],
    ];
    $totalDated = $rows->sum('dated');
    $overallOnTime = $totalDated > 0 ? round($rows->sum('on_time') / $totalDated * 100, 1) : null;
@endphp

<ul class="nav nav-pills mb-3">
    @foreach ($tabs as $key => $tab)
        <li class="nav-item"><a class="nav-link {{ $key === 'suppliers' ? 'active' : '' }}" href="{{ $tab['url'] }}">{{ $tab['label'] }}</a></li>
    @endforeach
</ul>

<form method="GET" class="row g-2 align-items-end mb-3">
    <x-filters.date-range :from="$from" :to="$to" col-class="col-auto" />
    <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">Generate</button></div>
    <div class="col-auto"><a href="{{ route('reports.suppliers') }}" class="btn btn-sm btn-outline-secondary">Reset</a></div>
    <div class="col-auto"><x-export-buttons dataset="supplier-performance" /></div>
    <div class="col-12"><small class="text-muted">Filters by order date. Only approved, sent and received orders count as placed.</small></div>
</form>

<div class="mb-3">
    <span class="badge bg-light text-dark border me-2 p-2">Suppliers: <strong>{{ $rows->count() }}</strong></span>
    <span class="badge bg-light text-dark border me-2 p-2">Orders placed: <strong>{{ $rows->sum('orders_placed') }}</strong></span>
    <span class="badge bg-light text-dark border me-2 p-2">Delivered: <strong>{{ $rows->sum('delivered') }}</strong></span>
    <span class="badge bg-light text-dark border me-2 p-2">Overall on-time: <strong>{{ $overallOnTime !== null ? $overallOnTime . '%' : 'n/a' }}</strong></span>
    <span class="badge bg-light text-dark border me-2 p-2">Total value: <strong>${{ number_format($rows->sum('total_value'), 2) }}</strong></span>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header fw-bold text-success">Top Performers</div>
            <ul class="list-group list-group-flush">
                @forelse ($rankings['top'] as $r)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <a href="{{ route('suppliers.show', $r['supplier_id']) }}">{{ $r['name'] }}</a>
                        <span><span class="badge bg-{{ $r['rating']['color'] }}">{{ $r['rating']['label'] }}</span> {{ $r['rating']['score'] }}%</span>
                    </li>
                @empty
                    <li class="list-group-item text-muted">No completed deliveries yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header fw-bold text-danger">Needs Attention</div>
            <ul class="list-group list-group-flush">
                @forelse ($rankings['bottom'] as $r)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <a href="{{ route('suppliers.show', $r['supplier_id']) }}">{{ $r['name'] }}</a>
                        <span><span class="badge bg-{{ $r['rating']['color'] }}">{{ $r['rating']['label'] }}</span> {{ $r['rating']['score'] }}%</span>
                    </li>
                @empty
                    <li class="list-group-item text-muted">Nothing to flag (or too few suppliers to compare).</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6"><div class="card"><div class="card-body"><canvas id="trendChart" height="220"></canvas></div></div></div>
    <div class="col-lg-6"><div class="card"><div class="card-body"><canvas id="supplierChart" height="220"></canvas></div></div></div>
</div>

<div class="card chart-card">
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Supplier</th>
                    <th class="text-end">Orders</th>
                    <th class="text-end">Delivered</th>
                    <th class="text-end">On-time</th>
                    <th class="text-end">Avg actual</th>
                    <th class="text-end">Avg quoted</th>
                    <th class="text-end">Variance</th>
                    <th class="text-end">Accuracy</th>
                    <th class="text-end">Total value</th>
                    <th>Rating</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td>
                            <a href="{{ route('suppliers.show', $r['supplier_id']) }}">{{ $r['name'] }}</a>
                            @if ($r['deleted'])<span class="badge bg-dark">Deleted</span>@endif
                        </td>
                        <td class="text-end">{{ $r['orders_placed'] }}</td>
                        <td class="text-end">{{ $r['delivered'] }}</td>
                        <td class="text-end">{{ $r['on_time_pct'] !== null ? $r['on_time_pct'] . '%' : '—' }}</td>
                        <td class="text-end">{{ $r['avg_actual_days'] !== null ? $r['avg_actual_days'] . ' d' : '—' }}</td>
                        <td class="text-end">{{ $r['avg_quoted_days'] !== null ? $r['avg_quoted_days'] . ' d' : '—' }}</td>
                        <td class="text-end">
                            @if ($r['variance_days'] === null)
                                —
                            @else
                                <span class="{{ $r['variance_days'] > 0 ? 'text-danger' : 'text-success' }}">
                                    {{ $r['variance_days'] > 0 ? '+' : '' }}{{ $r['variance_days'] }} d
                                </span>
                            @endif
                        </td>
                        <td class="text-end">{{ $r['accuracy_pct'] !== null ? $r['accuracy_pct'] . '%' : '—' }}</td>
                        <td class="text-end">${{ number_format($r['total_value'], 2) }}</td>
                        <td><span class="badge bg-{{ $r['rating']['color'] }}">{{ $r['rating']['label'] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted py-4">No orders in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<p class="small text-muted mt-2">
    On-time = received on or before the expected date. Variance = average actual delivery days minus quoted days (positive = slower than quoted).
    Accuracy = share of received orders that arrived complete in a single delivery.
</p>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    new Chart(document.getElementById('trendChart'), {
        type: 'bar',
        data: {
            labels: @json($trend->pluck('label')->values()),
            datasets: [
                { label: 'On time', data: @json($trend->pluck('on_time')->values()), backgroundColor: '#28a745' },
                { label: 'Late', data: @json($trend->pluck('late')->values()), backgroundColor: '#dc3545' },
            ],
        },
        options: {
            responsive: true,
            plugins: { title: { display: true, text: 'Deliveries per month (last 6 months)' } },
            scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } },
        },
    });

    new Chart(document.getElementById('supplierChart'), {
        type: 'bar',
        data: {
            labels: @json($rows->pluck('name')->values()),
            datasets: [{ label: 'On-time %', data: @json($rows->map(fn ($r) => $r['on_time_pct'] ?? 0)->values()), backgroundColor: '#606C38' }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: { title: { display: true, text: 'On-time delivery by supplier' }, legend: { display: false } },
            scales: { x: { beginAtZero: true, max: 100 } },
        },
    });
</script>
@endpush