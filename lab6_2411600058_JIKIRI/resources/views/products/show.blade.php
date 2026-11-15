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
                    <dt class="col-sm-3">Category</dt><dd class="col-sm-9">{{ $product->category?->name }}</dd>
                    <dt class="col-sm-3">Brand</dt><dd class="col-sm-9">{{ $product->brand?->name ?? 'No brand' }}</dd>
                    <dt class="col-sm-3">Description</dt><dd class="col-sm-9">{{ $product->description ?: 'No description' }}</dd>
                    <dt class="col-sm-3">Quantity</dt><dd class="col-sm-9">{{ $product->quantity }} (reorder at {{ $product->reorder_level }})</dd>
                    <dt class="col-sm-3">Unit Price</dt><dd class="col-sm-9">₱{{ number_format($product->unit_price, 2) }}</dd>
                    <dt class="col-sm-3">Supplier</dt><dd class="col-sm-9">{{ $product->supplier ?: 'No supplier' }}</dd>
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

        @include('products._history')
        
    </div>

    <div class="col-md-4">
        <div class="card chart-card">
            <div class="card-body d-flex flex-column gap-2">
                <a href="{{ route('products.edit', $product) }}" class="btn btn-primary">Edit</a>
                <form method="POST" action="{{ route('products.destroy', $product) }}"
                      data-confirm="Delete {{ $product->name }}?" onsubmit="return confirm(this.dataset.confirm);">
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
