<?php

namespace App\Http\Controllers;

use App\Http\Resources\AyudaArticuloDetalleResource;
use App\Http\Resources\AyudaArticuloResource;
use App\Http\Resources\AyudaCategoriaResource;
use App\Models\AyudaArticulo;
use App\Models\AyudaCategoria;
use App\Models\AyudaValoracion;
use App\Models\User;
use App\Support\BuscadorAyuda;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Centro de Ayuda: lectura pública. Si llega un token se reconoce a la
 * persona (como hace SKAI) para mostrar solo lo que su rol puede ver.
 * Un borrador o desactivado responde 404: no se revela que existe.
 */
class AyudaController extends Controller
{
    private const MAX_FAQ = 8;
    private const MAX_DESTACADOS = 6;
    private const MAX_POPULARES = 6;
    private const MAX_RELACIONADOS = 4;
    private const CATEGORIA = 'categoria:id_ayc,nombre_ayc,slug_ayc';

    /** GET /ayuda — todo lo de la pantalla principal en una sola petición. */
    public function inicio(Request $request): JsonResponse
    {
        $usuario = $request->user('sanctum');
        $modulo = $this->moduloPedido($request);

        $categorias = $this->categoriasVisibles($usuario, $modulo);

        $visibles = fn () => AyudaArticulo::query()
            ->visiblesPara($usuario)
            ->deModulo($modulo)
            ->with(self::CATEGORIA)
            ->ordenados();

        return response()->json([
            'categorias' => AyudaCategoriaResource::collection($categorias)->resolve(),
            'faq' => AyudaArticuloResource::collection(
                $visibles()->where('faq_aya', true)->limit(self::MAX_FAQ)->get()
            )->resolve(),
            'destacados' => AyudaArticuloResource::collection(
                $visibles()->where('destacado_aya', true)->limit(self::MAX_DESTACADOS)->get()
            )->resolve(),
            // Solo artículos con al menos una visita real, el más visto primero.
            'populares' => AyudaArticuloResource::collection(
                $visibles()->reorder()
                    ->where('visitas_aya', '>', 0)
                    ->orderByDesc('visitas_aya')
                    ->orderBy('titulo_aya')
                    ->limit(self::MAX_POPULARES)
                    ->get()
            )->resolve(),
        ]);


    }

    /** GET /ayuda/categorias */
    public function categorias(Request $request): JsonResponse
    {
        $categorias = $this->categoriasVisibles(
            $request->user('sanctum'),
            $this->moduloPedido($request)
        );

        return response()->json([
            'categorias' => AyudaCategoriaResource::collection($categorias)->resolve(),
        ]);
    }

    /** GET /ayuda/categorias/{slug} — la categoría con sus artículos. */
    public function categoria(Request $request, string $slug): JsonResponse
    {
        $usuario = $request->user('sanctum');

        $categoria = AyudaCategoria::query()->activas()->where('slug_ayc', $slug)->first();

        $articulos = $categoria
            ? $categoria->articulos()->visiblesPara($usuario)->with(self::CATEGORIA)->ordenados()->get()
            : collect();

        // Sin artículos visibles para esta persona = como si no existiera.
        if (!$categoria || $articulos->isEmpty()) {
            return response()->json([
                'message' => 'Esta categoría no existe o todavía no tiene artículos disponibles.',
            ], 404);
        }

        return response()->json([
            'categoria' => (new AyudaCategoriaResource($categoria))->resolve(),
            'articulos' => AyudaArticuloResource::collection($articulos)->resolve(),
        ]);
    }

    /**
     * GET /ayuda/articulos — filtros opcionales:
     * ?modulo= ?categoria=(slug) ?contexto= ?faq=1 ?destacados=1
     * Lo usa la ayuda contextual ("?") de cada página.
     */
    public function articulos(Request $request): JsonResponse
    {
        $request->validate([
            'modulo' => 'nullable|in:deportivo,social',
            'categoria' => 'nullable|string|max:120',
            'contexto' => 'nullable|string|max:60',
            'faq' => 'nullable|boolean',
            'destacados' => 'nullable|boolean',
        ]);

        $articulos = AyudaArticulo::query()
            ->visiblesPara($request->user('sanctum'))
            ->deModulo($request->input('modulo'))
            ->with(self::CATEGORIA)
            ->when($request->filled('categoria'), fn ($q) => $q->whereHas(
                'categoria', fn ($c) => $c->where('slug_ayc', $request->input('categoria'))
            ))
            ->when($request->filled('contexto'), fn ($q) => $q->where('contexto_aya', $request->input('contexto')))
            ->when($request->boolean('faq'), fn ($q) => $q->where('faq_aya', true))
            ->when($request->boolean('destacados'), fn ($q) => $q->where('destacado_aya', true))
            ->ordenados()
            ->limit(100)
            ->get();

        return response()->json([
            'articulos' => AyudaArticuloResource::collection($articulos)->resolve(),
        ]);
    }

    /** GET /ayuda/articulos/{slug} — artículo completo + relacionados. */
    public function mostrar(Request $request, string $slug): JsonResponse
    {
        $usuario = $request->user('sanctum');

        $articulo = AyudaArticulo::query()
            ->visiblesPara($usuario)
            ->with('categoria')
            ->where('slug_aya', $slug)
            ->first();

        if (!$articulo) {
            return response()->json([
                'message' => 'Este artículo no existe o ya no está disponible.',
            ], 404);
        }

        $miValoracion = $usuario
            ? AyudaValoracion::query()
                ->where('id_aya', $articulo->id_aya)
                ->where('id_u', $usuario->getKey())
                ->first()
            : null;

        return response()->json([
            'articulo' => (new AyudaArticuloDetalleResource($articulo))->resolve(),
            'relacionados' => AyudaArticuloResource::collection(
                $this->relacionados($articulo, $usuario)
            )->resolve(),
            'mi_valoracion' => $miValoracion
                ? ['util' => $miValoracion->util_ayv, 'comentario' => $miValoracion->comentario_ayv]
                : null,
        ]);

    }

