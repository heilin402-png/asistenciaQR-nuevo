<?php

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/conexion.php";

date_default_timezone_set('America/Bogota');

$idUsuario = (int) $_SESSION['id_usuario'];
$nombreUsuario = $_SESSION['nombre'] ?? 'Docente';

$mensaje = '';
$tipoMensaje = '';

/* =========================================================
   CREAR SESIÓN
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {

    if ($_POST['accion'] === 'crear_sesion') {

        $idCurso = (int) ($_POST['id_curso'] ?? 0);

        if ($idCurso <= 0) {

            $mensaje = 'Selecciona un curso válido.';
            $tipoMensaje = 'error';

        } else {

            $sqlVerificar = "
                SELECT id_curso
                FROM docente_curso
                WHERE id_usuario = ?
                AND id_curso = ?
                LIMIT 1
            ";

            $stmtVerificar = mysqli_prepare($conexion, $sqlVerificar);

            if ($stmtVerificar) {

                mysqli_stmt_bind_param(
                    $stmtVerificar,
                    "ii",
                    $idUsuario,
                    $idCurso
                );

                mysqli_stmt_execute($stmtVerificar);

                $resultadoVerificar = mysqli_stmt_get_result($stmtVerificar);
                $cursoPermitido = mysqli_fetch_assoc($resultadoVerificar);

                mysqli_stmt_close($stmtVerificar);

                if (!$cursoPermitido) {

                    $mensaje = 'No tienes permiso para utilizar este curso.';
                    $tipoMensaje = 'error';

                } else {

                    $fecha = date('Y-m-d');
                    $horaInicio = date('Y-m-d H:i:s');
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
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            ?
                        )
                    ";

                    $stmtCrear = mysqli_prepare($conexion, $sqlCrear);

                    if ($stmtCrear) {

                        mysqli_stmt_bind_param(
                            $stmtCrear,
                            "iisss",
                            $idUsuario,
                            $idCurso,
                            $fecha,
                            $horaInicio,
                            $estado
                        );

                        if (mysqli_stmt_execute($stmtCrear)) {

                            $idNuevaSesion = mysqli_insert_id($conexion);

                            mysqli_stmt_close($stmtCrear);

                            header(
                                "Location: asistencia.php?sesion=" .
                                $idNuevaSesion
                            );

                            exit();

                        } else {

                            $mensaje = 'No fue posible crear la sesión.';
                            $tipoMensaje = 'error';

                            mysqli_stmt_close($stmtCrear);
                        }

                    } else {

                        $mensaje = 'No fue posible preparar la sesión.';
                        $tipoMensaje = 'error';
                    }
                }

            } else {

                $mensaje = 'No fue posible verificar el curso.';
                $tipoMensaje = 'error';
            }
        }
    }


    /* =====================================================
       CERRAR SESIÓN
    ===================================================== */

    if ($_POST['accion'] === 'cerrar_sesion') {

        $idSesionCerrar = (int) ($_POST['id_sesion'] ?? 0);

        if ($idSesionCerrar > 0) {

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

                header(
                    "Location: asistencia.php?sesion=" .
                    $idSesionCerrar
                );

                exit();
            }
        }
    }
}


/* =========================================================
   CARGAR CURSOS DEL DOCENTE
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

$stmtCursos = mysqli_prepare($conexion, $sqlCursos);

if ($stmtCursos) {

    mysqli_stmt_bind_param(
        $stmtCursos,
        "i",
        $idUsuario
    );

    mysqli_stmt_execute($stmtCursos);

    $resultadoCursos = mysqli_stmt_get_result($stmtCursos);

    while ($fila = mysqli_fetch_assoc($resultadoCursos)) {
        $cursos[] = $fila;
    }

    mysqli_stmt_close($stmtCursos);
}


/* =========================================================
   CARGAR SESIONES DEL DOCENTE
========================================================= */

$sesiones = [];

$sqlSesiones = "
    SELECT
        s.id_sesion,
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

    $resultadoSesiones = mysqli_stmt_get_result(
        $stmtSesiones
    );

    while ($fila = mysqli_fetch_assoc($resultadoSesiones)) {
        $sesiones[] = $fila;
    }

    mysqli_stmt_close($stmtSesiones);
}


/* =========================================================
   SESIÓN SELECCIONADA
========================================================= */

$idSesionSeleccionada = (int) (
    $_GET['sesion'] ?? 0
);

