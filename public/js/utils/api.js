// Wrapper único para las llamadas fetch del Resumen: todas mandan un POST
// con FormData y esperan JSON {ok, ...} de vuelta, y todas necesitan el
// mismo manejo de "no se pudo conectar"
export async function enviarFormulario(url, campos = {}) {
    const formData = new FormData();
    Object.entries(campos).forEach(([clave, valor]) => formData.append(clave, valor));
    try {
        const r = await fetch(url, { method: 'POST', body: formData });
        return await r.json();
    } catch (e) {
        return { ok: false, error: 'No se pudo conectar con el servidor.' };
    }
}

export async function obtenerJson(url, mensajeError = 'No se pudo conectar con el servidor.') {
    try {
        const r = await fetch(url);
        return await r.json();
    } catch (e) {
        return { ok: false, error: mensajeError };
    }
}
