<?php

it('redirects the old customer auth URLs that lived under /admin', function () {
    // Sebelum panel dipisah, /admin/login & /admin/register adalah halaman login & registrasi
    // CUSTOMER. Bookmark itu sudah beredar; tanpa redirect, customer dengan password yang benar
    // ditolak "These credentials do not match our records." karena guard-nya beda.
    $this->get('/admin/login')->assertRedirect('/login');
    $this->get('/admin/register')->assertRedirect('/register');
    $this->get('/admin')->assertRedirect('/');
});

it('serves the backoffice from its own path', function () {
    $this->get('/backoffice/login')->assertOk();
});
