<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditoriaObserver
{
    public function created(Model $model): void
    {
        $this->registrar($model, 'CREADO', array_keys($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $campos = array_values(array_diff(array_keys($model->getChanges()), ['updated_at', 'ultimo_acceso_en']));
        if ($campos !== []) {
            $this->registrar($model, 'ACTUALIZADO', $campos);
        }
    }

    public function deleted(Model $model): void
    {
        $this->registrar($model, 'ELIMINADO', []);
    }

    /** @param array<int, string> $campos */
    private function registrar(Model $model, string $accion, array $campos): void
    {
        // Algunas migraciones crean o actualizan modelos antes de crear esta tabla.
        if (! Schema::hasTable('auditoria_eventos')) {
            return;
        }

        $atributos = $model->getAttributes();
        $etiqueta = $atributos['codigo']
            ?? $atributos['codigo_orden']
            ?? $atributos['username']
            ?? null;

        DB::table('auditoria_eventos')->insert([
            'usuario_id' => Auth::id(),
            'entidad' => class_basename($model),
            'entidad_id' => $model->getKey(),
            'etiqueta' => $etiqueta ? mb_substr((string) $etiqueta, 0, 100) : null,
            'accion' => $accion,
            'campos' => $campos === [] ? null : json_encode($campos, JSON_THROW_ON_ERROR),
            'estado_anterior' => $accion === 'ACTUALIZADO' && in_array('estado', $campos, true)
                ? (string) $model->getRawOriginal('estado') : null,
            'estado_nuevo' => in_array('estado', $campos, true)
                ? (string) $model->getAttribute('estado') : null,
            'created_at' => now(),
        ]);
    }
}
