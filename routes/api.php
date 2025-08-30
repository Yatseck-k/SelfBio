<?php

declare(strict_types=1);

use App\Http\Controllers\BlogController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/welcome', [WelcomeController::class, 'index']);

Route::get('/contacts', [ContactController::class, 'show']);

Route::get('/blog/{slug}', [BlogController::class, 'show']);

Route::middleware('auth:sanctum')->get('/user', fn (Request $request) => $request->user());

Route::middleware('throttle:60,1')->get('/blog', [BlogController::class, 'index']);