$sesionActual = null;


/* Si viene una sesión por URL */
if ($idSesionSeleccionada > 0) {

    foreach ($sesiones as $sesion) {

        if (
            (int)$sesion['id_sesion']
            === $idSesionSeleccionada
        ) {

            $sesionActual = $sesion;
            break;
        }
    }
}


/* Si no viene ninguna, buscar una abierta */
if (!$sesionActual) {

    foreach ($sesiones as $sesion) {

        if ($sesion['estado'] === 'ABIERTA') {

            $sesionActual = $sesion;

            $idSesionSeleccionada =
                (int)$sesion['id_sesion'];

            break;
        }
    }
}


/* =========================================================
   CARGAR ASISTENCIAS
========================================================= */

$asistencias = [];

$totalPresentes = 0;
$totalExcusas = 0;

if ($sesionActual) {

    $sqlAsistencia = "
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

    $stmtAsistencia = mysqli_prepare(
        $conexion,
        $sqlAsistencia
    );

    if ($stmtAsistencia) {

        mysqli_stmt_bind_param(
            $stmtAsistencia,
            "i",
            $idSesionSeleccionada
        );

        mysqli_stmt_execute($stmtAsistencia);

        $resultadoAsistencia =
            mysqli_stmt_get_result(
                $stmtAsistencia
            );

        while (
            $fila =
            mysqli_fetch_assoc($resultadoAsistencia)
        ) {

            $asistencias[] = $fila;

            if ($fila['estado'] === 'PRESENTE') {
                $totalPresentes++;
            }

            if (
                !empty($fila['estado_excusa'])
            ) {
                $totalExcusas++;
            }
        }

        mysqli_stmt_close($stmtAsistencia);
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

    <title>Asistencia | Asistencia QR</title>

    <!-- =====================================================
         LIBRERÍA PARA LEER QR
    ====================================================== -->

    <script src="https://unpkg.com/html5-qrcode"></script>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background:
                linear-gradient(
                    135deg,
                    #eefcfb 0%,
                    #eef9f9 45%,
                    #eef1ff 100%
                );
            color: #18546a;
            min-height: 100vh;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        /* =====================================================
           SIDEBAR
        ====================================================== */

        .sidebar {
            width: 270px;
            background: rgba(255,255,255,.86);
            border-right: 1px solid rgba(110,180,190,.15);
            padding: 36px 18px 24px;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 10;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 0 14px 25px;
            border-bottom: 1px solid #e3eeee;
            margin-bottom: 26px;
        }

        .brand-icon {
            width: 54px;
            height: 54px;
            border-radius: 17px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 25px rgba(46,120,130,.10);
            font-size: 25px;
        }

        .brand-title {
            font-size: 19px;
            font-weight: 800;
            color: #14536a;
        }

        .brand-subtitle {
            font-size: 11px;
            color: #7c9ba4;
            margin-top: 5px;
        }

        .menu-title {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1px;
            color: #7898a1;
            margin: 20px 16px 12px;
        }

        .menu-title::before {
            content: "";
            display: inline-block;
            width: 18px;
            height: 2px;
            background: #54ccd0;
            vertical-align: middle;
            margin-right: 9px;
        }

        .menu-item {
            height: 54px;
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 0 14px;
            border-radius: 17px;
            margin-bottom: 7px;
            color: #587f8c;
            font-size: 14px;
            font-weight: 700;
        }

        .menu-item:hover {
            background: #f0fbfb;
        }

        .menu-item.active {
            background: linear-gradient(
                90deg,
                #d9f8f7,
                #eefbfb
            );
            color: #08637a;
            border-left: 4px solid #29c7c9;
            padding-left: 10px;
        }

        .menu-icon {
            width: 39px;
            height: 39px;
            border-radius: 13px;
            background: #f4fbfb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .menu-item.active .menu-icon {
            background: #c8f5f3;
        }

        .user-box {
            position: absolute;
            left: 22px;
            right: 22px;
            bottom: 75px;
            background: rgba(255,255,255,.8);
            border-radius: 18px;
            padding: 13px;
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 13px;
            background: linear-gradient(
                135deg,
                #20bfc4,
                #18aeb8
            );
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
        }

        .user-name {
            font-weight: 800;
            color: #215c6e;
            font-size: 13px;
        }

        .user-role {
            color: #7f9ba4;
            font-size: 9px;
            font-weight: 800;
            margin-top: 3px;
            letter-spacing: .8px;
        }

        .online {
            margin-left: auto;
            width: 5px;
            height: 5px;
            background: #27c89c;
            border-radius: 50%;
        }

        .logout {
            position: absolute;
            left: 22px;
            right: 22px;
            bottom: 18px;
            height: 45px;
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 0 12px;
            color: #668994;
            font-size: 12px;
            font-weight: 700;
        }

        /* =====================================================
           CONTENIDO
        ====================================================== */

        .main {
            margin-left: 270px;
            width: calc(100% - 270px);
            padding: 18px 18px 45px;
        }

        .topbar {
            height: 110px;
            border-radius: 25px;
            background: rgba(255,255,255,.86);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 25px;
            margin-bottom: 18px;
            box-shadow: 0 10px 35px rgba(56,125,145,.04);
        }

        .top-title {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .top-line {
            width: 9px;
            height: 48px;
            border-radius: 8px;
            background: linear-gradient(
                #21c7c7,
                #7d72dc
            );
        }

        .top-title h1 {
            font-size: 27px;
            color: #15556b;
        }

        .top-title p {
            font-size: 13px;
            color: #76949d;
            margin-top: 5px;
            font-weight: 600;
        }

        .clock-box {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 17px;
            background: #fbffff;
        }

        .clock-icon {
            width: 37px;
            height: 37px;
            border-radius: 12px;
            background: #e9fbfb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .clock-time {
            font-size: 17px;
            font-weight: 800;
            color: #15566b;
        }

        .clock-date {
            font-size: 10px;
            color: #839ba3;
            margin-top: 3px;
        }

        /* =====================================================
           ENCABEZADO
        ====================================================== */

        .hero {
            background: rgba(255,255,255,.82);
            border-radius: 25px;
            padding: 25px 30px;
            margin-bottom: 18px;
        }

        .tag {
            display: inline-block;
            padding: 8px 13px;
            background: #e8fafa;
            color: #087184;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .6px;
        }

        .hero h2 {
            font-size: 26px;
            color: #15556a;
            margin-top: 14px;
        }

        .hero p {
            font-size: 13px;
            color: #6e8e99;
            margin-top: 8px;
        }

        /* =====================================================
           TARJETAS
        ====================================================== */

        .card {
            background: rgba(255,255,255,.87);
            border-radius: 23px;
            padding: 24px;
            margin-bottom: 18px;
            box-shadow: 0 8px 28px rgba(59,116,135,.04);
        }

        .card-title {
            color: #17586d;
            font-size: 17px;
            font-weight: 800;
            margin-bottom: 7px;
        }

        .card-subtitle {
            color: #78949d;
            font-size: 12px;
        }

        .session-create {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 18px;
            align-items: end;
        }

        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 800;
            color: #4f7986;
            margin-bottom: 8px;
        }

        select {
            width: 100%;
            height: 47px;
            border: 1px solid #dcebed;
            border-radius: 13px;
            padding: 0 14px;
            background: white;
            color: #396b79;
            font-weight: 700;
            outline: none;
        }

        select:focus {
            border-color: #4ccbd0;
        }

        .btn {
            height: 47px;
            border: none;
            border-radius: 13px;
            padding: 0 22px;
            cursor: pointer;
            font-weight: 800;
            font-size: 12px;
        }

        .btn-primary {
            color: white;
            background: linear-gradient(
                135deg,
                #20bfc3,
                #28aebc
            );
        }

        .btn-danger {
            color: white;
            background: linear-gradient(
                135deg,
                #e67d87,
                #d96d7b
            );
        }

        /* =====================================================
           INFORMACIÓN SESIÓN
        ====================================================== */

        .session-info {
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 20px;
        }

        .session-name {
            font-size: 22px;
            color: #17576b;
            font-weight: 800;
        }

        .session-details {
            display: flex;
            gap: 18px;
            margin-top: 8px;
            color: #78949d;
            font-size: 12px;
        }

        .badge {
            display: inline-block;
            padding: 7px 11px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 800;
        }

        .badge-open {
            color: #13886d;
            background: #e6faf3;
        }

        .badge-closed {
            color: #9a6570;
            background: #fae9ec;
        }

        /* =====================================================
           ESCÁNER
        ====================================================== */

        .scanner-card {
            text-align: center;
        }

        .scanner-heading {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 11px;
            margin-bottom: 5px;
        }

        .scanner-heading-icon {
            width: 43px;
            height: 43px;
            border-radius: 14px;
            background: #e3f9f8;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .scanner-title {
            font-size: 18px;
            font-weight: 800;
            color: #18576b;
        }

        .scanner-description {
            color: #78969f;
            font-size: 12px;
            margin-bottom: 17px;
        }

        #qr-reader {
            width: 100%;
            max-width: 520px;
            margin: 0 auto;
            border: none !important;
            border-radius: 20px;
            overflow: hidden;
            background: #182022;
        }

        #qr-reader video {
            border-radius: 20px;
        }

        #qr-reader__scan_region {
            min-height: 320px;
        }

        #qr-reader__dashboard {
            padding: 12px !important;
            background: white;
        }

        #qr-reader__dashboard button {
            border: none !important;
            border-radius: 10px !important;
            padding: 9px 15px !important;
            background: #e7f8f8 !important;
            color: #176478 !important;
            font-weight: 700 !important;
            cursor: pointer;
        }

        .scan-message {
            max-width: 900px;
            margin: 14px auto 0;
            padding: 12px 15px;
            border-radius: 12px;
            display: none;
            font-size: 12px;
            font-weight: 700;
            text-align: left;
        }

        .scan-message.success {
            display: block;
            color: #14755e;
            background: #e8faf4;
        }

        .scan-message.error {
            display: block;
            color: #a1515b;
            background: #fff0f1;
        }

        .scan-message.info {
            display: block;
            color: #486e79;
            background: #eef9fa;
        }

        /* =====================================================
           RESUMEN
        ====================================================== */

        .summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-bottom: 18px;
        }

        .summary-card {
            background: rgba(255,255,255,.87);
            border-radius: 20px;
            padding: 20px;
        }

        .summary-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: #e7faf9;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
        }

        .summary-number {
            font-size: 26px;
            font-weight: 800;
            color: #296376;
        }

        .summary-label {
            color: #819ba3;
            font-size: 10px;
            font-weight: 800;
            margin-top: 4px;
            text-transform: uppercase;
        }

        /* =====================================================
           TABLA
        ====================================================== */

        .table-wrap {
            overflow-x: auto;
            margin-top: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            font-size: 10px;
            color: #78949d;
            text-transform: uppercase;
            padding: 13px;
            border-bottom: 1px solid #e8eeee;
        }

        td {
            padding: 15px 13px;
            font-size: 12px;
            color: #4c7180;
            border-bottom: 1px solid #eef2f3;
        }

        .student-name {
            font-weight: 800;
            color: #235f72;
        }

        .empty {
            text-align: center;
            padding: 35px;
            color: #8aa1a8;
            font-size: 13px;
        }

        .alert {
            padding: 13px 17px;
            border-radius: 13px;
            margin-bottom: 18px;
            font-size: 12px;
            font-weight: 700;
        }

        .alert-error {
            background: #fff0f1;
            color: #a3545c;
        }

        /* =====================================================
           RESPONSIVE
        ====================================================== */

        @media (max-width: 900px) {

            .sidebar {
                width: 220px;
            }

            .main {
                margin-left: 220px;
                width: calc(100% - 220px);
            }

            .summary {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 700px) {

            .sidebar {
                position: relative;
                width: 100%;
                min-height: auto;
            }

            .layout {
                display: block;
            }

            .user-box,
            .logout {
                position: static;
                margin-top: 10px;
            }

            .main {
                margin-left: 0;
                width: 100%;
                padding: 10px;
            }

            .topbar {
                height: auto;
                padding: 18px;
                gap: 15px;
                align-items: flex-start;
            }

            .session-create,
            .session-info {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<div class="layout">

    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <aside class="sidebar">

        <div class="brand">

            <div class="brand-icon">
                📷
            </div>

            <div>
                <div class="brand-title">
                    ASISTENCIA QR
                </div>

                <div class="brand-subtitle">
                    Sistema académico
                </div>
            </div>

        </div>


        <div class="menu-title">
            NAVEGACIÓN
        </div>

        <a
            href="dashboard.php"
            class="menu-item"
        >
            <span class="menu-icon">▦</span>
            <span>Inicio</span>
        </a>


        <div class="menu-title">
            GESTIÓN ACADÉMICA
        </div>

        <a
            href="cursos.php"
            class="menu-item"
        >
            <span class="menu-icon">🎓</span>
            <span>Mis cursos</span>
        </a>


        <div class="menu-title">
            CONTROL
        </div>

        <a
            href="asistencia.php"
            class="menu-item active"
        >
            <span class="menu-icon">▧</span>
            <span>Asistencia</span>
        </a>

        <a
            href="reportes.php"
            class="menu-item"
        >
            <span class="menu-icon">▥</span>
            <span>Reportes</span>
        </a>


        <div class="user-box">

            <div class="user-avatar">
                <?php
                echo strtoupper(
                    substr($nombreUsuario, 0, 1)
                );
                ?>
            </div>

            <div>
                <div class="user-name">
                    <?= htmlspecialchars($nombreUsuario) ?>
                </div>

                <div class="user-role">
                    DOCENTE
                </div>
            </div>

            <div class="online"></div>

        </div>


        <a
            href="../logout.php"
            class="logout"
        >
            ↪
            <span>Cerrar sesión</span>
        </a>

    </aside>


    <!-- =====================================================
         CONTENIDO PRINCIPAL
    ====================================================== -->

    <main class="main">


        <header class="topbar">

            <div class="top-title">

                <div class="top-line"></div>

                <div>
                    <h1>Asistencia</h1>

                    <p>
                        Control de asistencia mediante código QR
                    </p>
                </div>

            </div>


            <div class="clock-box">

                <div class="clock-icon">
                    ◷
                </div>

                <div>

                    <div
                        class="clock-time"
                        id="reloj"
                    >
                        00:00:00
                    </div>

                    <div
                        class="clock-date"
                        id="fecha"
                    >
                        --/--/----
                    </div>

                </div>

            </div>

        </header>


        <section class="hero">

            <span class="tag">
                ▦ CONTROL DE ASISTENCIA
            </span>

            <h2>
                Registro de asistencia
            </h2>

            <p>
                Crea una sesión y registra la asistencia
                de tus estudiantes mediante su código QR.
            </p>

        </section>


        <?php if ($mensaje !== ''): ?>

            <div class="alert alert-error">
                <?= htmlspecialchars($mensaje) ?>
            </div>

        <?php endif; ?>


        <!-- =================================================
             CREAR SESIÓN
        ================================================== -->

        <section class="card">

            <div class="session-create">

                <div>

                    <div class="card-title">
                        Nueva sesión de asistencia
                    </div>

                    <div class="card-subtitle">
                        Selecciona el curso para comenzar
                        una nueva sesión.
                    </div>

                    <form
                        method="POST"
                        style="margin-top:16px;"
                    >

                        <input
                            type="hidden"
                            name="accion"
                            value="crear_sesion"
                        >

                        <label class="form-label">
                            Curso
                        </label>

                        <select
                            name="id_curso"
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

                        <button
                            type="submit"
                            class="btn btn-primary"
                            style="margin-top:12px;"
                        >
                            + Iniciar sesión
                        </button>

                    </form>

                </div>

            </div>

        </section>


        <?php if ($sesionActual): ?>


            <!-- =============================================
                 INFORMACIÓN SESIÓN
            ============================================== -->

            <section class="card">

                <div class="session-info">

                    <div>

                        <div class="card-title">
                            Sesión actual
                        </div>

                        <div class="session-name">
                            <?= htmlspecialchars(
                                $sesionActual['nombre_curso']
                            ) ?>
                        </div>

                        <div class="session-details">

                            <span>
                                📅
                                <?= date(
                                    'd/m/Y',
                                    strtotime(
                                        $sesionActual['fecha']
                                    )
                                ) ?>
                            </span>

                            <span>
                                🕐
                                <?= date(
                                    'H:i',
                                    strtotime(
                                        $sesionActual['hora_inicio']
                                    )
                                ) ?>
                            </span>

                        </div>

                        <div style="margin-top:12px;">

                            <?php if (
                                $sesionActual['estado']
                                === 'ABIERTA'
                            ): ?>

                                <span class="badge badge-open">
                                    ● SESIÓN ABIERTA
                                </span>

                            <?php else: ?>

                                <span class="badge badge-closed">
                                    ● SESIÓN CERRADA
                                </span>

                            <?php endif; ?>

                        </div>

                    </div>


                    <?php if (
                        $sesionActual['estado']
                        === 'ABIERTA'
                    ): ?>

                        <form method="POST">

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
                                class="btn btn-danger"
                                onclick="
                                    return confirm(
                                        '¿Deseas cerrar esta sesión de asistencia?'
                                    );
                                "
                            >
                                Cerrar sesión
                            </button>

                        </form>

                    <?php endif; ?>

                </div>

            </section>


            <!-- =============================================
                 ESCÁNER QR
            ============================================== -->

            <?php if (
                $sesionActual['estado']
                === 'ABIERTA'
            ): ?>

                <section class="card scanner-card">

                    <div class="scanner-heading">

                        <div class="scanner-heading-icon">
                            ▧
                        </div>

                        <div class="scanner-title">
                            Escanear QR del estudiante
                        </div>

                    </div>

                    <div class="scanner-description">
                        Apunta la cámara al código QR personal
                        del estudiante.
                    </div>


                    <div id="qr-reader"></div>


                    <div
                        id="scan-message"
                        class="scan-message"
                    ></div>

                </section>

            <?php endif; ?>


            <!-- =============================================
                 RESUMEN
            ============================================== -->

            <div class="summary">

                <div class="summary-card">

                    <div class="summary-icon">
                        ✓
                    </div>

                    <div class="summary-number">
                        <?= $totalPresentes ?>
                    </div>

                    <div class="summary-label">
                        Presentes
                    </div>

                </div>


                <div class="summary-card">

                    <div class="summary-icon">
                        ▣
                    </div>

                    <div class="summary-number">
                        <?= $totalExcusas ?>
                    </div>

                    <div class="summary-label">
                        Excusas
                    </div>

                </div>


                <div class="summary-card">

                    <div class="summary-icon">
                        ◷
                    </div>

                    <div class="summary-number">
                        <?= count($asistencias) ?>
                    </div>

                    <div class="summary-label">
                        Registros
                    </div>

                </div>

            </div>


            <!-- =============================================
                 TABLA
            ============================================== -->

            <section class="card">

                <div class="card-title">
                    Registros de asistencia
                </div>

                <div class="card-subtitle">
                    Estudiantes registrados en esta sesión.
                </div>


                <div class="table-wrap">

                    <?php if (!empty($asistencias)): ?>

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
                                        Estado
                                    </th>

                                    <th>
                                        Hora
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach (
                                    $asistencias
                                    as $asistencia
                                ): ?>

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
                                            <?= htmlspecialchars(
                                                $asistencia['documento']
                                            ) ?>
                                        </td>

                                        <td>

                                            <span
                                                class="badge badge-open"
                                            >
                                                <?= htmlspecialchars(
                                                    $asistencia['estado']
                                                ) ?>
                                            </span>

                                        </td>

                                        <td>

                                            <?= date(
                                                'H:i:s',
                                                strtotime(
                                                    $asistencia['hora_registro']
                                                )
                                            ) ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    <?php else: ?>

                        <div class="empty">
                            Todavía no hay estudiantes registrados
                            en esta sesión.
                        </div>

                    <?php endif; ?>

                </div>

            </section>


        <?php else: ?>


            <section class="card">

                <div class="empty">

                    No hay una sesión de asistencia abierta.

                    <br><br>

                    Selecciona un curso y pulsa
                    <strong>Iniciar sesión</strong>
                    para comenzar.

                </div>

            </section>


        <?php endif; ?>


    </main>

</div>


<!-- =========================================================
     JAVASCRIPT
========================================================== -->

<script>

    /* =====================================================
       RELOJ
    ====================================================== */

    function actualizarReloj() {

        const ahora = new Date();

        const horas =
            String(ahora.getHours()).padStart(2, '0');

        const minutos =
            String(ahora.getMinutes()).padStart(2, '0');

        const segundos =
            String(ahora.getSeconds()).padStart(2, '0');

        const dia =
            String(ahora.getDate()).padStart(2, '0');

        const mes =
            String(ahora.getMonth() + 1).padStart(2, '0');

        const anio =
            ahora.getFullYear();

        const reloj =
            document.getElementById('reloj');

        const fecha =
            document.getElementById('fecha');

        if (reloj) {
            reloj.textContent =
                horas + ':' + minutos + ':' + segundos;
        }

        if (fecha) {
            fecha.textContent =
                dia + '/' + mes + '/' + anio;
        }
    }

    actualizarReloj();

    setInterval(
        actualizarReloj,
        1000
    );


    /* =====================================================
       ESCÁNER QR
    ====================================================== */

    const idSesion =
        <?= $sesionActual
            ? (int)$sesionActual['id_sesion']
            : 0 ?>;

    const sesionAbierta =
        <?= (
            $sesionActual &&
            $sesionActual['estado'] === 'ABIERTA'
        )
            ? 'true'
            : 'false' ?>;

    let procesandoQR = false;

    let scanner = null;


    function mostrarMensaje(
        mensaje,
        tipo
    ) {

        const elemento =
            document.getElementById(
                'scan-message'
            );

        if (!elemento) {
            return;
        }

        elemento.className =
            'scan-message ' + tipo;

        elemento.textContent =
            mensaje;
    }


    async function registrarQR(
        contenidoQR
    ) {

        if (procesandoQR) {
            return;
        }

        procesandoQR = true;

        mostrarMensaje(
            'Procesando asistencia...',
            'info'
        );


        try {

            const documento =
                String(contenidoQR || '')
                .trim();


            if (documento === '') {

                throw new Error(
                    'El código QR está vacío.'
                );
            }


            if (idSesion <= 0) {

                throw new Error(
                    'No existe una sesión válida.'
                );
            }


            /*
             * IMPORTANTE:
             * Enviamos FormData porque
             * procesar_asistencia.php
             * recibe $_POST.
             */

            const datos =
                new FormData();

            datos.append(
                'documento',
                documento
            );

            datos.append(
                'id_sesion',
                idSesion
            );


            const respuesta =
                await fetch(
                    'procesar_asistencia.php',
                    {
                        method: 'POST',
                        body: datos,
                        cache: 'no-store'
                    }
                );


            const texto =
                await respuesta.text();


            console.log(
                'Respuesta del servidor:',
                texto
            );


            let resultado;

            try {

                resultado =
                    JSON.parse(texto);

            } catch (errorJSON) {

                console.error(
                    'Respuesta no válida:',
                    texto
                );

                throw new Error(
                    'El servidor devolvió una respuesta inesperada.'
                );
            }


            if (!resultado.success) {

                mostrarMensaje(
                    resultado.mensaje ||
                    'No fue posible registrar la asistencia.',
                    'error'
                );

                setTimeout(
                    () => {
                        procesandoQR = false;
                    },
                    1800
                );

                return;
            }


            mostrarMensaje(
                resultado.mensaje +
                ' Estudiante: ' +
                resultado.estudiante +
                ' | Hora: ' +
                resultado.hora,
                'success'
            );


            /*
             * Esperamos un momento para que
             * el docente vea el mensaje.
             */

            setTimeout(
                function () {

                    window.location.href =
                        'asistencia.php?sesion=' +
                        idSesion;

                },
                1300
            );


        } catch (error) {

            console.error(
                'Error:',
                error
            );

            mostrarMensaje(
                error.message ||
                'No fue posible comunicarse con el servidor.',
                'error'
            );

            setTimeout(
                () => {
                    procesandoQR = false;
                },
                1800
            );
        }
    }


    function iniciarScanner() {

        if (!sesionAbierta) {
            return;
        }

        const lector =
            document.getElementById('qr-reader');

        if (!lector) {
            return;
        }

        if (typeof Html5Qrcode === 'undefined') {

            mostrarMensaje(
                'No se pudo cargar el lector QR.',
                'error'
            );

            return;
        }


        scanner =
            new Html5Qrcode('qr-reader');


        const configuracion = {

            fps: 10,

            qrbox: {
                width: 250,
                height: 250
            },

            aspectRatio: 1.0

        };


        scanner.start(

            {
                facingMode: 'user'
            },

            configuracion,

            function(decodedText) {

                console.log(
                    'Código QR detectado:',
                    decodedText
                );

                registrarQR(decodedText);

            },

            function(errorMessage) {

                // El lector está buscando el QR.
                // No mostramos estos mensajes.

            }

        ).catch(function(error) {

            console.error(
                'Error de cámara:',
                error
            );

            mostrarMensaje(
                'No fue posible iniciar la cámara. Verifica los permisos del navegador.',
                'error'
            );

        });

    }

    


    /*
     * Esperar a que la página termine
     * de cargar antes de iniciar la cámara.
     */

    document.addEventListener(
        'DOMContentLoaded',
        function() {

            if (
                sesionAbierta &&
                typeof Html5Qrcode !== 'undefined'
            ) {

                iniciarScanner();

            }

        }
    );

</script>

</body>
</html>