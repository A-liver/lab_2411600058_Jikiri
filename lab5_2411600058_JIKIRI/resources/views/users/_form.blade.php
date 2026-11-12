@php $item = $user ?? null; @endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Full Name</label>
        <input type="text" id="name" name="name" value="{{ old('name', $item->name ?? '') }}"
               class="form-control @error('name') is-invalid @enderror" maxlength="255" required autocomplete="off">
        @error('name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="email" class="form-label">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email', $item->email ?? '') }}"
               class="form-control @error('email') is-invalid @enderror" maxlength="255" required autocomplete="off">
        @error('email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="password" class="form-label">{{ $item ? 'New Password' : 'Password' }}</label>
        <input type="password" id="password" name="password"
               class="form-control @error('password') is-invalid @enderror"
               autocomplete="new-password" {{ $item ? '' : 'required' }}>
        @error('password') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        @if ($item)
            <div class="form-text">Leave blank to keep the current password.</div>
        @endif
    </div>
    <div class="col-md-6">
        <label for="password_confirmation" class="form-label">Confirm Password</label>
        <input type="password" id="password_confirmation" name="password_confirmation"
               class="form-control" autocomplete="new-password" {{ $item ? '' : 'required' }}>
    </div>
</div>
