<?php
include "config/conexion.php";
include "includes/cabecera.php";

$sql = "SELECT n.titulo, n.slug, n.resumen, n.imagen, n.categoria_id, c.nombre AS categoria
        FROM noticias n
        JOIN categorias c ON n.categoria_id = c.id
        WHERE n.publicada = TRUE
        ORDER BY n.fecha_publicacion DESC";

$resultado = $conexion->query($sql);
?>

<h2>Últimas noticias</h2>

<div class="noticias">
    <?php while ($noticia = $resultado->fetch_assoc()) { ?>
        <div class="tarjeta">
            <small>
                <a href="paginas/categoria.php?id=<?php echo $noticia["categoria_id"]; ?>">
                    <?php echo htmlspecialchars($noticia["categoria"]); ?>
                </a>
            </small>
            <h2>
                <a href="/mis-proyectos-php/proyectos/revista-coches/paginas/noticia.php?slug=<?php echo urlencode($noticia["slug"]); ?>">
                    <?php echo htmlspecialchars($noticia["titulo"]); ?>
                </a>
            </h2>
            <p><?php echo htmlspecialchars($noticia["resumen"]); ?></p>
        </div>
    <?php } ?>
</div>

<?php include "includes/pie.php"; ?>