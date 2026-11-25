const { writeFile } = require('node:fs/promises');
const { join } = require('node:path');

function html(texto = '') {
    const entidades = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
    return String(texto).replace(/[&<>"']/g, caracter => entidades[caracter]);
}

function fila(r) {
    const lista = [...r.hallazgos, ...r.candidatos].map(item => '<li>' + html(item) + '</li>').join('');
    const captura = r.captura ? '<a href="' + html(r.captura) + '">Ver captura</a>' : '—';
    return '<tr><th scope="row">' + html(r.nombre) + '<small>' + html(r.ruta) + '</small></th>' +
        '<td>' + html(r.rol) + '</td><td>' + html(r.navegador) + ' · ' + r.ancho + ' px</td>' +
        '<td>' + html(r.estado) + '</td><td>' + (r.http ?? '—') + '</td><td>' +
        (r.documento ?? '—') + '</td><td>' + (lista ? '<ul>' + lista + '</ul>' : 'OK') + '</td><td>' + captura + '</td></tr>';
}

function tabla(resultados) {
    return '<div class="table"><table><thead><tr><th>Pantalla</th><th>Rol</th><th>Perfil</th>' +
        '<th>Estado</th><th>HTTP</th><th>Ancho del documento</th><th>Resultado</th><th>Captura</th>' +
        '</tr></thead><tbody>' + resultados.map(fila).join('') + '</tbody></table></div>';
}

function galeria(resultados) {
    const modulos = Map.groupBy(resultados, resultado => resultado.nombre.split('.')[0]);
    return [...modulos].sort(([a], [b]) => a.localeCompare(b)).map(([modulo, capturas]) =>
        '<details><summary>' + html(modulo) + ' · ' + capturas.length + ' capturas</summary>' +
        '<div class="galeria">' + capturas.map(r =>
            '<a class="captura" href="' + html(r.captura) + '">' +
            '<img alt="" loading="lazy" src="' + html(r.captura) + '">' +
            html(r.nombre) + '<small>' + html(r.navegador) + ' · ' + r.ancho + ' px · ' +
            html(r.rol) + ' · ' + html(r.estado) + '</small></a>'
        ).join('') + '</div></details>'
    ).join('');
}

async function escribirInforme(carpeta, pantallas, exclusiones, resultados) {
    const conHallazgos = resultados.filter(r => r.hallazgos.length);
    const conCaptura = resultados.filter(r => r.captura);
    const pantallasMedidas = new Set(resultados.filter(r => r.estado === 'Apertura' && Number.isInteger(r.documento)).map(r => r.nombre));
    const documento = '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' +
        '<title>Auditoría móvil de HIDROIL</title><style>' +
        'body{font:16px system-ui,sans-serif;color:#142b3b;background:#f4f7fa;margin:24px auto;padding:0 16px;max-width:1400px} ' +
        '.table{overflow:auto} table{width:100%;border-collapse:collapse;background:#fff}th,td{text-align:left;vertical-align:top;border-bottom:1px solid #d7e0e8;padding:10px} ' +
        'th{white-space:nowrap}td ul{margin:0;padding-inline-start:18px}small{display:block;font-weight:normal;color:#445d70} a{color:#075781} summary{cursor:pointer;margin:16px 0;font-weight:700}' +
        '.galeria{display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:12px} ' +
        '.captura{display:block;padding:6px;border:1px solid #d7e0e8;border-radius:8px;background:#fff;overflow-wrap:anywhere} ' +
        '.captura img{display:block;width:100%;height:220px;object-fit:cover;object-position:top;background:#edf3f7;margin-bottom:6px}' +
        '</style><main><h1>Auditoría móvil de HIDROIL</h1><p>' + pantallasMedidas.size + ' pantallas medidas de ' +
        Object.keys(pantallas).length + ' previstas · ' + resultados.length + ' comprobaciones · ' + conHallazgos.length +
        ' con hallazgos · ' + conCaptura.length + ' capturas</p><h2>Hallazgos</h2>' +
        (conHallazgos.length ? tabla(conHallazgos) : '<p>Sin hallazgos en las comprobaciones ejecutadas.</p>') +
        '<h2>Galería por módulo</h2>' + galeria(conCaptura) +
        '<details><summary>Todas las comprobaciones</summary>' + tabla(resultados) + '</details>' +
        '<details><summary>Rutas fuera del barrido</summary><ul>' +
        Object.entries(exclusiones).map(([ruta, razon]) => '<li><code>' + html(ruta) + '</code> · ' + html(razon) + '</li>').join('') +
        '</ul></details></main></html>';

    await Promise.all([
        writeFile(join(carpeta, 'index.html'), documento),
        writeFile(join(carpeta, 'resultados.json'), JSON.stringify({ resultados, exclusiones }, null, 2)),
    ]);
    console.log(pantallasMedidas.size + ' pantallas medidas, ' + resultados.length + ' comprobaciones, ' + conHallazgos.length + ' con hallazgos.');
    console.log('Informe: ' + join(carpeta, 'index.html'));
    return conHallazgos.length > 0;
}

module.exports = { escribirInforme };
