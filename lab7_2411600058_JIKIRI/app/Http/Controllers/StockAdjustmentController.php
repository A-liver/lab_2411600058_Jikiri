<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockAdjustmentRequest;
use App\Models\InventoryTransaction;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    public function create(Request $request): View
    {
        $products = Product::orderBy('name')->get(['id', 'name', 'sku']);
        $reasons = InventoryTransaction::ADJUSTMENT_REASONS;
        $selectedProduct = $request->query('product_id');

        return view('adjustments.create', compact('products', 'reasons', 'selectedProduct'));
    }

    public function store(StoreStockAdjustmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $physical = (int) $data['physical_count'];

        // The difference is calculated on the server, under a row lock, from the
        // CURRENT system quantity. We never trust the number the browser displayed,
        // because stock may have changed since the form was opened.
        $transaction = DB::transaction(function () use ($data, $physical) {
            $product = Product::lockForUpdate()->findOrFail($data['product_id']);
            $difference = $physical - $product->quantity;

            if ($difference === 0) {
                return null;
            }

            $note = "Physical count {$physical} vs system count {$product->quantity}.";
            if (! empty($data['notes'])) {
                $note .= ' ' . $data['notes'];
            }

            // Observer updates the stock and may fire a low-stock alert.
            return InventoryTransaction::record([
                'product_id' => $product->id,
                'user_id' => auth()->id(),
                'type' => InventoryTransaction::TYPE_ADJUSTMENT,
                'quantity' => $difference,               // signed: physical - system
                'reason' => $data['reason'],
                'reference_number' => 'ADJ-' . now()->format('YmdHis'),
                'notes' => $note,
            ]);
        });

        if ($transaction === null) {
            return back()->withInput()->withErrors([
                'physical_count' => 'The physical count matches the system count, so no adjustment was needed.',
            ]);
        }

        $sign = $transaction->quantity > 0 ? '+' : '';

        return redirect()
            ->route('transactions.show', $transaction)
            ->with('success', "Adjustment of {$sign}{$transaction->quantity} recorded for "
                . "\"{$transaction->product->name}\". New stock: {$transaction->balance_after}.");
    }
}