@props([
    'iconClass' => 'size-5',
    'label' => null,
    'labelClass' => 'ml-2 text-sm font-medium',
    'ariaLabel' => 'Authzio on GitHub',
])

<a
    href="https://github.com/azodik/authzio"
    {{ $attributes->class('inline-flex items-center justify-center text-ink-soft/70 transition-colors hover:text-ink') }}
    aria-label="{{ $ariaLabel }}"
    rel="noopener noreferrer"
    target="_blank"
>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="currentColor"
        aria-hidden="true"
        @class([$iconClass, 'shrink-0'])
        width="20"
        height="20"
    >
        <path d="M12 0C5.37 0 0 5.37 0 12c0 5.3 3.44 9.8 8.21 11.39.6.11.82-.26.82-.58 0-.28-.01-1.03-.02-2.02-3.34.73-4.04-1.61-4.04-1.61-.55-1.39-1.33-1.76-1.33-1.76-1.09-.74.08-.73.08-.73 1.2.09 1.84 1.24 1.84 1.24 1.07 1.83 2.8 1.3 3.49.99.11-.78.42-1.3.76-1.6-2.67-.3-5.47-1.33-5.47-5.93 0-1.31.47-2.38 1.24-3.22-.12-.3-.54-1.52.12-3.17 0 0 1.01-.32 3.3 1.23a11.5 11.5 0 0 1 6 0c2.29-1.55 3.3-1.23 3.3-1.23.66 1.65.24 2.87.12 3.17.77.84 1.24 1.91 1.24 3.22 0 4.61-2.81 5.62-5.49 5.92.43.37.81 1.1.81 2.22 0 1.6-.01 2.89-.01 3.28 0 .32.22.7.83.58C20.56 21.8 24 17.3 24 12 24 5.37 18.63 0 12 0Z"/>
    </svg>
    @if ($label)
        <span class="{{ $labelClass }}">{{ $label }}</span>
    @endif
</a>
