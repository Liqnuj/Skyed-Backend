<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Los ids de las rutas son numéricos. Sin esto, algo como
        // /api/inscripciones/abc llegaba al controlador (que espera int)
        // y respondía 500 con la traza del error en vez de un 404.
        Route::pattern('id', '[0-9]+');
        Route::pattern('eventoId', '[0-9]+');
        Route::pattern('usuarioId', '[0-9]+');
        Route::pattern('rolId', '[0-9]+');
    }
}
