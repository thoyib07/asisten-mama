<?php

use App\Livewire\Akun;
use App\Livewire\Beranda;
use Illuminate\Support\Facades\Route;

// Tanpa `auth`: Beranda sendiri yang mencabang — tamu dapat halaman perkenalan, anggota
// keluarga dapat Beranda seperti biasa. Satu URL, supaya `start_url` PWA tetap `/`.
Route::get('/', Beranda::class)->name('beranda');
Route::get('/akun', Akun::class)->middleware('auth')->name('akun');

// Bookmark & tautan yang beredar dari sebelum panel dipisah: /admin dulunya halaman login &
// registrasi CUSTOMER, bukan backoffice. Backoffice sekarang di /backoffice.
Route::permanentRedirect('/admin/login', '/login');
Route::permanentRedirect('/admin/register', '/register');
Route::permanentRedirect('/admin', '/');

Route::get('/manifest.json', fn () => response(file_get_contents(public_path('manifest.json')), 200, ['Content-Type' => 'application/json']));
Route::get('/sw.js', fn () => response(file_get_contents(public_path('sw.js')), 200, ['Content-Type' => 'application/javascript; charset=UTF-8']));
