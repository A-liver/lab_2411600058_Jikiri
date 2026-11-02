@extends('emails.layout')

@section('title', 'Low stock alert')

@section('content')
    <h2 style="margin:0 0 12px;color:#606C38;">Low stock alert</h2>

    <p>The reorder check found <strong>{{ count($items) }}</strong> product(s) at or below their reorder point.</p>

    <table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;border-collapse:collapse;margin:16px 0;">
        <thead>
            <tr style="background:#f5f1d8;">
                <th align="left" style="padding:8px;">Product</th>
                <th align="right" style="padding:8px;">Stock</th>
                <th align="right" style="padding:8px;">Reorder point</th>
                <th align="right" style="padding:8px;">Order qty</th>
                <th align="left" style="padding:8px;">Supplier</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr style="border-bottom:1px solid #eeeeee;">
                    <td style="padding:8px;">{{ $item['name'] }} <span style="color:#999999;">{{ $item['sku'] }}</span></td>
                    <td align="right" style="padding:8px;color:{{ $item['stock'] <= 0 ? '#dc3545' : '#283618' }};">{{ $item['stock'] }}</td>
                    <td align="right" style="padding:8px;">{{ $item['reorder_point'] }}</td>
                    <td align="right" style="padding:8px;">{{ $item['quantity'] }}</td>
                    <td style="padding:8px;">{{ $item['supplier'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($references)
        <p><strong>Draft purchase orders created:</strong> {{ implode(', ', $references) }}</p>
    @else
        <p>No purchase orders were created (no active supplier is linked to these products).</p>
    @endif

    <p style="margin-top:24px;">
        <a href="{{ route('purchase-orders.index', ['status' => 'draft']) }}"
           style="display:inline-block;background:#606C38;color:#ffffff;padding:10px 20px;border-radius:4px;text-decoration:none;">
            Review draft orders
        </a>
    </p>
@endsection