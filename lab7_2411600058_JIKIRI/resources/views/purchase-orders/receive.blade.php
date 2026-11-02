@extends('layouts.app')

@section('title', 'Receive ' . $order->reference_number)
@section('page-title', 'Receive Goods: ' . $order->reference_number)

@section('content')
<div class="card chart-card">
    <div class="card-body">
        <p class="text-muted mb-3">
            Supplier: <strong>{{ $order->supplier->name }}</strong>
            &middot; Expected: <strong>{{ $order->expected_delivery_date?->format('Y-m-d') ?? 'not set' }}</strong>
        </p>

        <form method="POST" action="{{ route('purchase-orders.receive.store', $order) }}"
              onsubmit="return confirm('Record this receipt? Stock levels will be updated immediately.');">
            @csrf

            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-end">Ordered</th>
                            <th class="text-end">Already Received</th>
                            <th class="text-end">Outstanding</th>
                            <th style="width:160px">Receive Now</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->items as $item)
                            <tr>
                                <td>{{ $item->product->name }} <small class="text-muted">{{ $item->product->sku }}</small></td>
                                <td class="text-end">{{ $item->quantity }}</td>
                                <td class="text-end">{{ $item->received_quantity }}</td>
                                <td class="text-end fw-bold">{{ $item->outstanding_quantity }}</td>
                                <td>
                                    <input type="number" name="received[{{ $item->id }}]" class="form-control form-control-sm receive-input"
                                           min="0" max="{{ $item->outstanding_quantity }}" data-max="{{ $item->outstanding_quantity }}"
                                           value="{{ old('received.' . $item->id, 0) }}"
                                           @disabled($item->outstanding_quantity === 0)>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <button type="button" id="fillAll" class="btn btn-sm btn-outline-primary mb-3">Receive all outstanding</button>

            <div class="row g-3">
                <div class="col-md-4">
                    <label for="delivery_note" class="form-label">Delivery Note No. (optional)</label>
                    <input type="text" id="delivery_note" name="delivery_note" maxlength="100"
                           value="{{ old('delivery_note') }}" class="form-control @error('delivery_note') is-invalid @enderror">
                    @error('delivery_note') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-8">
                    <label for="notes" class="form-label">Notes (optional)</label>
                    <input type="text" id="notes" name="notes" maxlength="500"
                           value="{{ old('notes') }}" class="form-control @error('notes') is-invalid @enderror">
                    @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-success">Confirm Receipt</button>
                <a href="{{ route('purchase-orders.show', $order) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('fillAll').addEventListener('click', () => {
        document.querySelectorAll('.receive-input:not([disabled])').forEach((el) => { el.value = el.dataset.max; });
    });
</script>
@endpush