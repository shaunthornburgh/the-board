<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <title>{{ $deal['reference'] ?? 'Deal' }} pack</title>
    <style>
        @page { margin: 28mm 18mm 24mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #292524; line-height: 1.45; }
        header { position: fixed; top: -18mm; left: 0; right: 0; font-size: 8pt; color: #78716c; border-bottom: 0.5pt solid #d6d3d1; padding-bottom: 2mm; }
        footer { position: fixed; bottom: -14mm; left: 0; right: 0; font-size: 8pt; color: #78716c; border-top: 0.5pt solid #d6d3d1; padding-top: 2mm; }
        h1 { font-size: 15pt; font-weight: normal; margin: 0 0 1mm; }
        h2 { font-size: 10pt; text-transform: uppercase; letter-spacing: 0.06em; color: #78716c; font-weight: normal; margin: 7mm 0 2mm; }
        .muted { color: #78716c; }
        table { width: 100%; border-collapse: collapse; }
        td, th { text-align: left; vertical-align: top; padding: 1.4mm 0; border-bottom: 0.5pt solid #e7e5e4; }
        th { font-weight: normal; color: #78716c; width: 38%; }
        .comps th, .comps td { width: auto; padding-right: 3mm; }
        .note { white-space: pre-line; }
    </style>
</head>
<body>
<header>
    The Board &middot; {{ $deal['reference'] }}@if ($deal['is_sample']) &middot; SAMPLE @endif
    <span style="float: right">Prepared for {{ $email }} &middot; {{ $stampedAt->format('j M Y H:i') }}</span>
</header>
<footer>
    Screen of listing material, not a valuation, not advice.
    <span style="float: right">Prepared for {{ $email }}</span>
</footer>

<h1>{{ $deal['address'] }}</h1>
<div class="muted">{{ collect([$deal['town'], $deal['county'], $deal['postcode']])->filter()->implode(', ') }}</div>

<h2>Summary</h2>
<table>
    <tr><th>Type</th><td>{{ $deal['type'] }}</td></tr>
    <tr><th>Asking price</th><td>{{ $deal['asking_price'] ? '£'.number_format($deal['asking_price']) : '—' }}</td></tr>
    <tr><th>Tenure</th><td>{{ $deal['tenure'] ?? '—' }}</td></tr>
    <tr><th>Tenant</th><td>{{ $deal['tenant'] ?? '—' }}</td></tr>
    <tr><th>Vacant area</th><td>{{ $deal['vacant_area_sq_ft'] ? number_format($deal['vacant_area_sq_ft']).' sq ft' : '—' }}</td></tr>
    <tr><th>Source</th><td>{{ $deal['source_name'] ?? '—' }}@if ($deal['source_url'])<br><span class="muted">{{ $deal['source_url'] }}</span>@endif</td></tr>
</table>

<h2>Bot appraisal</h2>
<table>
    <tr><th>Appraised</th><td>{{ $deal['appraised_at'] ?? '—' }}</td></tr>
</table>
@if ($deal['bot_note'])
    <p class="note">{{ $deal['bot_note'] }}</p>
@endif

@if (count($deal['evidence']))
    <h2>Evidence</h2>
    <table>
        @foreach ($deal['evidence'] as $label => $value)
            <tr><th>{{ $label }}</th><td>{{ $value }}</td></tr>
        @endforeach
    </table>
@endif

@if (count($deal['comps']))
    <h2>Comparables</h2>
    <table class="comps">
        <tr><th>Address</th><th>Price</th><th>Date</th><th>Note</th></tr>
        @foreach ($deal['comps'] as $comp)
            <tr>
                <td>{{ $comp['address'] ?? '' }}</td>
                <td>{{ isset($comp['price']) ? '£'.number_format($comp['price']) : '' }}</td>
                <td>{{ $comp['date'] ?? '' }}</td>
                <td>{{ $comp['note'] ?? '' }}</td>
            </tr>
        @endforeach
    </table>
@endif
</body>
</html>
