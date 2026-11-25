@extends('layouts.app')

@section('title', 'Gasto real · '.$orden->codigo_orden)
@section('page-kicker', 'Órdenes de operación')
@section('page-title', 'Estimado vs. gasto real')

@section('content')
    <a class="button button--ghost" href="{{ route('ordenes-operacion.show', $orden) }}">Volver a {{ $orden->codigo_orden }}</a>
    <section class="panel">
        <div class="panel__header">
          <div>
            <h1>Gasto real · {{ $orden->codigo_orden }}</h1>
            <p>Consulta en soles · {{ $reporte['generado_en'] }} · Orden principal y sus OS internas no anuladas.</p>
            <a class="button button--primary" href="{{ route('ordenes-operacion.gasto-real.excel', $orden) }}" data-file-download>Descargar Excel de gasto real</a>
            <p>La descarga consulta los registros nuevamente. Si se registran movimientos entre ambas consultas, los totales pueden cambiar.</p>
          </div>
        </div>
    </section>
    <div class="notice notice--warning notice--block">
        <div>
            @foreach ($reporte['avisos'] as $aviso)
                <p>{{ $aviso }}</p>
            @endforeach
            <p>N/D significa que no hay información suficiente, no cero. La diferencia es real menos estimado.</p>
            <p>El cierre guardado es individual e histórico; no se reemplaza por el consolidado de esta consulta.</p>
        </div>
    </div>
    @foreach ($secciones as $seccion)
        <details class="panel" @if ($loop->first) open @endif>
            <summary class="panel__header">{{ $seccion['titulo'] }}</summary>
            <div class="table-wrap table-wrap--cards">
                <table class="data-table data-table--cards" role="table">
                    <thead role="rowgroup">
                        <tr role="row">
                            @foreach ($seccion['columnas'] as $titulo)
                                <th scope="col" role="columnheader">{{ $titulo }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody role="rowgroup">
                        @forelse ($seccion['filas'] as $fila)
                            <tr role="row">
                                @foreach ($seccion['columnas'] as $clave => $titulo)
                                    @php($valor = $fila[$clave] ?? null)
                                    <td role="cell" data-label="{{ $titulo }}" @class(['table-card__wide' => $loop->first || $clave === 'producto'])>
                                        {{ is_int($valor) || is_float($valor) ? number_format($valor, 2) : ($valor ?? 'N/D') }}
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr role="row"><td role="cell" colspan="{{ count($seccion['columnas']) }}">Sin registros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </details>
    @endforeach
@endsection
