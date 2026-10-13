@extends('layouts.app')

@section('title', $report['title'])
@section('page-title', $report['title'])

@section('content')
@php
    $tabs = [
        'movement' => ['label' => 'Stock Movement', 'url' => route('reports.movement')],
        'low-stock' => ['label' => 'Low Stock', 'url' => route('reports.lowStock')],
        'valuation' => ['label' => 'Inventory Valuation', 'url' => route('reports.valuation')],
    ];
@endphp

<ul class="nav nav-pills mb-3">
    @foreach ($tabs as $key => $tab)
        <li class="nav-item">
            <a class="nav-link {{ $report['active'] === $key ? 'active' : '' }}" href="{{ $tab['url'] }}">{{ $tab['label'] }}</a>
        </li>
    @endforeach
</ul>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    @if ($report['dates'])
        <form method="GET" class="d-flex gap-2 align-items-end">
            <div>
                <label for="from" class="form-label small mb-0">From</label>
                <input type="date" id="from" name="from" class="form-control form-control-sm" value="{{ $report['dates']['from'] }}">
            </div>
            <div>
                <label for="to" class="form-label small mb-0">To</label>
                <input type="date" id="to" name="to" class="form-control form-control-sm" value="{{ $report['dates']['to'] }}">
            </div>
            <button type="submit" class="btn btn-sm btn-primary">Generate</button>
        </form>
    @else
        <div class="text-muted">{{ $report['subtitle'] }}</div>
    @endif

    <div class="d-flex gap-2">
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-sm btn-outline-success">Export CSV</a>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">Print / Save as PDF</button>
    </div>
</div>

<div class="mb-3">
    @foreach ($report['summary'] as $label => $value)
        <span class="badge bg-light text-dark border me-2 p-2">{{ $label }}: <strong>{{ $value }}</strong></span>
    @endforeach
</div>

<div class="card chart-card">
    <div class="table-responsive">
        <table class="table table-striped table-hover table-sm align-middle mb-0">
            <thead>
                <tr>
                    @foreach ($report['columns'] as $col)
                        <th>{{ $col }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($report['rows'] as $row)
                    <tr>
                        @foreach ($row as $cell)
                            @php $numeric = preg_match('/^[+\-]?[\d,]+(\.\d+)?$/', (string) $cell); @endphp
                            <td class="{{ $numeric ? 'text-end' : '' }}">{{ $cell !== '' ? $cell : '—' }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($report['columns']) }}" class="text-center text-muted py-4">No data for this report.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection