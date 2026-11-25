<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentoDrive extends Model
{
    protected $table = 'documentos_drive';

    protected $fillable = [
        'tipo', 'origen_id', 'hash_sha256', 'estado',
        'drive_id', 'drive_url', 'registrado_por',
    ];
}
