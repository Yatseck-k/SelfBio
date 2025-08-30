<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'));
Route::get('/contacts', fn () => Inertia::render('ContactPage'));
Route::get('/blog', fn () => Inertia::render('BlogPage'));
Route::get('/blog/{slug}', fn ($slug) => Inertia::render('BlogPostPage', ['slug' => $slug]));
