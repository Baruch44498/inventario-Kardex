<?php

namespace App\Services\Compras;

use App\Models\Requisicion;
use App\Models\User;

class SiguienteAccionRequerimientoService
{
    /**
     * @return array{titulo:string,detalle:string,tono:string,icono:string,ruta:?string,boton:?string,boton_listado:?string}
     */
    public function construir(
        User $usuario,
        Requisicion $requerimiento,
        ?bool $hayOfertas = null
    ): array {
        $puedeCrear = $usuario->puede('requerimientos.compra.crear') || $usuario->esAdministrador();
        $puedeGestionar = $usuario->puede('requerimientos.compra.gestionar') || $usuario->esAdministrador();
        $puedeComprar = $usuario->puede('compras.gestionar') || $usuario->esAdministrador();
        $puedeRecibir = $usuario->puede('ingresos.registrar') || $usuario->esAdministrador();

        if ($requerimiento->estaAnulada()) {
            return $this->accion(
                'No requiere otra acción',
                'El documento quedó anulado y se conserva únicamente para trazabilidad.',
                'neutral',
                'error'
            );
        }

        if ($requerimiento->abastecimientoCompleto()) {
            return $this->accion(
                'Abastecimiento completado',
                'Todas las cantidades solicitadas fueron confirmadas mediante notas de ingreso.',
                'success',
                'check-circle'
            );
        }

        if ($requerimiento->esBorrador()) {
            return $this->accion(
                'Revisar y enviar a Logística',
                'Almacén debe confirmar productos y cantidades antes de enviar el requerimiento.',
                'info',
                'arrow-right',
                $puedeCrear ? route('requerimientos-compra.edit', $requerimiento) : null,
                $puedeCrear ? 'Revisar borrador' : null,
                $puedeCrear ? 'Continuar borrador' : null
            );
        }

        if ($requerimiento->estaEnviada()) {
            return $this->accion(
                'Logística debe tomar el requerimiento',
                'El documento ya salió de Almacén y está esperando un responsable de Logística.',
                'info',
                'mail',
                $puedeGestionar
                    ? route('requerimientos-compra.show', $requerimiento).'#gestion-logistica'
                    : null,
                $puedeGestionar ? 'Ir a tomar requerimiento' : null,
                $puedeGestionar ? 'Tomar requerimiento' : null
            );
        }

        if ($requerimiento->estaEnRevision()) {
            return $this->accion(
                'Iniciar la cotización con proveedores',
                'Logística debe confirmar que comenzó a solicitar precios para habilitar la comparación.',
                'warning',
                'quotes',
                $puedeGestionar
                    ? route('requerimientos-compra.show', $requerimiento).'#gestion-logistica'
                    : null,
                $puedeGestionar ? 'Ir al seguimiento' : null,
                $puedeGestionar ? 'Iniciar cotización' : null
            );
        }

        if ($requerimiento->estaCotizando()) {
            $hayOfertas ??= $this->tieneOfertasRegistradas($requerimiento);

            return $this->accion(
                $hayOfertas
                    ? 'Comparar ofertas y elegir proveedores'
                    : 'Registrar cotizaciones de proveedores',
                $hayOfertas
                    ? 'Ya existen ofertas disponibles. Selecciona por producto y el sistema generará una orden por proveedor.'
                    : 'Todavía hay productos sin una oferta disponible. Registra la respuesta de uno o más proveedores.',
                'warning',
                $hayOfertas ? 'banknote' : 'quotes',
                $puedeComprar
                    ? ($hayOfertas
                        ? route('requerimientos-compra.comparativo', $requerimiento)
                        : route('cotizaciones-proveedor.create', ['requisicion_id' => $requerimiento->id]))
                    : null,
                $puedeComprar
                    ? ($hayOfertas ? 'Comparar ofertas' : 'Registrar cotización')
                    : null
            );
        }

        return $this->accion(
            'Registrar las recepciones pendientes',
            'La gestión de compra terminó; Almacén debe ingresar las entregas parciales o totales de las órdenes emitidas.',
            'info',
            'entry',
            $puedeRecibir
                ? route('ordenes-compra.index', [
                    'q' => $requerimiento->codigo,
                    'situacion' => 'PENDIENTE',
                ])
                : null,
            $puedeRecibir ? 'Ver órdenes pendientes' : null
        );
    }

    private function tieneOfertasRegistradas(Requisicion $requerimiento): bool
    {
        if ($requerimiento->getAttribute('cotizaciones_registradas_count') !== null) {
            return (int) $requerimiento->getAttribute('cotizaciones_registradas_count') > 0;
        }

        return $requerimiento->cotizaciones()
            ->where('estado', 'REGISTRADA')
            ->whereHas('detalles')
            ->exists();
    }

    /**
     * @return array{titulo:string,detalle:string,tono:string,icono:string,ruta:?string,boton:?string,boton_listado:?string}
     */
    private function accion(
        string $titulo,
        string $detalle,
        string $tono,
        string $icono,
        ?string $ruta = null,
        ?string $boton = null,
        ?string $botonListado = null
    ): array {
        return [
            'titulo' => $titulo,
            'detalle' => $detalle,
            'tono' => $tono,
            'icono' => $icono,
            'ruta' => $ruta,
            'boton' => $boton,
            'boton_listado' => $botonListado ?? $boton,
        ];
    }
}
