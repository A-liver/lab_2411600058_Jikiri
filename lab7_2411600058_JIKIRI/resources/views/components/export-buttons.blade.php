@props(['dataset'])

@php $filters = request()->except('page'); @endphp

<div class="btn-group btn-group-sm" role="group" aria-label="Export">
    <a href="{{ route('exports.download', ['dataset' => $dataset, 'format' => 'csv'] + $filters) }}" class="btn btn-outline-success">Export CSV</a>
    <a href="{{ route('exports.download', ['dataset' => $dataset, 'format' => 'pdf'] + $filters) }}" class="btn btn-outline-danger">Export PDF</a>
</div>