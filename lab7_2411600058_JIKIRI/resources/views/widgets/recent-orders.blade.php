<div class="card h-100 shadow-hover">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold">Recent Purchase Orders</span>
        <a href="{{ route('purchase-orders.index') }}" class="small">View all</a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Reference</th><th>Supplier</th><th>Status</th><th class="text-end">Total</th></tr></thead>
            <tbody id="wRecentBody">
                @forelse ($recentOrders as $order)
                    <tr>
                        <td><a href="{{ $order['url'] }}">{{ $order['reference'] }}</a></td>
                        <td>{{ $order['supplier'] }}</td>
                        <td><span class="badge bg-{{ $order['status_color'] }} {{ $order['status_color'] === 'warning' ? 'text-dark' : '' }}">{{ $order['status_label'] }}</span></td>
                        <td class="text-end">${{ number_format($order['total'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-3">No purchase orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>