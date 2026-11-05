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

export async function descargarTxtReporte(respuesta, nombreTxt, nombrePdf) {
    const contentType = respuesta.headers.get('Content-Type') || '';

    if (contentType.includes('application/json')) {
        const data = await respuesta.json();
        if (!data.ok) throw new Error(data.error || 'Error en respuesta');
        
        // TXT
        if (data.txt?.contenido) {
            const blobTxt = base64ToBlob(data.txt.contenido, 'text/plain');
            descargarBlob(blobTxt, data.txt.nombre_archivo || nombreTxt);
        }

        // Reporte pdf
        if (data.pdf?.contenido) {
            const blobPdf = base64ToBlob(data.pdf.contenido, 'application/pdf');
            descargarBlob(blobPdf, data.pdf.nombre_archivo || nombrePdf);
        }
        return;
    }
    await descargarTxtDesdeRespuesta(respuesta, nombreTxt);
}

function base64ToBlob(base64, mimeType) {
    const binary = atob(base64); // Decodifica base64
    const len = binary.length;
    const bytes = new Uint8Array(len);
    for (let i = 0; i < len; i++) bytes[i] = binary.charCodeAt(i);
    return new Blob([bytes], {type:mimeType});
}

function descargarBlob(blob, nombreArchivo) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = nombreArchivo;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}