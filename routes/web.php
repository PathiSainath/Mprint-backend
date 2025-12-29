<?php

use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;

Route::get('/', fn() => view('welcome'));

// Must stay in web.php
Route::get('/sanctum/csrf-cookie', [CsrfCookieController::class, 'show']);
