<?php

namespace {{PROJECT_NAMESPACE}}\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthCheck extends Controller
{


    public function readiness(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function liveness(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }
}
