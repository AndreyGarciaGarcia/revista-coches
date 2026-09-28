<?php
session_start();

if (!isset($_SESSION["autor_id"]) || $_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: login.php");
    exit;
}

include "../config/conexion.php";

$id = (int) ($_POST["id"] ?? 0);

$consulta = $conexion->prepare("SELECT imagen FROM noticias WHERE id = ?");
$consulta->bind_param("i", $id);
$consulta->execute();
$noticia = $consulta->get_result()->fetch_assoc();

if ($noticia) {
    $consulta = $conexion->prepare("DELETE FROM noticia_coche WHERE noticia_id = ?");
    $consulta->bind_param("i", $id);
    $consulta->execute();

    $consulta = $conexion->prepare("DELETE FROM noticias WHERE id = ?");
    $consulta->bind_param("i", $id);
    $consulta->execute();

    if ($noticia["imagen"]) {
        $ruta = "../assets/img/noticias/" . basename($noticia["imagen"]);
        if (file_exists($ruta)) {
            unlink($ruta);
        }
    }
}

header("Location: panel.php");
exit;
