<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Knobik\SqlAgent\Http\Requests\AskUserReplyRequest;

class AskUserReplyController extends Controller
{
    public function __invoke(AskUserReplyRequest $request): JsonResponse
    {
        Cache::put($request->getRequestId(), $request->getAnswer(), now()->addMinutes(10));

        return response()->json(['status' => 'ok']);
    }
}
