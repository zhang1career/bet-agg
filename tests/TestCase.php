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
            $url = $request->url();
            if ($request->method() === 'GET' && preg_match('#/api/cms/game/(\d+)$#', $url, $m)) {
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

            if ($request->method() !== 'GET' || ! str_contains($url, '/api/cms/game')) {
                return Http::response([], 404);
            }

            $path = parse_url($url, PHP_URL_PATH);
            $path = is_string($path) ? rtrim($path, '/') : '';
            if (! str_ends_with($path, '/api/cms/game')) {
                return Http::response([], 404);
            }

            $q = parse_url($url, PHP_URL_QUERY);
            parse_str(is_string($q) ? $q : '', $params);
            if (! array_key_exists('ids', $params)) {
                return Http::response([], 404);
            }

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
        };

        return [
            'http://gw.test/api/cms/game*' => $handler,
            'http://foundation.local/api/cms/game*' => $handler,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(self::cmsGatewayGameFakes());
    }
}
