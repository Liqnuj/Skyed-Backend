<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\CatalogoCopias;
use App\Support\GestorCopias;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Datos de demostración de SKYED (pruebas y presentación).
 *
 * Uso:   php artisan db:seed --class=DatosDemoSeeder
 * Docker: docker exec skyed_api php artisan db:seed --class=DatosDemoSeeder
 *
 * - Crea usuarios, eventos deportivos con rutas GPX reales de Boyacá, kits,
 *   inscripciones con pago y QR, resultados, patrocinadores, asistentes,
 *   lugares, servicios, eventos sociales, reservas, reseñas, PQR,
 *   notificaciones y copias de seguridad.
 * - Las fotos y los GPX vienen de database/seeders/demo y se copian al disco
 *   "public" (storage/app/public). Hace falta `php artisan storage:link`.
 * - No borra nada. Si los datos demo ya existen no hace nada (se puede correr
 *   varias veces sin duplicar).
 * - Contraseña de todos los usuarios demo: Skyed2026
 */
class DatosDemoSeeder extends Seeder
{
    private const MARCA = 'laura.gomez@skyed.test';
    private const CLAVE = 'Skyed2026';
    private const TZ = 'America/Bogota';

    private string $demo;
    private array $rutas = [];
    private array $usuarios = [];      // clave => id_u
    private array $datosUsuario = [];  // clave => fila
    private array $eventos = [];       // clave => id_e
    private array $datosEvento = [];   // clave => definición
    private array $kits = [];          // clave => id_k
    private array $categorias = [];    // clave evento => [nombre => id_cc]
    private array $patrocinadores = []; // clave => id_p
    private array $servicios = [];     // nombre => id_s
    private array $ambientes = [];     // clave => id_a
    private array $tipos = [];         // nombre => id_tipo_eves
    private array $eventosSociales = []; // clave => id_er
    private int $adminDeportivo;
    private int $adminSocial;
    private int $superAdmin;

