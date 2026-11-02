<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $order->reference_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #283618; }
        h1 { font-size: 20px; margin: 0; color: #606C38; }
        .ref { font-size: 16px; font-weight: bold; }
        .muted { color: #666666; }
        table { width: 100%; border-collapse: collapse; }
        .items th { background: #606C38; color: #ffffff; text-align: left; padding: 6px; }
        .items td { padding: 6px; border-bottom: 1px solid #dddddd; }
        .num { text-align: right; }
        .box { border: 1px solid #dddddd; padding: 8px; }
        .totals td { padding: 3px 6px; }
        .section { margin-top: 18px; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td>
                <h1>{{ $appName }}</h1>
                <div class="muted">Purchase Order</div>
            </td>
            <td style="text-align:right">
                <div class="ref">{{ $order->reference_number }}</div>
                <div>Date: {{ $order->order_date->format('M j, Y') }}</div>
                <div>Status: {{ $order->status_label }}</div>
            </td>
        </tr>
    </table>

    <table class="section">
        <tr>
            <td style="width:50%; vertical-align:top; padding-right:8px;">
                <div class="box">
                    <strong>Supplier</strong><br>
                    {{ $order->supplier->name }}<br>
                    @if ($order->supplier->contact_person){{ $order->supplier->contact_person }}<br>@endif
                    @if ($order->supplier->email){{ $order->supplier->email }}<br>@endif
                    @if ($order->supplier->phone){{ $order->supplier->phone }}<br>@endif
                    @if ($order->supplier->address){{ $order->supplier->address }}@endif
                </div>
            </td>
            <td style="width:50%; vertical-align:top; padding-left:8px;">
                <div class="box">
                    <strong>Order details</strong><br>
                    Expected delivery: {{ $order->expected_delivery_date?->format('M j, Y') ?? 'To be confirmed' }}<br>
                    Payment terms: {{ $order->supplier->payment_terms }}<br>
                    Prepared by: {{ $order->createdBy->name ?? 'System (auto-generated)' }}<br>
                    @if ($order->approvedBy)Approved by: {{ $order->approvedBy->name }}@endif
                </div>
            </td>
        </tr>
    </table>

    <table class="items section">
        <thead>
            <tr>
                <th style="width:5%">#</th>
                <th>Product</th>
                <th>SKU</th>
                <th class="num">Qty</th>
                <th class="num">Unit price</th>
                <th class="num">Line total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->product->name }}</td>
                    <td>{{ $item->product->sku }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">${{ number_format($item->unit_price, 2) }}</td>
                    <td class="num">${{ number_format($item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="width:40%; margin-left:60%; margin-top:8px;">
        <tr><td>Subtotal</td><td class="num">${{ number_format($order->subtotal, 2) }}</td></tr>
        <tr><td>Tax ({{ rtrim(rtrim(number_format($order->tax_rate, 2), '0'), '.') }}%)</td><td class="num">${{ number_format($order->tax, 2) }}</td></tr>
        <tr><td><strong>Total</strong></td><td class="num"><strong>${{ number_format($order->total, 2) }}</strong></td></tr>
    </table>

    @if ($order->terms)
        <div class="section"><strong>Terms</strong><br>{{ $order->terms }}</div>
    @endif
    @if ($order->notes)
        <div class="section"><strong>Notes</strong><br>{{ $order->notes }}</div>
    @endif
</body>
</html>