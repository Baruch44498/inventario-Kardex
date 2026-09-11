<?php

namespace App\Services\Compras;

use App\Models\AlertaStock;
use App\Models\OrdenCompra;
use App\Models\Requisicion;
use App\Models\User;

class BandejaOperativaComprasService
{
    /**
     * @return array{
     *     visible: bool,
     *     perfil: string,
     *     descripcion: string,
     *     items: array<int, array{titulo: string, detalle: string, cantidad: int, tono: string, icono: string, ruta: string}>
     * }
     */
    public function construir(User $usuario): array
    {
        if ($usuario->tieneRol('ALMACEN')) {
            return $this->paraAlmacen();
        }

        if ($usuario->tieneRol('COMERCIAL_LOGISTICA') && $usuario->puede('compras.gestionar')) {
            return $this->paraLogistica();
        }

        return [
            'visible' => false,
            'perfil' => '',
            'descripcion' => '',
            'items' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function paraAlmacen(): array
    {
        $ordenesPendientes = OrdenCompra::query()
            ->whereIn('estado', ['APROBADA', 'PARCIALMENTE_RECIBIDA']);

        return $this->bandeja(
            'Almacén',
            'Prioridades de reposición y recepción que requieren atención.',
            [
                $this->item(
                    'Órdenes atrasadas',
                    'Recepciones cuya fecha comprometida ya venció.',
                    (clone $ordenesPendientes)
                        ->whereNotNull('fecha_entrega_requerida')
                        ->whereDate('fecha_entrega_requerida', '<', today())
                        ->count(),
                    'danger',
                    'warning',
                    route('ordenes-compra.index', ['situacion' => 'ATRASADA'])
                ),
                $this->item(
                    'Órdenes por recibir',
                    'Compras aprobadas con productos aún pendientes de ingreso.',
                    (clone $ordenesPendientes)->count(),
                    'warning',
                    'entry',
                    route('ordenes-compra.index', ['situacion' => 'PENDIENTE'])
                ),
                $this->item(
                    'Alertas sin requerimiento',
                    'Faltantes activos que todavía no forman parte de una reposición.',
                    AlertaStock::query()
                        ->whereIn('estado', ['ACTIVA', 'ATENDIDA'])
                        ->whereDoesntHave('requerimientos', fn($query) => $query
                            ->where('estado', '!=', 'ANULADA'))
                        ->count(),
                    'danger',
                    'alerts',
                    route('alertas.index')
                ),
                $this->item(
                    'Borradores por enviar',
                    'Requerimientos guardados que Logística aún no puede atender.',
                    Requisicion::query()->where('estado', 'BORRADOR')->count(),
                    'info',
                    'requisitions',
                    route('requerimientos-compra.index', ['estado' => 'BORRADOR'])
                ),
            ]
        );
    }

    /** @return array<string, mixed> */
    private function paraLogistica(): array
    {
        $cotizando = Requisicion::query()->where('estado', 'COTIZANDO');
        $conOfertaRegistrada = fn($query) => $query
            ->where('estado', 'REGISTRADA')
            ->whereHas('detalles');

        return $this->bandeja(
            'Logística',
            'Requerimientos, comparativos y compras que esperan una decisión.',
            [
                $this->item(
                    'Requerimientos por recibir',
                    'Solicitudes enviadas por Almacén que aún no fueron tomadas.',
                    Requisicion::query()->where('estado', 'ENVIADA')->count(),
                    'warning',
                    'requisitions',
                    route('requerimientos-compra.index', ['estado' => 'ENVIADA'])
                ),
                $this->item(
                    'Requerimientos en revisión',
                    'Solicitudes recibidas que todavía no iniciaron cotización.',
                    Requisicion::query()->where('estado', 'EN_REVISION')->count(),
                    'info',
                    'clipboard',
                    route('requerimientos-compra.index', ['estado' => 'EN_REVISION'])
                ),
                $this->item(
                    'Pendientes de cotizar',
                    'Requerimientos que aún no tienen ofertas registradas.',
                    (clone $cotizando)
                        ->whereDoesntHave('cotizaciones', $conOfertaRegistrada)
                        ->count(),
                    'warning',
                    'quotes',
                    route('requerimientos-compra.index', ['estado' => 'COTIZANDO'])
                ),
                $this->item(
                    'Listos para comparar',
                    'Requerimientos con ofertas disponibles para seleccionar.',
                    (clone $cotizando)
                        ->whereHas('cotizaciones', $conOfertaRegistrada)
                        ->count(),
                    'success',
                    'activity',
                    route('requerimientos-compra.index', ['estado' => 'COTIZANDO'])
                ),
                $this->item(
                    'Órdenes atrasadas',
                    'Compras pendientes cuya fecha comprometida ya venció.',
                    OrdenCompra::query()
                        ->whereIn('estado', ['APROBADA', 'PARCIALMENTE_RECIBIDA'])
                        ->whereNotNull('fecha_entrega_requerida')
                        ->whereDate('fecha_entrega_requerida', '<', today())
                        ->count(),
                    'danger',
                    'warning',
                    route('ordenes-compra.index', ['situacion' => 'ATRASADA'])
                ),
            ]
        );
    }

    /**
     * @param  array<int, array{titulo: string, detalle: string, cantidad: int, tono: string, icono: string, ruta: string}>  $items
     * @return array<string, mixed>
     */
    private function bandeja(string $perfil, string $descripcion, array $items): array
    {
        return [
            'visible' => true,
            'perfil' => $perfil,
            'descripcion' => $descripcion,
            'items' => array_values(array_filter(
                $items,
                fn(array $item): bool => $item['cantidad'] > 0
            )),
        ];
    }

    /** @return array{titulo: string, detalle: string, cantidad: int, tono: string, icono: string, ruta: string} */
    private function item(
        string $titulo,
        string $detalle,
        int $cantidad,
        string $tono,
        string $icono,
        string $ruta
    ): array {
        return compact('titulo', 'detalle', 'cantidad', 'tono', 'icono', 'ruta');
    }
}
