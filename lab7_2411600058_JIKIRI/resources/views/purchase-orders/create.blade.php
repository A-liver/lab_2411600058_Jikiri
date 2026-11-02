@extends('layouts.app')

@section('title', 'New Purchase Order')
@section('page-title', 'New Purchase Order')

@section('content')
<div class="card chart-card">
    <div class="card-body">
        <form method="POST" action="{{ route('purchase-orders.store') }}">
            @csrf
            @include('purchase-orders._form')
            <div class="d-flex gap-2 mt-4">
                <button type="submit" name="action" value="draft" class="btn btn-outline-primary">Save as Draft</button>
                <button type="submit" name="action" value="submit" class="btn btn-primary">Submit for Approval</button>
                <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection