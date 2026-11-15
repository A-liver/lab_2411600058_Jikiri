<x-guest-layout>
    <x-slot name="subtitle">Confirm your password</x-slot>

    <p class="small text-muted mb-3">
        This is a secure area. Confirm your password before continuing.
    </p>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input id="password" type="password" name="password"
                   class="form-control @error('password') is-invalid @enderror"
                   required autofocus autocomplete="current-password">
            @error('password')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2">Confirm</button>
    </form>
</x-guest-layout>