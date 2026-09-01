<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/conexion.php";

date_default_timezone_set('America/Bogota');

/* =========================================================
   INFORMACIÓN DEL USUARIO
========================================================= */

$nombreUsuario = $_SESSION['nombre'] ?? 'Administrador Sistema';

$partesNombre = preg_split('/\s+/', trim($nombreUsuario));

$primerNombre = $partesNombre[0] ?? 'Administrador';

$iniciales = '';

foreach (array_slice($partesNombre, 0, 2) as $parte) {
    $iniciales .= strtoupper(substr($parte, 0, 1));
}

if ($iniciales === '') {
    $iniciales = 'AS';
}

$horaActual = date('H:i:s');
$fechaActual = date('d/m/Y');

$mensaje = '';
$tipoMensaje = '';

$idUsuarioSesion = (int)$_SESSION['id_usuario'];


/* =========================================================
   CREAR USUARIO
========================================================= */

if (isset($_POST['agregar_usuario'])) {

    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    $idRol = (int)($_POST['id_rol'] ?? 0);

    if (
        $nombre !== '' &&
        $apellido !== '' &&
        $usuario !== '' &&
        $password !== '' &&
        $idRol > 0
    ) {

        /* Verificar usuario existente */

        $sqlVerificar = "
            SELECT id_usuario
            FROM usuarios
            WHERE usuario = ?
            LIMIT 1
        ";

        $stmtVerificar = mysqli_prepare(
            $conexion,
            $sqlVerificar
        );

        $existe = false;

        if ($stmtVerificar) {

            mysqli_stmt_bind_param(
                $stmtVerificar,
                "s",
                $usuario
            );

            mysqli_stmt_execute(
                $stmtVerificar
            );

            mysqli_stmt_store_result(
                $stmtVerificar
            );

            $existe =
                mysqli_stmt_num_rows(
                    $stmtVerificar
                ) > 0;

            mysqli_stmt_close(
                $stmtVerificar
            );
        }

        if ($existe) {

            $mensaje =
                'El nombre de usuario ya está registrado.';

            $tipoMensaje = 'error';

        } else {

            $passwordHash =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

            $sql = "
                INSERT INTO usuarios
                (
                    nombre,
                    apellido,
                    usuario,
                    password,
                    id_rol,
                    estado,
                    fecha_creacion
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, 'ACTIVO', NOW()
                )
            ";

            $stmt = mysqli_prepare(
                $conexion,
                $sql
            );

            if ($stmt) {

                mysqli_stmt_bind_param(
                    $stmt,
                    "ssssi",
                    $nombre,
                    $apellido,
                    $usuario,
                    $passwordHash,
                    $idRol
                );

                if (
                    mysqli_stmt_execute($stmt)
                ) {

                    $mensaje =
                        'Usuario creado correctamente.';

                    $tipoMensaje = 'success';

                } else {

                    $mensaje =
                        'No fue posible crear el usuario.';

                    $tipoMensaje = 'error';
                }

                mysqli_stmt_close($stmt);
            }
        }

    } else {

        $mensaje =
            'Completa todos los datos del usuario.';

        $tipoMensaje = 'error';
    }
}


/* =========================================================
   EDITAR USUARIO
========================================================= */

if (isset($_POST['editar_usuario'])) {

    $idUsuario = (int)(
        $_POST['id_usuario'] ?? 0
    );

    $nombre = trim(
        $_POST['nombre'] ?? ''
    );

    $apellido = trim(
        $_POST['apellido'] ?? ''
    );

    $usuario = trim(
        $_POST['usuario'] ?? ''
    );

    $idRol = (int)(
        $_POST['id_rol'] ?? 0
    );

    if (
        $idUsuario > 0 &&
        $nombre !== '' &&
        $apellido !== '' &&
        $usuario !== '' &&
        $idRol > 0
    ) {

        /*
         * Comprobar que el usuario no esté
         * siendo utilizado por otra cuenta.
         */

        $sqlVerificar = "
            SELECT id_usuario
            FROM usuarios
            WHERE usuario = ?
            AND id_usuario <> ?
            LIMIT 1
        ";

        $stmtVerificar = mysqli_prepare(
            $conexion,
            $sqlVerificar
        );

        $existe = false;

        if ($stmtVerificar) {

            mysqli_stmt_bind_param(
                $stmtVerificar,
                "si",
                $usuario,
                $idUsuario
            );

            mysqli_stmt_execute(
                $stmtVerificar
            );

            mysqli_stmt_store_result(
                $stmtVerificar
            );

            $existe =
                mysqli_stmt_num_rows(
                    $stmtVerificar
                ) > 0;

            mysqli_stmt_close(
                $stmtVerificar
            );
        }

        if ($existe) {

            $mensaje =
                'El nombre de usuario ya está registrado.';

            $tipoMensaje = 'error';

        } else {

            $sql = "
                UPDATE usuarios
                SET
                    nombre = ?,
                    apellido = ?,
                    usuario = ?,
                    id_rol = ?
                WHERE id_usuario = ?
            ";

            $stmt = mysqli_prepare(
                $conexion,
                $sql
            );

            if ($stmt) {

                mysqli_stmt_bind_param(
                    $stmt,
                    "sssii",
                    $nombre,
                    $apellido,
                    $usuario,
                    $idRol,
                    $idUsuario
                );

                if (
                    mysqli_stmt_execute($stmt)
                ) {

                    /*
                     * Actualizamos también la sesión
                     * si el administrador se editó a sí mismo.
                     */

                    if (
                        $idUsuario ===
                        $idUsuarioSesion
                    ) {

                        $_SESSION['nombre'] =
                            trim(
                                $nombre .
                                ' ' .
                                $apellido
                            );

                        $_SESSION['id_rol'] =
                            $idRol;
                    }

                    $mensaje =
                        'Usuario actualizado correctamente.';

                    $tipoMensaje = 'success';

                } else {

                    $mensaje =
                        'No fue posible actualizar el usuario.';

                    $tipoMensaje = 'error';
                }

                mysqli_stmt_close($stmt);
            }
        }

    } else {

        $mensaje =
            'Completa todos los datos del usuario.';

        $tipoMensaje = 'error';
    }
}


/* =========================================================
   CAMBIAR CONTRASEÑA
========================================================= */

