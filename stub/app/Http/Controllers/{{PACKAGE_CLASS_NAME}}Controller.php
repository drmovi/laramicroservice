<?php

namespace {{PACKAGE_NAMESPACE}}\Http\Controllers;

use App\Http\Controllers\Controller;
use {{PACKAGE_NAMESPACE}}\Http\Requests\IndexRequest;

class {{PACKAGE_CLASS_NAME}}Controller extends Controller{


    public function index(IndexRequest $request):array
    {
        return [];
    }
}
