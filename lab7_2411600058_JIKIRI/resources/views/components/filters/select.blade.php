@props(['name', 'label', 'options' => [], 'selected' => null, 'placeholder' => 'All', 'colClass' => 'col-md-2'])

<div class="{{ $colClass }}">
    <label for="{{ $name }}" class="form-label">{{ $label }}</label>
    <select id="{{ $name }}" name="{{ $name }}" class="form-select">
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $text }}</option>
        @endforeach
    </select>
</div>