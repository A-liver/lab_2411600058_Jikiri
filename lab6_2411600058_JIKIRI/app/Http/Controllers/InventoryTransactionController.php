<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryTransaction;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryTransactionController extends Controller
{
    private const MANUAL_TYPES = [InventoryTransaction::TYPE_IN, InventoryTransaction::TYPE_OUT];

    public function index(Request $request): View
    {
        $request->validate([
            'product_id' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::in(InventoryTransaction::TYPES)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $transactions = InventoryTransaction::query()
            ->with(['product', 'user']) // eager load: avoids one query per row
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))
            ->recent()
            ->paginate(20)
            ->withQueryString();

        return view('transactions.index', [
            'transactions' => $transactions,
            'products' => Product::orderBy('name')->get(['id', 'name', 'sku']),
            'types' => InventoryTransaction::TYPES,
        ]);
    }

    public function create(): View
    {
        return view('transactions.create', [
            'products' => Product::orderBy('name')->get(['id', 'name', 'sku', 'quantity']),
            'types' => self::MANUAL_TYPES, // adjustments get their own form in Step 9
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'type' => ['required', Rule::in(self::MANUAL_TYPES)],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'product_id.exists' => 'That product no longer exists.',
        ]);

        try {
            $transaction = InventoryTransaction::record(
                $validated['product_id'],
                $validated['type'],
                (int) $validated['quantity'],
                $validated['reference'] ?? null,
                $validated['notes'] ?? null,
                $request->user()->id,
            );
        } catch (InsufficientStockException $e) {
            // the race-safe check lives in the observer; the user keeps what they typed
            return back()->withInput()->withErrors(['quantity' => $e->getMessage()]);
        }

        return redirect()
            ->route('transactions.show', $transaction)
            ->with('success', "{$transaction->type_label} of {$transaction->quantity} recorded for {$transaction->product->name}.");
    }

    public function show(InventoryTransaction $transaction): View
    {
        $transaction->load(['product.category', 'user']);

        return view('transactions.show', compact('transaction'));
    }

    /** Admin only. Records an opposite entry instead of erasing history. */
    public function destroy(Request $request, InventoryTransaction $transaction): RedirectResponse
    {
        $marker = 'Reversal of #';
        $reference = $marker . $transaction->id;

        if (str_starts_with((string) $transaction->reference, $marker)) {
            return back()->with('error', 'A reversal entry cannot be reversed.');
        }

        if ($transaction->product?->trashed()) {
            return back()->with('error', 'This product was deleted, so its movements can no longer be reversed.');
        }

        $alreadyReversed = InventoryTransaction::where('product_id', $transaction->product_id)
            ->where('reference', $reference)
            ->exists();

        if ($alreadyReversed) {
            return back()->with('error', "Transaction #{$transaction->id} was already reversed.");
        }

        [$type, $quantity] = match ($transaction->type) {
            InventoryTransaction::TYPE_IN => [InventoryTransaction::TYPE_OUT, $transaction->quantity],
            InventoryTransaction::TYPE_OUT => [InventoryTransaction::TYPE_IN, $transaction->quantity],
            default => [InventoryTransaction::TYPE_ADJUSTMENT, -$transaction->quantity],
        };

        try {
            InventoryTransaction::record(
                $transaction->product_id,
                $type,
                $quantity,
                $reference,
                "Reverses {$transaction->type_label} of {$transaction->quantity} from {$transaction->created_at->format('Y-m-d H:i')}",
                $request->user()->id,
            );
        } catch (InsufficientStockException $e) {
            return back()->with('error', "Cannot reverse transaction #{$transaction->id}: {$e->getMessage()}");
        }

        return redirect()
            ->route('transactions.index')
            ->with('success', "Transaction #{$transaction->id} was reversed.");
    }
}