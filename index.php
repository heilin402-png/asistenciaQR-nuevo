<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['id_usuario'])) {

    if (
        isset($_SESSION['id_rol']) &&
        (int) $_SESSION['id_rol'] === 1
    ) {

        header("Location: admin/dashboard.php");
        exit();

    }

    if (
        isset($_SESSION['id_rol']) &&
        (int) $_SESSION['id_rol'] === 2
    ) {

        header("Location: docente/dashboard.php");
        exit();

    }

}

header("Location: auth/login.php");
exit();

?>