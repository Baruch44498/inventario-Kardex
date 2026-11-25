<?php

namespace App\Services\Documentos;

use App\Models\DriveConexion;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleDriveRespaldoService
{
    private const LIMITE_ARCHIVO = 15 * 1024 * 1024;

    public function configurado(): bool
    {
        return filled(config('services.hidroil_drive.client_id'))
            && filled(config('services.hidroil_drive.client_secret'));
    }

    public function urlAutorizacion(string $estado, string $redirect): string
    {
        $this->exigirConfiguracion();

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.hidroil_drive.client_id'),
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/drive.file',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $estado,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function intercambiarCodigo(string $codigo, string $redirect): string
    {
        $this->exigirConfiguracion();
        $respuesta = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'code' => $codigo,
            'client_id' => config('services.hidroil_drive.client_id'),
            'client_secret' => config('services.hidroil_drive.client_secret'),
            'redirect_uri' => $redirect,
            'grant_type' => 'authorization_code',
        ]);

        if (! $respuesta->successful() || ! is_string($respuesta->json('refresh_token'))
            || $respuesta->json('refresh_token') === '') {
            throw new RuntimeException('Google no entregó autorización permanente. Vuelve a conectar la cuenta.');
        }

        return $respuesta->json('refresh_token');
    }

    /** @return array{id: string, url: string} */
    public function subir(string $rutaAbsoluta, string $nombre, string $mime): array
    {
        $token = $this->tokenAcceso();

        $contenido = file_get_contents($rutaAbsoluta);
        if ($contenido === false) {
            throw new RuntimeException('No se pudo leer el comprobante original.');
        }

        if (strlen($contenido) > self::LIMITE_ARCHIVO) {
            throw new RuntimeException('El archivo supera el límite de 15 MB para este respaldo.');
        }

        $frontera = 'hidroil_'.bin2hex(random_bytes(16));
        $nombreSeguro = preg_replace('/[[:cntrl:]]/', '', basename($nombre)) ?: 'comprobante';
        $metadatos = json_encode(['name' => $nombreSeguro, 'mimeType' => $mime], JSON_THROW_ON_ERROR);
        $cuerpo = "--{$frontera}\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n{$metadatos}\r\n"
            ."--{$frontera}\r\nContent-Type: {$mime}\r\n\r\n{$contenido}\r\n--{$frontera}--";

        $respuesta = Http::withToken($token)->timeout(90)
            ->withBody($cuerpo, 'multipart/related; boundary='.$frontera)
            ->post('https://www.googleapis.com/upload/drive/v3/files?'.http_build_query([
                'uploadType' => 'multipart', 'fields' => 'id,webViewLink',
            ]));

        $id = $respuesta->successful() ? $respuesta->json('id') : null;
        if (! is_string($id) || $id === '') {
            throw new RuntimeException('Google Drive no confirmó el respaldo. Revisa la conexión e inténtalo de nuevo.');
        }

        return [
            'id' => $id,
            'url' => $respuesta->json('webViewLink') ?: 'https://drive.google.com/file/d/'.$id.'/view',
        ];
    }

    /** @return array{nombre: string, contenido: string} */
    public function descargar(string $id): array
    {
        $token = $this->tokenAcceso();
        $url = 'https://www.googleapis.com/drive/v3/files/'.rawurlencode($id);
        $metadatos = Http::withToken($token)->timeout(20)->get($url, [
            'fields' => 'id,name,size,capabilities(canDownload)',
        ]);
        if (! $metadatos->successful() || $metadatos->json('id') !== $id) {
            throw new RuntimeException('La copia no está disponible en la cuenta Google conectada.');
        }
        if ($metadatos->json('capabilities.canDownload') !== true) {
            throw new RuntimeException('La cuenta Google conectada no puede descargar esta copia.');
        }
        $tamano = $metadatos->json('size');
        if (! is_numeric($tamano) || (int) $tamano < 0 || (int) $tamano > self::LIMITE_ARCHIVO) {
            throw new RuntimeException('El tamaño de la copia supera el límite de 15 MB o no está disponible.');
        }

        $respuesta = Http::withToken($token)->timeout(90)->get($url, ['alt' => 'media']);
        if (! $respuesta->successful()) {
            throw new RuntimeException('No se pudo descargar la copia desde Google Drive.');
        }
        $contenido = $respuesta->body();
        if (strlen($contenido) !== (int) $tamano) {
            throw new RuntimeException('La descarga no coincide con el tamaño registrado en Google Drive.');
        }
        $nombre = preg_replace('/[[:cntrl:]]/', '', basename(str_replace('\\', '/', (string) $metadatos->json('name'))));

        return ['nombre' => $nombre ?: 'respaldo-hidroil', 'contenido' => $contenido];
    }

    private function tokenAcceso(): string
    {
        $this->exigirConfiguracion();
        $conexion = DriveConexion::query()->first();
        if (! $conexion) {
            throw new RuntimeException('Conecta primero una cuenta de Google Drive.');
        }

        $respuestaToken = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.hidroil_drive.client_id'),
            'client_secret' => config('services.hidroil_drive.client_secret'),
            'refresh_token' => $conexion->refresh_token,
            'grant_type' => 'refresh_token',
        ]);
        $token = $respuestaToken->successful() ? $respuestaToken->json('access_token') : null;
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('La conexión con Google Drive caducó. Conecta nuevamente la cuenta.');
        }

        return $token;
    }

    private function exigirConfiguracion(): void
    {
        if (! $this->configurado()) {
            throw new RuntimeException('Configura el ID y secreto OAuth de Google Drive en el archivo .env.');
        }
    }
}
