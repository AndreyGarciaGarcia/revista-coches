<?php
include "../config/conexion.php";
include "../includes/cabecera.php";

$id = (int) ($_GET["id"] ?? 0);

$sql = "SELECT c.modelo, c.anio_lanzamiento, c.tipo_combustible, c.potencia_cv,
               c.precio_desde, c.estado, c.descripcion, m.nombre AS marca, m.pais
        FROM coches c
        JOIN marcas m ON c.marca_id = m.id
        WHERE c.id = ?";

$consulta = $conexion->prepare($sql);
$consulta->bind_param("i", $id);
$consulta->execute();
$coche = $consulta->get_result()->fetch_assoc();

if ($coche) {
    $sqlNoticias = "SELECT n.titulo, n.slug
                    FROM noticias n
                    JOIN noticia_coche nc ON n.id = nc.noticia_id
                    WHERE nc.coche_id = ? AND n.publicada = TRUE
                    ORDER BY n.fecha_publicacion DESC";

    $consultaNoticias = $conexion->prepare($sqlNoticias);
    $consultaNoticias->bind_param("i", $id);
    $consultaNoticias->execute();
    $noticias = $consultaNoticias->get_result();
}
?>

<?php if ($coche) { ?>
    <article class="noticia">
        <small><?php echo htmlspecialchars($coche["marca"]); ?> · <?php echo htmlspecialchars($coche["pais"]); ?></small>
        <h2><?php echo htmlspecialchars($coche["modelo"]); ?></h2>

        <table class="ficha">
            <tr>
                <th>Año</th>
                <td><?php echo $coche["anio_lanzamiento"]; ?></td>
            </tr>
            <tr>
                <th>Combustible</th>
                <td><?php echo ucfirst($coche["tipo_combustible"]); ?></td>
            </tr>
            <tr>
                <th>Potencia</th>
                <td><?php echo $coche["potencia_cv"]; ?> CV</td>
            </tr>
            <tr>
                <th>Precio desde</th>
                <td><?php echo number_format($coche["precio_desde"], 0, ",", "."); ?> €</td>
            </tr>
        </table>

        <?php if ($coche["estado"] == "proximo_lanzamiento") { ?>
            <span class="etiqueta">Próximo lanzamiento</span>
        <?php } ?>

        <p><?php echo nl2br(htmlspecialchars($coche["descripcion"])); ?></p>

        <?php if ($noticias->num_rows > 0) { ?>
            <h3>Noticias relacionadas</h3>
            <ul>
                <?php while ($n = $noticias->fetch_assoc()) { ?>
                    <li>
                        <a href="noticia.php?slug=<?php echo urlencode($n["slug"]); ?>">
                            <?php echo htmlspecialchars($n["titulo"]); ?>
                        </a>
                    </li>
                <?php } ?>
            </ul>
        <?php } ?>

        <p><a href="catalogo.php">← Volver al catálogo</a></p>
    </article>
<?php } else { ?>
    <p>Coche no encontrado.</p>
<?php } ?>

<?php include "../includes/pie.php"; ?>