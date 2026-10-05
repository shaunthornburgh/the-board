<?php

use Illuminate\Support\Facades\Route;

// Laravel is the API. Every page lives in the Nuxt site.
Route::get('/', fn () => redirect(config('app.frontend_url')));
