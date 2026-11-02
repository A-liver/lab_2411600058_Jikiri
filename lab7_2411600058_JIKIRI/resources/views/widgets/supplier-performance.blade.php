<div class="card shadow-hover">
    <div class="card-header fw-bold">Top Suppliers by On-Time Delivery</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr><th>Supplier</th><th style="width:35%"></th><th class="text-end">On time</th><th class="text-end">Orders</th><th class="text-end">Spent</th></tr>
            </thead>
            <tbody id="wPerformanceBody">
                @forelse ($performance as $s)
                    @php $color = $s['on_time_pct'] >= 90 ? 'success' : ($s['on_time_pct'] >= 70 ? 'warning' : 'danger'); @endphp
                    <tr>
                        <td><a href="{{ $s['url'] }}">{{ $s['name'] }}</a></td>
                        <td><div class="progress" style="height:8px"><div class="progress-bar bg-{{ $color }}" style="width: {{ $s['on_time_pct'] }}%"></div></div></td>
                        <td class="text-end">{{ $s['on_time_pct'] }}%</td>
                        <td class="text-end">{{ $s['orders'] }}</td>
                        <td class="text-end">${{ number_format($s['spent'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">No completed deliveries yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>