<?php
define("BASE_URL", "/mis-proyectos-php/proyectos/revista-coches");
define("NOMBRE_SITIO", "Revista de Coches"); // cámbialo cuando elijas nombre
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo NOMBRE_SITIO; ?></title>
    <link rel="stylesheet" href="/mis-proyectos-php/proyectos/revista-coches/assets/css/estilos.css">
</head>

<body>
    <header>
        <h1><a href="<?php echo BASE_URL; ?>/"><?php echo NOMBRE_SITIO; ?></a></h1>
        <?php
        $menuCategorias = isset($conexion)
            ? $conexion->query("SELECT id, nombre FROM categorias ORDER BY nombre")
            : null;
        ?>
        <nav>
            <a href="<?php echo BASE_URL; ?>/">Inicio</a>
            <a href="<?php echo BASE_URL; ?>/paginas/catalogo.php">Catálogo</a>
            <?php if ($menuCategorias) { ?>
                <?php while ($mc = $menuCategorias->fetch_assoc()) { ?>
                    <a href="<?php echo BASE_URL; ?>/paginas/categoria.php?id=<?php echo $mc["id"]; ?>">
                        <?php echo htmlspecialchars($mc["nombre"]); ?>
                    </a>
                <?php } ?>
            <?php } ?>
        </nav>
    </header>
    <main>