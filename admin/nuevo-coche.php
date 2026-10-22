<?php
session_start();

if (!isset($_SESSION["autor_id"])) {
    header("Location: login.php");
    exit;
}

include "../config/conexion.php";

$mensaje = "";
$combustibles = ["gasolina", "diesel", "hibrido", "electrico"];
$estados = ["en_venta", "proximo_lanzamiento", "descatalogado"];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $marca_id = (int) $_POST["marca_id"];
    $modelo = $_POST["modelo"];
    $anio = (int) $_POST["anio_lanzamiento"];
    $combustible = $_POST["tipo_combustible"];
    $potencia = (int) $_POST["potencia_cv"];
    $precio = (float) $_POST["precio_desde"];
    $estado = $_POST["estado"];
    $descripcion = $_POST["descripcion"];
    $nombreImagen = null;

    if (!in_array($combustible, $combustibles) || !in_array($estado, $estados)) {
        $mensaje = "Datos no válidos";
    }

    if ($mensaje == "" && isset($_FILES["imagen"]) && $_FILES["imagen"]["error"] == UPLOAD_ERR_OK) {
        $extension = strtolower(pathinfo($_FILES["imagen"]["name"], PATHINFO_EXTENSION));
        $tmp = $_FILES["imagen"]["tmp_name"];

        if (in_array($extension, ["jpg", "jpeg", "png", "webp"]) && getimagesize($tmp) !== false && $_FILES["imagen"]["size"] <= 2 * 1024 * 1024) {
            $nombreImagen = "coche-" . time() . "." . $extension;
            move_uploaded_file($tmp, "../assets/img/coches/" . $nombreImagen);
        } else {
            $mensaje = "Imagen no válida (jpg, png o webp, máximo 2 MB)";
        }
    }

    if ($mensaje == "") {
        $sql = "INSERT INTO coches
                (marca_id, modelo, anio_lanzamiento, tipo_combustible, potencia_cv, precio_desde, estado, descripcion, imagen)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $consulta = $conexion->prepare($sql);
        $consulta->bind_param("isisidsss", $marca_id, $modelo, $anio, $combustible, $potencia, $precio, $estado, $descripcion, $nombreImagen);
        $consulta->execute();

        $mensaje = "Coche añadido correctamente";
    }
}

$marcas = $conexion->query("SELECT id, nombre FROM marcas ORDER BY nombre");

include "../includes/cabecera.php";
?>

<h2>Nuevo coche</h2>

<?php if ($mensaje != "") { ?>
    <p><strong><?php echo $mensaje; ?></strong></p>
<?php } ?>

<form method="POST" action="" class="formulario" enctype="multipart/form-data">
    <label>Marca</label>
    <select name="marca_id" required>
        <?php while ($m = $marcas->fetch_assoc()) { ?>
            <option value="<?php echo $m["id"]; ?>"><?php echo htmlspecialchars($m["nombre"]); ?></option>
        <?php } ?>
    </select>

    <label>Modelo</label>
    <input type="text" name="modelo" required>

    <label>Año de lanzamiento</label>
    <input type="number" name="anio_lanzamiento" min="1950" max="2100">

    <label>Combustible</label>
    <select name="tipo_combustible">
        <?php foreach ($combustibles as $c) { ?>
            <option value="<?php echo $c; ?>"><?php echo ucfirst($c); ?></option>
        <?php } ?>
    </select>

    <label>Potencia (CV)</label>
    <input type="number" name="potencia_cv" min="0">

    <label>Precio desde (€)</label>
    <input type="number" name="precio_desde" min="0" step="0.01">

    <label>Estado</label>
    <select name="estado">
        <?php foreach ($estados as $e) { ?>
            <option value="<?php echo $e; ?>"><?php echo ucfirst(str_replace("_", " ", $e)); ?></option>
        <?php } ?>
    </select>

    <label>Descripción</label>
    <textarea name="descripcion" rows="5"></textarea>

    <label>Imagen (opcional)</label>
    <input type="file" name="imagen" accept="image/*">

    <button type="submit">Guardar coche</button>
</form>

<p><a href="panel.php">← Volver al panel</a></p>

<?php include "../includes/pie.php"; ?>