if (isset($_POST['cambiar_password'])) {

    $idUsuario = (int)(
        $_POST['id_usuario'] ?? 0
    );

    $passwordNueva =
        $_POST['password_nueva'] ?? '';

    if (
        $idUsuario > 0 &&
        $passwordNueva !== ''
    ) {

        $passwordHash =
            password_hash(
                $passwordNueva,
                PASSWORD_DEFAULT
            );

        $sql = "
            UPDATE usuarios
            SET password = ?
            WHERE id_usuario = ?
        ";

        $stmt = mysqli_prepare(
            $conexion,
            $sql
        );

        if ($stmt) {

            mysqli_stmt_bind_param(
                $stmt,
                "si",
                $passwordHash,
                $idUsuario
            );

            if (
                mysqli_stmt_execute($stmt)
            ) {

                $mensaje =
                    'Contraseña actualizada correctamente.';

                $tipoMensaje = 'success';

            } else {

                $mensaje =
                    'No fue posible actualizar la contraseña.';

                $tipoMensaje = 'error';
            }

            mysqli_stmt_close($stmt);
        }

    } else {

        $mensaje =
            'Escribe una contraseña nueva.';

        $tipoMensaje = 'error';
    }
}


/* =========================================================
   CAMBIAR ESTADO
========================================================= */

if (isset($_POST['cambiar_estado'])) {

    $idUsuario = (int)(
        $_POST['id_usuario'] ?? 0
    );

    $nuevoEstado =
        ($_POST['nuevo_estado'] ?? '') === 'ACTIVO'
        ? 'ACTIVO'
        : 'INACTIVO';

    /*
     * No permitimos desactivar la cuenta
     * actualmente utilizada.
     */

    if (
        $idUsuario === $idUsuarioSesion &&
        $nuevoEstado === 'INACTIVO'
    ) {

        $mensaje =
            'No puedes desactivar el usuario con el que estás conectado.';

        $tipoMensaje = 'error';

    } elseif ($idUsuario > 0) {

        $sql = "
            UPDATE usuarios
            SET estado = ?
            WHERE id_usuario = ?
        ";

        $stmt = mysqli_prepare(
            $conexion,
            $sql
        );

        if ($stmt) {

            mysqli_stmt_bind_param(
                $stmt,
                "si",
                $nuevoEstado,
                $idUsuario
            );

            if (
                mysqli_stmt_execute($stmt)
            ) {

                $mensaje =
                    $nuevoEstado === 'ACTIVO'
                    ? 'Usuario activado correctamente.'
                    : 'Usuario desactivado correctamente.';

                $tipoMensaje = 'success';

            } else {

                $mensaje =
                    'No fue posible cambiar el estado.';

                $tipoMensaje = 'error';
            }

            mysqli_stmt_close($stmt);
        }
    }
}


/* =========================================================
   ELIMINAR USUARIO
========================================================= */

if (isset($_POST['eliminar_usuario'])) {

    $idUsuario = (int)(
        $_POST['id_usuario'] ?? 0
    );

    /*
     * Evitar que el administrador
     * elimine su propia cuenta.
     */

    if (
        $idUsuario === $idUsuarioSesion
    ) {

        $mensaje =
            'No puedes eliminar el usuario con el que estás conectado.';

        $tipoMensaje = 'error';

    } elseif ($idUsuario > 0) {

        $sql = "
            DELETE FROM usuarios
            WHERE id_usuario = ?
        ";

        $stmt = mysqli_prepare(
            $conexion,
            $sql
        );

        if ($stmt) {

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $idUsuario
            );

            if (
                mysqli_stmt_execute($stmt)
            ) {

                $mensaje =
                    'Usuario eliminado correctamente.';

                $tipoMensaje = 'success';

            } else {

                $mensaje =
                    'No fue posible eliminar el usuario.';

                $tipoMensaje = 'error';
            }

            mysqli_stmt_close($stmt);
        }
    }
}


/* =========================================================
   OBTENER USUARIOS
========================================================= */

$usuarios = [];

$sqlUsuarios = "
    SELECT
        u.id_usuario,
        u.nombre,
        u.apellido,
        u.usuario,
        u.id_rol,
        u.estado,
        u.fecha_creacion,
        r.nombre_rol

    FROM usuarios u

    LEFT JOIN roles r
        ON r.id_rol = u.id_rol

    ORDER BY
        u.nombre ASC,
        u.apellido ASC
";

$resultadoUsuarios =
    mysqli_query(
        $conexion,
        $sqlUsuarios
    );

if ($resultadoUsuarios) {

    while (
        $fila =
        mysqli_fetch_assoc(
            $resultadoUsuarios
        )
    ) {

        $usuarios[] = $fila;
    }
}


/* =========================================================
   ESTADÍSTICAS
========================================================= */

$totalUsuarios =
    count($usuarios);

$totalActivos = 0;
$totalInactivos = 0;
$totalAdministradores = 0;
$totalDocentes = 0;

foreach ($usuarios as $usuarioFila) {

    if (
        $usuarioFila['estado'] === 'ACTIVO'
    ) {

        $totalActivos++;

    } else {

        $totalInactivos++;
    }

    if (
        (int)$usuarioFila['id_rol'] === 1
    ) {

        $totalAdministradores++;

    } elseif (
        (int)$usuarioFila['id_rol'] === 2
    ) {

        $totalDocentes++;
    }
}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Asistencia QR | Usuarios</title>

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>

<style>

/* =========================================================
   VARIABLES
========================================================= */

:root{
    --aqua:#18d8ce;
    --aqua-dark:#087d92;
    --blue:#69b8d5;
    --mint:#42cda1;
    --purple:#8579d2;
    --coral:#e99a78;
    --text:#3e6f7d;
    --dark:#20596d;
    --muted:#7897a0;
}


/* =========================================================
   GENERAL
========================================================= */

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    min-height:100vh;
    overflow-x:hidden;

    font-family:"Segoe UI",Arial,sans-serif;

    color:var(--text);

    background:
        radial-gradient(
            circle at 5% 10%,
            rgba(24,216,206,.13),
            transparent 27%
        ),
        radial-gradient(
            circle at 96% 88%,
            rgba(133,121,210,.13),
            transparent 30%
        ),
        linear-gradient(
            135deg,
            #e8faf7 0%,
            #f8fdfc 48%,
            #eaf6fb 100%
        );
}


/* =========================================================
   APP
========================================================= */

.app{
    position:relative;
    z-index:1;

    display:flex;

    gap:18px;

    min-height:100vh;

    padding:18px;
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar{
    width:285px;

    flex-shrink:0;

    min-height:calc(100vh - 36px);

    display:flex;
    flex-direction:column;

    padding:22px 16px 16px;

    border:1px solid rgba(255,255,255,.94);

    border-radius:29px;

    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,.87),
            rgba(232,250,247,.68)
        );

    backdrop-filter:blur(25px);

    box-shadow:
        0 25px 65px
        rgba(55,113,129,.10);
}

