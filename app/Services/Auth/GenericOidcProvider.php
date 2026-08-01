<?php

namespace App\Services\Auth;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

class GenericOidcProvider extends AbstractProvider implements ProviderInterface
{
    protected $scopeSeparator = ' ';

    /**
     * @var list<string>
     */
    protected $scopes = ['openid', 'profile', 'email'];

    private string $authorizationEndpoint = '';

    private string $tokenEndpoint = '';

    private string $userinfoEndpoint = '';

    public function setEndpoints(
        string $authorizationEndpoint,
        string $tokenEndpoint,
        string $userinfoEndpoint,
    ): self {
        $this->authorizationEndpoint = $authorizationEndpoint;
        $this->tokenEndpoint = $tokenEndpoint;
        $this->userinfoEndpoint = $userinfoEndpoint;

        return $this;
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->authorizationEndpoint, $state);
    }

    protected function getTokenUrl(): string
    {
        return $this->tokenEndpoint;
    }

    /**
     * @param  string  $token
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get($this->userinfoEndpoint, [
            RequestOptions::HEADERS => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $response->getBody(), true) ?? [];

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $user
     */
    protected function mapUserToObject(array $user): User
    {
        $id = Arr::get($user, 'sub') ?? Arr::get($user, 'id');

        return (new User)->setRaw($user)->map([
            'id' => $id !== null ? (string) $id : null,
            'nickname' => Arr::get($user, 'preferred_username') ?? Arr::get($user, 'nickname'),
            'name' => Arr::get($user, 'name'),
            'email' => Arr::get($user, 'email'),
            'avatar' => Arr::get($user, 'picture'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function getCodeFields($state = null): array
    {
        $fields = parent::getCodeFields($state);
        $fields['response_type'] = 'code';

        return $fields;
    }
}
