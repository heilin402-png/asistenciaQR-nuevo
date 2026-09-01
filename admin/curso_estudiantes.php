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

/* =========================================================
   CURSO SELECCIONADO
========================================================= */

$idCursoSeleccionado = isset($_GET['curso'])
    ? (int)$_GET['curso']
    : 0;

$cursoSeleccionado = null;

/* =========================================================
   AGREGAR CURSO
========================================================= */

if (isset($_POST['agregar_curso'])) {

    $nombreCurso = trim($_POST['nombre_curso'] ?? '');

    if ($nombreCurso !== '') {

        $sql = "INSERT INTO cursos
                (nombre_curso, estado, fecha_creacion)
                VALUES (?, 'ACTIVO', NOW())";

        $stmt = mysqli_prepare($conexion, $sql);

        if ($stmt) {

            mysqli_stmt_bind_param(
                $stmt,
                "s",
                $nombreCurso
            );

            if (mysqli_stmt_execute($stmt)) {

                $mensaje = 'Curso creado correctamente.';
                $tipoMensaje = 'success';

            } else {

                $mensaje = 'No fue posible crear el curso.';
                $tipoMensaje = 'error';
            }

            mysqli_stmt_close($stmt);
        }

    } else {

        $mensaje = 'Escribe el nombre del curso.';
        $tipoMensaje = 'error';
    }
}

/* =========================================================
   AGREGAR ESTUDIANTE
========================================================= */