.sidebar-header{
    display:flex;

    align-items:center;

    gap:13px;

    padding:3px 9px 20px;
}

.logo-container{
    width:64px;
    height:64px;

    display:flex;

    align-items:center;
    justify-content:center;

    padding:7px;

    flex-shrink:0;

    border-radius:19px;

    background:rgba(255,255,255,.76);

    border:1px solid rgba(255,255,255,.95);

    box-shadow:
        0 12px 30px
        rgba(55,113,129,.10);
}

.logo-container img{
    width:100%;
    height:100%;

    object-fit:contain;
}

.sidebar-title strong{
    display:block;

    color:#075273;

    font-size:19px;

    font-weight:950;
}

.sidebar-title small{
    display:block;

    margin-top:6px;

    color:#7898a1;

    font-size:11px;

    font-weight:750;
}

.sidebar-line{
    position:relative;

    height:1px;

    margin:0 9px 16px;

    background:rgba(50,111,130,.09);
}

.sidebar-line span{
    position:absolute;

    left:0;
    top:-1px;

    width:55px;
    height:2px;

    border-radius:5px;

    background:
        linear-gradient(
            90deg,
            var(--aqua),
            transparent
        );
}

.navigation{
    flex:1;
}

.menu-section{
    margin-bottom:12px;
}

.menu-label{
    display:flex;

    align-items:center;

    gap:8px;

    min-height:30px;

    padding:0 11px;

    color:#7d9aa3;

    font-size:11px;

    font-weight:950;

    letter-spacing:1.35px;
}

.label-line{
    width:17px;
    height:2px;

    border-radius:4px;

    background:#b9d7db;
}

.nav-link{
    position:relative;

    display:flex;

    align-items:center;

    gap:11px;

    width:100%;

    min-height:55px;

    margin-bottom:4px;

    padding:6px 10px;

    border-radius:15px;

    color:#557f8b;

    text-decoration:none;

    font-size:14px;

    font-weight:850;

    transition:.25s;
}

.nav-link:hover{
    color:#075273;

    background:rgba(255,255,255,.74);

    transform:translateX(4px);
}

.nav-link.active{
    color:#08758a;

    background:
        linear-gradient(
            100deg,
            rgba(24,216,206,.17),
            rgba(255,255,255,.70)
        );
}

.nav-link.active::before{
    content:"";

    position:absolute;

    left:0;

    top:8px;

    bottom:8px;

    width:4px;

    border-radius:0 7px 7px 0;

    background:
        linear-gradient(
            180deg,
            var(--aqua),
            var(--blue)
        );
}

.nav-icon{
    width:40px;
    height:40px;

    display:flex;

    align-items:center;
    justify-content:center;

    flex-shrink:0;

    border-radius:12px;

    color:#4b98a8;

    background:rgba(24,216,206,.075);

    font-size:19px;
}

.nav-icon.academic{
    color:#766cc8;

    background:rgba(133,121,210,.10);
}

.nav-icon.people{
    color:#488da1;

    background:rgba(105,184,213,.10);
}

.nav-icon.qr-icon{
    color:#078395;

    background:rgba(24,216,206,.12);
}

.nav-icon.reports{
    color:#bd8a40;

    background:rgba(209,161,88,.12);
}

.nav-icon.restaurant{
    color:#d99a24;

    background:rgba(245,190,70,.14);
}

.nav-icon.audit{
    color:#7569c2;

    background:rgba(133,121,210,.11);
}

.nav-arrow{
    margin-left:auto;

    color:#a1b8be;

    opacity:0;

    transition:.2s;
}

.nav-link:hover .nav-arrow{
    opacity:1;
}


/* =========================================================
   PERFIL
========================================================= */

.sidebar-bottom{
    margin-top:auto;

    padding-top:10px;
}

.profile-card{
    display:flex;

    align-items:center;

    gap:10px;

    padding:10px 11px;

    border-radius:15px;

    background:rgba(255,255,255,.54);

    border:1px solid rgba(255,255,255,.85);
}

.profile-avatar{
    width:42px;
    height:42px;

    display:flex;

    align-items:center;
    justify-content:center;

    flex-shrink:0;

    border-radius:12px;

    color:#fff;

    background:
        linear-gradient(
            145deg,
            #52dca9,
            #15966f
        );

    font-size:14px;

    font-weight:950;
}

.profile-info{
    flex:1;

    min-width:0;
}

.profile-info strong{
    display:block;

    overflow:hidden;

    color:#4d7c89;

    font-size:13px;

    font-weight:900;

    white-space:nowrap;

    text-overflow:ellipsis;
}

.profile-info small{
    display:block;

    margin-top:3px;

    color:#8ca6ad;

    font-size:10px;
}

.profile-status{
    color:#27b884;

    font-size:11px;
}

.logout{
    display:flex;

    align-items:center;

    gap:9px;

    min-height:48px;

    margin-top:3px;

    padding:0 10px;

    color:#b86e77;

    text-decoration:none;

    font-size:13px;

    font-weight:850;
}

.logout:hover{
    color:#a4535c;

    background:
        rgba(242,143,150,.08);

    border-radius:13px;
}

.logout-icon{
    width:35px;
    height:35px;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:10px;

    background:
        rgba(242,143,150,.08);
}


/* =========================================================
   MAIN
========================================================= */

.main{
    flex:1;

    min-width:0;

    display:flex;

    flex-direction:column;

    gap:18px;
}


/* =========================================================
   TOPBAR
========================================================= */

.topbar{
    min-height:82px;

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:20px;

    padding:14px 22px;

    border:1px solid rgba(255,255,255,.92);

    border-radius:23px;

    background:rgba(255,255,255,.68);

    backdrop-filter:blur(20px);

    box-shadow:
        0 16px 42px
        rgba(55,113,129,.065);
}

.page-info{
    display:flex;

    align-items:center;

    gap:13px;
}

.page-indicator{
    width:9px;

    height:47px;

    border-radius:7px;

    background:
        linear-gradient(
            180deg,
            var(--aqua),
            var(--purple)
        );
}

.page-title h1{
    color:#15576c;

    font-size:28px;

    font-weight:950;
}

.page-title p{
    margin-top:5px;

    color:#7898a2;

    font-size:14px;

    font-weight:650;
}

.clock-box{
    display:flex;

    align-items:center;

    gap:12px;

    padding:10px 15px;

    border-radius:15px;

    background:rgba(255,255,255,.73);

    border:1px solid rgba(255,255,255,.90);
}

