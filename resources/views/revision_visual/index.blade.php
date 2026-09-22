@extends('layouts.app')

@section('title', 'Recorrido visual')

@section('content')
    <x-ui.page-header
        kicker="Revisión de interfaz"
        title="Recorrido visual"
        description="Abre cada pantalla disponible para tu perfil. En los listados, selecciona un registro para revisar su detalle."
        :back-href="route('dashboard')"
        back-label="Volver al dashboard"
    />

    <div class="notice notice--info notice--block" role="note">
        Comprueba las pantallas en escritorio, tablet y móvil. Las acciones y documentos
        siguen sujetos a los permisos de cada módulo.
    </div>

    <div class="dashboard-grid admin-area-grid">
        @foreach (config('hidroil_revision_visual.grupos') as $grupo)
            @php
                $visibles = array_filter(
                    $grupo['enlaces'],
                    fn (array $enlace): bool => auth()->user()->puedeAlguno(...$enlace['permisos'])
                );
            @endphp

            @if (count($visibles))
                <section class="panel admin-area-card" aria-labelledby="revision-grupo-{{ $loop->index }}">
                    <header class="panel__header">
                        <h2 id="revision-grupo-{{ $loop->index }}">{{ $grupo['titulo'] }}</h2>
                    </header>
                    <div class="admin-area-links">
                        @foreach ($visibles as $enlace)
                            <a class="role-quick-card" href="{{ route($enlace['ruta']) }}">
                                <span><x-ui.icon name="arrow-right" :size="18" /></span>
                                <div>
                                    <strong>{{ $enlace['titulo'] }}</strong>
                                    <small>{{ $enlace['detalle'] }}</small>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    </div>
@endsection
