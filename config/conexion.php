<?php

$servidor = "localhost";
$usuario = "root";
$password = "";
$base_datos = "asistencia_qr_escolar";

$conexion = mysqli_connect(
    $servidor,
    $usuario,
    $password,
    $base_datos
);

if (!$conexion) {

    die(
        "Error de conexión con la base de datos: "
        . mysqli_connect_error()
    );

}

mysqli_set_charset($conexion, "utf8mb4");

?>
