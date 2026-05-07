<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetConsoleLocaleFromCookie
{
    public const COOKIE = 'mall_admin_locale';

    /** @var list<string> */
    public const ALLOWED = ['en', 'zh_CN'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie(self::COOKIE);
        if ($locale === null || ! in_array($locale, self::ALLOWED, true)) {
            $locale = 'en';
        }
        app()->setLocale($locale);

        return $next($request);
    }
}
