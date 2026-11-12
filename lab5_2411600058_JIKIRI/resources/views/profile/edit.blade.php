@extends('layouts.app')

@section('title', 'Profile')
@section('page-title', 'Profile')

@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card chart-card mb-4">
            <div class="card-body">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="card chart-card mb-4">
            <div class="card-body">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="card chart-card mb-4">
            <div class="card-body">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</div>
@endsection
