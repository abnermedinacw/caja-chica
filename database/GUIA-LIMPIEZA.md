# Guía de limpieza de datos — Caja Chica

> Objetivo: borrar datos de prueba/transaccionales y **conservar los maestros**
> (trabajadores, áreas, niveles de aprobación, empresas, usuarios).
>
> **Haz un respaldo antes de ejecutar:**
>
> ```powershell
> & C:\xampp\mysql\bin\mysqldump.exe -u root caja_chica >
>   backups\antes_limpieza.sql
> ```

---

## 1. Tablas que se conservan (maestros y configuración)

| Tabla | Qué contiene | Por qué se conserva |
|---|---|---|
| `empresa` | Empresas/matrices y cuenta BBVA | Base de todo el sistema |
| `empresa_cliente` | Clientes | `trabajador.empresa_cliente_id` las referencia |
| `area` | Áreas | `trabajador.area_id` las referencia |
| `cargo` | Cargos | `trabajador.cargo_id` los referencia |
| `trabajador` | Personal | Se conserva según lo indicado |
| `usuario` | Cuentas de acceso | `trabajador.jefe_usuario_id` las referencia y permiten iniciar sesión |
| `nivel_aprobacion` | Flujo de aprobación | Configuración del circuito |
| `tipo_comprobante` | Catálogo | Tipos de comprobante |
| `tipo_documento_no_contable` | Catálogo | Tipos de documento no contable |
| `configuracion` | Parámetros | Ej. `dia_cierre_mensual` |

> `usuario` se conserva porque `trabajador.jefe_usuario_id` apunta a ella.
> Si se vaciara, las jefaturas quedarían rotas y nadie podría iniciar sesión.

---

## 2. Tablas que se vacían (movimiento / adjuntos)

| Tabla | Qué contiene |
|---|---|
| `caja_chica` | Rendiciones / cajas |
| `evento_caja_chica` | Historial de aprobaciones y eventos |
| `fondo` | Fondos asignados |
| `comprobante` | Comprobantes de gasto |
| `documento_contable` | Documentos contables |
| `documento_contable_detalle` | Detalle de documentos contables |
| `documento_no_contable` | Documentos no contables |
| `documento_no_contable_detalle` | Detalle de documentos no contables |
| `planilla_movilidad` | Planillas de movilidad |
| `planilla_movilidad_detalle` | Detalle de planillas de movilidad |
| `adjunto_caja_chica` | Adjuntos de cajas |
| `adjunto_documento_contable` | Adjuntos de documentos contables |
| `adjunto_documento_no_contable` | Adjuntos de documentos no contables |
| `adjunto_movilidad` | Adjuntos de movilidad |
| `firma_digital` | Firmas cargadas (opcional: quítala de la lista si quieres conservarlas) |

---

## 3. Sentencias de limpieza

```sql
SET FOREIGN_KEY_CHECKS = 0;

-- 3.1 Adjuntos primero (hijos)
TRUNCATE TABLE adjunto_caja_chica;
TRUNCATE TABLE adjunto_documento_contable;
TRUNCATE TABLE adjunto_documento_no_contable;
TRUNCATE TABLE adjunto_movilidad;

-- 3.2 Eventos y documentos
TRUNCATE TABLE evento_caja_chica;
TRUNCATE TABLE documento_contable_detalle;
TRUNCATE TABLE documento_contable;
TRUNCATE TABLE documento_no_contable_detalle;
TRUNCATE TABLE documento_no_contable;
TRUNCATE TABLE comprobante;

-- 3.3 Planillas y movimientos
TRUNCATE TABLE planilla_movilidad_detalle;
TRUNCATE TABLE planilla_movilidad;
TRUNCATE TABLE caja_chica;
TRUNCATE TABLE fondo;

-- 3.4 Firmas (opcional)
TRUNCATE TABLE firma_digital;

SET FOREIGN_KEY_CHECKS = 1;
```

`TRUNCATE` reinicia los `AUTO_INCREMENT`, así que los nuevos registros
empezarán desde 1.

---

## 4. Limpiar archivos físicos

La BD no borra los archivos de `storage/`. Si vaciaste `adjunto_*` y
`firma_digital`, borra también los archivos:

```powershell
Get-ChildItem storage\uploads -Recurse -File | Remove-Item -Force
Get-ChildItem storage\logs -File | Remove-Item -Force
Get-ChildItem storage\correo -File | Remove-Item -Force
```

---

## 5. Verificación

```sql
SELECT 'caja_chica' AS tabla, COUNT(*) AS filas FROM caja_chica
UNION ALL SELECT 'evento_caja_chica', COUNT(*) FROM evento_caja_chica
UNION ALL SELECT 'fondo', COUNT(*) FROM fondo
UNION ALL SELECT 'comprobante', COUNT(*) FROM comprobante
UNION ALL SELECT 'documento_contable', COUNT(*) FROM documento_contable
UNION ALL SELECT 'documento_no_contable', COUNT(*) FROM documento_no_contable
UNION ALL SELECT 'adjunto_caja_chica', COUNT(*) FROM adjunto_caja_chica
UNION ALL SELECT 'trabajador (conservado)', COUNT(*) FROM trabajador
UNION ALL SELECT 'usuario (conservado)', COUNT(*) FROM usuario
UNION ALL SELECT 'area (conservado)', COUNT(*) FROM area
UNION ALL SELECT 'nivel_aprobacion (conservado)', COUNT(*) FROM nivel_aprobacion;
```

---

## 6. Notas

- `es_jefe` ya fue eliminada de `trabajador`; el sistema la calcula desde
  `jefe_usuario_id`. En cPanel solo hay que aplicar el `ALTER` correspondiente.
- No se toca `nivel_aprobacion.aprobador_usuario_id` porque los usuarios se
  conservan.
- Si quieres conservar las firmas, elimina `TRUNCATE TABLE firma_digital;`
  y no borres `storage/uploads/firmas/`.
