<?php

declare(strict_types=1);

namespace App\Http\Controllers\admin;

use App\Components\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\mall\MallOssUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class AdminUploadController extends Controller
{
    public function __construct(
        private readonly MallOssUploadService $mallOssUpload,
    ) {}

    public function store(Request $request): JsonResponse
    {
        if (! $request->hasFile('file')) {
            $hint = 'No file was received. Check nginx client_max_body_size and PHP post_max_size / upload_max_filesize.';

            throw ValidationException::withMessages(['file' => [$hint]]);
        }

        /** @var UploadedFile $file */
        $file = $request->file('file');

        if (! $file->isValid()) {
            $msg = $file->getErrorMessage();
            if ($msg === '') {
                $msg = 'Upload rejected: PHP temp dir, permissions, or open_basedir may block this file.';
            }

            throw ValidationException::withMessages(['file' => [$msg]]);
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'image', 'max:102400'],
            'upload_kind' => ['required', 'string', Rule::in(MallOssUploadService::GAME_MEDIA_SEGMENTS)],
        ]);

        $objectKey = $this->mallOssUpload->uploadGameMediaFile(
            $validated['file'],
            $validated['upload_kind']
        );

        return response()->json(ApiResponse::ok(['path' => $objectKey]));
    }
}
