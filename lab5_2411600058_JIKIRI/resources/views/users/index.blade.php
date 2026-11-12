@extends('layouts.app')

@section('title', 'Users')
@section('page-title', 'User Management')

@section('content')

<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('users.index') }}" class="row g-3 align-items-end">
            <div class="col-md-8">
                <label for="userSearch" class="form-label">Search by name or email</label>
                <input type="text" id="userSearch" name="search" class="form-control"
                       value="{{ request('search') }}" placeholder="e.g. admin@example.com">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">Search</button>
                <a href="{{ route('users.index') }}" class="btn btn-outline-secondary flex-fill">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card chart-card mb-3">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div class="text-muted">{{ $users->total() }} {{ \Illuminate\Support\Str::plural('user', $users->total()) }}</div>
        <a href="{{ route('users.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Add User
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Accounts</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                {{ $user->name }}
                                @if ($user->is(auth()->user()))
                                    <span class="text-muted small">(you)</span>
                                @endif
                            </td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->created_at->format('Y-m-d') }}</td>
                            <td class="text-end">
                                <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-primary" title="Edit" aria-label="Edit {{ $user->name }}"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                                @unless ($user->is(auth()->user()))
                                    <form method="POST" action="{{ route('users.destroy', $user) }}" class="d-inline"
                                          data-confirm="Delete the account for {{ $user->name }}?" onsubmit="return confirm(this.dataset.confirm);">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete" aria-label="Delete {{ $user->name }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                @if (request()->filled('search'))
                                    No users match "{{ request('search') }}".
                                @else
                                    No users yet.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $users->links() }}</div>

@endsection
