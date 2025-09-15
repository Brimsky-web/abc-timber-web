<?php

use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

Route::get('/', function () {
    return new JsonResponse(['message' => 'server is running']);
});
