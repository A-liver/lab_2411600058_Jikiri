@extends('layouts.app')

@section('title', 'Add Category')
@section('page-title', 'Add Category')

@section('content')
<div class="card chart-card">
    <div class="card-body">
        <form method="POST" action="{{ route('categories.store') }}">
            @csrf
            @include('categories._form')
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save Category</button>
                <a href="{{ route('categories.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
