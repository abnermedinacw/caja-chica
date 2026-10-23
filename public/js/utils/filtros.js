// Funciones de filtro reutilizables — antes cada pantalla (Historial de
// Resúmenes, Fondos, Historial de aprobaciones) escribía su propia versión
// de "busca este texto en estos campos" o "esta fecha cae en este rango",
// con pequeñas diferencias entre una y otra. Son puras (sin Vue, sin DOM),
// así que una sola versión sirve para las tres.

// Busca el término (sin importar mayúsculas/tildes de más o de menos en
// mayúsculas) en cualquiera de los campos indicados. Pensado para la caja
// de búsqueda libre, que suele mirar más de un campo a la vez (ej. número
// de documento + DNI + nombre).
export function coincideEnAlguno(item, campos, termino) {
    if (!termino || !termino.trim()) return true;
    const t = termino.trim().toLowerCase();
    return campos.some(campo => String(item[campo] ?? '').toLowerCase().includes(t));
}

// Para un <select> de filtro exacto (estado, tipo, empresa...) — "" siempre
// significa "todos", sin importar el valor real del campo.
export function coincideExacto(valor, filtro) {
    if (filtro === '' || filtro === null || filtro === undefined) return true;
    return String(valor) === String(filtro);
}

// Para filtros de rango de fecha (Desde/Hasta). Acepta tanto "2026-09-15"
// como "2026-09-15 11:43:19" (los eventos traen hora, los demás campos no)
// — solo usa los primeros 10 caracteres para comparar.
export function dentroDeRango(fecha, desde, hasta) {
    if (!fecha) return !desde && !hasta;
    const soloFecha = String(fecha).slice(0, 10);
    if (desde && soloFecha < desde) return false;
    if (hasta && soloFecha > hasta) return false;
    return true;
}
