<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    public const REASONS = [
        'count' => 'Physical count discrepancy',
        'damaged' => 'Damaged goods',
        'loss' => 'Theft or loss',
        'system_error' => 'System error',
    ];

    public function create(): View
    {
        return view('adjustments.create', [
            'products' => Product::orderBy('name')->get(['id', 'name', 'sku']),
            'reasons' => self::REASONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'system_quantity' => ['required', 'integer', 'min:0'],
            'physical_count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reason' => ['required', Rule::in(array_keys(self::REASONS))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'system_quantity.required' => 'Choose a product and wait for its system quantity to load.',
            'physical_count.required' => 'Enter the number you counted.',
        ]);

        $outcome = DB::transaction(function () use ($validated, $request) {
            // lock first, then compare: the browser's number may already be out of date
            $product = Product::whereKey($validated['product_id'])->lockForUpdate()->firstOrFail();

            if ((int) $validated['system_quantity'] !== $product->quantity) {
                return ['stale' => $product->quantity];
            }

            $difference = (int) $validated['physical_count'] - $product->quantity;

            if ($difference === 0) {
                return ['same' => true];
            }

            return ['transaction' => InventoryTransaction::record(
                $product,
                InventoryTransaction::TYPE_ADJUSTMENT,
                $difference,
                self::REASONS[$validated['reason']],
                $validated['notes'] ?? null,
                $request->user()->id,
            )];
        });

        if (isset($outcome['stale'])) {
            return back()->withInput()->withErrors([
                'physical_count' => "Stock changed while you were counting. The system now shows {$outcome['stale']}. Check your count and submit again.",
            ]);
        }

        if (isset($outcome['same'])) {
            return back()->withInput()->withErrors([
                'physical_count' => 'Your count matches the system quantity, so there is nothing to adjust.',
            ]);
        }

        $transaction = $outcome['transaction'];

        return redirect()
            ->route('transactions.show', $transaction)
            ->with('success', sprintf('Stock adjusted by %+d. New quantity: %d.', $transaction->quantity, $transaction->balance_after));
    }
}