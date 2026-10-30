<?php

namespace App\Support;

use App\Models\CopiaSeguridad;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Crea, lee y restaura copias de seguridad.
 *
 * Cada copia es un archivo privado (storage/app/private/copias-seguridad, nunca
 * accesible por URL) con los datos en JSON, que es lo que se restaura, y en CSV
 * para abrirlos en Excel. Si el servidor no tiene la extensión zip de PHP, se
 * guarda un solo .json con los mismos datos, sin los CSV.
 */
class GestorCopias
{
    private const CARPETA = 'copias-seguridad';

    public const VERSION_FORMATO = 1;

    /** La aplicación guarda en UTC; lo que lee una persona va en hora de Colombia. */
    public const ZONA_HORARIA = 'America/Bogota';

    /** Crea la copia de estos conjuntos y deja su registro. */
    public static function crear(array $conjuntos, ?User $autor, string $origen = 'manual'): CopiaSeguridad
    {
        $ahora = now();
        $local = $ahora->copy()->setTimezone(self::ZONA_HORARIA);
        $alcance = CatalogoCopias::alcance($conjuntos);
        $datos = [];
        $tablas = [];

        foreach (self::ordenarPorDependencias(CatalogoCopias::tablasDe($conjuntos)) as $tabla) {
            $privadas = array_flip(CatalogoCopias::COLUMNAS_PRIVADAS[$tabla] ?? []);
            $filas = DB::table($tabla)->get()
                ->map(fn ($fila) => array_diff_key((array) $fila, $privadas))
                ->all();

            $datos[$tabla] = $filas;
            $tablas[$tabla] = ['registros' => count($filas)];
        }

        $manifiesto = [
            'aplicacion' => 'SKYED',
            'version_formato' => self::VERSION_FORMATO,
            'creada_en' => $local->toIso8601String(),
            'creada_por' => $autor?->correo_u ?? 'Sistema',
            'origen' => $origen,
            'alcance' => $alcance,
            'conjuntos' => $conjuntos,
            'tablas' => $tablas,
        ];

        $base = sprintf('skyed-copia-%s-%s-%s', $alcance, $local->format('Ymd-His'), Str::lower(Str::random(4)));
        [$nombre, $contenido] = class_exists(ZipArchive::class)
            ? [$base . '.zip', self::empaquetarZip($manifiesto, $datos)]
            : [$base . '.json', self::codificar(['manifiesto' => $manifiesto, 'datos' => $datos])];

        $ruta = self::CARPETA . '/' . $nombre;
        if (!Storage::disk('local')->put($ruta, $contenido)) {
            throw new RuntimeException('No se pudo guardar el archivo de la copia en el servidor.');
        }

        return CopiaSeguridad::create([
            'fecha_cs' => $ahora,
            'alcance_cs' => $alcance,
            'conjuntos_cs' => $conjuntos,
            'archivo_cs' => $ruta,
            'tamano_cs' => strlen($contenido),
            'registros_cs' => array_sum(array_column($tablas, 'registros')),
            'checksum_cs' => hash('sha256', $contenido),
            'origen_cs' => $origen,
            'id_u' => $autor?->id_u,
        ]);
    }

    /**
     * Recupera los datos de la copia. Solo agrega lo que falta y actualiza lo
     * que cambió: lo creado después de la copia NO se borra. Antes hace una
     * copia del estado actual, y si algo falla no se aplica ningún cambio.
     *
     * @return array{tablas: array<string,int>, registros: int, copia_previa: CopiaSeguridad}
     */
    public static function restaurar(CopiaSeguridad $copia, ?User $autor): array
    {
        $datos = self::leerDatos($copia);
        $conjuntos = $copia->conjuntos_cs;
        // Solo tablas de los conjuntos de la copia: aunque el archivo traiga
        // otras, no se tocan.
        $tablas = self::ordenarPorDependencias(CatalogoCopias::tablasDe($conjuntos));

        $previa = self::crear($conjuntos, $autor, 'previa_restauracion');
        $resumen = [];

        try {
            DB::transaction(function () use ($tablas, $datos, &$resumen) {
                foreach ($tablas as $tabla) {
                    $resumen[$tabla] = self::restaurarTabla($tabla, $datos[$tabla] ?? []);
                }
            });
        } catch (QueryException $e) {
            Log::warning('Copia de seguridad: restauración fallida', ['copia' => $copia->id_cs, 'error' => $e->getMessage()]);

            throw new RuntimeException(
                'No se pudo restaurar y no se cambió nada. Es posible que estos datos dependan de otros que ya no '
                . 'existen (por ejemplo, un usuario o un evento) o que choquen con datos nuevos. '
                . 'Restaura primero una copia que incluya esos datos.'
            );
        }

        foreach ($tablas as $tabla) {
            self::ajustarContador($tabla);
        }

        Log::info('Copia de seguridad restaurada', [
            'copia' => $copia->id_cs,
            'usuario' => $autor?->id_u,
            'copia_previa' => $previa->id_cs,
        ]);

        return ['tablas' => $resumen, 'registros' => array_sum($resumen), 'copia_previa' => $previa];
    }

