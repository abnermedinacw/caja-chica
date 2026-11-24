<?php
require_once __DIR__ . '/layout.php';

function vistaVerResumen(
    array $resumen,
    ?array $documentoContable,
    array $itemsDocumentoContable,
    ?array $documentoNoContable,
    array $itemsDocumentoNoContable,
    ?array $planilla,
    array $itemsPlanilla,
    array $planillasDisponibles,
    array $tiposComprobante,
    array $tiposDocumentoNoContable,
    bool $esEditable,
    array $eventos,
    string $modo = 'LECTURA',
    array $regreso = [],
    array $adjuntosSueltos = [],
    bool $puedeEditarItems = false
): void {
    $mostrarColumnaAcciones = $esEditable || $puedeEditarItems;
    $regreso += [
        'url' => BASE_URL . '/home',
        'texto' => 'Volver al inicio',
    ];
    aperturaLayout('Resumen N°' . numeroDocumento($resumen['numero']), 'home');
?>
    <div class="barra-superior">
        <div>
            <a href="<?= htmlspecialchars($regreso['url']) ?>" class="volver"><?= icono('volver') ?> <?= htmlspecialchars($regreso['texto']) ?></a>
            <h1>Resumen N° <?= numeroDocumento($resumen['numero']) ?></h1>
        </div>
        <div class="barra-superior-derecha">
            <span class="estado estado-<?= strtolower($resumen['estado_general']) ?>">
                <?= htmlspecialchars($resumen['estado_general']) ?>
            </span>
            <?php if (!empty($eventos)): ?>
                <button type="button" class="boton boton-secundario" onclick="window.abrirHistorial()">
                    <?= icono('ver') ?> Historial (<?= count($eventos) ?>)
                </button>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/exportar-resumen-pdf?id=<?= $resumen['id'] ?>" target="_blank" class="boton boton-secundario">Descargar PDF</a>
        </div>
    </div>
    <?php if ($modo !== 'APROBACION' && $resumen['estado_general'] === 'RECHAZADO' && !empty($resumen['motivo_rechazo'])): ?>
        <section class="caja-motivo">
            <h2>Motivo del rechazo</h2>
            <p><?= nl2br(htmlspecialchars($resumen['motivo_rechazo'])) ?></p>
            <?php if (!$esEditable): ?>
                <p class="texto-vacio" style="margin:8px 0 0;">
                    Está siendo revisado por <strong><?= htmlspecialchars($resumen['nivel_actual_nombre'] ?? 'un nivel anterior') ?></strong> antes de llegarte para que lo corrijas — todavía no puedes editarlo.
                </p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <div id="app" v-cloak>
        <section class="ficha ficha-cabecera">
            <div class="ficha-cabecera-acciones" v-if="esEditable">
                <div class="ficha-cabecera-botones">
                    <template v-if="!cabeceraEditando">
                        <button type="button" class="boton boton-chico" :disabled="hayPanelesEnEdicion || procesando" @click="iniciarEdicionCabecera" title="Editar cabecera">
                        <?= icono('editar') ?> Editar
                    </button>
                    <button type="button" class="boton boton-chico boton-anular-sutil" :disabled="hayPanelesEnEdicion || procesando" @click="anularResumen" title="Anular resumen">
                        <?= icono('quitar') ?>
                    </button>
                    </template> 
                    <template v-else>
                        <button type="button" class="boton boton-primario boton-chico" :disabled="guardandoCabecera" @click="guardarCabecera">Guardar</button>
                        <button type="button" class="boton boton-secundario boton-chico" :disabled="guardandoCabecera" @click="cancelarEdicionCabecera">Cancelar</button>
                    </template>
                </div>
                <p class="error ficha-cabecera-error" v-if="errorCabecera">{{ errorCabecera }}</p>
            </div>
            
            <div class="ficha-fila">
                <span class="ficha-label">Responsable</span>
                <span class="ficha-valor">
                    {{ resumen.responsable }}
                </span>

               <span class="ficha-label">Número</span>
               <span class="ficha-valor">{{ resumen.numero }}</span>
            </div>
            <div class="ficha-fila">
                <span class="ficha-label">DNI</span>
                <span class="ficha-valor">{{ resumen.dni }}</span>

                 <span class="ficha-label">Periodo</span>
                <span class="ficha-valor" v-if="!cabeceraEditando">{{ formatearFecha(resumen.periodo_inicio) }} - {{ formatearFecha(resumen.periodo_fin) }}</span>
                <span class="ficha-valor ficha-valor-edicion" v-else>
                    <input type="date" v-model="cabeceraForm.periodo_inicio" :max="hoy">
                    <input type="date" v-model="cabeceraForm.periodo_fin" :max="hoy">
                </span>
            </div>
            <div class="ficha-fila">
                <span class="ficha-label">Área</span>
                <span class="ficha-valor">{{ resumen.area_nombre }}</span>

                <span class="ficha-label">Fecha de emisión</span>
                <span class="ficha-valor" v-if="!cabeceraEditando">{{ formatearFecha(resumen.fecha_emision) }}</span>
                <span class="ficha-valor" v-else><input type="date" v-model="cabeceraForm.fecha_emision" :max="hoy"></span>
            </div>
            <div class="ficha-fila">
                <span class="ficha-label">Monto asignado</span>
                <span class="ficha-valor">{{ formatearSoles(resumen.monto_asignado) }}</span>
                <span class="ficha-label">Total egresos</span>
                <span class="ficha-valor">{{ formatearSoles(resumen.total_egresos) }}</span>
            </div>
            <div class="ficha-fila">
                <span class="ficha-label">Empresa</span>
                <span class="ficha-valor">{{ resumen.empresa }}</span>

                <span class="ficha-label">Nivel actual</span>
                <span class="ficha-valor">{{ resumen.nivel_actual_nombre || '-' }}</span>
            </div>
        </section>

        <!-- Confirmación de guardado: sobrevive al recargo de página (ver
             guardarYRecargar en ver_resumen.js) para que quede claro que la
             acción sí terminó, no solo que "la pantalla cambió". -->
        <div v-if="toast" class="toast toast-exito" role="status">
            <?= icono('check') ?>
            <span>{{ toast.mensaje }}</span>
            <button type="button" class="toast-cerrar" @click="toast = null" aria-label="Cerrar aviso">×</button>
        </div>

        <section>
            <div class="encabezado-documentos">
                <h2>Documentos del Resumen</h2>
                <div class="acciones-documentos">
                    <button
                        v-if="esEdicionContabilidad"
                        type="button"
                        class="boton boton-chico"
                        :class="{ 'boton-primario': modoCorreccion }"
                        :disabled="hayPanelesEnEdicion || procesando"
                        @click="alternarModoCorreccion"
                        title="Habilitar edición puntual de ítems y adjuntos">
                        <?= icono('editar') ?> {{ modoCorreccion ? 'Salir de corrección' : 'Corregir ítems' }}
                    </button>
                    <button type="button" class="boton boton-chico" @click="mostrarVistaPrevia = true" title="Vista previa del Resumen">
                        <?= icono('ver') ?> Vista previa
                    </button>
                </div>
            </div>
            <p v-if="esEdicionContabilidad && modoCorreccion" class="aviso-modo-correccion" role="status">
                Modo corrección activo: presiona <strong>Editar</strong> en el documento que necesites corregir. Solo puedes modificar ítems existentes y sus adjuntos; los cambios quedarán registrados.
            </p>

            <div class="tabla-scroll tabla-scroll-vertical tabla-scroll-resumen">
            <table class="tabla tabla-resumen-documentos">
                <colgroup>
                    <?php if ($mostrarColumnaAcciones): ?>
                        <col style="width:5%">
                        <col style="width:29%">
                        <col style="width:8%">
                        <col style="width:14%">
                        <col style="width:14%">
                        <col style="width:30%">
                    <?php else: ?>
                        <col style="width:5%">
                        <col style="width:46%">
                        <col style="width:10%">
                        <col style="width:19%">
                        <col style="width:20%">
                    <?php endif; ?>
                </colgroup>
                <thead>
                    <tr>
                        <th class="celda-control-global">
                            <button
                                type="button"
                                class="boton-control-tabla"
                                :class="{ 'boton-control-tabla-abierto': todosDesplegados }"
                                :title="todosDesplegados ? 'Contraer todo' : 'Desplegar todo'"
                                :aria-label="todosDesplegados ? 'Contraer todo' : 'Desplegar todo'"
                                :aria-expanded="todosDesplegados"
                                @click.stop="alternarTodasLasFilas">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </button>
                        </th>
                        <th>Tipo</th>
                        <th>N°</th>
                        <th>Fecha</th>
                        <th>Total</th>
                        <?php if ($mostrarColumnaAcciones): ?>
                            <th></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>

                    <!-- Documento Contable -->
                    <template v-if="contable.documento || contable.editando">
                        <tr class="fila-documento fila-documento-contable" :class="{ 'fila-documento-abierta': filaEstaAbierta('contable') }" @click="toggleFila('contable')">
                            <td data-col="chevron">{{ filaEstaAbierta('contable') ? '▾' : '▸' }}</td>
                            <td data-col="tipo"><span class="punto-tipo punto-tipo-contable"></span>Documento Contable</td>
                            <td data-col="numero">{{ contable.documento ? numeroDocumento(contable.documento.numero) : '(nuevo)' }}</td>
                            <td data-col="fecha">{{ contable.documento ? formatearFecha(contable.documento.fecha_rendicion) : '-' }}</td>
                            <td data-col="total">{{ formatearSoles(totalContable) }}</td>
                            <?php if ($mostrarColumnaAcciones): ?>
                                <td data-col="acciones" class="celda-acciones" :class="{ 'celda-acciones-oculta-movil': !((esEditable || modoCorreccion) && contable.documento && !contable.editando) }" @click.stop>
                                    <button
                                        v-if="(esEditable || modoCorreccion) && contable.documento && !contable.editando" type="button"
                                        class="boton boton-chico"
                                        :disabled="hayPanelesEnEdicion || cabeceraEditando || procesando"
                                        @click="editarContable">
                                        <?= icono('editar') ?> Editar</button>
                                    <button v-if="esEditable && contable.documento && !contable.editando" type="button" class="boton boton-chico" :disabled="hayPanelesEnEdicion || cabeceraEditando || procesando" @click="quitarDocumentoContable"><?= icono('quitar') ?> Quitar</button>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <tr v-if="filaEstaAbierta('contable')" class="fila-detalle-documento">
                            <td colspan="<?= $mostrarColumnaAcciones ? 6 : 5 ?>" class="celda-expandida celda-expandida-contable">
                                <p class="subtitulo-documento subtitulo-documento-contable">
                                    <strong>Documento Contable</strong> - {{ contable.editando ? 'Editando' : 'Detalle' }}
                                </p>
                                <template v-if="!contable.editando">
                                    <div class="tabla-scroll tabla-scroll-detalle tabla-scroll-tarjetas">
                                    <table class="tabla tabla-tarjetas tabla-tarjetas-detalle">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Fecha</th>
                                                <th>Comprobante</th>
                                                <th>Proveedor</th>
                                                <th>Concepto</th>
                                                <th>Monto</th>
                                                <th>Adjuntos</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(it, idx) in contable.items" :key="idx">
                                                <td data-label="Item" class="celda-item-num">{{ idx + 1 }}</td>
                                                <td data-label="Fecha">{{ formatearFecha(it.fecha) }}</td>
                                                <td data-label="Comprobante" class="celda-comprobante">
                                                    <span>{{ [it.tipo_comprobante_nombre || it.tipo_comprobante, it.serie, it.numero_comprobante].filter(Boolean).join(' ') }}</span>
                                                    <div v-if="it.cpe_estado_sunat" class="badge-cpe" :class="it.cpe_estado_sunat === 'ACEPTADO' && it.cpe_estado_ruc === 'ACTIVO' && it.cpe_condicion_ruc === 'HABIDO' ? 'badge-cpe-ok' : 'badge-cpe-error'">
                                                        SUNAT: {{ it.cpe_estado_sunat }}
                                                    </div>
                                                </td>
                                                <td data-label="Proveedor" class="celda-proveedor">
                                                    <strong>{{ it.proveedor_ruc || '-' }}</strong><br>
                                                    <small>{{ it.proveedor_razon_social || 'Sin razón social' }}</small><br>
                                                    <small v-if="it.proveedor_estado || it.proveedor_condicion">{{ [it.proveedor_estado, it.proveedor_condicion].filter(Boolean).join(' · ') }}</small>
                                                </td>
                                                <td data-label="Concepto">{{ it.concepto }}</td>
                                                <td data-label="Monto" class="celda-monto">{{ formatearSoles(it.monto) }}</td>
                                                <td data-label="Adjuntos" class="celda-adjuntos">
                                                    <a v-for="adj in it.adjuntos" :key="adj.id"
                                                        :href="datos.baseUrl + '/descargar?tipo=contable&id=' + contable.documento.id + '&adjunto=' + adj.id"
                                                        target="_blank" rel="noopener" class="chip-adjunto"
                                                        :class="{ 'chip-adjunto-xml': adj.tipo_archivo === 'XML' }"
                                                        :title="adj.nombre_archivo">
                                                        <?= icono('clip') ?><span>{{ adj.tipo_archivo === 'XML' ? 'XML: ' : 'Adjunto: ' }}{{ adj.nombre_archivo }}</span>
                                                    </a>
                                                    <span v-if="!it.adjuntos || it.adjuntos.length === 0" class="texto-vacio">Sin adjuntos</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    </div>
                                    <div v-if="datos.adjuntosSueltos.contable.length" class="adjuntos-sueltos">
                                        <strong>Adjuntos del documento sin ítem asociado</strong>
                                        <a v-for="adj in datos.adjuntosSueltos.contable" :key="adj.id"
                                            :href="datos.baseUrl + '/descargar?tipo=contable&id=' + contable.documento.id + '&adjunto=' + adj.id"
                                            target="_blank" rel="noopener" class="chip-adjunto" :title="adj.nombre_archivo">
                                            <?= icono('clip') ?><span>{{ adj.nombre_archivo }}</span>
                                        </a>
                                    </div>
                                    <p v-if="esEditable" class="texto-vacio texto-ayuda-guardar" style="text-align:center;">
                                        <?= icono('clip') ?> Cada ítem requiere sustento (PDF). 
                                    </p>
                                </template>
                                <template v-else>
                                    <section class="ficha ficha-formulario">
                                        <div class="campo">
                                            <label>Periodo inicio</label>
                                            <input type="date" v-model="contable.periodoInicio" :min="resumen.periodo_inicio" :max="resumen.periodo_fin" required>
                                        </div>
                                        <div class="campo">
                                            <label>Periodo fin</label>
                                            <input type="date" v-model="contable.periodoFin" :min="resumen.periodo_inicio" :max="resumen.periodo_fin" required>
                                        </div>
                                        <div class="campo">
                                            <label>Fecha de rendición</label>
                                            <input type="date" v-model="contable.fecha" :min="contable.periodoFin" :max="hoy" required>
                                        </div>
                                    </section>

                                    <div class="tabla-scroll tabla-scroll-detalle tabla-scroll-tarjetas">
                                    <table class="tabla tabla-tarjetas tabla-tarjetas-detalle">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Fecha</th>
                                                <th>Concepto</th>
                                                <th>Comprobante</th>
                                                <th>Monto</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(it, idx) in contable.items" :key="idx">
                                                <td data-label="Item" class="celda-item-num">{{ idx + 1 }}</td>
                                                <td data-label="Fecha">{{ it.fecha }}</td>
                                                <td data-label="Concepto">{{ it.concepto }}</td>
                                                <td data-label="Comprobante" class="celda-comprobante">
                                                    <span>{{ [it.tipo_comprobante_nombre || it.tipo_comprobante, it.serie, it.numero_comprobante].filter(Boolean).join(' ') }}</span>
                                                    <div v-if="it.cpe_estado_sunat" class="badge-cpe" :class="it.cpe_estado_sunat === 'ACEPTADO' && it.cpe_estado_ruc === 'ACTIVO' && it.cpe_condicion_ruc === 'HABIDO' ? 'badge-cpe-ok' : 'badge-cpe-error'">
                                                        SUNAT: {{ it.cpe_estado_sunat }}
                                                    </div>
                                                </td>
                                                <td data-label="Monto" class="celda-monto">{{ formatearSoles(it.monto) }}</td>
                                                <td class="celda-acciones celda-accion-tarjeta">
                                                    <button type="button" class="boton boton-chico" @click="abrirModalItemContable(idx)"><?= icono('editar') ?> Editar</button>
                                                    <button v-if="esEditable" type="button" class="boton boton-chico" @click="quitarItemContable(idx)"><?= icono('quitar') ?> Quitar</button>
                                                    <br><small v-if="it._archivoPendienteNombre">📎 {{ it._archivoPendienteNombre }}</small>
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr class="fila-total-detalle">
                                                <td colspan="4" class="texto-derecha"><strong>Total</strong></td>
                                                <td colspan="2"><strong>{{ formatearSoles(totalContable) }}</strong></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                    </div>
                                    <p class="texto-vacio" v-if="contable.items.length === 0">Todavía no agregaste ningún comprobante.</p>

                                    <div v-if="esEditable" class="acciones-edicion-documento-items">
                                        <button type="button" class="boton boton-chico" @click="abrirModalItemContable()"><?= icono('mas') ?> Añadir comprobante</button>
                                    </div>
                                    <div class="error" v-if="contable.error">{{ contable.error }}</div>
                                    <div class="acciones-edicion-documento-cierre">
                                        <button type="button" class="boton boton-secundario" @click="cancelarContable">Cancelar</button>
                                        <button type="button" class="boton boton-primario" :disabled="contable.guardando" @click="guardarDocumentoContable">
                                            {{ contable.guardando ? 'Guardando…' : 'Guardar' }}
                                        </button>
                                    </div>
                                </template>
                            </td>
                        </tr>
                    </template>

                    <!-- Documento No Contable -->
                    <template v-if="noContable.documento || noContable.editando">
                        <tr class="fila-documento fila-documento-no_contable" :class="{ 'fila-documento-abierta': filaEstaAbierta('no_contable') }" @click="toggleFila('no_contable')">
                            <td data-col="chevron">{{ filaEstaAbierta('no_contable') ? '▾' : '▸' }}</td>
                            <td data-col="tipo"><span class="punto-tipo punto-tipo-no_contable"></span>Documento No Contable</td>
                            <td data-col="numero">{{ noContable.documento ? numeroDocumento(noContable.documento.numero) : '(nuevo)' }}</td>
                            <td data-col="fecha">{{ noContable.documento ? formatearFecha(noContable.documento.fecha_rendicion) : '-' }}</td>
                            <td data-col="total">{{ formatearSoles(totalNoContable) }}</td>
                            <?php if ($mostrarColumnaAcciones): ?>
                                <td data-col="acciones" class="celda-acciones" :class="{ 'celda-acciones-oculta-movil': !((esEditable || modoCorreccion) && noContable.documento && !noContable.editando) }" @click.stop>
                                    <button
                                        v-if="(esEditable || modoCorreccion) && noContable.documento && !noContable.editando" type="button"
                                        class="boton boton-chico"
                                        :disabled="hayPanelesEnEdicion || cabeceraEditando || procesando"
                                        @click="editarNoContable">
                                        <?= icono('editar') ?> Editar
                                    </button>
                                    <button v-if="esEditable && noContable.documento && !noContable.editando" type="button" class="boton boton-chico" :disabled="hayPanelesEnEdicion || cabeceraEditando || procesando" @click="quitarDocumentoNoContable"><?= icono('quitar') ?> Quitar</button>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <tr v-if="filaEstaAbierta('no_contable')" class="fila-detalle-documento">
                            <td colspan="<?= $mostrarColumnaAcciones ? 6 : 5 ?>" class="celda-expandida celda-expandida-no_contable">
                                <p class="subtitulo-documento subtitulo-documento-contable">
                                    <strong>Documento No Contable</strong> - {{ noContable.editando ? 'Editando' : 'Detalle' }}
                                </p>
                                <template v-if="!noContable.editando">
                                    <div class="tabla-scroll tabla-scroll-detalle tabla-scroll-tarjetas">
                                    <table class="tabla tabla-tarjetas tabla-tarjetas-detalle">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Fecha</th>
                                                <th>Tipo</th>
                                                <th>Descripción</th>
                                                <th>Sustento</th>
                                                <th>Monto</th>
                                                <th>Adjuntos</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(it, idx) in noContable.items" :key="idx">
                                                <td data-label="Item" class="celda-item-num">{{ idx + 1 }}</td>
                                                <td data-label="Fecha">{{ formatearFecha(it.fecha_documento) }}</td>
                                                <td data-label="Tipo">{{ it.tipo_documento_nombre }}</td>
                                                <td data-label="Descripción">{{ it.descripcion }}</td>
                                                <td data-label="Sustento">
                                                    <span v-if="it.numero_paginas">{{ it.numero_paginas }} pág.</span>
                                                    <span v-if="it.numero_paginas && it.observaciones"> · </span>
                                                    <span>{{ it.observaciones || (it.numero_paginas ? '' : '-') }}</span>
                                                </td>
                                                <td data-label="Monto" class="celda-monto">{{ formatearSoles(it.monto) }}</td>
                                                <td data-label="Adjuntos" class="celda-adjuntos">
                                                    <a v-for="adj in it.adjuntos" :key="adj.id"
                                                        :href="datos.baseUrl + '/descargar?tipo=no_contable&id=' + noContable.documento.id + '&adjunto=' + adj.id"
                                                        target="_blank" rel="noopener" class="chip-adjunto"
                                                        :class="{ 'chip-adjunto-xml': adj.tipo_archivo === 'XML' }"
                                                        :title="adj.nombre_archivo">
                                                        <?= icono('clip') ?><span>{{ adj.tipo_archivo === 'XML' ? 'XML: ' : 'Adjunto: ' }}{{ adj.nombre_archivo }}</span>
                                                    </a>
                                                    <span v-if="!it.adjuntos || it.adjuntos.length === 0" class="texto-vacio">Sin adjuntos</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    </div>
                                    <div v-if="datos.adjuntosSueltos.no_contable.length" class="adjuntos-sueltos">
                                        <strong>Adjuntos del documento sin ítem asociado</strong>
                                        <a v-for="adj in datos.adjuntosSueltos.no_contable" :key="adj.id"
                                            :href="datos.baseUrl + '/descargar?tipo=no_contable&id=' + noContable.documento.id + '&adjunto=' + adj.id"
                                            target="_blank" rel="noopener" class="chip-adjunto" :title="adj.nombre_archivo">
                                            <?= icono('clip') ?><span>{{ adj.nombre_archivo }}</span>
                                        </a>
                                    </div>
                                    <p v-if="esEditable" class="texto-vacio texto-ayuda-guardar" style="text-align:center;">
                                        <?= icono('clip') ?> Cada ítem requiere sustento (PDF). 
                                    </p>
                                </template>
                                <template v-else>
                                    <section class="ficha ficha-formulario">
                                        <div class="campo">
                                            <label>Periodo inicio</label>
                                            <input type="date" v-model="noContable.periodoInicio" :min="resumen.periodo_inicio" :max="resumen.periodo_fin" required>
                                        </div>
                                        <div class="campo">
                                            <label>Periodo fin</label>
                                            <input type="date" v-model="noContable.periodoFin" :min="resumen.periodo_inicio" :max="resumen.periodo_fin" required>
                                        </div>
                                        <div class="campo">
                                            <label>Fecha de rendición</label>
                                            <input type="date" v-model="noContable.fecha" :min="noContable.periodoFin" :max="hoy" required>
                                        </div>
                                    </section>

                                    <div class="tabla-scroll tabla-scroll-detalle tabla-scroll-tarjetas">
                                    <table class="tabla tabla-tarjetas tabla-tarjetas-detalle">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Fecha</th>
                                                <th>Tipo</th>
                                                <th>Descripción</th>
                                                <th>Monto</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(it, idx) in noContable.items" :key="idx">
                                                <td data-label="Item" class="celda-item-num">{{ idx + 1 }}</td>
                                                <td data-label="Fecha">{{ formatearFecha(it.fecha_documento) }}</td>
                                                <td data-label="Tipo">{{ it.tipo_documento_nombre }}</td>
                                                <td data-label="Descripción">{{ it.descripcion }}</td>
                                                <td data-label="Monto" class="celda-monto">{{ formatearSoles(it.monto) }}</td>
                                                <td class="celda-acciones celda-accion-tarjeta">
                                                    <button type="button" class="boton boton-chico" @click="abrirModalItemNoContable(idx)"><?= icono('editar') ?> Editar</button>
                                                    <button v-if="esEditable" type="button" class="boton boton-chico" @click="quitarItemNoContable(idx)"><?= icono('quitar') ?> Quitar</button>
                                                    <br><small v-if="it._archivoPendienteNombre">📎 {{ it._archivoPendienteNombre }}</small>
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr class="fila-total-detalle">
                                                <td colspan="4" class="texto-derecha"><strong>Total</strong></td>
                                                <td colspan="2"><strong>{{ formatearSoles(totalNoContable) }}</strong></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                    </div>
                                    <p class="texto-vacio" v-if="noContable.items.length === 0">Todavía no agregaste ningún item.</p>

                                    <div v-if="esEditable" class="acciones-edicion-documento-items">
                                        <button type="button" class="boton boton-chico" @click="abrirModalItemNoContable()"><?= icono('mas') ?> Añadir item</button>
                                    </div>
                                    <div class="error" v-if="noContable.error">{{ noContable.error }}</div>
                                    <div class="acciones-edicion-documento-cierre">
                                        <button type="button" class="boton boton-secundario" @click="cancelarNoContable">Cancelar</button>
                                        <button type="button" class="boton boton-primario" :disabled="noContable.guardando" @click="guardarDocumentoNoContable">
                                            {{ noContable.guardando ? 'Guardando…' : 'Guardar' }}
                                        </button>
                                    </div>
                                </template>
                            </td>
                        </tr>
                    </template>

                    <!-- Planilla de Movilidad -->
                    <template v-if="planilla.documento || planilla.editando">
                        <tr class="fila-documento fila-documento-planilla" :class="{ 'fila-documento-abierta': filaEstaAbierta('planilla') }" @click="toggleFila('planilla')">
                            <td data-col="chevron">{{ filaEstaAbierta('planilla') ? '▾' : '▸' }}</td>
                            <td data-col="tipo"><span class="punto-tipo punto-tipo-planilla"></span>Planilla de Movilidad</td>
                            <td data-col="numero">{{ planilla.documento ? numeroDocumento(planilla.documento.numero) : '(nuevo)' }}</td>
                            <td data-col="fecha">{{ planilla.documento ? formatearFecha(planilla.documento.fecha_emision) : '-' }}</td>
                            <td data-col="total">{{ formatearSoles(totalPlanilla) }}</td>
                            <?php if ($mostrarColumnaAcciones): ?>
                                <td data-col="acciones" class="celda-acciones" :class="{ 'celda-acciones-oculta-movil': !((esEditable || modoCorreccion) && planilla.documento && !planilla.editando) }" @click.stop>
                                    <button
                                        v-if="(esEditable || modoCorreccion) && planilla.documento && !planilla.editando"
                                        type="button"
                                        class="boton boton-chico"
                                        :disabled="hayPanelesEnEdicion || cabeceraEditando || procesando"
                                        @click="editarPlanilla">
                                        <?= icono('editar') ?> Editar
                                    </button>
                                    <button v-if="esEditable && planilla.documento && !planilla.editando" type="button" class="boton boton-chico" :disabled="hayPanelesEnEdicion || cabeceraEditando || procesando" @click="quitarPlanilla"><?= icono('quitar') ?> Quitar</button>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <tr v-if="filaEstaAbierta('planilla')" class="fila-detalle-documento">
                            <td colspan="<?= $mostrarColumnaAcciones ? 6 : 5 ?>" class="celda-expandida celda-expandida-planilla">
                                <p class="subtitulo-documento subtitulo-documento-contable">
                                    <strong>Planilla de movilidad</strong> - {{ planilla.editando ? 'Editando' : 'Detalle' }}
                                </p>
                                <template v-if="!planilla.editando">
                                    <div class="tabla-scroll tabla-scroll-detalle tabla-scroll-tarjetas">
                                    <table class="tabla tabla-tarjetas tabla-tarjetas-detalle">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Fecha</th>
                                                <th>Motivo</th>
                                                <th>Origen</th>
                                                <th>Destino</th>
                                                <th>Monto</th>
                                                <th>Evidencia</th>
                                                <th>Adjuntos</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(it, idx) in planilla.items" :key="idx">
                                                <td data-label="Item" class="celda-item-num">{{ idx + 1 }}</td>
                                                <td data-label="Fecha">{{ formatearFecha(it.fecha) }}</td>
                                                <td data-label="Motivo">{{ it.motivo }}</td>
                                                <td data-label="Origen">{{ it.origen }}</td>
                                                <td data-label="Destino">{{ it.destino }}</td>
                                                <td data-label="Monto" class="celda-monto">{{ formatearSoles(it.monto) }}</td>
                                                <td data-label="Evidencia">
                                                    {{ it.tipo_evidencia || 'NINGUNO' }}
                                                </td>
                                                <td data-label="Adjuntos" class="celda-adjuntos">
                                                    <a 
                                                        v-for="adj in it.adjuntos" 
                                                        :key="adj.id" 
                                                        :href="datos.baseUrl 
                                                            + '/descargar?tipo=movilidad&id=' 
                                                            + planilla.documento.id 
                                                            + '&adjunto=' 
                                                            + adj.id" 
                                                        target="_blank"
                                                        rel="noopener"
                                                        class="chip-adjunto"
                                                        :class="{ 'chip-adjunto-xml': adj.tipo_archivo === 'XML' }"
                                                        :title="adj.nombre_archivo">
                                                        <?= icono('clip') ?><span>{{ adj.tipo_archivo === 'XML' ? 'XML: ' : 'Adjunto: ' }}{{ adj.nombre_archivo }}</span>
                                                    </a>
                                                    <span v-if="!it.adjuntos || it.adjuntos.length === 0" class="texto-vacio">Sin adjuntos</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    </div>
                                    <div v-if="datos.adjuntosSueltos.movilidad.length" class="adjuntos-sueltos">
                                        <strong>Adjuntos de la planilla sin viaje asociado</strong>
                                        <a v-for="adj in datos.adjuntosSueltos.movilidad" :key="adj.id"
                                            :href="datos.baseUrl + '/descargar?tipo=movilidad&id=' + planilla.documento.id + '&adjunto=' + adj.id"
                                            target="_blank" rel="noopener" class="chip-adjunto" :title="adj.nombre_archivo">
                                            <?= icono('clip') ?><span>{{ adj.nombre_archivo }}</span>
                                        </a>
                                    </div>
                                    <p v-if="esEditable" class="texto-vacio texto-ayuda-guardar" style="text-align:center;">
                                        <?= icono('clip') ?> El adjunto de movilidad es opcional. Puedes usar PDF o imagen.
                                    </p>
                                </template>
                                <template v-else>
                                    <div v-if="!planilla.documento && datos.planillasDisponibles.length > 0 && modoPlanilla === null" class="acciones-crear">
                                        <button type="button" class="boton boton-chico" @click="modoPlanilla = 'elegir'">Usar una ya creada</button>
                                        <button type="button" class="boton boton-chico" @click="modoPlanilla = 'nueva'">Crear nueva</button>
                                    </div>

                                    <div v-if="modoPlanilla === 'elegir'">
                                        <ul class="lista-planillas">
                                            <li v-for="p in datos.planillasDisponibles" :key="p.id">
                                                <span>Planilla N° {{ numeroDocumento(p.numero) }} - {{ formatearSoles(p.total) }} - {{ p.fecha_emision }}</span>
                                                <button type="button" class="boton boton-chico" @click="enlazarPlanillaExistente(p.id)">Enlazar</button>
                                            </li>
                                        </ul>
                                        <div class="acciones-crear">
                                            <button type="button" class="boton boton-secundario" @click="cancelarPlanilla">Cancelar</button>
                                        </div>
                                    </div>

                                    <div v-else-if="modoPlanilla === 'nueva' || planilla.documento">
                                        <section class="ficha ficha-formulario">
                                            <div class="campo">
                                                <label>Periodo inicio</label>
                                                <input type="date" v-model="planilla.periodoInicio" :min="resumen.periodo_inicio" :max="resumen.periodo_fin" required>
                                            </div>
                                            <div class="campo">
                                                <label>Periodo fin</label>
                                                <input type="date" v-model="planilla.periodoFin" :min="resumen.periodo_inicio" :max="resumen.periodo_fin" required>
                                            </div>
                                            <div class="campo">
                                                <label>Fecha de emisión</label>
                                                <input type="date" v-model="planilla.fecha" :min="planilla.periodoFin" :max="hoy" required>
                                            </div>
                                        </section>

                                        <div class="tabla-scroll tabla-scroll-detalle tabla-scroll-tarjetas">
                                        <table class="tabla tabla-tarjetas tabla-tarjetas-detalle">
                                            <thead>
                                                <tr>
                                                    <th>Item</th>
                                                    <th>Fecha</th>
                                                    <th>Motivo</th>
                                                    <th>Origen</th>
                                                    <th>Destino</th>
                                                    <th>Monto</th>
                                                    <th>Evidencia</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="(it, idx) in planilla.items" :key="idx">
                                                    <td data-label="Item" class="celda-item-num">{{ idx + 1 }}</td>
                                                    <td data-label="Fecha">{{ formatearFecha(it.fecha) }}</td>
                                                    <td data-label="Motivo">{{ it.motivo }}</td>
                                                    <td data-label="Origen">{{ it.origen }}</td>
                                                    <td data-label="Destino">{{ it.destino }}</td>
                                                    <td data-label="Monto" class="celda-monto">{{ formatearSoles(it.monto) }}</td>
                                                    <td data-label="Evidencia">{{ it.tipo_evidencia }}</td>
                                                    <td class="celda-acciones celda-accion-tarjeta">
                                                        <button type="button" class="boton boton-chico" @click="abrirModalItemPlanilla(idx)"><?= icono('editar') ?> Editar</button>
                                                        <button v-if="esEditable" type="button" class="boton boton-chico" @click="quitarItemPlanilla(idx)"><?= icono('quitar') ?> Quitar</button>
                                                        <br><small v-if="it._archivoPendienteNombre">📎 {{ it._archivoPendienteNombre }}</small>
                                                    </td>
                                                </tr>
                                            </tbody>
                                            <tfoot>
                                                <tr class="fila-total-detalle">
                                                    <td colspan="5" class="texto-derecha"><strong>Total</strong></td>
                                                    <td colspan="3"><strong>{{ formatearSoles(totalPlanilla) }}</strong></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                        </div>
                                        <p class="texto-vacio" v-if="planilla.items.length === 0">Todavía no agregaste ningún viaje.</p>

                                        <div v-if="esEditable" class="acciones-edicion-documento-items">
                                            <button type="button" class="boton boton-chico" @click="abrirModalItemPlanilla()"><?= icono('mas') ?> Añadir ítem</button>
                                        </div>
                                        <div class="error" v-if="planilla.error">{{ planilla.error }}</div>
                                        <div class="acciones-edicion-documento-cierre">
                                            <button type="button" class="boton boton-secundario" @click="cancelarPlanilla">Cancelar</button>
                                            <button type="button" class="boton boton-primario" :disabled="planilla.guardando" @click="guardarPlanilla">
                                                {{ planilla.guardando ? 'Guardando…' : 'Guardar' }}
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </td>
                        </tr>
                    </template>

                    <tr class="fila-vacia-documentos" v-if="!contable.documento && !contable.editando && !noContable.documento && !noContable.editando && !planilla.documento && !planilla.editando">
                        <td colspan="<?= $mostrarColumnaAcciones ? 6 : 5 ?>" class="texto-vacio">Todavía no añadiste ningún documento a este Resumen.</td>
                    </tr>
                </tbody>
                <tfoot v-if="contable.documento || noContable.documento || planilla.documento">
                    <tr class="fila-totales">
                        <td colspan="4" class="texto-derecha">Total general</td>
                        <td>{{ formatearSoles(totalGeneral) }}</td>
                        <?php if ($mostrarColumnaAcciones): ?>
                            <td class="celda-total-vacia"></td>
                        <?php endif; ?>
                    </tr>
                </tfoot>
            </table>
            </div>

            <div v-if="esEditable && tiposFaltantes.length && !mostrarSelectorTipo" class="acciones-crear">
                <button
                    type="button"
                    class="boton boton-primario"
                    :disabled="hayPanelesEnEdicion || cabeceraEditando || procesando"
                    title="Guarda o cancela la edición actual antes de añadir otro documento."
                    @click="iniciarAgregarDocumento">
                    <?= icono('mas') ?> Añadir documento
                </button>
            </div>

            <div v-if="mostrarSelectorTipo" class="selector-tipo-documento">
                <p>¿Qué documento deseas añadir?</p>
                <button v-for="t in tiposFaltantes" :key="t.clave" type="button" class="boton boton-chico" @click="elegirTipoNuevo(t.clave)">{{ t.etiqueta }}</button>
                <button type="button" class="boton boton-chico" @click="mostrarSelectorTipo = false">Cancelar</button>
            </div>
        </section>

        <div class="acciones-crear" v-if="esEditable">
            <button
                type="button"
                class="boton boton-primario"
                :disabled="!puedeEnviar"
                :title="hayPanelesEnEdicion ? 'Guarda o cancela el documento en edición' : (cabeceraEditando ? 'Guarda o cancela edición principal' : '')"
                @click="enviarAAprobacion">
                <span v-if="enviando" class="spinner"></span>
                {{ enviando ? 'Enviando...' : (resumenEstado === 'RECHAZADO' ? 'Reenviar a aprobación' : 'Enviar a aprobación') }}
            </button>
        </div>

        <?php if ($modo === 'APROBACION' && $resumen['estado_general'] === 'RECHAZADO'): ?>
            <section class="ficha caja-motivo resolver-aprobacion">
                <div class="resolver-aprobacion-texto">
                    <h2>Revisión de observación</h2>
                    <p><?= nl2br(htmlspecialchars($resumen['motivo_rechazo'] ?? '')) ?></p>
                    <p class="texto-vacio">Confirma tu revisión.</p>
                </div>
                <div class="resolver-aprobacion-acciones">
                    <button type="button" id="botonContinuarRechazo" class="boton boton-primario"><?= icono('check') ?> Revisado</button>
                </div>
            </section>
            <dialog id="dialogoContinuarRechazo" class="dialogo-confirmacion">
                <h2>Continuar devolución</h2>
                <p>El Resumen avanzará al siguiente nivel de retorno o llegará al solicitante.</p>
                <form method="post" action="<?= BASE_URL ?>/continuar-cascada-rechazo">
                    <input type="hidden" name="resumen_id" value="<?= (int)$resumen['id'] ?>">
                    <div class="acciones-modal">
                        <button type="button" data-cerrar-dialogo class="boton boton-secundario">Cancelar</button>
                        <button type="submit" class="boton boton-primario">Confirmar y continuar</button>
                    </div>
                </form>
            </dialog>
        <?php elseif ($modo === 'APROBACION'): ?>
            <section class="ficha resolver-aprobacion">
                <div class="resolver-aprobacion-texto">
                    <h2>Resolver aprobación</h2>
                    <p class="texto-vacio">Revisa el detalle, los adjuntos, el historial y el PDF antes de decidir.</p>
                </div>
                <div class="resolver-aprobacion-acciones">
                    <button type="button" id="botonDevolverResumen" class="boton boton-peligro" :disabled="hayPanelesEnEdicion || procesando" :title="hayPanelesEnEdicion ? 'Guarda o cancela la edición en curso antes de continuar' : ''"><?= icono('quitar') ?> Devolver para corrección</button>
                    <button type="button" id="botonAprobarResumen" class="boton boton-primario" :disabled="hayPanelesEnEdicion || procesando" :title="hayPanelesEnEdicion ? 'Guarda o cancela la edición en curso antes de continuar' : ''"><?= icono('check') ?> Aprobar y continuar</button>
                </div>
            </section>

            <dialog id="dialogoAprobarResumen" class="dialogo-confirmacion">
                <h2>Aprobar y continuar</h2>
                <p>Se pasará al siguiente nivel de revisión.</p>
                <form method="post" action="<?= BASE_URL ?>/aprobar-resumen">
                    <input type="hidden" name="resumen_id" value="<?= (int)$resumen['id'] ?>">
                    <div class="acciones-modal">
                        <button type="button" data-cerrar-dialogo class="boton boton-secundario">Cancelar</button>
                        <button type="submit" class="boton boton-primario">Confirmar aprobación</button>
                    </div>
                </form>
            </dialog>

            <dialog id="dialogoDevolverResumen" class="dialogo-confirmacion">
                <h2>Devolver para corrección</h2>
                <form method="post" action="<?= BASE_URL ?>/rechazar-resumen">
                    <input type="hidden" name="resumen_id" value="<?= (int)$resumen['id'] ?>">
                    <label for="motivoDevolucion">Indica qué debe corregir el solicitante</label>
                    <textarea name="motivo" id="motivoDevolucion" required></textarea>
                    <div class="acciones-modal">
                        <button type="button" data-cerrar-dialogo class="boton boton-secundario">Cancelar</button>
                        <button type="submit" class="boton boton-peligro">Confirmar devolución</button>
                    </div>
                </form>
            </dialog>
        <?php endif; ?>
        
        <dialog ref="refModalHistorial" class="dialogo-historial">
            <h2><?= icono('ver') ?> Historial del Resumen</h2>
            <ol class="linea-tiempo">
                <li v-for="ev in datos.eventos" :key="ev.fecha + ev.evento" :class="'evento-' + ev.evento.toLowerCase()">
                    <span class="linea-punto"></span>
                    <div class="linea-contenido">
                        <p class="linea-tiempo-titulo">
                            {{ ev.evento.replaceAll('_', ' ') }}
                            <span v-if="ev.nivel" class="texto-vacio"> — {{ ev.nivel }}</span>
                        </p>
                        <p class="linea-tiempo-meta">{{ formatearFecha(ev.fecha) }} · {{ ev.usuario }}</p>
                        <p v-if="ev.motivo" class="texto-vacio linea-tiempo-motivo">{{ ev.motivo }}</p>
                    </div>
                </li>
            </ol>
            <div class="acciones-modal">
                <button type="button" class="boton boton-primario" @click="historialAbierto = false">Cerrar</button>
            </div>
        </dialog>

        <dialog ref="refModalItemContable" @cancel.prevent="cancelarModalItemContable">
            <form @submit.prevent="guardarItemContable">
                <h2>{{ editandoIdxContable !== null ? 'Editar comprobante' : 'Nuevo comprobante' }}</h2>
                <label>Concepto</label>
                <input type="text" v-model="nuevoItemContable.concepto" spellcheck="true" required>

                <label>Fecha de emisión</label>
                <input type="date" v-model="nuevoItemContable.fecha" :min="contable.periodoInicio" :max="contable.periodoFin" @change="alCambiarDatosCpe" required>

                <label>Tipo de comprobante</label>
                <select v-model="nuevoItemContable.tipo_comprobante_id" @change="alCambiarDatosCpe" required>
                    <option value="">-- Selecciona --</option>
                    <option v-for="tc in datos.tiposComprobante" :key="tc.id" :value="tc.id">{{ tc.nombre }}</option>
                </select>

                <label>Serie</label>
                <input type="text" v-model="nuevoItemContable.serie" maxlength="10" placeholder="F001 o 001" @input="alCambiarDatosCpe" required>

                <label>Número</label>
                <input type="text" v-model="nuevoItemContable.numero_comprobante" maxlength="8" inputmode="numeric" placeholder="00012345" @input="alCambiarDatosCpe" required>

                <label>Monto</label>
                <input type="number" step="0.01" min="0.01" max="50000" v-model="nuevoItemContable.monto" @input="alCambiarDatosCpe" required>

                <label>RUC del proveedor</label>
                <div class="campo-ruc">
                    <input type="text" v-model="nuevoItemContable.proveedor_ruc" maxlength="11" inputmode="numeric" @input="alCambiarRucContable">
                    <button type="button" class="boton boton-chico" :disabled="verificandoRuc" @click="verificarRuc">
                        {{ verificandoRuc ? 'Verificando…' : 'Verificar RUC' }}
                    </button>
                </div>
                <p class="texto-vacio" v-if="nuevoItemContable.proveedor_estado">
                    Estado: {{ nuevoItemContable.proveedor_estado }} · Condición: {{ nuevoItemContable.proveedor_condicion }}
                </p>

                <label>Razón social</label>
                <input type="text" v-model="nuevoItemContable.proveedor_razon_social">

                <div v-if="requiereVerificacionCpe" class="bloque-verificacion-cpe">
                    <div class="bloque-verificacion-cpe-cabecera">
                        <span>Validación de comprobante (SUNAT)</span>
                        <button type="button" class="boton boton-chico" :disabled="verificandoCpe" @click="verificarComprobante">
                            {{ verificandoCpe ? 'Consultando SUNAT…' : 'Verificar comprobante' }}
                        </button>
                    </div>
                    <div v-if="nuevoItemContable.cpe_estado_sunat" class="resultado-cpe" :class="cpeEsValido ? 'resultado-cpe-ok' : 'resultado-cpe-error'">
                        <strong>{{ cpeEsValido ? '✓ Comprobante válido ante SUNAT' : '✕ Comprobante no cumple validación' }}</strong>
                        <span>Comprobante: <b>{{ nuevoItemContable.cpe_estado_sunat }}</b></span>
                        <span>RUC (a la emisión): <b>{{ nuevoItemContable.cpe_estado_ruc || '-' }}</b></span>
                        <span>Domicilio (a la emisión): <b>{{ nuevoItemContable.cpe_condicion_ruc || '-' }}</b></span>
                        <small v-for="(obs, idxObs) in observacionesCpe" :key="idxObs">Obs.: {{ obs }}</small>
                    </div>
                    <p v-else class="texto-vacio texto-cpe-pendiente">
                        Requiere verificación estricta SUNAT (Aceptado, Activo y Habido). La constancia se anexará automáticamente al PDF.
                    </p>
                </div>

                <template v-if="editandoIdxContable !== null && adjuntosItemEditandoContable.length">
                    <label>Adjuntos guardados</label>
                    <div v-for="adj in adjuntosItemEditandoContable" :key="adj.id" class="adjunto-seleccionado-fila">
                        <span>{{ adj.tipo_archivo === 'XML' ? '📄 XML: ' : '📎 ' }}{{ adj.nombre_archivo }}</span>
                        <button type="button" class="boton-quitar-adjunto-modal" title="Quitar adjunto" @click="quitarAdjuntoExistenteContable(adj.id)">✕</button>
                    </div>
                </template>

                <label>Sustento (PDF o Imagen)</label>
                <input type="file" ref="refArchivoContable" multiple accept="application/pdf,image/png,image/jpeg,.pdf,.png,.jpg,.jpeg" @change="agregarEvidenciasContable">
                <div class="adjunto-seleccionado-fila" v-for="(arch, i) in archivoNuevoItemContable" :key="i">
                    <span>📎 {{ arch.name }}</span>
                    <button type="button" class="boton-quitar-adjunto-modal" title="Quitar archivo" @click="limpiarArchivoNuevoContable(i)">✕</button>
                </div>

                <label>Archivo XML</label>
                <input type="file" ref="refXmlContable" :disabled="!!xmlNuevoItemContable || (editandoIdxContable !== null && adjuntosItemEditandoContable.some(a => a.tipo_archivo === 'XML'))" accept="application/xml,text/xml,.xml" @change="xmlNuevoItemContable = $event.target.files[0] || null">
                <div class="adjunto-seleccionado-fila" v-if="xmlNuevoItemContable">
                    <span>📄 {{ xmlNuevoItemContable.name }}</span>
                    <button type="button" class="boton-quitar-adjunto-modal" title="Quitar XML" @click="limpiarXmlNuevoContable">✕</button>
                </div>
                <p class="error" v-if="mensajeValidacionItemContable">{{ mensajeValidacionItemContable }}</p>
                <div class="acciones-modal">
                    <button type="button" class="boton boton-secundario" @click="cancelarModalItemContable">Cancelar</button>
                    <button type="submit" class="boton boton-primario" :disabled="!puedeGuardarItemContable">{{ editandoIdxContable !== null ? 'Guardar cambios' : 'Añadir' }}</button>
                </div>

            </form>
        </dialog>

        <dialog ref="refModalItemNoContable" @cancel.prevent="cancelarModalItemNoContable">
            <form @submit.prevent="guardarItemNoContable">
                <h2>{{ editandoIdxNoContable !== null ? 'Editar item' : 'Nuevo item' }}</h2>
                <label>Tipo de documento</label>
                <select v-model="nuevoItemNoContable.tipo_documento_id" required>
                    <option value="">-- Selecciona --</option>
                    <option v-for="td in datos.tiposDocumentoNoContable" :key="td.id" :value="td.id">{{ td.nombre }}</option>
                </select>

                <label>Descripción</label>
                <input type="text" v-model="nuevoItemNoContable.descripcion" spellcheck="true" required>

                <label>Fecha de emisión</label>
                <input type="date" v-model="nuevoItemNoContable.fecha_documento" :min="noContable.periodoInicio" :max="noContable.periodoFin" required>

                <label>Monto</label>
                <input type="number" step="0.01" min="0.01" max="50000" v-model="nuevoItemNoContable.monto" required>

                <label>Número de páginas (opcional)</label>
                <input type="number" min="1" v-model="nuevoItemNoContable.numero_paginas">

                <label>Observaciones (opcional)</label>
                <input type="text" v-model="nuevoItemNoContable.observaciones" spellcheck="true">

                <template v-if="editandoIdxNoContable !== null && adjuntosItemEditandoNoContable.length">
                    <label>Adjuntos guardados</label>
                    <div v-for="adj in adjuntosItemEditandoNoContable" :key="adj.id" class="adjunto-seleccionado-fila">
                        <span>{{ adj.tipo_archivo === 'XML' ? '📄 XML: ' : '📎 ' }}{{ adj.nombre_archivo }}</span>
                        <button type="button" class="boton-quitar-adjunto-modal" title="Quitar adjunto" @click="quitarAdjuntoExistenteNoContable(adj.id)">✕</button>
                    </div>
                </template>

                <label>Sustento (PDF o Imagen)</label>
                <input type="file" ref="refArchivoNoContable" multiple accept="application/pdf,image/png,image/jpeg,.pdf,.png,.jpg,.jpeg" @change="agregarEvidenciasNoContable">
                <div class="adjunto-seleccionado-fila" v-for="(arch, i) in archivoNuevoItemNoContable" :key="i">
                    <span>📎 {{ arch.name }}</span>
                    <button type="button" class="boton-quitar-adjunto-modal" title="Quitar archivo" @click="limpiarArchivoNuevoNoContable(i)">✕</button>
                </div>

                <label>Archivo XML (Opcional)</label>
                <input type="file" ref="refXmlNoContable" :disabled="!!xmlNuevoItemNoContable || (editandoIdxNoContable !== null && adjuntosItemEditandoNoContable.some(a => a.tipo_archivo === 'XML'))" accept="application/xml,text/xml,.xml" @change="xmlNuevoItemNoContable = $event.target.files[0] || null">
                <div class="adjunto-seleccionado-fila" v-if="xmlNuevoItemNoContable">
                    <span>📄 {{ xmlNuevoItemNoContable.name }}</span>
                    <button type="button" class="boton-quitar-adjunto-modal" title="Quitar XML" @click="limpiarXmlNuevoNoContable">✕</button>
                </div>

                <div class="acciones-modal">
                    <button type="button" class="boton boton-secundario" @click="cancelarModalItemNoContable">Cancelar</button>
                    <button type="submit" class="boton boton-primario">{{ editandoIdxNoContable !== null ? 'Guardar cambios' : 'Añadir' }}</button>
                </div>
            </form>
        </dialog>

        <dialog ref="refModalItemPlanilla" @cancel.prevent="cancelarModalItemPlanilla">
            <form @submit.prevent="guardarItemPlanilla">
                <h2>{{ editandoIdxPlanilla !== null ? 'Editar viaje' : 'Nuevo viaje' }}</h2>
                <label>Fecha de emisión</label>
                <input type="date" v-model="nuevoItemPlanilla.fecha" :min="planilla.periodoInicio" :max="planilla.periodoFin" required>

                <label>Motivo</label>
                <input type="text" v-model="nuevoItemPlanilla.motivo" spellcheck="true" required>

                <label>Origen</label>
                <input type="text" v-model="nuevoItemPlanilla.origen" spellcheck="true" required>

                <label>Destino</label>
                <input type="text" v-model="nuevoItemPlanilla.destino" spellcheck="true" required>

                <label>Monto</label>
                <input type="number" step="0.01" min="0.01" max="50000" v-model="nuevoItemPlanilla.monto" required>

                <label>Evidencia</label>
                <select v-model="nuevoItemPlanilla.tipo_evidencia">
                    <option value="NINGUNO">Ninguna</option>
                    <option value="YAPE">Yape</option>
                    <option value="PLIN">Plin</option>
                    <option value="TRANSFERENCIA">Transferencia</option>
                </select>

                <label>Observación de evidencia (opcional)</label>
                <input type="text" v-model="nuevoItemPlanilla.observacion_evidencia" spellcheck="true">

                <template v-if="editandoIdxPlanilla !== null && adjuntosItemEditandoPlanilla.length">
                    <label>Adjuntos guardados</label>
                    <div v-for="adj in adjuntosItemEditandoPlanilla" :key="adj.id" class="adjunto-seleccionado-fila">
                        <span>📎 {{ adj.nombre_archivo }}</span>
                        <button type="button" class="boton-quitar-adjunto-modal" title="Quitar adjunto" @click="quitarAdjuntoExistentePlanilla(adj.id)">✕</button>
                    </div>
                </template>

                <label>Adjunto (PDF o Imagen)</label>
                <input type="file" ref="refArchivoPlanilla" multiple accept="application/pdf,image/png,image/jpeg,.pdf,.png,.jpg,.jpeg" @change="agregarEvidenciasPlanilla">
                <div class="adjunto-seleccionado-fila" v-for="(arch, i) in archivoNuevoItemPlanilla" :key="i">
                    <span>📎 {{ arch.name }}</span>
                    <button type="button" class="boton-quitar-adjunto-modal" title="Quitar archivo" @click="limpiarArchivoNuevoPlanilla(i)">✕</button>
                </div>

                <div class="acciones-modal">
                    <button type="button" class="boton boton-secundario" @click="cancelarModalItemPlanilla">Cancelar</button>
                    <button type="submit" class="boton boton-primario">{{ editandoIdxPlanilla !== null ? 'Guardar cambios' : 'Añadir' }}</button>
                </div>
            </form>
        </dialog>

        <!-- Modal de confirmación -->
        <dialog ref="refDialogoConfirmar" class="dialogo-confirmacion">
            <div class="aviso-cabecera">
                <span class="aviso-icono" :class="{ 'aviso-icono-normal' : dialogoConfirmar.tipo !== 'peligro' }">
                    <?= icono('alerta') ?>
                </span>
                <h2>{{ dialogoConfirmar.titulo }}</h2>
            </div>
            <p>{{ dialogoConfirmar.mensaje }}</p>
            <div class="acciones-modal">
                <button type="button" class="boton boton-secundario" @click="cancelarConfirmacion">Cancelar</button>
                <button type="button" :class="['boton', dialogoConfirmar.tipo === 'peligro' ? 'boton-peligro' : 'boton-primario']" @click="confirmarAccion">
                    {{ dialogoConfirmar.textoConfirmar }}
                </button>
            </div>
        </dialog>

        <!-- Modal de aviso -->
        <dialog ref="refDialogoAviso" class="dialogo-aviso">
            <div class="aviso-cabecera">
                <span class="aviso-icono"><?= icono('alerta') ?></span>
                <h2>{{ dialogoAviso.titulo }}</h2>
            </div>
            <p>{{ dialogoAviso.mensaje }}</p>
            <div class="acciones-modal">
                <button type="button" class="boton boton-primario" @click='cerrarAviso'>Entendido</button>
            </div>
        </dialog>

        <!-- Vista previa: solo lectura, para que el usuario vea de un
             vistazo cómo quedaría el Resumen antes de enviarlo — no edita
             nada, así que no necesita ser una sección más entre los botones. -->
        <dialog ref="refModalVistaPrevia" class="dialogo-vista-previa">
            <h2><?= icono('ver') ?> Vista previa de Resumen de Caja Chica</h2>

            <div class="tabs-vista-previa">
                <button type="button" :class="{ activo: tabVistaPrevia === 'sistema' }" @click="tabVistaPrevia = 'sistema'">Vista del sistema</button>
                <button type="button" :class="{ activo: tabVistaPrevia === 'pdf' }" @click="tabVistaPrevia = 'pdf'">Vista previa del PDF</button>
            </div>

            <div class="tabla-scroll tabla-scroll-vertical" v-if="tabVistaPrevia === 'sistema'">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Tipo de Documento</th>
                        <th>Ingresos</th>
                        <th>Egresos</th>
                        <th>Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(f, idx) in filasVistaPrevia" :key="idx">
                        <td>{{ f.numero }}</td>
                        <td>{{ f.etiqueta }}</td>
                        <td>{{ formatearSoles(f.ingreso) }}</td>
                        <td>{{ formatearSoles(f.egreso) }}</td>
                        <td>{{ formatearSoles(f.saldo) }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="fila-totales">
                        <td colspan="2">TOTALES</td>
                        <td>{{ formatearSoles(resumen.saldo_inicial) }}</td>
                        <td>{{ formatearSoles(totalGeneral) }}</td>
                        <td>{{ formatearSoles(saldoRestante) }}</td>
                    </tr>
                </tfoot>
            </table>
            </div>

            <template v-if="tabVistaPrevia === 'pdf'">
                <iframe :src="datos.baseUrl + '/exportar-resumen-pdf?id=' + datos.resumenId" class="iframe-pdf"></iframe>
                <div class="vista-previa-pdf-movil">
                    <p>En móvil el PDF se visualiza mejor directamente en el visor de tu dispositivo.</p>
                    <a :href="datos.baseUrl + '/exportar-resumen-pdf?id=' + datos.resumenId" target="_blank" rel="noopener" class="boton boton-primario">
                        <?= icono('ver') ?> Abrir PDF completo
                    </a>
                </div>
            </template>

            <div class="acciones-modal">
                <button type="button" class="boton boton-primario" @click="mostrarVistaPrevia = false">Cerrar</button>
            </div>
        </dialog>
    </div>

    <p class="cargando-vista" role="status"><span class="spinner" aria-hidden="true"></span> Cargando resumen…</p>
    <noscript><p>Activa JavaScript para consultar el detalle del resumen.</p></noscript>
    <script>
        window.DATOS_INICIALES = <?= json_encode([
                                        'baseUrl' => BASE_URL,
                                        'hoy' => date('Y-m-d'),
                                        'resumenId' => $resumen['id'],
                                        'esEditable' => $esEditable,
                                        'puedeEditarItems' => $esEditable || $puedeEditarItems,
                                        'esEdicionContabilidad' => !$esEditable && $puedeEditarItems,
                                        'resumenEstado' => $resumen['estado_general'],
                                        'resumen' => [
                                            'periodo_inicio' => $resumen['periodo_inicio'],
                                            'periodo_fin' => $resumen['periodo_fin'],
                                            'fecha_emision' => $resumen['fecha_emision'],
                                            'saldo_inicial' => (float)$resumen['saldo_inicial'],
                                            'empresa' => $resumen['empresa'],
                                            'nivel_actual_nombre' => $resumen['nivel_actual_nombre'],
                                            'monto_asignado' => $resumen['monto_asignado'] !== null ? (float)$resumen['monto_asignado'] : null,
                                            'total_egresos' => (float)$resumen['total_egresos'],
                                            'numero' => numeroDocumento((int)$resumen['numero']),
                                            'responsable' => $resumen['nombres'],
                                            'dni' => $resumen['dni'],
                                            'area_nombre' => $resumen['area_nombre'],
                                        ],
                                        'tiposComprobante' => $tiposComprobante,
                                        'documentoContable' => $documentoContable,
                                        'itemsDocumentoContable' => $itemsDocumentoContable,
                                        'tiposDocumentoNoContable' => $tiposDocumentoNoContable,
                                        'documentoNoContable' => $documentoNoContable,
                                        'itemsDocumentoNoContable' => $itemsDocumentoNoContable,
                                        'planilla' => $planilla,
                                        'itemsPlanilla' => $itemsPlanilla,
                                        'adjuntosSueltos' => $adjuntosSueltos,
                                        'planillasDisponibles' => $planillasDisponibles,
                                        'eventos' => $eventos,
                                    ]) ?>
    </script>
    <script src="<?= BASE_URL ?>/js/vendor/vue.global.prod.js"></script>
    <script type="module" src="<?= assetUrl('/js/ver_resumen.js') ?>"></script>
    <?php if ($modo === 'APROBACION'): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const enlazarDialogo = (botonId, dialogoId) => {
                    const boton = document.getElementById(botonId);
                    const dialogo = document.getElementById(dialogoId);
                    if (!boton || !dialogo) return;
                    boton.addEventListener('click', () => dialogo.showModal());
                    dialogo.querySelectorAll('[data-cerrar-dialogo]').forEach((cerrar) => {
                        cerrar.addEventListener('click', () => dialogo.close());
                    });
                    dialogo.querySelector('form').addEventListener('submit', (evento) => {
                        const formulario = evento.currentTarget;
                        if (formulario.dataset.enviando) {
                            evento.preventDefault();
                            return;
                        }
                        formulario.dataset.enviando = '1';
                        formulario.querySelectorAll('button').forEach(boton => { boton.disabled = true; });
                        formulario.querySelector('button[type="submit"]').textContent = 'Procesando…';
                    });
                };
                enlazarDialogo('botonContinuarRechazo', 'dialogoContinuarRechazo');
                enlazarDialogo('botonAprobarResumen', 'dialogoAprobarResumen');
                enlazarDialogo('botonDevolverResumen', 'dialogoDevolverResumen');
            });
        </script>
    <?php endif; ?>
<?php
    cierreLayout();
}
