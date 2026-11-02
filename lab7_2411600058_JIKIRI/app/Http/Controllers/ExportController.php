<?php

namespace App\Http\Controllers;

use App\Exports\Export;
use App\Exports\ProductsExport;
use App\Exports\PurchaseOrdersExport;
use App\Exports\SupplierPerformanceExport;
use App\Exports\TransactionsExport;
use App\Models\InventoryTransaction;
use App\Models\PurchaseOrder;
use App\Services\ExportService;
use App\Services\SearchService;
use App\Services\SupplierPerformanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ExportController extends Controller
{
    public function download(Request $request, string $dataset, string $format, SearchService $search, ExportService $exports)
    {
        $export = match ($dataset) {
            'products' => $this->products($request, $search),
            'purchase-orders' => $this->purchaseOrders($request, $search),
            'transactions' => $this->transactions($request, $search),
            'supplier-performance' => $this->supplierPerformance($request),
            default => abort(404),
        };

        $name = str_replace('-', '_', $dataset);

        return $format === 'pdf' ? $exports->pdf($export, $name) : $exports->csv($export, $name);
    }

    /** A single purchase order as a PDF document. */
    public function purchaseOrder(PurchaseOrder $purchaseOrder, ExportService $exports)
    {
        Gate::authorize('view', $purchaseOrder);

        return $exports->purchaseOrderPdf($purchaseOrder)->download($purchaseOrder->reference_number . '.pdf');
    }

    // ---------- datasets (same filters as the list pages) ----------

    private function products(Request $request, SearchService $search): Export
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'stock_status' => ['nullable', Rule::in(['all', 'in-stock', 'low-stock', 'out-of-stock'])],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'supplier_id' => ['nullable', 'integer'],
        ]);

        return (new ProductsExport($search->products($filters)))->withFilters($filters);
    }

    private function purchaseOrders(Request $request, SearchService $search): Export
    {
        Gate::authorize('viewAny', PurchaseOrder::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(PurchaseOrder::STATUSES))],
            'supplier_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:50'],
            'auto' => ['nullable', Rule::in(['0', '1'])],
            'min_total' => ['nullable', 'numeric', 'min:0'],
            'max_total' => ['nullable', 'numeric', 'min:0'],
        ]);

        return (new PurchaseOrdersExport($search->purchaseOrders($filters)))->withFilters($filters);
    }

    private function transactions(Request $request, SearchService $search): Export
    {
        $filters = $request->validate([
            'product_id' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::in(array_keys(InventoryTransaction::types()))],
            'user_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return (new TransactionsExport($search->transactions($filters)))->withFilters($filters);
    }

    private function supplierPerformance(Request $request): Export
    {
        Gate::authorize('manage-stock');

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $rows = app(SupplierPerformanceService::class)->summary($filters['from'] ?? null, $filters['to'] ?? null);

        return (new SupplierPerformanceExport($rows))->withFilters($filters);
    }
}