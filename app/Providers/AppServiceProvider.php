<?php

namespace App\Providers;

use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // FIX: `php artisan serve` arranca el servidor de PHP pasándole solo
        // unas pocas variables de entorno, y no incluye TEMP/TMP. En Windows,
        // sin ellas PHP no encuentra la carpeta temporal y TODA subida de
        // archivos falla ("unable to create a temporary file"): fotos de
        // eventos, de perfil y del panel social. Hay que reiniciar
        // `php artisan serve` para que tome este cambio.
        ServeCommand::$passthroughVariables = array_values(array_unique(array_merge(
            ServeCommand::$passthroughVariables,
            ['TEMP', 'TMP', 'TMPDIR'],
        )));
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
