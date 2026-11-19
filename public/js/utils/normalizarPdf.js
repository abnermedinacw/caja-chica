import { PDFDocument } from 'https://cdn.jsdelivr.net/npm/pdf-lib@1.17.1/+esm';

export async function prepararArchivoParaSubida(archivo) {
    if (!archivo || !archivo.name.toLowerCase().endsWith('.pdf')) return archivo;

    try {
        const bytes = await archivo.arrayBuffer();
        const pdfDoc = await PDFDocument.load(bytes);

        // Re guarda el archivo sin el ObjStm Comprimido para que fpdi pueda leerlo
        const pdfCompatible = await pdfDoc.save({useObjectStreams: false});
        return new File([pdfCompatible], archivo.name, {type: 'application/pdf'});
    } catch (e) {
        const msg = String(e?.message || '').toLowerCase();
        if (msg.includes('encrypt') || msg.includes('password')) {
            throw new Error(`El PDF "${archivo.name}" tiene protección. Quitar compresión (evite usar ilovepdf).`);
        }
        return archivo;
    }
}