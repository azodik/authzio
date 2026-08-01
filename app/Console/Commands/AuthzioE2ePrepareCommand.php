<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class AuthzioE2ePrepareCommand extends Command
{
    protected $signature = 'authzio:e2e-prepare
                            {--skip-build : Skip npm run build}
                            {--skip-env : Do not copy .env.e2e.example over .env}';

    protected $description = 'Prepare a fresh SQLite database and fixtures for Playwright E2E';

    /**
     * Dodo / billing keys preserved across prepare so credentials in .env are not wiped.
     *
     * @var list<string>
     */
    private const PRESERVE_ENV_KEYS = [
        'DODO_PAYMENTS_API_KEY',
        'DODO_PAYMENTS_WEBHOOK_SECRET',
        'DODO_PAYMENTS_ENVIRONMENT',
        'DODO_PAYMENTS_BASE_URL',
        'DODO_PAYMENTS_RETURN_URL',
        'DODO_PRODUCT_STARTER',
        'DODO_PRODUCT_GROWTH',
        'DODO_PRODUCT_SCALE',
    ];

    public function handle(): int
    {
        $this->components->info('Authzio E2E prepare');

        if (! $this->option('skip-env')) {
            $example = base_path('.env.e2e.example');
            if (! File::exists($example)) {
                $this->components->error('.env.e2e.example is missing.');

                return self::FAILURE;
            }

            $preserved = $this->readEnvKeys(base_path('.env'), self::PRESERVE_ENV_KEYS);
            if ($preserved === []) {
                $preserved = $this->readEnvKeys(base_path('.env.e2e'), self::PRESERVE_ENV_KEYS);
            }

            File::copy($example, base_path('.env'));
            $this->components->twoColumnDetail('.env', 'copied from .env.e2e.example');

            if ($preserved !== []) {
                $this->writeEnvKeys(base_path('.env'), $preserved);
                $this->components->twoColumnDetail(
                    'Dodo keys',
                    'preserved ('.count($preserved).' keys)',
                );
            }

            File::copy(base_path('.env'), base_path('.env.e2e'));
            $this->components->twoColumnDetail('.env.e2e', 'synced from .env');
        }

        $database = database_path('e2e.sqlite');
        File::ensureDirectoryExists(dirname($database));
        if (File::exists($database)) {
            File::delete($database);
        }
        File::put($database, '');
        $this->components->twoColumnDetail('SQLite', 'database/e2e.sqlite');

        // Subprocesses reload .env (this process may still hold the previous env).
        if (! $this->runArtisan(['config:clear'])) {
            return self::FAILURE;
        }

        if (! $this->runArtisan(['migrate:fresh', '--force'])) {
            return self::FAILURE;
        }

        if (! $this->runArtisan([
            'db:seed',
            '--class=Database\\Seeders\\ConsoleE2eSeeder',
            '--force',
        ])) {
            return self::FAILURE;
        }

        if (! $this->option('skip-build')) {
            $manifest = public_path('build/manifest.json');
            if (! File::exists($manifest)) {
                $this->components->task('npm run build', function (): void {
                    passthru('npm run build', $code);
                    if ($code !== 0) {
                        throw new \RuntimeException('npm run build failed');
                    }
                });
            } else {
                $this->components->twoColumnDetail('Assets', 'public/build already present');
            }
        }

        $this->components->info('E2E database ready. Start Mailpit, then: npm run test:e2e');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    private function readEnvKeys(string $path, array $keys): array
    {
        if (! File::exists($path)) {
            return [];
        }

        $values = [];
        $lines = preg_split("/\r\n|\n|\r/", File::get($path)) ?: [];

        foreach ($lines as $line) {
            if ($line === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }

            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }

            $key = substr($line, 0, $eq);
            if (! in_array($key, $keys, true)) {
                continue;
            }

            $raw = substr($line, $eq + 1);
            $value = trim($raw);
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            if ($value === '') {
                continue;
            }

            $values[$key] = $value;
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function writeEnvKeys(string $path, array $values): void
    {
        $contents = File::get($path);

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->quoteEnvValue($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $contents) === 1) {
                $contents = preg_replace($pattern, $line, $contents) ?? $contents;
            } else {
                $contents = rtrim($contents)."\n".$line."\n";
            }
        }

        File::put($path, $contents);
    }

    private function quoteEnvValue(string $value): string
    {
        if (str_contains($value, '${')) {
            return '"'.$value.'"';
        }

        if ($value === '' || preg_match('/\s|#|"|\'/', $value) === 1) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return $value;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function runArtisan(array $arguments): bool
    {
        $label = $arguments[0] ?? 'artisan';
        $ok = true;

        $this->components->task($label, function () use ($arguments, $label, &$ok): void {
            $process = new Process([PHP_BINARY, 'artisan', ...$arguments], base_path());
            $process->setTimeout(300);
            $process->run();

            if ($process->getOutput() !== '') {
                $this->output->write($process->getOutput());
            }
            if ($process->getErrorOutput() !== '') {
                $this->output->write($process->getErrorOutput());
            }

            if (! $process->isSuccessful()) {
                $ok = false;
                throw new \RuntimeException('php artisan '.$label.' failed');
            }
        });

        return $ok;
    }
}
