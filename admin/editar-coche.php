<?php
session_start();

if (!isset($_SESSION["autor_id"])) {
    header("Location: login.php");
    exit;
}

include "../config/conexion.php";

$id = (int) ($_GET["id"] ?? $_POST["id"] ?? 0);
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

    if (in_array($combustible, $combustibles) && in_array($estado, $estados)) {
        $sql = "UPDATE coches
                SET marca_id = ?, modelo = ?, anio_lanzamiento = ?, tipo_combustible = ?,
                    potencia_cv = ?, precio_desde = ?, estado = ?, descripcion = ?
                WHERE id = ?";

        $consulta = $conexion->prepare($sql);
        $consulta->bind_param("isisidssi", $marca_id, $modelo, $anio, $combustible, $potencia, $precio, $estado, $descripcion, $id);
        $consulta->execute();

        $mensaje = "Cambios guardados";
    } else {
        $mensaje = "Datos no válidos";
    }
}

$consulta = $conexion->prepare("SELECT * FROM coches WHERE id = ?");
$consulta->bind_param("i", $id);
$consulta->execute();
$coche = $consulta->get_result()->fetch_assoc();

$marcas = $conexion->query("SELECT id, nombre FROM marcas ORDER BY nombre");

include "../includes/cabecera.php";
?>

<h2>Editar coche</h2>

<?php if ($mensaje != "") { ?>
    <p><strong><?php echo $mensaje; ?></strong></p>
<?php } ?>

<?php if ($coche) { ?>
    <form method="POST" action="" class="formulario">
        <input type="hidden" name="id" value="<?php echo $coche["id"]; ?>">

        <label>Marca</label>
        <select name="marca_id" required>
            <?php while ($m = $marcas->fetch_assoc()) { ?>
                <option value="<?php echo $m["id"]; ?>" <?php echo $m["id"] == $coche["marca_id"] ? "selected" : ""; ?>>
                    <?php echo htmlspecialchars($m["nombre"]); ?>
                </option>
            <?php } ?>
        </select>

        <label>Modelo</label>
        <input type="text" name="modelo" value="<?php echo htmlspecialchars($coche["modelo"]); ?>" required>

        <label>Año de lanzamiento</label>
        <input type="number" name="anio_lanzamiento" value="<?php echo $coche["anio_lanzamiento"]; ?>">

        <label>Combustible</label>
        <select name="tipo_combustible">
            <?php foreach ($combustibles as $c) { ?>
                <option value="<?php echo $c; ?>" <?php echo $c == $coche["tipo_combustible"] ? "selected" : ""; ?>>
                    <?php echo ucfirst($c); ?>
                </option>
            <?php } ?>
        </select>

        <label>Potencia (CV)</label>
        <input type="number" name="potencia_cv" value="<?php echo $coche["potencia_cv"]; ?>">

        <label>Precio desde (€)</label>
        <input type="number" name="precio_desde" step="0.01" value="<?php echo $coche["precio_desde"]; ?>">

        <label>Estado</label>
        <select name="estado">
            <?php foreach ($estados as $e) { ?>
                <option value="<?php echo $e; ?>" <?php echo $e == $coche["estado"] ? "selected" : ""; ?>>
                    <?php echo ucfirst(str_replace("_", " ", $e)); ?>
                </option>
            <?php } ?>
        </select>

        <label>Descripción</label>
        <textarea name="descripcion" rows="5"><?php echo htmlspecialchars($coche["descripcion"]); ?></textarea>

        <button type="submit">Guardar cambios</button>
    </form>
<?php } else { ?>
    <p>Coche no encontrado.</p>
<?php } ?>

<p><a href="panel.php">← Volver al panel</a></p>

<?php include "../includes/pie.php"; ?>