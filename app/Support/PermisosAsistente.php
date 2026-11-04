<?php

namespace App\Support;

use App\Models\Asistente;
use App\Models\User;
use Illuminate\Http\Request;

class PermisosAsistente
{
    public static function paraUsuario(User $usuario, ?string $modulo = null): ?Asistente
    {
        $consulta = Asistente::query()
            ->where('id_usuario', $usuario->id_u)
            ->where('activo', true);

        if ($modulo !== null) {
            $consulta->where('modulo', $modulo);
        }

        $asistente = $consulta->first();

        return $asistente && $usuario->hasRoleInContext('asistente', $asistente->modulo)
            ? $asistente
            : null;
    }

    public static function puedeVer(User $usuario, string $modulo, string $seccion): bool
    {
        $nivel = self::paraUsuario($usuario, $modulo)?->permisos[$seccion] ?? null;

        return in_array($nivel, ['ver', 'editar', 'eliminar'], true);
    }

    public static function puedeEnRuta(Request $request, Asistente $asistente, ?string $moduloRequerido = null): bool
    {
        if ($moduloRequerido !== null && $asistente->modulo !== $moduloRequerido) {
            return false;
        }

        $ruta = '/' . ltrim((string) $request->route()?->uri(), '/');
        if (str_starts_with($ruta, '/api/')) {
            $ruta = substr($ruta, 4);
        }
        $secciones = self::seccionesDeRuta($ruta, $asistente->modulo);
        $niveles = array_map(fn (string $seccion) => $asistente->permisos[$seccion] ?? null, $secciones);
        $metodo = strtoupper($request->method());

        if (in_array($metodo, ['GET', 'HEAD'], true)) {
            return count(array_filter($niveles, fn ($nivel) => in_array($nivel, ['ver', 'editar', 'eliminar'], true))) > 0;
        }

        $nivelNecesario = $metodo === 'DELETE' ? 'eliminar' : 'editar';

        return in_array($nivelNecesario, $niveles, true);
    }

    private static function seccionesDeRuta(string $ruta, string $modulo): array
    {
        if (preg_match('#^/(users|roles)(/|$)#', $ruta)) {
            return ['usuarios'];
        }

        if (preg_match('#^/reportes/' . preg_quote($modulo, '#') . '/([^/]+)/#', $ruta, $coincidencias)) {
            $reporte = match ($modulo . ':' . $coincidencias[1]) {
                'social:ambientes' => 'lugares',
                'social:servicios' => 'servicios',
                'social:eventos' => 'eventos',
                'social:reservas' => 'reservas',
                'social:pqrs' => 'pqr',
                'social:usuarios' => 'usuarios',
                'deportivo:eventos' => 'eventos',
                'deportivo:inscripciones-pagos' => 'inscripciones',
                'deportivo:resultados' => 'resultados',
                'deportivo:kits' => 'kits',
                'deportivo:usuarios' => 'usuarios',
                default => null,
            };

            return $reporte ? [$reporte] : [];
        }

        if ($modulo === 'social') {
            return match (true) {
                $ruta === '/social/imagenes' => ['eventos', 'lugares'],
                str_starts_with($ruta, '/ambientes/') || $ruta === '/ambientes' => ['lugares'],
                str_starts_with($ruta, '/servicios/') || $ruta === '/servicios' => ['servicios'],
                str_starts_with($ruta, '/eventos-sociales/') || $ruta === '/eventos-sociales',
                str_starts_with($ruta, '/tipos-evento/') || $ruta === '/tipos-evento' => ['eventos'],
                str_starts_with($ruta, '/reservas/') || $ruta === '/reservas' => ['reservas'],
                str_starts_with($ruta, '/pqr/') || $ruta === '/pqr' => ['pqr'],
                default => [],
            };
        }

        return match (true) {
            $ruta === '/deportivo/imagenes' => ['eventos'],
            str_starts_with($ruta, '/eventos/') || $ruta === '/eventos',
            str_starts_with($ruta, '/categorias/') || $ruta === '/categorias',
            str_starts_with($ruta, '/patrocinadores/') || $ruta === '/patrocinadores',
            str_starts_with($ruta, '/entregas-kit/') || $ruta === '/entregas-kit',
            str_starts_with($ruta, '/estaciones/') || $ruta === '/estaciones',
            str_starts_with($ruta, '/rutas/') || $ruta === '/rutas',
            str_starts_with($ruta, '/tipos-evento/') || $ruta === '/tipos-evento' => ['eventos'],
            str_starts_with($ruta, '/pagos/') || $ruta === '/pagos',
            str_starts_with($ruta, '/inscripciones/') || $ruta === '/inscripciones' => ['inscripciones'],
            str_starts_with($ruta, '/qr/') || $ruta === '/qr' => ['acceso'],
            str_starts_with($ruta, '/resultados/') || $ruta === '/resultados' => ['resultados'],
            str_starts_with($ruta, '/kits/') || $ruta === '/kits' => ['kits'],
            default => [],
        };
    }
}
