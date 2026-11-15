@extends('layouts.app')

@section('title', 'Add Brand')
@section('page-title', 'Add Brand')

@section('content')
<div class="card chart-card">
    <div class="card-body">
        <form method="POST" action="{{ route('brands.store') }}">
            @csrf
            @include('brands._form')
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save Brand</button>
                <a href="{{ route('brands.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