.clock-icon{
    width:38px;
    height:38px;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:11px;

    color:#0b9f9c;

    background:
        rgba(24,216,206,.10);

    font-size:18px;
}

.clock-time{
    color:#155b70;

    font-size:18px;

    font-weight:950;

    letter-spacing:.5px;
}

.clock-date{
    margin-top:2px;

    color:#819ba3;

    font-size:10px;

    font-weight:750;
}


/* =========================================================
   CONTENT
========================================================= */

.content-card{
    padding:28px;

    border:1px solid rgba(255,255,255,.94);

    border-radius:27px;

    background:rgba(255,255,255,.74);

    box-shadow:
        0 20px 48px
        rgba(55,113,129,.07);
}

.content-heading{
    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:20px;

    margin-bottom:22px;
}

.heading-left h2{
    color:#315f70;

    font-size:23px;

    font-weight:950;
}

.heading-left p{
    margin-top:6px;

    color:#819ca4;

    font-size:13px;

    font-weight:650;
}


/* =========================================================
   BOTONES
========================================================= */

.btn-primary{
    display:inline-flex;

    align-items:center;
    justify-content:center;

    gap:8px;

    min-height:44px;

    padding:0 17px;

    border:none;

    border-radius:13px;

    color:#fff;

    background:
        linear-gradient(
            135deg,
            #18cfc6,
            #159fae
        );

    box-shadow:
        0 9px 20px
        rgba(24,216,206,.18);

    font-size:13px;

    font-weight:900;

    cursor:pointer;

    transition:.2s;
}

.btn-primary:hover{
    transform:translateY(-2px);
}


/* =========================================================
   ALERTAS
========================================================= */

.alert{
    display:flex;

    align-items:center;

    gap:10px;

    margin-bottom:20px;

    padding:13px 15px;

    border-radius:14px;

    font-size:13px;

    font-weight:800;
}

.alert.success{
    color:#217d68;

    background:
        rgba(66,205,161,.10);

    border:
        1px solid
        rgba(66,205,161,.18);
}

.alert.error{
    color:#a35e68;

    background:
        rgba(242,143,150,.10);

    border:
        1px solid
        rgba(242,143,150,.18);
}


/* =========================================================
   ESTADÍSTICAS
========================================================= */

.mini-stats{
    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:13px;

    margin-bottom:22px;
}

.mini-stat{
    display:flex;

    align-items:center;

    gap:12px;

    padding:14px 16px;

    border-radius:17px;

    background:rgba(255,255,255,.68);

    border:1px solid rgba(255,255,255,.88);
}

.mini-stat-icon{
    width:43px;
    height:43px;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:13px;

    font-size:19px;
}

.mini-stat:nth-child(1)
.mini-stat-icon{
    color:#0b9f9c;

    background:
        rgba(24,216,206,.10);
}

.mini-stat:nth-child(2)
.mini-stat-icon{
    color:#28a579;

    background:
        rgba(66,205,161,.11);
}

.mini-stat:nth-child(3)
.mini-stat-icon{
    color:#7569c2;

    background:
        rgba(133,121,210,.11);
}

.mini-stat:nth-child(4)
.mini-stat-icon{
    color:#bd8a40;

    background:
        rgba(209,161,88,.12);
}

.mini-stat span{
    display:block;

    color:#819ca4;

    font-size:10px;

    font-weight:800;
}

.mini-stat strong{
    display:block;

    margin-top:2px;

    color:#315f70;

    font-size:20px;

    font-weight:950;
}


/* =========================================================
   BUSCADOR
========================================================= */

.search-box{
    position:relative;

    margin-bottom:18px;
}

.search-box i{
    position:absolute;

    left:15px;

    top:50%;

    transform:translateY(-50%);

    color:#73a0a9;

    font-size:17px;
}

.search-box input{
    width:100%;

    height:50px;

    padding:
        0 16px 0 45px;

    border:
        1px solid
        rgba(180,215,220,.48);

    border-radius:15px;

    outline:none;

    color:#416f7e;

    background:
        rgba(255,255,255,.70);

    font-family:inherit;

    font-size:13px;

    font-weight:700;
}


/* =========================================================
   TABLA
========================================================= */

.table-wrapper{
    overflow-x:auto;

    border-radius:17px;
}

.users-table{
    width:100%;

    min-width:900px;

    border-collapse:collapse;

    text-align:center;
}

.users-table thead{
    background:
        rgba(24,216,206,.055);
}

.users-table th{
    padding:13px 14px;

    text-align:center;

    vertical-align:middle;

    color:#668995;

    font-size:10px;

    font-weight:950;

    letter-spacing:.4px;
}

.users-table td{
    padding:14px;

    text-align:center;

    vertical-align:middle;

    border-top:
        1px solid
        rgba(120,170,180,.08);

    color:#527b87;

    font-size:12px;

    font-weight:700;
}

.user-name{
    color:#416f7e !important;

    font-weight:900 !important;
}

.user-login{
    color:#087d92 !important;

    font-weight:850 !important;
}

.role-badge{
    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:6px;

    padding:6px 10px;

    border-radius:9px;

    font-size:9px;

    font-weight:950;
}

.role-admin{
    color:#7569c2;

    background:
        rgba(133,121,210,.11);
}

.role-docente{
    color:#087d92;

    background:
        rgba(24,216,206,.10);
}

.user-badge{
    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:5px;

    padding:5px 8px;

    border-radius:8px;

    font-size:9px;

    font-weight:900;
}

.user-badge.active{
    color:#218b6c;

    background:
        rgba(66,205,161,.10);
}

.user-badge.inactive{
    color:#aa7279;

    background:
        rgba(242,143,150,.10);
}

.table-actions{
    display:flex;

    align-items:center;

    justify-content:center;

    gap:8px;

    flex-wrap:wrap;
}

.table-actions form{
    margin:0;

    display:flex;

    justify-content:center;
}

.table-btn{
    min-height:39px;

    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:6px;

    padding:0 12px;

    border:none;

    border-radius:11px;

    font-family:inherit;

    font-size:10px;

    font-weight:900;

    cursor:pointer;

    text-decoration:none;

    transition:.2s;
}

.table-btn:hover{
    transform:translateY(-1px);
}

.btn-edit{
    color:#087d92;

    background:
        rgba(24,216,206,.10);
}

.btn-password{
    color:#7569c2;

    background:
        rgba(133,121,210,.10);
}

.btn-status-on,
.btn-delete{
    color:#b45e68;

    background:
        rgba(242,143,150,.10);
}

