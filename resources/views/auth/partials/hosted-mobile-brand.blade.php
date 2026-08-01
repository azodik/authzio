<div class="mobile-brand">
    @if ($client->logo_url)
        <div class="mark"><img src="{{ $client->logo_url }}" alt=""></div>
    @else
        <div class="mark">{{ strtoupper(substr($client->name, 0, 1)) }}</div>
    @endif
    <span>{{ $client->name }}</span>
</div>
