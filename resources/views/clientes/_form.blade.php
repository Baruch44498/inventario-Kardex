@php
    $editando = isset($cliente);
    $protegido = (bool) ($cliente->es_mostrador ?? false);
    $tipoActual = old(
        'tipo_documento',
        $cliente->tipo_documento ?? 'RUC'
    );
@endphp

<div class="client-form-sections" data-client-form>
    @include('clientes.partials._form_identificacion')
    @include('clientes.partials._form_contacto')
</div>

@include('clientes.partials._form_acciones')

@unless ($protegido)
    @push('scripts')
        <script src="{{ asset('js/client-form.js') }}" defer></script>
    @endpush
@endunless
