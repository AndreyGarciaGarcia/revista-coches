<?php
session_start();

if (!isset($_SESSION["autor_id"])) {
    header("Location: login.php");
    exit;
}

include "../config/conexion.php";
include "../includes/cabecera.php";

$sql = "SELECT n.id, n.titulo, n.publicada, c.nombre AS categoria
        FROM noticias n
        JOIN categorias c ON n.categoria_id = c.id
        ORDER BY n.fecha_creacion DESC";

$resultado = $conexion->query($sql);
?>

<h2>Panel de administración</h2>

<p>
    <a href="nueva-noticia.php">+ Nueva noticia</a> ·
    <a href="logout.php">Cerrar sesión</a>
</p>

<table class="tabla-admin">
    <tr>
        <th>Título</th>
        <th>Categoría</th>
        <th>Estado</th>
        <th>Acciones</th>
    </tr>
    <?php while ($n = $resultado->fetch_assoc()) { ?>
        <tr>
            <td><?php echo htmlspecialchars($n["titulo"]); ?></td>
            <td><?php echo htmlspecialchars($n["categoria"]); ?></td>
            <td><?php echo $n["publicada"] ? "Publicada" : "Borrador"; ?></td>
            <td>
                <a href="editar-noticia.php?id=<?php echo $n["id"]; ?>">Editar</a>
                <form method="POST" action="borrar-noticia.php" style="display:inline"
                    onsubmit="return confirm('¿Seguro que quieres borrar esta noticia?');">
                    <input type="hidden" name="id" value="<?php echo $n["id"]; ?>">
                    <button type="submit">Borrar</button>
                </form>
            </td>
        </tr>
    <?php } ?>
</table>

<?php
$coches = $conexion->query("SELECT c.id, c.modelo, c.estado, m.nombre AS marca
                            FROM coches c
                            JOIN marcas m ON c.marca_id = m.id
                            ORDER BY m.nombre, c.modelo");
?>

<h2>Coches</h2>
<p><a href="nuevo-coche.php">+ Nuevo coche</a></p>

<table class="tabla-admin">
    <tr>
        <th>Marca</th>
        <th>Modelo</th>
        <th>Estado</th>
        <th>Acciones</th>
    </tr>
    <?php while ($c = $coches->fetch_assoc()) { ?>
        <tr>
            <td><?php echo htmlspecialchars($c["marca"]); ?></td>
            <td><?php echo htmlspecialchars($c["modelo"]); ?></td>
            <td><?php echo str_replace("_", " ", $c["estado"]); ?></td>
            <td>
                <a href="editar-coche.php?id=<?php echo $c["id"]; ?>">Editar</a>
                <form method="POST" action="borrar-coche.php" style="display:inline"
                    onsubmit="return confirm('¿Seguro que quieres borrar este coche?');">
                    <input type="hidden" name="id" value="<?php echo $c["id"]; ?>">
                    <button type="submit">Borrar</button>
                </form>
            </td>
        </tr>
    <?php } ?>
</table>

<?php include "../includes/pie.php"; ?>