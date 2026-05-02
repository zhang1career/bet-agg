<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\mall\MallOssUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use RuntimeException;

final class AdminUploadController extends Controller
{
    public function __construct(
        private readonly MallOssUploadService $mallOssUpload,
    ) {}

    public function store(Request $request): JsonResponse
    {
        if (! $request->hasFile('file')) {
            $hint = 'No file was received. Check nginx client_max_body_size and PHP post_max_size / upload_max_filesize.';

            return response()->json([
                'message' => $hint,
                'errors' => ['file' => [$hint]],
            ], 422);
        }

        /** @var UploadedFile $file */
        $file = $request->file('file');

        if (! $file->isValid()) {
            $msg = $file->getErrorMessage();
            if ($msg === '') {
                $msg = 'Upload rejected: PHP temp dir, permissions, or open_basedir may block this file.';
            }

            return response()->json([
                'message' => $msg,
                'errors' => ['file' => [$msg]],
            ], 422);
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'image', 'max:102400'],
        ]);

        try {
            $objectKey = $this->mallOssUpload->uploadProductFile($validated['file']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json(['path' => $objectKey]);
    }
}
