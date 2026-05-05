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
            if (str_contains($request->url(), '/api/cms/game/batch-detail') && $request->method() === 'GET') {
                $q = parse_url($request->url(), PHP_URL_QUERY);
                parse_str(is_string($q) ? $q : '', $params);
                $idsRaw = $params['ids'] ?? '';
                $items = [];
                foreach (array_filter(array_map('trim', explode(',', is_string($idsRaw) ? $idsRaw : ''))) as $token) {
                    if (! ctype_digit($token)) {
                        continue;
                    }
                    $id = (int) $token;
                    if ($id === 9_999_991 || $id < 1) {
                        continue;
                    }
                    $items[] = [
                        'id' => $id,
                        'title' => 'CMS game '.$id,
                        'banner' => 'cms/banner.png',
                        'main_media' => 'cms/cover.png',
                        'starts_at' => 1_700_000_000_000,
                    ];
                }

                return Http::response([
                    'errorCode' => 0,
                    'message' => '',
                    'data' => ['items' => $items],
                ], 200);
            }
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
