<?php
session_start();

if (!isset($_SESSION["autor_id"]) || $_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: login.php");
    exit;
}

include "../config/conexion.php";

$id = (int) ($_POST["id"] ?? 0);

$consulta = $conexion->prepare("SELECT imagen FROM coches WHERE id = ?");
$consulta->bind_param("i", $id);
$consulta->execute();
$coche = $consulta->get_result()->fetch_assoc();

if ($coche) {
    $consulta = $conexion->prepare("DELETE FROM noticia_coche WHERE coche_id = ?");
    $consulta->bind_param("i", $id);
    $consulta->execute();

    $consulta = $conexion->prepare("DELETE FROM coches WHERE id = ?");
    $consulta->bind_param("i", $id);
    $consulta->execute();

    if ($coche["imagen"]) {
        $ruta = "../assets/img/coches/" . basename($coche["imagen"]);
        if (file_exists($ruta)) {
            unlink($ruta);
        }
    }
}

header("Location: panel.php");
exit;
