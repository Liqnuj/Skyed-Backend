<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seguro: las pruebas usan RefreshDatabase, que BORRA todas las tablas.
     * phpunit.xml las manda a SQLite en memoria, pero si alguien ejecutó
     * `php artisan config:cache`, Laravel ignora phpunit.xml y correrían
     * contra la base real (PostgreSQL) del .env.
     *
     * Se revisa aquí, al crear la aplicación, porque RefreshDatabase se
     * ejecuta justo después (dentro de setUp) y ya sería tarde.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $config = $app['config'];
        if ($config->get('database.default') !== 'sqlite'
            || $config->get('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException(
                'Las pruebas NO se ejecutaron para proteger tu base de datos: la configuración de Laravel '
                . 'está en caché. Corre "php artisan config:clear" y vuelve a ejecutar "php artisan test".'
            );
        }

        return $app;
    }
}
