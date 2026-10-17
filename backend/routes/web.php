<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/docs/api', fn () => view('api-docs'))->name('api.docs');

Route::get('/docs/api/openapi.yaml', function () {
    $path = base_path('../docs/openapi.yaml');
    abort_unless(is_file($path), 404);

    return response(file_get_contents($path), 200, [
        'Content-Type' => 'application/yaml; charset=UTF-8',
    ]);
})->name('api.docs.spec');