.btn-status-off{
    color:#218b6c;

    background:
        rgba(66,205,161,.11);
}

.btn-disabled{
    opacity:.45;

    cursor:not-allowed;

    transform:none !important;
}


/* =========================================================
   EMPTY
========================================================= */

.empty-users{
    padding:50px 20px;

    text-align:center;
}

.empty-users i{
    display:block;

    margin-bottom:10px;

    color:#8dc6c5;

    font-size:40px;
}

.empty-users strong{
    color:#527b87;

    font-size:15px;
}

.empty-users p{
    margin-top:5px;

    color:#8aa3aa;

    font-size:11px;
}


/* =========================================================
   MODAL
========================================================= */

.modal{
    position:fixed;

    inset:0;

    z-index:100;

    display:none;

    align-items:center;

    justify-content:center;

    padding:20px;

    background:
        rgba(32,89,109,.18);

    backdrop-filter:blur(7px);
}

.modal.show{
    display:flex;
}

.modal-card{
    width:min(500px,100%);

    max-height:90vh;

    overflow-y:auto;

    padding:26px;

    border:
        1px solid
        rgba(255,255,255,.95);

    border-radius:24px;

    background:
        rgba(255,255,255,.96);

    box-shadow:
        0 30px 80px
        rgba(55,113,129,.18);
}

.modal-header{
    display:flex;

    align-items:center;

    justify-content:space-between;

    margin-bottom:20px;
}

.modal-header h3{
    color:#315f70;

    font-size:19px;

    font-weight:950;
}

.modal-close{
    width:35px;
    height:35px;

    border:none;

    border-radius:10px;

    color:#7998a1;

    background:
        rgba(120,170,180,.08);

    cursor:pointer;

    font-size:18px;
}

.form-group{
    margin-bottom:15px;
}

.form-label{
    display:block;

    margin-bottom:7px;

    color:#527b87;

    font-size:11px;

    font-weight:900;
}

.form-input{
    width:100%;

    height:46px;

    padding:0 13px;

    outline:none;

    border:
        1px solid
        rgba(180,215,220,.50);

    border-radius:12px;

    color:#416f7e;

    background:
        rgba(248,253,252,.90);

    font-family:inherit;

    font-size:13px;

    font-weight:700;
}

.form-select{
    width:100%;

    height:46px;

    padding:0 13px;

    outline:none;

    border:
        1px solid
        rgba(180,215,220,.50);

    border-radius:12px;

    color:#416f7e;

    background:
        rgba(248,253,252,.90);

    font-family:inherit;

    font-size:13px;

    font-weight:700;

    cursor:pointer;
}

.modal-actions{
    display:flex;

    justify-content:flex-end;

    gap:8px;

    margin-top:20px;
}

.btn-cancel{
    min-height:42px;

    padding:0 15px;

    border:none;

    border-radius:11px;

    color:#6f9099;

    background:
        rgba(120,170,180,.09);

    font-family:inherit;

    font-size:11px;

    font-weight:900;

    cursor:pointer;
}

.btn-save{
    min-height:42px;

    padding:0 17px;

    border:none;

    border-radius:11px;

    color:#fff;

    background:
        linear-gradient(
            135deg,
            #18cfc6,
            #159fae
        );

    font-family:inherit;

    font-size:11px;

    font-weight:900;

    cursor:pointer;
}

.hidden{
    display:none !important;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1100px){

    .mini-stats{
        grid-template-columns:
            repeat(2,1fr);
    }
}

@media(max-width:950px){

    .app{
        flex-direction:column;
    }

    .sidebar{
        width:100%;

        min-height:auto;
    }

    .sidebar-bottom{
        display:none;
    }
}

@media(max-width:700px){

    .topbar{
        align-items:flex-start;

        flex-direction:column;
    }

    .clock-box{
        width:100%;
    }

    .content-card{
        padding:19px;
    }

    .content-heading{
        align-items:flex-start;

        flex-direction:column;
    }

    .mini-stats{
        grid-template-columns:1fr;
    }
}

</style>

</head>


<body>


<div class="app">


<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar">


<div class="sidebar-header">

<div class="logo-container">

<img
    src="Logo.png"
    alt="Logo Asistencia QR"
>

</div>

<div class="sidebar-title">

<strong>
ASISTENCIA QR
</strong>

<small>
Sistema académico
</small>

</div>

</div>


<div class="sidebar-line">
    <span></span>
</div>


<nav class="navigation">


<div class="menu-section">

<div class="menu-label">

<span class="label-line"></span>

NAVEGACIÓN

</div>


<a
    href="dashboard.php"
    class="nav-link"
>

<div class="nav-icon">

<i class="bi bi-grid-1x2"></i>

</div>

<span>
Inicio
</span>

<span class="nav-arrow">
→
</span>

</a>

</div>


<div class="menu-section">

<div class="menu-label">

<span class="label-line"></span>

GESTIÓN ACADÉMICA

</div>


<a
    href="curso_estudiantes.php"
    class="nav-link"
>

<div class="nav-icon academic">

<i class="bi bi-mortarboard"></i>

</div>

<span>
Cursos
</span>

<span class="nav-arrow">
→
</span>

</a>

</div>


<div class="menu-section">

<div class="menu-label">

<span class="label-line"></span>

PERSONAS

</div>


<a
    href="docentes.php"
    class="nav-link"
>

<div class="nav-icon people">

<i class="bi bi-person-workspace"></i>

</div>

<span>
Docentes
</span>

<span class="nav-arrow">
→
</span>

</a>


<a
    href="usuarios.php"
    class="nav-link active"
>

<div class="nav-icon people">

<i class="bi bi-person-badge"></i>

</div>

<span>
Usuarios
</span>

<span class="nav-arrow">
→
</span>

</a>

</div>


<div class="menu-section">

<div class="menu-label">

<span class="label-line"></span>

CONTROL

</div>


<a
    href="asistencia.php"
    class="nav-link"
>

<div class="nav-icon qr-icon">

<i class="bi bi-qr-code-scan"></i>

</div>

<span>
Asistencia
</span>

<span class="nav-arrow">
→
</span>

</a>


<a
    href="restaurante.php"
    class="nav-link"
>

<div class="nav-icon restaurant">

<i class="bi bi-egg-fried"></i>

</div>

<span>
Restaurante
</span>

<span class="nav-arrow">
→
</span>

</a>


<a
    href="reportes.php"
    class="nav-link"
>

<div class="nav-icon reports">

<i class="bi bi-bar-chart-line"></i>

</div>

<span>
Reportes
</span>

