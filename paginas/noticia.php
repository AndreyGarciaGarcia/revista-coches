<?php
include "../config/conexion.php";
include "../includes/cabecera.php";

$slug = $_GET["slug"] ?? "";

$sql = "SELECT n.titulo, n.contenido, n.fecha_publicacion,
               c.nombre AS categoria, a.nombre AS autor
        FROM noticias n
        JOIN categorias c ON n.categoria_id = c.id
        JOIN autores a ON n.autor_id = a.id
        WHERE n.slug = ? AND n.publicada = TRUE";

$consulta = $conexion->prepare($sql);
$consulta->bind_param("s", $slug);
$consulta->execute();
$noticia = $consulta->get_result()->fetch_assoc();
?>

<?php if ($noticia) { ?>
    <article class="noticia">
        <small><?php echo htmlspecialchars($noticia["categoria"]); ?></small>
        <h2><?php echo htmlspecialchars($noticia["titulo"]); ?></h2>
        <p class="autor">
            Por <?php echo htmlspecialchars($noticia["autor"]); ?> ·
            <?php echo date("d/m/Y", strtotime($noticia["fecha_publicacion"])); ?>
        </p>
        <p><?php echo nl2br(htmlspecialchars($noticia["contenido"])); ?></p>
    </article>
<?php } else { ?>
    <p>Noticia no encontrada.</p>
<?php } ?>

<?php include "../includes/pie.php"; ?>