    public static function eliminar(CopiaSeguridad $copia): void
    {
        if ($copia->archivo_cs) {
            Storage::disk('local')->delete($copia->archivo_cs);
        }
        $copia->delete();
    }

    /** Deja solo las $conservar copias automáticas más recientes. */
    public static function podarAutomaticas(int $conservar): int
    {
        $sobrantes = CopiaSeguridad::where('origen_cs', 'automatica')
            ->orderByDesc('fecha_cs')
            ->orderByDesc('id_cs')
            ->skip($conservar)
            ->take(PHP_INT_MAX)
            ->get();

        $sobrantes->each(fn (CopiaSeguridad $c) => self::eliminar($c));

        return $sobrantes->count();
    }

    public static function archivoDisponible(CopiaSeguridad $copia): bool
    {
        return $copia->archivo_cs && Storage::disk('local')->exists($copia->archivo_cs);
    }

    /** Verifica que el archivo no se haya perdido ni cambiado, y devuelve sus datos por tabla. */
    private static function leerDatos(CopiaSeguridad $copia): array
    {
        if (!self::archivoDisponible($copia)) {
            throw new RuntimeException('El archivo de esta copia ya no está en el servidor.');
        }

        $contenido = Storage::disk('local')->get($copia->archivo_cs);

        if (!hash_equals((string) $copia->checksum_cs, hash('sha256', $contenido))) {
            throw new RuntimeException('El archivo de esta copia fue modificado o está dañado. Por seguridad no se restaura.');
        }

        return str_ends_with($copia->archivo_cs, '.zip')
            ? self::leerZip($contenido)
            : (json_decode($contenido, true)['datos'] ?? []);
    }

