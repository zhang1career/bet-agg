<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * @return array<string, mixed>
     */
    final protected static function cmsGatewayGameFakes(): array
    {
        /** @var callable(Request): Response $handler */
        $handler = function (Request $request) {
            if (preg_match('#/api/cms/game/(\\d+)$#', $request->url(), $m)) {
                $id = (int) $m[1];
                if ($id === 9_999_991) {
                    return Http::response(['message' => 'not found'], 404);
                }

                return Http::response([
                    'data' => [
                        'id' => $id,
                        'title' => 'CMS game '.$id,
                        'banner' => 'cms/banner.png',
                        'main_media' => 'cms/cover.png',
                        'starts_at' => 1_700_000_000_000,
                    ],
                ], 200);
            }

            return Http::response([], 404);
        };

        return [
            'http://gw.test/api/cms/game/*' => $handler,
            'http://foundation.local/api/cms/game/*' => $handler,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(self::cmsGatewayGameFakes());
    }
}
