<?php
session_start();

if (!isset($_SESSION["autor_id"])) {
    header("Location: login.php");
    exit;
}

include "../config/conexion.php";
include "../includes/cabecera.php";

function crearSlug($texto)
{
    $texto = mb_strtolower(trim($texto), "UTF-8");
    $texto = strtr($texto, ["á" => "a", "é" => "e", "í" => "i", "ó" => "o", "ú" => "u", "ñ" => "n", "ü" => "u"]);
    $texto = preg_replace("/[^a-z0-9]+/", "-", $texto);
    return trim($texto, "-");
}

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $titulo = $_POST["titulo"];
    $resumen = $_POST["resumen"];
    $contenido = $_POST["contenido"];
    $categoria_id = $_POST["categoria_id"];
    $slug = crearSlug($titulo);
    $nombreImagen = null;

    if (isset($_FILES["imagen"]) && $_FILES["imagen"]["error"] == UPLOAD_ERR_OK) {
        $extension = strtolower(pathinfo($_FILES["imagen"]["name"], PATHINFO_EXTENSION));
        $permitidas = ["jpg", "jpeg", "png", "webp"];
        $tmp = $_FILES["imagen"]["tmp_name"];

        if (in_array($extension, $permitidas) && getimagesize($tmp) !== false && $_FILES["imagen"]["size"] <= 2 * 1024 * 1024) {
            $nombreImagen = $slug . "-" . time() . "." . $extension;
            move_uploaded_file($tmp, "../assets/img/noticias/" . $nombreImagen);
        } else {
            $mensaje = "Imagen no válida (jpg, png o webp, máximo 2 MB)";
        }
    }

    if ($mensaje == "") {
        try {
            $sql = "INSERT INTO noticias
                    (titulo, slug, resumen, contenido, imagen, categoria_id, autor_id, publicada, fecha_publicacion)
                    VALUES (?, ?, ?, ?, ?, ?, ?, TRUE, NOW())";

            $consulta = $conexion->prepare($sql);
            $consulta->bind_param("sssssii", $titulo, $slug, $resumen, $contenido, $nombreImagen, $categoria_id, $_SESSION["autor_id"]);
            $consulta->execute();

            $mensaje = "Noticia publicada correctamente";
        } catch (Exception $e) {
            $mensaje = "No se pudo publicar (¿ya existe una noticia con ese título?)";
        }
    }
}

$categorias = $conexion->query("SELECT id, nombre FROM categorias ORDER BY nombre");
?>

<h2>Nueva noticia</h2>

<?php if ($mensaje != "") { ?>
    <p><strong><?php echo $mensaje; ?></strong></p>
<?php } ?>

<form method="POST" action="" class="formulario" enctype="multipart/form-data">
    <label>Imagen (opcional)</label>
    <input type="file" name="imagen" accept="image/*">

    <label>Título</label>
    <input type="text" name="titulo" required>

    <label>Categoría</label>
    <select name="categoria_id" required>
        <?php while ($c = $categorias->fetch_assoc()) { ?>
            <option value="<?php echo $c["id"]; ?>">
                <?php echo htmlspecialchars($c["nombre"]); ?>
            </option>
        <?php } ?>
    </select>

    <label>Resumen</label>
    <input type="text" name="resumen" maxlength="300">

    <label>Contenido</label>
    <textarea name="contenido" rows="8" required></textarea>

    <button type="submit">Publicar</button>
</form>

<?php include "../includes/pie.php"; ?>