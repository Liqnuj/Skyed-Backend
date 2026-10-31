<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAyudaArticuloRequest;
use App\Http\Requests\StoreAyudaCategoriaRequest;
use App\Http\Requests\UpdateAyudaArticuloRequest;
use App\Http\Requests\UpdateAyudaCategoriaRequest;
use App\Http\Resources\AyudaArticuloAdminResource;
use App\Http\Resources\AyudaCategoriaAdminResource;
use App\Models\AyudaArticulo;
use App\Models\AyudaCategoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Administración del contenido del Centro de Ayuda.
 * Las rutas exigen ser admin; aquí además se valida el MÓDULO del contenido:
 * cada admin gestiona el suyo y lo "general" solo el super administrador
 * (ver User::modulosGestionablesAyuda).
 */
class AyudaAdminController extends Controller
{
    private const CATEGORIA = 'categoria:id_ayc,nombre_ayc,slug_ayc';

    // ===================== Opciones para formularios =====================

    /** GET /ayuda/admin/opciones */
    public function opciones(Request $request): JsonResponse
    {
        $gestionables = $request->user()->modulosGestionablesAyuda();

        $categorias = AyudaCategoria::query()
            ->whereIn('modulo_ayc', array_unique(array_merge(['general'], $gestionables)))
            ->orderBy('orden_ayc')
            ->orderBy('nombre_ayc')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id_ayc,
                'nombre' => $c->nombre_ayc,
                'modulo' => $c->modulo_ayc,
                'activa' => (bool) $c->activa_ayc,
                'gestionable' => in_array($c->modulo_ayc, $gestionables, true),
            ])
            ->values();

        return response()->json([
            'estados' => AyudaArticulo::ESTADOS,
            'audiencias' => AyudaArticulo::AUDIENCIAS,
            'modulos' => $gestionables,
            'categorias' => $categorias,
        ]);
    }

    // =========================== Artículos ===============================

    /** GET /ayuda/admin/articulos (?estado= ?modulo= ?categoria_id= ?q=) */
    public function articulos(Request $request): JsonResponse
    {
        $request->validate([
            'estado' => ['nullable', Rule::in(AyudaArticulo::ESTADOS)],
            'modulo' => ['nullable', Rule::in(AyudaArticulo::MODULOS)],
            'categoria_id' => 'nullable|integer',
            'q' => 'nullable|string|max:100',
        ]);

        $termino = mb_strtolower(trim((string) $request->input('q', '')));

        $articulos = AyudaArticulo::query()
            ->whereIn('modulo_aya', $request->user()->modulosGestionablesAyuda())
            ->with(self::CATEGORIA)
            ->withCount($this->conteosValoracion())
            ->when($request->filled('estado'), fn ($q) => $q->where('estado_aya', $request->input('estado')))
            ->when($request->filled('modulo'), fn ($q) => $q->where('modulo_aya', $request->input('modulo')))
            ->when($request->filled('categoria_id'), fn ($q) => $q->where('id_ayc', (int) $request->input('categoria_id')))
            ->when($termino !== '', fn ($q) => $q->where(
                fn ($w) => $w->whereRaw('LOWER(titulo_aya) LIKE ?', ["%{$termino}%"])
                    ->orWhereRaw('LOWER(slug_aya) LIKE ?', ["%{$termino}%"])
            ))
            ->orderBy('orden_aya')
            ->orderBy('titulo_aya')
            ->limit(300)
            ->get();

        return response()->json([
            'articulos' => AyudaArticuloAdminResource::collection($articulos)->resolve(),
        ]);
    }

    /** GET /ayuda/admin/articulos/{id}: detalle + comentarios de "No me fue útil". */
    public function verArticulo(Request $request, int $id): JsonResponse
    {
        $articulo = AyudaArticulo::query()
            ->with(self::CATEGORIA)
            ->withCount($this->conteosValoracion())
            ->find($id);

        abort_if(!$articulo, 404, 'El artículo no existe.');
        $this->autorizar($request, $articulo->modulo_aya);

        // Solo el texto y la fecha: nunca quién lo escribió.
        $comentarios = $articulo->valoraciones()
            ->where('util_ayv', false)
            ->whereNotNull('comentario_ayv')
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get(['comentario_ayv', 'updated_at'])
            ->map(fn ($v) => [
                'comentario' => $v->comentario_ayv,
                'fecha' => $v->updated_at?->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'articulo' => (new AyudaArticuloAdminResource($articulo))->resolve(),
            'comentarios' => $comentarios,
        ]);
    }

    /** POST /ayuda/admin/articulos */
    public function guardarArticulo(StoreAyudaArticuloRequest $request): JsonResponse
    {
        $d = $request->validated();

        $this->autorizar($request, $d['modulo']);
        $this->validarCategoria(AyudaCategoria::findOrFail($d['categoria_id']), $d['modulo']);

        $articulo = AyudaArticulo::create(array_merge($this->columnasArticulo($d), [
            'slug_aya' => $this->slugUnico(
                $d['slug'] ?? $d['titulo'],
                fn ($s) => AyudaArticulo::where('slug_aya', $s)->exists()
            ),
            'id_autor' => $request->user()->getKey(),
        ]));

        return response()->json([
            'message' => 'Artículo creado.',
            'articulo' => (new AyudaArticuloAdminResource($articulo->refresh()->load(self::CATEGORIA)))->resolve(),
        ], 201);
    }

    /**
     * PUT|PATCH /ayuda/admin/articulos/{id}: parcial.
     * Publicar / desactivar = enviar {"estado": "publicado"|"desactivado"}.
     * Destacar = {"destacado": true}.
     */
    public function actualizarArticulo(UpdateAyudaArticuloRequest $request, int $id): JsonResponse
    {
        $articulo = AyudaArticulo::find($id);
        abort_if(!$articulo, 404, 'El artículo no existe.');

        $d = $request->validated();

        // Debe gestionar el módulo actual Y el nuevo (si lo cambia).
        $this->autorizar($request, $articulo->modulo_aya);
        $modulo = $d['modulo'] ?? $articulo->modulo_aya;
        $this->autorizar($request, $modulo);

        if (array_key_exists('categoria_id', $d) || array_key_exists('modulo', $d)) {
            $this->validarCategoria(
                AyudaCategoria::findOrFail($d['categoria_id'] ?? $articulo->id_ayc),
                $modulo
            );
        }

        $columnas = $this->columnasArticulo($d);
        if (!empty($d['slug'])) {
            $columnas['slug_aya'] = Str::slug($d['slug']);
        }

        $articulo->fill($columnas)->save();

        return response()->json([
            'message' => 'Artículo actualizado.',
            'articulo' => (new AyudaArticuloAdminResource($articulo->refresh()->load(self::CATEGORIA)))->resolve(),
        ]);
    }

    /** DELETE /ayuda/admin/articulos/{id}: solo borradores o desactivados. */
    public function eliminarArticulo(Request $request, int $id): JsonResponse
    {
        $articulo = AyudaArticulo::find($id);
        abort_if(!$articulo, 404, 'El artículo no existe.');
        $this->autorizar($request, $articulo->modulo_aya);

        if ($articulo->estado_aya === AyudaArticulo::PUBLICADO) {
            return response()->json([
                'message' => 'Un artículo publicado no se puede eliminar: desactívalo primero.',
            ], 422);
        }

        DB::transaction(function () use ($articulo) {
            $articulo->valoraciones()->delete();
            $articulo->delete();
        });

        return response()->json(['message' => 'Artículo eliminado.']);
    }

    /** POST /ayuda/admin/articulos/orden  {"ids":[3,1,2]}: el primero queda con orden 1. */
    public function ordenarArticulos(Request $request): JsonResponse
    {
        $d = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'distinct', 'exists:ayuda_articulo,id_aya'],
        ]);

        foreach (AyudaArticulo::whereIn('id_aya', $d['ids'])->get(['id_aya', 'modulo_aya']) as $a) {
            $this->autorizar($request, $a->modulo_aya);
        }

        // DB::table: reordenar no debe cambiar la "Última actualización".
        DB::transaction(function () use ($d) {
            foreach ($d['ids'] as $posicion => $id) {
                DB::table('ayuda_articulo')->where('id_aya', $id)->update(['orden_aya' => $posicion + 1]);
            }
        });

        return response()->json(['message' => 'Orden actualizado.']);
    }

    // =========================== Categorías ==============================

    /** GET /ayuda/admin/categorias */
    public function categorias(Request $request): JsonResponse
    {
        $categorias = AyudaCategoria::query()
            ->whereIn('modulo_ayc', $request->user()->modulosGestionablesAyuda())
            ->withCount('articulos')
            ->orderBy('orden_ayc')
            ->orderBy('nombre_ayc')
            ->get();

        return response()->json([
            'categorias' => AyudaCategoriaAdminResource::collection($categorias)->resolve(),
        ]);
    }

    /** POST /ayuda/admin/categorias */
    public function guardarCategoria(StoreAyudaCategoriaRequest $request): JsonResponse
    {
        $d = $request->validated();
        $this->autorizar($request, $d['modulo']);

        $categoria = AyudaCategoria::create([
            'nombre_ayc' => $d['nombre'],
            'slug_ayc' => $this->slugUnico(
                $d['slug'] ?? $d['nombre'],
                fn ($s) => AyudaCategoria::where('slug_ayc', $s)->exists(),
                100
            ),
            'descripcion_ayc' => $d['descripcion'] ?? null,
            'icono_ayc' => $d['icono'] ?? 'ti-help-circle',
            'modulo_ayc' => $d['modulo'],
            'orden_ayc' => $d['orden'] ?? 0,
            'activa_ayc' => $d['activa'] ?? true,
        ]);

        return response()->json([
            'message' => 'Categoría creada.',
            'categoria' => (new AyudaCategoriaAdminResource($categoria->refresh()))->resolve(),
        ], 201);
    }

    /**
     * PUT|PATCH /ayuda/admin/categorias/{id}: parcial.
     * Ojo: desactivar una categoría oculta TODOS sus artículos al público.
     */
    public function actualizarCategoria(UpdateAyudaCategoriaRequest $request, int $id): JsonResponse
    {
        $categoria = AyudaCategoria::find($id);
        abort_if(!$categoria, 404, 'La categoría no existe.');

        $this->autorizar($request, $categoria->modulo_ayc);
        $d = $request->validated();

        if (isset($d['modulo']) && $d['modulo'] !== $categoria->modulo_ayc) {
            $this->autorizar($request, $d['modulo']);

            if ($categoria->articulos()->exists()) {
                throw ValidationException::withMessages([
                    'modulo' => ['No puedes cambiar el módulo de una categoría que ya tiene artículos.'],
                ]);
            }
        }

        $mapa = [
            'nombre' => 'nombre_ayc', 'descripcion' => 'descripcion_ayc', 'icono' => 'icono_ayc',
            'modulo' => 'modulo_ayc', 'orden' => 'orden_ayc', 'activa' => 'activa_ayc',
        ];
        $columnas = [];
        foreach ($mapa as $campo => $columna) {
            if (array_key_exists($campo, $d)) {
                $columnas[$columna] = $d[$campo];
            }
        }
        if (!empty($d['slug'])) {
            $columnas['slug_ayc'] = Str::slug($d['slug']);
        }

        $categoria->fill($columnas)->save();

        return response()->json([
            'message' => 'Categoría actualizada.',
            'categoria' => (new AyudaCategoriaAdminResource($categoria->refresh()->loadCount('articulos')))->resolve(),
        ]);
    }

    /** DELETE /ayuda/admin/categorias/{id}: solo si no tiene artículos. */
    public function eliminarCategoria(Request $request, int $id): JsonResponse
    {
        $categoria = AyudaCategoria::find($id);
        abort_if(!$categoria, 404, 'La categoría no existe.');
        $this->autorizar($request, $categoria->modulo_ayc);

        $total = $categoria->articulos()->count();
        if ($total > 0) {
            return response()->json([
                'message' => "La categoría tiene {$total} artículo(s). Muévelos a otra categoría o desactívala en lugar de eliminarla.",
            ], 422);
        }

        $categoria->delete();

        return response()->json(['message' => 'Categoría eliminada.']);
    }

    /** POST /ayuda/admin/categorias/orden  {"ids":[2,1,3]} */
    public function ordenarCategorias(Request $request): JsonResponse
    {
        $d = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct', 'exists:ayuda_categoria,id_ayc'],
        ]);

        foreach (AyudaCategoria::whereIn('id_ayc', $d['ids'])->get(['id_ayc', 'modulo_ayc']) as $c) {
            $this->autorizar($request, $c->modulo_ayc);
        }

        DB::transaction(function () use ($d) {
            foreach ($d['ids'] as $posicion => $id) {
                DB::table('ayuda_categoria')->where('id_ayc', $id)->update(['orden_ayc' => $posicion + 1]);
            }
        });

        return response()->json(['message' => 'Orden actualizado.']);
    }

    // ============================ Ayudantes ==============================

    /** 403 si esta persona no gestiona contenido de ese módulo. */
    private function autorizar(Request $request, string $modulo): void
    {
        abort_unless(
            in_array($modulo, $request->user()->modulosGestionablesAyuda(), true),
            403,
            'No tienes permisos para gestionar contenido de este módulo.'
        );
    }

    /** La categoría debe ser general o del mismo módulo que el artículo. */
    private function validarCategoria(AyudaCategoria $categoria, string $modulo): void
    {
        if ($categoria->modulo_ayc !== 'general' && $categoria->modulo_ayc !== $modulo) {
            throw ValidationException::withMessages([
                'categoria_id' => ['La categoría elegida no pertenece al módulo del artículo.'],
            ]);
        }
    }

    /** Nombres de la API → columnas, solo de los campos que llegaron. */
    private function columnasArticulo(array $d): array
    {
        $mapa = [
            'categoria_id' => 'id_ayc', 'titulo' => 'titulo_aya', 'resumen' => 'resumen_aya',
            'contenido' => 'contenido_aya', 'palabras_clave' => 'palabras_clave_aya',
            'modulo' => 'modulo_aya', 'audiencia' => 'audiencia_aya', 'estado' => 'estado_aya',
            'destacado' => 'destacado_aya', 'faq' => 'faq_aya', 'orden' => 'orden_aya',
            'contexto' => 'contexto_aya',
        ];

        $columnas = [];
        foreach ($mapa as $campo => $columna) {
            if (array_key_exists($campo, $d)) {
                $columnas[$columna] = $d[$campo];
            }
        }

        return $columnas;
    }

    /** Dirección legible y única: "como-cancelo-un-pedido", "...-2", "...-3". */
    private function slugUnico(string $texto, callable $existe, int $max = 150): string
    {
        $base = Str::limit(Str::slug($texto), $max, '') ?: 'sin-titulo';
        $slug = $base;
        $n = 2;

        while ($existe($slug)) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }

    private function conteosValoracion(): array
    {
        return [
            'valoraciones as utiles_count' => fn ($q) => $q->where('util_ayv', true),
            'valoraciones as no_utiles_count' => fn ($q) => $q->where('util_ayv', false),
        ];
    }
}