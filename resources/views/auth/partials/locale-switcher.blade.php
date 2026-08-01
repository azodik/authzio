@if (($allow_locale_switch ?? false) && count($available_locales ?? []) > 1)
    <div class="form-chrome">
        <label class="locale-switch">
            <span class="sr-only">{{ __('Language') }}</span>
            <select id="hosted-locale" aria-label="{{ __('Language') }}">
                @foreach ($available_locales as $code)
                    <option value="{{ $code }}" @selected(($locale ?? 'en') === $code)>{{ strtoupper($code) }}</option>
                @endforeach
            </select>
        </label>
    </div>
    <script>
        (() => {
            const select = document.getElementById('hosted-locale');
            if (!select) return;
            select.addEventListener('change', () => {
                const url = new URL(window.location.href);
                url.searchParams.set('ui_locales', select.value);
                window.location.assign(url.toString());
            });
        })();
    </script>
@endif
