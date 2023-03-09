<?php

namespace App\GG\Providers;
use App\GG\Account;
use App\GG\Token;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Crypt;

abstract class AbstractProvider implements ProviderInterface
{
    protected $providerName;

    protected $request;

    protected $httpClient;

    protected $clientId;

    protected $clientSecret;

    protected $redirectUrl;

    protected $scopes = [];

    protected $account;

    protected $scopeSeparator = ' ';

    protected $version;

    protected $config;

    public function __construct(Request $request, string $clientId, string $clientSecret, string $redirectUrl, array $scopes = [])
    {
        $this->request = $request;
        $this->clientId = $clientId;
        $this->redirectUrl = $redirectUrl;
        $this->clientSecret = $clientSecret;
        $this->scopes = $scopes;
        $this->config = config('services.' . $this->getProviderName());
    }

    public function redirect(): RedirectResponse
    {
        $this->request->query->add(['state' => $this->getState()]);

        if ($user = $this->request->user($this->getConfig('guard', 'web'))) {
            $this->request->query->add(['user_id' => $user->getKey()]);
        }

        return new RedirectResponse($this->createAuthUrl());
    }
    public function callback(): Account
    {
        if (isset($this->account)) {
            return $this->account;
        }

        $state = $this->request->get('state');

        try {
            if (isset($state)) {
                $state = Crypt::decrypt($state);
            }

            $credentials = $this->fetchAccessTokenWithAuthCode(
                $this->request->get('code', '')
            );

            $this->account = $this->toUser($this->getBasicProfile($credentials));
        } catch (\Exception $exception) {
            report($exception);
            throw new \InvalidArgumentException($exception->getMessage());
        }

        $token = $this->createToken($credentials);
        $userId = $state['user_id'] ?? null;

        return $this->account->setUserId($userId)->setToken($token);
    }
    protected function getHttpClient()
    {
        if (is_null($this->httpClient)) {
            $this->httpClient = new Client();
        }

        return $this->httpClient;
    }

    protected function getState(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * @return string
     */
    public function getScopeSeparator(): string
    {
        return $this->scopeSeparator;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @return mixed
     */
    public function getProviderName()
    {
        return $this->providerName;
    }

    /**
     * @return array
     */
    public function getConfig(string $key, string $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function __call($method, $args)
    {
        if (! method_exists($this->httpClient, $method)) {
            throw new \InvalidArgumentException("Method Not Allowed ${method}");
        }

        return call_user_func_array([$this->httpClient, $method], $args);
    }
    abstract protected function createAuthUrl();
    abstract protected function fetchAccessTokenWithAuthCode(string $code);
    abstract protected function getBasicProfile($credentials);
    abstract protected function toUser($userProfile);
    abstract protected function createToken(array $credentials): Token;
}
