<?php
session_start();
include "../config/conexion.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $contrasena = $_POST["contrasena"];

    $consulta = $conexion->prepare("SELECT id, nombre, contrasena FROM autores WHERE email = ?");
    $consulta->bind_param("s", $email);
    $consulta->execute();
    $autor = $consulta->get_result()->fetch_assoc();

    if ($autor && password_verify($contrasena, $autor["contrasena"])) {
        $_SESSION["autor_id"] = $autor["id"];
        $_SESSION["autor_nombre"] = $autor["nombre"];
        header("Location: panel.php");
        exit;
    } else {
        $error = "Email o contraseña incorrectos";
    }
}

include "../includes/cabecera.php";
?>

<h2>Acceso</h2>

<?php if ($error != "") { ?>
    <p><strong><?php echo $error; ?></strong></p>
<?php } ?>

<form method="POST" action="" class="formulario">
    <label>Email</label>
    <input type="email" name="email" required>

    <label>Contraseña</label>
    <input type="password" name="contrasena" required>

    <button type="submit">Entrar</button>
</form>

<?php include "../includes/pie.php"; ?>