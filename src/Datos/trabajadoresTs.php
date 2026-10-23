<?php
require_once __DIR__ . '/../../config/databaseTs.php';

function obtenerTrabajadorTsPorDni(string $dni): ?array
{
	$pdoTs = obtenerConexionTs();
	$stmt = $pdoTs->prepare(
		"SELECT DISTINCT
		per.per_vcDocumento AS dni,  
		per.per_vcNombres AS nombres, 
		cl.cli_vcRuc AS matriz_ruc,
		cl.cli_vcRazonSocial AS matriz_razon_social,
		cli.cli_vcRuc AS cliente_ruc,
		cli.cli_vcRazonSocial AS cliente_razon_social,
		cc.concar_vcNombre AS cargo,
		ca.conarea_vcNombre AS area
		FROM persona AS per
			INNER JOIN planilla_trabajador pt ON pt.per_iId = per.per_iId
			INNER JOIN contrato_planilla cp ON pt.conpla_iId = cp.conpla_iId
			INNER JOIN contrato c ON cp.con_iId = c.con_iId
			INNER JOIN cliente cli ON cli.cli_iId = c.cli_iId
			INNER JOIN contrato_empresa cem ON cem.con_iId = c.con_iId
			INNER JOIN cliente cl ON cl.cli_iId = cem.cli_iId
			INNER JOIN contrato_centrocosto ccc ON c.con_iId = ccc.con_iId
			INNER JOIN cliente_establecimiento ce ON ce.cliest_iId = pt.clieest_iId
			INNER JOIN contrato_cargo cc ON pt.concar_iId = cc.concar_iId
			LEFT JOIN contrato_areas ca on ca.conarea_iId = pt.conarea_iId
		WHERE per.per_vcDocumento = ? AND pt.platra_dRetiro IS NULL"
	);

	$stmt->execute([$dni]);
	$fila = $stmt->fetch();
	return $fila ?: null;
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