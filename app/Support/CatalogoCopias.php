<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Qué se puede respaldar y quién puede hacerlo.
 *
 * Cada "conjunto" agrupa las tablas que tienen sentido juntas (una inscripción
 * sin su QR o su invitado queda incompleta). Si se agrega una tabla nueva al
 * proyecto hay que ponerla en un conjunto o en TABLAS_EXCLUIDAS: la prueba
 * CopiaSeguridadTest falla si queda alguna sin clasificar.
 */
class CatalogoCopias
{
    public const GRUPOS = [
        'deportivo' => [
            'nombre' => 'Skyed Deportivo',
            'rol' => 'adminDeportivo',
            'conjuntos' => [
                'eventos_deportivos' => [
                    'nombre' => 'Eventos deportivos',
                    'descripcion' => 'Eventos, categorías, rutas, estaciones y patrocinadores.',
                    'tablas' => ['eventoDeportivo', 'categoria_competencia', 'ruta_evento', 'estacion', 'patrocinador', 'evento_patrocinador'],
                ],
                'inscripciones' => [
                    'nombre' => 'Inscripciones',
                    'descripcion' => 'Inscripciones, invitados, entradas QR e historial de participación. Incluye condiciones médicas.',
                    'tablas' => ['invitados', 'inscripcion', 'qr_entrada', 'historial_participacion'],
                ],
                'pagos' => [
                    'nombre' => 'Pagos',
                    'descripcion' => 'Pagos de las inscripciones y su estado.',
                    'tablas' => ['pago'],
                ],
                'kits' => [
                    'nombre' => 'Kits',
                    'descripcion' => 'Kits, tallas y entregas.',
                    'tablas' => ['kit', 'kit_talla', 'entrega_kit'],
                ],
                'resultados' => [
                    'nombre' => 'Resultados',
                    'descripcion' => 'Resultados, posiciones por categoría y premios.',
                    'tablas' => ['resultado', 'categoria_resultado', 'premio'],
                ],
            ],
        ],
        'social' => [
            'nombre' => 'Skyed Social',
            'rol' => 'adminSocial',
            'conjuntos' => [
                'eventos_sociales' => [
                    'nombre' => 'Eventos sociales',
                    'descripcion' => 'Eventos publicados y tipos de evento.',
                    'tablas' => ['tipo_evento', 'evento_realizado'],
                ],
                'lugares_servicios' => [
                    'nombre' => 'Lugares y servicios',
                    'descripcion' => 'Ambientes, servicios y qué servicios ofrece cada ambiente.',
                    'tablas' => ['ambiente', 'servicio', 'ambiente_servicio'],
                ],
                'reservas' => [
                    'nombre' => 'Reservas',
                    'descripcion' => 'Reservas, servicios contratados, gestión y seguimiento.',
                    'tablas' => ['reserva', 'reserva_servicio', 'reserva_gestion', 'seguimiento_reserva'],
                ],
                'pqr' => [
                    'nombre' => 'PQRS',
                    'descripcion' => 'Peticiones, quejas, reclamos y sugerencias, con su historial.',
                    'tablas' => ['pqr', 'pqr_seguimientos'],
                ],
            ],
        ],
        'sistema' => [
            'nombre' => 'Sistema',
            'rol' => null, // Solo el super administrador
            'conjuntos' => [
                'usuarios' => [
                    'nombre' => 'Usuarios',
                    'descripcion' => 'Cuentas de usuario, sin contraseñas ni códigos de recuperación.',
                    'tablas' => ['usuario'],
                ],
                'roles' => [
                    'nombre' => 'Roles y permisos',
                    'descripcion' => 'Roles y qué rol tiene cada usuario.',
                    'tablas' => ['rol', 'usuario_contexto_rol'],
                ],
                'notificaciones' => [
                    'nombre' => 'Notificaciones',
                    'descripcion' => 'Notificaciones enviadas a los usuarios.',
                    'tablas' => ['notificaciones'],
                ],
                'centro_ayuda' => [
                    'nombre' => 'Centro de Ayuda',
                    'descripcion' => 'Categorías, artículos y valoraciones.',
                    'tablas' => ['ayuda_categoria', 'ayuda_articulo', 'ayuda_valoracion'],
                ],
            ],
        ],
    ];

