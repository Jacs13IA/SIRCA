<?php

namespace App\Services\Foundry;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Throwable;

class FoundryTokenProvider
{
    private const TOKEN_SCOPE = 'https://ai.azure.com/.default';

    private ?string $authenticationMethod = null;

    public function getToken(): string
    {
        $cacheKey = 'foundry.access_token.'.sha1((string) config('services.foundry.project_endpoint'));
        $cachedToken = Cache::get($cacheKey);

        if (is_string($cachedToken) && $cachedToken !== '') {
            $cachedMethod = Cache::get($this->authenticationMethodCacheKey());
            $this->authenticationMethod = is_string($cachedMethod) ? $cachedMethod : 'cached';

            return $cachedToken;
        }

        if ($token = $this->requestManagedIdentityToken()) {
            return $token;
        }

        return $this->requestAzureCliToken();
    }

    public function authenticationMethod(): ?string
    {
        return $this->authenticationMethod;
    }

    private function requestManagedIdentityToken(): ?string
    {
        $endpoint = config('services.foundry.identity_endpoint');
        $header = config('services.foundry.identity_header');

        if (! is_string($endpoint) || $endpoint === '' || ! is_string($header) || $header === '') {
            return null;
        }

        $query = [
            'api-version' => '2019-08-01',
            'resource' => 'https://ai.azure.com',
        ];

        $clientId = config('services.foundry.managed_identity_client_id');

        if (is_string($clientId) && $clientId !== '') {
            $query['client_id'] = $clientId;
        }

        try {
            $response = Http::acceptJson()
                ->withHeader('X-IDENTITY-HEADER', $header)
                ->timeout(10)
                ->get($endpoint, $query);
        } catch (Throwable) {
            throw new FoundryFailure('authentication', 'managed_identity_unavailable', 'Managed identity token could not be requested.', [
                'authentication_method' => 'managed_identity',
            ]);
        }

        if ($response->failed()) {
            throw new FoundryFailure('authentication', 'managed_identity_rejected', 'Managed identity authentication failed.', [
                'authentication_method' => 'managed_identity',
            ]);
        }

        return $this->cacheToken(
            $response->json('access_token'),
            (int) $response->json('expires_in', 3600),
            'managed_identity',
        );
    }

    private function requestAzureCliToken(): string
    {
        $binary = PHP_OS_FAMILY === 'Windows' ? 'az.cmd' : 'az';
        $command = "{$binary} account get-access-token --scope ".self::TOKEN_SCOPE.' --query accessToken --output tsv';

        try {
            $process = Process::timeout(15)
                ->env(['AZURE_CONFIG_DIR' => (string) config('services.foundry.azure_cli_config_dir')])
                ->run($command);
        } catch (Throwable) {
            throw new FoundryFailure('authentication', 'azure_cli_unavailable', 'Azure CLI could not be started.', [
                'authentication_method' => 'azure_cli',
            ]);
        }

        $token = trim($process->output());

        if (! $process->successful() || $token === '') {
            $context = ['authentication_method' => 'azure_cli'];

            if ($process->exitCode() !== null) {
                $context['process_exit_code'] = $process->exitCode();
            }

            throw new FoundryFailure('authentication', 'azure_cli_authentication_failed', 'Azure CLI authentication failed.', $context);
        }

        return $this->cacheToken($token, 3600, 'azure_cli');
    }

    private function cacheToken(mixed $token, int $expiresIn, string $authenticationMethod): string
    {
        if (! is_string($token) || $token === '') {
            throw new FoundryFailure('authentication', 'access_token_missing', 'Authentication did not return a usable token.', [
                'authentication_method' => $authenticationMethod,
            ]);
        }

        $ttl = max(60, $expiresIn - 60);
        $cacheKey = 'foundry.access_token.'.sha1((string) config('services.foundry.project_endpoint'));
        Cache::put($cacheKey, $token, now()->addSeconds($ttl));
        Cache::put($this->authenticationMethodCacheKey(), $authenticationMethod, now()->addSeconds($ttl));
        $this->authenticationMethod = $authenticationMethod;

        return $token;
    }

    private function authenticationMethodCacheKey(): string
    {
        return 'foundry.authentication_method.'.sha1((string) config('services.foundry.project_endpoint'));
    }
}