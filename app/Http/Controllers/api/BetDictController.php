<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\MallDictionaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Paganini\Constants\ResponseConstant;

final class BetDictController extends Controller
{
    /**
     * @throws ValidationException
     */
    public function __invoke(Request $request, MallDictionaryService $dictionary): JsonResponse
    {
        $codesRaw = $request->query('codes');
        if (! is_string($codesRaw) || trim($codesRaw) === '') {
            return response()->json(ApiResponse::error(ResponseConstant::RET_MISSING_PARAM, 'codes is required'));
        }

        if (strlen($codesRaw) > 512) {
            throw ValidationException::withMessages([
                'codes' => ['The codes query may not be greater than 512 characters.'],
            ]);
        }

        $codes = array_values(array_filter(array_map(trim(...), explode(',', $codesRaw))));
        if ($codes === []) {
            return response()->json(ApiResponse::error(ResponseConstant::RET_MISSING_PARAM, 'codes is required'));
        }

        $data = $dictionary->resolve($codes);

        return response()->json(ApiResponse::ok($data));
    }
}
