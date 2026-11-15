<section>
    <h5 class="mb-1">Profile Information</h5>
    <p class="text-muted small mb-4">Update the name and email address used to sign in.</p>

    <form method="POST" action="{{ route('profile.update') }}">
        @csrf
        @method('PATCH')

        <div class="mb-3">
            <label for="name" class="form-label">Name</label>
            <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}"
                   class="form-control @error('name') is-invalid @enderror" required autocomplete="name">
            @error('name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}"
                   class="form-control @error('email') is-invalid @enderror" required autocomplete="username">
            @error('email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="btn btn-primary">Save Profile</button>
    </form>
</section>