<span class="nav-arrow">
→
</span>

</a>


<a
    href="auditoria.php"
    class="nav-link"
>

<div class="nav-icon audit">

<i class="bi bi-shield-check"></i>

</div>

<span>
Auditoría
</span>

<span class="nav-arrow">
→
</span>

</a>

</div>


</nav>


<div class="sidebar-bottom">


<div class="profile-card">

<div class="profile-avatar">

<?= htmlspecialchars($iniciales) ?>

</div>

<div class="profile-info">

<strong>
<?= htmlspecialchars($nombreUsuario) ?>
</strong>

<small>
ADMINISTRADOR
</small>

</div>

<div class="profile-status">
●
</div>

</div>


<a
    href="../auth/logout.php"
    class="logout"
>

<div class="logout-icon">

<i class="bi bi-box-arrow-left"></i>

</div>

<span>
Cerrar sesión
</span>

</a>

</div>


</aside>


<!-- =====================================================
     CONTENIDO
===================================================== -->

<main class="main">


<header class="topbar">


<div class="page-info">

<div class="page-indicator">
</div>

<div class="page-title">

<h1>
Usuarios
</h1>

<p>
Administración de usuarios y permisos del sistema
</p>

</div>

</div>


<div class="clock-box">

<div class="clock-icon">

<i class="bi bi-clock"></i>

</div>

<div>

<div
    class="clock-time"
    id="reloj"
>

<?= $horaActual ?>

</div>

<div class="clock-date">

<?= $fechaActual ?>

</div>

</div>

</div>


</header>


<section class="content-card">


<?php if ($mensaje !== ''): ?>

<div
    class="alert <?= htmlspecialchars($tipoMensaje) ?>"
>

<i class="bi
<?= $tipoMensaje === 'success'
    ? 'bi-check-circle-fill'
    : 'bi-exclamation-circle-fill'
?>"></i>

<?= htmlspecialchars($mensaje) ?>

</div>

<?php endif; ?>


<div class="content-heading">


<div class="heading-left">

<h2>
Usuarios registrados
</h2>

<p>
Administra las cuentas y los permisos de acceso al sistema.
</p>

</div>


<button
    type="button"
    class="btn-primary"
    onclick="abrirModalAgregar()"
>

<i class="bi bi-person-plus-fill"></i>

Agregar usuario

</button>


</div>


<!-- =====================================================
     ESTADÍSTICAS
===================================================== -->

<div class="mini-stats">


<div class="mini-stat">

<div class="mini-stat-icon">

<i class="bi bi-people-fill"></i>

</div>

<div>

<span>
Total usuarios
</span>

<strong>
<?= $totalUsuarios ?>
</strong>

</div>

</div>


<div class="mini-stat">

<div class="mini-stat-icon">

<i class="bi bi-person-check-fill"></i>

</div>

<div>

<span>
Usuarios activos
</span>

<strong>
<?= $totalActivos ?>
</strong>

</div>

</div>


<div class="mini-stat">

<div class="mini-stat-icon">

<i class="bi bi-person-x-fill"></i>

</div>

<div>

<span>
Usuarios inactivos
</span>

<strong>
<?= $totalInactivos ?>
</strong>

</div>

</div>


<div class="mini-stat">

<div class="mini-stat-icon">

<i class="bi bi-shield-lock-fill"></i>

</div>

<div>

<span>
Administradores
</span>

<strong>
<?= $totalAdministradores ?>
</strong>

</div>

</div>


</div>


<!-- =====================================================
     BUSCADOR
===================================================== -->

<div class="search-box">

<i class="bi bi-search"></i>

<input
    type="text"
    id="buscadorUsuarios"
    placeholder="Buscar por nombre, apellido o usuario..."
    autocomplete="off"
>

</div>


<!-- =====================================================
     TABLA
===================================================== -->

<?php if (count($usuarios) > 0): ?>


<div class="table-wrapper">


<table class="users-table">


<thead>

<tr>

<th>
NOMBRE
</th>

<th>
APELLIDO
</th>

<th>
USUARIO
</th>

<th>
ROL
</th>

<th>
ESTADO
</th>

<th>
REGISTRO
</th>

<th>
ACCIONES
</th>

</tr>

</thead>


<tbody id="tablaUsuarios">


<?php foreach ($usuarios as $usuarioFila): ?>


<tr
    data-busqueda="<?= htmlspecialchars(
        strtolower(
            $usuarioFila['nombre']
            . ' '
            . $usuarioFila['apellido']
            . ' '
            . $usuarioFila['usuario']
            . ' '
            . ($usuarioFila['nombre_rol'] ?? '')
        )
    ) ?>"
>


<td class="user-name">

<?= htmlspecialchars(
    $usuarioFila['nombre']
) ?>

</td>


<td class="user-name">

<?= htmlspecialchars(
    $usuarioFila['apellido']
) ?>

</td>


<td class="user-login">

<?= htmlspecialchars(
    $usuarioFila['usuario']
) ?>

</td>


<td>


<?php if (
    (int)$usuarioFila['id_rol'] === 1
): ?>

<span class="role-badge role-admin">

<i class="bi bi-shield-fill-check"></i>

Administrador

</span>


<?php else: ?>

<span class="role-badge role-docente">

<i class="bi bi-person-workspace"></i>

Docente

</span>

<?php endif; ?>


</td>


<td>

<span
    class="user-badge
    <?= $usuarioFila['estado'] === 'ACTIVO'
        ? 'active'
        : 'inactive'
    ?>"
>

<span class="status-dot"></span>

<?= htmlspecialchars(
    $usuarioFila['estado']
) ?>

</span>

</td>


<td>

<?= htmlspecialchars(
    date(
        'd/m/Y',
        strtotime(
            $usuarioFila['fecha_creacion']
        )
    )
) ?>

</td>


<td>


<div class="table-actions">


<!-- EDITAR -->

<button
    type="button"
    class="table-btn btn-edit"
    onclick='editarUsuario(
        <?= json_encode(
            $usuarioFila,
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_QUOT |
            JSON_HEX_AMP
        ) ?>
    )'
>

<i class="bi bi-pencil"></i>

Editar

</button>


<!-- CONTRASEÑA -->

<button
    type="button"
    class="table-btn btn-password"
    onclick='abrirPassword(
        <?= (int)$usuarioFila['id_usuario'] ?>,
        <?= json_encode(
            $usuarioFila['nombre'] . ' ' .
            $usuarioFila['apellido'],
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_QUOT |
            JSON_HEX_AMP
        ) ?>
    )'
>

<i class="bi bi-key-fill"></i>

