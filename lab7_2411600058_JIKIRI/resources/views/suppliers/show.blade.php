@extends('layouts.app')

@section('title', $supplier->name)
@section('page-title', $supplier->name)

@section('content')

<div class="d-flex justify-content-end gap-2 mb-3">
    @if (Route::has('purchase-orders.create'))
        <a href="{{ route('purchase-orders.create', ['supplier_id' => $supplier->id]) }}" class="btn btn-primary">+ New Purchase Order</a>
    @endif
    <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-outline-secondary">Edit</a>
    <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary">Back to List</a>
</div>

{{-- Performance summary --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="card stat-card text-center shadow-hover"><div class="card-body">
        <h6 class="text-muted mb-1">Total Orders</h6><h4 class="fw-bold mb-0">{{ $stats['orders'] }}</h4>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card text-center shadow-hover"><div class="card-body">
        <h6 class="text-muted mb-1">Open Orders</h6><h4 class="fw-bold text-warning mb-0">{{ $stats['open'] }}</h4>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card text-center shadow-hover"><div class="card-body">
        <h6 class="text-muted mb-1">Total Received Value</h6><h4 class="fw-bold text-success mb-0">${{ number_format($stats['spent'], 2) }}</h4>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card text-center shadow-hover"><div class="card-body">
        <h6 class="text-muted mb-1">On-Time Delivery</h6>
        <h4 class="fw-bold mb-0">{{ $stats['rating'] !== null ? $stats['rating'] . '%' : 'No deliveries yet' }}</h4>
    </div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card chart-card mb-3">
            <div class="card-header fw-bold">Supplier Details</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">Status</dt>
                    <dd class="col-sm-7">
                        <span class="badge bg-{{ $supplier->is_active ? 'success' : 'secondary' }}">{{ $supplier->is_active ? 'Active' : 'Inactive' }}</span>
                    </dd>
                    <dt class="col-sm-5">Contact Person</dt><dd class="col-sm-7">{{ $supplier->contact_person ?: '—' }}</dd>
                    <dt class="col-sm-5">Email</dt><dd class="col-sm-7">{{ $supplier->email ?: '—' }}</dd>
                    <dt class="col-sm-5">Phone</dt><dd class="col-sm-7">{{ $supplier->phone ?: '—' }}</dd>
                    <dt class="col-sm-5">Address</dt><dd class="col-sm-7">{{ $supplier->address ?: '—' }}</dd>
                    <dt class="col-sm-5">Payment Terms</dt><dd class="col-sm-7">{{ $supplier->payment_terms }}</dd>
                    <dt class="col-sm-5">Lead Time</dt><dd class="col-sm-7">{{ $supplier->lead_time_days }} days</dd>
                    <dt class="col-sm-5">Minimum Order</dt><dd class="col-sm-7">${{ number_format($supplier->minimum_order_amount, 2) }}</dd>
                </dl>
            </div>
        </div>

        <div class="card chart-card">
            <div class="card-header fw-bold">Recent Purchase Orders</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Reference</th><th>Date</th><th>Status</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                        @forelse ($recentOrders as $po)
                            <tr>
                                <td>
                                    @if (Route::has('purchase-orders.show'))
                                        <a href="{{ route('purchase-orders.show', $po) }}">{{ $po->reference_number }}</a>
                                    @else
                                        {{ $po->reference_number }}
                                    @endif
                                </td>
                                <td>{{ $po->order_date->format('Y-m-d') }}</td>
                                <td><span class="badge bg-{{ $po->status_color }}">{{ $po->status_label }}</span></td>
                                <td class="text-end">${{ number_format($po->total, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">No purchase orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card chart-card mb-3">
            <div class="card-header fw-bold">Products Supplied ({{ $supplier->products->count() }})</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Product</th><th>Supplier SKU</th>
                            <th class="text-end">Unit Cost</th><th class="text-end">Min Qty</th>
                            <th class="text-end">Pack</th><th></th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($supplier->products as $product)
                            <tr>
                                <td><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a></td>
                                <td>{{ $product->pivot->supplier_sku ?: '—' }}</td>
                                <td class="text-end">${{ number_format($product->pivot->unit_cost, 2) }}</td>
                                <td class="text-end">{{ $product->pivot->min_order_quantity }}</td>
                                <td class="text-end">{{ $product->pivot->pack_size }}</td>
                                <td>@if ($product->pivot->is_preferred)<span class="badge bg-success">Preferred</span>@endif</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('suppliers.products.detach', [$supplier, $product]) }}"
                                          onsubmit="return confirm('Unlink {{ addslashes($product->name) }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Unlink</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-3">No products linked yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card chart-card">
            <div class="card-header fw-bold">Link a Product</div>
            <div class="card-body">
                <form method="POST" action="{{ route('suppliers.products.attach', $supplier) }}">
                    @csrf
                    <div class="row g-2 align-items-end">
                        <div class="col-md-6">
                            <label for="product_id" class="form-label small mb-0">Product</label>
                            <select id="product_id" name="product_id" class="form-select form-select-sm" required>
                                <option value="">-- Select --</option>
                                @foreach ($availableProducts as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="supplier_sku" class="form-label small mb-0">Supplier SKU</label>
                            <input type="text" id="supplier_sku" name="supplier_sku" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label for="unit_cost" class="form-label small mb-0">Unit Cost ($)</label>
                            <input type="number" id="unit_cost" name="unit_cost" min="0" step="0.01" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-4">
                            <label for="min_order_quantity" class="form-label small mb-0">Min Order Qty</label>
                            <input type="number" id="min_order_quantity" name="min_order_quantity" min="1" value="1" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-4">
                            <label for="pack_size" class="form-label small mb-0">Pack Size</label>
                            <input type="number" id="pack_size" name="pack_size" min="1" value="1" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6">
                            <input type="hidden" name="is_preferred" value="0">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_preferred" name="is_preferred" value="1">
                                <label class="form-check-label small" for="is_preferred">Preferred supplier for this product</label>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <button type="submit" class="btn btn-sm btn-primary">Link Product</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection