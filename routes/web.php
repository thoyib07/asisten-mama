<?php

use App\Livewire\Beranda;
use Illuminate\Support\Facades\Route;

Route::get('/', Beranda::class)->middleware('auth')->name('beranda');

Route::get('/manifest.json', fn () => response(file_get_contents(public_path('manifest.json')), 200, ['Content-Type' => 'application/json']));
Route::get('/sw.js', fn () => response(file_get_contents(public_path('sw.js')), 200, ['Content-Type' => 'application/javascript; charset=UTF-8']));
