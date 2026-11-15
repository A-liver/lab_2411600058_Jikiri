@extends('layouts.app')

@section('title', 'Brands')
@section('page-title', 'Brands')

@section('content')

<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('brands.index') }}" class="row g-3 align-items-end">
            <div class="col-md-8">
                <label for="brandSearch" class="form-label">Search by name</label>
                <input type="text" id="brandSearch" name="search" class="form-control"
                       value="{{ request('search') }}" placeholder="e.g. Farmhouse">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">Search</button>
                <a href="{{ route('brands.index') }}" class="btn btn-outline-secondary flex-fill">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card chart-card mb-3">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div class="text-muted">{{ $brands->total() }} {{ \Illuminate\Support\Str::plural('brand', $brands->total()) }}</div>
        <a href="{{ route('brands.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Add Brand
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Brands List</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th class="text-end">Products</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($brands as $brand)
                        <tr>
                            <td>{{ $brand->name }}</td>
                            <td>{{ $brand->description }}</td>
                            <td class="text-end">{{ $brand->products_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('products.index', ['brand' => $brand->id]) }}" class="btn btn-sm btn-outline-secondary" title="View products" aria-label="View products in {{ $brand->name }}"><i class="bi bi-eye" aria-hidden="true"></i></a>
                                <a href="{{ route('brands.edit', $brand) }}" class="btn btn-sm btn-outline-primary" title="Edit" aria-label="Edit {{ $brand->name }}"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                                <form method="POST" action="{{ route('brands.destroy', $brand) }}" class="d-inline"
                                      data-confirm="Delete {{ $brand->name }}?" onsubmit="return confirm(this.dataset.confirm);">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete" aria-label="Delete {{ $brand->name }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                @if (request()->filled('search'))
                                    No brands match "{{ request('search') }}".
                                @else
                                    No brands yet. <a href="{{ route('brands.create') }}">Add the first brand</a>.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $brands->links() }}</div>

@endsection
