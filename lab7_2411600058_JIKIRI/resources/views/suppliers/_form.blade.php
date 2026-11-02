@php $s = $supplier ?? null; @endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Company Name</label>
        <input type="text" id="name" name="name" value="{{ old('name', $s->name ?? '') }}"
               class="form-control @error('name') is-invalid @enderror" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="contact_person" class="form-label">Contact Person</label>
        <input type="text" id="contact_person" name="contact_person" value="{{ old('contact_person', $s->contact_person ?? '') }}"
               class="form-control @error('contact_person') is-invalid @enderror">
        @error('contact_person') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="email" class="form-label">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email', $s->email ?? '') }}"
               class="form-control @error('email') is-invalid @enderror">
        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="phone" class="form-label">Phone</label>
        <input type="text" id="phone" name="phone" value="{{ old('phone', $s->phone ?? '') }}"
               class="form-control @error('phone') is-invalid @enderror">
        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <label for="address" class="form-label">Address</label>
        <textarea id="address" name="address" rows="2"
                  class="form-control @error('address') is-invalid @enderror">{{ old('address', $s->address ?? '') }}</textarea>
        @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label for="payment_terms" class="form-label">Payment Terms</label>
        <select id="payment_terms" name="payment_terms" class="form-select @error('payment_terms') is-invalid @enderror" required>
            @foreach (\App\Models\Supplier::PAYMENT_TERMS as $term)
                <option value="{{ $term }}" @selected(old('payment_terms', $s->payment_terms ?? 'Net 30') === $term)>{{ $term }}</option>
            @endforeach
        </select>
        @error('payment_terms') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label for="lead_time_days" class="form-label">Lead Time (days)</label>
        <input type="number" id="lead_time_days" name="lead_time_days" min="0" max="365"
               value="{{ old('lead_time_days', $s->lead_time_days ?? 7) }}"
               class="form-control @error('lead_time_days') is-invalid @enderror" required>
        @error('lead_time_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label for="minimum_order_amount" class="form-label">Minimum Order Amount ($)</label>
        <input type="number" id="minimum_order_amount" name="minimum_order_amount" min="0" step="0.01"
               value="{{ old('minimum_order_amount', $s->minimum_order_amount ?? 0) }}"
               class="form-control @error('minimum_order_amount') is-invalid @enderror" required>
        @error('minimum_order_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <input type="hidden" name="is_active" value="0">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                   @checked((bool) old('is_active', $s->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active supplier (can receive purchase orders)</label>
        </div>
    </div>
</div>