@extends('emails.layout')

@section('title', 'Purchase order')

@section('content')
    <h2 style="margin:0 0 12px;color:#606C38;">Purchase order {{ $order->reference_number }}</h2>

    <p>Dear {{ $order->supplier->contact_person ?: $order->supplier->name }},</p>
    <p>Please find our purchase order below. Kindly confirm receipt and the expected delivery date.</p>

    @include('emails.partials.order-summary')

    @if ($order->terms)
        <p><strong>Terms:</strong> {{ $order->terms }}</p>
    @endif
    @if ($order->notes)
        <p><strong>Notes:</strong> {{ $order->notes }}</p>
    @endif

    <p>Thank you,<br>{{ config('app.name') }}</p>
@endsection