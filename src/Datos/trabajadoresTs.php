<?php
require_once __DIR__ . '/../../config/databaseTs.php';

function obtenerTrabajadorTsPorDni(string $dni): ?array
{
	$pdoTs = obtenerConexionTs();
	$stmt = $pdoTs->prepare(
		"SELECT DISTINCT
			pt.platra_iId AS trabajador_ts_id,
			cp.con_iId AS contrato_ts_id,
			pt.conarea_iId AS area_ts_id,
			per.per_vcDocumento AS dni,
			per.per_vcNombres AS nombres,
			matriz.cli_vcRuc AS matriz_ruc,
			matriz.cli_vcRazonSocial AS matriz_razon_social,
			cliente.cli_vcRuc AS cliente_ruc,
			cliente.cli_vcRazonSocial AS cliente_razon_social,
			cc.concar_vcNombre AS cargo,
			ca.conarea_vcNombre AS area
		FROM persona AS per
		INNER JOIN planilla_trabajador pt ON pt.per_iId = per.per_iId
		INNER JOIN contrato_planilla cp ON pt.conpla_iId = cp.conpla_iId
		INNER JOIN contrato c ON cp.con_iId = c.con_iId
		INNER JOIN cliente cliente ON cliente.cli_iId = c.cli_iId
		INNER JOIN contrato_empresa ce ON ce.con_iId = c.con_iId
		INNER JOIN cliente matriz ON matriz.cli_iId = ce.cli_iId
		LEFT JOIN contrato_cargo cc ON pt.concar_iId = cc.concar_iId
		LEFT JOIN contrato_areas ca ON pt.conarea_iId = ca.conarea_iId
		WHERE per.per_vcDocumento = ? AND pt.platra_dRetiro IS NULL"
	);

	$stmt->execute([$dni]);
	$filas = $stmt->fetchAll();
	if (count($filas) > 1 ) throw new DomainException('Hay más de una asignación activa para este DNI. Revisar contrato.');
	return $filas[0] ?? null;
}

function obtenerGruposAreasTs(int $areaId): array
{
	$stmt = obtenerConexionTs()->prepare(
		"SELECT
			g.ca_grupo_iId AS grupo_id,
			g.ca_grupo_vcNombre AS grupo_nombre,
			p.per_vcDocumento AS jefe_dni,
			p.per_vcNombres AS jefe_nombre,
			(j.platra_iId IS NOT NULL AND j.platra_dRetiro IS NULL) AS jefe_activo
		FROM contrato_areas_grupo_detalle d
		JOIN contrato_areas_grupo g ON g.ca_grupo_iId = d.ca_grupo_iId
		LEFT JOIN planilla_trabajador j ON j.platra_iId = g.platra_iid
		LEFT JOIN persona p ON p.per_iId = j.per_iId
		WHERE d.conarea_iId = ?
			AND d.ca_grupo_det_iEstado = 1
			AND g.ca_grupo_iEstado = 1"
	);

	$stmt->execute([$areaId]);
	return $stmt->fetchAll();
}

function obtenerGruposDirigidosTs(int $trabajadorTsId): array
{
	$stmt = obtenerConexionTs()->prepare(
		"SELECT ca_grupo_iId AS id, ca_grupo_vcNombre AS nombre
		FROM contrato_areas_grupo
		WHERE platra_iid = ? AND ca_grupo_iEstado = 1
		ORDER BY ca_grupo_vcNombre"
	);
	$stmt->execute([$trabajadorTsId]);
	return $stmt->fetchAll();
}

function obtenerCuentaBbvaTsPorDni(string $dni): array
{
	$pdoTs = obtenerConexionTs();

	$stmt = $pdoTs->prepare(
		"SELECT DISTINCT
			TRIM(pt.platra_vcCuentaBancaria) AS cuenta_bbva
		FROM persona per
		INNER JOIN planilla_trabajador pt ON pt.per_iId = per.per_iId
		WHERE per.per_vcDocumento = ?
			AND pt.platra_dRetiro IS NULL
			AND pt.sunentfin_vcCodigo = '11'
			AND pt.platra_vcCuentaBancaria IS NOT NULL"
	);

	$stmt->execute([$dni]);
	return array_values(array_unique(array_filter(array_column($stmt->fetchAll(), 'cuenta_bbva'))));
}
