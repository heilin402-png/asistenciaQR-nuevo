```php
<?php

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/conexion.php";

date_default_timezone_set('America/Bogota');

/* =========================================================
   USUARIO DOCENTE
========================================================= */

$idUsuario = (int) $_SESSION['id_usuario'];

$nombreUsuario = $_SESSION['nombre'] ?? 'Docente';

$partesNombre = preg_split('/\s+/', trim($nombreUsuario));

$iniciales = '';

foreach (array_slice($partesNombre, 0, 2) as $parte) {
    $iniciales .= strtoupper(substr($parte, 0, 1));
}

if ($iniciales === '') {
    $iniciales = 'DO';
}

$horaActual = date('H:i:s');
$fechaActual = date('d/m/Y');
$fechaHoy = date('Y-m-d');


/* =========================================================
   CREAR SESIÓN
   IMPORTANTE:
   - El docente crea la sesión.
   - NO se genera ningún QR aquí.
========================================================= */

$mensaje = '';
$tipoMensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion = $_POST['accion'] ?? '';

    /* -----------------------------------------------------
       CREAR NUEVA SESIÓN
    ----------------------------------------------------- */

    if ($accion === 'crear_sesion') {

        $idCurso = isset($_POST['id_curso'])
            ? (int) $_POST['id_curso']
            : 0;

        $horaInicio = $_POST['hora_inicio'] ?? '';

        if ($idCurso <= 0 || empty($horaInicio)) {

            $mensaje = 'Debes seleccionar un curso y una hora.';
            $tipoMensaje = 'error';

        } else {

            /* ---------------------------------------------
               Verificar que el curso pertenece al docente.

               AQUÍ usamos docente_curso.id_usuario,
               NO id_docente.
            --------------------------------------------- */

            $sqlVerificarCurso = "
                SELECT id_docente_curso
                FROM docente_curso
                WHERE id_usuario = ?
                AND id_curso = ?
                LIMIT 1
            ";

            $stmtVerificar = mysqli_prepare(
                $conexion,
                $sqlVerificarCurso
            );

            if ($stmtVerificar) {

                mysqli_stmt_bind_param(
                    $stmtVerificar,
                    "ii",
                    $idUsuario,
                    $idCurso
                );

                mysqli_stmt_execute($stmtVerificar);

                $resultadoVerificar =
                    mysqli_stmt_get_result($stmtVerificar);

                $cursoPermitido =
                    mysqli_num_rows($resultadoVerificar) > 0;

                mysqli_stmt_close($stmtVerificar);

                if (!$cursoPermitido) {

                    $mensaje =
                        'No tienes asignado este curso.';

                    $tipoMensaje = 'error';

                } else {

                    /* -------------------------------------
                       Crear sesión.

                       sesiones_clase SÍ usa id_docente,
                       porque esa columna existe en esa tabla.
                    ------------------------------------- */

                    $horaCompleta =
                        $fechaHoy . ' ' . $horaInicio . ':00';

                    $estado = 'ABIERTA';

                    $sqlCrear = "
                        INSERT INTO sesiones_clase
                        (
                            id_docente,
                            id_curso,
                            fecha,
                            hora_inicio,
                            estado
                        )
                        VALUES (?, ?, ?, ?, ?)
                    ";

                    $stmtCrear = mysqli_prepare(
                        $conexion,
                        $sqlCrear
                    );

                    if ($stmtCrear) {

                        mysqli_stmt_bind_param(
                            $stmtCrear,
                            "iisss",
                            $idUsuario,
                            $idCurso,
                            $fechaHoy,
                            $horaCompleta,
                            $estado
                        );

                        if (mysqli_stmt_execute($stmtCrear)) {

                            $idNuevaSesion =
                                mysqli_insert_id($conexion);

                            mysqli_stmt_close($stmtCrear);

                            /*
                             * IMPORTANTE:
                             * NO GENERAMOS QR.
                             *
                             * El QR pertenece al estudiante.
                             * La sesión solamente queda ABIERTA.
                             */

                            header(
                                "Location: asistencia.php?sesion="
                                . $idNuevaSesion
                            );

                            exit();

                        } else {

                            $mensaje =
                                'No fue posible crear la sesión: '
                                . mysqli_error($conexion);

                            $tipoMensaje = 'error';

                            mysqli_stmt_close($stmtCrear);
                        }

                    } else {

                        $mensaje =
                            'No fue posible preparar la creación de la sesión.';

                        $tipoMensaje = 'error';
                    }
                }

            } else {

                $mensaje =
                    'No fue posible verificar el curso.';

                $tipoMensaje = 'error';
            }
        }
    }


    /* -----------------------------------------------------
       CERRAR SESIÓN
    ----------------------------------------------------- */

    if ($accion === 'cerrar_sesion') {

        $idSesionCerrar = isset($_POST['id_sesion'])
            ? (int) $_POST['id_sesion']
            : 0;

        if ($idSesionCerrar > 0) {

            /*
             * Verificamos que la sesión realmente
             * pertenezca al docente.
             */

            $sqlCerrar = "
                UPDATE sesiones_clase
                SET estado = 'CERRADA'
                WHERE id_sesion = ?
                AND id_docente = ?
            ";

            $stmtCerrar = mysqli_prepare(
                $conexion,
                $sqlCerrar
            );

            if ($stmtCerrar) {

                mysqli_stmt_bind_param(
                    $stmtCerrar,
                    "ii",
                    $idSesionCerrar,
                    $idUsuario
                );

                mysqli_stmt_execute($stmtCerrar);

                mysqli_stmt_close($stmtCerrar);
            }

            header(
                "Location: asistencia.php?sesion="
                . $idSesionCerrar
            );

            exit();
        }
    }
}


/* =========================================================
   CURSOS DEL DOCENTE
   AQUÍ ESTÁ LA CORRECCIÓN PRINCIPAL.

   docente_curso:
       id_usuario
       id_curso

   NO:
       id_docente
========================================================= */

$cursos = [];

$sqlCursos = "
    SELECT
        c.id_curso,
        c.nombre_curso
    FROM docente_curso dc
    INNER JOIN cursos c
        ON c.id_curso = dc.id_curso
    WHERE dc.id_usuario = ?
    ORDER BY c.nombre_curso ASC
";

$stmtCursos = mysqli_prepare(
    $conexion,
    $sqlCursos
);

if ($stmtCursos) {

    mysqli_stmt_bind_param(
        $stmtCursos,
        "i",
        $idUsuario
    );

    mysqli_stmt_execute($stmtCursos);

    $resultadoCursos =
        mysqli_stmt_get_result($stmtCursos);

    while ($fila = mysqli_fetch_assoc($resultadoCursos)) {
        $cursos[] = $fila;
    }

    mysqli_stmt_close($stmtCursos);
}


/* =========================================================
   SESIONES DEL DOCENTE
========================================================= */

$sesiones = [];

$sqlSesiones = "
    SELECT
        s.id_sesion,
        s.id_docente,
        s.id_curso,
        s.fecha,
        s.hora_inicio,
        s.estado,
        c.nombre_curso
    FROM sesiones_clase s
    INNER JOIN cursos c
        ON c.id_curso = s.id_curso
    WHERE s.id_docente = ?
    ORDER BY
        s.fecha DESC,
        s.hora_inicio DESC
";

$stmtSesiones = mysqli_prepare(
    $conexion,
    $sqlSesiones
);

if ($stmtSesiones) {

    mysqli_stmt_bind_param(
        $stmtSesiones,
        "i",
        $idUsuario
    );

    mysqli_stmt_execute($stmtSesiones);

    $resultadoSesiones =
        mysqli_stmt_get_result($stmtSesiones);

    while ($fila = mysqli_fetch_assoc($resultadoSesiones)) {
        $sesiones[] = $fila;
    }

    mysqli_stmt_close($stmtSesiones);
}


/* =========================================================
   SESIÓN SELECCIONADA
========================================================= */

$idSesionSeleccionada = 0;

if (isset($_GET['sesion'])) {

    $idSesionSeleccionada =
        (int) $_GET['sesion'];
}


/* =========================================================
   SI NO HAY SESIÓN SELECCIONADA,
   BUSCAR UNA ABIERTA DEL DOCENTE
========================================================= */

if ($idSesionSeleccionada <= 0) {

    foreach ($sesiones as $sesion) {

        if ($sesion['estado'] === 'ABIERTA') {

            $idSesionSeleccionada =
                (int) $sesion['id_sesion'];

            break;
        }
    }
}


/* =========================================================
   DATOS DE LA SESIÓN
========================================================= */

$sesionActual = null;

foreach ($sesiones as $sesion) {

    if (
        (int) $sesion['id_sesion']
        === $idSesionSeleccionada
    ) {

        $sesionActual = $sesion;

        break;
    }
}


/* =========================================================
   ASISTENCIAS

   IMPORTANTE:
   Aquí SOLO usamos id_sesion.

   NO buscamos id_docente dentro de asistencia_clase
   porque esa tabla NO tiene esa columna.
========================================================= */

$asistencias = [];

if ($sesionActual) {

    $sqlAsistencias = "
        SELECT
            a.id_asistencia,
            a.id_sesion,
            a.id_estudiante,
            a.estado,
            a.estado_excusa,
            a.hora_registro,
            e.documento,
            e.nombres,
            e.apellidos
        FROM asistencia_clase a
        INNER JOIN estudiantes e
            ON e.id_estudiante = a.id_estudiante
        WHERE a.id_sesion = ?
        ORDER BY a.hora_registro DESC
    ";

    $stmtAsistencias = mysqli_prepare(
        $conexion,
        $sqlAsistencias
    );

    if ($stmtAsistencias) {

        mysqli_stmt_bind_param(
            $stmtAsistencias,
            "i",
            $idSesionSeleccionada
        );

        mysqli_stmt_execute(
            $stmtAsistencias
        );

        $resultadoAsistencias =
            mysqli_stmt_get_result(
                $stmtAsistencias
            );

        while (
            $fila =
            mysqli_fetch_assoc(
                $resultadoAsistencias
            )
        ) {

            $asistencias[] = $fila;
        }

        mysqli_stmt_close(
            $stmtAsistencias
        );
    }
}


/* =========================================================
   CONTADORES
========================================================= */

$totalPresentes = 0;
$totalExcusas = 0;

foreach ($asistencias as $asistencia) {

    if (
        strtoupper(
            $asistencia['estado']
        ) === 'PRESENTE'
    ) {

        $totalPresentes++;
    }

    if (
        !empty(
            $asistencia['estado_excusa']
        )
    ) {

        $totalExcusas++;
    }
}

$totalRegistrados =
    count($asistencias);

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Asistencia QR | Control de asistencia
</title>

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>

<style>

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

    font-family:
        "Segoe UI",
        Arial,
        sans-serif;

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

    min-height:
        calc(100vh - 36px);

    display:flex;

    flex-direction:column;

    padding:
        22px 16px 16px;

    border:
        1px solid
        rgba(255,255,255,.94);

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

    padding:
        3px 9px 20px;
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

    background:
        rgba(255,255,255,.76);

    border:
        1px solid
        rgba(255,255,255,.95);

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

    margin:
        0 9px 16px;

    background:
        rgba(50,111,130,.09);
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

    padding:
        0 11px;

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

    padding:
        6px 10px;

    border-radius:15px;

    color:#557f8b;

    text-decoration:none;

    font-size:14px;

    font-weight:850;

    transition:.25s;
}

.nav-link:hover{

    color:#075273;

    background:
        rgba(255,255,255,.74);

    transform:
        translateX(4px);
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

    border-radius:
        0 7px 7px 0;

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

    background:
        rgba(24,216,206,.075);

    font-size:19px;
}

.nav-icon.academic{

    color:#766cc8;

    background:
        rgba(133,121,210,.10);
}

.nav-icon.qr-icon{

    color:#078395;

    background:
        rgba(24,216,206,.12);
}

.nav-icon.reports{

    color:#bd8a40;

    background:
        rgba(209,161,88,.12);
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

    padding:
        10px 11px;

    border-radius:15px;

    background:
        rgba(255,255,255,.54);

    border:
        1px solid
        rgba(255,255,255,.85);
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

    padding:
        0 10px;

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

    padding:
        14px 22px;

    border:
        1px solid
        rgba(255,255,255,.92);

    border-radius:23px;

    background:
        rgba(255,255,255,.68);

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

    padding:
        10px 15px;

    border-radius:15px;

    background:
        rgba(255,255,255,.73);

    border:
        1px solid
        rgba(255,255,255,.90);
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
}

.clock-date{

    margin-top:2px;

    color:#819ba3;

    font-size:10px;

    font-weight:750;
}


/* =========================================================
   ALERTAS
========================================================= */

.alert{

    padding:
        14px 18px;

    border-radius:15px;

    font-size:13px;

    font-weight:800;
}

.alert-error{

    color:#a4535c;

    background:
        rgba(242,143,150,.12);

    border:
        1px solid
        rgba(242,143,150,.20);
}


/* =========================================================
   CREAR SESIÓN
========================================================= */

.create-card{

    padding:25px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:25px;

    background:
        rgba(255,255,255,.76);

    box-shadow:
        0 18px 42px
        rgba(55,113,129,.065);
}

.create-header{

    display:flex;

    align-items:center;

    gap:14px;

    margin-bottom:20px;
}

.create-icon{

    width:48px;
    height:48px;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:15px;

    color:#087d92;

    background:
        rgba(24,216,206,.10);

    font-size:22px;
}

.create-header h2{

    color:#315f70;

    font-size:20px;

    font-weight:950;
}

.create-header p{

    margin-top:4px;

    color:#819ca4;

    font-size:12px;

    font-weight:650;
}

.create-form{

    display:grid;

    grid-template-columns:
        1fr 190px auto;

    gap:12px;

    align-items:end;
}

.form-group label{

    display:block;

    margin-bottom:7px;

    color:#71929c;

    font-size:11px;

    font-weight:900;

    text-transform:uppercase;

    letter-spacing:.5px;
}

.form-control{

    width:100%;

    height:48px;

    padding:
        0 14px;

    border:
        1px solid
        #d8eeee;

    border-radius:13px;

    outline:none;

    color:#416f7e;

    background:
        rgba(248,253,252,.95);

    font-family:inherit;

    font-size:13px;

    font-weight:750;
}

.form-control:focus{

    border-color:
        rgba(24,216,206,.55);

    box-shadow:
        0 0 0 4px
        rgba(24,216,206,.08);
}

.btn-create{

    height:48px;

    padding:
        0 20px;

    border:none;

    border-radius:13px;

    cursor:pointer;

    color:#fff;

    background:
        linear-gradient(
            135deg,
            #18cfc4,
            #078ba0
        );

    font-family:inherit;

    font-size:12px;

    font-weight:950;

    box-shadow:
        0 10px 22px
        rgba(24,216,206,.20);

    transition:.25s;
}

.btn-create:hover{

    transform:
        translateY(-2px);
}


/* =========================================================
   SESIONES
========================================================= */

.session-card{

    padding:25px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:25px;

    background:
        rgba(255,255,255,.76);

    box-shadow:
        0 18px 42px
        rgba(55,113,129,.065);
}

.session-header{

    display:flex;

    align-items:flex-start;

    justify-content:space-between;

    gap:20px;

    margin-bottom:20px;
}

.session-header h2{

    color:#315f70;

    font-size:20px;

    font-weight:950;
}

.session-header p{

    margin-top:5px;

    color:#819ca4;

    font-size:12px;

    font-weight:650;
}

.session-select{

    width:100%;

    padding:
        13px 15px;

    border:
        1px solid
        #d8eeee;

    border-radius:13px;

    outline:none;

    color:#416f7e;

    background:
        rgba(248,253,252,.95);

    font-family:inherit;

    font-size:13px;

    font-weight:750;
}

.session-select:focus{

    border-color:
        rgba(24,216,206,.55);

    box-shadow:
        0 0 0 4px
        rgba(24,216,206,.08);
}


/* =========================================================
   INFO SESIÓN
========================================================= */

.session-info{

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:12px;

    margin-top:18px;
}

.info-box{

    padding:16px;

    border-radius:17px;

    background:
        rgba(255,255,255,.74);

    border:
        1px solid
        rgba(230,244,244,.9);
}

.info-box span{

    display:block;

    color:#8aa3aa;

    font-size:10px;

    font-weight:850;

    text-transform:uppercase;

    letter-spacing:.5px;
}

.info-box strong{

    display:block;

    margin-top:6px;

    color:#426f7d;

    font-size:14px;

    font-weight:950;
}

.status-open{

    color:#1ba77e !important;
}

.status-closed{

    color:#b86e77 !important;
}


/* =========================================================
   CONTROL SESIÓN ABIERTA
========================================================= */

.session-control{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:15px;

    margin-top:18px;

    padding:15px 17px;

    border-radius:16px;

    background:
        rgba(24,216,206,.055);

    border:
        1px solid
        rgba(24,216,206,.10);
}

.session-control-text{

    display:flex;

    align-items:center;

    gap:10px;
}

.session-control-text i{

    color:#18bfae;

    font-size:18px;
}

.session-control-text span{

    color:#507984;

    font-size:12px;

    font-weight:800;
}

.btn-close-session{

    padding:
        10px 15px;

    border:none;

    border-radius:10px;

    cursor:pointer;

    color:#a4535c;

    background:
        rgba(242,143,150,.10);

    font-family:inherit;

    font-size:11px;

    font-weight:950;
}

.btn-close-session:hover{

    background:
        rgba(242,143,150,.18);
}


/* =========================================================
   AVISO QR
========================================================= */

.qr-info{

    display:flex;

    align-items:center;

    gap:12px;

    margin-top:15px;

    padding:14px 16px;

    border-radius:15px;

    background:
        rgba(133,121,210,.065);

    border:
        1px solid
        rgba(133,121,210,.10);
}

.qr-info i{

    color:#7569c2;

    font-size:22px;
}

.qr-info strong{

    display:block;

    color:#5d559e;

    font-size:12px;

    font-weight:950;
}

.qr-info span{

    display:block;

    margin-top:3px;

    color:#8797a8;

    font-size:11px;

    font-weight:650;
}


/* =========================================================
   RESUMEN
========================================================= */

.summary-grid{

    display:grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:16px;
}

.summary-item{

    min-height:115px;

    display:flex;

    align-items:center;

    gap:17px;

    padding:
        21px 22px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:23px;

    background:
        rgba(255,255,255,.76);

    box-shadow:
        0 18px 42px
        rgba(55,113,129,.065);

    transition:.25s;
}

.summary-item:hover{

    transform:
        translateY(-4px);
}

.summary-icon{

    width:60px;
    height:60px;

    display:flex;

    align-items:center;
    justify-content:center;

    flex-shrink:0;

    border-radius:18px;

    color:#0a9995;

    background:
        rgba(24,216,206,.10);

    font-size:26px;
}

.summary-item:nth-child(2)
.summary-icon{

    color:#7569c2;

    background:
        rgba(133,121,210,.10);
}

.summary-item:nth-child(3)
.summary-icon{

    color:#3aa47d;

    background:
        rgba(66,205,161,.10);
}

.summary-text span{

    display:block;

    color:#819da5;

    font-size:12px;

    font-weight:800;
}

.summary-text strong{

    display:block;

    margin-top:5px;

    color:#315f70;

    font-size:28px;

    font-weight:950;
}


/* =========================================================
   ASISTENCIAS
========================================================= */

.attendance-card{

    overflow:hidden;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:25px;

    background:
        rgba(255,255,255,.76);

    box-shadow:
        0 18px 42px
        rgba(55,113,129,.065);
}

.attendance-header{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:15px;

    padding:
        23px 25px;

    border-bottom:
        1px solid
        rgba(50,111,130,.08);
}

.attendance-header h3{

    color:#416f7e;

    font-size:18px;

    font-weight:950;
}

.attendance-header p{

    margin-top:4px;

    color:#819ca4;

    font-size:12px;
}

.count-badge{

    padding:
        8px 12px;

    border-radius:10px;

    color:#087d82;

    background:
        rgba(24,216,206,.09);

    font-size:11px;

    font-weight:950;
}

.table-container{

    width:100%;

    overflow-x:auto;
}

table{

    width:100%;

    border-collapse:collapse;

    min-width:700px;
}

thead{

    background:
        rgba(238,249,248,.65);
}

th{

    padding:
        13px 20px;

    text-align:left;

    color:#7898a2;

    font-size:10px;

    font-weight:950;

    text-transform:uppercase;

    letter-spacing:.6px;
}

td{

    padding:
        15px 20px;

    border-top:
        1px solid
        rgba(50,111,130,.065);

    color:#527985;

    font-size:12px;

    font-weight:700;
}

.student-name{

    color:#416f7e;

    font-weight:900;
}

.student-document{

    color:#7897a0;

    font-weight:800;
}

.present-badge{

    display:inline-flex;

    align-items:center;

    gap:6px;

    padding:
        6px 9px;

    border-radius:9px;

    color:#198f70;

    background:
        rgba(66,205,161,.11);

    font-size:10px;

    font-weight:950;
}

.excuse-badge{

    display:inline-flex;

    align-items:center;

    gap:6px;

    padding:
        6px 9px;

    border-radius:9px;

    color:#a36d32;

    background:
        rgba(233,154,120,.12);

    font-size:10px;

    font-weight:950;
}

.empty{

    padding:
        55px 20px;

    text-align:center;

    color:#8aa3aa;

    font-size:13px;

    font-weight:750;
}

.empty i{

    display:block;

    margin-bottom:10px;

    color:#8dc6c5;

    font-size:40px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1100px){

    .sidebar{
        width:255px;
    }

    .session-info{
        grid-template-columns:
            repeat(2,1fr);
    }

    .create-form{
        grid-template-columns:
            1fr 170px;
    }

    .btn-create{
        width:100%;
    }

}

@media(max-width:900px){

    .app{

        flex-direction:column;

        padding:10px;
    }

    .sidebar{

        width:100%;

        min-height:auto;
    }

    .navigation{

        display:grid;

        grid-template-columns:
            repeat(2,1fr);
    }

    .menu-label{

        grid-column:
            1 / -1;
    }

    .sidebar-bottom{
        display:none;
    }

    .create-form{
        grid-template-columns:1fr;
    }

}

@media(max-width:700px){

    .summary-grid{

        grid-template-columns:1fr;
    }

    .session-info{

        grid-template-columns:1fr;
    }

}

@media(max-width:650px){

    .topbar{

        align-items:flex-start;

        flex-direction:column;
    }

    .clock-box{
        width:100%;
    }

    .navigation{
        grid-template-columns:1fr;
    }

    .menu-label{
        grid-column:auto;
    }

    .session-header{
        flex-direction:column;
    }

    .session-control{

        align-items:flex-start;

        flex-direction:column;
    }

    .btn-close-session{
        width:100%;
    }

}

</style>

</head>

<body>

<div class="app">


<!-- =====================================================
     SIDEBAR
====================================================== -->

<aside class="sidebar">

<div class="sidebar-header">

    <div class="logo-container">

        <img
            src="../Logo.png"
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

    </a>

</div>


<div class="menu-section">

    <div class="menu-label">

        <span class="label-line"></span>

        GESTIÓN ACADÉMICA

    </div>


    <a
        href="cursos.php"
        class="nav-link"
    >

        <div class="nav-icon academic">

            <i class="bi bi-mortarboard"></i>

        </div>

        <span>
            Mis cursos
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
        class="nav-link active"
    >

        <div class="nav-icon qr-icon">

            <i class="bi bi-qr-code-scan"></i>

        </div>

        <span>
            Asistencia
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

            <?= htmlspecialchars(
                $nombreUsuario
            ) ?>

        </strong>

        <small>
            DOCENTE
        </small>

    </div>


    <div class="profile-status">
        ●
    </div>

</div>


<a
    href="../auth/logout.php"
    class="logout"

    onclick="
        return confirm(
            '¿Deseas cerrar tu sesión?'
        );
    "
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
     MAIN
====================================================== -->

<main class="main">


<header class="topbar">

<div class="page-info">

    <div class="page-indicator"></div>

    <div class="page-title">

        <h1>
            Control de asistencia
        </h1>

        <p>
            Crea sesiones y consulta la asistencia de tus clases
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


<?php if ($mensaje !== ''): ?>

<div class="alert alert-error">

    <i class="bi bi-exclamation-circle"></i>

    <?= htmlspecialchars($mensaje) ?>

</div>

<?php endif; ?>


<!-- =====================================================
     CREAR SESIÓN
====================================================== -->

<section class="create-card">

<div class="create-header">

    <div class="create-icon">

        <i class="bi bi-calendar-plus"></i>

    </div>

    <div>

        <h2>
            Nueva sesión de clase
        </h2>

        <p>
            Abre una sesión para que los estudiantes puedan registrar su asistencia.
        </p>

    </div>

</div>


<?php if (count($cursos) > 0): ?>

<form
    method="POST"
    class="create-form"
>

    <input
        type="hidden"
        name="accion"
        value="crear_sesion"
    >


    <div class="form-group">

        <label>
            Curso
        </label>

        <select
            name="id_curso"
            class="form-control"
            required
        >

            <option value="">
                Selecciona un curso
            </option>

            <?php foreach ($cursos as $curso): ?>

                <option
                    value="<?= (int)$curso['id_curso'] ?>"
                >

                    <?= htmlspecialchars(
                        $curso['nombre_curso']
                    ) ?>

                </option>

            <?php endforeach; ?>

        </select>

    </div>


    <div class="form-group">

        <label>
            Hora de inicio
        </label>

        <input
            type="time"
            name="hora_inicio"
            class="form-control"
            value="<?= date('H:i') ?>"
            required
        >

    </div>


    <button
        type="submit"
        class="btn-create"
    >

        <i class="bi bi-play-circle"></i>

        Abrir sesión

    </button>

</form>


<div class="qr-info">

    <i class="bi bi-qr-code"></i>

    <div>

        <strong>
            La sesión no genera ningún QR
        </strong>

        <span>
            Los estudiantes utilizan su QR personal para registrar su asistencia en esta sesión.
        </span>

    </div>

</div>


<?php else: ?>

<div class="empty">

    <i class="bi bi-mortarboard"></i>

    No tienes cursos asignados actualmente.

</div>

<?php endif; ?>

</section>


<!-- =====================================================
     SESIONES EXISTENTES
====================================================== -->

<section class="session-card">

<div class="session-header">

<div>

    <h2>
        Sesiones de clase
    </h2>

    <p>
        Selecciona una sesión para consultar los registros de asistencia.
    </p>

</div>

</div>


<?php if (count($sesiones) > 0): ?>

<select
    class="session-select"
    onchange="
        if(this.value){
            window.location.href =
            'asistencia.php?sesion='
            + this.value;
        }
    "
>

<option value="">
    Selecciona una sesión
</option>


<?php foreach ($sesiones as $sesion): ?>

<option
    value="<?= (int)$sesion['id_sesion'] ?>"

    <?= (
        (int)$sesion['id_sesion']
        === $idSesionSeleccionada
    )
        ? 'selected'
        : ''
    ?>
>

<?= htmlspecialchars(
    $sesion['nombre_curso']
) ?>

 —

<?= htmlspecialchars(
    date(
        'd/m/Y',
        strtotime(
            $sesion['fecha']
        )
    )
) ?>

 —

<?= htmlspecialchars(
    date(
        'H:i',
        strtotime(
            $sesion['hora_inicio']
        )
    )
) ?>

 —

<?= htmlspecialchars(
    $sesion['estado']
) ?>

</option>

<?php endforeach; ?>

</select>


<?php if ($sesionActual): ?>

<div class="session-info">


<div class="info-box">

<span>
Curso
</span>

<strong>

<?= htmlspecialchars(
    $sesionActual['nombre_curso']
) ?>

</strong>

</div>


<div class="info-box">

<span>
Fecha
</span>

<strong>

<?= htmlspecialchars(
    date(
        'd/m/Y',
        strtotime(
            $sesionActual['fecha']
        )
    )
) ?>

</strong>

</div>


<div class="info-box">

<span>
Hora
</span>

<strong>

<?= htmlspecialchars(
    date(
        'H:i',
        strtotime(
            $sesionActual['hora_inicio']
        )
    )
) ?>

</strong>

</div>


<div class="info-box">

<span>
Estado
</span>

<strong
    class="<?= (
        $sesionActual['estado']
        === 'ABIERTA'
    )
        ? 'status-open'
        : 'status-closed'
    ?>"
>

<?= htmlspecialchars(
    $sesionActual['estado']
) ?>

</strong>

</div>


</div>


<?php if ($sesionActual['estado'] === 'ABIERTA'): ?>

<div class="session-control">

    <div class="session-control-text">

        <i class="bi bi-broadcast"></i>

        <span>
            La sesión está abierta y puede recibir registros de los estudiantes.
        </span>

    </div>


    <form
        method="POST"
        onsubmit="
            return confirm(
                '¿Deseas cerrar esta sesión? Los estudiantes ya no podrán registrar asistencia en ella.'
            );
        "
    >

        <input
            type="hidden"
            name="accion"
            value="cerrar_sesion"
        >

        <input
            type="hidden"
            name="id_sesion"
            value="<?= (int)$sesionActual['id_sesion'] ?>"
        >

        <button
            type="submit"
            class="btn-close-session"
        >

            <i class="bi bi-stop-circle"></i>

            Cerrar sesión

        </button>

    </form>

</div>

<?php endif; ?>


<div class="qr-info">

    <i class="bi bi-person-vcard"></i>

    <div>

        <strong>
            Registro mediante QR del estudiante
        </strong>

        <span>
            Los estudiantes deben escanear su QR personal mientras esta sesión se encuentre abierta.
        </span>

    </div>

</div>


<?php endif; ?>


<?php else: ?>

<div class="empty">

    <i class="bi bi-calendar-x"></i>

    No tienes sesiones registradas.

</div>

<?php endif; ?>

</section>


<?php if ($sesionActual): ?>


<!-- =====================================================
     RESUMEN
====================================================== -->

<section class="summary-grid">


<div class="summary-item">

    <div class="summary-icon">

        <i class="bi bi-people-fill"></i>

    </div>

    <div class="summary-text">

        <span>
            Asistencias registradas
        </span>

        <strong>
            <?= $totalRegistrados ?>
        </strong>

    </div>

</div>


<div class="summary-item">

    <div class="summary-icon">

        <i class="bi bi-person-check-fill"></i>

    </div>

    <div class="summary-text">

        <span>
            Presentes
        </span>

        <strong>
            <?= $totalPresentes ?>
        </strong>

    </div>

</div>


<div class="summary-item">

    <div class="summary-icon">

        <i class="bi bi-file-earmark-text-fill"></i>

    </div>

    <div class="summary-text">

        <span>
            Con excusa
        </span>

        <strong>
            <?= $totalExcusas ?>
        </strong>

    </div>

</div>


</section>


<!-- =====================================================
     TABLA
====================================================== -->

<section class="attendance-card">

<div class="attendance-header">

<div>

<h3>
Estudiantes registrados
</h3>

<p>
Estudiantes que han escaneado su QR durante esta sesión.
</p>

</div>


<div class="count-badge">

<?= $totalRegistrados ?>

REGISTROS

</div>

</div>


<div class="table-container">

<?php if (count($asistencias) > 0): ?>

<table>

<thead>

<tr>

<th>
Estudiante
</th>

<th>
Documento
</th>

<th>
Hora de registro
</th>

<th>
Estado
</th>

<th>
Excusa
</th>

</tr>

</thead>


<tbody>

<?php foreach ($asistencias as $asistencia): ?>

<tr>

<td>

<div class="student-name">

<?= htmlspecialchars(
    $asistencia['nombres']
    . ' '
    . $asistencia['apellidos']
) ?>

</div>

</td>


<td>

<div class="student-document">

<?= htmlspecialchars(
    $asistencia['documento']
) ?>

</div>

</td>


<td>

<?= htmlspecialchars(
    date(
        'H:i:s',
        strtotime(
            $asistencia['hora_registro']
        )
    )
) ?>

</td>


<td>

<?php if (
    strtoupper(
        $asistencia['estado']
    ) === 'PRESENTE'
): ?>

<span class="present-badge">

<i class="bi bi-check-circle-fill"></i>

PRESENTE

</span>

<?php else: ?>

<span class="excuse-badge">

<i class="bi bi-info-circle-fill"></i>

<?= htmlspecialchars(
    $asistencia['estado']
) ?>

</span>

<?php endif; ?>

</td>


<td>

<?php if (
    !empty(
        $asistencia['estado_excusa']
    )
): ?>

<span class="excuse-badge">

<i class="bi bi-file-earmark-check"></i>

<?= htmlspecialchars(
    $asistencia['estado_excusa']
) ?>

</span>

<?php else: ?>

<span style="color:#9ab0b6;">
    —
</span>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>


<?php else: ?>

<div class="empty">

<i class="bi bi-person-x"></i>

Todavía no hay estudiantes registrados
en esta sesión.

</div>

<?php endif; ?>

</div>

</section>

<?php endif; ?>


</main>

</div>


<script>

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
        document.getElementById('reloj');

    if(reloj){

        reloj.textContent =
            horas
            + ':'
            + minutos
            + ':'
            + segundos;
    }
}

actualizarReloj();

setInterval(
    actualizarReloj,
    1000
);

</script>

</body>

</html>
```
