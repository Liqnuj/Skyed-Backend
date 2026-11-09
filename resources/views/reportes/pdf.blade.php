<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>{{ $reporte->titulo() }}</title>
@php
    $t = $tema;
    $esDeportivo = $t['clave'] === 'deportivo';
    $mesesAbrev = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $fechaLarga = $ahora->format('d') . ' de ' . $mesesAbrev[(int) $ahora->format('n')] . ' de ' . $ahora->format('Y');
    $fechaHora = $fechaLarga . ', ' . $ahora->format('H:i');
    $titulo = $t['mayusculas'] ? mb_strtoupper($reporte->titulo()) : $reporte->titulo();
@endphp
<style>
    /* Hoja: el margen superior e inferior dejan sitio a la banda y al pie, que se repiten en cada página. */
    @page { margin: 96px 34px 60px 34px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.2px; color: #1f2430; }

    /* ---------- Banda superior (cada página) ---------- */
    #banda { position: fixed; top: -96px; left: -34px; width: {{ $anchoPagina }}px; height: 62px; background: {{ $t['primario'] }}; }
    #banda table { width: 100%; height: 62px; border-collapse: collapse; }
    #banda td { vertical-align: middle; padding: 0; height: 62px; }
    #filete { position: fixed; top: -34px; left: -34px; width: {{ $anchoPagina }}px; height: 4px; background: {{ $t['linea'] }}; }
    .insignia { width: 50px; height: 36px; background: #ffffff; border-radius: 9px; text-align: center; }
    .insignia img { width: 40px; margin-top: 5px; }
    .marca { font-size: 17px; font-weight: bold; letter-spacing: 4px; color: #ffffff; }
    .modulo-banda { font-size: 7.5px; font-weight: bold; letter-spacing: 2.5px; text-transform: uppercase; color: {{ $t['marca'] }}; margin-top: 2px; }
    .rep-banda { font-size: 8px; color: #ffffff; text-align: right; letter-spacing: .4px; }
    .barras div { height: 3px; margin: 3px 0 0 auto; }

    /* ---------- Pie (cada página) ---------- */
    #pie { position: fixed; bottom: -60px; left: -34px; width: {{ $anchoPagina }}px; height: 34px; background: {{ $t['suave'] }}; border-top: 2px solid {{ $t['linea'] }}; }

    /* ---------- Encabezado del reporte (primera página) ---------- */
    .eyebrow { font-size: 7.5px; font-weight: bold; letter-spacing: 2.5px; text-transform: uppercase; color: {{ $t['acento'] }}; }
    .titulo { font-family: '{{ $t['fuente_titulo'] }}', serif; font-size: {{ $esDeportivo ? '19' : '22' }}px; font-weight: bold; color: {{ $t['primario'] }}; margin-top: 3px; letter-spacing: {{ $esDeportivo ? '1.2' : '0' }}px; }
    .regla { width: 46px; height: 3px; background: {{ $t['linea'] }}; margin: 7px 0 7px 0; }
    .meta { font-size: 8px; color: #6b6878; }

    /* ---------- Tarjetas de resumen ---------- */
    table.tarjetas { width: 100%; border-collapse: separate; border-spacing: 0 0; margin: 12px 0 12px 0; }
    table.tarjetas td { vertical-align: top; }
    .tarjeta { background: {{ $t['suave'] }}; border-left: 4px solid {{ $t['linea'] }}; border-radius: 6px; padding: 8px 12px; }
    .tarjeta .n { font-family: '{{ $t['fuente_titulo'] }}', serif; font-size: 19px; font-weight: bold; color: {{ $t['primario'] }}; }
    .tarjeta .n.corto { font-size: 15px; }
    .tarjeta .l { font-size: 6.8px; font-weight: bold; letter-spacing: 1.4px; text-transform: uppercase; color: {{ $t['acento'] }}; margin-top: 2px; }
    .hueco { width: 8px; }
    .chip { display: inline-block; background: #ffffff; border: 1px solid {{ $t['trazo'] }}; border-radius: 9px; padding: 2px 8px; margin: 3px 4px 0 0; font-size: 7.6px; color: {{ $t['primario'] }}; }
    .chip b { color: {{ $t['acento'] }}; }
    .sin-filtros { font-size: 8px; color: #6b6878; margin-top: 3px; }

    /* ---------- Tabla de datos ---------- */
    table.datos { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.datos thead { display: table-header-group; }
    table.datos th { background: {{ $t['primario'] }}; color: #ffffff; text-align: left; padding: 7px 6px; font-size: 6.9px; letter-spacing: .9px; text-transform: uppercase; border-bottom: 3px solid {{ $t['linea'] }}; }
    table.datos td { padding: 5px 6px; border-bottom: 1px solid {{ $t['trazo'] }}; word-wrap: break-word; vertical-align: middle; }
    table.datos tr { page-break-inside: avoid; }
    table.datos tr.alt td { background: {{ $t['zebra'] }}; }
    table.datos th.der, table.datos td.der { text-align: right; }
    table.datos td.id { color: #8d8a99; }
    table.datos td.fuerte { font-weight: bold; color: {{ $t['primario'] }}; }

    .pildora { display: inline-block; padding: 1px 8px; border-radius: 9px; font-size: 7.2px; font-weight: bold; }
    .ok { background: #dcfce7; color: #166534; }
    .mal { background: #fee2e2; color: #991b1b; }
    .aviso { background: #fef3c7; color: #92400e; }
    .info { background: #dbeafe; color: #1e40af; }
    .neutro { background: #eceef3; color: #475569; }

    .vacio { text-align: center; padding: 26px; color: #6b6878; font-style: italic; }
    .cierre { margin-top: 10px; text-align: center; font-size: 7.4px; letter-spacing: 1.6px; text-transform: uppercase; color: {{ $t['acento'] }}; }
</style>
</head>
<body>
    <div id="banda">
        <table>
            <tr>
                <td style="width: 34px;"></td>
                @if ($logo)
                    <td style="width: 62px;"><div class="insignia"><img src="{{ $logo }}" alt="SKYED"></div></td>
                @endif
                <td>
                    <div class="marca">SKYED</div>
                    <div class="modulo-banda">{{ $reporte->modulo() }}</div>
                </td>
                <td style="text-align: right; width: 46%; vertical-align: middle;">
                    <div class="rep-banda" style="padding-top: {{ $esDeportivo ? '10' : '0' }}px;">{{ $reporte->titulo() }}</div>
                    @if ($esDeportivo)
                        <div class="barras">
                            <div style="width: 74px; background: {{ $t['linea'] }};"></div>
                            <div style="width: 50px; background: {{ $t['marca'] }};"></div>
                            <div style="width: 28px; background: #ffffff;"></div>
                        </div>
                    @endif
                </td>
                <td style="width: 34px;"></td>
            </tr>
        </table>
    </div>
    <div id="filete"></div>
    <div id="pie"></div>

    <div class="eyebrow">{{ $reporte->modulo() }} · Reporte</div>
    <div class="titulo">{{ $titulo }}</div>
    <div class="regla"></div>
    <div class="meta">Generado el {{ $fechaHora }} · por {{ $generadoPor }}</div>

    @php $numero = number_format($total, 0, ',', '.'); @endphp
    <table class="tarjetas">
        <tr>
            <td style="width: 19%;">
                <div class="tarjeta">
                    <div class="n">{{ $numero }}</div>
                    <div class="l">{{ $total === 1 ? 'Registro' : 'Registros' }}</div>
                </div>
            </td>
            @foreach ($resumen as $etiqueta => $valor)
                <td class="hueco"></td>
                <td style="width: 25%;">
                    <div class="tarjeta">
                        <div class="n corto">{{ $valor }}</div>
                        <div class="l">{{ $etiqueta }}</div>
                    </div>
                </td>
            @endforeach
            <td class="hueco"></td>
            <td>
                <div class="tarjeta">
                    <div class="l" style="margin: 0 0 2px 0;">Filtros aplicados</div>
                    @if (count($filtros))
                        @foreach ($filtros as $etiqueta => $valor)
                            <span class="chip"><b>{{ $etiqueta }}:</b> {{ $valor }}</span>
                        @endforeach
                    @else
                        <div class="sin-filtros">Ninguno · todos los registros</div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <table class="datos">
        <colgroup>
            @foreach ($columnas as $c)
                <col style="width: {{ round($c->peso / $sumaPesos * 100, 2) }}%">
            @endforeach
        </colgroup>
        <thead>
            <tr>
                @foreach ($columnas as $c)
                    <th class="{{ $c->numerica() ? 'der' : '' }}" style="width: {{ round($c->peso / $sumaPesos * 100, 2) }}%">{{ $c->titulo }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $i => $fila)
                <tr class="{{ $i % 2 ? 'alt' : '' }}">
                    @foreach ($fila as $j => $celda)
                        @php
                            $col = $columnas[$j];
                            $primeraDeTexto = $columnas[0]->clave === 'id' ? $j === 1 : $j === 0;
                            $clases = trim(
                                ($col->numerica() ? 'der ' : '')
                                . ($col->clave === 'id' ? 'id ' : '')
                                . ($primeraDeTexto ? 'fuerte' : '')
                            );
                        @endphp
                        <td class="{{ $clases }}">
                            @if (isset($tonos[$i][$j]) && $celda !== '' && $celda !== '—')
                                <span class="pildora {{ $tonos[$i][$j] }}">{{ $celda }}</span>
                            @else
                                {{ $celda }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td class="vacio" colspan="{{ count($columnas) }}">No hay registros con los filtros seleccionados.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($total > 0)
        <div class="cierre">— Fin del reporte · {{ $numero }} {{ $total === 1 ? 'registro' : 'registros' }} —</div>
    @endif
</body>
</html>