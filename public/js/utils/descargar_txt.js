export async function descargarTxtDesdeRespuesta(respuesta, nombrePorDefecto) {
    const blob = await respuesta.blob();
    const disposition = respuesta.headers.get('Content-Disposition') || '';
    const coincidencia = disposition.match(/filename="?([^";]+)"?/i);
    const nombreArchivo = coincidencia ? coincidencia[1] : nombrePorDefecto;

    const url = URL.createObjectURL(blob);
    const enlace = document.createElement('a');
    enlace.href = url;
    enlace.download = nombreArchivo;
    document.body.appendChild(enlace);
    enlace.click();
    enlace.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}
