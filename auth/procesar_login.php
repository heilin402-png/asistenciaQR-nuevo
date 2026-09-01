```php
<?php

/* ==========================================================
   PROCESAR INICIO DE SESIÓN
   Sistema de Asistencia QR
========================================================== */


/* ==========================================================
   SESIÓN
========================================================== */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* ==========================================================
   SOLO SE PERMITE POST
========================================================== */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: login.php");
    exit();

}


/* ==========================================================
   CONEXIÓN
========================================================== */

require_once "../config/conexion.php";


/* ==========================================================
   DATOS DEL FORMULARIO
========================================================== */

$usuario = trim(
    $_POST['usuario'] ?? ''
);

$password = $_POST['password'] ?? '';


/* ==========================================================
   VALIDACIÓN BÁSICA
========================================================== */

if ($usuario === '' || $password === '') {

    header(
        "Location: login.php?error=" .
        urlencode(
            "Por favor, completa todos los campos."
        )
    );

    exit();

}


/* ==========================================================
   CONSULTAR USUARIO
==========================================================

   IMPORTANTE:

   La tabla usuarios REAL de tu proyecto tiene:

   id_usuario
   nombre
   apellido
   usuario
   password
   id_rol
   estado
   fecha_creacion

   NO existe:

   documento

========================================================== */

$sql = "
    SELECT
        id_usuario,
        nombre,
        apellido,
        usuario,
        password,
        id_rol,
        estado
    FROM usuarios
    WHERE usuario = ?
    LIMIT 1
";


/* ==========================================================
   PREPARAR CONSULTA
========================================================== */

$stmt = mysqli_prepare(
    $conexion,
    $sql
);


if (!$stmt) {

    header(
        "Location: login.php?error=" .
        urlencode(
            "No fue posible procesar el inicio de sesión."
        )
    );

    exit();

}


/* ==========================================================
   VINCULAR USUARIO
========================================================== */

mysqli_stmt_bind_param(
    $stmt,
    "s",
    $usuario
);


/* ==========================================================
   EJECUTAR
========================================================== */

if (!mysqli_stmt_execute($stmt)) {

    mysqli_stmt_close($stmt);

    header(
        "Location: login.php?error=" .
        urlencode(
            "Ocurrió un error al consultar el usuario."
        )
    );

    exit();

}


/* ==========================================================
   OBTENER RESULTADO
========================================================== */

mysqli_stmt_store_result($stmt);


if (mysqli_stmt_num_rows($stmt) !== 1) {

    mysqli_stmt_close($stmt);

    header(
        "Location: login.php?error=" .
        urlencode(
            "Usuario o contraseña incorrectos."
        )
    );

    exit();

}


/* ==========================================================
   VINCULAR RESULTADOS
========================================================== */

mysqli_stmt_bind_result(
    $stmt,
    $id_usuario,
    $nombre,
    $apellido,
    $usuarioBD,
    $passwordBD,
    $id_rol,
    $estado
);


/* ==========================================================
   OBTENER DATOS
========================================================== */

mysqli_stmt_fetch($stmt);


/* ==========================================================
   CERRAR CONSULTA
========================================================== */

mysqli_stmt_close($stmt);


/* ==========================================================
   COMPROBAR ESTADO
========================================================== */

if (strtoupper(trim($estado)) !== 'ACTIVO') {

    header(
        "Location: login.php?error=" .
        urlencode(
            "Este usuario se encuentra inactivo."
        )
    );

    exit();

}


/* ==========================================================
   VERIFICAR CONTRASEÑA
========================================================== */

if (
    !password_verify(
        $password,
        $passwordBD
    )
) {

    header(
        "Location: login.php?error=" .
        urlencode(
            "Usuario o contraseña incorrectos."
        )
    );

    exit();

}


/* ==========================================================
   REGENERAR SESIÓN
========================================================== */

session_regenerate_id(true);


/* ==========================================================
   CREAR VARIABLES DE SESIÓN
========================================================== */

$_SESSION['id_usuario'] =
    (int) $id_usuario;

$_SESSION['id_rol'] =
    (int) $id_rol;

$_SESSION['nombre'] =
    $nombre;

$_SESSION['apellido'] =
    $apellido;

$_SESSION['usuario'] =
    $usuarioBD;


/* ==========================================================
   REDIRECCIÓN SEGÚN ROL
========================================================== */


/* ----------------------------------------------------------
   ADMINISTRADOR
   id_rol = 1
---------------------------------------------------------- */

if ((int) $id_rol === 1) {

    header(
        "Location: ../admin/dashboard.php"
    );

    exit();

}


/* ----------------------------------------------------------
   DOCENTE
   id_rol = 2
---------------------------------------------------------- */

if ((int) $id_rol === 2) {

    header(
        "Location: ../docente/dashboard.php"
    );

    exit();

}


/* ==========================================================
   ROL NO RECONOCIDO
========================================================== */

/*
   Si el usuario existe pero tiene un rol que
   todavía no está configurado en el sistema,
   destruimos la sesión para evitar dejar
   una sesión parcialmente autenticada.
*/

$_SESSION = [];


if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );

}


session_destroy();


header(
    "Location: login.php?error=" .
    urlencode(
        "El rol de este usuario no está configurado."
    )
);

exit();

?>
```
