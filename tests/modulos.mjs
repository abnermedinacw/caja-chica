import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { createContext, SourceTextModule } from 'node:vm';

function renderizarCabecera(baseUrl) {
    return execFileSync('C:/xampp/php/php.exe', ['-r', `
        define('BASE_URL', '${baseUrl}');
        $_SESSION = ['username' => 'Prueba', 'rol' => 'SOLICITANTE'];
        require 'src/Vistas/layout.php';
        aperturaLayout('Prueba de módulos', 'home');
    `], { cwd: new URL('..', import.meta.url), encoding: 'utf8' });
}
const base = 'http://localhost';
const html = renderizarCabecera('/caja-chica/public');
const mapa = JSON.parse(html.match(/<script type="importmap">(.*?)<\/script>/s)[1]).imports;
const formatos = '/caja-chica/public/js/utils/formatos.js';
assert.match(mapa[formatos], /formatos\.js\?v=\d+$/);
assert(html.indexOf('type="importmap"') < html.indexOf('</head>'));

// También funciona al alojar public directamente en la raíz de un dominio.
const htmlRaiz = renderizarCabecera('');
const mapaRaiz = JSON.parse(htmlRaiz.match(/<script type="importmap">(.*?)<\/script>/s)[1]).imports;
assert.match(mapaRaiz['/js/utils/formatos.js'], /^\/js\/utils\/formatos\.js\?v=\d+$/);

const contexto = createContext({});
const modulos = new Map();
const cacheAntigua = new Map([[new URL(formatos, base).href,
    'export function formatearFecha() {} export function formatearSoles() {}',
]]);
let lecturasAntiguas = 0;
function cargar(url) {
    if (!modulos.has(url)) {
        modulos.set(url, (async () => {
            // Una URL sin versión podría devolver la copia antigua que produjo el error.
            assert(new URL(url).searchParams.has('v'), `Import sin versión: ${url}`);
            if (cacheAntigua.has(url)) {
                lecturasAntiguas++;
                return new SourceTextModule(cacheAntigua.get(url), { context: contexto, identifier: url });
            }
            const respuesta = await fetch(url);
            assert.equal(respuesta.status, 200, url);
            return new SourceTextModule(await respuesta.text(), { context: contexto, identifier: url });
        })());
    }
    return modulos.get(url);
}
function resolver(specifier, referencia) {
    const url = new URL(specifier, referencia);
    return new URL(mapa[url.pathname] || url.href, base).href;
}
for (const archivo of ['historial_resumenes', 'historial_aprobaciones', 'ver_resumen', 'pagos_bbva', 'perfil']) {
    const ruta = `/caja-chica/public/js/${archivo}.js`;
    const modulo = await cargar(new URL(mapa[ruta], base).href);
    if (modulo.status === 'unlinked') {
        await modulo.link((specifier, padre) => cargar(resolver(specifier, padre.identifier)));
    }
    assert.equal(modulo.status, 'linked');
}
assert.equal(lecturasAntiguas, 0);
console.log(`OK: ${modulos.size} módulos HTTP enlazados con versiones y exports válidos, incluso con la URL antigua cacheada`);
