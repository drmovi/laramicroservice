<?php


\Illuminate\Support\Facades\Route::get('service-health/readiness',[\{{PROJECT_NAMESPACE}}\Http\Controllers\HealthCheck::class,'readiness']);
\Illuminate\Support\Facades\Route::get('service-health/liveness',[\{{PROJECT_NAMESPACE}}\Http\Controllers\HealthCheck::class,'liveness']);
