@if ($bandejaOperativa['visible'])
    <section class="panel" aria-label="Bandeja operativa de {{ $bandejaOperativa['perfil'] }}">
        <header class="panel__header">
            <div>
                <p class="eyebrow">Trabajo pendiente · {{ $bandejaOperativa['perfil'] }}</p>
                <h2>Bandeja operativa</h2>
                <p>{{ $bandejaOperativa['descripcion'] }}</p>
            </div>
            <span class="badge badge--{{ $bandejaOperativa['items'] === [] ? 'success' : 'warning' }}">
                {{ count($bandejaOperativa['items']) }} {{ count($bandejaOperativa['items']) === 1 ? 'prioridad' : 'prioridades' }}
            </span>
        </header>

        @if ($bandejaOperativa['items'] !== [])
            <div class="role-quick-grid">
                @foreach ($bandejaOperativa['items'] as $pendiente)
                    <a href="{{ $pendiente['ruta'] }}" class="role-quick-card">
                        <span><x-ui.icon :name="$pendiente['icono']" :size="22" /></span>
                        <div>
                            <strong>
                                <span class="badge badge--{{ $pendiente['tono'] }}">
                                    {{ number_format($pendiente['cantidad']) }}
                                </span>
                                {{ $pendiente['titulo'] }}
                            </strong>
                            <small>{{ $pendiente['detalle'] }}</small>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="empty-card">
                <span class="empty-state__icon empty-state__icon--success">
                    <x-ui.icon name="check-circle" :size="34" />
                </span>
                <strong>Sin pendientes operativos</strong>
                <span>No hay acciones de abastecimiento que requieran atención.</span>
            </div>
        @endif
    </section>
@endif
