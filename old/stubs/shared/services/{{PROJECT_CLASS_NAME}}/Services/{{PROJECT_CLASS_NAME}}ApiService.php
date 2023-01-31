<?php

namespace Shared\Services\{{PROJECT_CLASS_NAME}}Services;

use Illuminate\Http\Client\PendingRequest;

class {{PROJECT_CLASS_NAME}}ApiService
{
    public function __construct(private readonly PendingRequest $client)
    {
        //
    }
}
