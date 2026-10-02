<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriveConexion extends Model
{
    protected $table = 'drive_conexiones';

    protected $fillable = ['refresh_token', 'conectado_por'];

    protected function casts(): array
    {
        return ['refresh_token' => 'encrypted'];
    }
}