    /** Nunca salen en una copia. Al restaurar se conservan los valores actuales. */
    public const COLUMNAS_PRIVADAS = [
        'usuario' => ['contrasena_u', 'codigo', 'codigo_expira_at'],
    ];

    /** Tablas que no se respaldan: técnicas, temporales o con credenciales. */
    public const TABLAS_EXCLUIDAS = [
        'cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs', 'migrations', 'sessions',
        'users',                 // Tabla por defecto de Laravel, sin uso (las cuentas están en "usuario")
        'copia_seguridad',       // El registro de las propias copias
        'password_reset_tokens', // Credenciales temporales
        'personal_access_tokens',
        'historial_contrasena',  // Contraseñas cifradas anteriores
    ];

    /** Todos los conjuntos: ['pqr' => ['nombre' => ..., 'tablas' => ..., 'grupo' => 'social'], ...] */
    public static function conjuntos(): array
    {
        $todos = [];
        foreach (self::GRUPOS as $grupo => $datos) {
            foreach ($datos['conjuntos'] as $clave => $conjunto) {
                $todos[$clave] = $conjunto + ['grupo' => $grupo];
            }
        }

        return $todos;
    }

    /** Claves de los grupos que puede manejar esta persona. */
    public static function gruposPermitidos(User $usuario): array
    {
        if ($usuario->esSuperAdmin()) {
            return array_keys(self::GRUPOS);
        }

        return array_keys(array_filter(
            self::GRUPOS,
            fn (array $grupo) => $grupo['rol'] !== null && $usuario->hasRole($grupo['rol'])
        ));
    }

    /** Claves de los conjuntos que puede manejar esta persona. */
    public static function conjuntosPermitidos(User $usuario): array
    {
        $grupos = self::gruposPermitidos($usuario);

        return array_keys(array_filter(
            self::conjuntos(),
            fn (array $conjunto) => in_array($conjunto['grupo'], $grupos, true)
        ));
    }

    /** ¿Puede manejar TODOS estos conjuntos? Una copia mixta no se abre a medias. */
    public static function puedeManejar(User $usuario, array $conjuntos): bool
    {
        return array_diff($conjuntos, self::conjuntosPermitidos($usuario)) === [];
    }

    /** Lo que se le muestra en el panel, con cuántos registros tiene cada conjunto. */
    public static function paraMostrar(User $usuario): array
    {
        $grupos = [];
        foreach (self::gruposPermitidos($usuario) as $clave) {
            $grupo = self::GRUPOS[$clave];
            $conjuntos = [];
            foreach ($grupo['conjuntos'] as $claveConjunto => $conjunto) {
                $conjuntos[] = [
                    'clave' => $claveConjunto,
                    'nombre' => $conjunto['nombre'],
                    'descripcion' => $conjunto['descripcion'],
                    'registros' => array_sum(array_map(fn ($t) => DB::table($t)->count(), $conjunto['tablas'])),
                ];
            }
            $grupos[] = ['clave' => $clave, 'nombre' => $grupo['nombre'], 'conjuntos' => $conjuntos];
        }

        return $grupos;
    }

    /** Tablas de estos conjuntos. */
    public static function tablasDe(array $conjuntos): array
    {
        $catalogo = self::conjuntos();

        return array_values(array_unique(array_merge(
            ...array_map(fn ($c) => $catalogo[$c]['tablas'], $conjuntos)
        )));
    }

    /** "deportivo", "social" o "sistema" si es de un solo grupo; si no, "general". */
    public static function alcance(array $conjuntos): string
    {
        $catalogo = self::conjuntos();
        $grupos = array_unique(array_map(fn ($c) => $catalogo[$c]['grupo'], $conjuntos));

        return count($grupos) === 1 ? reset($grupos) : 'general';
    }

    /** Nombres legibles de estos conjuntos. */
    public static function nombres(array $conjuntos): array
    {
        $catalogo = self::conjuntos();

        return array_values(array_map(fn ($c) => $catalogo[$c]['nombre'] ?? $c, $conjuntos));
    }
}
