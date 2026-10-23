<?php
session_start();
require_once __DIR__ . '/../Negocio/auth.php';
cerrarSesion();
header('Location: ' . BASE_URL . '/login');
exit;