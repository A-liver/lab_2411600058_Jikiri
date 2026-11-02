@extends('layouts.app')

@section('title', 'Purchase Orders')
@section('page-title', 'Purchase Orders')

@section('content')

@can('create', \App\Models\PurchaseOrder::class)
    <div class="d-flex justify-content-end gap-2 mb-3">
        <form method="POST" action="{{ route('purchase-orders.reorder-check') }}"
              onsubmit="return confirm('Run the reorder check now? Draft purchase orders will be created for low-stock products.');">
            @csrf
            <button type="submit" class="btn btn-outline-warning">Run Reorder Check</button>
        </form>
        <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary">+ New Purchase Order</a>
        <x-export-buttons dataset="purchase-orders" />
    </div>
@endcan

<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('purchase-orders.index') }}">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="search" class="form-label">Reference or supplier</label>
                    <input type="text" id="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="PO-2026... or name">
                </div>
                <x-filters.select name="status" label="Status" :options="$statuses" :selected="request('status')" />
                <x-filters.select name="supplier_id" label="Supplier" :options="$suppliers" :selected="request('supplier_id')" col-class="col-md-3" />
                <x-filters.select name="auto" label="Origin" :options="['1' => 'Auto-generated', '0' => 'Manual']" :selected="request('auto')" />
                <x-filters.date-range :from="request('from')" :to="request('to')" />
                <div class="col-md-2">
                    <label for="min_total" class="form-label">Total from ($)</label>
                    <input type="number" id="min_total" name="min_total" min="0" step="0.01" class="form-control" value="{{ request('min_total') }}">
                </div>
                <div class="col-md-2">
                    <label for="max_total" class="form-label">Total to ($)</label>
                    <input type="number" id="max_total" name="max_total" min="0" step="0.01" class="form-control" value="{{ request('max_total') }}">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Apply</button>
                    <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary flex-fill">Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card chart-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Supplier</th>
                    <th>Order Date</th>
                    <th>Expected</th>
                    <th>Status</th>
                    <th class="text-end">Total</th>
                    <th>Created By</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td>
                            <a href="{{ route('purchase-orders.show', $order) }}">{{ $order->reference_number }}</a>
                            @if ($order->auto_generated)<span class="badge bg-light text-dark border ms-1">Auto</span>@endif
                        </td>
                        <td>{{ $order->supplier->name }}</td>
                        <td>{{ $order->order_date->format('Y-m-d') }}</td>
                        <td>{{ $order->expected_delivery_date?->format('Y-m-d') ?? '—' }}</td>
                        <td>
                            <span class="badge bg-{{ $order->status_color }} {{ $order->status_color === 'warning' ? 'text-dark' : '' }}">{{ $order->status_label }}</span>
                        </td>
                        <td class="text-end">${{ number_format($order->total, 2) }}</td>
                        <td>{{ $order->createdBy->name ?? 'System' }}</td>
                        <td class="text-end">
                            <a href="{{ route('purchase-orders.show', $order) }}" class="btn btn-sm btn-outline-primary">View</a>
                            @can('update', $order)
                                <a href="{{ route('purchase-orders.edit', $order) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No purchase orders found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $orders->links() }}</div>

@endsection