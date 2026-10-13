<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('reports.movement');
    }

    /** All transactions within a date range (default: last 30 days). */
    public function movement(Request $request): View|StreamedResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = $data['from'] ?? now()->subDays(29)->toDateString();
        $to = $data['to'] ?? now()->toDateString();

        $transactions = InventoryTransaction::with(['product', 'user'])
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $rows = $transactions->map(fn ($t) => [
            $t->created_at->format('Y-m-d H:i'),
            $t->product->name,
            $t->product->sku,
            $t->type_label,
            $this->signed($t->signed_quantity),
            $t->balance_after ?? '',
            $t->reference_number ?? '',
            $t->user->name ?? 'System',
        ])->all();

        return $this->respond($request, [
            'active' => 'movement',
            'title' => 'Stock Movement Report',
            'subtitle' => "{$from} to {$to}",
            'dates' => ['from' => $from, 'to' => $to],
            'columns' => ['Date / Time', 'Product', 'SKU', 'Type', 'Quantity', 'Balance After', 'Reference', 'User'],
            'rows' => $rows,
            'summary' => [
                'Transactions' => $transactions->count(),
                'Total stock in' => $transactions->where('type', InventoryTransaction::TYPE_IN)->sum('quantity'),
                'Total stock out' => $transactions->where('type', InventoryTransaction::TYPE_OUT)->sum('quantity'),
                'Net adjustments' => $this->signed((int) $transactions->where('type', InventoryTransaction::TYPE_ADJUSTMENT)->sum('quantity')),
            ],
        ], 'stock_movement');
    }

    /** Products at or below their reorder level. */
    public function lowStock(Request $request): View|StreamedResponse
    {
        $products = Product::lowStock()->orderBy('quantity')->orderBy('name')->get();

        $rows = $products->map(fn ($p) => [
            $p->sku,
            $p->name,
            $p->category,
            $p->quantity,
            $p->reorder_level,
            max($p->reorder_level - $p->quantity, 0),
            $p->supplier ?? '',
            $p->stock_status_label,
        ])->all();

        return $this->respond($request, [
            'active' => 'low-stock',
            'title' => 'Low Stock Report',
            'subtitle' => 'Generated ' . now()->format('Y-m-d H:i'),
            'dates' => null,
            'columns' => ['SKU', 'Product', 'Category', 'Quantity', 'Reorder Level', 'Shortfall', 'Supplier', 'Status'],
            'rows' => $rows,
            'summary' => [
                'Items needing reorder' => $products->count(),
                'Out of stock' => $products->where('quantity', '<=', 0)->count(),
            ],
        ], 'low_stock');
    }

    /** Total value of current inventory. */
    public function valuation(Request $request): View|StreamedResponse
    {
        $products = Product::orderBy('category')->orderBy('name')->get();

        $rows = $products->map(fn ($p) => [
            $p->sku,
            $p->name,
            $p->category,
            $p->quantity,
            number_format($p->unit_price, 2),
            number_format($p->quantity * $p->unit_price, 2),
        ])->all();

        $totalValue = $products->sum(fn ($p) => $p->quantity * $p->unit_price);

        return $this->respond($request, [
            'active' => 'valuation',
            'title' => 'Inventory Valuation Report',
            'subtitle' => 'As of ' . now()->format('Y-m-d H:i'),
            'dates' => null,
            'columns' => ['SKU', 'Product', 'Category', 'Quantity', 'Unit Price ($)', 'Total Value ($)'],
            'rows' => $rows,
            'summary' => [
                'Products' => $products->count(),
                'Total units' => $products->sum('quantity'),
                'Total inventory value' => '$' . number_format($totalValue, 2),
            ],
        ], 'inventory_valuation');
    }

    private function signed(int $n): string
    {
        return ($n > 0 ? '+' : '') . $n;
    }

    /** HTML page by default; CSV download when ?export=csv. */
    private function respond(Request $request, array $report, string $filename): View|StreamedResponse
    {
        if ($request->query('export') === 'csv') {
            return response()->streamDownload(function () use ($report) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");               // UTF-8 BOM so Excel reads it correctly
                fputcsv($out, [$report['title'], $report['subtitle']]);
                fputcsv($out, $report['columns']);
                foreach ($report['rows'] as $row) {
                    fputcsv($out, $row);
                }
                fputcsv($out, []);
                foreach ($report['summary'] as $label => $value) {
                    fputcsv($out, [$label, $value]);
                }
                fclose($out);
            }, $filename . '_' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
        }

        return view('reports.show', ['report' => $report]);
    }
}