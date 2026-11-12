@extends('layouts.app')

@section('title', 'Edit Brand')
@section('page-title', 'Edit Brand')

@section('content')
<div class="card chart-card">
    <div class="card-body">
        <form method="POST" action="{{ route('brands.update', $brand) }}">
            @csrf
            @method('PUT')
            @include('brands._form')
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Update Brand</button>
                <a href="{{ route('brands.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
