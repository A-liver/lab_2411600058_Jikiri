<div class="card chart-card mt-3" id="history">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
            <h6 class="mb-0">Stock History</h6>
            <form method="GET" action="{{ route('products.show', $product) }}#history" class="d-flex flex-wrap gap-2 align-items-end">
                <div>
                    <label for="from" class="form-label small mb-1">From</label>
                    <input type="date" id="from" name="from" value="{{ request('from') }}" class="form-control">
                </div>
                <div>
                    <label for="to" class="form-label small mb-1">To</label>
                    <input type="date" id="to" name="to" value="{{ request('to') }}" class="form-control">
                </div>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="{{ route('products.show', $product) }}#history" class="btn btn-outline-secondary">Reset</a>
            </form>
        </div>

        @if ($errors->has('from') || $errors->has('to'))
            <p class="text-danger small">{{ $errors->first('from') ?: $errors->first('to') }}</p>
        @endif

        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th class="text-end">Change</th>
                        <th class="text-end">Balance</th>
                        <th>Reference</th>
                        <th>By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($history as $t)
                        <tr>
                            <td>{{ $t->created_at->format('Y-m-d H:i') }}</td>
                            <td><span class="badge text-bg-{{ $t->type_color }}">{{ $t->type_label }}</span></td>
                            <td class="text-end">{{ sprintf('%+d', $t->delta()) }}</td>
                            <td class="text-end">{{ $t->balance_after ?? '-' }}</td>
                            <td>{{ $t->reference ?? '-' }}</td>
                            <td>{{ $t->user?->name ?? 'System' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">No movements in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $history->links() }}
    </div>
</div>