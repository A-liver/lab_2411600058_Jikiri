@extends('layouts.app')

@section('title', $order->reference_number)
@section('page-title', $order->reference_number)

@section('content')

@if ($order->isAwaitingFinalApproval())
    <div class="alert alert-info">
        <strong>Awaiting final approval.</strong> This order's total (${{ number_format($order->total, 2) }}) is at or above
        ${{ number_format(config('procurement.approval_threshold'), 0) }}, so it needs an admin's approval after the first-level approval.
    </div>
@endif

@if ($order->status === 'rejected' && $order->rejection_reason)
    <div class="alert alert-danger"><strong>Rejected:</strong> {{ $order->rejection_reason }}</div>
@endif

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <span class="badge fs-6 bg-{{ $order->status_color }} {{ $order->status_color === 'warning' ? 'text-dark' : '' }}">{{ $order->status_label }}</span>
        @if ($order->auto_generated)
        <span class="badge bg-light text-dark border">Auto-generated</span>
        @endif
        @if ($order->status === 'pending')
            <span class="badge bg-light text-dark border">Approval level {{ $order->approvedLevel() }} of {{ $order->requiredApprovalLevels() }}</span>
        @endif
        @if ($order->isPartiallyReceived())
            <span class="badge bg-info">Partially received</span>
        @endif
    </div>

    <div class="d-flex flex-wrap gap-2">
        @can('submit', $order)
            <form method="POST" action="{{ route('purchase-orders.submit', $order) }}">
                @csrf
                <button type="submit" class="btn btn-primary">Submit for Approval</button>
            </form>
        @endcan

        @can('approve', $order)
            <form method="POST" action="{{ route('purchase-orders.approve', $order) }}" class="d-flex gap-1">
                @csrf
                <input type="text" name="comment" maxlength="500" placeholder="Comment (optional)"
                       class="form-control form-control-sm" style="width: 180px;">
                <button type="submit" class="btn btn-success">Approve</button>
            </form>
        @endcan

        @can('reject', $order)
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#rejectBox">Reject</button>
        @endcan

        @can('send', $order)
            <form method="POST" action="{{ route('purchase-orders.send', $order) }}"
                  onsubmit="return confirm('Mark this order as sent to {{ addslashes($order->supplier->name) }}?');">
                @csrf
                <button type="submit" class="btn btn-primary">Mark as Sent</button>
            </form>
        @endcan

        @can('reopen', $order)
            <form method="POST" action="{{ route('purchase-orders.reopen', $order) }}">
                @csrf
                <button type="submit" class="btn btn-outline-primary">Reopen as Draft</button>
            </form>
        @endcan

        @can('receive', $order)
            <a href="{{ route('purchase-orders.receive', $order) }}" class="btn btn-success">Receive Goods</a>
        @endcan

        @can('update', $order)
            <a href="{{ route('purchase-orders.edit', $order) }}" class="btn btn-outline-primary">Edit</a>
        @endcan

        @if (auth()->user()->can('delete', $order) || auth()->user()->can('cancel', $order))
            @php $deleting = $order->status === 'draft' && auth()->user()->can('delete', $order); @endphp
            <form method="POST" action="{{ route('purchase-orders.destroy', $order) }}"
                  onsubmit="return confirm('{{ $deleting ? 'Delete this draft?' : 'Cancel this purchase order?' }}');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger">{{ $deleting ? 'Delete Draft' : 'Cancel Order' }}</button>
            </form>
        @endif
        @can('view', $order)
            <a href="{{ route('purchase-orders.pdf', $order) }}" class="btn btn-outline-secondary">Download PDF</a>
        @endcan
        <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary">Back to List</a>
    </div>
</div>

@can('reject', $order)
    <div class="collapse mb-3" id="rejectBox">
        <div class="card card-body">
            <form method="POST" action="{{ route('purchase-orders.reject', $order) }}">
                @csrf
                <label for="reason" class="form-label">Reason for rejection</label>
                <textarea id="reason" name="reason" rows="2" minlength="5" maxlength="500" required
                          class="form-control @error('reason') is-invalid @enderror">{{ old('reason') }}</textarea>
                @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <button type="submit" class="btn btn-danger mt-2">Confirm Rejection</button>
            </form>
        </div>
    </div>
