@extends('layouts.app')

@section('title', 'Respaldo en Google Drive')
@section('page-kicker', 'Administración')
@section('page-title', 'Respaldo en Google Drive')

@section('content')
    <section class="module-header">
        <div>
            <p class="eyebrow">Archivo documental</p>
            <h1>Respaldo en Google Drive</h1>
            <p>Respalda originales de proveedor e instantáneas del Excel de gasto real en la cuenta Google autorizada.</p>
        </div>
    </section>

    <section class="panel">
        <div class="panel__header"><div><p class="eyebrow">Conexión</p><h2>Cuenta Google</h2></div></div>
        @if (! $configurado)
            <div class="notice notice--warning notice--block"><div><strong>Falta configurar OAuth</strong><p>El administrador técnico debe indicar HIDROIL_DRIVE_CLIENT_ID y HIDROIL_DRIVE_CLIENT_SECRET en .env. La URI de retorno es {{ route('drive.callback') }}.</p></div></div>
        @else
            <p>{{ $conectado ? 'Cuenta autorizada para respaldar documentos.' : 'Conecta una cuenta Google para comenzar.' }}</p>
            <a href="{{ route('drive.conectar') }}" class="button button--primary">{{ $conectado ? 'Volver a conectar' : 'Conectar Google Drive' }}</a>
        @endif
    </section>

    <details class="panel" @if (! request('cotizaciones_page') && ! request('ordenes_page')) open @endif>
        <summary class="panel__header">Facturas de proveedor · {{ $facturas->total() }} comprobantes</summary>
        @if ($facturas->isNotEmpty())
            <div class="table-wrap table-wrap--responsive" data-responsive-table>
                <table class="data-table data-table--responsive supplier-invoice-list-table">
                    <thead><tr><th>Documento</th><th>Proveedor</th><th>Archivo</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody>
                        @foreach ($facturas as $factura)
                            @php($respaldo = $respaldos->get($factura->id))
                            <tr>
                                <td data-label="Documento"><a class="table-primary-link" href="{{ route('facturas-proveedor.show', $factura) }}">{{ $factura->numeroVisible() }}</a></td>
                                <td data-label="Proveedor">{{ $factura->proveedor?->nombreVisible() }}</td>
                                <td data-label="Archivo">{{ $factura->archivo_original_nombre }}</td>
                                <td data-label="Estado">{{ match ($respaldo?->estado) { 'COMPLETO' => 'Respaldado', 'SUBIENDO' => 'Subiendo', 'ERROR' => 'Error', default => 'Pendiente' } }}</td>
                                <td data-label="Acción">
                                    @if ($respaldo?->estado === 'COMPLETO' && $respaldo->drive_url)
                                        <div class="module-header__actions">
                                            @if ($configurado && $conectado && $respaldo->drive_id)
                                                <a href="{{ route('drive.documentos.descargar', $respaldo) }}" class="button button--secondary button--small" data-file-download>Descargar copia</a>
                                            @endif
                                            <a href="{{ $respaldo->drive_url }}" target="_blank" rel="noopener noreferrer" class="button button--ghost button--small">Abrir en Drive</a>
                                        </div>
                                    @elseif ($configurado && $conectado)
                                        <form method="POST" action="{{ route('drive.facturas.subir', $factura) }}">@csrf<button type="submit" class="button button--secondary button--small">Respaldar</button></form>
                                    @else
                                        <span>Conecta la cuenta</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$facturas" />
        @else
            <div class="empty-table-state"><strong>No hay comprobantes originales</strong><span>Los archivos adjuntos a las facturas de proveedor aparecerán aquí.</span></div>
        @endif
    </details>

    <details class="panel" @if (request('cotizaciones_page')) open @endif>
        <summary class="panel__header">Cotizaciones de proveedor · {{ $cotizaciones->total() }} documentos</summary>
        @if ($cotizaciones->isNotEmpty())
            <div class="table-wrap table-wrap--responsive" data-responsive-table>
                <table class="data-table data-table--responsive supplier-invoice-list-table">
                    <thead><tr><th>Cotización</th><th>Proveedor</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody>
                        @foreach ($cotizaciones as $cotizacion)
                            @php($respaldo = $respaldosCotizaciones->get($cotizacion->id))
                            <tr>
                                <td data-label="Cotización"><a class="table-primary-link" href="{{ route('cotizaciones-proveedor.show', $cotizacion) }}">{{ $cotizacion->codigo }}</a></td>
                                <td data-label="Proveedor">{{ $cotizacion->proveedor?->nombreVisible() }}</td>
                                <td data-label="Estado">{{ match ($respaldo?->estado) { 'COMPLETO' => 'Respaldado', 'SUBIENDO' => 'Subiendo', 'ERROR' => 'Error', default => 'Pendiente' } }}</td>
                                <td data-label="Acción">
                                    @if ($respaldo?->estado === 'COMPLETO' && $respaldo->drive_url)
                                        <div class="module-header__actions">
                                            @if ($configurado && $conectado && $respaldo->drive_id)
                                                <a href="{{ route('drive.documentos.descargar', $respaldo) }}" class="button button--secondary button--small" data-file-download>Descargar copia</a>
                                            @endif
                                            <a href="{{ $respaldo->drive_url }}" target="_blank" rel="noopener noreferrer" class="button button--ghost button--small">Abrir en Drive</a>
                                        </div>
                                    @elseif ($configurado && $conectado)
                                        <form method="POST" action="{{ route('drive.cotizaciones-proveedor.subir', $cotizacion) }}">@csrf<button type="submit" class="button button--secondary button--small">Respaldar original</button></form>
                                    @else
                                        <span>Conecta la cuenta</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$cotizaciones" />
        @else
            <div class="empty-table-state"><strong>No hay cotizaciones con archivo original</strong><span>Las cotizaciones importadas desde PDF o Excel aparecerán aquí.</span></div>
        @endif
    </details>

    <details class="panel" @if (request('ordenes_page')) open @endif>
        <summary class="panel__header">Excel de gasto real · {{ $ordenes->total() }} órdenes cerradas</summary>
        <p>Cada respaldo genera una instantánea nueva del reporte. Los movimientos registrados después pueden cambiar una descarga futura.</p>
        @if ($ordenes->isNotEmpty())
            <div class="table-wrap table-wrap--responsive" data-responsive-table>
                <table class="data-table data-table--responsive supplier-invoice-list-table">
                    <thead><tr><th>Orden</th><th>Última copia</th><th>Acción</th></tr></thead>
                    <tbody>
                        @foreach ($ordenes as $orden)
                            @php($respaldo = $respaldosOrdenes->get($orden->id))
                            <tr>
                                <td data-label="Orden"><a class="table-primary-link" href="{{ route('ordenes-operacion.gasto-real', $orden) }}">{{ $orden->codigo_orden }}</a></td>
                                <td data-label="Última copia">
                                    @if ($respaldo?->drive_url)
                                        <a href="{{ $respaldo->drive_url }}" target="_blank" rel="noopener noreferrer">{{ $respaldo->created_at?->format('d/m/Y H:i') }}</a>
                                    @else
                                        Sin respaldo
                                    @endif
                                </td>
                                <td data-label="Acción">
                                    <div class="module-header__actions">
                                        @if ($configurado && $conectado && $respaldo?->drive_id)
                                            <a href="{{ route('drive.documentos.descargar', $respaldo) }}" class="button button--ghost button--small" data-file-download>Descargar copia</a>
                                        @endif
                                        @if ($configurado && $conectado)
                                            <form method="POST" action="{{ route('drive.gasto-real.subir', $orden) }}">@csrf<button type="submit" class="button button--secondary button--small">Guardar instantánea</button></form>
                                        @else
                                            <span>Conecta la cuenta</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$ordenes" />
        @else
            <div class="empty-table-state"><strong>No hay órdenes principales cerradas</strong><span>El Excel final podrá respaldarse cuando cierres una orden OM, OP u OS.</span></div>
        @endif
    </details>
@endsection
