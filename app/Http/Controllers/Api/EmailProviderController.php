<?php

namespace App\Http\Controllers\Api;

use App\Enums\EmailProviderDriver;
use App\Enums\EmailTemplateSlug;
use App\Enums\OrgPermission;
use App\Http\Controllers\Concerns\AuthorizesOrganization;
use App\Http\Controllers\Concerns\HandlesDemoSoftMutations;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationEmailProvider;
use App\Services\Billing\PlanEntitlements;
use App\Services\Demo\DemoCapability;
use App\Services\Mail\EmailUsageTracker;
use App\Services\Mail\TransactionalMailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmailProviderController extends Controller
{
    use AuthorizesOrganization;
    use HandlesDemoSoftMutations;

    public function __construct(
        private readonly PlanEntitlements $entitlements,
        private readonly EmailUsageTracker $usage,
        private readonly TransactionalMailer $mailer,
    ) {}

    public function show(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrg($request, $organization, OrgPermission::EmailProviderRead);

        $provider = $organization->emailProvider;
        $entitlements = $this->entitlements->forOrganization($organization);

        return response()->json([
            'entitlements' => [
                'allows_custom_email_provider' => $entitlements['allows_custom_email_provider'],
                'email_daily_limit' => $entitlements['email_daily_limit'],
                'email_monthly_limit' => $entitlements['email_monthly_limit'],
            ],
            'usage' => $this->usage->snapshot($organization),
            'provider' => $provider === null ? null : [
                'driver' => $provider->driver->value,
                'from_address' => $provider->from_address,
                'from_name' => $provider->from_name,
                'is_active' => $provider->is_active,
                'verified_at' => $provider->verified_at,
                'last_error' => $provider->last_error,
                'has_credentials' => $provider->credentials !== null && $provider->credentials !== [],
            ],
            'drivers' => array_map(
                static fn (EmailProviderDriver $driver): string => $driver->value,
                EmailProviderDriver::cases(),
            ),
        ]);
    }

    public function upsert(Request $request, Organization $organization): JsonResponse
    {
        // demo-soft:DemoCapability::EmailProviderMutate
        if ($response = $this->demoSoftAck($request, DemoCapability::EmailProviderMutate)) {
            return $response;
        }

        $this->authorizeOrg($request, $organization, OrgPermission::EmailProviderWrite);
        $this->entitlements->assertCustomEmailProvider($organization);

        $validated = $request->validate([
            'driver' => ['required', Rule::enum(EmailProviderDriver::class)],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'credentials' => ['required', 'array'],
        ]);

        $provider = OrganizationEmailProvider::query()->updateOrCreate(
            ['organization_id' => $organization->id],
            [
                'driver' => $validated['driver'],
                'from_address' => $validated['from_address'],
                'from_name' => $validated['from_name'] ?? null,
                'credentials' => $validated['credentials'],
                'is_active' => $validated['is_active'] ?? true,
                'last_error' => null,
            ],
        );

        return response()->json(['message' => __('Email provider saved.'), 'id' => $provider->id]);
    }

    public function test(Request $request, Organization $organization): JsonResponse
    {
        if ($response = $this->demoSoftAck($request, DemoCapability::EmailProviderMutate, [
            'message' => 'Saved for this demo session.',
        ])) {
            return $response;
        }

        $this->authorizeOrg($request, $organization, OrgPermission::EmailProviderWrite);
        $this->entitlements->assertCustomEmailProvider($organization);

        $provider = $organization->emailProvider;
        if ($provider === null || ! $provider->is_active) {
            throw ValidationException::withMessages([
                'email_provider' => [__('Save and activate an email provider first.')],
            ]);
        }

        $to = $request->user()->email;
        $this->mailer->sendOrganization(
            $organization,
            $to,
            EmailTemplateSlug::Welcome,
            ['user_name' => $request->user()->name],
            queue: false,
        );

        return response()->json(['message' => __('Test email sent to :email.', ['email' => $to])]);
    }
}
