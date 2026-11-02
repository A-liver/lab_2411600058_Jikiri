@extends('emails.layout')

@section('title', 'Purchase order approved')

@section('content')
    <h2 style="margin:0 0 12px;color:#606C38;">Purchase order approved</h2>

    <p>Purchase order <strong>{{ $order->reference_number }}</strong> was approved by <strong>{{ $approver->name }}</strong>.
       It can now be sent to {{ $order->supplier->name }}.</p>

    @include('emails.partials.order-summary')

    <p style="margin-top:24px;">
        <a href="{{ route('purchase-orders.show', $order) }}"
           style="display:inline-block;background:#606C38;color:#ffffff;padding:10px 20px;border-radius:4px;text-decoration:none;">
            View purchase order
        </a>
    </p>
@endsection