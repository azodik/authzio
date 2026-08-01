<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrgPermission;
use App\Http\Controllers\Concerns\AuthorizesOrganization;
use App\Http\Controllers\Concerns\HandlesDemoSoftMutations;
use App\Http\Controllers\Controller;
use App\Models\BillingPlan;
use App\Models\Organization;
use App\Services\Billing\BillingService;
use App\Services\Demo\DemoCapability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class BillingController extends Controller
{
    use AuthorizesOrganization;
    use HandlesDemoSoftMutations;

    public function __construct(
        private readonly BillingService $billingService,
    ) {}

    public function show(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrg($request, $organization, OrgPermission::BillingRead);

        return response()->json([
            'organization' => $organization->only(['id', 'name', 'slug', 'billing_email']),
            'data' => $this->billingService->dashboard($organization),
        ]);
    }

    public function checkout(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrg($request, $organization, OrgPermission::BillingManage);

        $validated = $request->validate([
            'plan_slug' => ['required', 'string', 'exists:billing_plans,slug'],
        ]);

        $plan = BillingPlan::query()->where('slug', $validated['plan_slug'])->firstOrFail();

        if (! $plan->is_self_serve) {
            throw ValidationException::withMessages([
                'plan_slug' => [__('Contact sales for this plan.')],
            ]);
        }

        try {
            $session = $this->billingService->startCheckout($organization, $plan, $request->user());
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'plan_slug' => [$exception->getMessage()],
            ]);
        }

        return response()->json([
            'checkout_url' => $session['checkout_url'],
            'session_id' => $session['session_id'],
            'mode' => $session['mode'] ?? 'checkout',
        ]);
    }

    public function previewChange(Request $request, Organization $organization): JsonResponse
    {
        // demo-soft:DemoCapability::BillingPreview
        if ($response = $this->demoSoftAck($request, DemoCapability::BillingPreview)) {
            return $response;
        }

        $this->authorizeOrg($request, $organization, OrgPermission::BillingManage);

        $validated = $request->validate([
            'plan_slug' => ['required', 'string', 'exists:billing_plans,slug'],
        ]);

        $plan = BillingPlan::query()->where('slug', $validated['plan_slug'])->firstOrFail();

        if (! $plan->is_self_serve) {
            throw ValidationException::withMessages([
                'plan_slug' => [__('Contact sales for this plan.')],
            ]);
        }

        try {
            $preview = $this->billingService->previewPlanChange($organization, $plan);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'plan_slug' => [$exception->getMessage()],
            ]);
        }

        return response()->json([
            'data' => $preview,
        ]);
    }

    public function invoices(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrg($request, $organization, OrgPermission::BillingRead);

        try {
            $invoices = $this->billingService->listInvoices($organization);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'billing' => [$exception->getMessage()],
            ]);
        }

        return response()->json([
            'data' => $invoices,
        ]);
    }

    public function downloadInvoice(Request $request, Organization $organization, string $paymentId): Response
    {
        $this->authorizeOrg($request, $organization, OrgPermission::BillingRead);

        try {
            $pdf = $this->billingService->downloadInvoice($organization, $paymentId);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'payment_id' => [$exception->getMessage()],
            ]);
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="invoice-'.$paymentId.'.pdf"',
        ]);
    }
}
