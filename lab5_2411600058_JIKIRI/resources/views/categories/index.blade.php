@extends('layouts.app')

@section('title', 'Categories')
@section('page-title', 'Categories')

@section('content')

<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('categories.index') }}" class="row g-3 align-items-end">
            <div class="col-md-8">
                <label for="categorySearch" class="form-label">Search by name</label>
                <input type="text" id="categorySearch" name="search" class="form-control"
                       value="{{ request('search') }}" placeholder="e.g. Seafood">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">Search</button>
                <a href="{{ route('categories.index') }}" class="btn btn-outline-secondary flex-fill">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card chart-card mb-3">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div class="text-muted">{{ $categories->total() }} {{ \Illuminate\Support\Str::plural('category', $categories->total()) }}</div>
        <a href="{{ route('categories.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Add Category
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Categories List</h5></div>
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
                    @forelse ($categories as $category)
                        <tr>
                            <td>{{ $category->name }}</td>
                            <td>{{ $category->description }}</td>
                            <td class="text-end">{{ $category->products_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('products.index', ['category' => $category->id]) }}" class="btn btn-sm btn-outline-secondary" title="View products" aria-label="View products in {{ $category->name }}"><i class="bi bi-eye" aria-hidden="true"></i></a>
                                <a href="{{ route('categories.edit', $category) }}" class="btn btn-sm btn-outline-primary" title="Edit" aria-label="Edit {{ $category->name }}"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                                <form method="POST" action="{{ route('categories.destroy', $category) }}" class="d-inline"
                                      data-confirm="Delete {{ $category->name }}?" onsubmit="return confirm(this.dataset.confirm);">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete" aria-label="Delete {{ $category->name }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                @if (request()->filled('search'))
                                    No categories match "{{ request('search') }}".
                                @else
                                    No categories yet. <a href="{{ route('categories.create') }}">Add the first category</a>.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $categories->links() }}</div>

@endsection
