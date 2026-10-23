import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { execFileSync } from 'node:child_process';
import { numeroDocumento } from '../public/js/utils/formatos.js';
import { coincideEnAlguno } from '../public/js/utils/filtros.js';

const numeros = [1, 9, 10, 99, 999, 1000, '005'];
assert.deepEqual(numeros.map(numeroDocumento), ['001', '009', '010', '099', '999', '1000', '005']);
const php = execFileSync('C:/xampp/php/php.exe', ['-r',
    'require "src/Vistas/helpers.php"; echo json_encode(array_map("numeroDocumento", [1,9,10,99,999,1000,"005"]));',
], { cwd: new URL('..', import.meta.url), encoding: 'utf8' });
assert.deepEqual(numeros.map(numeroDocumento), JSON.parse(php));
assert(coincideEnAlguno({ numero: 5, numero_formateado: numeroDocumento(5) }, ['numero', 'numero_formateado'], '005'));
console.log('OK: numeración PHP/Vue coherente y filtro con ceros');

globalThis.Vue = { ref: value => ({ value }), watch: () => {} };
const { usarModales } = await import('../public/js/resumen/modales.js');
const modales = usarModales();
let ejecuciones = 0;
modales.pedirConfirmacion({ titulo: 'Enviar', mensaje: '', accion: () => { ejecuciones++; } });
modales.confirmarAccion();
modales.confirmarAccion();
assert.equal(ejecuciones, 1);
modales.pedirConfirmacion({ titulo: 'Enviar', mensaje: '', accion: () => { ejecuciones++; } });
modales.cancelarConfirmacion();
modales.confirmarAccion();
assert.equal(ejecuciones, 1);
console.log('OK: doble clic y confirmación cancelada no repiten la operación');

function elemento(valores = {}) {
    return { eventos: {}, atributos: {}, ...valores,
        addEventListener(nombre, accion) { this.eventos[nombre] = accion; },
        setAttribute(nombre, valor) { this.atributos[nombre] = valor; },
    };
}
const password = elemento({ type: 'password' });
const ojo = elemento({ dataset: { iconoMostrar: 'mostrar', iconoOcultar: 'ocultar' } });
const animaciones = [];
let cancelaciones = 0;
const moneda = { clientHeight: 64, animate(fotogramas, opciones) {
    animaciones.push({ fotogramas, opciones });
    return { cancel() { cancelaciones++; } };
} };
const boton = elemento({ querySelector: () => moneda });
const preferencia = { matches: false };
runInNewContext(readFileSync(new URL('../public/js/login.js', import.meta.url), 'utf8'), {
    document: { getElementById: id => id === 'password' ? password : ojo, querySelectorAll: () => [boton] },
    window: { matchMedia: () => preferencia },
});
ojo.eventos.click();
assert.equal(password.type, 'text');
assert.equal(ojo.atributos['aria-pressed'], 'true');
ojo.eventos.click();
assert.equal(password.type, 'password');
boton.eventos.click();
boton.eventos.click();
assert.equal(cancelaciones, 1);
assert(animaciones[0].fotogramas[1].transform.includes('rotateY(180deg)'));
preferencia.matches = true;
boton.eventos.click();
assert(animaciones.at(-1).fotogramas.every(frame => !frame.transform));
console.log('OK: contraseña, monedas, clics repetidos y movimiento reducido');

const respuesta = await fetch('http://localhost/caja-chica/public/login');
assert.equal(respuesta.status, 200);
const html = await respuesta.text();
assert.equal((html.match(/class="moneda-juego/g) || []).length, 6);
assert.match(html, /method="POST"/);
assert.match(html, /autocomplete="current-password"/);
assert(!html.includes('aria-hidden="true">\n            <svg class="fondo-red"'));
for (const recurso of [...html.matchAll(/(?:src|href)="([^"]+\.(?:css|js|ttf)(?:\?[^"]*)?)"/g)]) {
    assert.equal((await fetch(new URL(recurso[1], respuesta.url))).status, 200, recurso[1]);
}
console.log('OK: login HTTP y sus recursos cargan correctamente');
