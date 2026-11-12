@php $p = $product ?? null; @endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Product Name</label>
        <input type="text" id="name" name="name" value="{{ old('name', $p->name ?? '') }}" class="form-control @error('name') is-invalid @enderror" required>
        @error('name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="sku" class="form-label">SKU</label>
        <input type="text" id="sku" name="sku" value="{{ old('sku', $p->sku ?? '') }}" class="form-control @error('sku') is-invalid @enderror" required>
        @error('sku') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Description</label>
        <textarea id="description" name="description" class="form-control" rows="2">{{ old('description', $p->description ?? '') }}</textarea>
    </div>

    <div class="col-md-4">
        <label for="category_id" class="form-label">Category</label>
        <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
            <option value="">Choose a category</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" {{ (string) old('category_id', $p->category_id ?? '') === (string) $category->id ? 'selected' : '' }}>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
        @error('category_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        @if ($categories->isEmpty())
            <div class="form-text">No categories yet. <a href="{{ route('categories.create') }}">Add a category</a> first.</div>
        @endif
    </div>
    <div class="col-md-4">
        <label for="brand_id" class="form-label">Brand</label>
        <select id="brand_id" name="brand_id" class="form-select @error('brand_id') is-invalid @enderror">
            <option value="">No brand</option>
            @foreach ($brands as $brand)
                <option value="{{ $brand->id }}" {{ (string) old('brand_id', $p->brand_id ?? '') === (string) $brand->id ? 'selected' : '' }}>
                    {{ $brand->name }}
                </option>
            @endforeach
        </select>
        @error('brand_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label for="supplier" class="form-label">Supplier</label>
        <input type="text" id="supplier" name="supplier" value="{{ old('supplier', $p->supplier ?? '') }}" class="form-control">
    </div>

    <div class="col-md-4">
        <label for="unit_price" class="form-label">Unit Price ($)</label>
        <input type="number" step="0.01" min="0" id="unit_price" name="unit_price" value="{{ old('unit_price', $p->unit_price ?? '') }}" class="form-control @error('unit_price') is-invalid @enderror" required>
        @error('unit_price') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        @if ($p)
            <label for="quantity_display" class="form-label">Quantity in Stock</label>
            <input type="text" id="quantity_display" value="{{ $p->quantity }}" class="form-control" readonly aria-describedby="quantity_help">
            <div class="form-text" id="quantity_help">Change it with Adjust Stock below.</div>
        @else
            <label for="quantity" class="form-label">Opening Quantity</label>
            <input type="number" min="0" id="quantity" name="quantity" value="{{ old('quantity', 0) }}" class="form-control @error('quantity') is-invalid @enderror" required>
            @error('quantity') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        @endif
    </div>
    <div class="col-md-4">
        <label for="reorder_level" class="form-label">Reorder Level</label>
        <input type="number" min="0" id="reorder_level" name="reorder_level" value="{{ old('reorder_level', $p->reorder_level ?? 0) }}" class="form-control @error('reorder_level') is-invalid @enderror" required>
        @error('reorder_level') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </div>
</div>
