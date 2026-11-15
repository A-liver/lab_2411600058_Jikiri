<x-guest-layout>
    <x-slot name="subtitle">Verify your email</x-slot>

    <p class="small text-muted mb-3">
        Before you continue, open the link we emailed you to verify your address.
        If it did not arrive, we can send another.
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-success">A new verification link was sent to the email address you registered with.</div>
    @endif

    <div class="d-flex align-items-center justify-content-between gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn-primary">Resend Verification Email</button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-link">Log Out</button>
        </form>
    </div>
</x-guest-layout>