@endcan

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card chart-card mb-3">
            <div class="card-header fw-bold">Line Items</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Line Total</th>
                            <th class="text-end">Received</th>
                            <th class="text-end">Outstanding</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->items as $item)
                            <tr>
                                <td><a href="{{ route('products.show', $item->product_id) }}">{{ $item->product->name }}</a> <small class="text-muted">{{ $item->product->sku }}</small></td>
                                <td class="text-end">{{ $item->quantity }}</td>
                                <td class="text-end">${{ number_format($item->unit_price, 2) }}</td>
                                <td class="text-end">${{ number_format($item->line_total, 2) }}</td>
                                <td class="text-end">{{ $item->received_quantity }}</td>
                                <td class="text-end">{{ $item->outstanding_quantity }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr><td colspan="3" class="text-end">Subtotal</td><td class="text-end">${{ number_format($order->subtotal, 2) }}</td><td colspan="2"></td></tr>
                        <tr><td colspan="3" class="text-end">Tax ({{ rtrim(rtrim(number_format($order->tax_rate, 2), '0'), '.') }}%)</td><td class="text-end">${{ number_format($order->tax, 2) }}</td><td colspan="2"></td></tr>
                        <tr class="fw-bold"><td colspan="3" class="text-end">Total</td><td class="text-end">${{ number_format($order->total, 2) }}</td><td colspan="2"></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card chart-card">
            <div class="card-header fw-bold">Approval History</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>When</th><th>Action</th><th>By</th><th>Comment</th></tr></thead>
                    <tbody>
                        @forelse ($order->approvals as $log)
                            <tr>
                                <td>{{ $log->created_at->format('Y-m-d H:i') }}</td>
                                <td><span class="badge bg-secondary">{{ ucfirst($log->action) }}</span></td>
                                <td>{{ $log->user->name ?? 'System' }}</td>
                                <td>{{ $log->comment ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">No activity yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    @if ($order->inventoryTransactions->isNotEmpty())
        <div class="card chart-card mt-3">
            <div class="card-header fw-bold">Goods Received (Inventory Transactions)</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr><th>When</th><th>Product</th><th class="text-end">Qty</th><th class="text-end">Stock After</th><th>By</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($order->inventoryTransactions->sortByDesc('id') as $t)
                            <tr>
                                <td>{{ $t->created_at->format('Y-m-d H:i') }}</td>
                                <td>{{ $t->product->name }}</td>
                                <td class="text-end text-success fw-bold">+{{ $t->quantity }}</td>
                                <td class="text-end">{{ $t->balance_after }}</td>
                                <td>{{ $t->user->name ?? 'System' }}</td>
                                <td class="text-end"><a href="{{ route('transactions.show', $t) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="col-lg-4">
        <div class="card chart-card mb-3">
            <div class="card-header fw-bold">Order Information</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">Supplier</dt>
                    <dd class="col-sm-7"><a href="{{ route('suppliers.show', $order->supplier_id) }}">{{ $order->supplier->name }}</a></dd>
                    <dt class="col-sm-5">Order Date</dt><dd class="col-sm-7">{{ $order->order_date->format('Y-m-d') }}</dd>
                    <dt class="col-sm-5">Expected</dt><dd class="col-sm-7">{{ $order->expected_delivery_date?->format('Y-m-d') ?? '—' }}</dd>
                    <dt class="col-sm-5">Received</dt><dd class="col-sm-7">{{ $order->received_date?->format('Y-m-d') ?? '—' }}</dd>
                    <dt class="col-sm-5">Created By</dt><dd class="col-sm-7">{{ $order->createdBy->name ?? 'System' }}</dd>
                    <dt class="col-sm-5">Approved By</dt>
                    <dd class="col-sm-7">{{ $order->approvedBy ? $order->approvedBy->name . ' (' . $order->approved_at->format('Y-m-d') . ')' : '—' }}</dd>
                </dl>
            </div>
        </div>

        <div class="card chart-card">
            <div class="card-header fw-bold">Terms and Notes</div>
            <div class="card-body">
                <div class="mb-2"><strong>Terms</strong><div>{{ $order->terms ?: '—' }}</div></div>
                <div><strong>Notes</strong><div>{{ $order->notes ?: '—' }}</div></div>
            </div>
        </div>
    </div>
</div>

@endsection