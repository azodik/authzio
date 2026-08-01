# Security Policy

## Supported versions

Security fixes are applied to the latest `main` branch of Authzio.

## Reporting a vulnerability

Please **do not** open a public GitHub issue for security-sensitive findings.

Prefer a private report to **Azodik Consulting Private Limited** via [azodik.com](https://azodik.com).

Include:

- Affected version or commit SHA
- Steps to reproduce
- Impact (data exposure, auth bypass, billing manipulation, etc.)

We will acknowledge reports and work on a fix before any public disclosure.

## Safe defaults for self-hosters

- Keep `APP_DEBUG=false` in production
- Never commit `.env`
- Do not seed the shared demo account (`--with-demo`) on production systems
- Require Dodo webhook signatures (`DODO_PAYMENTS_WEBHOOK_SECRET`); unsigned webhook deliveries are always rejected
- Put TLS in front of Docker / PHP and keep database/Redis ports private
