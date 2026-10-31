<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>{{ $reporte->titulo() }}</title>
<style>
    @page { margin: 28px 28px 46px 28px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5px; color: #1f2937; }
    .cabecera { width: 100%; border-collapse: collapse; border-bottom: 3px solid {{ $colores['linea'] }}; margin-bottom: 10px; }
    .cabecera td { vertical-align: middle; padding: 0 0 8px 0; }
    .logo { width: 58px; }
    .marca { font-size: 20px; font-weight: bold; letter-spacing: 2px; color: {{ $colores['primario'] }}; }
    .modulo { font-size: 9px; color: {{ $colores['acento'] }}; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
    .titulo { font-size: 15px; font-weight: bold; color: {{ $colores['primario'] }}; text-align: right; }
    .meta { font-size: 8px; color: #6b7280; text-align: right; }
    .caja { background: {{ $colores['suave'] }}; border-left: 3px solid {{ $colores['acento'] }}; padding: 6px 9px; margin-bottom: 10px; }
    .caja b { color: {{ $colores['primario'] }}; }
    .caja .fila { margin: 1px 0; }
    table.datos { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.datos thead { display: table-header-group; }
    table.datos th { background: {{ $colores['primario'] }}; color: #ffffff; text-align: left; padding: 5px 5px; font-size: 8px; }
    table.datos td { padding: 4px 5px; border-bottom: 1px solid #e5e7eb; word-wrap: break-word; vertical-align: top; }
    table.datos tr { page-break-inside: avoid; }
    table.datos tr.alt td { background: #f9fafb; }
    table.datos th.der, table.datos td.der { text-align: right; }
    .vacio { text-align: center; padding: 18px; color: #6b7280; }
    .total { margin-top: 8px; font-weight: bold; color: {{ $colores['primario'] }}; text-align: right; }
</style>
</head>
<body>
    <table class="cabecera">
        <tr>
            @if ($logo)
                <td style="width: 66px;"><img class="logo" src="{{ $logo }}" alt="SKYED"></td>
            @endif
            <td>
                <div class="marca">SKYED</div>
                <div class="modulo">{{ $reporte->modulo() }}</div>
            </td>
            <td>
                <div class="titulo">{{ $reporte->titulo() }}</div>
                <div class="meta">Generado el {{ $generado }} · por {{ $generadoPor }}</div>
            </td>
        </tr>
    </table>

    <div class="caja">
        <div class="fila"><b>Filtros aplicados:</b>
            @if (count($filtros))
                @foreach ($filtros as $etiqueta => $valor)
                    {{ $etiqueta }}: {{ $valor }}@if (!$loop->last) · @endif
                @endforeach
            @else
                ninguno (todos los registros)
            @endif
        </div>
        <div class="fila"><b>Total de registros:</b> {{ number_format($total, 0, ',', '.') }}</div>
        @foreach ($resumen as $etiqueta => $valor)
            <div class="fila"><b>{{ $etiqueta }}:</b> {{ $valor }}</div>
        @endforeach
    </div>

    <table class="datos">
        <colgroup>
            @foreach ($columnas as $c)
                <col style="width: {{ round($c->peso / $sumaPesos * 100, 2) }}%">
            @endforeach
        </colgroup>
        <thead>
            <tr>
                @foreach ($columnas as $c)
                    <th class="{{ $c->numerica() ? 'der' : '' }}">{{ $c->titulo }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $i => $fila)
                <tr class="{{ $i % 2 ? 'alt' : '' }}">
                    @foreach ($fila as $j => $celda)
                        <td class="{{ $columnas[$j]->numerica() ? 'der' : '' }}">{{ $celda }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td class="vacio" colspan="{{ count($columnas) }}">No hay registros con los filtros seleccionados.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($total > 0)
        <div class="total">{{ number_format($total, 0, ',', '.') }} {{ $total === 1 ? 'registro' : 'registros' }}</div>
    @endif
</body>
</html>