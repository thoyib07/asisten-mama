<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('home'));

Route::get('/manifest.json', fn () => response(file_get_contents(public_path('manifest.json')), 200, ['Content-Type' => 'application/json']));
Route::get('/sw.js', fn () => response(file_get_contents(public_path('sw.js')), 200, ['Content-Type' => 'application/javascript; charset=UTF-8']));
