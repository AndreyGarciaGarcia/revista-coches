<?php
include "../config/conexion.php";
include "../includes/cabecera.php";

$id = (int) ($_GET["id"] ?? 0);

$consulta = $conexion->prepare("SELECT nombre, descripcion FROM categorias WHERE id = ?");
$consulta->bind_param("i", $id);
$consulta->execute();
$categoria = $consulta->get_result()->fetch_assoc();

if ($categoria) {
    $sql = "SELECT titulo, slug, resumen, imagen
            FROM noticias
            WHERE categoria_id = ? AND publicada = TRUE
            ORDER BY fecha_publicacion DESC";

    $consulta = $conexion->prepare($sql);
    $consulta->bind_param("i", $id);
    $consulta->execute();
    $noticias = $consulta->get_result();
}
?>

<?php if ($categoria) { ?>
    <h2><?php echo htmlspecialchars($categoria["nombre"]); ?></h2>
    <p><?php echo htmlspecialchars($categoria["descripcion"]); ?></p>

    <?php if ($noticias->num_rows == 0) { ?>
        <p>Todavía no hay noticias en esta sección.</p>
    <?php } ?>

    <div class="noticias">
        <?php while ($n = $noticias->fetch_assoc()) { ?>
            <div class="tarjeta">
                <?php if ($n["imagen"]) { ?>
                    <img src="<?php echo BASE_URL; ?>/assets/img/noticias/<?php echo htmlspecialchars($n["imagen"]); ?>"
                        alt="<?php echo htmlspecialchars($n["titulo"]); ?>">
                <?php } ?>
                <h2>
                    <a href="noticia.php?slug=<?php echo urlencode($n["slug"]); ?>">
                        <?php echo htmlspecialchars($n["titulo"]); ?>
                    </a>
                </h2>
                <p><?php echo htmlspecialchars($n["resumen"]); ?></p>
            </div>
        <?php } ?>
    </div>
<?php } else { ?>
    <p>Categoría no encontrada.</p>
<?php } ?>

<?php include "../includes/pie.php"; ?>