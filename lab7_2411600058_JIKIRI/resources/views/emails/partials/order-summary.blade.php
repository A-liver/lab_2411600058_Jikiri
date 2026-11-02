<table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;margin:16px 0;">
    <tr><td style="padding:4px 0;color:#606C38;">Reference</td><td style="padding:4px 0;text-align:right;"><strong>{{ $order->reference_number }}</strong></td></tr>
    <tr><td style="padding:4px 0;color:#606C38;">Supplier</td><td style="padding:4px 0;text-align:right;">{{ $order->supplier->name }}</td></tr>
    <tr><td style="padding:4px 0;color:#606C38;">Order date</td><td style="padding:4px 0;text-align:right;">{{ $order->order_date->format('M j, Y') }}</td></tr>
    <tr><td style="padding:4px 0;color:#606C38;">Expected delivery</td><td style="padding:4px 0;text-align:right;">{{ $order->expected_delivery_date?->format('M j, Y') ?? 'To be confirmed' }}</td></tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;border-collapse:collapse;">
    <thead>
        <tr style="background:#f5f1d8;">
            <th align="left" style="padding:8px;">Product</th>
            <th align="right" style="padding:8px;">Qty</th>
            <th align="right" style="padding:8px;">Unit price</th>
            <th align="right" style="padding:8px;">Line total</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($order->items as $item)
            <tr style="border-bottom:1px solid #eeeeee;">
                <td style="padding:8px;">{{ $item->product->name }}</td>
                <td align="right" style="padding:8px;">{{ $item->quantity }}</td>
                <td align="right" style="padding:8px;">${{ number_format($item->unit_price, 2) }}</td>
                <td align="right" style="padding:8px;">${{ number_format($item->line_total, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr><td colspan="3" align="right" style="padding:8px;">Subtotal</td><td align="right" style="padding:8px;">${{ number_format($order->subtotal, 2) }}</td></tr>
        <tr><td colspan="3" align="right" style="padding:8px;">Tax</td><td align="right" style="padding:8px;">${{ number_format($order->tax, 2) }}</td></tr>
        <tr><td colspan="3" align="right" style="padding:8px;font-weight:bold;">Total</td><td align="right" style="padding:8px;font-weight:bold;">${{ number_format($order->total, 2) }}</td></tr>
    </tfoot>
</table>