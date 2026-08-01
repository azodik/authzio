{{-- Shared hosted-login surface. Page must set --primary and --bg. --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<style>
    :root, html.theme-light {
        --ink: #111827;
        --muted: #5b6572;
        --faint: #8b95a1;
        --paper: #ffffff;
        --line: #e5e7eb;
        --field: #f9fafb;
        --danger: #dc2626;
        --ok: #047857;
        color-scheme: light;
    }
    html.theme-dark {
        --ink: #edf2ef;
        --muted: #a8b3ac;
        --faint: #7a8680;
        --paper: #151c19;
        --line: #2a3531;
        --field: #1c2622;
        --danger: #f97066;
        --ok: #47cd89;
        color-scheme: dark;
    }
    html { color-scheme: light; }
    html.theme-dark { color-scheme: dark; }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        min-height: 100vh;
        font-family: "Plus Jakarta Sans", system-ui, sans-serif;
        color: var(--ink);
        background: var(--bg, #f3f4f6);
    }
    .layout {
        min-height: 100vh;
        display: grid;
        grid-template-columns: minmax(240px, 38%) 1fr;
    }
    .layout--form-right {
        grid-template-columns: minmax(240px, 38%) 1fr;
    }
    .layout--form-left {
        grid-template-columns: 1fr minmax(240px, 38%);
    }
    .layout--form-left .brand { order: 2; }
    .layout--form-left .main { order: 1; }
    .layout--centered {
        grid-template-columns: 1fr;
    }
    .layout--centered .brand { display: none; }
    .layout--centered .mobile-brand { display: flex; }
    .layout--centered .main {
        min-height: 100vh;
        place-items: center;
    }
    .brand {
        position: relative;
        overflow: hidden;
        padding: 40px 36px;
        color: #fff;
        background:
            linear-gradient(160deg, color-mix(in srgb, var(--primary, #0F766E) 88%, #000) 0%, var(--primary, #0F766E) 55%, color-mix(in srgb, var(--primary, #0F766E) 75%, #fff) 100%);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        animation: brand-in 520ms cubic-bezier(0.22, 1, 0.36, 1) both;
    }
    .brand::after {
        content: "";
        position: absolute;
        inset: auto -20% -30% 20%;
        height: 70%;
        background: radial-gradient(circle, rgba(255,255,255,0.18), transparent 65%);
        pointer-events: none;
    }
    .brand-top, .brand-bottom { position: relative; z-index: 1; }
    .brand-mark {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        object-fit: contain;
        background: rgba(255,255,255,0.16);
        display: grid;
        place-items: center;
        font-family: "Space Grotesk", sans-serif;
        font-weight: 700;
        font-size: 1.2rem;
        border: 1px solid rgba(255,255,255,0.22);
    }
    .brand-mark img {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        object-fit: contain;
        background: #fff;
    }
    .brand h2 {
        margin: 28px 0 0;
        font-family: "Space Grotesk", sans-serif;
        font-size: clamp(1.8rem, 3vw, 2.35rem);
        font-weight: 650;
        letter-spacing: -0.03em;
        line-height: 1.15;
        max-width: 12ch;
    }
    .brand p {
        margin: 14px 0 0;
        max-width: 28ch;
        font-size: 0.95rem;
        line-height: 1.55;
        color: rgba(255,255,255,0.82);
    }
    .brand-bottom {
        font-size: 0.78rem;
        color: rgba(255,255,255,0.7);
        letter-spacing: 0.02em;
    }
    .main {
        display: grid;
        place-items: center;
        padding: 40px 24px;
        background:
            linear-gradient(180deg, var(--paper) 0%, color-mix(in srgb, var(--bg, #f3f4f6) 70%, var(--paper)) 100%);
        animation: form-in 480ms 60ms cubic-bezier(0.22, 1, 0.36, 1) both;
    }
    .form-chrome {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 12px;
        margin-bottom: 8px;
        min-height: 28px;
    }
    .locale-switch select {
        appearance: none;
        border: 1px solid var(--line);
        background: var(--field);
        color: var(--ink);
        border-radius: 6px;
        font: inherit;
        font-size: 0.8rem;
        font-weight: 550;
        padding: 6px 28px 6px 10px;
        background-image: linear-gradient(45deg, transparent 50%, var(--muted) 50%), linear-gradient(135deg, var(--muted) 50%, transparent 50%);
        background-position: calc(100% - 14px) calc(50% - 2px), calc(100% - 9px) calc(50% - 2px);
        background-size: 5px 5px, 5px 5px;
        background-repeat: no-repeat;
        cursor: pointer;
    }
    .locale-switch select:focus {
        outline: 2px solid color-mix(in srgb, var(--primary, #0F766E) 45%, transparent);
        outline-offset: 1px;
    }
    @keyframes brand-in {
        from { opacity: 0; transform: translateX(-10px); }
        to { opacity: 1; transform: none; }
    }
    @keyframes form-in {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: none; }
    }
    @media (prefers-reduced-motion: reduce) {
        .brand, .main { animation: none; }
    }
    .form-wrap {
        width: min(100%, 400px);
    }
    .mobile-brand {
        display: none;
        align-items: center;
        gap: 12px;
        margin-bottom: 28px;
    }
    .mobile-brand .mark {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: var(--primary, #0F766E);
        color: #fff;
        display: grid;
        place-items: center;
        font-family: "Space Grotesk", sans-serif;
        font-weight: 700;
    }
    .mobile-brand .mark img {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        object-fit: contain;
    }
    .mobile-brand span {
        font-weight: 650;
        font-size: 0.95rem;
    }
    h1 {
        margin: 0;
        font-family: "Space Grotesk", sans-serif;
        font-size: 1.75rem;
        font-weight: 650;
        letter-spacing: -0.03em;
        line-height: 1.2;
    }
    .lede {
        margin: 8px 0 0;
        color: var(--muted);
        font-size: 0.95rem;
        line-height: 1.5;
    }
    .methods {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 18px;
        margin: 28px 0 8px;
        padding-bottom: 14px;
        border-bottom: 1px solid var(--line);
    }
    .methods button {
        border: 0;
        background: none;
        padding: 0;
        font: inherit;
        font-size: 0.9rem;
        font-weight: 550;
        color: var(--faint);
        cursor: pointer;
        position: relative;
    }
    .methods button:hover { color: var(--ink); }
    .methods button.active {
        color: var(--ink);
        font-weight: 650;
    }
    .methods button.active::after {
        content: "";
        position: absolute;
        left: 0;
        right: 0;
        bottom: -15px;
        height: 2px;
        background: var(--primary, #0F766E);
        border-radius: 2px;
    }
    .step { display: none; }
    .step.active { display: block; }
    .alert {
        margin-top: 16px;
        padding: 11px 12px;
        border-radius: 8px;
        font-size: 0.875rem;
        line-height: 1.4;
    }
    .alert.error {
        color: #991b1b;
        background: #fef2f2;
        border: 1px solid #fecaca;
    }
    .alert.ok {
        color: var(--ok);
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
    }
    .social { margin-top: 22px; display: grid; gap: 10px; }
    .divider {
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        gap: 12px;
        align-items: center;
        margin: 20px 0 4px;
        color: var(--faint);
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }
    .divider::before, .divider::after {
        content: "";
        height: 1px;
        background: var(--line);
    }
    label.field {
        display: block;
        margin-top: 18px;
        font-size: 0.8rem;
        font-weight: 650;
        color: var(--ink);
    }
    label.field input {
        display: block;
        width: 100%;
        margin-top: 7px;
        border: 1px solid var(--line);
        background: var(--field);
        color: var(--ink);
        border-radius: 8px;
        padding: 12px 13px;
        font: inherit;
        font-size: 0.95rem;
        outline: none;
        transition: border-color 140ms ease, box-shadow 140ms ease, background 140ms ease;
    }
    label.field input::placeholder { color: var(--faint); }
    label.field input:focus {
        border-color: var(--primary, #0F766E);
        background: var(--paper);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary, #0F766E) 20%, transparent);
    }
    .hint {
        margin: 8px 0 0;
        font-size: 0.82rem;
        color: var(--muted);
        line-height: 1.45;
    }
    .row-links {
        display: flex;
        justify-content: flex-end;
        margin-top: 10px;
    }
    .row-links a, .meta a, .legal a, .text-link {
        color: var(--primary, #0F766E);
        font-weight: 650;
        text-decoration: none;
    }
    .row-links a:hover, .meta a:hover, .legal a:hover, .text-link:hover {
        text-decoration: underline;
    }
    .row-links a, .text-link { font-size: 0.84rem; }
    button.text-link, a.text-link {
        border: 0;
        background: none;
        padding: 0;
        cursor: pointer;
        font: inherit;
    }
    .legal {
        display: flex;
        gap: 10px;
        align-items: flex-start;
        margin-top: 16px;
        font-size: 0.8rem;
        color: var(--muted);
        line-height: 1.45;
    }
    .legal input[type="checkbox"] {
        width: 16px;
        height: 16px;
        margin-top: 2px;
        flex-shrink: 0;
        accent-color: var(--primary, #0F766E);
    }
    button.primary, a.primary {
        display: grid;
        place-items: center;
        width: 100%;
        margin-top: 22px;
        border: 0;
        border-radius: 8px;
        background: var(--primary, #0F766E);
        color: #fff;
        font: inherit;
        font-weight: 700;
        font-size: 0.95rem;
        padding: 13px 16px;
        min-height: 48px;
        cursor: pointer;
        text-decoration: none;
        transition: filter 140ms ease, transform 140ms ease;
    }
    button.primary:hover, a.primary:hover { filter: brightness(1.05); }
    button.primary:active, a.primary:active { transform: translateY(1px); }
    button.ghost, a.ghost {
        display: grid;
        place-items: center;
        width: 100%;
        margin-top: 10px;
        border: 1px solid var(--line);
        border-radius: 8px;
        background: var(--paper);
        color: var(--ink);
        font: inherit;
        font-weight: 600;
        font-size: 0.92rem;
        padding: 12px 16px;
        min-height: 46px;
        cursor: pointer;
        text-decoration: none;
    }
    button.ghost:hover, a.ghost:hover { background: var(--field); }
    .footer {
        margin-top: 28px;
        display: grid;
        gap: 10px;
    }
    .meta {
        margin: 0;
        font-size: 0.875rem;
        color: var(--muted);
    }
    .fine {
        margin: 0;
        font-size: 0.72rem;
        color: var(--faint);
        letter-spacing: 0.01em;
    }
    .preview-badge {
        position: fixed;
        top: 14px;
        right: 14px;
        z-index: 5;
        background: #111827;
        color: #fff;
        font-size: 0.68rem;
        font-weight: 650;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        padding: 7px 10px;
        border-radius: 6px;
    }
    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }
    @media (max-width: 860px) {
        .layout { grid-template-columns: 1fr; }
        .brand { display: none; }
        .mobile-brand { display: flex; }
        .main {
            padding: 28px 20px 40px;
            min-height: 100vh;
            align-content: start;
            place-items: stretch;
        }
        .form-wrap { width: 100%; max-width: 420px; margin: 0 auto; }
    }
</style>
