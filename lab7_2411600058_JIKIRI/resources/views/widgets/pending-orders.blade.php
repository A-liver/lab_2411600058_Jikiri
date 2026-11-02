<div class="card stat-card h-100 shadow-hover">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
            <h6 class="text-muted mb-1">Pending Approval</h6>
            <a href="{{ route('purchase-orders.index', ['status' => 'pending']) }}" class="small">View</a>
        </div>
        <h3 class="fw-bold text-warning mb-0" id="wPendingCount">{{ $pending['count'] }}</h3>
        <div class="small text-muted">Total value: <span id="wPendingValue">${{ number_format($pending['total_value'], 2) }}</span></div>
        <div class="small mt-2" id="wPendingExtra">
            @php
                $parts = [];
                if ($pending['awaiting_final'] > 0) $parts[] = $pending['awaiting_final'] . ' awaiting admin approval';
                if ($pending['oldest_days'] !== null) $parts[] = 'oldest waiting ' . $pending['oldest_days'] . ' day(s)';
            @endphp
            {{ $parts ? implode(' · ', $parts) : 'Nothing waiting' }}
        </div>
    </div>
</div>