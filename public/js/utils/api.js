// Wrapper único para las llamadas fetch del Resumen: todas mandan un POST
// con FormData y esperan JSON {ok, ...} de vuelta, y todas necesitan el
// mismo manejo de "no se pudo conectar"
export async function enviarFormulario(url, campos = {}) {
    const formData = new FormData();
    Object.entries(campos).forEach(([clave, valor]) => formData.append(clave, valor));
    try {
        const r = await fetch(url, { method: 'POST', body: formData });
        const texto = await r.text();
        try {
            return JSON.parse(texto);
        } catch (e) {
            const detalle = String(texto || '').replace(/\s+/g, ' ').trim().slice(0, 180);
            return {
                ok: false,
                error: detalle
                    ? `El servidor devolvió una respuesta inválida (no JSON): ${detalle}`
                    : 'El servidor devolvió una respuesta inválida (no JSON).',
            };
        }
    } catch (e) {
        return { ok: false, error: 'No se pudo conectar con el servidor.' };
    }
}

export async function obtenerJson(url, mensajeError = 'No se pudo conectar con el servidor.') {
    try {
        const r = await fetch(url);
        const texto = await r.text();
        try {
            return JSON.parse(texto);
        } catch (e) {
            const detalle = String(texto || '').replace(/\s+/g, ' ').trim().slice(0, 180);
            return {
                ok: false,
                error: detalle
                    ? `El servidor devolvió una respuesta inválida (no JSON): ${detalle}`
                    : 'El servidor devolvió una respuesta inválida (no JSON).',
            };
        }
    } catch (e) {
        return { ok: false, error: mensajeError };
    }
}
