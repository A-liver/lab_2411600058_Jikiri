<section>
    <h5 class="mb-1 text-danger">Delete Account</h5>
    <p class="text-muted small mb-3">
        Deleting your account removes your sign-in permanently. Stock movements you recorded stay in the history without your name.
    </p>

    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#confirmUserDeletion">
        Delete My Account
    </button>

    <div class="modal fade" id="confirmUserDeletion" tabindex="-1" aria-labelledby="confirmUserDeletionLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('profile.destroy') }}" class="modal-content">
                @csrf
                @method('DELETE')

                <div class="modal-header">
                    <h5 class="modal-title" id="confirmUserDeletionLabel">Delete your account?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p class="small text-muted">Enter your password to confirm. This cannot be undone.</p>
                    <label for="delete_password" class="form-label">Password</label>
                    <input type="password" id="delete_password" name="password"
                           class="form-control @if ($errors->userDeletion->has('password')) is-invalid @endif"
                           autocomplete="current-password" required>
                    @if ($errors->userDeletion->has('password'))
                        <div class="text-danger small mt-1">{{ $errors->userDeletion->first('password') }}</div>
                    @endif
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Account</button>
                </div>
            </form>
        </div>
    </div>

    @if ($errors->userDeletion->isNotEmpty())
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    new bootstrap.Modal(document.getElementById('confirmUserDeletion')).show();
                });
            </script>
        @endpush
    @endif
</section>
