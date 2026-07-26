<?php

use Illuminate\Support\Facades\Route;

// All routes are handled by Vue Router — Laravel just serves the blade shell.
Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');