if (isset($_POST['agregar_estudiante'])) {

    $idCurso = (int)($_POST['id_curso'] ?? 0);
    $documento = trim($_POST['documento'] ?? '');
    $nombres = trim($_POST['nombres'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');

    if (
        $idCurso > 0 &&
        $documento !== '' &&
        $nombres !== '' &&
        $apellidos !== ''
    ) {

        $sql = "INSERT INTO estudiantes
                (
                    id_curso,
                    documento,
                    nombres,
                    apellidos,
                    estado,
                    fecha_creacion
                )
                VALUES (?, ?, ?, ?, 'ACTIVO', NOW())";

        $stmt = mysqli_prepare($conexion, $sql);

        if ($stmt) {

            mysqli_stmt_bind_param(
                $stmt,
                "isss",
                $idCurso,
                $documento,
                $nombres,
                $apellidos
            );

            if (mysqli_stmt_execute($stmt)) {

                $mensaje = 'Estudiante agregado correctamente.';
                $tipoMensaje = 'success';

                $idCursoSeleccionado = $idCurso;

            } else {

                $mensaje = 'No fue posible agregar el estudiante.';
                $tipoMensaje = 'error';
            }

            mysqli_stmt_close($stmt);
        }

    } else {

        $mensaje = 'Completa todos los datos del estudiante.';
        $tipoMensaje = 'error';
    }
}

/* =========================================================
   EDITAR ESTUDIANTE
========================================================= */

if (isset($_POST['editar_estudiante'])) {

    $idEstudiante = (int)($_POST['id_estudiante'] ?? 0);
    $idCurso = (int)($_POST['id_curso'] ?? 0);

    $documento = trim($_POST['documento'] ?? '');
    $nombres = trim($_POST['nombres'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');

    if (
        $idEstudiante > 0 &&
        $documento !== '' &&
        $nombres !== '' &&
        $apellidos !== ''
    ) {

        $sql = "UPDATE estudiantes
                SET
                    documento = ?,
                    nombres = ?,
                    apellidos = ?
                WHERE id_estudiante = ?";

        $stmt = mysqli_prepare($conexion, $sql);

        if ($stmt) {

            mysqli_stmt_bind_param(
                $stmt,
                "sssi",
                $documento,
                $nombres,
                $apellidos,
                $idEstudiante
            );

            if (mysqli_stmt_execute($stmt)) {

                $mensaje = 'Estudiante actualizado correctamente.';
                $tipoMensaje = 'success';

                if ($idCurso > 0) {
                    $idCursoSeleccionado = $idCurso;
                }

            } else {

                $mensaje = 'No fue posible actualizar el estudiante.';
                $tipoMensaje = 'error';
            }

            mysqli_stmt_close($stmt);
        }

    } else {

        $mensaje = 'Completa todos los datos del estudiante.';
        $tipoMensaje = 'error';
    }
}

/* =========================================================
   CAMBIAR ESTADO DEL ESTUDIANTE
========================================================= */

if (isset($_POST['cambiar_estado_estudiante'])) {

    $idEstudiante = (int)($_POST['id_estudiante'] ?? 0);
    $idCurso = (int)($_POST['id_curso'] ?? 0);

    $nuevoEstado =
        ($_POST['nuevo_estado'] ?? '') === 'ACTIVO'
        ? 'ACTIVO'
        : 'INACTIVO';

    if ($idEstudiante > 0) {

        $sql = "UPDATE estudiantes
                SET estado = ?
                WHERE id_estudiante = ?";

        $stmt = mysqli_prepare($conexion, $sql);

        if ($stmt) {

            mysqli_stmt_bind_param(
                $stmt,
                "si",
                $nuevoEstado,
                $idEstudiante
            );

            if (mysqli_stmt_execute($stmt)) {

                $mensaje =
                    $nuevoEstado === 'ACTIVO'
                    ? 'Estudiante activado correctamente.'
                    : 'Estudiante desactivado correctamente.';

                $tipoMensaje = 'success';

                if ($idCurso > 0) {
                    $idCursoSeleccionado = $idCurso;
                }

            } else {

                $mensaje = 'No fue posible cambiar el estado.';
                $tipoMensaje = 'error';
            }

            mysqli_stmt_close($stmt);
        }
    }
}

/* =========================================================
   ELIMINAR ESTUDIANTE
========================================================= */

if (isset($_POST['eliminar_estudiante'])) {

    $idEstudiante = (int)($_POST['id_estudiante'] ?? 0);
    $idCurso = (int)($_POST['id_curso'] ?? 0);

    if ($idEstudiante > 0) {

        $sql = "DELETE FROM estudiantes
                WHERE id_estudiante = ?";

        $stmt = mysqli_prepare($conexion, $sql);

        if ($stmt) {

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $idEstudiante
            );

            if (mysqli_stmt_execute($stmt)) {

                $mensaje = 'Estudiante eliminado correctamente.';
                $tipoMensaje = 'success';

                if ($idCurso > 0) {
                    $idCursoSeleccionado = $idCurso;
                }

            } else {

                $mensaje = 'No fue posible eliminar el estudiante.';
                $tipoMensaje = 'error';
            }

            mysqli_stmt_close($stmt);
        }
    }
}

/* =========================================================
   OBTENER CURSO SELECCIONADO
========================================================= */

if ($idCursoSeleccionado > 0) {

    $sqlCurso = "SELECT
                    id_curso,
                    nombre_curso,
                    estado,
                    fecha_creacion
                 FROM cursos
                 WHERE id_curso = ?
                 LIMIT 1";

    $stmtCurso = mysqli_prepare(
        $conexion,
        $sqlCurso
    );

    if ($stmtCurso) {

        mysqli_stmt_bind_param(
            $stmtCurso,
            "i",
            $idCursoSeleccionado
        );

        mysqli_stmt_execute($stmtCurso);

        $resultadoCurso =
            mysqli_stmt_get_result($stmtCurso);

        $cursoSeleccionado =
            mysqli_fetch_assoc($resultadoCurso);

        mysqli_stmt_close($stmtCurso);
    }

    if (!$cursoSeleccionado) {

        $idCursoSeleccionado = 0;

        $mensaje = 'El curso seleccionado no existe.';
        $tipoMensaje = 'error';
    }
}

/* =========================================================
   OBTENER TODOS LOS CURSOS
========================================================= */

$cursos = [];

$sqlCursos = "SELECT
                c.id_curso,
                c.nombre_curso,
                c.estado,
                c.fecha_creacion,
                COUNT(e.id_estudiante) AS total_estudiantes
              FROM cursos c

              LEFT JOIN estudiantes e
                ON e.id_curso = c.id_curso

              GROUP BY
                c.id_curso,
                c.nombre_curso,
                c.estado,
                c.fecha_creacion

              ORDER BY
                CAST(c.nombre_curso AS UNSIGNED) ASC,
                c.nombre_curso ASC";

$resultadoCursos =
    mysqli_query($conexion, $sqlCursos);

if ($resultadoCursos) {

    while ($fila = mysqli_fetch_assoc($resultadoCursos)) {

        $cursos[] = $fila;
    }
}

/* =========================================================
   OBTENER ESTUDIANTES DEL CURSO
========================================================= */

$estudiantes = [];

if ($cursoSeleccionado) {

    $sqlEstudiantes = "SELECT
                            id_estudiante,
                            id_curso,
                            documento,
                            nombres,
                            apellidos,
                            estado,
                            fecha_creacion

                       FROM estudiantes

                       WHERE id_curso = ?

                       ORDER BY
                            nombres ASC,
                            apellidos ASC";

    $stmtEstudiantes = mysqli_prepare(
        $conexion,
        $sqlEstudiantes
    );

    if ($stmtEstudiantes) {

        mysqli_stmt_bind_param(
            $stmtEstudiantes,
            "i",
            $idCursoSeleccionado
        );

        mysqli_stmt_execute($stmtEstudiantes);

        $resultadoEstudiantes =
            mysqli_stmt_get_result(
                $stmtEstudiantes
            );

        while (
            $fila =
            mysqli_fetch_assoc(
                $resultadoEstudiantes
            )
        ) {

            $estudiantes[] = $fila;
        }

        mysqli_stmt_close($stmtEstudiantes);
    }
}

/* =========================================================
   ESTADÍSTICAS
========================================================= */

$totalCursos = count($cursos);

$totalActivos = 0;
$totalInactivos = 0;

foreach ($cursos as $curso) {

    if ($curso['estado'] === 'ACTIVO') {

        $totalActivos++;

    } else {

        $totalInactivos++;
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

<title>Asistencia QR | Cursos</title>

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>

<style>

.btn-qr{
    color:#087d92;
    background:rgba(24,216,206,.12);
}

.btn-qr:hover{
    background:rgba(24,216,206,.20);
}

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

.app{
    position:relative;
    z-index:1;

    display:flex;
    gap:18px;

    min-height:100vh;

    padding:18px;
}

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
    background:rgba(242,143,150,.08);
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

.main{
    flex:1;
    min-width:0;

    display:flex;
    flex-direction:column;
    gap:18px;
}

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
    background:rgba(24,216,206,.10);

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

.mini-stats{
    display:grid;
    grid-template-columns:repeat(3,1fr);

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

.mini-stat:nth-child(1) .mini-stat-icon{
    color:#0b9f9c;
    background:rgba(24,216,206,.10);
}

.mini-stat:nth-child(2) .mini-stat-icon{
    color:#28a579;
    background:rgba(66,205,161,.11);
}

.mini-stat:nth-child(3) .mini-stat-icon{
    color:#7569c2;
    background:rgba(133,121,210,.11);
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

    padding:0 16px 0 45px;

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

.course-grid{
    display:grid;

    grid-template-columns:
        repeat(
            auto-fill,
            minmax(235px,1fr)
        );

    gap:15px;
}

.course-card{
    position:relative;
    overflow:hidden;

    padding:18px;

    border:
        1px solid
        rgba(255,255,255,.92);

    border-radius:20px;

    background:
        rgba(255,255,255,.70);

    box-shadow:
        0 12px 30px
        rgba(55,113,129,.055);

    transition:.22s;
}

.course-card:hover{
    transform:translateY(-4px);
}

.course-top{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;

    gap:12px;
}

.course-number{
    width:51px;
    height:51px;

    display:flex;
    align-items:center;
    justify-content:center;

    flex-shrink:0;

    border-radius:15px;

    color:#087d92;

    background:
        linear-gradient(
            145deg,
            rgba(24,216,206,.13),
            rgba(105,184,213,.12)
        );

    font-size:17px;
    font-weight:950;
}

.course-name{
    margin-top:12px;

    color:#315f70;

    font-size:14px;
    font-weight:900;
}

.course-status{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;

    height:30px;
    padding:0 11px;

    border-radius:9px;

    font-size:9px;
    font-weight:950;

    letter-spacing:.4px;
    white-space:nowrap;
}

.status-dot{
    width:7px;
    height:7px;

    min-width:7px;

    border-radius:50%;

    display:inline-block;
    flex:none;
}

.course-status.active{
    color:#218b6c;
    background:rgba(66,205,161,.11);
}

.course-status.inactive{
    color:#aa7279;
    background:rgba(242,143,150,.11);
}

.course-status.active .status-dot{
    background:#42cda1;
}

.course-status.inactive .status-dot{
    background:#e99a9f;
}

.course-students{
    display:flex;
    align-items:center;
    gap:8px;

    margin-top:16px;
    padding:9px 10px;

    border-radius:11px;

    color:#668995;

    background:
        rgba(24,216,206,.055);

    font-size:11px;
    font-weight:800;
}

.course-students i{
    color:#0b9f9c;
    font-size:15px;
}

.course-actions{
    display:grid;
    grid-template-columns:1fr;

    gap:8px;
    margin-top:14px;
}

.course-action{
    min-height:39px;

    display:flex;
    align-items:center;
    justify-content:center;
    gap:6px;

    border:none;
    border-radius:11px;

    text-decoration:none;

    font-family:inherit;
    font-size:10px;
    font-weight:900;

    cursor:pointer;
    transition:.2s;
}

.action-view{
    color:#087d92;
    background:rgba(24,216,206,.10);
}

.action-view:hover{
    background:rgba(24,216,206,.17);
}

.back-link{
    display:inline-flex;
    align-items:center;
    gap:7px;

    margin-bottom:17px;

    color:#168f92;
    text-decoration:none;

    font-size:12px;
    font-weight:900;
}

.selected-course{
    display:flex;
    align-items:center;
    gap:15px;

    padding:17px;
    margin-bottom:18px;

    border-radius:17px;

    background:
        linear-gradient(
            135deg,
            rgba(24,216,206,.08),
            rgba(133,121,210,.06)
        );

    border:
        1px solid
        rgba(255,255,255,.90);
}

.selected-icon{
    width:56px;
    height:56px;

    display:flex;
    align-items:center;
    justify-content:center;

    border-radius:16px;

    color:#087d92;

    background:
        rgba(24,216,206,.11);

    font-size:23px;
}

.selected-info{
    flex:1;
}

.selected-info strong{
    display:block;
    color:#315f70;
    font-size:21px;
    font-weight:950;
}

.selected-info small{
    display:block;
    margin-top:4px;
    color:#849fa7;
    font-size:11px;
    font-weight:750;
}

.table-wrapper{
    overflow-x:auto;
    border-radius:17px;
}

.students-table{
    width:100%;
    min-width:950px;

    border-collapse:collapse;
}

.students-table thead{
    background:
        rgba(24,216,206,.055);
}

.students-table th{
    padding:13px 14px;

    text-align:center;
    vertical-align:middle;

    color:#668995;

    font-size:10px;
    font-weight:950;

    letter-spacing:.4px;
}

.students-table td{
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

.student-name{
    color:#416f7e !important;
    font-weight:900 !important;
    text-align:center !important;
}

.student-badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:5px;

    padding:5px 8px;

    border-radius:8px;

    font-size:9px;
    font-weight:900;
}

.student-badge.active{
    color:#218b6c;
    background:rgba(66,205,161,.10);
}

.student-badge.inactive{
    color:#aa7279;
    background:rgba(242,143,150,.10);
}

.table-actions{
    display:flex;
    align-items:center;
    justify-content:center;

    gap:8px;
    flex-wrap:wrap;
}

.table-btn{
    min-height:40px;

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
    background:rgba(24,216,206,.10);
}

.btn-qr{
    color:#7569c2;
    background:rgba(133,121,210,.12);
}

.btn-qr:hover{
    background:rgba(133,121,210,.19);
}

.btn-status-on,
.btn-delete{
    color:#b45e68;
    background:rgba(242,143,150,.10);
}

.btn-status-off{
    color:#218b6c;
    background:rgba(66,205,161,.11);
}

.empty-students{
    padding:50px 20px;
    text-align:center;
}

.empty-students i{
    display:block;
    margin-bottom:10px;

    color:#8dc6c5;
    font-size:40px;
}

.empty-students strong{
    color:#527b87;
    font-size:15px;
}

.empty-students p{
    margin-top:5px;
    color:#8aa3aa;
    font-size:11px;
}

/* =========================================================
   MODALES GENERALES
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

    animation:
        modalEntrada .22s ease;
}

@keyframes modalEntrada{

    from{
        opacity:0;
        transform:translateY(15px) scale(.97);
    }

    to{
        opacity:1;
        transform:translateY(0) scale(1);
    }
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

/* =========================================================
   MODAL QR
========================================================= */

.qr-modal-card{
    width:min(480px,100%);

    padding:0;

    overflow:hidden;

    border-radius:28px;

    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,.98),
            rgba(239,252,250,.98)
        );

    border:
        1px solid
        rgba(255,255,255,.98);

    box-shadow:
        0 35px 90px
        rgba(40,100,115,.24);
}

.qr-modal-top{
    position:relative;

    padding:25px 25px 20px;

    text-align:center;

    background:
        linear-gradient(
            135deg,
            rgba(24,216,206,.13),
            rgba(133,121,210,.10)
        );
}

.qr-modal-close{
    position:absolute;

    top:16px;
    right:16px;

    width:38px;
    height:38px;

    display:flex;
    align-items:center;
    justify-content:center;

    border:none;
    border-radius:12px;

    color:#668995;

    background:
        rgba(255,255,255,.75);

    cursor:pointer;

    font-size:17px;

    transition:.2s;
}

.qr-modal-close:hover{
    background:#fff;
    transform:rotate(4deg);
}

.qr-title-icon{
    width:52px;
    height:52px;

    display:flex;
    align-items:center;
    justify-content:center;

    margin:0 auto 10px;

    border-radius:16px;

    color:#087d92;

    background:
        rgba(255,255,255,.78);

    box-shadow:
        0 10px 25px
        rgba(55,113,129,.08);

    font-size:25px;
}

.qr-modal-top h2{
    color:#245d70;

    font-size:21px;
    font-weight:950;
}

.qr-modal-top p{
    margin-top:5px;

    color:#7b9aa3;

    font-size:11px;
    font-weight:700;
}

.qr-content{
    padding:24px;
    text-align:center;
}

.qr-image-box{
    width:250px;
    height:250px;

    display:flex;
    align-items:center;
    justify-content:center;

    margin:0 auto 18px;

    padding:15px;

    border-radius:23px;

    background:#fff;

    border:
        1px solid
        rgba(180,215,220,.30);

    box-shadow:
        0 18px 45px
        rgba(55,113,129,.11);
}

.qr-image-box img{
    width:100%;
    height:100%;

    display:block;

    object-fit:contain;
}

.qr-student-name{
    color:#315f70;

    font-size:19px;
    font-weight:950;
}

.qr-data-grid{
    display:grid;

    grid-template-columns:repeat(2,1fr);

    gap:10px;

    margin-top:17px;
}

.qr-data{
    padding:12px;

    text-align:left;

    border-radius:13px;

    background:
        rgba(255,255,255,.72);

    border:
        1px solid
        rgba(180,215,220,.20);
}

.qr-data-label{
    display:block;

    margin-bottom:4px;

    color:#91a8ae;

    font-size:9px;
    font-weight:900;

    text-transform:uppercase;
    letter-spacing:.6px;
}

.qr-data-value{
    display:block;

    overflow:hidden;

    color:#4a7784;

    font-size:12px;
    font-weight:900;

    white-space:nowrap;
    text-overflow:ellipsis;
}

.qr-status{
    display:inline-flex;

    align-items:center;
    gap:6px;

    margin-top:14px;

    padding:7px 12px;

    border-radius:10px;

    font-size:10px;
    font-weight:900;
}

.qr-status.active{
    color:#218b6c;
    background:rgba(66,205,161,.11);
}

.qr-status.inactive{
    color:#aa7279;
    background:rgba(242,143,150,.11);
}

.qr-modal-actions{
    display:flex;

    gap:9px;

    margin-top:20px;
}

.qr-action{
    flex:1;

    min-height:45px;

    display:flex;
    align-items:center;
    justify-content:center;

    gap:7px;

    border:none;
    border-radius:13px;

    font-family:inherit;

    font-size:11px;
    font-weight:900;

    cursor:pointer;

    transition:.2s;
}

.qr-action:hover{
    transform:translateY(-2px);
}

.qr-print{
    color:#fff;

    background:
        linear-gradient(
            135deg,
            #18cfc6,
            #159fae
        );

    box-shadow:
        0 9px 20px
        rgba(24,216,206,.16);
}

.qr-close-action{
    color:#6f9099;

    background:
        rgba(120,170,180,.09);
}

.hidden{
    display:none !important;
}

/* =========================================================
   RESPONSIVE
========================================================= */

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

    .course-grid{
        grid-template-columns:1fr;
    }

    .selected-course{
        align-items:flex-start;
        flex-wrap:wrap;
    }

    .qr-modal-card{
        width:100%;
    }

    .qr-image-box{
        width:220px;
        height:220px;
    }

    .qr-data-grid{
        grid-template-columns:1fr;
    }

    .qr-modal-actions{
        flex-direction:column;
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

<strong>ASISTENCIA QR</strong>

<small>Sistema académico</small>

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

<span>Inicio</span>

<span class="nav-arrow">→</span>

</a>

</div>

<div class="menu-section">

<div class="menu-label">

<span class="label-line"></span>

GESTIÓN ACADÉMICA

</div>

<a
    href="curso_estudiantes.php"
    class="nav-link active"
>

<div class="nav-icon academic">

<i class="bi bi-mortarboard"></i>

</div>

<span>Cursos</span>

<span class="nav-arrow">→</span>

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

<span>Docentes</span>

<span class="nav-arrow">→</span>

</a>

<a
    href="usuarios.php"
    class="nav-link"
>

<div class="nav-icon people">

<i class="bi bi-person-badge"></i>

</div>

<span>Usuarios</span>

<span class="nav-arrow">→</span>

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

<span>Asistencia</span>

<span class="nav-arrow">→</span>

</a>

<a
    href="restaurante.php"
    class="nav-link"
>

<div class="nav-icon restaurant">

<i class="bi bi-egg-fried"></i>

</div>

<span>Restaurante</span>

<span class="nav-arrow">→</span>

</a>

<a
    href="reportes.php"
    class="nav-link"
>

<div class="nav-icon reports">

<i class="bi bi-bar-chart-line"></i>

</div>

<span>Reportes</span>

<span class="nav-arrow">→</span>

</a>

<a
    href="auditoria.php"
    class="nav-link"
>

<div class="nav-icon audit">

<i class="bi bi-shield-check"></i>

</div>

<span>Auditoría</span>

<span class="nav-arrow">→</span>

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

<span>Cerrar sesión</span>

</a>

</div>

</aside>

<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">

<header class="topbar">

<div class="page-info">

<div class="page-indicator"></div>

<div class="page-title">

<h1>
Cursos
</h1>

<p>
Administración de cursos y estudiantes
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

<?php if (!$cursoSeleccionado): ?>

<div class="content-heading">

<div class="heading-left">

<h2>
Seleccionar curso
</h2>

<p>
Selecciona un curso para administrar sus estudiantes.
</p>

</div>

<button
    type="button"
    class="btn-primary"
    onclick="abrirModalCurso()"
>

<i class="bi bi-plus-lg"></i>

Agregar curso

</button>

</div>

<div class="mini-stats">

<div class="mini-stat">

<div class="mini-stat-icon">

<i class="bi bi-mortarboard-fill"></i>

</div>

<div>

<span>
Total cursos
</span>

<strong>
<?= $totalCursos ?>
</strong>

</div>

</div>

<div class="mini-stat">

<div class="mini-stat-icon">

<i class="bi bi-check-circle-fill"></i>

</div>

<div>

<span>
Cursos activos
</span>

<strong>
<?= $totalActivos ?>
</strong>

</div>

</div>

<div class="mini-stat">

<div class="mini-stat-icon">

<i class="bi bi-pause-circle-fill"></i>

</div>

<div>

<span>
Cursos inactivos
</span>

<strong>
<?= $totalInactivos ?>
</strong>

</div>

</div>

</div>

<div class="search-box">

<i class="bi bi-search"></i>

<input
    type="text"
    id="buscadorCursos"
    placeholder="Buscar curso por número..."
    autocomplete="off"
>

</div>

<div
    class="course-grid"
    id="listaCursos"
>

<?php foreach ($cursos as $curso): ?>

<div
    class="course-card"
    data-curso="<?= htmlspecialchars(
        strtolower($curso['nombre_curso'])
    ) ?>"
>

<div class="course-top">

<div class="course-number">

<?= htmlspecialchars(
    $curso['nombre_curso']
) ?>

</div>

<div
    class="course-status
    <?= $curso['estado'] === 'ACTIVO'
        ? 'active'
        : 'inactive'
    ?>"
>

<span class="status-dot"></span>

<?= htmlspecialchars(
    $curso['estado']
) ?>

</div>

</div>

<div class="course-name">

Curso
<?= htmlspecialchars(
    $curso['nombre_curso']
) ?>

</div>

<div class="course-students">

<i class="bi bi-people-fill"></i>

<?= (int)$curso['total_estudiantes'] ?>

estudiante<?= (int)$curso['total_estudiantes'] === 1
    ? ''
    : 's'
?>

</div>

<div class="course-actions">

<a
    href="curso_estudiantes.php?curso=<?= (int)$curso['id_curso'] ?>"
    class="course-action action-view"
>

<i class="bi bi-people"></i>

Ver estudiantes

</a>

</div>

</div>

<?php endforeach; ?>

</div>

<?php if (count($cursos) === 0): ?>

<div class="empty-students">

<i class="bi bi-mortarboard"></i>

<strong>
No hay cursos registrados
</strong>

<p>
Agrega el primer curso usando el botón "Agregar curso".
</p>

</div>

<?php endif; ?>

<?php else: ?>

<a
    href="curso_estudiantes.php"
    class="back-link"
>

<i class="bi bi-arrow-left"></i>

Volver a cursos

</a>

<div class="selected-course">

<div class="selected-icon">

<i class="bi bi-mortarboard-fill"></i>

</div>

<div class="selected-info">

<strong>

Curso
<?= htmlspecialchars(
    $cursoSeleccionado['nombre_curso']
) ?>

</strong>

<small>

<?= count($estudiantes) ?>

estudiante<?= count($estudiantes) === 1
    ? ''
    : 's'
?>

·

<?= htmlspecialchars(
    $cursoSeleccionado['estado']
) ?>

·

Creado el
<?= htmlspecialchars(
    date(
        'd/m/Y',
        strtotime(
            $cursoSeleccionado['fecha_creacion']
        )
    )
) ?>

</small>

</div>

<div
    class="course-status
    <?= $cursoSeleccionado['estado'] === 'ACTIVO'
        ? 'active'
        : 'inactive'
    ?>"
>

<span class="status-dot"></span>

<?= htmlspecialchars(
    $cursoSeleccionado['estado']
) ?>

</div>

</div>

<div
    style="
        display:flex;
        justify-content:flex-end;
        margin-bottom:18px;
    "
>

<button
    type="button"
    class="btn-primary"
    onclick="abrirModalEstudiante()"
>

<i class="bi bi-person-plus-fill"></i>

Agregar estudiante

</button>

</div>

<?php if (count($estudiantes) > 0): ?>

<div class="table-wrapper">

<table class="students-table">

<thead>

<tr>

<th>
DOCUMENTO
</th>

<th>
ESTUDIANTE
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

<tbody>

<?php foreach ($estudiantes as $estudiante): ?>

<tr>

<td>

<?= htmlspecialchars(
    $estudiante['documento']
) ?>

</td>

<td class="student-name">

<?= htmlspecialchars(
    trim(
        $estudiante['nombres']
        . ' '
        . $estudiante['apellidos']
    )
) ?>

</td>

<td>

<span
    class="student-badge
    <?= $estudiante['estado'] === 'ACTIVO'
        ? 'active'
        : 'inactive'
    ?>"
>

<span class="status-dot"></span>

<?= htmlspecialchars(
    $estudiante['estado']
) ?>

</span>

</td>

<td>

<?= htmlspecialchars(
    date(
        'd/m/Y',
        strtotime(
            $estudiante['fecha_creacion']
        )
    )
) ?>

</td>

<td>

<div class="table-actions">

<!-- =====================================================
     BOTÓN VER QR
===================================================== -->

<a
    href="qr_estudiante.php?id=<?= (int)$estudiante['id_estudiante'] ?>"
    class="table-btn btn-qr"
>
    <i class="bi bi-qr-code"></i>
    Ver QR
</a>

<!-- =====================================================
     EDITAR
===================================================== -->

<button
    type="button"
    class="table-btn btn-edit"
    onclick='editarEstudiante(
        <?= json_encode(
            $estudiante,
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

<!-- =====================================================
     CAMBIAR ESTADO
===================================================== -->

<form
    method="POST"
    style="margin:0;"
>

<input
    type="hidden"
    name="id_estudiante"
    value="<?= (int)$estudiante['id_estudiante'] ?>"
>

<input
    type="hidden"
    name="id_curso"
    value="<?= (int)$cursoSeleccionado['id_curso'] ?>"
>

<input
    type="hidden"
    name="nuevo_estado"
    value="<?= $estudiante['estado'] === 'ACTIVO'
        ? 'INACTIVO'
        : 'ACTIVO'
    ?>"
>

<button
    type="submit"
    name="cambiar_estado_estudiante"
    class="table-btn
    <?= $estudiante['estado'] === 'ACTIVO'
        ? 'btn-status-on'
        : 'btn-status-off'
    ?>"
>

<i class="bi
<?= $estudiante['estado'] === 'ACTIVO'
    ? 'bi-pause-circle'
    : 'bi-play-circle'
?>"></i>

<?= $estudiante['estado'] === 'ACTIVO'
    ? 'Desactivar'
    : 'Activar'
?>

</button>

</form>

<!-- =====================================================
     ELIMINAR
===================================================== -->

<form
    method="POST"
    style="margin:0;"
    onsubmit="
        return confirm(
            '¿Seguro que deseas eliminar este estudiante?'
        );
    "
>

<input
    type="hidden"
    name="id_estudiante"
    value="<?= (int)$estudiante['id_estudiante'] ?>"
>

<input
    type="hidden"
    name="id_curso"
    value="<?= (int)$cursoSeleccionado['id_curso'] ?>"
>

<button
    type="submit"
    name="eliminar_estudiante"
    class="table-btn btn-delete"
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

<div class="empty-students">

<i class="bi bi-people"></i>

<strong>
Este curso todavía no tiene estudiantes
</strong>

<p>
Agrega estudiantes usando el botón "Agregar estudiante".
</p>

</div>

<?php endif; ?>

<?php endif; ?>

</section>

</main>

</div>

<!-- =====================================================
     MODAL AGREGAR CURSO
===================================================== -->

<div
    class="modal"
    id="modalCurso"
>

<div class="modal-card">

<div class="modal-header">

<h3>
Agregar curso
</h3>

<button
    type="button"
    class="modal-close"
    onclick="cerrarModalCurso()"
>

<i class="bi bi-x-lg"></i>

</button>

</div>

<form method="POST">

<div class="form-group">

<label
    class="form-label"
    for="nombreCurso"
>

Número del curso

</label>

<input
    type="number"
    min="1"
    class="form-input"
    id="nombreCurso"
    name="nombre_curso"
    placeholder="Ejemplo: 1104"
    required
>

</div>

<div class="modal-actions">

<button
    type="button"
    class="btn-cancel"
    onclick="cerrarModalCurso()"
>

Cancelar

</button>

<button
    type="submit"
    name="agregar_curso"
    class="btn-save"
>

<i class="bi bi-check-lg"></i>

Crear curso

</button>

</div>

</form>

</div>

</div>

<?php if ($cursoSeleccionado): ?>

<!-- =====================================================
     MODAL AGREGAR ESTUDIANTE
===================================================== -->

<div
    class="modal"
    id="modalEstudiante"
>

<div class="modal-card">

<div class="modal-header">

<h3>
Agregar estudiante
</h3>

<button
    type="button"
    class="modal-close"
    onclick="cerrarModalEstudiante()"
>

<i class="bi bi-x-lg"></i>

</button>

</div>

<form method="POST">

<input
    type="hidden"
    name="id_curso"
    value="<?= (int)$cursoSeleccionado['id_curso'] ?>"
>

<div class="form-group">

<label
    class="form-label"
    for="documentoNuevo"
>

Documento

</label>

<input
    type="text"
    class="form-input"
    id="documentoNuevo"
    name="documento"
    placeholder="Número de documento"
    required
>

</div>

<div class="form-group">

<label
    class="form-label"
    for="nombresNuevo"
>

Nombres

</label>

<input
    type="text"
    class="form-input"
    id="nombresNuevo"
    name="nombres"
    placeholder="Nombres del estudiante"
    required
>

</div>

<div class="form-group">

<label
    class="form-label"
    for="apellidosNuevo"
>

Apellidos

</label>

<input
    type="text"
    class="form-input"
    id="apellidosNuevo"
    name="apellidos"
    placeholder="Apellidos del estudiante"
    required
>

</div>

<div class="modal-actions">

<button
    type="button"
    class="btn-cancel"
    onclick="cerrarModalEstudiante()"
>

Cancelar

</button>

<button
    type="submit"
    name="agregar_estudiante"
    class="btn-save"
>

<i class="bi bi-person-plus"></i>

Agregar estudiante

</button>

</div>

</form>

</div>

</div>

<!-- =====================================================
     MODAL EDITAR ESTUDIANTE
===================================================== -->

<div
    class="modal"
    id="modalEditarEstudiante"
>

<div class="modal-card">

<div class="modal-header">

<h3>
Editar estudiante
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
    name="id_estudiante"
    id="editarId"
>

<input
    type="hidden"
    name="id_curso"
    value="<?= (int)$cursoSeleccionado['id_curso'] ?>"
>

<div class="form-group">

<label
    class="form-label"
    for="editarDocumento"
>

Documento

</label>

<input
    type="text"
    class="form-input"
    id="editarDocumento"
    name="documento"
    required
>

</div>

<div class="form-group">

<label
    class="form-label"
    for="editarNombres"
>

Nombres

</label>

<input
    type="text"
    class="form-input"
    id="editarNombres"
    name="nombres"
    required
>

</div>

<div class="form-group">

<label
    class="form-label"
    for="editarApellidos"
>

Apellidos

</label>

<input
    type="text"
    class="form-input"
    id="editarApellidos"
    name="apellidos"
    required
>

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
    name="editar_estudiante"
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
     MODAL QR DEL ESTUDIANTE
===================================================== -->

<div
    class="modal"
    id="modalQR"
>

<div class="qr-modal-card">

<div class="qr-modal-top">

<button
    type="button"
    class="qr-modal-close"
    onclick="cerrarModalQR()"
    aria-label="Cerrar"
>

<i class="bi bi-x-lg"></i>

</button>

<div class="qr-title-icon">

<i class="bi bi-qr-code"></i>

</div>

<h2>
Código QR del estudiante
</h2>

<p>
Este código identifica al estudiante mediante su documento.
</p>

</div>

<div class="qr-content">

<div class="qr-image-box">

<img
    id="imagenQR"
    src=""
    alt="Código QR del estudiante"
>

</div>

<div
    class="qr-student-name"
    id="qrNombre"
>

Estudiante

</div>

<div class="qr-data-grid">

<div class="qr-data">

<span class="qr-data-label">
Documento
</span>

<span
    class="qr-data-value"
    id="qrDocumento"
>
-
</span>

</div>

<div class="qr-data">

<span class="qr-data-label">
Curso
</span>

<span
    class="qr-data-value"
>

<?= htmlspecialchars(
    'Curso ' . $cursoSeleccionado['nombre_curso']
) ?>

</span>

</div>

</div>

<div
    id="qrEstado"
    class="qr-status active"
>

<span class="status-dot"></span>

<span id="qrEstadoTexto">
ACTIVO
</span>

</div>

<div class="qr-modal-actions">

<button
    type="button"
    class="qr-action qr-close-action"
    onclick="cerrarModalQR()"
>

<i class="bi bi-x-circle"></i>

Cerrar

</button>

<button
    type="button"
    class="qr-action qr-print"
    onclick="imprimirQR()"
>

<i class="bi bi-printer"></i>

Imprimir QR

</button>

</div>

</div>

</div>

</div>

<?php endif; ?>

<script>

/* =========================================================
   RELOJ
========================================================= */

function actualizarReloj(){

    const ahora = new Date();

    const horas =
        String(ahora.getHours()).padStart(2,'0');

    const minutos =
        String(ahora.getMinutes()).padStart(2,'0');

    const segundos =
        String(ahora.getSeconds()).padStart(2,'0');

    const reloj =
        document.getElementById('reloj');

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
   BUSCADOR DE CURSOS
========================================================= */

const buscador =
    document.getElementById(
        'buscadorCursos'
    );

const tarjetas =
    document.querySelectorAll(
        '.course-card'
    );

if(buscador){

    buscador.addEventListener(
        'input',
        function(){

            const texto =
                this.value
                .toLowerCase()
                .trim();

            tarjetas.forEach(
                function(tarjeta){

                    const curso =
                        tarjeta.dataset.curso || '';

                    if(
                        curso.includes(texto)
                    ){

                        tarjeta.classList.remove(
                            'hidden'
                        );

                    }else{

                        tarjeta.classList.add(
                            'hidden'
                        );
                    }

                }
            );

        }
    );
}


/* =========================================================
   MODAL CURSO
========================================================= */

function abrirModalCurso(){

    const modal =
        document.getElementById(
            'modalCurso'
        );

    if(modal){

        modal.classList.add(
            'show'
        );

        const input =
            document.getElementById(
                'nombreCurso'
            );

        if(input){

            setTimeout(
                function(){

                    input.focus();

                },
                100
            );
        }
    }
}

function cerrarModalCurso(){

    const modal =
        document.getElementById(
            'modalCurso'
        );

    if(modal){

        modal.classList.remove(
            'show'
        );
    }
}


/* =========================================================
   MODAL ESTUDIANTE
========================================================= */

function abrirModalEstudiante(){

    const modal =
        document.getElementById(
            'modalEstudiante'
        );

    if(modal){

        modal.classList.add(
            'show'
        );

        const input =
            document.getElementById(
                'documentoNuevo'
            );

        if(input){

            setTimeout(
                function(){

                    input.focus();

                },
                100
            );
        }
    }
}

function cerrarModalEstudiante(){

    const modal =
        document.getElementById(
            'modalEstudiante'
        );

    if(modal){

        modal.classList.remove(
            'show'
        );
    }
}


/* =========================================================
   EDITAR ESTUDIANTE
========================================================= */

function editarEstudiante(
    estudiante
){

    const modal =
        document.getElementById(
            'modalEditarEstudiante'
        );

    if(!modal){

        return;
    }

    document.getElementById(
        'editarId'
    ).value =
        estudiante.id_estudiante;

    document.getElementById(
        'editarDocumento'
    ).value =
        estudiante.documento;

    document.getElementById(
        'editarNombres'
    ).value =
        estudiante.nombres;

    document.getElementById(
        'editarApellidos'
    ).value =
        estudiante.apellidos;

    modal.classList.add(
        'show'
    );
}

function cerrarModalEditar(){

    const modal =
        document.getElementById(
            'modalEditarEstudiante'
        );

    if(modal){

        modal.classList.remove(
            'show'
        );
    }
}


/* =========================================================
   VER QR
========================================================= */

let estudianteQRActual = null;

function verQR(estudiante){

    const modal =
        document.getElementById(
            'modalQR'
        );

    const imagen =
        document.getElementById(
            'imagenQR'
        );

    const nombre =
        document.getElementById(
            'qrNombre'
        );

    const documento =
        document.getElementById(
            'qrDocumento'
        );

    const estado =
        document.getElementById(
            'qrEstado'
        );

    const estadoTexto =
        document.getElementById(
            'qrEstadoTexto'
        );

    if(
        !modal ||
        !imagen ||
        !nombre ||
        !documento ||
        !estado ||
        !estadoTexto
    ){

        return;
    }

    estudianteQRActual = estudiante;

    const nombreCompleto =
        (
            estudiante.nombres +
            ' ' +
            estudiante.apellidos
        ).trim();

    nombre.textContent =
        nombreCompleto;

    documento.textContent =
        estudiante.documento;

    estadoTexto.textContent =
        estudiante.estado;

    estado.classList.remove(
        'active',
        'inactive'
    );

    if(
        estudiante.estado === 'ACTIVO'
    ){

        estado.classList.add(
            'active'
        );

    }else{

        estado.classList.add(
            'inactive'
        );
    }

    /*
     * EL CONTENIDO DEL QR ES EL DOCUMENTO.
     *
     * No se envía el nombre, curso ni estado.
     * Solamente el documento.
     */

    imagen.src =
        'qr_estudiante.php?documento=' +
        encodeURIComponent(
            estudiante.documento
        ) +
        '&t=' +
        Date.now();

    modal.classList.add(
        'show'
    );
}

function cerrarModalQR(){

    const modal =
        document.getElementById(
            'modalQR'
        );

    if(modal){

        modal.classList.remove(
            'show'
        );
    }
}


/* =========================================================
   IMPRIMIR QR
========================================================= */

function imprimirQR(){

    if(!estudianteQRActual){

        return;
    }

    const estudiante =
        estudianteQRActual;

    const nombreCompleto =
        (
            estudiante.nombres +
            ' ' +
            estudiante.apellidos
        ).trim();

    const imagen =
        document.getElementById(
            'imagenQR'
        );

    if(!imagen){

        return;
    }

    const ventana =
        window.open(
            '',
            '_blank',
            'width=700,height=850'
        );

    if(!ventana){

        alert(
            'El navegador bloqueó la ventana de impresión. Permite ventanas emergentes para continuar.'
        );

        return;
    }

    const nombreSeguro =
        nombreCompleto
        .replace(
            /</g,
            '&lt;'
        )
        .replace(
            />/g,
            '&gt;'
        );

    const documentoSeguro =
        String(
            estudiante.documento
        )
        .replace(
            /</g,
            '&lt;'
        )
        .replace(
            />/g,
            '&gt;'
        );

    ventana.document.write(`
        <!DOCTYPE html>

        <html lang="es">

        <head>

        <meta charset="UTF-8">

        <title>QR - ${nombreSeguro}</title>

        <style>

        *{
            box-sizing:border-box;
        }

        body{
            margin:0;
            min-height:100vh;

            display:flex;
            align-items:center;
            justify-content:center;

            font-family:Arial,sans-serif;

            background:#fff;
        }

        .tarjeta{
            width:500px;

            padding:35px;

            text-align:center;

            border:1px solid #e2eeee;

            border-radius:25px;
        }

        h1{
            margin:0 0 8px;

            color:#245d70;

            font-size:25px;
        }

        .subtitulo{
            margin-bottom:25px;

            color:#7898a1;

            font-size:13px;
        }

        .qr{
            width:320px;
            height:320px;

            object-fit:contain;

            margin:0 auto 22px;

            display:block;
        }

        .nombre{
            color:#315f70;

            font-size:21px;

            font-weight:bold;
        }

        .documento{
            margin-top:8px;

            color:#668995;

            font-size:15px;
        }

        .linea{
            width:70%;

            height:1px;

            margin:22px auto;

            background:#dcebed;
        }

        .nota{
            color:#8aa3aa;

            font-size:11px;
        }

        @media print{

            body{
                min-height:auto;
            }

            .tarjeta{
                border:none;
            }

        }

        </style>

        </head>

        <body>

        <div class="tarjeta">

            <h1>ASISTENCIA QR</h1>

            <div class="subtitulo">
                Código de identificación del estudiante
            </div>

            <img
                class="qr"
                src="${imagen.src}"
                alt="Código QR"
            >

            <div class="nombre">
                ${nombreSeguro}
            </div>

            <div class="documento">
                Documento: ${documentoSeguro}
            </div>

            <div class="linea"></div>

            <div class="nota">
                El código QR contiene únicamente el documento del estudiante.
            </div>

        </div>

        <script>

        window.onload = function(){

            setTimeout(
                function(){

                    window.print();

                },
                500
            );

        };

        <\/script>

        </body>

        </html>
    `);

    ventana.document.close();
}


/* =========================================================
   CERRAR MODALES AL HACER CLICK AFUERA
========================================================= */

document.addEventListener(
    'click',
    function(event){

        const modalCurso =
            document.getElementById(
                'modalCurso'
            );

        const modalEstudiante =
            document.getElementById(
                'modalEstudiante'
            );

        const modalEditar =
            document.getElementById(
                'modalEditarEstudiante'
            );

        const modalQR =
            document.getElementById(
                'modalQR'
            );

        if(
            modalCurso &&
            event.target === modalCurso
        ){

            cerrarModalCurso();
        }

        if(
            modalEstudiante &&
            event.target === modalEstudiante
        ){

            cerrarModalEstudiante();
        }

        if(
            modalEditar &&
            event.target === modalEditar
        ){

            cerrarModalEditar();
        }

        if(
            modalQR &&
            event.target === modalQR
        ){

            cerrarModalQR();
        }

    }
);


/* =========================================================
   ESCAPE
========================================================= */

document.addEventListener(
    'keydown',
    function(event){

        if(
            event.key === 'Escape'
        ){

            cerrarModalCurso();

            cerrarModalEstudiante();

            cerrarModalEditar();

            cerrarModalQR();
        }

    }
);

</script>

</body>

</html>
