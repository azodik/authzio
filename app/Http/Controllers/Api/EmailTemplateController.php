<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\EmailTemplateSlug;
use App\Http\Controllers\Concerns\EnsuresOrganizationMembership;
use App\Http\Controllers\Concerns\HandlesDemoSoftMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateEmailTemplateRequest;
use App\Models\EmailTemplate;
use App\Models\Organization;
use App\Services\AuditLogger;
use App\Services\Billing\PlanEntitlements;
use App\Services\Demo\DemoCapability;
use App\Services\Demo\DemoOverlay;
use App\Services\EmailTemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    use EnsuresOrganizationMembership;
    use HandlesDemoSoftMutations;

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PlanEntitlements $entitlements,
        private readonly EmailTemplateRenderer $renderer,
        private readonly DemoOverlay $demoOverlay,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $organization = $this->resolveOrganization($request);
        $customizableSlugs = array_map(
            static fn (EmailTemplateSlug $slug): string => $slug->value,
            EmailTemplateSlug::organizationCustomizable(),
        );

        $templates = $organization->emailTemplates()
            ->whereIn('slug', $customizableSlugs)
            ->orderBy('name')
            ->get()
            ->map(function (EmailTemplate $template) use ($request) {
                $this->applyOverlay($request, $template);

                return $template;
            });

        return response()->json([
            'organization' => $organization->only(['id', 'name', 'slug']),
            'data' => $templates,
            'entitlements' => $this->entitlements->forOrganization($organization, $request->user()),
            'variables' => collect(EmailTemplateSlug::organizationCustomizable())
                ->mapWithKeys(fn (EmailTemplateSlug $slug) => [$slug->value => $slug->variables()])
                ->all(),
            'previews' => $templates->mapWithKeys(function (EmailTemplate $template) use ($organization) {
                $preview = $this->renderer->preview($template, $organization);

                return [$template->id => $preview];
            }),
        ]);
    }

    public function preview(
        Request $request,
        Organization $organization,
        EmailTemplate $emailTemplate,
    ): JsonResponse {
        $this->organizationForUser($request, $organization);
        abort_unless($emailTemplate->organization_id === $organization->id, 404);
        $slug = EmailTemplateSlug::tryFrom($emailTemplate->slug);
        abort_if($slug === null || ! $slug->isOrganizationCustomizable(), 404);

        $this->applyOverlay($request, $emailTemplate);

        $subject = (string) $request->input('subject', $emailTemplate->subject);
        $bodyHtml = (string) $request->input('body_html', $emailTemplate->body_html);

        $draft = $emailTemplate->replicate();
        $draft->subject = $subject;
        $draft->body_html = $bodyHtml;

        return response()->json([
            'data' => $this->renderer->preview($draft, $organization),
        ]);
    }

    public function update(
        UpdateEmailTemplateRequest $request,
        Organization $organization,
        EmailTemplate $emailTemplate,
    ): JsonResponse {
        $this->organizationForUser($request, $organization);
        abort_unless($emailTemplate->organization_id === $organization->id, 404);
        $slug = EmailTemplateSlug::tryFrom($emailTemplate->slug);
        abort_if($slug === null || ! $slug->isOrganizationCustomizable(), 404);
        $this->entitlements->assertEmailCustomization($organization);

        $payload = $request->validated();

        if ($this->isDemoSoft($request, DemoCapability::EmailTemplateUpdate)) {
            $this->demoOverlay->put(
                $request,
                $this->demoOverlay->emailTemplateKey((string) $emailTemplate->id),
                $payload,
            );
            $this->applyOverlay($request, $emailTemplate);

            return $this->demoSoftResponse([
                'data' => $emailTemplate,
                'preview' => $this->renderer->preview($emailTemplate, $organization),
            ]);
        }

        $emailTemplate->update($payload);

        $this->auditLogger->log(
            AuditAction::EmailTemplateUpdated,
            $request->user(),
            $organization,
            EmailTemplate::class,
            $emailTemplate->id,
            ['slug' => $emailTemplate->slug],
        );

        $fresh = $emailTemplate->fresh() ?? $emailTemplate;

        return response()->json([
            'data' => $fresh,
            'preview' => $this->renderer->preview($fresh, $organization),
        ]);
    }

    private function applyOverlay(Request $request, EmailTemplate $template): void
    {
        $overlay = $this->demoOverlay->get(
            $request,
            $this->demoOverlay->emailTemplateKey((string) $template->id),
        );
        if ($overlay === null) {
            return;
        }

        $template->fill($overlay);
    }
}
