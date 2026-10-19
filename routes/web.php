<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Diagnóstico de límites de subida. Solo en desarrollo: en producción
// revelaría la versión de PHP y la configuración del servidor.
if (app()->environment('local')) {
    Route::get('/debug-ini', function () {
        return response()->json([
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'php_version' => phpversion(),
        ]);
    });
}