@extends('layouts.app')

@section('title', 'Suppliers')
@section('page-title', 'Suppliers')

@section('content')

<div class="d-flex justify-content-end mb-3">
    <a href="{{ route('suppliers.create') }}" class="btn btn-primary">+ Add Supplier</a>
    <a href="{{ route('reports.suppliers') }}" class="btn btn-outline-secondary ms-2">Performance Report</a>
</div>

<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('suppliers.index') }}">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" id="search" name="search" class="form-control"
                           placeholder="Name, contact or email" value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        @foreach (['all' => 'All', 'active' => 'Active', 'inactive' => 'Inactive', 'deleted' => 'Deleted'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status', 'all') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="sort" class="form-label">Sort by</label>
                    <select id="sort" name="sort" class="form-select">
                        @foreach (['name' => 'Name', 'lead_time_days' => 'Lead time', 'created_at' => 'Date added'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('sort', 'name') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="direction" class="form-label">Order</label>
                    <select id="direction" name="direction" class="form-select">
                        <option value="asc" @selected(request('direction', 'asc') === 'asc')>Ascending</option>
                        <option value="desc" @selected(request('direction') === 'desc')>Descending</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Apply</button>
                    <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary flex-fill">Reset</a>
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
                    <th>Supplier</th>
                    <th>Contact</th>
                    <th>Terms</th>
                    <th class="text-end">Lead Time</th>
                    <th class="text-end">Products</th>
                    <th class="text-end">Orders</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($suppliers as $supplier)
                    <tr class="{{ $supplier->trashed() ? 'table-secondary' : '' }}">
                        <td>
                            @if ($supplier->trashed())
                                {{ $supplier->name }}
                            @else
                                <a href="{{ route('suppliers.show', $supplier) }}">{{ $supplier->name }}</a>
                            @endif
                        </td>
                        <td>
                            <div>{{ $supplier->contact_person ?: '—' }}</div>
                            <small class="text-muted">{{ $supplier->email ?: '' }}</small>
                        </td>
                        <td>{{ $supplier->payment_terms }}</td>
                        <td class="text-end">{{ $supplier->lead_time_days }} days</td>
                        <td class="text-end">{{ $supplier->products_count }}</td>
                        <td class="text-end">{{ $supplier->purchase_orders_count }}</td>
                        <td>
                            @if ($supplier->trashed())
                                <span class="badge bg-dark">Deleted</span>
                            @elseif ($supplier->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if ($supplier->trashed())
                                @can('admin')
                                    <form method="POST" action="{{ route('suppliers.restore', $supplier->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success">Restore</button>
                                    </form>
                                @endcan
                            @else
                                <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-sm btn-outline-primary">View</a>
                                <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                @can('admin')
                                    <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" class="d-inline"
                                          onsubmit="return confirm('Delete {{ addslashes($supplier->name) }}? It can be restored later.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No suppliers found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $suppliers->links() }}</div>

@endsection