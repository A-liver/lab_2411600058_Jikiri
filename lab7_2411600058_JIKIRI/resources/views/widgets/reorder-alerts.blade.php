<div class="card h-100 shadow-hover">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold">Reorder Alerts</span>
        <span class="badge bg-{{ $reorder['count'] > 0 ? 'danger' : 'success' }}" id="wReorderCount">{{ $reorder['count'] }}</span>
    </div>
    <ul class="list-group list-group-flush small" id="wReorderBody">
        @forelse ($reorder['items'] as $item)
            <li class="list-group-item">
                <a href="{{ $item['url'] }}" class="fw-semibold">{{ $item['name'] }}</a>
                @if ($item['in_draft'] > 0)
                    <span class="badge bg-info text-dark">Draft ready</span>
                @endif
                <div class="text-muted">
                    Stock {{ $item['stock'] }} / reorder at {{ $item['reorder_point'] }} · order {{ $item['quantity'] }}
                    · {{ $item['supplier'] }}
                </div>
            </li>
        @empty
            <li class="list-group-item text-muted">Every product is above its reorder point.</li>
        @endforelse
    </ul>
    <div class="card-footer small text-muted" id="wReorderFooter">
        @if ($reorder['drafted'] > 0)
            {{ $reorder['drafted'] }} already in a draft order.
        @endif
        @if ($reorder['count'] > count($reorder['items']))
            + {{ $reorder['count'] - count($reorder['items']) }} more.
        @endif
        @if ($reorder['without_supplier'] > 0)
            {{ $reorder['without_supplier'] }} product(s) have no active supplier.
        @endif
        <a href="{{ route('purchase-orders.index', ['status' => 'draft']) }}">Draft orders</a>
    </div>
</div>