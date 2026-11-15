@extends('layouts.app')

@section('title', 'Transaction #' . $transaction->id)
@section('page-title', 'Transaction #' . $transaction->id)

@section('content')

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">Transaction</h5></div>
            <div class="card-body">
                <dl class="mb-0">
                    <dt>Type</dt>
                    <dd><span class="badge text-bg-{{ $transaction->type_color }}">{{ $transaction->type_label }}</span></dd>
                    <dt>Quantity</dt>
                    <dd>{{ sprintf('%+d', $transaction->delta()) }}</dd>
                    <dt>Balance after</dt>
                    <dd>{{ $transaction->balance_after ?? '-' }}</dd>
                    <dt>Reference</dt>
                    <dd>{{ $transaction->reference ?? '-' }}</dd>
                    <dt>Notes</dt>
                    <dd>{{ $transaction->notes ?? '-' }}</dd>
                    <dt>Recorded</dt>
                    <dd class="mb-0">{{ $transaction->created_at->format('Y-m-d H:i:s') }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">Product</h5></div>
            <div class="card-body">
                <dl class="mb-3">
                    <dt>Name</dt>
                    <dd>{{ $transaction->product->name }}</dd>
                    <dt>SKU</dt>
                    <dd>{{ $transaction->product->sku }}</dd>
                    <dt>Category</dt>
                    <dd>{{ $transaction->product->category?->name ?? '-' }}</dd>
                    <dt>Stock now</dt>
                    <dd class="mb-0">{{ $transaction->product->quantity }}</dd>
                </dl>
                @if ($transaction->product->trashed())
                    <span class="text-muted small">This product was deleted.</span>
                @else
                    <a href="{{ route('products.show', $transaction->product) }}" class="btn btn-sm btn-outline-primary">View product</a>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">Recorded by</h5></div>
            <div class="card-body">
                @if ($transaction->user)
                    <dl class="mb-0">
                        <dt>Name</dt>
                        <dd>{{ $transaction->user->name }}</dd>
                        <dt>Email</dt>
                        <dd>{{ $transaction->user->email }}</dd>
                        <dt>Role</dt>
                        <dd class="mb-0">{{ ucfirst($transaction->user->role) }}</dd>
                    </dl>
                @else
                    <p class="text-muted mb-0">System entry (no user account recorded).</p>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="d-flex gap-2">
    <a href="{{ route('transactions.index') }}" class="btn btn-outline-secondary">Back to list</a>
    @can('manage-inventory')
        <form method="POST" action="{{ route('transactions.destroy', $transaction) }}"
              data-confirm="Reverse this transaction? A new opposite entry will be recorded."
              onsubmit="return confirm(this.dataset.confirm);">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger">Reverse transaction</button>
        </form>
    @endcan
</div>

@endsection