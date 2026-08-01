# Contributing to Authzio

Thanks for helping improve Authzio.

Please read the [Code of Conduct](CODE_OF_CONDUCT.md). By participating, you agree to follow it.

## Development

1. Fork and clone the repo
2. Copy `.env.example` → `.env` and configure Postgres (see README)
3. `composer install && npm install`
4. `php artisan key:generate && php artisan migrate --seed`
5. `npm run dev` (or `npm run build` for production assets)

## Quality checks (required for PRs)

```bash
./vendor/bin/pint --test
npm run typecheck
npm run lint
composer test
```

Keep `VERSION` and `package.json` `"version"` in sync when bumping SemVer releases.
Build numbers are assigned by CI on publish (not edited in the repo).

## Pull requests

- Open PRs against `main` — direct pushes to `main` are blocked
- Prefer small, focused PRs
- Include a short summary of *why*
- Prefer [Conventional Commits](https://www.conventionalcommits.org/) in PR titles / commit messages (`feat:`, `fix:`, `docs:`, `chore:`) so [`CHANGELOG.md`](CHANGELOG.md) groups cleanly on release
- Do not commit `.env`, secrets, or production credentials

## Security

Report vulnerabilities privately — see [SECURITY.md](SECURITY.md). Do not open public issues for security-sensitive findings.

## License

By contributing, you agree that your contributions are licensed under the [MIT License](LICENSE).
