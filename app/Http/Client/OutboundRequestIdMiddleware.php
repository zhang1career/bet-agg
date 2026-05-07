<?php

declare(strict_types=1);

namespace App\Http\Client;

use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * Propagates the inbound HTTP {@code X-Request-Id} header to every outbound
 * Laravel HTTP client call so downstream services (Foundation, CMS, OSS, etc.)
 * log under the same correlation id. When called outside an inbound HTTP
 * request (CLI, queue worker, XXL-Job callback) a fresh id is minted so the
 * outbound call still gets a stable correlation id in its own logs — but we
 * NEVER overwrite an id the caller (or an upstream HTTP client middleware)
 * already attached.
 *
 * Registered in {@see AppServiceProvider::boot} as a global
 * request middleware on Laravel's Http facade.
 */
final class OutboundRequestIdMiddleware
{
    public const HEADER = 'X-Request-Id';

    public static function addHeader(RequestInterface $request): RequestInterface
    {
        if ($request->hasHeader(self::HEADER)) {
            return $request;
        }

        $id = self::currentRequestId();
        if ($id === '') {
            return $request;
        }

        return $request->withHeader(self::HEADER, $id);
    }

    private static function currentRequestId(): string
    {
        try {
            if (! app()->bound('request')) {
                return self::mintFallback();
            }
            $req = app('request');
            if (! $req instanceof Request) {
                return self::mintFallback();
            }

            $header = $req->header(self::HEADER);
            if (is_string($header) && $header !== '') {
                return $header;
            }
        } catch (Throwable) {
            // app() may throw early during container boot; fall through to a fresh id.
        }

        return self::mintFallback();
    }

    private static function mintFallback(): string
    {
        try {
            $bytes = random_bytes(8);

            return 'oid-'.bin2hex($bytes);
        } catch (Throwable) {
            return 'oid-'.dechex((int) (microtime(true) * 1000)).'-'.dechex(mt_rand(0, 0xFFFFFF));
        }
    }
}
