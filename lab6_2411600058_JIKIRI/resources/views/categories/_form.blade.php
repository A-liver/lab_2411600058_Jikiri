@php $item = $category ?? null; @endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Category Name</label>
        <input type="text" id="name" name="name" value="{{ old('name', $item->name ?? '') }}"
               class="form-control @error('name') is-invalid @enderror" maxlength="100" required>
        @error('name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Description</label>
        <textarea id="description" name="description" rows="3" maxlength="500"
                  class="form-control @error('description') is-invalid @enderror">{{ old('description', $item->description ?? '') }}</textarea>
        @error('description') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </div>
</div>
