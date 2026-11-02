@extends('emails.layout')

@section('title', 'Approval needed')

@section('content')
    <h2 style="margin:0 0 12px;color:#606C38;">Purchase order awaiting approval</h2>

    <p>Purchase order <strong>{{ $order->reference_number }}</strong> for <strong>{{ $order->supplier->name }}</strong>
       (${{ number_format($order->total, 2) }}) is waiting for your approval.</p>

    @if ($order->requiredApprovalLevels() > 1)
        <p style="background:#FEFAE0;border-left:4px solid #DDA15E;padding:10px 12px;">
            This order is above ${{ number_format(config('procurement.approval_threshold'), 0) }}, so it needs a
            manager's approval followed by an admin's approval.
        </p>
    @endif

    @include('emails.partials.order-summary')

    <p style="margin-top:24px;">
        <a href="{{ route('purchase-orders.show', $order) }}"
           style="display:inline-block;background:#606C38;color:#ffffff;padding:10px 20px;border-radius:4px;text-decoration:none;">
            Review purchase order
        </a>
    </p>
@endsection