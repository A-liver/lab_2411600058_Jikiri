<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #283618; }
        h1 { font-size: 16px; margin: 0 0 2px; color: #606C38; }
        .meta { color: #666666; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #606C38; color: #ffffff; text-align: left; padding: 5px; }
        td { padding: 4px 5px; border-bottom: 1px solid #dddddd; }
        td.alt { background: #f7f5e6; }
        .num { text-align: right; }
        .summary { margin-top: 14px; width: auto; }
        .summary td { border: none; padding: 2px 8px 2px 0; }
        .note { margin-top: 8px; color: #b02a37; }
        .footer { position: fixed; bottom: 0; left: 0; font-size: 8px; color: #888888; }
    </style>
</head>
<body>
    <h1>{{ $appName }}: {{ $title }}</h1>
    <div class="meta">{{ $subtitle }} &middot; Generated {{ $generatedAt }}</div>

    <table>
        <thead>
            <tr>
                @foreach ($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        @php $numeric = preg_match('/^[+\-]?\$?[\d,]+(\.\d+)?%?$/', (string) $cell); @endphp
                        <td class="{{ $numeric ? 'num' : '' }} {{ $loop->parent->even ? 'alt' : '' }}">{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($headings) }}">No data for these filters.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($truncated)
        <div class="note">Only the first {{ number_format($limit) }} rows are shown. Use the CSV export for the full list.</div>
    @endif

    <table class="summary">
        @foreach ($summary as $label => $value)
            <tr><td><strong>{{ $label }}</strong></td><td>{{ $value }}</td></tr>
        @endforeach
    </table>

    <div class="footer">{{ $appName }}</div>
</body>
</html>