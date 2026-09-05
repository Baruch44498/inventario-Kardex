@extends('layouts.app')

@section('title', 'Presupuesto interno '.$cotizacion->codigo)
@section('page-kicker', 'Cotizaciones')
@section('page-title', 'Presupuesto interno')

@section('content')
<div class="document-flow-page budget-workflow-page">
    <a href="{{ route('cotizaciones-cliente.show', $cotizacion) }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a {{ $cotizacion->codigo }}
    </a>

    <section class="supplier-quote-hero commercial-document-hero">
        <div>
            <p class="eyebrow">Uso interno · {{ $cotizacion->codigo }}</p>
            <h1>Hoja universal de costos</h1>
            <p>{{ $cotizacion->cliente_nombre }} · OP, OM y OS · referencia cruzada en PEN y USD</p>
        </div>
        <x-ui.status-badge :tone="$cotizacion->tonoEstadoVisual()" class="badge--large">
            {{ $cotizacion->estadoVisual() }}
        </x-ui.status-badge>
    </section>

    <x-ui.workflow-stepper
        :steps="$pasosPresupuesto"
        :current="$pasoActual"
        label="Ruta de la hoja de costos"
    />

    <section class="notice notice--warning notice--block">
        <x-ui.icon name="warning" :size="20" />
        <div>
            <strong>Los costos siguen siendo internos; el precio de venta sí alimenta la cotización</strong>
            <span>Al sincronizar, OP y OS se publican como conceptos resumidos. En OM se detallan los materiales catalogados y se agrupan los costos complementarios del mantenimiento.</span>
        </div>
    </section>

    @if ($paso === 'revision' && $cotizacion->esEditable() && $cotizacion->proforma_id === null)
        <section class="notice notice--info notice--block">
            <x-ui.icon name="activity" :size="20" />
            <div>
                <strong>Actualizar la cotización del cliente</strong>
                <span>La sincronización consolida esta hoja en el detalle comercial de la orden principal y recalcula subtotal, IGV y total.</span>
                <span>
                    @if ($cotizacion->costeo_sincronizado_en)
                        Última sincronización: {{ $cotizacion->costeo_sincronizado_en->format('d/m/Y H:i') }}.
                    @else
                        Sincronización pendiente: la cotización no puede cerrarse hasta actualizar sus valores.
                    @endif
                </span>
                <form method="POST" action="{{ route('cotizaciones-cliente.presupuesto.sincronizar', $cotizacion) }}" data-confirm="¿Actualizar el detalle y el total comercial desde esta hoja de costos?">
                    @csrf
                    <button type="submit" class="button button--primary">
                        <x-ui.icon name="refresh" :size="17" />
                        Sincronizar con cotización
                    </button>
                </form>
            </div>
        </section>
    @endif

    @if ($paso === 'materiales' && $cotizacion->componentes->isNotEmpty())
        <section class="panel cost-template-workspace">
            <header class="supplier-panel-heading">
                <div>
                    <p class="eyebrow">Ahorra tiempo en trabajos repetidos</p>
                    <h2>Plantillas reutilizables de costeo</h2>
                    <p>Selecciona primero el trabajo. Las plantillas disponibles siempre respetan su tipo OP, OM u OS.</p>
                </div>
                <a href="{{ route('plantillas-costeo.index') }}" class="button button--ghost">
                    <x-ui.icon name="clipboard" :size="17" />
                    Ver plantillas guardadas
                </a>
            </header>

            <nav class="cost-template-components" aria-label="Seleccionar componente para la plantilla">
                @foreach ($cotizacion->componentes as $componente)
                    <a
                        href="{{ route('cotizaciones-cliente.presupuesto.show', ['cotizacionCliente' => $cotizacion, 'componente_id' => $componente, 'paso' => 'materiales']) }}"
                        @class([
                            'cost-template-component',
                            'is-active' => $componenteInicial?->id === $componente->id,
                        ])
                        @if ($componenteInicial?->id === $componente->id) aria-current="page" @endif
                    >
                        <span>{{ $componente->tipoOrden?->codigo }} {{ $componente->orden_secuencia }}</span>
                        <strong>{{ $componente->descripcion_componente }}</strong>
                    </a>
                @endforeach
            </nav>

            @if ($componenteInicial)
                <div class="cost-template-current">
                    <span class="type-chip">{{ $componenteInicial->tipoOrden?->codigo }}</span>
                    <div>
                        <small>Trabajando ahora</small>
                        <strong>{{ $componenteInicial->descripcion_componente }}</strong>
                    </div>
                    <span>{{ $partidasComponente->count() }} partidas vigentes</span>
                </div>

                @if ($cotizacion->esEditable() && $cotizacion->proforma_id === null)
                    <div class="cost-template-grid">
                        <article class="cost-template-card cost-template-card--apply">
                            <div>
                                <span class="cost-template-card__step">Opción A · Trabajo nuevo</span>
                                <h3>Cargar una plantilla existente</h3>
                                <p>Copia todas sus partidas al componente seleccionado. Luego podrás ajustar cantidades, precios y márgenes.</p>
                            </div>

                            @if ($partidasComponente->isEmpty())
                                <form method="POST" action="{{ route('cotizacion-componentes.plantillas.aplicar', $componenteInicial) }}" class="cost-template-form">
                                    @csrf
                                    <label for="plantilla_id">Plantilla {{ $componenteInicial->tipoOrden?->codigo }}</label>
                                    <select id="plantilla_id" name="plantilla_id" required @disabled($plantillasCompatibles->isEmpty())>
                                        <option value="">Selecciona una plantilla</option>
                                        @foreach ($plantillasCompatibles as $plantillaDisponible)
                                            <option value="{{ $plantillaDisponible->id }}" @selected((string) old('plantilla_id') === (string) $plantillaDisponible->id)>
                                                {{ $plantillaDisponible->nombre }} · {{ $plantillaDisponible->partidas_count }} partidas
                                            </option>
                                        @endforeach
                                    </select>
                                    @if ($plantillasCompatibles->isEmpty())
                                        <small>Aún no existe una plantilla para este tipo de orden.</small>
                                    @endif
                                    <button type="submit" class="button button--primary" @disabled($plantillasCompatibles->isEmpty())>
                                        <x-ui.icon name="clipboard" :size="17" />
                                        Aplicar plantilla a este trabajo
                                    </button>
                                </form>
                            @else
                                <div class="notice notice--warning notice--block cost-template-card__notice">
                                    <x-ui.icon name="warning" :size="18" />
                                    <div>
                                        <strong>Este componente ya tiene costos</strong>
                                        <span>Para evitar duplicados, solo se aplica una plantilla a un componente vacío.</span>
                                    </div>
                                </div>
                            @endif
                        </article>

                        <article class="cost-template-card cost-template-card--save">
                            <div>
                                <span class="cost-template-card__step">Opción B · Modelo terminado</span>
                                <h3>Guardar este costeo como plantilla</h3>
                                <p>Conserva el detalle completo para reutilizarlo en futuras cotizaciones del mismo tipo.</p>
                            </div>

                            @if ($partidasComponente->isNotEmpty())
                                <form method="POST" action="{{ route('cotizacion-componentes.plantillas.guardar', $componenteInicial) }}" class="cost-template-form">
                                    @csrf
                                    <label for="nombre_plantilla">Nombre de la plantilla</label>
                                    <input id="nombre_plantilla" name="nombre" type="text" value="{{ old('nombre') }}" minlength="5" maxlength="180" placeholder="Ej. Cisterna 5000 galones SCH-40" required>
                                    <label for="descripcion_plantilla">Descripción breve <span>(opcional)</span></label>
                                    <textarea id="descripcion_plantilla" name="descripcion" rows="2" maxlength="500" placeholder="Ej. Modelo base con estructura, tuberías, pintura y personal">{{ old('descripcion') }}</textarea>
                                    <button type="submit" class="button button--secondary">
                                        <x-ui.icon name="save" :size="17" />
                                        Guardar {{ $partidasComponente->count() }} partidas como plantilla
                                    </button>
                                </form>
                            @else
                                <div class="notice notice--info notice--block cost-template-card__notice">
                                    <x-ui.icon name="clipboard" :size="18" />
                                    <div>
                                        <strong>Primero completa la hoja de costos</strong>
                                        <span>Cuando este componente tenga partidas, podrás convertirlo en un modelo reutilizable.</span>
                                    </div>
                                </div>
                            @endif
                        </article>
                    </div>
                @else
                    <div class="notice notice--info notice--block cost-template-readonly">
                        <x-ui.icon name="lock" :size="18" />
                        <div><strong>Plantillas en modo consulta</strong><span>Esta cotización ya no permite cargar ni guardar partidas.</span></div>
                    </div>
                @endif
            @endif
        </section>
    @endif

    @if ($paso === 'revision')
    <section class="summary-strip summary-strip--four" aria-label="Resumen del presupuesto interno">
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--neutral"><x-ui.icon name="clipboard" :size="21" /></span>
            <div><span>Partidas vigentes</span><strong>{{ $resumen['lineas_vigentes'] }}</strong></div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--info"><x-ui.icon name="quotes" :size="21" /></span>
            <div><span>Costo neto PEN</span><strong>S/ {{ number_format($resumen['neto_soles'], 2) }}</strong></div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--success"><x-ui.icon name="activity" :size="21" /></span>
            <div><span>Venta neta PEN</span><strong>S/ {{ number_format($resumen['venta_neta_soles'], 2) }}</strong></div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--warning"><x-ui.icon name="warning" :size="21" /></span>
            <div><span>Utilidad estimada PEN</span><strong>S/ {{ number_format($resumen['utilidad_soles'], 2) }}</strong></div>
        </article>
    </section>
    @endif

    @if ($paso === 'materiales' && $cotizacion->esEditable() && $cotizacion->proforma_id === null && $componenteInicial)
        <section class="panel bulk-material-panel">
            <header class="panel-heading panel-heading--split">
                <div>
                    <p class="eyebrow">Carga rápida por etapa</p>
                    <h2>Agregar varios materiales juntos</h2>
                    <p>Define la etapa una vez, añade todas las filas necesarias y guarda el bloque completo.</p>
                </div>
                <span class="badge badge--info">{{ $componenteInicial->nombreVisible() }}</span>
            </header>
            @include('cotizaciones_cliente._materiales_etapa_form')
        </section>
    @endif

    @if ($paso === 'materiales')
        <nav class="form-actions" aria-label="Continuar hoja de costos">
            <a href="{{ $pasosPresupuesto[1]['href'] }}" class="button button--primary">
                Continuar a otros costos
                <x-ui.icon name="arrow-right" :size="17" />
            </a>
        </nav>
    @endif

    @if ($paso === 'costos')
        @if ($cotizacion->esEditable())
            <section class="panel">
                <header class="panel-heading">
                    <p class="eyebrow">Paso 2 · Registro individual</p>
                    <h2>Agregar mano de obra u otro costo</h2>
                    <p>Registra personal, servicios, transporte, viáticos u otros costos. Los materiales se gestionan en el paso anterior.</p>
                </header>
                @include('cotizaciones_cliente._presupuesto_form', [
                    'accion' => route('cotizaciones-cliente.presupuesto.store', $cotizacion),
                    'prefijo' => 'nuevo_presupuesto',
                ])
            </section>
        @endif

        <nav class="form-actions" aria-label="Navegar por la hoja de costos">
            <a href="{{ $pasosPresupuesto[0]['href'] }}" class="button button--ghost">
                <x-ui.icon name="arrow-left" :size="17" />
                Volver a materiales
            </a>
            <a href="{{ $pasosPresupuesto[2]['href'] }}" class="button button--primary">
                Revisar hoja completa
                <x-ui.icon name="arrow-right" :size="17" />
            </a>
        </nav>
    @endif

    @if ($paso === 'revision')
    @unless ($cotizacion->esEditable())
        <section class="notice notice--info notice--block">
            <x-ui.icon name="lock" :size="19" />
            <div><strong>Presupuesto congelado</strong><span>La cotización ya no está abierta. Puedes consultar el presupuesto, pero no modificarlo.</span></div>
        </section>
    @endunless

    <section class="panel supplier-quote-detail-lines">
        <header class="supplier-panel-heading">
            <div>
                <p class="eyebrow">Compatibilidad de datos anteriores</p>
                <h2>Distribución histórica del presupuesto</h2>
                <p>Estas agrupaciones se conservan para consulta. Al aprobar, todos los costos y áreas forman una sola orden principal.</p>
            </div>
        </header>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Componente</th><th class="text-right">Partidas</th><th class="text-right">Costo neto PEN</th><th class="text-right">Venta neta PEN</th><th class="text-right">Utilidad PEN</th><th class="text-right">IGV por pagar PEN</th><th class="text-right">Utilidad USD</th></tr></thead>
                <tbody>
                    @forelse ($resumen['por_componente'] as $grupo)
                        <tr>
                            <td><strong>{{ $grupo['codigo'] }}</strong><span>{{ $grupo['descripcion'] }}</span></td>
                            <td class="text-right">{{ $grupo['lineas'] }}</td>
                            <td class="text-right"><x-ui.money :value="$grupo['costo_neto_soles']" currency="PEN" /></td>
                            <td class="text-right"><x-ui.money :value="$grupo['venta_neta_soles']" currency="PEN" /></td>
                            <td class="text-right"><strong><x-ui.money :value="$grupo['utilidad_soles']" currency="PEN" /></strong></td>
                            <td class="text-right"><x-ui.money :value="$grupo['igv_por_pagar_soles']" currency="PEN" /></td>
                            <td class="text-right"><x-ui.money :value="$grupo['utilidad_dolares']" currency="USD" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="7">Agrega partidas manualmente, desde una plantilla o importando el Excel.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel supplier-quote-detail-lines">
        <header class="supplier-panel-heading">
            <div>
                <p class="eyebrow">Resumen comparable</p>
                <h2>Costos y resultado por tipo</h2>
                <p>Materiales, mano de obra, terceros, transporte, viáticos, EPP/consumibles y otros quedan bajo la misma estructura.</p>
            </div>
        </header>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Tipo</th><th class="text-right">Partidas</th><th class="text-right">Costo neto PEN</th><th class="text-right">Venta neta PEN</th><th class="text-right">Utilidad PEN</th><th class="text-right">Utilidad USD</th></tr></thead>
                <tbody>
                    @foreach ($resumen['por_tipo'] as $tipo => $grupo)
                        @continue($grupo['lineas'] === 0)
                        <tr>
                            <td><strong>{{ $grupo['nombre'] }}</strong></td>
                            <td class="text-right">{{ $grupo['lineas'] }}</td>
                            <td class="text-right"><x-ui.money :value="$grupo['neto_soles']" currency="PEN" /></td>
                            <td class="text-right"><x-ui.money :value="$grupo['venta_neta_soles']" currency="PEN" /></td>
                            <td class="text-right"><strong><x-ui.money :value="$grupo['utilidad_soles']" currency="PEN" /></strong></td>
                            <td class="text-right"><x-ui.money :value="$grupo['utilidad_dolares']" currency="USD" /></td>
                        </tr>
                    @endforeach
                    @if ($resumen['lineas_vigentes'] === 0)
                        <tr><td colspan="6">Aún no hay partidas vigentes en el presupuesto interno.</td></tr>
                    @endif
                </tbody>
                @if ($resumen['lineas_vigentes'] > 0)
                    <tfoot><tr><th>Total</th><th></th><th class="text-right"><x-ui.money :value="$resumen['neto_soles']" currency="PEN" /></th><th class="text-right"><x-ui.money :value="$resumen['venta_neta_soles']" currency="PEN" /></th><th class="text-right"><x-ui.money :value="$resumen['utilidad_soles']" currency="PEN" /></th><th class="text-right"><x-ui.money :value="$resumen['utilidad_dolares']" currency="USD" /></th></tr></tfoot>
                @endif
            </table>
        </div>
    </section>

    <section class="panel supplier-quote-detail-lines">
        <header class="supplier-panel-heading">
            <div><p class="eyebrow">Detalle y auditoría</p><h2>Partidas presupuestales</h2><p>Las partidas anuladas se conservan y dejan de sumar.</p></div>
        </header>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Tipo / concepto</th><th>Cálculo original</th><th class="text-right">Costo PEN</th><th class="text-right">Venta PEN</th><th class="text-right">Utilidad PEN</th><th class="text-right">Utilidad USD</th><th>Estado / acciones</th></tr></thead>
                <tbody>
                    @forelse ($partidas as $item)
                        <tr @class(['is-muted' => ! $item->estaVigente()])>
                            <td>
                                <strong>{{ $item->tipoVisible() }}</strong>
                                @if ($item->area)<span>Área: {{ $item->area->nombre }}</span>
                                @elseif ($item->grupo_costo)<span>Sección: {{ $item->grupo_costo }}</span>@endif
                                <span>{{ $item->descripcion }}</span>
                                @if ($item->tipo_costo === 'SERVICIO_TERCERO')
                                    <span>{{ \App\Models\CotizacionPresupuesto::EJECUCIONES_SERVICIO[$item->ejecucion_servicio] ?? 'Pendiente de clasificar' }}</span>
                                @endif
                                @if ($item->componente)<span>{{ $item->componente->tipoOrden?->codigo }} {{ $item->componente->orden_secuencia }} · {{ $item->componente->descripcion_componente }}</span>@endif
                                @if ($item->producto)<span>{{ $item->producto->codigo }} · vinculado a inventario</span>@endif
                                @if ($item->observacion)<span>{{ $item->observacion }}</span>@endif
                            </td>
                            <td>
                                <strong>{{ number_format((float) $item->cantidad, 2) }} {{ $item->unidadVisible() }} × {{ $item->moneda === 'USD' ? 'US$' : 'S/' }} {{ number_format((float) $item->costo_unitario, 2) }}</strong>
                                <span>{{ \App\Models\CotizacionPresupuesto::MODOS_IGV[$item->igv_modo] ?? $item->igv_modo }} · TC {{ number_format((float) $item->tipo_cambio, 2) }}</span>
                                @if ((float) $item->carga_social_porcentaje > 0)<span>Carga social {{ number_format((float) $item->carga_social_porcentaje, 2) }} %</span>@endif
                                <span>Margen {{ number_format((float) $item->margen_porcentaje, 2) }} % · IGV venta {{ number_format((float) $item->igv_venta_porcentaje, 2) }} %</span>
                            </td>
                            <td class="text-right"><x-ui.money :value="$item->costo_neto_soles" currency="PEN" /><span>Total <x-ui.money :value="$item->costo_total_soles" currency="PEN" /></span></td>
                            <td class="text-right"><x-ui.money :value="$item->precio_venta_neto_soles" currency="PEN" /><span>Total <x-ui.money :value="$item->precio_venta_total_soles" currency="PEN" /></span></td>
                            <td class="text-right"><strong><x-ui.money :value="$item->utilidad_estimada_soles" currency="PEN" /></strong><span>IGV pagar <x-ui.money :value="$item->igv_por_pagar_soles" currency="PEN" /></span></td>
                            <td class="text-right"><x-ui.money :value="$item->utilidad_estimada_dolares" currency="USD" /></td>
                            <td>
                                <x-ui.status-badge :tone="$item->estaVigente() ? 'success' : 'danger'">{{ ucfirst(strtolower($item->estado)) }}</x-ui.status-badge>
                                <span>{{ $item->registradoPor?->nombreVisible() }} · {{ $item->registrado_en?->format('d/m/Y H:i') }}</span>
                                @if ($item->estaVigente() && $cotizacion->esEditable())
                                    <a href="{{ route('cotizacion-presupuestos.edit', $item) }}" class="button button--ghost">Editar</a>
                                    <form method="POST" action="{{ route('cotizacion-presupuestos.anular', $item) }}" data-confirm="¿Anular esta partida y excluirla de los totales?">
                                        @csrf
                                        @method('PATCH')
                                        <input type="text" name="motivo_anulacion" minlength="5" maxlength="500" placeholder="Motivo de anulación" required>
                                        <button type="submit" class="button button--danger">Anular</button>
                                    </form>
                                @elseif (! $item->estaVigente())
                                    <span>{{ $item->motivo_anulacion }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No se han registrado partidas presupuestales.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($cotizacion->esEditable())
        <nav class="form-actions" aria-label="Volver a editar la hoja de costos">
            <a href="{{ $pasosPresupuesto[1]['href'] }}" class="button button--ghost">
                <x-ui.icon name="arrow-left" :size="17" />
                Volver a otros costos
            </a>
        </nav>
    @endif
    @endif
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/presupuesto-cotizacion.js') }}" defer></script>
    <script src="{{ asset('js/materiales-etapa-cotizacion.js') }}" defer></script>
@endpush