Contraseña

</button>


<!-- ACTIVAR / DESACTIVAR -->

<form
    method="POST"
    onsubmit="return confirmarEstado(
        <?= (int)$usuarioFila['id_usuario'] ?>,
        '<?= $usuarioFila['estado'] === 'ACTIVO'
            ? 'desactivar'
            : 'activar'
        ?>'
    );"
>

<input
    type="hidden"
    name="id_usuario"
    value="<?= (int)$usuarioFila['id_usuario'] ?>"
>

<input
    type="hidden"
    name="nuevo_estado"
    value="<?= $usuarioFila['estado'] === 'ACTIVO'
        ? 'INACTIVO'
        : 'ACTIVO'
    ?>"
>


<button
    type="submit"
    name="cambiar_estado"
    class="table-btn
    <?= $usuarioFila['estado'] === 'ACTIVO'
        ? 'btn-status-on'
        : 'btn-status-off'
    ?>
    <?= (int)$usuarioFila['id_usuario'] === $idUsuarioSesion
        ? 'btn-disabled'
        : ''
    ?>"
    <?= (int)$usuarioFila['id_usuario'] === $idUsuarioSesion &&
        $usuarioFila['estado'] === 'ACTIVO'
        ? 'disabled'
        : ''
    ?>
>

<i class="bi
<?= $usuarioFila['estado'] === 'ACTIVO'
    ? 'bi-pause-circle'
    : 'bi-play-circle'
?>"></i>

<?= $usuarioFila['estado'] === 'ACTIVO'
    ? 'Desactivar'
    : 'Activar'
?>

</button>

</form>


<!-- ELIMINAR -->

<form
    method="POST"
    onsubmit="return confirmarEliminar(
        <?= (int)$usuarioFila['id_usuario'] ?>,
        '<?= htmlspecialchars(
            $usuarioFila['nombre'],
            ENT_QUOTES
        ) ?>'
    );"
>

<input
    type="hidden"
    name="id_usuario"
    value="<?= (int)$usuarioFila['id_usuario'] ?>"
>


<button
    type="submit"
    name="eliminar_usuario"
    class="table-btn btn-delete
    <?= (int)$usuarioFila['id_usuario'] === $idUsuarioSesion
        ? 'btn-disabled'
        : ''
    ?>"
    <?= (int)$usuarioFila['id_usuario'] === $idUsuarioSesion
        ? 'disabled'
        : ''
    ?>
>

<i class="bi bi-trash"></i>

Eliminar

</button>

</form>


</div>


</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


</div>


<?php else: ?>


<div class="empty-users">

<i class="bi bi-people"></i>

<strong>
No hay usuarios registrados
</strong>

<p>
Agrega el primer usuario usando el botón "Agregar usuario".
</p>

</div>


<?php endif; ?>


</section>


</main>


</div>


<!-- =====================================================
     MODAL AGREGAR
===================================================== -->

<div
    class="modal"
    id="modalAgregar"
>

<div class="modal-card">


<div class="modal-header">

<h3>
Agregar usuario
</h3>

<button
    type="button"
    class="modal-close"
    onclick="cerrarModalAgregar()"
>

<i class="bi bi-x-lg"></i>

</button>

</div>


<form method="POST">


<div class="form-group">

<label
    class="form-label"
>
Nombre
</label>

<input
    type="text"
    class="form-input"
    name="nombre"
    placeholder="Nombre"
    required
>

</div>


<div class="form-group">

<label
    class="form-label"
>
Apellido
</label>

<input
    type="text"
    class="form-input"
    name="apellido"
    placeholder="Apellido"
    required
>

</div>


<div class="form-group">

<label
    class="form-label"
>
Usuario
</label>

<input
    type="text"
    class="form-input"
    name="usuario"
    placeholder="Nombre de usuario"
    required
>

</div>


<div class="form-group">

<label
    class="form-label"
>
Contraseña
</label>

<input
    type="password"
    class="form-input"
    name="password"
    placeholder="Contraseña"
    required
>

</div>


<div class="form-group">

<label
    class="form-label"
>
Rol
</label>

<select
    class="form-select"
    name="id_rol"
    required
>

<option value="">
Seleccionar rol
</option>

<option value="1">
Administrador
</option>

<option value="2">
Docente
</option>

</select>

</div>


<div class="modal-actions">

<button
    type="button"
    class="btn-cancel"
    onclick="cerrarModalAgregar()"
>

Cancelar

</button>


<button
    type="submit"
    name="agregar_usuario"
    class="btn-save"
>

<i class="bi bi-person-plus-fill"></i>

Crear usuario

</button>

</div>


</form>

</div>

</div>


<!-- =====================================================
     MODAL EDITAR
===================================================== -->

<div
    class="modal"
    id="modalEditar"
>

<div class="modal-card">


<div class="modal-header">

<h3>
Editar usuario
</h3>

<button
    type="button"
    class="modal-close"
    onclick="cerrarModalEditar()"
>

<i class="bi bi-x-lg"></i>

</button>

</div>


<form method="POST">


<input
    type="hidden"
    name="id_usuario"
    id="editarIdUsuario"
>


<div class="form-group">

<label class="form-label">
Nombre
</label>

<input
    type="text"
    class="form-input"
    name="nombre"
    id="editarNombre"
    required
>

</div>


<div class="form-group">

<label class="form-label">
Apellido
</label>

<input
    type="text"
    class="form-input"
    name="apellido"
    id="editarApellido"
    required
>

</div>


<div class="form-group">

<label class="form-label">
Usuario
</label>

<input
    type="text"
    class="form-input"
    name="usuario"
    id="editarUsuario"
    required
>

</div>


<div class="form-group">

<label class="form-label">
Rol
</label>

<select
    class="form-select"
    name="id_rol"
    id="editarRol"
    required
>

<option value="1">
Administrador
</option>

<option value="2">
Docente
</option>

</select>

</div>


<div class="modal-actions">

<button
    type="button"
    class="btn-cancel"
    onclick="cerrarModalEditar()"
>

Cancelar

</button>


<button
    type="submit"
    name="editar_usuario"
    class="btn-save"
>

<i class="bi bi-check-lg"></i>

Guardar cambios

</button>

</div>


</form>

</div>

</div>


<!-- =====================================================
     MODAL CONTRASEÑA
===================================================== -->

<div
    class="modal"
    id="modalPassword"
>

<div class="modal-card">


<div class="modal-header">

<h3>
Cambiar contraseña
</h3>

<button
    type="button"
    class="modal-close"
    onclick="cerrarPassword()"
>

