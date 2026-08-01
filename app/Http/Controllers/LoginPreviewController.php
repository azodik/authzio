<?php

namespace App\Http\Controllers;

use App\Models\OAuthClient;
use App\Services\Auth\HostedLoginPresentation;
use App\Services\Demo\DemoOverlay;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginPreviewController extends Controller
{
    public function __construct(
        private readonly HostedLoginPresentation $hostedLogin,
        private readonly DemoOverlay $demoOverlay,
    ) {}

    public function __invoke(Request $request, OAuthClient $oauthClient): View
    {
        abort_if($oauthClient->isRevoked(), 404);

        $overlay = $this->demoOverlay->get(
            $request,
            $this->demoOverlay->applicationKey($oauthClient->id),
        );
        if ($overlay !== null) {
            $oauthClient->fill($overlay);
        }

        return view('auth.login-preview', [
            'client' => $oauthClient,
            'organization' => $oauthClient->organization,
            ...$this->hostedLogin->apply($request, $oauthClient),
        ]);
    }
}