    public function run(): void
    {
        if (DB::table('usuario')->where('correo_u', self::MARCA)->exists()) {
            $this->command?->warn('Los datos demo ya están cargados (existe ' . self::MARCA . '). No se hizo nada.');
            return;
        }

        $this->demo = database_path('seeders/demo');
        $this->rutas = json_decode(File::get($this->demo . '/rutas/rutas.json'), true);
        mt_srand(2026);

        // Roles y tipos de evento base (no duplican). Los admins solo si faltan:
        // AdminUserSeeder reinicia su contraseña y no queremos cambiar la que ya usan.
        $this->call([RoleSeeder::class, TipoEventoSeeder::class]);
        $correosAdmin = ['admindeportivo@skyed.test', 'adminsocial@skyed.test', 'superadmin@skyed.test'];
        if (DB::table('usuario')->whereIn('correo_u', $correosAdmin)->count() < 3) {
            $this->call(AdminUserSeeder::class);
        }

        $this->adminDeportivo = (int) DB::table('usuario')->where('correo_u', 'admindeportivo@skyed.test')->value('id_u');
        $this->adminSocial = (int) DB::table('usuario')->where('correo_u', 'adminsocial@skyed.test')->value('id_u');
        $this->superAdmin = (int) DB::table('usuario')->where('correo_u', 'superadmin@skyed.test')->value('id_u');

        DB::transaction(function () {
            $this->copiarArchivos();
            $this->crearUsuarios();
            $this->crearAsistentes();
            $this->crearKits();
            $this->crearPatrocinadores();
            $this->crearEventosDeportivos();
            $this->crearInscripciones();
            $this->crearServiciosYLugares();
            $this->crearEventosSociales();
            $this->crearReservas();
            $this->crearPqrs();
        });

        // Las copias leen la base ya llena, por eso van al final y fuera de la transacción.
        $this->crearCopias();

        $this->command?->info('Datos demo cargados. Usuarios demo con contraseña: ' . self::CLAVE);
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    /** Fecha/hora de Colombia convertida a UTC (la app guarda en UTC). */
    private function co(string $fechaHora): Carbon
    {
        return Carbon::parse($fechaHora, self::TZ)->utc();
    }

    private function url(string $ruta): string
    {
        return rtrim((string) config('app.url'), '/') . '/storage/' . ltrim($ruta, '/');
    }

    private function sello(Carbon $c): array
    {
        return ['created_at' => $c, 'updated_at' => $c];
    }

    private function aleatorio(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    private function copiarArchivos(): void
    {
        $disco = Storage::disk('public');
        $copias = [
            'imagenes/deportivo' => 'deportivo',
            'imagenes/kits' => 'deportivo/kits',
            'imagenes/patrocinadores' => 'deportivo/patrocinadores',
            'imagenes/ambientes' => 'ambientes',
            'imagenes/eventos' => 'eventos',
        ];
        foreach ($copias as $origen => $destino) {
            foreach (File::files($this->demo . '/' . $origen) as $archivo) {
                $disco->put($destino . '/demo-' . $archivo->getFilename(), File::get($archivo->getPathname()));
            }
        }
        foreach (File::files($this->demo . '/rutas') as $archivo) {
            if ($archivo->getExtension() === 'gpx') {
                $disco->put('rutas-gpx/demo-' . $archivo->getFilename(), File::get($archivo->getPathname()));
            }
        }
    }

    // ------------------------------------------------------------------
    // Usuarios
    // ------------------------------------------------------------------

    private function crearUsuarios(): void
    {
        $clave = Hash::make(self::CLAVE);
        // clave, nombre, apellido, documento, teléfono, nacimiento, RH, ciudad, registrado
        $lista = [
            ['laura', 'Laura Valentina', 'Gómez Pérez', 1057584321, '3124587410', '1996-04-12', 'O+', 'Sogamoso', '2026-02-03'],
            ['andres', 'Andrés Felipe', 'Rincón Barrera', 1057591234, '3108742256', '1990-09-27', 'A+', 'Sogamoso', '2026-02-05'],
            ['camilo', 'Camilo Ernesto', 'Vargas Niño', 74185296, '3214569870', '1984-01-18', 'O+', 'Duitama', '2026-02-10'],
            ['natalia', 'Natalia', 'Suárez Cárdenas', 1049632587, '3157841236', '1993-11-02', 'B+', 'Tunja', '2026-02-14'],
            ['julian', 'Julián David', 'Moreno Chaparro', 1057612345, '3134567812', '1999-06-08', 'O-', 'Aquitania', '2026-02-20'],
            ['diana', 'Diana Marcela', 'Pinzón Ávila', 46378912, '3209874512', '1988-03-25', 'A-', 'Sogamoso', '2026-03-01'],
            ['sebastian', 'Sebastián', 'Cely Estupiñán', 1052398741, '3112349876', '2001-08-14', 'O+', 'Paipa', '2026-03-04'],
            ['mariana', 'Mariana', 'Becerra Rojas', 1057623456, '3168529637', '1997-12-30', 'AB+', 'Nobsa', '2026-03-09'],
            ['jorge', 'Jorge Enrique', 'Patiño Salamanca', 9531478, '3185296374', '1972-05-19', 'O+', 'Sogamoso', '2026-03-12'],
            ['paola', 'Paola Andrea', 'Fonseca Díaz', 1057556789, '3002587413', '1991-07-07', 'A+', 'Tibasosa', '2026-04-02'],
            ['ricardo', 'Ricardo', 'Alarcón Mesa', 7175236, '3017418529', '1979-10-21', 'B-', 'Aquitania', '2026-04-15'],
            ['valentina', 'Valentina', 'Torres Holguín', 1002547896, '3226547893', '2003-02-11', 'O+', 'Duitama', '2026-05-06'],
            ['oscar', 'Óscar Iván', 'Ochoa Medina', 1057634567, '3119638527', '1995-01-29', 'A+', 'Sogamoso', '2026-05-20'],
            ['luisa', 'Luisa Fernanda', 'Castro Ortiz', 1053789456, '3145236987', '1994-09-16', 'O+', 'Tunja', '2026-06-11'],
            ['felipe', 'Felipe', 'Gutiérrez Bernal', 1018456789, '3176549821', '1986-06-03', 'A+', 'Bogotá', '2026-07-08'],
            ['carolina', 'Carolina', 'Mendoza Silva', 1057645678, '3198527413', '1992-04-27', 'O-', 'Sogamoso', '2026-08-19'],
            ['hernan', 'Hernán Darío', 'Rojas Cuervo', 4123789, '3128529631', '1968-12-05', 'O+', 'Iza', '2026-09-23'],
            ['sofia', 'Sofía', 'Ramírez León', 1057656789, '3204569871', '2000-10-09', 'B+', 'Sogamoso', '2026-10-28'],
            // Asistentes de los paneles
            ['camila', 'Camila Andrea', 'Rojas Vega', 1057667890, '3137894561', '1998-08-22', 'O+', 'Sogamoso', '2026-02-02'],
            ['daniel', 'Daniel Esteban', 'Pérez Lozano', 1057678901, '3159637412', '1996-05-13', 'A+', 'Sogamoso', '2026-02-02'],
        ];

        $idParticipante = DB::table('rol')->where('nombre_rol', 'participante')->value('id_rol');
        $idCliente = DB::table('rol')->where('nombre_rol', 'cliente')->value('id_rol');

        foreach ($lista as [$k, $nombre, $apellido, $doc, $tel, $nac, $rh, $ciudad, $alta]) {
            $correo = Str::of(Str::ascii(explode(' ', $nombre)[0] . '.' . explode(' ', $apellido)[0]))->lower() . '@skyed.test';
            $fecha = $this->co($alta . ' 10:' . str_pad((string) $this->aleatorio(0, 59), 2, '0', STR_PAD_LEFT));
            $fila = [
                'tipo_documento_u' => 'CC',
                'documento_u' => $doc,
                'nombre_u' => $nombre,
                'apellido_u' => $apellido,
                'rh_u' => $rh,
                'telefono_u' => $tel,
                'correo_u' => (string) $correo,
                'fecha_nacimiento_u' => $nac,
                'contrasena_u' => $clave,
                'estado_u' => $k === 'hernan' ? 'inactivo' : 'activo',
                'ciudad_u' => $ciudad,
                'acepta_terminos_u' => true,
                'terminos_aceptados_en' => $fecha,
                'acepta_privacidad_u' => true,
                'privacidad_aceptada_en' => $fecha,
            ] + $this->sello($fecha);

            // Si ya existe alguien con ese documento o teléfono, se corre un número.
            while (DB::table('usuario')->where('documento_u', $fila['documento_u'])->exists()) {
                $fila['documento_u']++;
            }
            while (DB::table('usuario')->where('telefono_u', $fila['telefono_u'])->exists()) {
                $fila['telefono_u'] = (string) ((int) $fila['telefono_u'] + 1);
            }

            $id = DB::table('usuario')->insertGetId($fila, 'id_u');
            $this->usuarios[$k] = $id;
            $this->datosUsuario[$k] = $fila;

            foreach ([[$idParticipante, 'deportivo'], [$idCliente, 'social']] as [$rol, $ctx]) {
                DB::table('usuario_contexto_rol')->insert(['id_u' => $id, 'id_rol' => $rol, 'contexto' => $ctx] + $this->sello($fecha));
            }
        }
    }

    private function crearAsistentes(): void
    {
        $idRol = DB::table('rol')->where('nombre_rol', 'asistente')->value('id_rol');
        $asistentes = [
            ['camila', $this->adminDeportivo, 'deportivo', [
                'eventos' => 'ver', 'inscripciones' => 'editar', 'acceso' => 'editar', 'kits' => 'editar',
                'resultados' => 'editar', 'usuarios' => 'ver', 'reportes' => 'ver',
            ]],
            ['daniel', $this->adminSocial, 'social', [
                'reservas' => 'editar', 'pqr' => 'editar', 'eventos' => 'ver', 'lugares' => 'ver',
                'servicios' => 'ver', 'usuarios' => 'ver', 'reportes' => 'ver',
            ]],
        ];
        foreach ($asistentes as [$k, $admin, $modulo, $permisos]) {
            $fecha = $this->co('2026-02-02 15:30');
            DB::table('usuario_contexto_rol')->insert(['id_u' => $this->usuarios[$k], 'id_rol' => $idRol, 'contexto' => $modulo] + $this->sello($fecha));
            DB::table('asistente')->insert([
                'id_admin' => $admin,
                'id_usuario' => $this->usuarios[$k],
                'modulo' => $modulo,
                'permisos' => json_encode($permisos),
                'activo' => true,
            ] + $this->sello($fecha));
        }
    }

    // ------------------------------------------------------------------
    // Deportivo
    // ------------------------------------------------------------------

    private function crearKits(): void
    {
        // clave, nombre, contenido, imagen, precio, entrega, lugar, tallas
        $kits = [
            ['trucha', 'Kit Travesía Trucha y Cebolla', 'Jersey manga larga línea pro, medias, número, chip y bolsa de hidratación', 'jersey-manga-larga.jpg', 85000, '2027-03-20', 'Parque principal de Aquitania', ['S' => 20, 'M' => 40, 'L' => 35, 'XL' => 15]],
            ['puerto', 'Kit Desafío Puerto + Largo', 'Jersey manga corta de competencia, medias KOM, número y medalla finisher', 'jersey-manga-corta.jpg', 95000, '2027-07-10', 'Coliseo de Aguazul', ['S' => 15, 'M' => 30, 'L' => 30, 'XL' => 10]],
            ['medias', 'Kit Medias KOM', 'Par de medias técnicas KOM, número y refrigerio', 'medias-kom.jpg', 25000, '2026-11-28', 'Plaza de la Villa de Paipa', ['S' => 25, 'M' => 40, 'L' => 30]],
            ['tota', 'Kit Gran Fondo Tota', 'Jersey manga corta, caramañola, número, chip y almuerzo campesino', 'jersey-manga-corta.jpg', 90000, '2026-12-05', 'Plaza de la Villa, Sogamoso', ['S' => 15, 'M' => 30, 'L' => 30, 'XL' => 15]],
            ['navidad', 'Kit Ruta Navideña', 'Medias KOM navideñas, luz trasera LED, número y chocolate con almojábana', 'medias-kom.jpg', 30000, '2026-12-12', 'Parque principal de Aquitania', ['S' => 20, 'M' => 30, 'L' => 20]],
            ['gravel', 'Kit Gravel Boyacá', 'Jersey manga larga, guantes y número', 'jersey-manga-larga.jpg', 80000, '2027-01-16', 'Parque principal de Iza', ['S' => 10, 'M' => 20, 'L' => 20, 'XL' => 10]],
        ];
        foreach ($kits as [$k, $nombre, $contenido, $img, $precio, $entrega, $lugar, $tallas]) {
            $fecha = $this->co('2026-02-15 09:00');
            $id = DB::table('kit')->insertGetId([
                'nombre_k' => $nombre,
                'stock_k' => array_sum($tallas),
                'fecha_entrega_k' => $entrega,
                'lugar_entrega_k' => $lugar,
                'contenido_k' => $contenido,
                'precio_k' => $precio,
                'imagen_k' => 'deportivo/kits/demo-' . $img,
            ] + $this->sello($fecha), 'id_k');
            foreach ($tallas as $talla => $cantidad) {
                DB::table('kit_talla')->insert(['id_k' => $id, 'talla_kt' => $talla, 'cantidad_kt' => $cantidad] + $this->sello($fecha));
            }
            $this->kits[$k] = $id;
        }
    }

    /** Descuenta una unidad de la talla (como hace la app al inscribir). */
    private function descontarTalla(int $idKit, string $talla): void
    {
        DB::table('kit_talla')->where('id_k', $idKit)->where('talla_kt', $talla)->decrement('cantidad_kt');
        DB::table('kit')->where('id_k', $idKit)->decrement('stock_k');
    }

    private function crearPatrocinadores(): void
    {
        // clave, nombre, aporte, estado
        $lista = [
            ['alcaldia-aguazul', 'Alcaldía de Aguazul', 'Apoyo institucional y logística de salida', 'activo'],
            ['alcaldia-aquitania', 'Alcaldía de Aquitania', 'Permisos, cierre de vías y tarima', 'activo'],
            ['alcaldia-pajarito', 'Alcaldía de Pajarito', 'Puesto de abastecimiento y socorristas', 'activo'],
            ['loteria-boyaca', 'Lotería de Boyacá', 'Premiación en efectivo para el podio', 'activo'],
            ['kom', 'KOM Sportswear', 'Indumentaria oficial: jerseys y medias', 'activo'],
            ['electrolit', 'Electrolit', 'Hidratación en todos los puntos de la ruta', 'activo'],
            ['alfa-bikes', 'Alfa Bikes Sogamoso', 'Asistencia mecánica en ruta', 'activo'],
            ['sara-perez', 'Sara Pérez Design', 'Diseño de medallas y piezas gráficas', 'activo'],
            ['entre-rocas', 'Cabañas Entre Rocas', 'Alojamiento para ganadores', 'activo'],
            ['anysof', 'AnySof Tecnología', 'Cronometraje y pantalla de resultados', 'activo'],
            ['luna-mia', 'Luna Mía Glamping', 'Noche de glamping para sorteos', 'activo'],
            ['canelo', 'Canelo Sabores', 'Refrigerios para participantes', 'activo'],
            ['trigo-de-oro', 'Trigo de Oro Panadería', 'Almojábanas y pan en la meta', 'activo'],
            ['innovarq', 'Innovarq Arquitectura e Ingeniería', 'Montaje de arco de meta y carpas', 'activo'],
            ['monica-aponte', 'Mónica Aponte', 'Fotografía oficial del evento', 'inactivo'],
            ['broasters', 'Broaster\'s Boyacá', 'Almuerzo de cierre para el staff', 'activo'],
            ['nlb', 'NLB Sports', 'Bonos de descuento en accesorios', 'inactivo'],
        ];
        foreach ($lista as [$k, $nombre, $aporte, $estado]) {
            $fecha = $this->co('2026-02-12 11:00');
            $this->patrocinadores[$k] = DB::table('patrocinador')->insertGetId([
                'nombre_p' => $nombre,
                'logo_p' => $this->url('deportivo/patrocinadores/demo-' . $k . '.jpg'),
                'telefono_p' => null,
                'correo_p' => null,
                'pagina_web_p' => null,
                'aporte_p' => $aporte,
                'estado_p' => $estado,
            ] + $this->sello($fecha), 'id_p');
        }
    }

    private function km(string $ruta): string
    {
        return number_format($this->rutas[$ruta]['distancia_km'], 0, ',', '.') . ' km';
    }

    private function desnivel(string $ruta): string
    {
        return number_format($this->rutas[$ruta]['desnivel_m'], 0, ',', '.') . ' m';
    }

    private function crearEventosDeportivos(): void
    {
        $eventos = [
            // ---- Finalizados (con resultados) ----
            'trucha2026' => [
                'nombre' => '5ta Gran Travesía Ruta de la Trucha y la Cebolla', 'cat' => 'mtb', 'fecha' => '2026-03-22', 'hora' => '08:00',
                'lugar' => 'Parque principal, Aquitania (Boyacá)', 'img' => 'foto-05.jpg', 'cupos' => 150, 'estado' => 'finalizado', 'precio' => 50000,
                'kit' => 'trucha', 'rutas' => ['trucha-larga' => 'Ruta larga', 'trucha-corta' => 'Ruta corta'],
                'desc' => 'Travesía MTB alrededor del Lago de Tota entre cultivos de cebolla y miradores. Incluye almuerzo, medalla, 4 puntos de hidratación, asistencia médica y mecánica.',
                'req' => 'Bicicleta MTB en buen estado, casco obligatorio, mayor de 15 años (menores con autorización), póliza incluida en la inscripción.',
                'patro' => ['alcaldia-aquitania', 'electrolit', 'kom', 'alfa-bikes', 'trigo-de-oro', 'luna-mia'],
            ],
            'vueltaTota' => [
                'nombre' => 'Vuelta al Lago de Tota', 'cat' => 'ruta', 'fecha' => '2026-05-17', 'hora' => '07:00',
                'lugar' => 'Parque principal, Aquitania (Boyacá)', 'img' => 'foto-16.jpg', 'cupos' => 120, 'estado' => 'finalizado', 'precio' => 60000,
                'kit' => null, 'rutas' => ['vuelta-tota' => 'Recorrido único'],
                'desc' => 'Recorrido de ruta por la vía que rodea el lago más grande de Colombia, pasando por Playa Blanca, Tota y Cuítiva a más de 3.000 m s. n. m.',
                'req' => 'Bicicleta de ruta, casco, luces delantera y trasera, chaleco reflectivo y certificado médico vigente.',
                'patro' => ['alcaldia-aquitania', 'electrolit', 'canelo'],
            ],
            'puerto2026' => [
                'nombre' => 'Desafío Puerto + Largo de Colombia', 'cat' => 'ruta', 'fecha' => '2026-07-12', 'hora' => '06:00',
                'lugar' => 'Parque principal, Aguazul (Casanare)', 'img' => 'foto-25.jpg', 'cupos' => 200, 'estado' => 'finalizado', 'precio' => 120000,
                'kit' => null, 'rutas' => ['puerto-largo' => 'Aguazul — Alto de La Sarna'],
                'desc' => 'El ascenso más largo del país: desde el piedemonte llanero en Aguazul hasta el Alto de La Sarna, pasando por Pajarito. Un reto de resistencia de principio a fin.',
                'req' => 'Bicicleta de ruta, casco, dos caramañolas, ropa para frío de páramo y certificado médico. Tiempo de corte: 8 horas.',
                'patro' => ['alcaldia-aguazul', 'alcaldia-pajarito', 'loteria-boyaca', 'kom', 'electrolit', 'anysof', 'innovarq', 'broasters'],
            ],
            'mongui' => [
                'nombre' => 'Travesía Monguí — Páramo de Ocetá', 'cat' => 'mtb', 'fecha' => '2026-08-23', 'hora' => '07:30',
                'lugar' => 'Plaza principal, Monguí (Boyacá)', 'img' => 'foto-07.jpg', 'cupos' => 80, 'estado' => 'finalizado', 'precio' => 45000,
                'kit' => null, 'rutas' => ['mongui-oceta' => 'Recorrido único'],
                'desc' => 'Salida desde uno de los pueblos más lindos de Colombia hacia los caminos del Páramo de Ocetá entre frailejones y senderos de montaña.',
                'req' => 'Bicicleta MTB, casco, guantes, impermeable y nivel intermedio. Grupo con guía y carro escoba.',
                'patro' => ['entre-rocas', 'electrolit', 'alfa-bikes'],
            ],
            'gravelTibasosa' => [
                'nombre' => 'Gravel Tibasosa — Firavitoba', 'cat' => 'gravel', 'fecha' => '2026-09-20', 'hora' => '08:00',
                'lugar' => 'Parque principal, Tibasosa (Boyacá)', 'img' => 'foto-09.jpg', 'cupos' => 90, 'estado' => 'finalizado', 'precio' => 40000,
                'kit' => null, 'rutas' => ['tibasosa-firavitoba' => 'Recorrido único'],
                'desc' => 'Caminos destapados entre los cultivos de feijoa de Tibasosa y las veredas de Firavitoba, con regreso por Sogamoso.',
                'req' => 'Bicicleta gravel o MTB, casco y kit de despinche. Apto para principiantes con buen estado físico.',
                'patro' => ['canelo', 'trigo-de-oro'],
            ],
            'clasicaDuitama' => [
                'nombre' => 'Clásica Sogamoso — Nobsa — Duitama', 'cat' => 'ruta', 'fecha' => '2026-10-18', 'hora' => '07:00',
                'lugar' => 'Plaza de la Villa, Sogamoso (Boyacá)', 'img' => 'foto-13.jpg', 'cupos' => 150, 'estado' => 'finalizado', 'precio' => 55000,
                'kit' => null, 'rutas' => ['sogamoso-duitama' => 'Recorrido único'],
                'desc' => 'Clásica de ruta por el corredor industrial de Boyacá con llegada a Duitama. Velocidad, trabajo en grupo y embalaje final.',
                'req' => 'Bicicleta de ruta, casco, licencia o carné de club (para la categoría Élite) y certificado médico.',
                'patro' => ['loteria-boyaca', 'anysof', 'kom', 'electrolit'],
            ],
            // ---- Próximos ----
            'sochagota' => [
                'nombre' => 'Ruta Recreativa Lago Sochagota', 'cat' => 'ruta', 'fecha' => '2026-11-29', 'hora' => '08:30',
                'lugar' => 'Malecón del Lago Sochagota, Paipa (Boyacá)', 'img' => 'foto-03.jpg', 'cupos' => 100, 'estado' => 'activo', 'precio' => 30000,
                'kit' => 'medias', 'rutas' => ['sochagota' => 'Recorrido familiar'],
                'desc' => 'Paseo recreativo para toda la familia alrededor del Lago Sochagota con paso por el Pantano de Vargas. Ritmo tranquilo y acompañamiento.',
                'req' => 'Cualquier tipo de bicicleta en buen estado y casco. Menores de 14 años con acudiente.',
                'patro' => ['trigo-de-oro', 'canelo'],
            ],
            'granFondo' => [
                'nombre' => 'Gran Fondo Lago de Tota', 'cat' => 'ruta', 'fecha' => '2026-12-06', 'hora' => '06:30',
                'lugar' => 'Plaza de la Villa, Sogamoso (Boyacá)', 'img' => 'foto-11.jpg', 'cupos' => 180, 'estado' => 'activo', 'precio' => 90000,
                'kit' => 'tota', 'rutas' => ['gran-fondo-tota' => 'Gran Fondo'],
                'desc' => 'Gran Fondo desde Sogamoso hacia Iza, Cuítiva, Tota, Playa Blanca y Aquitania con regreso por el Alto del Crucero. Cronometrado con chip.',
                'req' => 'Bicicleta de ruta, casco, luces, certificado médico y experiencia en recorridos de más de 60 km.',
                'patro' => ['electrolit', 'kom', 'anysof', 'loteria-boyaca', 'alfa-bikes'],
            ],
            'navidadPlaya' => [
                'nombre' => 'Travesía Navideña Aquitania — Playa Blanca', 'cat' => 'mtb', 'fecha' => '2026-12-13', 'hora' => '08:00',
                'lugar' => 'Parque principal, Aquitania (Boyacá)', 'img' => 'foto-08.jpg', 'cupos' => 120, 'estado' => 'activo', 'precio' => 40000,
                'kit' => 'navidad', 'rutas' => ['aquitania-playa-blanca' => 'Recorrido único'],
                'desc' => 'Travesía MTB de fin de año por las veredas de Aquitania hasta la arena blanca del Lago de Tota. Novena, rifas y chocolate en la meta.',
                'req' => 'Bicicleta MTB, casco y ropa para frío. Disfraz navideño opcional (hay premio al mejor).',
                'patro' => ['alcaldia-aquitania', 'trigo-de-oro', 'luna-mia'],
            ],
            'nocturna' => [
                'nombre' => 'Ruta Nocturna de Luces Aquitania', 'cat' => 'ruta', 'fecha' => '2026-12-20', 'hora' => '18:30',
                'lugar' => 'Basílica de Aquitania, parque principal', 'img' => 'foto-22.jpg', 'cupos' => 80, 'estado' => 'activo', 'precio' => 25000,
                'kit' => null, 'rutas' => ['nocturna-aquitania' => 'Circuito nocturno'],
                'desc' => 'Recorrido nocturno por el alumbrado navideño de Aquitania y la orilla del lago. Salida y llegada frente a la basílica iluminada.',
                'req' => 'Luz delantera y trasera obligatorias, chaleco reflectivo y casco.',
                'patro' => ['alcaldia-aquitania', 'broasters'],
            ],
            'gravelIza' => [
                'nombre' => 'Gravel Iza — Pesca', 'cat' => 'gravel', 'fecha' => '2027-01-17', 'hora' => '08:00',
                'lugar' => 'Parque principal, Iza (Boyacá)', 'img' => 'foto-04.jpg', 'cupos' => 90, 'estado' => 'activo', 'precio' => 50000,
                'kit' => 'gravel', 'rutas' => ['iza-pesca' => 'Recorrido único'],
                'desc' => 'Ruta gravel entre el pueblo de los postres (Iza), Pesca y Firavitoba. Termales y postres en la meta.',
                'req' => 'Bicicleta gravel o MTB, casco, kit de despinche y caramañola.',
                'patro' => ['canelo', 'electrolit'],
            ],
            'villaLeyva' => [
                'nombre' => 'MTB Villa de Leyva — Desierto de La Candelaria', 'cat' => 'mtb', 'fecha' => '2027-02-14', 'hora' => '07:30',
                'lugar' => 'Plaza mayor, Villa de Leyva (Boyacá)', 'img' => 'foto-10.jpg', 'cupos' => 100, 'estado' => 'activo', 'precio' => 70000,
                'kit' => null, 'rutas' => ['villa-leyva-candelaria' => 'Recorrido único'],
                'desc' => 'Senderos secos y arenosos entre Villa de Leyva, el Desierto de La Candelaria y Ráquira. Paisaje único en Boyacá.',
                'req' => 'Bicicleta MTB, casco, protector solar y mínimo 1,5 L de agua.',
                'patro' => ['entre-rocas', 'sara-perez'],
            ],
            'trucha2027' => [
                'nombre' => '6ta Gran Travesía Ruta de la Trucha y la Cebolla', 'cat' => 'mtb', 'fecha' => '2027-03-21', 'hora' => '08:00',
                'lugar' => 'Parque principal, Aquitania (Boyacá)', 'img' => 'foto-26.jpg', 'cupos' => 150, 'estado' => 'activo', 'precio' => 55000,
                'kit' => 'trucha', 'rutas' => ['trucha-larga' => 'Ruta larga', 'trucha-corta' => 'Ruta corta'],
                'desc' => 'Vuelve la travesía más querida del Lago de Tota. Ruta larga y corta, almuerzo, medalla, póliza, rifas y asistencia en todo el recorrido.',
                'req' => 'Bicicleta MTB en buen estado, casco obligatorio, mayor de 15 años (menores con autorización).',
                'patro' => ['alcaldia-aquitania', 'electrolit', 'kom', 'alfa-bikes', 'trigo-de-oro'],
            ],
            'tunja' => [
                'nombre' => 'Clásica Tunja — Puente de Boyacá', 'cat' => 'ruta', 'fecha' => '2027-04-11', 'hora' => '07:00',
                'lugar' => 'Plaza de Bolívar, Tunja (Boyacá)', 'img' => 'foto-21.jpg', 'cupos' => 120, 'estado' => 'activo', 'precio' => 45000,
                'kit' => null, 'rutas' => ['tunja-puente-boyaca' => 'Ida y regreso'],
                'desc' => 'Ruta histórica de ida y regreso hasta el Puente de Boyacá, escenario de la batalla de independencia.',
                'req' => 'Bicicleta de ruta, casco, luces y certificado médico.',
                'patro' => ['loteria-boyaca', 'innovarq'],
            ],
            'puerto2027' => [
                'nombre' => 'Desafío Puerto + Largo de Colombia 2027', 'cat' => 'ruta', 'fecha' => '2027-07-11', 'hora' => '06:00',
                'lugar' => 'Parque principal, Aguazul (Casanare)', 'img' => 'foto-23.jpg', 'cupos' => 200, 'estado' => 'inactivo', 'precio' => 130000,
                'kit' => 'puerto', 'rutas' => ['puerto-largo' => 'Aguazul — Alto de La Sarna'],
                'desc' => 'Segunda edición del ascenso más largo de Colombia. Inscripciones se abren en enero: el evento aún no está publicado.',
                'req' => 'Bicicleta de ruta, casco, ropa para frío de páramo y certificado médico. Tiempo de corte: 8 horas.',
                'patro' => ['alcaldia-aguazul', 'alcaldia-pajarito', 'kom'],
            ],
            'laguna' => [
                'nombre' => 'Travesía Siscunsí — Laguna Negra', 'cat' => 'mtb', 'fecha' => '2027-05-16', 'hora' => '07:00',
                'lugar' => 'Vereda Siscunsí, Sogamoso (Boyacá)', 'img' => 'foto-19.jpg', 'cupos' => 60, 'estado' => 'inhabilitado', 'precio' => 60000,
                'kit' => null, 'rutas' => [],
                'desc' => 'Travesía de páramo hacia la Laguna Negra. Suspendida temporalmente mientras la autoridad ambiental renueva el permiso de paso.',
                'req' => 'Bicicleta MTB, casco, ropa impermeable y experiencia en alta montaña.',
                'patro' => [],
            ],
        ];

        foreach ($eventos as $k => $e) {
            $creado = Carbon::parse($e['fecha'], self::TZ)->subDays(75)->setTime(9, 15)->utc();
            if ($creado->isFuture()) {
                $creado = $this->co('2026-11-10 09:15');
            }
            $rutaPrincipal = array_key_first($e['rutas']);
            $id = DB::table('eventoDeportivo')->insertGetId([
                'nombre_e' => $e['nombre'],
                'categoria_e' => $e['cat'],
                'fecha_e' => $e['fecha'],
                'hora_e' => $e['hora'],
                'ubicacion_e' => $e['lugar'],
                'descripcion_e' => Str::limit($e['desc'], 252),
                'requisitos_e' => Str::limit($e['req'], 252),
                'imagen_e' => 'deportivo/demo-' . $e['img'],
                'cupos_disponibles_e' => $e['cupos'],
                'estado_e' => $e['estado'],
                'creado_e' => $creado,
                'id_k' => $e['kit'] ? $this->kits[$e['kit']] : null,
                'id_u' => $this->adminDeportivo,
                'precio_e' => $e['precio'],
                'distancia_e' => $rutaPrincipal ? $this->km($rutaPrincipal) : '38 km',
                'desnivel_e' => $rutaPrincipal ? $this->desnivel($rutaPrincipal) : '1.150 m',
            ] + $this->sello($creado), 'id_e');
            $this->eventos[$k] = $id;
            $this->datosEvento[$k] = $e;

            // Rutas con su GPX real
            foreach ($e['rutas'] as $clave => $etiqueta) {
                $r = $this->rutas[$clave];
                DB::table('ruta_evento')->insert([
                    'nombre_re' => Str::limit($etiqueta . ' · ' . $r['nombre'], 97),
                    'estado_re' => 'activo',
                    'distancia_re' => $this->km($clave),
                    'desnivel_re' => $this->desnivel($clave),
                    'descripcion_re' => Str::limit('Altitud máxima ' . number_format($r['altitud_max_m'], 0, ',', '.') . ' m s. n. m. Trazado sobre vías reales; descarga el GPX para tu GPS o ciclocomputador.', 252),
                    'archivo_gpx_re' => 'rutas-gpx/demo-' . $clave . '.gpx',
                    'precio_re' => null,
                    'id_e' => $id,
                ] + $this->sello($creado));
            }

            // Estaciones (de la ruta principal)
            if ($rutaPrincipal) {
                $estadoEst = $e['estado'] === 'finalizado' ? 'cerrada' : 'activa';
                foreach ($this->rutas[$rutaPrincipal]['estaciones'] as $est) {
                    DB::table('estacion')->insert([
                        'nombre_est' => $est['nombre'],
                        'tipo_est' => $est['tipo'],
                        'kilometro_est' => (string) $est['km'],
                        'latitud_est' => $est['lat'],
                        'longitud_est' => $est['lon'],
                        'descripcion_pest' => match ($est['tipo']) {
                            'hidratacion' => 'Agua, Electrolit, banano y bocadillo veleño.',
                            'primeros_auxilios' => 'Paramédicos de la Defensa Civil y ambulancia básica.',
                            'meta' => 'Arco de meta, cronometraje y entrega de medallas.',
                            default => 'Revisión de número, chip y casco antes de la salida.',
                        },
                        'estado_est' => $estadoEst,
                        'id_e' => $id,
                    ] + $this->sello($creado));
                }
            }

            // Categorías de competencia (niveles)
            $cats = $e['cat'] === 'mtb' || $e['cat'] === 'gravel'
                ? [['Élite', 18, 39, 'masculino'], ['Damas', 18, 60, 'femenino'], ['Máster', 40, 70, 'masculino'], ['Recreativa', 15, 70, 'mixto']]
                : [['Élite', 18, 39, 'masculino'], ['Damas', 18, 60, 'femenino'], ['Máster', 40, 70, 'masculino']];
            foreach ($cats as [$nombre, $min, $max, $genero]) {
                $this->categorias[$k][$nombre] = DB::table('categoria_competencia')->insertGetId([
                    'nombre_cc' => $nombre,
                    'edad_minima_cc' => $min,
                    'edad_maxima_cc' => $max,
                    'genero_cc' => $genero,
                    'distancia_cc' => $nombre === 'Recreativa' && count($e['rutas']) > 1
                        ? $this->km(array_keys($e['rutas'])[1])
                        : ($rutaPrincipal ? $this->km($rutaPrincipal) : null),
                    'descripcion_cc' => match ($nombre) {
                        'Élite' => 'Hombres de 18 a 39 años con experiencia competitiva.',
                        'Damas' => 'Mujeres desde los 18 años, clasificación única.',
                        'Máster' => 'Hombres de 40 años en adelante.',
                        default => 'Abierta para quienes buscan disfrutar el recorrido.',
                    },
                    'id_e' => $id,
                ] + $this->sello($creado), 'id_cc');
            }

            foreach ($e['patro'] as $p) {
                DB::table('evento_patrocinador')->insert([
                    'patrocinador_id_p' => $this->patrocinadores[$p],
                    'evento_id_e' => $id,
                    'detalle' => DB::table('patrocinador')->where('id_p', $this->patrocinadores[$p])->value('aporte_p'),
                ] + $this->sello($creado));
            }
        }
    }

    /** Categoría que le corresponde a un usuario según su edad y género. */
    private function categoriaPara(string $evento, string $usuario): ?int
    {
        $cats = $this->categorias[$evento] ?? [];
        $mujer = in_array($usuario, ['laura', 'natalia', 'diana', 'mariana', 'paola', 'valentina', 'luisa', 'carolina', 'sofia', 'camila'], true);
        $edad = Carbon::parse($this->datosUsuario[$usuario]['fecha_nacimiento_u'])->age;
        if ($mujer) {
            return $cats['Damas'] ?? null;
        }
        if ($edad >= 40) {
            return $cats['Máster'] ?? null;
        }
        return $cats['Élite'] ?? null;
    }

    private function crearInscripciones(): void
    {
        $parentescos = [['Madre', 'Martha Pérez'], ['Esposo(a)', 'Claudia Rodríguez'], ['Padre', 'Luis Alberto Moreno'], ['Hermano(a)', 'Andrea Cárdenas'], ['Esposo(a)', 'Jaime Ávila'], ['Madre', 'Gloria Barrera']];
        $tallas = ['S', 'M', 'M', 'L', 'L', 'XL'];
        $metodos = ['nequi', 'nequi', 'transferencia', 'efectivo'];

        // evento => [usuario => opciones]  (estado: confirmada|pendiente|rechazado; kit; invitado; dnf/dq)
        $plan = [
            'trucha2026' => ['julian' => ['kit' => 'M'], 'andres' => ['kit' => 'L'], 'ricardo' => ['kit' => 'L'], 'laura' => ['kit' => 'S'], 'camilo' => [], 'hernan' => ['dq' => true], 'oscar' => ['kit' => 'M', 'recreativa' => true]],
            'vueltaTota' => ['andres' => [], 'camilo' => [], 'natalia' => [], 'jorge' => []],
            'puerto2026' => ['andres' => [], 'sebastian' => [], 'felipe' => [], 'diana' => []],
            'mongui' => ['julian' => [], 'ricardo' => [], 'paola' => []],
            'gravelTibasosa' => ['paola' => [], 'oscar' => [], 'mariana' => []],
            'clasicaDuitama' => ['sebastian' => [], 'camilo' => [], 'valentina' => [], 'jorge' => [], 'felipe' => []],
            'sochagota' => ['mariana' => ['kit' => 'S'], 'carolina' => ['kit' => 'M', 'invitado' => true], 'sofia' => ['estado' => 'pendiente']],
            'granFondo' => ['andres' => ['kit' => 'L'], 'sebastian' => ['kit' => 'M'], 'felipe' => ['kit' => 'L', 'estado' => 'pendiente'], 'natalia' => ['kit' => 'S'], 'jorge' => ['estado' => 'rechazado'], 'valentina' => ['kit' => 'S']],
            'navidadPlaya' => ['julian' => ['kit' => 'M', 'invitado' => true], 'ricardo' => [], 'laura' => ['kit' => 'S', 'estado' => 'pendiente']],
            'nocturna' => ['oscar' => [], 'luisa' => []],
            'gravelIza' => ['paola' => ['kit' => 'M'], 'oscar' => ['estado' => 'pendiente']],
            'trucha2027' => ['julian' => ['kit' => 'M'], 'luisa' => ['kit' => 'S', 'estado' => 'pendiente']],
            'villaLeyva' => ['diana' => []],
        ];

        $invitados = [
            ['CC', 1057701234, 'Martha Lucía', 'Gómez Rincón', 'O+', '3115552233', '1970-03-14', 'S'],
            ['TI', 1052456123, 'Tomás', 'Moreno Chaparro', 'O+', '3124443322', '2010-06-21', 'S'],
        ];
        $iInv = 0;

        foreach ($plan as $ev => $inscritos) {
            $idE = $this->eventos[$ev];
            $e = $this->datosEvento[$ev];
            $finalizado = $e['estado'] === 'finalizado';
            $idKit = $e['kit'] ? $this->kits[$e['kit']] : null;
            $precioKit = $idKit ? (float) DB::table('kit')->where('id_k', $idKit)->value('precio_k') : 0.0;
            $dorsal = 100;
            $cuposUsados = 0;
            $diasAntes = 40;

            foreach ($inscritos as $u => $op) {
                $idU = $this->usuarios[$u];
                $estadoPago = $op['estado'] ?? 'confirmada';
                $estadoI = match ($estadoPago) { 'pendiente' => 'pendiente', 'rechazado' => 'cancelada', default => 'confirmada' };
                $deseaKit = $idKit && isset($op['kit']);
                $fecha = Carbon::parse($e['fecha'], self::TZ)->subDays($diasAntes)->setTime($this->aleatorio(7, 21), $this->aleatorio(0, 59))->utc();
                if ($fecha->isFuture()) {
                    $fecha = $this->co('2026-11-1' . $this->aleatorio(0, 6) . ' 19:20');
                }
                $diasAntes = max(3, $diasAntes - $this->aleatorio(2, 6));

                $idInv = null;
                $invKit = false;
                if (!empty($op['invitado'])) {
                    [$td, $doc, $n, $a, $rh, $tel, $nac, $tallaInv] = $invitados[$iInv++];
                    $invKit = (bool) $idKit;
                    $idInv = DB::table('invitados')->insertGetId([
                        'tipo_documento' => $td, 'documento_inv' => $doc, 'nombre_inv' => $n, 'apellido_inv' => $a,
                        'rh_inv' => $rh, 'telefono_inv' => $tel, 'fecha_nacimiento_inv' => $nac, 'correo_inv' => null,
                        'desea_kit_inv' => $invKit, 'talla_kit_inv' => $invKit ? $tallaInv : null, 'numero_dorsal_inv' => ++$dorsal,
                    ] + $this->sello($fecha), 'id_inv');
                }

                $precioEvento = (float) $e['precio'];
                $total = $precioEvento + ($deseaKit ? $precioKit : 0) + ($idInv ? $precioEvento + ($invKit ? $precioKit : 0) : 0);
                [$parentesco, $contacto] = $parentescos[$this->aleatorio(0, count($parentescos) - 1)];
                $cupo = $idInv ? 2 : 1;
                $idCat = !empty($op['recreativa']) ? ($this->categorias[$ev]['Recreativa'] ?? null) : $this->categoriaPara($ev, $u);

                $idI = DB::table('inscripcion')->insertGetId([
                    'cupo_i' => $cupo,
                    'estado_i' => $estadoI,
                    'fecha_i' => $fecha,
                    'precio_pagado_i' => $total,
                    'precio_inscripcion_i' => $precioEvento,
                    'precio_kit_i' => $deseaKit ? $precioKit : 0,
                    'precio_invitado_i' => $idInv ? $precioEvento : 0,
                    'precio_kit_invitado_i' => $invKit ? $precioKit : 0,
                    'contacto_emergencia_nombre' => $contacto,
                    'contacto_emergencia_telefono' => '31' . $this->aleatorio(10000000, 99999999),
                    'contacto_emergencia_parentesco' => $parentesco,
                    'telefono_contacto_i' => $this->datosUsuario[$u]['telefono_u'],
                    'condiciones_medicas_i' => match ($u) { 'jorge' => 'Hipertensión controlada con medicamento.', 'natalia' => 'Alergia a la penicilina.', default => null },
                    'talla_camiseta_i' => $deseaKit ? $op['kit'] : null,
                    'numero_dorsal_i' => ++$dorsal,
                    'desea_kit_i' => $deseaKit,
                    'id_cc' => $idCat,
                    'id_u' => $idU,
                    'id_e' => $idE,
                    'id_inv' => $idInv,
                ] + $this->sello($fecha), 'id_i');

                if ($estadoI !== 'cancelada') {
                    $cuposUsados += $cupo;
                    if ($deseaKit) {
                        $this->descontarTalla($idKit, $op['kit']);
                    }
                    if ($invKit) {
                        $this->descontarTalla($idKit, 'S');
                    }
                }

                // Pago
                $metodo = $metodos[$this->aleatorio(0, 3)];
                $estadoP = match ($estadoPago) { 'pendiente' => 'pendiente', 'rechazado' => 'rechazado', default => 'confirmado' };
                DB::table('pago')->insert([
                    'metodo_pago_p' => $metodo,
                    'referencia_p' => $metodo === 'efectivo' ? null : ($metodo === 'nequi' ? 'M' . $this->aleatorio(1000000, 9999999) : 'TRF-' . $this->aleatorio(100000, 999999)),
                    'comprobante_p' => null,
                    'monto_p' => $total,
                    'fecha_p' => $fecha,
                    'estado_p' => $estadoP,
                    'id_i' => $idI,
                ] + $this->sello($fecha->copy()->addHours($estadoP === 'pendiente' ? 0 : 20)));

                // QR de entrada
                $usado = $finalizado && empty($op['dq']);
                DB::table('qr_entrada')->insert([
                    'codigo_qr' => (string) Str::uuid(),
                    'qr_imagen_qr' => null,
                    'fecha_generacion_qr' => $fecha,
                    'fecha_uso_qr' => $finalizado ? $this->co($e['fecha'] . ' ' . $e['hora'])->subMinutes($this->aleatorio(15, 50)) : null,
                    'estado_qr' => match (true) {
                        $estadoI === 'cancelada' => 'cancelado',
                        $estadoI === 'pendiente' => 'pendiente',
                        $finalizado => 'usado',
                        default => 'activo',
                    },
                    'usos_permitidos_qr' => $cupo,
                    'usos_realizados_qr' => $finalizado ? $cupo : 0,
                    'codigo_kit_qr' => ($deseaKit || $invKit) ? 'KIT-' . strtolower((string) Str::uuid()) : null,
                    'id_i' => $idI,
                ] + $this->sello($fecha));

                // Historial
                DB::table('historial_participacion')->insert([
                    'fecha_hp' => $finalizado ? $this->co($e['fecha'] . ' 15:00') : $fecha,
                    'estado_hp' => $finalizado ? (!empty($op['dq']) ? 'abandono' : 'finalizado') : 'inscrito',
                    'observaciones_hp' => !empty($op['dq']) ? 'Se retiró en el km 18 por falla mecánica (cadena rota).' : null,
                    'id_u' => $idU,
                    'id_e' => $idE,
                    'id_i' => $idI,
                ] + $this->sello($fecha));

                // Entrega de kits
                if ($idKit) {
                    foreach (array_filter([$deseaKit ? null : false, $invKit ? $idInv : false], fn ($v) => $v !== false) as $inv) {
                        DB::table('entrega_kit')->insert([
                            'estado_ek' => $finalizado ? 'entregado' : 'pendiente',
                            'fecha_entrega_real_ek' => $finalizado ? $this->co(Carbon::parse($e['fecha'])->subDay()->toDateString() . ' 16:30') : null,
                            'persona_entrega_ek' => $finalizado ? 'Camila Rojas' : null,
                            'observaciones_ek' => null,
                            'id_k' => $idKit,
                            'id_u' => $idU,
                            'id_e' => $idE,
                            'id_i' => $idI,
                            'id_inv' => $inv,
                        ] + $this->sello($fecha));
                    }
                }

                // Notificaciones para el participante
                $this->notificar($idU, 'Recibimos tu inscripción', 'Te inscribiste a «' . $e['nombre'] . '». Se confirma cuando validemos tu pago.', 'inscripcion', $fecha, true);
                if ($estadoP === 'confirmado') {
                    $this->notificar($idU, 'Actualización de tu pago', 'Tu pago fue confirmado. ¡Tu inscripción quedó lista!', 'pago', $fecha->copy()->addHours(20), $finalizado);
                } elseif ($estadoP === 'rechazado') {
                    $this->notificar($idU, 'Actualización de tu pago', 'Tu pago fue rechazado. Por favor verifica tu comprobante.', 'pago', $fecha->copy()->addHours(30), false);
                }

                if ($finalizado) {
                    $this->registrosResultado[$ev][] = ['id_i' => $idI, 'u' => $u, 'id_cc' => $idCat, 'dq' => !empty($op['dq']), 'recreativa' => !empty($op['recreativa'])];
                }
            }

            DB::table('eventoDeportivo')->where('id_e', $idE)->decrement('cupos_disponibles_e', $cuposUsados);
        }

        $this->crearResultados();
    }

    private array $registrosResultado = [];

    private function crearResultados(): void
    {
        // Velocidad base (km/h) por categoría y tipo de evento.
        $base = ['Élite' => 1.0, 'Máster' => 0.88, 'Damas' => 0.84, 'Recreativa' => 0.7];
        $premios = [1 => ['Trofeo y medalla de oro', 300000], 2 => ['Medalla de plata', 150000], 3 => ['Medalla de bronce', 80000]];

        foreach ($this->registrosResultado as $ev => $filas) {
            $e = $this->datosEvento[$ev];
            $rutaKey = array_key_first($e['rutas']);
            $ruta = $this->rutas[$rutaKey];
            $velocidad = match ($e['cat']) { 'mtb' => 15.5, 'gravel' => 21.0, default => 27.0 };
            $velocidad *= max(0.55, 1 - $ruta['desnivel_m'] / ($ruta['distancia_km'] * 60));
            $nombresCat = array_flip($this->categorias[$ev]);

            $calc = [];
            foreach ($filas as $f) {
                $cat = $nombresCat[$f['id_cc']] ?? 'Élite';
                $dist = $f['recreativa'] && count($e['rutas']) > 1 ? $this->rutas[array_keys($e['rutas'])[1]]['distancia_km'] : $ruta['distancia_km'];
                $v = $velocidad * ($base[$cat] ?? 0.9) * (0.9 + $this->aleatorio(0, 20) / 100);
                $seg = (int) round($dist / $v * 3600);
                $calc[] = $f + ['seg' => $seg, 'cat' => $cat];
            }

            // Posición general: solo quienes terminaron, por tiempo.
            $validos = array_values(array_filter($calc, fn ($c) => !$c['dq']));
            usort($validos, fn ($a, $b) => $a['seg'] <=> $b['seg']);
            $posGeneral = [];
            foreach ($validos as $i => $c) {
                $posGeneral[$c['id_i']] = $i + 1;
            }
            $porCat = [];
            foreach ($validos as $c) {
                $porCat[$c['cat']][] = $c['id_i'];
            }

            $fechaRes = $this->co($e['fecha'] . ' 18:00');
            foreach ($calc as $c) {
                $tiempo = $c['dq'] ? '01:52:10' : gmdate('H:i:s', $c['seg']);
                $idR = DB::table('resultado')->insertGetId([
                    'tiempo_final_r' => $tiempo,
                    'posicion_general_r' => $c['dq'] ? null : $posGeneral[$c['id_i']],
                    'estado_r' => $c['dq'] ? 'descalificado' : 'oficial',
                    'id_i' => $c['id_i'],
                ] + $this->sello($fechaRes), 'id_r');

                $posCat = $c['dq'] ? null : array_search($c['id_i'], $porCat[$c['cat']], true) + 1;
                DB::table('categoria_resultado')->insert([
                    'posicion_categoria' => $posCat ?? 0,
                    'estado_competidor' => $c['dq'] ? 'descalificado' : 'clasificado',
                    'id_cc' => $c['id_cc'],
                    'id_r' => $idR,
                ] + $this->sello($fechaRes));

                if ($posCat && $posCat <= 3 && isset($premios[$posCat])) {
                    [$nombre, $valor] = $premios[$posCat];
                    DB::table('premio')->insert([
                        'nombre_premio' => $nombre . ' — ' . $c['cat'],
                        'descripcion_premio' => $posCat === 1 ? 'Incluye bono de la Lotería de Boyacá y kit KOM.' : 'Entrega en la tarima de premiación.',
                        'posicion_premio' => $posCat,
                        'valor_premio' => $c['cat'] === 'Recreativa' ? 0 : $valor,
                        'id_r' => $idR,
                    ] + $this->sello($fechaRes));
                }

                $idU = $this->usuarios[$c['u']];
                $this->notificar($idU, 'Resultados publicados', 'Ya están los resultados oficiales de «' . $e['nombre'] . '». ¡Revisa tu tiempo y posición!', 'resultado', $fechaRes, true);
            }
        }
    }

    // ------------------------------------------------------------------
    // Social
    // ------------------------------------------------------------------

    private function crearServiciosYLugares(): void
    {
        $servicios = [
            ['Catering gourmet', 'Menú de autor a la mesa con entrada, plato fuerte y postre.'],
            ['Buffet campestre', 'Buffet con platos típicos boyacenses: cocido, mute y trucha.'],
            ['Mesa de postres', 'Mesa decorada con postres de Iza, cupcakes y dulces artesanales.'],
            ['Torta personalizada', 'Torta de hasta 4 pisos con el diseño que elijas.'],
            ['DJ y sonido profesional', 'DJ con cabina, sonido line array y luces de pista.'],
            ['Orquesta en vivo', 'Orquesta de 8 músicos: salsa, merengue, vallenato y crossover.'],
            ['Fotografía profesional', 'Cobertura completa, edición y galería digital en 15 días.'],
            ['Video y dron', 'Video resumen en 4K y tomas aéreas con dron.'],
            ['Decoración floral', 'Centros de mesa, arco floral y bouquet con flores frescas.'],
            ['Iluminación ambiental', 'Luces cálidas, guirnaldas y uplights para el salón.'],
            ['Mobiliario y mantelería', 'Sillas Tiffany o Crossback, mesas y mantelería fina.'],
            ['Barra de coctelería', 'Bartender, cócteles de autor y bebidas sin alcohol.'],
            ['Hora loca y animación', 'Animadores, accesorios y show de 45 minutos.'],
            ['Coordinación del evento', 'Coordinador que dirige el cronograma el día del evento.'],
            ['Transporte de invitados', 'Buses climatizados ida y regreso desde el punto acordado.'],
            ['Pantallas y streaming', 'Pantalla LED, proyección y transmisión en vivo.'],
        ];
        foreach ($servicios as [$n, $d]) {
            $existente = DB::table('servicio')->where('nombre_s', $n)->value('id_s');
            $this->servicios[$n] = $existente ?: DB::table('servicio')->insertGetId(['nombre_s' => $n, 'descripcion_s' => $d] + $this->sello($this->co('2026-02-08 10:00')), 'id_s');
        }

        // clave, nombre, descripción, capacidad, precio, imagen, servicios
        $lugares = [
            ['cristal', 'Salón Cristal', 'Salón con techo de luces cálidas y piso en mármol, en el centro de Sogamoso. Ideal para bodas y XV años.', 250, 4800000, 'lugar-01.jpg', ['Catering gourmet', 'DJ y sonido profesional', 'Iluminación ambiental', 'Mobiliario y mantelería', 'Decoración floral']],
            ['dorado', 'Gran Salón Dorado', 'Salón de gala con pantalla LED panorámica y sillas doradas en Duitama.', 350, 6500000, 'lugar-07.jpg', ['Catering gourmet', 'Orquesta en vivo', 'Pantallas y streaming', 'Barra de coctelería', 'Iluminación ambiental']],
            ['vidrio', 'Casa de Vidrio Jardín Interior', 'Espacio tipo invernadero con árbol central y techo de vidrio en Paipa, perfecto para eventos de día.', 180, 4200000, 'lugar-08.jpg', ['Buffet campestre', 'Decoración floral', 'Fotografía profesional', 'Mobiliario y mantelería']],
            ['hacienda', 'Hacienda El Portón', 'Antigua bodega de hacienda restaurada en Tibasosa con capacidad para grandes celebraciones.', 400, 7200000, 'lugar-10.jpg', ['Buffet campestre', 'Orquesta en vivo', 'Transporte de invitados', 'Hora loca y animación', 'Mobiliario y mantelería']],
            ['arrayanes', 'Salón Blanco Los Arrayanes', 'Salón luminoso de doble altura con ventanales y jardín vertical en Sogamoso.', 160, 3500000, 'lugar-11.jpg', ['Catering gourmet', 'Mesa de postres', 'Decoración floral', 'DJ y sonido profesional']],
            ['lavanda', 'Salón Lavanda', 'Salón con iluminación lila, sillas Tiffany y pista amplia en Tunja. Muy pedido para XV años.', 220, 4000000, 'lugar-13.jpg', ['DJ y sonido profesional', 'Hora loca y animación', 'Mesa de postres', 'Iluminación ambiental', 'Torta personalizada']],
            ['sugamuxi', 'Centro de Convenciones Sugamuxi', 'Auditorio y salón de banquetes para eventos empresariales en Sogamoso.', 500, 8500000, 'lugar-14.jpg', ['Pantallas y streaming', 'Catering gourmet', 'Coordinación del evento', 'Mobiliario y mantelería']],
            ['palmas', 'Terraza Las Palmas', 'Terraza al aire libre con palmas y guirnaldas de luces en Paipa, cerca de los termales.', 120, 3200000, 'lugar-05.jpg', ['Barra de coctelería', 'Iluminación ambiental', 'DJ y sonido profesional', 'Catering gourmet']],
            ['esperanza', 'Jardín La Esperanza', 'Jardín campestre con mesa imperial y vista a las montañas en Nobsa.', 150, 3800000, 'lugar-15.jpg', ['Buffet campestre', 'Decoración floral', 'Fotografía profesional', 'Coordinación del evento']],
            ['mirador', 'Mirador de Tota', 'Salón con chimenea y comedor de madera a orillas del Lago de Tota, en Aquitania.', 90, 2900000, 'lugar-03.jpg', ['Buffet campestre', 'Fotografía profesional', 'Video y dron', 'Decoración floral']],
        ];
        foreach ($lugares as [$k, $n, $d, $cap, $precio, $img, $servs]) {
            $f = $this->co('2026-02-09 10:30');
            $id = DB::table('ambiente')->insertGetId([
                'nombre_a' => $n,
                'descripcion_a' => $d,
                'capacidad_a' => $cap,
                'precio_referencia_a' => $precio,
                'imagen_principal_a' => $this->url('ambientes/demo-' . $img),
            ] + $this->sello($f), 'id_a');
            foreach ($servs as $s) {
                DB::table('ambiente_servicio')->insert(['id_a' => $id, 'id_s' => $this->servicios[$s]]);
            }
            $this->ambientes[$k] = $id;
        }
    }

    private function crearEventosSociales(): void
    {
        $this->tipos = DB::table('tipo_evento')->where('modulo_tipo_eves', 'social')->pluck('id_tipo_eves', 'nombre_tipo_eves')->all();

        // clave, nombre, descripción, fecha, tipo, lugar, imagen, estado
        $eventos = [
            ['bodaJardin', 'Boda al atardecer en terraza', 'Ceremonia y brindis al aire libre con torta de tres pisos y vista a la montaña.', '2026-04-18', 'Boda', 'palmas', 'evento-14.jpg', 'activo'],
            ['bodaSalon', 'Boda clásica en Salón Cristal', 'Recepción con 200 invitados, brindis con las damas de honor y orquesta en vivo.', '2026-05-09', 'Boda', 'cristal', 'evento-09.jpg', 'activo'],
            ['bodaFlores', 'Boda floral en Casa de Vidrio', 'Mesa principal rodeada de flores frescas y luces colgantes en un jardín interior.', '2026-06-13', 'Boda', 'vidrio', 'evento-08.jpg', 'activo'],
            ['bodaBaile', 'Boda con fiesta hasta el amanecer', 'Entrada de los novios con toda la familia, hora loca y baile en Hacienda El Portón.', '2026-08-08', 'Boda', 'hacienda', 'evento-15.jpg', 'activo'],
            ['bodaNoche', 'Boda de noche bajo las luces', 'Vals de los novios bajo un cielo de luces y fotografía artística.', '2026-09-26', 'Boda', 'lavanda', 'evento-10.jpg', 'activo'],
            ['bodaRustica', 'Boda campestre con la familia', 'Celebración tradicional boyacense con brindis y ruana para los invitados.', '2026-03-14', 'Boda', 'esperanza', 'evento-06.jpg', 'activo'],
            ['xvGala', 'XV años de gala dorada', 'Vals coreografiado, vestido dorado y entrada triunfal con los chambelanes.', '2026-05-30', 'Quinceañera', 'lavanda', 'evento-04.jpg', 'activo'],
            ['xvFiesta', 'XV años con hora loca', 'Fiesta con cotillón, brindis con las amigas y DJ hasta la medianoche.', '2026-07-18', 'Quinceañera', 'dorado', 'evento-11.jpg', 'activo'],
            ['cumple50', 'Cumpleaños 50 sorpresa', 'Cena sorpresa con brindis, discurso de los hijos y música en vivo.', '2026-06-27', 'Cumpleaños', 'mirador', 'evento-01.jpg', 'activo'],
            ['cumple30', 'Cumpleaños 30 en terraza', 'Celebración nocturna con coctelería, DJ y fotos con luces.', '2026-08-29', 'Cumpleaños', 'palmas', 'evento-05.jpg', 'activo'],
            ['corporativo', 'Cena de fin de año empresarial', 'Cena de reconocimiento a 180 colaboradores con presentación institucional.', '2026-11-07', 'Corporativo', 'sugamuxi', 'evento-02.jpg', 'activo'],
            ['brindisEmpresa', 'Lanzamiento con brindis y DJ', 'Lanzamiento de marca con barra de coctelería y DJ en vivo.', '2026-10-10', 'Corporativo', 'cristal', 'evento-07.jpg', 'activo'],
            ['despedida', 'Despedida de soltera con las amigas', 'Mañana de batas, brindis y desayuno sorpresa para la novia.', '2026-04-04', 'Otro', 'arrayanes', 'evento-03.jpg', 'activo'],
            ['aniversario', 'Aniversario de bodas de plata', 'Renovación de votos y cena íntima para 60 invitados a orillas del lago.', '2026-10-24', 'Otro', 'mirador', 'evento-13.jpg', 'activo'],
            ['babyShower', 'Baby shower de tarde', 'Mesa de dulces, torta temática y torre de copas para celebrar a la futura mamá.', '2026-09-05', 'Baby shower', 'arrayanes', '../ambientes/demo-lugar-12.jpg', 'inactivo'],
            ['bodaBrindis', 'Boda con brindis en la pista', 'Brindis de la novia con los invitados y fiesta en el Gran Salón Dorado.', '2026-10-17', 'Boda', 'dorado', 'evento-12.jpg', 'activo'],
        ];
        foreach ($eventos as [$k, $n, $d, $fecha, $tipo, $lugar, $img, $estado]) {
            $img = str_starts_with($img, '../') ? substr($img, 3) : 'eventos/demo-' . $img;
            $f = $this->co($fecha . ' 22:00');
            $this->eventosSociales[$k] = DB::table('evento_realizado')->insertGetId([
                'nombre_er' => $n,
                'descripcion_er' => $d,
                'fecha_er' => $fecha,
                'id_tipo_eves' => $this->tipos[$tipo] ?? $this->tipos['Otro'],
                'id_a' => $this->ambientes[$lugar],
                'id_u' => $this->adminSocial,
                'estado_er' => $estado,
                'imagen_er' => $this->url($img),
            ] + $this->sello($f), 'id_er');
        }
    }

    private function crearReservas(): void
    {
        $nombreLugar = DB::table('ambiente')->whereIn('id_a', $this->ambientes)->pluck('nombre_a', 'id_a')->all();

        // usuario, nombre, tipo, fecha, invitados, presupuesto, lugar, estado, etapa, total, servicios, reseña [estrellas, texto]
        $reservas = [
            ['laura', 'Matrimonio Laura y Andrés', 'Boda', '2026-04-18', 120, 28000000, 'palmas', 'completada', null, 26500000, ['Catering gourmet', 'Fotografía profesional', 'Decoración floral', 'Barra de coctelería'], [5, 'Todo salió como lo soñamos. El equipo estuvo pendiente de cada detalle y la terraza con las luces quedó hermosa.']],
            ['diana', 'XV de Sara', 'Quinceañera', '2026-05-30', 150, 18000000, 'lavanda', 'completada', null, 17200000, ['DJ y sonido profesional', 'Hora loca y animación', 'Mesa de postres', 'Torta personalizada'], [5, 'Mi hija quedó feliz. La coreografía del vals y la hora loca fueron lo mejor de la noche.']],
            ['jorge', 'Cumpleaños 50 de Marta', 'Cumpleaños', '2026-06-27', 60, 7000000, 'mirador', 'completada', null, 6800000, ['Buffet campestre', 'Fotografía profesional'], [4, 'Muy buena atención y la comida deliciosa. Solo el sonido tardó un poco en estar listo.']],
            ['natalia', 'Boda Natalia y Mauricio', 'Boda', '2026-06-13', 180, 32000000, 'vidrio', 'completada', null, 31000000, ['Buffet campestre', 'Decoración floral', 'Fotografía profesional', 'Video y dron'], [5, 'La Casa de Vidrio es un sueño y la coordinación fue impecable. Recomendadísimos.']],
            ['felipe', 'Fin de año Constructora Andina', 'Corporativo', '2026-11-07', 180, 25000000, 'sugamuxi', 'completada', null, 23900000, ['Pantallas y streaming', 'Catering gourmet', 'Coordinación del evento'], [5, 'Excelente producción. La pantalla, el catering y los tiempos se cumplieron al minuto.']],
            ['mariana', 'Baby shower de Mariana', 'Baby shower', '2026-09-05', 40, 4500000, 'arrayanes', 'completada', null, 4300000, ['Mesa de postres', 'Decoración floral'], [5, 'Muy bonito todo, la mesa de postres encantó a mis invitadas.']],
            ['camilo', 'Aniversario 25 años', 'Otro', '2026-10-24', 60, 9000000, 'mirador', 'completada', null, 8700000, ['Buffet campestre', 'Video y dron', 'Fotografía profesional'], [5, 'Renovamos votos frente al lago. Inolvidable, gracias por tanta dedicación.']],
            ['paola', 'Cumpleaños 30 Paola', 'Cumpleaños', '2026-08-29', 70, 6500000, 'palmas', 'completada', null, 6200000, ['Barra de coctelería', 'DJ y sonido profesional'], [4, 'La terraza es preciosa y el bartender un crack. Hubiera querido más opciones vegetarianas.']],
            ['valentina', 'XV años de Valentina', 'Quinceañera', '2026-07-18', 200, 22000000, 'dorado', 'completada', null, 21500000, ['Orquesta en vivo', 'Pantallas y streaming', 'Hora loca y animación'], [5, 'El salón dorado con la pantalla LED quedó espectacular. Mis amigas no paran de hablar de la fiesta.']],
            ['oscar', 'Boda Óscar y Daniela', 'Boda', '2026-08-08', 250, 38000000, 'hacienda', 'completada', null, 36400000, ['Buffet campestre', 'Orquesta en vivo', 'Transporte de invitados', 'Hora loca y animación'], [5, 'La hacienda es enorme y el transporte de invitados nos salvó la vida. Fiesta hasta el amanecer.']],
            ['luisa', 'Grado de Luisa', 'Otro', '2026-09-19', 80, 8000000, 'cristal', 'completada', null, 7600000, ['Catering gourmet', 'DJ y sonido profesional'], [4, 'Muy elegante el Salón Cristal. El servicio a la mesa fue rápido y amable.']],
            ['sebastian', 'Despedida de soltero Sebastián', 'Otro', '2026-04-04', 30, 3000000, 'arrayanes', 'completada', null, 2900000, ['Barra de coctelería'], [3, 'Buen lugar, pero el horario de salida era muy temprano para una despedida.']],
            ['carolina', 'Boda Carolina y Hernán', 'Boda', '2026-09-26', 160, 27000000, 'lavanda', 'completada', null, 26000000, ['Catering gourmet', 'Iluminación ambiental', 'Fotografía profesional', 'Decoración floral'], [5, 'Las luces del vals fueron mágicas. Gracias al equipo de SKYED por hacerlo tan fácil.']],
            ['andres', 'Lanzamiento Alfa Bikes', 'Corporativo', '2026-10-10', 120, 12000000, 'cristal', 'completada', null, 11800000, ['Barra de coctelería', 'DJ y sonido profesional', 'Pantallas y streaming'], [5, 'Lanzamiento con mucho estilo, los clientes quedaron felices.']],
            ['ricardo', 'Primera comunión de Samuel', 'Otro', '2026-05-16', 50, 4000000, 'esperanza', 'completada', null, 3900000, ['Buffet campestre', 'Mesa de postres'], [4, 'Un jardín muy tranquilo y lindo para la familia.']],
            ['sofia', 'Cumpleaños de Sofía', 'Cumpleaños', '2026-10-31', 45, 3500000, 'arrayanes', 'completada', null, 3400000, ['Mesa de postres', 'DJ y sonido profesional'], null],
            // Próximas (confirmadas, en planificación)
            ['laura', 'Bautizo de Emilia', 'Otro', '2026-12-12', 50, 4500000, 'mirador', 'confirmada', 'en_planificacion', 4300000, ['Buffet campestre', 'Torta personalizada'], null],
            ['felipe', 'Integración de navidad Andina', 'Corporativo', '2026-12-18', 200, 26000000, 'sugamuxi', 'confirmada', 'pendiente_confirmacion', 25500000, ['Catering gourmet', 'Orquesta en vivo', 'Pantallas y streaming'], null],
            ['natalia', 'Cumpleaños 40 Mauricio', 'Cumpleaños', '2027-01-23', 80, 9000000, 'palmas', 'confirmada', 'en_planificacion', 8800000, ['Barra de coctelería', 'DJ y sonido profesional', 'Catering gourmet'], null],
            ['mariana', 'Boda Mariana y Julián', 'Boda', '2027-03-06', 200, 35000000, 'hacienda', 'confirmada', 'en_planificacion', 34000000, ['Buffet campestre', 'Orquesta en vivo', 'Decoración floral', 'Fotografía profesional', 'Transporte de invitados'], null],
            // Pendientes
            ['valentina', 'Grado de bachiller', 'Otro', '2026-12-05', 60, 5000000, 'arrayanes', 'pendiente', 'contactando_usuario', 0, ['DJ y sonido profesional', 'Mesa de postres'], null],
            ['luisa', 'XV de Isabella', 'Quinceañera', '2027-02-20', 150, 16000000, 'lavanda', 'pendiente', 'en_revision', 0, ['Hora loca y animación', 'Torta personalizada'], null],
            ['sofia', 'Boda civil Sofía y Tomás', 'Boda', '2027-04-17', 70, 12000000, 'vidrio', 'pendiente', 'nueva', 0, ['Decoración floral', 'Fotografía profesional'], null],
            // Cancelada
            ['jorge', 'Reunión de exalumnos', 'Otro', '2026-11-21', 90, 6000000, 'cristal', 'cancelada', null, 0, ['Catering gourmet'], null],
        ];

        foreach ($reservas as $i => [$u, $nombre, $tipo, $fecha, $inv, $presu, $lugar, $estado, $etapa, $total, $servs, $resena]) {
            $idU = $this->usuarios[$u];
            $idA = $this->ambientes[$lugar];
            $fechaEvento = Carbon::parse($fecha, self::TZ);
            $creada = $fechaEvento->copy()->subDays($estado === 'pendiente' ? 0 : 60)->setTime(11, 20)->utc();
            if ($estado === 'pendiente') {
                $creada = $this->co('2026-11-' . str_pad((string) (8 + $i % 8), 2, '0', STR_PAD_LEFT) . ' 16:40');
            } elseif ($creada->copy()->addDays(5)->isFuture()) {
                // Reservas de eventos lejanos: pedidas en octubre (así su historial no queda en el futuro).
                $creada = $this->co('2026-10-' . str_pad((string) (5 + $i % 20), 2, '0', STR_PAD_LEFT) . ' 16:40');
            }
            $hora = ['15:00', '17:00', '19:00', '12:00'][$i % 4];
            $duracion = $tipo === 'Boda' ? 8 : ($tipo === 'Corporativo' ? 5 : 6);

            $idRese = DB::table('reserva')->insertGetId([
                'fecha_evento_rese' => $fecha,
                'invitados_rese' => $inv,
                'presupuesto_rese' => $presu,
                'ubicacion_rese' => $nombreLugar[$idA],
                'Observaciones_rese' => match ($tipo) {
                    'Boda' => 'Queremos ceremonia y recepción en el mismo lugar, menú con opción vegetariana.',
                    'Quinceañera' => 'Vals con coreografía y entrada especial de la quinceañera.',
                    'Corporativo' => 'Necesitamos pantalla para presentación y factura a nombre de la empresa.',
                    'Baby shower' => 'Decoración en tonos pastel y juegos para las invitadas.',
                    default => 'Celebración familiar, preferimos música crossover y comida típica.',
                },
                'total_rese' => $total,
                'estado_rese' => $estado,
                'creado_en_rese' => $creada,
                'id_u' => $idU,
                'id_er' => null,
                'nombre_rese' => $nombre,
                'id_tipo_eves' => $this->tipos[$tipo] ?? $this->tipos['Otro'],
                'hora_aprox_rese' => $hora,
                'duracion_horas_rese' => $duracion,
                'fecha_hasta_rese' => null,
            ] + $this->sello($creada), 'id_rese');

            $abierta = in_array($estado, ['pendiente', 'confirmada'], true);
            $etapaFinal = $etapa ?? ($estado === 'completada' ? 'en_ejecucion' : 'en_revision');
            $horaFin = Carbon::createFromFormat('H:i', $hora)->addHours($duracion)->format('H:i');
            DB::table('reserva_gestion')->insert([
                'id_rese' => $idRese,
                'etapa_gestion' => $etapaFinal,
                'id_responsable' => in_array($etapaFinal, ['nueva'], true) ? null : ($i % 3 === 0 ? $this->usuarios['daniel'] : $this->adminSocial),
                'fecha_propuesta' => $fecha,
                'fecha_confirmada' => in_array($estado, ['confirmada', 'completada'], true) ? $fecha : null,
                'hora_inicio' => in_array($estado, ['confirmada', 'completada'], true) ? $hora : null,
                'hora_fin' => in_array($estado, ['confirmada', 'completada'], true) ? $horaFin : null,
                'id_a' => $etapaFinal === 'nueva' ? null : $idA,
                'asistentes_final' => in_array($estado, ['confirmada', 'completada'], true) ? $inv : null,
                'distribucion_espacio' => in_array($estado, ['confirmada', 'completada'], true) ? 'Mesas redondas de 10 personas, pista al centro y mesa principal al fondo.' : null,
                'necesidades_especiales' => $i % 5 === 0 ? 'Rampa de acceso para invitada en silla de ruedas.' : null,
                'contactado' => $etapaFinal !== 'nueva',
                'ultimo_contacto_en' => $etapaFinal !== 'nueva' ? $creada->copy()->addDays(1) : null,
                'medio_ultimo_contacto' => $etapaFinal !== 'nueva' ? ['whatsapp', 'telefono', 'correo'][$i % 3] : null,
                'proximo_contacto_en' => $abierta ? $this->co('2026-11-' . (20 + $i % 8) . ' 10:00') : null,
                'compromisos' => $estado === 'confirmada' ? 'Enviar menú final y confirmar número de invitados 15 días antes.' : null,
            ] + $this->sello($creada));

            foreach ($servs as $j => $s) {
                DB::table('reserva_servicio')->insert([
                    'id_rese' => $idRese,
                    'id_s' => $this->servicios[$s],
                    'nombre_servicio' => $s,
                    'cantidad' => 1,
                    'origen' => $j === 0 && $estado !== 'pendiente' ? 'admin' : 'cliente',
                    'estado' => match ($estado) { 'pendiente' => 'pendiente', 'cancelada' => 'no_disponible', default => 'confirmado' },
                    'id_responsable' => $estado === 'pendiente' ? null : $this->adminSocial,
                    'observaciones' => null,
                ] + $this->sello($creada));
            }

            // Historial de la reserva
            $seg = fn (Carbon $f, string $tipo, string $com, ?string $ant = null, ?string $nue = null, bool $vis = true, ?int $admin = null) => DB::table('seguimiento_reserva')->insert([
                'id_rese' => $idRese, 'fecha_actualizacion' => $f, 'comentario' => $com, 'id_admin' => $admin,
                'tipo_evento' => $tipo, 'estado_anterior' => $ant, 'estado_nuevo' => $nue, 'visible_usuario' => $vis,
            ] + $this->sello($f));

            $seg($creada, 'creacion', 'Reserva creada');
            if ($etapaFinal !== 'nueva') {
                $seg($creada->copy()->addDay(), 'contacto', 'Te contactamos por ' . ['WhatsApp', 'teléfono', 'correo'][$i % 3] . ' para revisar los detalles de tu evento.', null, null, true, $this->adminSocial);
                $seg($creada->copy()->addDay(), 'cambio_etapa', 'La reserva pasó a revisión.', 'nueva', 'en_revision', false, $this->adminSocial);
            }
            if (in_array($estado, ['confirmada', 'completada'], true)) {
                $seg($creada->copy()->addDays(4), 'cambio_estado', 'Tu reserva fue confirmada. ¡Empezamos a planear tu evento!', 'pendiente', 'confirmada', true, $this->adminSocial);
                $seg($creada->copy()->addDays(4), 'servicio', 'Servicios confirmados: ' . implode(', ', $servs) . '.', null, null, true, $this->adminSocial);
                $this->notificar($idU, 'Reserva confirmada', 'Tu reserva «' . $nombre . '» fue confirmada para el ' . $fechaEvento->format('d/m/Y') . '.', 'reserva', $creada->copy()->addDays(4), $estado === 'completada');
            }
            if ($estado === 'completada') {
                $fin = $fechaEvento->copy()->addDay()->setTime(10, 0)->utc();
                $seg($fin, 'cambio_estado', 'Tu evento se realizó con éxito. ¡Gracias por confiar en nosotros!', 'confirmada', 'completada', true, $this->adminSocial);
            }
            if ($estado === 'cancelada') {
                $seg($creada->copy()->addDays(3), 'cambio_estado', 'Reserva cancelada a solicitud del cliente (cambio de fecha del encuentro).', 'pendiente', 'cancelada', true, $this->adminSocial);
            }

            if ($resena) {
                [$estrellas, $texto] = $resena;
                $fRes = $fechaEvento->copy()->addDays(3)->setTime(20, 15)->utc();
                DB::table('testimonio')->insert([
                    'id_u' => $idU,
                    'id_rese' => $idRese,
                    'calificacion_tes' => $estrellas,
                    'comentario_tes' => $texto,
                    'estado_tes' => 'publicado',
                ] + $this->sello($fRes));
            }
        }
    }

    // ------------------------------------------------------------------
    // PQR
    // ------------------------------------------------------------------

    private function crearPqrs(): void
    {
        // usuario, contexto, tipo, asunto, mensaje, estado final, días atrás, respuesta
        $pqrs = [
            ['andres', 'deportivo', 'peticion', 'Factura electrónica de la inscripción', 'Necesito la factura electrónica de mi inscripción al Gran Fondo Lago de Tota a nombre de mi empresa.', 'cerrado', 30, 'Te enviamos la factura electrónica al correo registrado. Cualquier ajuste nos avisas.'],
            ['jorge', 'deportivo', 'reclamo', 'Pago rechazado sin explicación', 'Pagué por Nequi el Gran Fondo y me aparece rechazado. Adjunté el comprobante correcto.', 'en_proceso', 6, null],
            ['laura', 'deportivo', 'sugerencia', 'Más puntos de hidratación', 'En la Travesía de la Trucha el segundo punto de hidratación quedó muy lejos. Sugiero uno más en el km 30.', 'resuelto', 40, 'Gracias por la sugerencia. Para la edición 2027 agregamos un punto adicional en el km 30.'],
            ['ricardo', 'deportivo', 'felicitacion', 'Excelente organización en Monguí', 'Felicitaciones por la travesía al Páramo de Ocetá, el guía y el carro escoba estuvieron atentos todo el tiempo.', 'cerrado', 80, 'Mil gracias por tus palabras, nos vemos en la próxima travesía.'],
            ['sebastian', 'deportivo', 'queja', 'Error en mi tiempo de la Clásica', 'Mi tiempo en la Clásica Sogamoso — Duitama aparece con 3 minutos de más, crucé la meta con Camilo.', 'en_revision', 4, null],
            ['valentina', 'deportivo', 'peticion', 'Cambio de talla del kit', '¿Puedo cambiar la talla de mi jersey del Gran Fondo de S a M?', 'abierto', 1, null],
            ['natalia', 'deportivo', 'peticion', 'Certificado de participación', 'Solicito el certificado de participación en la Vuelta al Lago de Tota para mi club.', 'cerrado', 120, 'Adjuntamos tu certificado en formato PDF a tu correo.'],
            ['oscar', 'deportivo', 'reclamo', 'No llegó el correo con el QR', 'Me inscribí a la Ruta Nocturna y no me llegó el correo con el código QR.', 'resuelto', 9, 'El QR está disponible en tu perfil, sección Mis inscripciones. También lo reenviamos a tu correo.'],
            ['laura', 'social', 'felicitacion', 'Gracias por nuestra boda', 'Queremos agradecer a todo el equipo por la boda en la Terraza Las Palmas. Fue perfecta.', 'cerrado', 200, '¡Gracias a ustedes por elegirnos! Les deseamos lo mejor en esta nueva etapa.'],
            ['jorge', 'social', 'queja', 'Demora en el montaje de sonido', 'En el cumpleaños de mi esposa el sonido empezó 40 minutos tarde.', 'cerrado', 140, 'Lamentamos la demora. Aplicamos un descuento del 10 % en el servicio de sonido y ajustamos el protocolo de montaje.'],
            ['diana', 'social', 'peticion', 'Fotos del XV de mi hija', '¿Cuándo estarán listas las fotos del XV de Sara?', 'resuelto', 150, 'La galería ya está disponible; te enviamos el enlace al correo registrado.'],
            ['felipe', 'social', 'peticion', 'Cotización para evento de enero', 'Quisiera cotizar una integración para 200 personas en enero.', 'en_proceso', 5, null],
            ['luisa', 'social', 'sugerencia', 'Opciones vegetarianas en el menú', 'Sería bueno tener un menú vegetariano completo, no solo la entrada.', 'en_revision', 3, null],
            ['sofia', 'social', 'peticion', 'Disponibilidad de la Casa de Vidrio', '¿La Casa de Vidrio está disponible el 17 de abril de 2027 para 70 personas?', 'abierto', 0, null],
            ['valentina', 'social', 'reclamo', 'Cobro doble del anticipo', 'En mi extracto aparecen dos cobros del anticipo de la reserva del grado.', 'abierto', 1, null],
            ['carolina', 'social', 'felicitacion', 'Las luces del vals', 'El montaje de luces del vals fue lo más bonito de la boda. ¡Gracias!', 'resuelto', 45, 'Qué alegría leerte. Le compartimos tu mensaje al equipo de iluminación.'],
            ['mariana', 'social', 'peticion', 'Cambio de fecha de la boda', 'Necesitamos mover la boda del 6 al 13 de marzo de 2027, ¿es posible?', 'en_proceso', 2, null],
            ['paola', 'social', 'queja', 'Pocas opciones vegetarianas', 'En mi cumpleaños solo había una opción vegetariana y se acabó rápido.', 'cerrado', 70, 'Gracias por contarnos. Ampliamos el menú vegetariano para todos los paquetes.'],
        ];

        $flujo = ['abierto', 'en_revision', 'en_proceso', 'resuelto', 'cerrado'];
        foreach ($pqrs as [$u, $ctx, $tipo, $asunto, $mensaje, $estadoFinal, $dias, $respuesta]) {
            $idU = $this->usuarios[$u];
            $admin = $ctx === 'deportivo' ? $this->adminDeportivo : $this->adminSocial;
            $creada = now()->subDays($dias)->subHours($this->aleatorio(1, 8));
            $idPqr = DB::table('pqr')->insertGetId([
                'tipo_pqr' => $tipo,
                'asunto_pqr' => $asunto,
                'mensaje_pqr' => $mensaje,
                'estado_pqr' => $estadoFinal,
                'respuesta_pqr' => in_array($estadoFinal, ['resuelto', 'cerrado'], true) ? $respuesta : null,
                'respondido_en_pqr' => in_array($estadoFinal, ['resuelto', 'cerrado'], true) ? $creada->copy()->addDays(2) : null,
                'creado_en_pqr' => $creada,
                'id_u' => $idU,
                'contexto_pqr' => $ctx,
            ] + $this->sello($creada), 'id_pqr');

            $s = fn (Carbon $f, ?int $adm, ?string $ant, string $nue, string $tipoEv, ?string $pub, ?string $nota = null) => DB::table('pqr_seguimientos')->insert([
                'id_pqr' => $idPqr, 'id_admin' => $adm, 'estado_anterior' => $ant, 'estado_nuevo' => $nue,
                'tipo_evento' => $tipoEv, 'comentario_publico' => $pub, 'nota_interna' => $nota,
            ] + $this->sello($f));

            $s($creada, null, null, 'abierto', 'radicacion', 'Tu solicitud fue radicada.');
            $hasta = array_search($estadoFinal, $flujo, true);
            $f = $creada->copy();
            for ($p = 1; $p <= $hasta; $p++) {
                $f = $f->copy()->addHours(min(20, max(2, $dias * 3)));
                if ($f->isFuture()) {
                    $f = now()->subMinutes(30 * (6 - $p));
                }
                $nuevo = $flujo[$p];
                if ($nuevo === 'resuelto') {
                    $s($f, $admin, 'en_proceso', 'resuelto', 'respuesta', $respuesta);
                } elseif ($nuevo === 'cerrado') {
                    $s($f, null, 'resuelto', 'cerrado', 'cerrada_cliente', 'El usuario confirmó que su solicitud quedó resuelta.');
                } else {
                    $s($f, $admin, $flujo[$p - 1], $nuevo, 'cambio_estado', $nuevo === 'en_revision' ? 'Estamos revisando tu solicitud.' : 'Tu caso está en proceso; te contaremos novedades pronto.', $nuevo === 'en_proceso' ? 'Validar con tesorería / coordinación.' : null);
                }
            }
            if ($estadoFinal === 'en_proceso' && $u === 'jorge') {
                $s(now()->subDays(2), null, 'en_proceso', 'en_proceso', 'respuesta_cliente', 'Adjunto de nuevo el número de aprobación de Nequi: M4829173.');
            }
            if (in_array($estadoFinal, ['resuelto', 'cerrado'], true)) {
                $this->notificar($idU, 'Respuesta a tu PQR', 'Respondimos tu solicitud «' . $asunto . '».', 'pqr', $creada->copy()->addDays(2), $estadoFinal === 'cerrado');
            }
        }
    }

    // ------------------------------------------------------------------
    // Notificaciones y copias
    // ------------------------------------------------------------------

    private function notificar(int $idU, string $titulo, string $mensaje, string $tipo, Carbon $fecha, bool $leida): void
    {
        if ($fecha->isFuture()) {
            $fecha = now()->subHours(2);
        }
        DB::table('notificaciones')->insert([
            'user_id' => $idU,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'tipo' => $tipo,
            'leida' => $leida,
            'leida_en' => $leida ? $fecha->copy()->addHours(3) : null,
        ] + $this->sello($fecha));
    }

    private function crearCopias(): void
    {
        $superadmin = User::find($this->superAdmin);
        $adminDep = User::find($this->adminDeportivo);
        $adminSoc = User::find($this->adminSocial);
        $todos = array_keys(CatalogoCopias::conjuntos());
        $deDeportivo = array_keys(CatalogoCopias::GRUPOS['deportivo']['conjuntos']);
        $deSocial = array_keys(CatalogoCopias::GRUPOS['social']['conjuntos']);

        try {
            GestorCopias::crear($deDeportivo, $adminDep, 'manual');
            GestorCopias::crear($deSocial, $adminSoc, 'manual');
            GestorCopias::crear($todos, $superadmin, 'manual');
        } catch (\Throwable $e) {
            $this->command?->warn('No se pudieron crear las copias de seguridad demo: ' . $e->getMessage());
        }
    }
}
