@if ($client->require_legal_accept)
    <label class="legal">
        <input type="checkbox" name="accept_legal" value="1" @checked(old('accept_legal')) required>
        <span>
            I agree to the
            @if ($client->terms_url)<a href="{{ $client->terms_url }}" target="_blank" rel="noreferrer">Terms</a>@else Terms @endif
            and
            @if ($client->privacy_url)<a href="{{ $client->privacy_url }}" target="_blank" rel="noreferrer">Privacy Policy</a>@else Privacy Policy @endif
        </span>
    </label>
@elseif ($client->terms_url || $client->privacy_url)
    <p class="meta" style="margin-top:14px;text-align:center">
        @if ($client->terms_url)<a href="{{ $client->terms_url }}" target="_blank" rel="noreferrer">Terms</a>@endif
        @if ($client->terms_url && $client->privacy_url) · @endif
        @if ($client->privacy_url)<a href="{{ $client->privacy_url }}" target="_blank" rel="noreferrer">Privacy</a>@endif
    </p>
@endif
