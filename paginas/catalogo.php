<?php
include "../config/conexion.php";
include "../includes/cabecera.php";

$marca = $_GET["marca"] ?? "";

$marcas = $conexion->query("SELECT nombre FROM marcas ORDER BY nombre");

$sql = "SELECT c.id, c.modelo, c.anio_lanzamiento, c.tipo_combustible,
               c.potencia_cv, c.precio_desde, c.estado, c.imagen, m.nombre AS marca
        FROM coches c
        JOIN marcas m ON c.marca_id = m.id";

if ($marca != "") {
    $sql .= " WHERE m.nombre = ?";
}

$sql .= " ORDER BY m.nombre, c.modelo";

$consulta = $conexion->prepare($sql);

if ($marca != "") {
    $consulta->bind_param("s", $marca);
}

$consulta->execute();
$resultado = $consulta->get_result();
?>

<h2>Catálogo</h2>

<div class="filtros">
    <a href="catalogo.php">Todas</a>
    <?php while ($m = $marcas->fetch_assoc()) { ?>
        <a href="catalogo.php?marca=<?php echo urlencode($m["nombre"]); ?>">
            <?php echo htmlspecialchars($m["nombre"]); ?>
        </a>
    <?php } ?>
</div>

<?php if ($resultado->num_rows == 0) { ?>
    <p>No hay coches de esta marca.</p>
<?php } ?>

<div class="noticias">
    <?php while ($coche = $resultado->fetch_assoc()) { ?>
        <div class="tarjeta">

            <?php if ($coche["imagen"]) { ?>
                <img src="<?php echo BASE_URL; ?>/assets/img/coches/<?php echo htmlspecialchars($coche["imagen"]); ?>"
                    alt="<?php echo htmlspecialchars($coche["marca"] . " " . $coche["modelo"]); ?>">
            <?php } ?>

            <small><?php echo htmlspecialchars($coche["marca"]); ?></small>
            <h2>
                <a href="coche.php?id=<?php echo $coche["id"]; ?>">
                    <?php echo htmlspecialchars($coche["modelo"]); ?>
                </a>
            </h2>
            <p>
                <?php echo $coche["anio_lanzamiento"]; ?> ·
                <?php echo ucfirst($coche["tipo_combustible"]); ?> ·
                <?php echo $coche["potencia_cv"]; ?> CV
            </p>
            <p><strong>Desde <?php echo number_format($coche["precio_desde"], 0, ",", "."); ?> €</strong></p>
            <?php if ($coche["estado"] == "proximo_lanzamiento") { ?>
                <span class="etiqueta">Próximo lanzamiento</span>
            <?php } ?>
        </div>
    <?php } ?>
</div>

<?php include "../includes/pie.php"; ?>