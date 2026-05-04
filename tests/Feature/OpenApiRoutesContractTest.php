<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Keeps {@see base_path('docs/api.json')} aligned with registered HTTP routes.
 */
final class OpenApiRoutesContractTest extends TestCase
{
    public function test_spec_file_is_valid_openapi_document(): void
    {
        $path = base_path('docs/api.json');
        $this->assertFileExists($path);
        /** @var array<string, mixed> $spec */
        $spec = json_decode(file_get_contents($path) ?: 'null', true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('3.0.3', $spec['openapi']);
        $this->assertArrayHasKey('info', $spec);
        $this->assertArrayHasKey('paths', $spec);
        $this->assertIsArray($spec['paths']);
        $this->assertArrayHasKey('components', $spec);
        /** @var array<string, mixed> $components */
        $components = $spec['components'];
        $this->assertArrayHasKey('schemas', $components);
        $this->assertArrayHasKey('examples', $components);
    }

    public function test_documented_paths_match_laravel_routes(): void
    {
        /** @var array<string, mixed> $spec */
        $spec = json_decode(file_get_contents(base_path('docs/api.json')) ?: 'null', true, 512, JSON_THROW_ON_ERROR);
        $map = $this->methodUriRegistrationMap();

        foreach ($spec['paths'] as $openApiPath => $operations) {
            $this->assertIsString($openApiPath);
            $uri = ltrim($openApiPath, '/');
            $this->assertNotSame('', $uri, 'OpenAPI path must not be empty.');

            $this->assertIsArray($operations);
            foreach ($operations as $methodLower => $_) {
                if (! is_string($methodLower)) {
                    continue;
                }
                if (! in_array(strtolower($methodLower), ['get', 'post', 'patch', 'put', 'delete', 'options'], true)) {
                    continue;
                }
                $method = strtoupper($methodLower);
                $this->assertArrayHasKey(
                    "{$method} {$uri}",
                    $map,
                    "OpenAPI documents {$method} {$openApiPath} but Laravel has no matching route."
                );
            }
        }
    }

    #[DataProvider('examplePayloadProvider')]
    public function test_named_request_examples_are_objects(string $exampleName): void
    {
        /** @var array<string, mixed> $spec */
        $spec = json_decode(file_get_contents(base_path('docs/api.json')) ?: 'null', true, 512, JSON_THROW_ON_ERROR);
        /** @var array<string, mixed> $examples */
        $examples = $spec['components']['examples'];
        $this->assertArrayHasKey($exampleName, $examples);
        $entry = $examples[$exampleName];
        $this->assertIsArray($entry);
        $this->assertArrayHasKey('value', $entry);
        $this->assertIsArray($entry['value']);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function examplePayloadProvider(): array
    {
        return [
            ['BetOrderCreateBody'],
            ['BetCheckoutBody'],
            ['BetOrderPatchBody'],
            ['XxlJobRunBody'],
            ['XxlJobKillBody'],
            ['ApiEnvelopeSuccess'],
        ];
    }

    /**
     * @return array<string, true>
     */
    private function methodUriRegistrationMap(): array
    {
        $map = [];
        foreach (Route::getRoutes() as $route) {
            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }
                $map[$method.' '.$route->uri()] = true;
            }
        }

        return $map;
    }
}
