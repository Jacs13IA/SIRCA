<?php

namespace Tests\Unit;

use App\Services\Foundry\FoundryTokenProvider;
use App\Services\Foundry\FoundryFailure;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class FoundryTokenProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'services.foundry.project_endpoint' => 'https://resource.services.ai.azure.com/api/projects/sirca',
            'services.foundry.identity_endpoint' => null,
            'services.foundry.identity_header' => null,
            'services.foundry.managed_identity_client_id' => null,
            'services.foundry.azure_cli_config_dir' => storage_path('app/private/azure-cli-test'),
        ]);
    }

    public function test_it_uses_and_caches_an_app_service_managed_identity_token(): void
    {
        config([
            'services.foundry.identity_endpoint' => 'https://identity.test/token',
            'services.foundry.identity_header' => 'platform-identity-header',
        ]);
        Http::fake([
            'identity.test/*' => Http::response([
                'access_token' => 'managed-identity-token',
                'expires_in' => 3600,
            ]),
        ]);

        $provider = new FoundryTokenProvider();

        $this->assertSame('managed-identity-token', $provider->getToken());
        $this->assertSame('managed-identity-token', $provider->getToken());
        $this->assertSame('managed_identity', $provider->authenticationMethod());

        Http::assertSent(fn (Request $request): bool =>
            $request->method() === 'GET'
            && $request->hasHeader('X-IDENTITY-HEADER', 'platform-identity-header')
            && str_contains($request->url(), 'resource=https%3A%2F%2Fai.azure.com')
        );
        Http::assertSentCount(1);
    }

    public function test_it_uses_the_logged_in_azure_cli_outside_azure(): void
    {
        Process::fake(fn () => Process::result('cli-access-token'));

        $provider = new FoundryTokenProvider();
        $token = $provider->getToken();

        $this->assertSame('cli-access-token', $token);
        $this->assertSame('azure_cli', $provider->authenticationMethod());
        Process::assertRan(fn ($process): bool =>
            $process->command === (PHP_OS_FAMILY === 'Windows' ? 'az.cmd' : 'az')
                .' account get-access-token --scope https://ai.azure.com/.default --query accessToken --output tsv'
                && $process->environment['AZURE_CONFIG_DIR'] === storage_path('app/private/azure-cli-test')
        );
    }

    public function test_it_explains_when_the_azure_cli_is_not_authenticated(): void
    {
        Process::fake(fn () => Process::result('', 'Sensitive Azure CLI stderr', 1));

        try {
            (new FoundryTokenProvider())->getToken();
            $this->fail('Expected Azure CLI authentication to fail.');
        } catch (FoundryFailure $failure) {
            $this->assertSame('authentication', $failure->stage);
            $this->assertSame('azure_cli_authentication_failed', $failure->failureCode);
            $this->assertSame([
                'authentication_method' => 'azure_cli',
                'process_exit_code' => 1,
            ], $failure->safeContext);
            $this->assertStringNotContainsString('Sensitive Azure CLI stderr', json_encode($failure->safeContext));
        }
    }
}