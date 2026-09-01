```php
<?php

/* ==========================================================
   CERRAR SESIÓN
   Sistema de Asistencia QR
========================================================== */


/* ==========================================================
   INICIAR SESIÓN SI TODAVÍA NO ESTÁ INICIADA
========================================================== */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* ==========================================================
   ELIMINAR VARIABLES DE SESIÓN
========================================================== */

$_SESSION = [];


/* ==========================================================
   ELIMINAR COOKIE DE SESIÓN
========================================================== */

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


/* ==========================================================
   DESTRUIR SESIÓN
========================================================== */

session_destroy();


/* ==========================================================
   VOLVER AL LOGIN
==========================================================

   IMPORTANTE:

   logout.php está dentro de:

   asistenciaQR/auth/

   Por eso login.php está en la MISMA carpeta.

   NO debemos utilizar:

   ../login.php

   porque ese archivo no existe.

========================================================== */

header(
    "Location: login.php"
);

exit();

?>
```