<i class="bi bi-x-lg"></i>

</button>

</div>


<form method="POST">


<input
    type="hidden"
    name="id_usuario"
    id="passwordIdUsuario"
>


<div
    style="
        margin-bottom:18px;
        padding:13px;
        border-radius:13px;
        background:rgba(133,121,210,.07);
        color:#7569c2;
        font-size:12px;
        font-weight:800;
    "
>

<i class="bi bi-person"></i>

Usuario:

<strong id="passwordNombre">
</strong>

</div>


<div class="form-group">

<label class="form-label">

Nueva contraseña

</label>

<input
    type="password"
    class="form-input"
    name="password_nueva"
    placeholder="Escribe la nueva contraseña"
    required
>

</div>


<div class="modal-actions">

<button
    type="button"
    class="btn-cancel"
    onclick="cerrarPassword()"
>

Cancelar

</button>


<button
    type="submit"
    name="cambiar_password"
    class="btn-save"
>

<i class="bi bi-key-fill"></i>

Actualizar contraseña

</button>

</div>


</form>

</div>

</div>


<script>

/* =========================================================
   RELOJ
========================================================= */

function actualizarReloj(){

    const ahora = new Date();

    const horas =
        String(
            ahora.getHours()
        ).padStart(2,'0');

    const minutos =
        String(
            ahora.getMinutes()
        ).padStart(2,'0');

    const segundos =
        String(
            ahora.getSeconds()
        ).padStart(2,'0');

    const reloj =
        document.getElementById(
            'reloj'
        );

    if(reloj){

        reloj.textContent =
            horas + ':' +
            minutos + ':' +
            segundos;
    }
}

actualizarReloj();

setInterval(
    actualizarReloj,
    1000
);


/* =========================================================
   BUSCADOR
========================================================= */

const buscadorUsuarios =
    document.getElementById(
        'buscadorUsuarios'
    );

if(buscadorUsuarios){

    buscadorUsuarios.addEventListener(
        'input',
        function(){

            const texto =
                this.value
                .toLowerCase()
                .trim();

            const filas =
                document.querySelectorAll(
                    '#tablaUsuarios tr'
                );

            filas.forEach(
                function(fila){

                    const busqueda =
                        fila.dataset.busqueda ||
                        '';

                    if(
                        busqueda.includes(texto)
                    ){

                        fila.classList.remove(
                            'hidden'
                        );

                    }else{

                        fila.classList.add(
                            'hidden'
                        );
                    }

                }
            );
        }
    );
}


/* =========================================================
   MODAL AGREGAR
========================================================= */

function abrirModalAgregar(){

    const modal =
        document.getElementById(
            'modalAgregar'
        );

    if(modal){

        modal.classList.add(
            'show'
        );

        setTimeout(
            function(){

                const input =
                    modal.querySelector(
                        'input[name="nombre"]'
                    );

                if(input){

                    input.focus();
                }

            },
            100
        );
    }
}

function cerrarModalAgregar(){

    const modal =
        document.getElementById(
            'modalAgregar'
        );

    if(modal){

        modal.classList.remove(
            'show'
        );
    }
}


/* =========================================================
   EDITAR USUARIO
========================================================= */

function editarUsuario(
    usuario
){

    const modal =
        document.getElementById(
            'modalEditar'
        );

    if(!modal){

        return;
    }

    document.getElementById(
        'editarIdUsuario'
    ).value =
        usuario.id_usuario;

    document.getElementById(
        'editarNombre'
    ).value =
        usuario.nombre;

    document.getElementById(
        'editarApellido'
    ).value =
        usuario.apellido;

    document.getElementById(
        'editarUsuario'
    ).value =
        usuario.usuario;

    document.getElementById(
        'editarRol'
    ).value =
        usuario.id_rol;

    modal.classList.add(
        'show'
    );
}

function cerrarModalEditar(){

    const modal =
        document.getElementById(
            'modalEditar'
        );

    if(modal){

        modal.classList.remove(
            'show'
        );
    }
}


/* =========================================================
   CONTRASEÑA
========================================================= */

function abrirPassword(
    idUsuario,
    nombre
){

    const modal =
        document.getElementById(
            'modalPassword'
        );

    if(!modal){

        return;
    }

    document.getElementById(
        'passwordIdUsuario'
    ).value =
        idUsuario;

    document.getElementById(
        'passwordNombre'
    ).textContent =
        nombre;

    modal.classList.add(
        'show'
    );

    setTimeout(
        function(){

            const input =
                modal.querySelector(
                    'input[name="password_nueva"]'
                );

            if(input){

                input.focus();
            }

        },
        100
    );
}

function cerrarPassword(){

    const modal =
        document.getElementById(
            'modalPassword'
        );

    if(modal){

        modal.classList.remove(
            'show'
        );
    }
}


/* =========================================================
   CONFIRMACIONES
========================================================= */

function confirmarEstado(
    idUsuario,
    accion
){

    if(
        Number(idUsuario) ===
        <?= $idUsuarioSesion ?>
    &&
        accion === 'desactivar'
    ){

        return false;
    }

    return confirm(
        accion === 'desactivar'
        ? '¿Seguro que deseas desactivar este usuario?'
        : '¿Deseas activar este usuario?'
    );
}


function confirmarEliminar(
    idUsuario,
    nombre
){

    if(
        Number(idUsuario) ===
        <?= $idUsuarioSesion ?>
    ){

        return false;
    }

    return confirm(
        '¿Seguro que deseas eliminar al usuario "' +
        nombre +
        '"? Esta acción no se puede deshacer.'
    );
}


/* =========================================================
   CERRAR MODALES AL HACER CLICK AFUERA
========================================================= */

document.addEventListener(
    'click',
    function(event){

        const modalAgregar =
            document.getElementById(
                'modalAgregar'
            );

        const modalEditar =
            document.getElementById(
                'modalEditar'
            );

        const modalPassword =
            document.getElementById(
                'modalPassword'
            );


        if(
            modalAgregar &&
            event.target === modalAgregar
        ){

            cerrarModalAgregar();
        }


        if(
            modalEditar &&
            event.target === modalEditar
        ){

            cerrarModalEditar();
        }


        if(
            modalPassword &&
            event.target === modalPassword
        ){

            cerrarPassword();
        }

    }
);


/* =========================================================
   ESC
========================================================= */

document.addEventListener(
    'keydown',
    function(event){

        if(
            event.key === 'Escape'
        ){

            cerrarModalAgregar();

            cerrarModalEditar();

            cerrarPassword();
        }

    }
);

</script>


</body>

</html>
