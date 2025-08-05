<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::get('/contacts', function () {
    return Inertia::render('ContactPage');
});

Route::get('/blog', function () {
    return Inertia::render('BlogPage');
});

Route::get('/blog/{slug}', function ($slug) {
    return Inertia::render('BlogPostPage', ['slug' => $slug]);
});
