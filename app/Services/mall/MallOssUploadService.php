<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Services\api_gw\ResolvedApiGatewayBaseUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Uploads files to the foundation OSS API (HTTP PUT), same mechanism as Django
 * app_user.services.avatar_storage_service (HTTP PUT to /api/oss/{bucket}/{key}).
 */
final readonly class MallOssUploadService
{
    public function __construct(private ResolvedApiGatewayBaseUrl $resolvedFoundationBaseUrl) {}

    /**
     * @return non-empty-string OSS object key (e.g. bet/uploads/{uuid}.jpg)
     */
    public function uploadProductFile(UploadedFile $uploadedFile): string
    {
        $prefix = trim((string) config('bet_upload.prefix'), '/');
        if ($prefix === '') {
            throw new RuntimeException(
                'OSS upload prefix is missing: set BET_UPLOAD_PREFIX.'
            );
        }

        $extension = $uploadedFile->getClientOriginalExtension() ?: $uploadedFile->extension() ?: 'bin';
        $pathId = (string) Str::uuid();
        $objectKey = $prefix.'/'.$pathId.'.'.$extension;

        $base = $this->resolvedFoundationBaseUrl->resolvePathSuffix('/api/oss');
        $bucket = trim((string) config('bet_upload.oss_bucket'), '/');

        if ($base === '' || $bucket === '') {
            throw new RuntimeException(
                'OSS upload is not configured: set API_GATEWAY_BASE_URL and BET_OSS_BUCKET.'
            );
        }

        $encodedKey = $this->encodeObjectKeyForUrl($objectKey);
        $uploadUrl = $base.'/'.$bucket.'/'.$encodedKey;

        $mime = $uploadedFile->getMimeType() ?: 'application/octet-stream';
        $path = $uploadedFile->getRealPath();
        if ($path === false) {
            throw new RuntimeException('Temporary upload path unavailable.');
        }

        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Failed to read uploaded file.');
        }

        try {
            $request = Http::timeout(120)->withHeaders([
                'Accept' => '*/*',
            ]);
            $gatewayBearer = (string) config('bet_upload.gateway_bearer_token', '');
            if ($gatewayBearer !== '') {
                $request = $request->withToken($gatewayBearer);
            }
            $response = $request->withBody($stream, $mime)->put($uploadUrl);
        } catch (Throwable $e) {
            throw new RuntimeException('OSS upload request failed: '.$e->getMessage(), 0, $e);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $status = $response->status();
        if (! in_array($status, [200, 204], true)) {
            $preview = mb_substr($response->body(), 0, 500);

            throw new RuntimeException(
                sprintf('OSS upload rejected: HTTP %d. %s', $status, $preview)
            );
        }

        return $objectKey;
    }

    /**
     * Encode object key path segments like Python's urllib.parse.quote(..., safe='/').
     */
    private function encodeObjectKeyForUrl(string $objectKey): string
    {
        $segments = explode('/', $objectKey);

        return implode('/', array_map(static fn (string $s): string => rawurlencode($s), $segments));
    }
}
