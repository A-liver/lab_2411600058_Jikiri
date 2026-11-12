{{-- Stock totals grouped by category or brand. Expects $title, $rows, $filter (query key), $label (column heading). --}}
<div class="card chart-card h-100">
    <div class="card-header"><h5 class="mb-0">{{ $title }}</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ $label }}</th>
                        <th class="text-end">Products</th>
                        <th class="text-end">Units</th>
                        <th class="text-end">Value</th>
                        <th class="text-end">Low</th>
                        <th class="text-end">Out</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                @if ($row->id)
                                    <a href="{{ route('products.index', [$filter => $row->id]) }}">{{ $row->name }}</a>
                                @else
                                    {{ $row->name }}
                                @endif
                            </td>
                            <td class="text-end">{{ $row->product_count }}</td>
                            <td class="text-end">{{ number_format($row->units) }}</td>
                            <td class="text-end">${{ number_format($row->stock_value, 2) }}</td>
                            <td class="text-end">{{ $row->low_count }}</td>
                            <td class="text-end">{{ $row->out_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-3">Nothing to show yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
