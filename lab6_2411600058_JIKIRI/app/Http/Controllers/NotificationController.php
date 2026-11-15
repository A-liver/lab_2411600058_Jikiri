<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function read(Request $request, string $notification): RedirectResponse
    {
        // looked up through the signed-in user, so nobody can touch someone else's alerts
        $item = $request->user()->notifications()->findOrFail($notification);
        $item->markAsRead();

        $productId = $item->data['product_id'] ?? null;

        if ($productId && Product::whereKey($productId)->exists()) {
            return redirect()->route('products.show', $productId);
        }

        return back()->with('error', 'That product no longer exists.');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}