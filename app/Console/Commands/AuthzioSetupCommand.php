<?php

namespace App\Console\Commands;

use App\Enums\OrgPermission;
use App\Models\BillingPlan;
use App\Models\Permission;
use App\Models\Role;
use App\Services\OrganizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AuthzioSetupCommand extends Command
{
    protected $signature = 'authzio:setup
                            {--force : Overwrite existing .env keys that are empty or placeholders}
                            {--skip-migrate : Skip running migrations}
                            {--skip-seed : Skip seeding plans and permissions}
                            {--with-demo : Seed the read-only demo@authzio.com account and Demo Org}';

    protected $description = 'Create/update .env, migrate, seed billing plans & permissions, print a setup summary';

    public function handle(OrganizationService $organizations): int
    {
        $this->components->info('Authzio setup');

        $envPath = base_path('.env');
        $examplePath = base_path('.env.example');

        if (! File::exists($envPath)) {
            if (! File::exists($examplePath)) {
                $this->components->error('.env.example is missing.');

                return self::FAILURE;
            }

            File::copy($examplePath, $envPath);
            $this->components->twoColumnDetail('.env', 'created from .env.example');
        } else {
            $this->components->twoColumnDetail('.env', 'already present');
        }

        $this->ensureEnvKeys($envPath);

        if (blank(env('APP_KEY')) || $this->envValue($envPath, 'APP_KEY') === '') {
            Artisan::call('key:generate', ['--force' => true]);
            $this->components->twoColumnDetail('APP_KEY', 'generated');
        } else {
            $this->components->twoColumnDetail('APP_KEY', 'ok');
        }

        if (! $this->option('skip-migrate')) {
            $this->components->task('Running migrations', function (): void {
                Artisan::call('migrate', ['--force' => true]);
            });
        }

        if (! $this->option('skip-seed')) {
            $this->components->task('Seeding billing plans', function (): void {
                Artisan::call('db:seed', [
                    '--class' => 'Database\\Seeders\\BillingPlanSeeder',
                    '--force' => true,
                ]);
            });

            $this->components->task('Syncing permission catalog', function () use ($organizations): void {
                $organizations->syncPermissionCatalog();
                $this->resyncSystemRolePermissions();
            });

            if ($this->option('with-demo')) {
                $this->components->task('Seeding demo account', function (): void {
                    Artisan::call('db:seed', [
                        '--class' => 'Database\\Seeders\\AuthzioSeeder',
                        '--force' => true,
                    ]);
                });
            }
        }

        $this->newLine();
        $this->printSummary($envPath);

        $this->newLine();
        $this->components->info('Next steps');
        $this->line('  npm run build');
        $this->line('  php artisan serve   # or open your Herd site');
        $this->line('  Console: '.rtrim((string) $this->envValue($envPath, 'APP_URL'), '/').'/console');

        return self::SUCCESS;
    }

    private function ensureEnvKeys(string $envPath): void
    {
        $defaults = [
            'APP_NAME' => 'Authzio',
            'APP_URL' => 'https://authzio.test',
            'APP_LOCALE' => 'en',
            'APP_FALLBACK_LOCALE' => 'en',
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '5432',
            'DB_DATABASE' => 'authzio',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => '',
            'SESSION_DRIVER' => 'database',
            'QUEUE_CONNECTION' => 'database',
            'CACHE_STORE' => 'database',
            'MAIL_MAILER' => 'log',
            'MAIL_FROM_ADDRESS' => 'hello@authzio.test',
            'MAIL_FROM_NAME' => '${APP_NAME}',
            'SANCTUM_STATEFUL_DOMAINS' => 'authzio.test,localhost,127.0.0.1',
            'AUTHZIO_BILLING_ENABLED' => 'false',
            'AUTHZIO_BILLING_CURRENCY' => 'USD',
            'AUTHZIO_MAU_TIMEZONE' => 'UTC',
            'DODO_PAYMENTS_BASE_URL' => 'https://test.dodopayments.com',
            'DODO_PAYMENTS_ENVIRONMENT' => 'test_mode',
            'DODO_PAYMENTS_RETURN_URL' => '${APP_URL}/console/{organization_id}/billing',
        ];

        foreach ($defaults as $key => $value) {
            $current = $this->envValue($envPath, $key);
            $missing = $current === null;
            $empty = $current === '';
            $shouldWrite = $missing || ($this->option('force') && $empty);

            if (! $shouldWrite) {
                continue;
            }

            $this->writeEnv($envPath, $key, $value);
            $this->components->twoColumnDetail($key, $missing ? 'added' : 'updated');
        }
    }

    private function envValue(string $envPath, string $key): ?string
    {
        $contents = File::get($envPath);
        if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $matches)) {
            return null;
        }

        $value = trim($matches[1]);
        if (Str::startsWith($value, '"') && Str::endsWith($value, '"')) {
            $value = substr($value, 1, -1);
        }

        return $value;
    }

    private function writeEnv(string $envPath, string $key, string $value): void
    {
        $contents = File::get($envPath);
        $line = $key.'='.$value;

        if (preg_match('/^'.preg_quote($key, '/').'=.*/m', $contents) === 1) {
            $contents = preg_replace('/^'.preg_quote($key, '/').'=.*/m', $line, $contents) ?? $contents;
        } else {
            $contents = rtrim($contents).PHP_EOL.$line.PHP_EOL;
        }

        File::put($envPath, $contents);
    }

    private function resyncSystemRolePermissions(): void
    {
        $map = Permission::query()->pluck('id', 'slug');

        Role::query()->with('organization')->each(function (Role $role) use ($map): void {
            if ($role->is_owner) {
                $role->permissions()->sync($map->values()->all());

                return;
            }

            if ($role->slug === 'admin') {
                $role->permissions()->sync(
                    $map->only(OrgPermission::adminDefaults())->values()->all(),
                );

                return;
            }

            if ($role->slug === 'member') {
                $role->permissions()->sync(
                    $map->only(OrgPermission::memberDefaults())->values()->all(),
                );
            }
        });
    }

    private function printSummary(string $envPath): void
    {
        $this->components->info('Configuration summary');

        $keys = [
            'APP_NAME',
            'APP_URL',
            'APP_LOCALE',
            'DB_CONNECTION',
            'DB_DATABASE',
            'MAIL_MAILER',
            'AUTHZIO_BILLING_ENABLED',
            'DODO_PAYMENTS_ENVIRONMENT',
        ];

        foreach ($keys as $key) {
            $value = $this->envValue($envPath, $key) ?? '(missing)';
            if (Str::contains(Str::lower($key), ['key', 'secret', 'password']) && $value !== '' && $value !== '(missing)') {
                $value = Str::mask($value, '*', 2);
            }
            $this->components->twoColumnDetail($key, $value);
        }

        $this->newLine();
        $this->components->info('Billing plans');

        $plans = BillingPlan::query()->orderBy('sort_order')->get([
            'slug',
            'name',
            'mau_limit',
            'application_limit',
            'email_daily_limit',
            'email_monthly_limit',
            'allows_custom_email_provider',
            'price_cents_monthly',
        ]);

        if ($plans->isEmpty()) {
            $this->components->warn('No plans found — re-run without --skip-seed.');

            return;
        }

        foreach ($plans as $plan) {
            $apps = $plan->application_limit === null ? '∞ apps' : $plan->application_limit.' app(s)';
            $email = $plan->allows_custom_email_provider
                ? 'BYO email'
                : sprintf(
                    'platform email %s/day · %s/mo',
                    $plan->email_daily_limit ?? '∞',
                    $plan->email_monthly_limit ?? '∞',
                );
            $price = $plan->price_cents_monthly > 0
                ? '$'.number_format($plan->price_cents_monthly / 100, 2).'/mo'
                : 'free';

            $this->components->twoColumnDetail(
                $plan->slug,
                sprintf('%s · %s MAU · %s · %s · %s', $plan->name, number_format((int) $plan->mau_limit), $apps, $email, $price),
            );
        }

        $this->newLine();
        $this->components->twoColumnDetail('Permissions', (string) Permission::query()->count());
        $this->components->twoColumnDetail(
            'Free email caps',
            '100 / day · 3000 / month (Authzio platform mail)',
        );
    }
}
