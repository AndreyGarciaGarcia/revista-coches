<?php

$servidor = "localhost";
$usuario = "root";
$contraseña = "PON_AQUI_TU_CONTRASEÑA";
$basedatos = "revista_coches";

$conexion = new mysqli($servidor, $usuario, $contraseña, $basedatos);

if ($conexion->connect_error) {
    die("Error de conexion: " . $conexion->connect_error);
}

$conexion->set_charset("utf8mb4");