    private static function leerZip(string $contenido): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Para restaurar esta copia, el servidor necesita la extensión zip de PHP.');
        }

        $temporal = tempnam(sys_get_temp_dir(), 'copia');
        file_put_contents($temporal, $contenido);

        try {
            $zip = new ZipArchive();
            if ($zip->open($temporal) !== true) {
                throw new RuntimeException('No se pudo abrir el archivo de la copia.');
            }

            $datos = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entrada = $zip->getNameIndex($i);
                if (str_starts_with($entrada, 'datos/') && str_ends_with($entrada, '.json')) {
                    $datos[basename($entrada, '.json')] = json_decode($zip->getFromIndex($i), true) ?? [];
                }
            }
            $zip->close();

            return $datos;
        } finally {
            @unlink($temporal);
        }
    }

    private static function empaquetarZip(array $manifiesto, array $datos): string
    {
        $temporal = tempnam(sys_get_temp_dir(), 'copia');

        try {
            $zip = new ZipArchive();
            if ($zip->open($temporal, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No se pudo crear el archivo de la copia.');
            }

            $zip->addFromString('LEEME.txt', self::textoLeeme($manifiesto));
            $zip->addFromString('manifiesto.json', self::codificar($manifiesto));
            foreach ($datos as $tabla => $filas) {
                $zip->addFromString("datos/{$tabla}.json", self::codificar($filas));
                $zip->addFromString("excel/{$tabla}.csv", self::aCsv($tabla, $filas));
            }
            $zip->close();

            return file_get_contents($temporal);
        } finally {
            @unlink($temporal);
        }
    }

    private static function codificar(array $valor): string
    {
        return json_encode($valor, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** CSV para Excel en español: punto y coma, y con BOM para que respete las tildes. */
    private static function aCsv(string $tabla, array $filas): string
    {
        $columnas = $filas
            ? array_keys($filas[0])
            : array_values(array_diff(Schema::getColumnListing($tabla), CatalogoCopias::COLUMNAS_PRIVADAS[$tabla] ?? []));

        $salida = fopen('php://temp', 'r+');
        fwrite($salida, "\xEF\xBB\xBF");
        fputcsv($salida, $columnas, ';', '"', '');
        foreach ($filas as $fila) {
            fputcsv($salida, array_map([self::class, 'celdaSegura'], array_values($fila)), ';', '"', '');
        }
        rewind($salida);
        $csv = stream_get_contents($salida);
        fclose($salida);

        return $csv;
    }

    /**
     * Excel ejecuta como fórmula lo que empieza por =, @, + o -. Muchos textos
     * los escribe el público (una PQRS, por ejemplo): se les antepone un
     * apóstrofo. Los números como "+57 300..." o "-5" se dejan tal cual.
     */
    private static function celdaSegura(mixed $valor): mixed
    {
        return is_string($valor) && preg_match('/^(?:[=@\t\r]|[+\-](?!\s*\d))/', $valor) ? "'" . $valor : $valor;
    }

    private static function textoLeeme(array $m): string
    {
        $tablas = '';
        foreach ($m['tablas'] as $tabla => $info) {
            $tablas .= "  - {$tabla}: {$info['registros']} registros\n";
        }

        return "COPIA DE SEGURIDAD DE SKYED\n\n"
            . "Creada: {$m['creada_en']}\nPor: {$m['creada_por']}\nAlcance: {$m['alcance']}\n\n"
            . "Contenido:\n{$tablas}\n"
            . "excel/  Los datos en CSV, para abrir en Excel.\n"
            . "datos/  Los datos en JSON. Es lo que se usa para restaurar: no los modifiques.\n\n"
            . "No incluye contraseñas ni códigos de recuperación. Sí incluye datos personales:\n"
            . "guarda este archivo en un lugar seguro y no lo compartas.\n";
    }

    /** Inserta lo que falta y actualiza lo que existe, según la llave primaria. */
    private static function restaurarTabla(string $tabla, array $filas): int
    {
        if (!$filas) {
            return 0;
        }

        // Columnas actuales: si la tabla cambió después de la copia, se ignoran las
        // que ya no existen. Las privadas nunca se escriben desde un archivo.
        $columnas = array_diff(Schema::getColumnListing($tabla), CatalogoCopias::COLUMNAS_PRIVADAS[$tabla] ?? []);
        $llave = self::llavePrimaria($tabla);
        $permitidas = array_flip($columnas);
        $limpias = array_map(fn (array $fila) => array_intersect_key($fila, $permitidas), $filas);
        $actualizar = array_values(array_diff(array_keys($limpias[0]), $llave));

        // Una cuenta que se borró vuelve con una contraseña imposible de adivinar
        // (la copia no trae contraseñas): esa persona debe usar "¿Olvidaste tu
        // contraseña?". Las cuentas que existen conservan la suya.
        if ($tabla === 'usuario') {
            $contrasena = Hash::make(Str::random(40));
            $limpias = array_map(fn (array $fila) => $fila + ['contrasena_u' => $contrasena], $limpias);
        }

        foreach (array_chunk($limpias, 200) as $lote) {
            if ($actualizar) {
                DB::table($tabla)->upsert($lote, $llave, $actualizar);
            } else {
                DB::table($tabla)->insertOrIgnore($lote);
            }
        }

        return count($limpias);
    }

    private static function llavePrimaria(string $tabla): array
    {
        foreach (Schema::getIndexes($tabla) as $indice) {
            if ($indice['primary']) {
                return $indice['columns'];
            }
        }

        throw new RuntimeException("La tabla {$tabla} no tiene llave primaria.");
    }

    /**
     * En PostgreSQL, el contador de ids no se entera de las filas insertadas con
     * su id: sin este ajuste, el siguiente registro nuevo chocaría con ellas.
     */
    private static function ajustarContador(string $tabla): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $llave = self::llavePrimaria($tabla);
        if (count($llave) !== 1) {
            return;
        }

        $columna = $llave[0];
        $secuencia = DB::selectOne('SELECT pg_get_serial_sequence(?, ?) AS s', ['"' . $tabla . '"', $columna])->s ?? null;

        if ($secuencia) {
            DB::select("SELECT setval(?, COALESCE((SELECT MAX(\"{$columna}\") FROM \"{$tabla}\"), 1))", [$secuencia]);
        }
    }

    /**
     * Ordena las tablas para que cada una vaya después de las que necesita (el
     * evento antes que su inscripción). Se lee de la base de datos, así que las
     * relaciones nuevas se respetan solas.
     */
    private static function ordenarPorDependencias(array $tablas): array
    {
        $pendientes = [];
        foreach ($tablas as $tabla) {
            $dependencias = array_map(fn (array $fk) => $fk['foreign_table'], Schema::getForeignKeys($tabla));
            $pendientes[$tabla] = array_values(array_intersect(array_unique($dependencias), array_diff($tablas, [$tabla])));
        }

        $orden = [];
        while ($pendientes) {
            $libres = array_keys(array_filter($pendientes, fn (array $deps) => array_diff($deps, $orden) === []));
            if (!$libres) {
                // Una relación circular: no hay orden perfecto, se sigue con las que quedan.
                return array_merge($orden, array_keys($pendientes));
            }
            foreach ($libres as $tabla) {
                $orden[] = $tabla;
                unset($pendientes[$tabla]);
            }
        }

        return $orden;
    }
}
