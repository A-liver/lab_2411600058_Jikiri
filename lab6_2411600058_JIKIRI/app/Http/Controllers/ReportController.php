<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportController extends Controller
{
    private const REPORTS = [
        'movement' => 'Stock Movement',
        'low-stock' => 'Low Stock',
        'valuation' => 'Inventory Valuation',
    ];

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'report' => ['nullable', Rule::in(array_keys(self::REPORTS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $report = $validated['report'] ?? 'movement';
        $from = $validated['from'] ?? now()->subDays(29)->toDateString();
        $to = $validated['to'] ?? now()->toDateString();

        $data = match ($report) {
            'movement' => $this->movement($from, $to),
            'low-stock' => ['products' => Product::atReorderLevel()->with('category')->orderBy('quantity')->orderBy('name')->get()],
            'valuation' => $this->valuation(),
        };

        return view('reports.index', [
            'reports' => self::REPORTS,
            'report' => $report,
            'from' => $from,
            'to' => $to,
        ] + $data);
    }

    private function movement(string $from, string $to): array
    {
        $base = InventoryTransaction::query()
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to);

        $summary = (clone $base)->selectRaw("
            COUNT(*) as movements,
            COALESCE(SUM(CASE WHEN type = 'stock_in' THEN quantity END), 0) as units_in,
            COALESCE(SUM(CASE WHEN type = 'stock_out' THEN quantity END), 0) as units_out,
            COALESCE(SUM(CASE WHEN type = 'adjustment' THEN quantity END), 0) as net_adjustment
        ")->first();

        $rows = $base->with(['product', 'user'])->recent()->paginate(25)->withQueryString();

        return compact('summary', 'rows');
    }

    private function valuation(): array
    {
        return [
            'totalValue' => (float) Product::sum(DB::raw('quantity * unit_price')),
            'totalUnits' => (int) Product::sum('quantity'),
            'products' => Product::with('category')
                ->orderByRaw('quantity * unit_price DESC')
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
        ];
    }
}