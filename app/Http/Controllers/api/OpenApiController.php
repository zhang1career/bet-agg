<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use JsonException;
use RuntimeException;

/**
 * Public OpenAPI document for the agent-facing surface (everything under
 * {@code /api/*}). The on-disk source of truth is {@code docs/api.json};
 * this endpoint just serves it verbatim with a sensible cache header so
 * clients (and tooling like the contract test) always see the canonical
 * spec without parsing the repo themselves.
 *
 * Internal routes ({@code /internal/xxl-job/*}) are deliberately omitted —
 * they are infrastructure callbacks, not part of the agent contract.
 */
class OpenApiController extends Controller
{
    /**
     * @throws JsonException
     */
    public function __invoke(): JsonResponse
    {
        $path = base_path('docs/api.json');
        if (! is_file($path)) {
            throw new RuntimeException('OpenAPI document not found at docs/api.json.');
        }

        $raw = (string) file_get_contents($path);
        if ($raw === '') {
            throw new RuntimeException('OpenAPI document is empty.');
        }

        $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAPI document is not a JSON object.');
        }

        return new JsonResponse($decoded, 200, [
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
