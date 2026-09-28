<?php
session_start();

if (!isset($_SESSION["autor_id"])) {
    header("Location: login.php");
    exit;
}

include "../config/conexion.php";

$id = (int) ($_GET["id"] ?? $_POST["id"] ?? 0);
$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $titulo = $_POST["titulo"];
    $resumen = $_POST["resumen"];
    $contenido = $_POST["contenido"];
    $categoria_id = (int) $_POST["categoria_id"];
    $publicada = isset($_POST["publicada"]) ? 1 : 0;

    $sql = "UPDATE noticias
            SET titulo = ?, resumen = ?, contenido = ?, categoria_id = ?, publicada = ?
            WHERE id = ?";

    $consulta = $conexion->prepare($sql);
    $consulta->bind_param("sssiii", $titulo, $resumen, $contenido, $categoria_id, $publicada, $id);
    $consulta->execute();

    $mensaje = "Cambios guardados";
}

$consulta = $conexion->prepare("SELECT * FROM noticias WHERE id = ?");
$consulta->bind_param("i", $id);
$consulta->execute();
$noticia = $consulta->get_result()->fetch_assoc();

$categorias = $conexion->query("SELECT id, nombre FROM categorias ORDER BY nombre");

include "../includes/cabecera.php";
?>

<h2>Editar noticia</h2>

<?php if ($mensaje != "") { ?>
    <p><strong><?php echo $mensaje; ?></strong></p>
<?php } ?>

<?php if ($noticia) { ?>
    <form method="POST" action="" class="formulario">
        <input type="hidden" name="id" value="<?php echo $noticia["id"]; ?>">

        <label>Título</label>
        <input type="text" name="titulo" value="<?php echo htmlspecialchars($noticia["titulo"]); ?>" required>

        <label>Categoría</label>
        <select name="categoria_id" required>
            <?php while ($c = $categorias->fetch_assoc()) { ?>
                <option value="<?php echo $c["id"]; ?>"
                    <?php echo $c["id"] == $noticia["categoria_id"] ? "selected" : ""; ?>>
                    <?php echo htmlspecialchars($c["nombre"]); ?>
                </option>
            <?php } ?>
        </select>

        <label>Resumen</label>
        <input type="text" name="resumen" maxlength="300" value="<?php echo htmlspecialchars($noticia["resumen"]); ?>">

        <label>Contenido</label>
        <textarea name="contenido" rows="8" required><?php echo htmlspecialchars($noticia["contenido"]); ?></textarea>

        <label>
            <input type="checkbox" name="publicada" <?php echo $noticia["publicada"] ? "checked" : ""; ?>>
            Publicada
        </label>

        <button type="submit">Guardar cambios</button>
    </form>
<?php } else { ?>
    <p>Noticia no encontrada.</p>
<?php } ?>

<p><a href="panel.php">← Volver al panel</a></p>

<?php include "../includes/pie.php"; ?>