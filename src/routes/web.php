<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/profile', function () {
    // Only authenticated users may access this route...
})->middleware('auth.basic');
