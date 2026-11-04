<?php

namespace App\Http\Controllers;

use App\Models\Asistente;
use App\Models\Role;
use App\Models\User;
use App\Support\ReglasUsuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AsistenteController extends Controller
{
    private const MAX_ASISTENTES = 10;

    private const PERMISOS = [
        'social' => ['inicio', 'eventos', 'reservas', 'usuarios', 'pqr', 'lugares', 'servicios'],
        'deportivo' => ['inicio', 'eventos', 'inscripciones', 'acceso', 'resultados', 'kits', 'usuarios'],
    ];

    public function index(Request $request): JsonResponse
    {
        $modulo = $this->moduloValidado($request);
        $admin = $this->administradorAutorizado($request, $modulo);

        $items = Asistente::query()
            ->with('usuario')
            ->where('id_admin', $admin->id_u)
            ->where('modulo', $modulo)
            ->orderBy('id_asistente')
            ->get()
            ->map(fn (Asistente $asistente) => $this->formatear($asistente));

        return response()->json(['data' => $items, 'limite' => self::MAX_ASISTENTES]);
    }

    public function store(Request $request): JsonResponse
    {
        $modulo = $this->moduloValidado($request);
        $admin = $this->administradorAutorizado($request, $modulo);
        $datos = $this->validarDatos($request, $modulo);

        if (Asistente::where('id_admin', $admin->id_u)->where('modulo', $modulo)->count() >= self::MAX_ASISTENTES) {
            throw ValidationException::withMessages([
                'asistente' => ['Ya alcanzaste el límite de ' . self::MAX_ASISTENTES . ' asistentes.'],
            ]);
        }

        $asistente = DB::transaction(function () use ($admin, $modulo, $datos) {
            $usuario = User::create([
                'tipo_documento_u' => $datos['tipo_documento_u'],
                'documento_u' => $datos['documento_u'],
                'nombre_u' => $datos['nombre_u'],
                'apellido_u' => $datos['apellido_u'],
                'rh_u' => 'N/A',
                'telefono_u' => $datos['telefono_u'],
                'correo_u' => $datos['correo_u'],
                'fecha_nacimiento_u' => $datos['fecha_nacimiento_u'],
                'contrasena_u' => Hash::make($datos['contrasena_u']),
                'estado_u' => 'activo',
            ]);

            $rol = Role::firstOrCreate(['nombre_rol' => 'asistente']);
            $usuario->roles()->attach($rol->id_rol, ['contexto' => $modulo]);

            return Asistente::create([
                'id_admin' => $admin->id_u,
                'id_usuario' => $usuario->id_u,
                'modulo' => $modulo,
                'permisos' => $datos['permisos'],
                'activo' => true,
            ])->load('usuario');
        });

        return response()->json(['data' => $this->formatear($asistente)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $modulo = $this->moduloValidado($request);
        $admin = $this->administradorAutorizado($request, $modulo);
        $asistente = $this->buscarAsistente($admin, $modulo, $id);
        $datos = $this->validarDatos($request, $modulo, $asistente);

        DB::transaction(function () use ($asistente, $datos) {
            $asistente->usuario->update([
                'nombre_u' => $datos['nombre_u'],
                'apellido_u' => $datos['apellido_u'],
                'correo_u' => $datos['correo_u'],
                'telefono_u' => $datos['telefono_u'],
            ]);
            $asistente->update(['permisos' => $datos['permisos']]);
        });

        return response()->json(['data' => $this->formatear($asistente->fresh()->load('usuario'))]);
    }

    public function cambiarEstado(Request $request, int $id): JsonResponse
    {
        $modulo = $this->moduloValidado($request);
        $admin = $this->administradorAutorizado($request, $modulo);
        $asistente = $this->buscarAsistente($admin, $modulo, $id);
        $datos = $request->validate(['activo' => ['required', 'boolean']]);

        $asistente->update(['activo' => $datos['activo']]);

        if (!$datos['activo']) {
            $asistente->usuario->tokens()->delete();
        }

        return response()->json(['data' => $this->formatear($asistente->fresh()->load('usuario'))]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $modulo = $this->moduloValidado($request);
        $admin = $this->administradorAutorizado($request, $modulo);
        $asistente = $this->buscarAsistente($admin, $modulo, $id);
        $usuario = $asistente->usuario;

        DB::transaction(function () use ($asistente, $usuario, $modulo) {
            $rol = Role::where('nombre_rol', 'asistente')->first();
            if ($rol) {
                $usuario->roles()->wherePivot('contexto', $modulo)->detach($rol->id_rol);
            }
            $usuario->tokens()->delete();
            $asistente->delete();
        });

        return response()->json(['message' => 'Asistente dado de baja correctamente.']);
    }

    public function misPermisos(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $modulo = $request->query('modulo');
        if ($modulo !== null && !in_array($modulo, ['social', 'deportivo'], true)) {
            throw ValidationException::withMessages(['modulo' => ['El módulo no es válido.']]);
        }

        if ($modulo === null) {
            $asistentes = Asistente::where('id_usuario', $usuario->id_u)
                ->where('activo', true)
                ->get();
            if ($asistentes->count() > 1) {
                throw ValidationException::withMessages([
                    'modulo' => ['Indica el módulo para consultar tus permisos.'],
                ]);
            }
            $asistente = $asistentes->first();
            if (!$asistente) {
                return response()->json([
                    'es_admin' => false,
                    'es_asistente' => false,
                    'modulo' => null,
                    'permisos' => [],
                ]);
            }
            $modulo = $asistente->modulo;
        }

        $esAdmin = $usuario->hasRoleInContext($modulo === 'social' ? 'adminSocial' : 'adminDeportivo', $modulo);

        if ($esAdmin) {
            return response()->json([
                'es_admin' => true,
                'es_asistente' => false,
                'modulo' => $modulo,
                'permisos' => array_fill_keys(self::PERMISOS[$modulo], 'eliminar'),
            ]);
        }

        $asistente = Asistente::query()
            ->where('id_usuario', $usuario->id_u)
            ->where('modulo', $modulo)
            ->where('activo', true)
            ->first();

        return response()->json([
            'es_admin' => false,
            'es_asistente' => $asistente !== null,
            'modulo' => $asistente ? $modulo : null,
            'permisos' => $asistente?->permisos ?? [],
        ]);
    }

    private function moduloValidado(Request $request): string
    {
        $datos = $request->validate([
            'modulo' => ['required', Rule::in(['social', 'deportivo'])],
        ]);

        return $datos['modulo'];
    }

    private function administradorAutorizado(Request $request, string $modulo): User
    {
        $admin = $request->user();
        $rol = $modulo === 'social' ? 'adminSocial' : 'adminDeportivo';

        if (!$admin->hasRoleInContext($rol, $modulo)) {
            abort(403, 'No tienes permisos para administrar asistentes en este módulo.');
        }

        return $admin;
    }

    private function buscarAsistente(User $admin, string $modulo, int $id): Asistente
    {
        return Asistente::with('usuario')
            ->where('id_admin', $admin->id_u)
            ->where('modulo', $modulo)
            ->whereKey($id)
            ->firstOrFail();
    }

    private function validarDatos(Request $request, string $modulo, ?Asistente $asistente = null): array
    {
        $usuario = $asistente?->usuario;
        $request->merge(ReglasUsuario::limpiar($request->only([
            'nombre_u', 'apellido_u', 'telefono_u', 'documento_u', 'correo_u',
        ])));

        $reglas = [
            'nombre_u' => ['required', 'string', 'max:50', 'regex:' . ReglasUsuario::NOMBRE],
            'apellido_u' => ['required', 'string', 'max:50', 'regex:' . ReglasUsuario::NOMBRE],
            'telefono_u' => [
                'required', 'digits_between:7,15',
                Rule::unique('usuario', 'telefono_u')->ignore($usuario?->id_u, 'id_u'),
            ],
            'correo_u' => [
                'required', 'email', 'max:80',
                function (string $attribute, mixed $value, \Closure $fail) use ($usuario) {
                    $existe = User::whereRaw('LOWER(correo_u) = ?', [ReglasUsuario::normalizarCorreo((string) $value)])
                        ->when($usuario, fn ($query) => $query->where('id_u', '!=', $usuario->id_u))
                        ->exists();
                    if ($existe) {
                        $fail('Este correo ya está registrado en el sistema.');
                    }
                },
            ],
            'permisos' => ['required', 'array:' . implode(',', self::PERMISOS[$modulo])],
            'permisos.*' => ['required', Rule::in(['ver', 'editar', 'eliminar'])],
        ];

        if (!$asistente) {
            $reglas += [
                'tipo_documento_u' => ['required', 'string', 'in:CC,TI,CE,PA', 'max:30'],
                'documento_u' => [
                    'required', 'integer', 'digits_between:5,12',
                    Rule::unique('usuario', 'documento_u'),
                ],
                'fecha_nacimiento_u' => [
                    'required', 'date', 'after:1900-01-01',
                    'before_or_equal:' . ReglasUsuario::fechaMaximaNacimiento(),
                ],
                'contrasena_u' => ReglasUsuario::contrasena(),
            ];
        }

        $datos = $request->validate($reglas, ReglasUsuario::mensajes());

        $permisos = array_intersect_key($datos['permisos'], array_flip(self::PERMISOS[$modulo]));
        $permisos = array_filter($permisos, fn ($nivel) => in_array($nivel, ['ver', 'editar', 'eliminar'], true));
        if ((!$asistente || $asistente->activo) && $permisos === []) {
            throw ValidationException::withMessages([
                'permisos' => ['Activa al menos una sección para este asistente.'],
            ]);
        }

        $datos['correo_u'] = ReglasUsuario::normalizarCorreo($datos['correo_u']);
        $datos['permisos'] = $permisos;

        return $datos;
    }

    private function formatear(Asistente $asistente): array
    {
        $usuario = $asistente->usuario;

        return [
            'id' => $asistente->id_asistente,
            'nombre' => $usuario->nombre_u,
            'apellido' => $usuario->apellido_u,
            'correo' => $usuario->correo_u,
            'telefono' => $usuario->telefono_u,
            'foto_url' => $usuario->foto_u ? asset('storage/' . $usuario->foto_u) : null,
            'activo' => $asistente->activo,
            'modulo' => $asistente->modulo,
            'permisos' => $asistente->permisos ?? [],
        ];
    }
}
