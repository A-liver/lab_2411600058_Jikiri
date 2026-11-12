<section>
    <h5 class="mb-1">Change Password</h5>
    <p class="text-muted small mb-4">Use a long password that you do not use anywhere else.</p>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="update_password_current_password" class="form-label">Current Password</label>
            <input type="password" id="update_password_current_password" name="current_password"
                   class="form-control @if ($errors->updatePassword->has('current_password')) is-invalid @endif"
                   autocomplete="current-password">
            @if ($errors->updatePassword->has('current_password'))
                <div class="text-danger small mt-1">{{ $errors->updatePassword->first('current_password') }}</div>
            @endif
        </div>

        <div class="mb-3">
            <label for="update_password_password" class="form-label">New Password</label>
            <input type="password" id="update_password_password" name="password"
                   class="form-control @if ($errors->updatePassword->has('password')) is-invalid @endif"
                   autocomplete="new-password">
            @if ($errors->updatePassword->has('password'))
                <div class="text-danger small mt-1">{{ $errors->updatePassword->first('password') }}</div>
            @endif
        </div>

        <div class="mb-3">
            <label for="update_password_password_confirmation" class="form-label">Confirm New Password</label>
            <input type="password" id="update_password_password_confirmation" name="password_confirmation"
                   class="form-control" autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary">Change Password</button>
    </form>
</section>