    /** GET /ayuda/buscar?q= */
    public function buscar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|min:2|max:100',
            'modulo' => 'nullable|in:deportivo,social',
        ]);

        $articulos = AyudaArticulo::query()
            ->visiblesPara($request->user('sanctum'))
            ->deModulo($validated['modulo'] ?? null)
            ->with(self::CATEGORIA)
            ->ordenados()
            ->get();

        $resultados = BuscadorAyuda::buscar($articulos, $validated['q']);

        return response()->json([
            'consulta' => $validated['q'],
            'total' => $resultados->count(),
            'resultados' => AyudaArticuloResource::collection($resultados)->resolve(),
        ]);
    }

        /**
     * POST /ayuda/articulos/{slug}/valoracion (requiere sesión)
     * Un voto por persona y artículo: si ya votó, se actualiza (no se duplica).
     */
    public function valorar(Request $request, string $slug): JsonResponse
    {
        $usuario = $request->user();

        $datos = $request->validate([
            'util' => 'required|boolean',
            'comentario' => 'nullable|string|max:500',
        ], [
            'util.required' => 'Indica si el artículo te fue útil.',
            'util.boolean' => 'La respuesta no es válida.',
            'comentario.max' => 'El comentario puede tener hasta 500 caracteres.',
        ]);

        $articulo = AyudaArticulo::query()
            ->visiblesPara($usuario)
            ->where('slug_aya', $slug)
            ->first();

        if (!$articulo) {
            return response()->json(['message' => 'Este artículo no existe o ya no está disponible.'], 404);
        }

        $util = (bool) $datos['util'];

        // El comentario solo tiene sentido cuando NO fue útil; se limpia de HTML.
        $comentario = $util
            ? null
            : (trim(strip_tags((string) ($datos['comentario'] ?? ''))) ?: null);

        $valoracion = AyudaValoracion::updateOrCreate(
            ['id_aya' => $articulo->id_aya, 'id_u' => $usuario->getKey()],
            ['util_ayv' => $util, 'comentario_ayv' => $comentario]
        );

        return response()->json([
            'message' => $util
                ? '¡Gracias por tu opinión!'
                : 'Gracias, revisaremos este artículo para mejorarlo.',
            'valoracion' => [
                'util' => $valoracion->util_ayv,
                'comentario' => $valoracion->comentario_ayv,
            ],
        ]);
    }

    /** POST /ayuda/articulos/{slug}/visita: suma 1 al contador (sin datos personales). */
    public function registrarVisita(Request $request, string $slug): JsonResponse
    {
        $id = AyudaArticulo::query()
            ->visiblesPara($request->user('sanctum'))
            ->where('slug_aya', $slug)
            ->value('id_aya');

        if (!$id) {
            return response()->json(['message' => 'Este artículo no existe o ya no está disponible.'], 404);
        }

        // DB::table (y no el modelo) para NO tocar updated_at: esa fecha es la
        // "Última actualización" que ve el usuario y no debe cambiar con cada visita.
        DB::table('ayuda_articulo')->where('id_aya', $id)->increment('visitas_aya');

        return response()->json(['ok' => true]);
    }

    
    // ------------------------------------------------------------------

    private function moduloPedido(Request $request): ?string
    {
        $request->validate(['modulo' => 'nullable|in:deportivo,social']);

        return $request->input('modulo') ?: null;
    }

    /**
     * Categorías activas con el número de artículos que ESTA persona puede
     * ver; se ocultan las que quedan en cero. Se filtra en PHP (no con
     * HAVING sobre el alias) porque PostgreSQL no permite alias en HAVING.
     */
    private function categoriasVisibles(?User $usuario, ?string $modulo): Collection
    {
        return AyudaCategoria::query()
            ->activas()
            ->deModulo($modulo)
            ->withCount(['articulos' => fn ($q) => $q->visiblesPara($usuario)->deModulo($modulo)])
            ->orderBy('orden_ayc')
            ->orderBy('nombre_ayc')
            ->get()
            ->filter(fn ($c) => $c->articulos_count > 0)
            ->values();
    }

    /** Misma categoría (+10) y palabras clave en común (+1 c/u). */
    private function relacionados(AyudaArticulo $actual, ?User $usuario): Collection
    {
        $mias = collect($actual->palabrasClave())
            ->map(fn ($k) => BuscadorAyuda::normalizar($k));

        return AyudaArticulo::query()
            ->visiblesPara($usuario)
            ->where('id_aya', '!=', $actual->id_aya)
            ->with(self::CATEGORIA)
            ->ordenados()
            ->get()
            ->map(function ($a) use ($actual, $mias) {
                $suyas = collect($a->palabrasClave())
                    ->map(fn ($k) => BuscadorAyuda::normalizar($k));

                $puntos = ((int) $a->id_ayc === (int) $actual->id_ayc ? 10 : 0)
                    + $mias->intersect($suyas)->count();

                return ['articulo' => $a, 'puntos' => $puntos];
            })
            ->filter(fn ($r) => $r['puntos'] > 0)
            ->sortByDesc('puntos')
            ->take(self::MAX_RELACIONADOS)
            ->pluck('articulo')
            ->values();
    }
}