<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Components\ApiResponse;
use App\Exceptions\bet\BetDomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Paganini\Aggregation\Exceptions\DownstreamServiceException;
use Paganini\Constants\ResponseConstant;
use Random\RandomException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use ValueError;

/**
 * JSON envelope for {@code api/*}, {@code internal/*}, and {@code admin/*} requests that
 * {@code expectJson()} (e.g. {@code fetch} with {@code Accept: application/json}). Returns {@code null}
 * for other paths so {@code admin/*} HTML forms keep default validation redirects and error pages.
 */
final class ApiJsonExceptionHandler
{
    /**
     * @throws RandomException
     */
    public static function render(Request $request, Throwable $exception): ?JsonResponse
    {
        $path = $request->path();
        $jsonEnvelopePath = str_starts_with($path, 'api/')
            || str_starts_with($path, 'internal/');
        $adminJson = str_starts_with($path, 'admin/') && $request->expectsJson();
        if (! $jsonEnvelopePath && ! $adminJson) {
            return null;
        }

        $reqId = $request->header('X-Request-Id') ?: bin2hex(random_bytes(8));
        $isInternal = str_starts_with($path, 'internal/');

        if ($exception instanceof ValidationException) {
            $message = $exception->validator->errors()->first() ?: 'Validation failed.';
            $payload = ApiResponse::error(ResponseConstant::RET_INVALID_PARAM, $message, $reqId);
            $payload['errors'] = $exception->errors();

            return response()->json($payload, 422);
        }

        if ($exception instanceof HttpException) {
            return self::httpExceptionResponse($exception, $reqId);
        }

        if ($exception instanceof BetDomainException) {
            return response()->json(
                ApiResponse::error($exception->errorCode(), $exception->getMessage(), $reqId),
                $exception->httpStatus()
            );
        }

        if ($exception instanceof FoundationAuthRequiredException) {
            return response()->json(
                ApiResponse::error(
                    (int) config('bet_agg.foundation.unauthorized_code', ResponseConstant::RET_UNAUTHORIZED),
                    $exception->getMessage(),
                    $reqId
                ),
                401
            );
        }

        if ($exception instanceof ConfigurationMissingException) {
            return response()->json(
                ApiResponse::error(ResponseConstant::RET_CONFIG_ERROR, $exception->getMessage(), $reqId),
                503
            );
        }

        if ($exception instanceof DownstreamServiceException) {
            return self::downstreamResponse($request, $exception, $reqId, $isInternal);
        }

        if ($exception instanceof ModelNotFoundException) {
            $message = self::modelNotFoundMessage($request);

            return response()->json(
                ApiResponse::error(ResponseConstant::RET_RESOURCE_NOT_FOUND, $message, $reqId),
                $isInternal ? 200 : 404
            );
        }

        if ($exception instanceof ValueError) {
            $message = $exception->getMessage();
            if ($message === '') {
                $message = 'Invalid parameter value.';
            }

            return response()->json(
                ApiResponse::error(ResponseConstant::RET_INVALID_PARAM, $message, $reqId),
                422
            );
        }

        if ($exception instanceof RuntimeException) {
            return response()->json(
                ApiResponse::error(ResponseConstant::RET_BUSINESS_ERROR, $exception->getMessage(), $reqId),
                $isInternal ? 200 : 422
            );
        }

        Log::error('Uncaught API exception', [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            '_req_id' => $reqId,
        ]);

        $publicMessage = config('app.debug') ? $exception->getMessage() : '服务器内部错误';

        return response()->json(
            ApiResponse::error(ResponseConstant::RET_UNKNOWN, $publicMessage, $reqId),
            500
        );
    }

    private static function httpExceptionResponse(HttpException $exception, string $reqId): JsonResponse
    {
        $status = $exception->getStatusCode();
        $ret = match ($status) {
            401 => ResponseConstant::RET_UNAUTHORIZED,
            403 => ResponseConstant::RET_FORBIDDEN,
            404 => ResponseConstant::RET_RESOURCE_NOT_FOUND,
            422 => ResponseConstant::RET_INVALID_PARAM,
            429 => ResponseConstant::RET_RATE_LIMITED,
            502 => ResponseConstant::RET_HTTP_5XX,
            503 => ResponseConstant::RET_SERVICE_UNAVAILABLE,
            504 => ResponseConstant::RET_HTTP_5XX,
            default => $status >= 400 && $status < 500
                ? ResponseConstant::RET_ERR
                : ResponseConstant::RET_UNKNOWN,
        };

        return response()->json(
            ApiResponse::error($ret, $exception->getMessage() ?: 'HTTP error', $reqId),
            $status
        );
    }

    private static function downstreamResponse(
        Request $request,
        DownstreamServiceException $exception,
        string $reqId,
        bool $isInternal,
    ): JsonResponse {
        if ($isInternal) {
            return response()->json(
                ApiResponse::error(ResponseConstant::RET_DEPENDENCY_ERROR, $exception->getMessage(), $reqId)
            );
        }

        return response()->json(
            ApiResponse::error(ResponseConstant::RET_DEPENDENCY_ERROR, $exception->getMessage(), $reqId),
            502
        );

    }

    private static function modelNotFoundMessage(Request $request): string
    {
        if ($request->is('api/bet/orders') || $request->is('api/bet/orders/*')) {
            return 'Order not found.';
        }

        return 'Resource not found.';
    }
}
