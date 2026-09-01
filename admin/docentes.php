<?php

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/conexion.php";

date_default_timezone_set('America/Bogota');

/* =========================================================
   DATOS DEL USUARIO ACTUAL
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

/* =========================================================
   MENSAJES
========================================================= */

$mensaje = '';
$tipoMensaje = '';

/* =========================================================
   CREAR DOCENTE
========================================================= */

if (isset($_POST['agregar_docente'])) {

    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if (
        $nombre !== '' &&
        $apellido !== '' &&
        $usuario !== '' &&
        $password !== ''
    ) {

        $sqlExiste = "SELECT id_usuario
                      FROM usuarios
                      WHERE usuario = ?
                      LIMIT 1";

        $stmtExiste = mysqli_prepare($conexion, $sqlExiste);

        $existe = false;

        if ($stmtExiste) {

            mysqli_stmt_bind_param(
                $stmtExiste,
                "s",
                $usuario
            );

            mysqli_stmt_execute($stmtExiste);

            $resultadoExiste = mysqli_stmt_get_result($stmtExiste);

            if (mysqli_fetch_assoc($resultadoExiste)) {
                $existe = true;
            }

            mysqli_stmt_close($stmtExiste);
        }

        if ($existe) {

            $mensaje = 'El usuario o correo ya está registrado.';
            $tipoMensaje = 'error';

        } else {

            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $sql = "INSERT INTO usuarios
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
                        ?,
                        ?,
                        ?,
                        ?,
                        2,
                        'ACTIVO',
                        NOW()
                    )";

            $stmt = mysqli_prepare($conexion, $sql);

            if ($stmt) {

                mysqli_stmt_bind_param(
                    $stmt,
                    "ssss",
                    $nombre,
                    $apellido,
                    $usuario,
                    $passwordHash
                );

                if (mysqli_stmt_execute($stmt)) {

                    $mensaje = 'Docente creado correctamente.';
                    $tipoMensaje = 'success';

                } else {

                    $mensaje = 'No fue posible crear el docente.';
                    $tipoMensaje = 'error';
                }

                mysqli_stmt_close($stmt);
            }
        }

    } else {

        $mensaje = 'Completa todos los datos del docente.';
        $tipoMensaje = 'error';
    }
}

/* =========================================================
   EDITAR DOCENTE
========================================================= */

if (isset($_POST['editar_docente'])) {

    $idDocente = (int)($_POST['id_usuario'] ?? 0);

    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if (
        $idDocente > 0 &&
        $nombre !== '' &&
        $apellido !== '' &&
        $usuario !== ''
    ) {

        $sqlExiste = "SELECT id_usuario
                      FROM usuarios
                      WHERE usuario = ?
                      AND id_usuario <> ?
                      LIMIT 1";

        $stmtExiste = mysqli_prepare($conexion, $sqlExiste);

        $existe = false;

        if ($stmtExiste) {

            mysqli_stmt_bind_param(
                $stmtExiste,
                "si",
                $usuario,
                $idDocente
            );

            mysqli_stmt_execute($stmtExiste);

            $resultadoExiste = mysqli_stmt_get_result($stmtExiste);

            if (mysqli_fetch_assoc($resultadoExiste)) {
                $existe = true;
            }

            mysqli_stmt_close($stmtExiste);
        }

        if ($existe) {

            $mensaje =
                'El usuario o correo ya pertenece a otro usuario.';

            $tipoMensaje = 'error';

        } else {

            if ($password !== '') {

                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $sql = "UPDATE usuarios
                        SET
                            nombre = ?,
                            apellido = ?,
                            usuario = ?,
                            password = ?
                        WHERE
                            id_usuario = ?
                            AND id_rol = 2";

                $stmt = mysqli_prepare($conexion, $sql);

                if ($stmt) {

                    mysqli_stmt_bind_param(
                        $stmt,
                        "ssssi",
                        $nombre,
                        $apellido,
                        $usuario,
                        $passwordHash,
                        $idDocente
                    );

                    $ejecutado = mysqli_stmt_execute($stmt);

                    mysqli_stmt_close($stmt);

                    if ($ejecutado) {

                        $mensaje =
                            'Docente actualizado correctamente.';

                        $tipoMensaje = 'success';

                    } else {

                        $mensaje =
                            'No fue posible actualizar el docente.';

                        $tipoMensaje = 'error';
                    }
                }

            } else {

                $sql = "UPDATE usuarios
                        SET
                            nombre = ?,
                            apellido = ?,
                            usuario = ?
                        WHERE
                            id_usuario = ?
                            AND id_rol = 2";

                $stmt = mysqli_prepare($conexion, $sql);

                if ($stmt) {

                    mysqli_stmt_bind_param(
                        $stmt,
                        "sssi",
                        $nombre,
                        $apellido,
                        $usuario,
                        $idDocente
                    );

                    $ejecutado = mysqli_stmt_execute($stmt);

                    mysqli_stmt_close($stmt);

                    if ($ejecutado) {

                        $mensaje =
                            'Docente actualizado correctamente.';

                        $tipoMensaje = 'success';

                    } else {

                        $mensaje =
                            'No fue posible actualizar el docente.';

                        $tipoMensaje = 'error';
                    }
                }
            }
        }

    } else {

        $mensaje =
            'Completa los datos obligatorios del docente.';

        $tipoMensaje = 'error';
    }
}

/* =========================================================
   CAMBIAR ESTADO DEL DOCENTE
========================================================= */

if (isset($_POST['cambiar_estado_docente'])) {

    $idDocente = (int)(
        $_POST['id_usuario'] ?? 0
    );

    $nuevoEstado =
        ($_POST['nuevo_estado'] ?? '') === 'ACTIVO'
        ? 'ACTIVO'
        : 'INACTIVO';

    if ($idDocente > 0) {

        $sql = "UPDATE usuarios
                SET estado = ?
                WHERE id_usuario = ?
                AND id_rol = 2";

        $stmt = mysqli_prepare($conexion, $sql);

        if ($stmt) {

            mysqli_stmt_bind_param(
                $stmt,
                "si",
                $nuevoEstado,
                $idDocente
            );

            if (mysqli_stmt_execute($stmt)) {

                $mensaje =
                    $nuevoEstado === 'ACTIVO'
                    ? 'Docente activado correctamente.'
                    : 'Docente desactivado correctamente.';

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
   ASIGNAR VARIOS CURSOS
========================================================= */

if (isset($_POST['asignar_cursos'])) {

    $idDocente = (int)(
        $_POST['id_usuario'] ?? 0
    );

    $cursosSeleccionados =
        $_POST['id_cursos'] ?? [];

    if (!is_array($cursosSeleccionados)) {
        $cursosSeleccionados = [];
    }

    $cursosSeleccionados = array_values(
        array_unique(
            array_filter(
                array_map(
                    'intval',
                    $cursosSeleccionados
                ),
                function ($id) {
                    return $id > 0;
                }
            )
        )
    );

    if (
        $idDocente > 0 &&
        count($cursosSeleccionados) > 0
    ) {

        /* Verificar docente */

        $sqlDocente = "SELECT id_usuario
                       FROM usuarios
                       WHERE id_usuario = ?
                       AND id_rol = 2
                       LIMIT 1";

        $stmtDocente = mysqli_prepare(
            $conexion,
            $sqlDocente
        );

        $docenteValido = false;

        if ($stmtDocente) {

            mysqli_stmt_bind_param(
                $stmtDocente,
                "i",
                $idDocente
            );

            mysqli_stmt_execute($stmtDocente);

            $resultadoDocente =
                mysqli_stmt_get_result(
                    $stmtDocente
                );

            if (mysqli_fetch_assoc($resultadoDocente)) {
                $docenteValido = true;
            }

            mysqli_stmt_close($stmtDocente);
        }

        if ($docenteValido) {

            $asignados = 0;
            $yaAsignados = 0;
            $errores = 0;

            foreach ($cursosSeleccionados as $idCurso) {

                /* Verificar que el curso exista */

                $sqlCurso = "SELECT id_curso
                             FROM cursos
                             WHERE id_curso = ?
                             LIMIT 1";

                $stmtCurso = mysqli_prepare(
                    $conexion,
                    $sqlCurso
                );

                $cursoValido = false;

                if ($stmtCurso) {

                    mysqli_stmt_bind_param(
                        $stmtCurso,
                        "i",
                        $idCurso
                    );

                    mysqli_stmt_execute($stmtCurso);

                    $resultadoCurso =
                        mysqli_stmt_get_result(
                            $stmtCurso
                        );

                    if (
                        mysqli_fetch_assoc(
                            $resultadoCurso
                        )
                    ) {
                        $cursoValido = true;
                    }

                    mysqli_stmt_close($stmtCurso);
                }

                if (!$cursoValido) {
                    $errores++;
                    continue;
                }

                /* Verificar si ya está asignado */

                $sqlExiste = "SELECT id_docente_curso
                              FROM docente_curso
                              WHERE id_usuario = ?
                              AND id_curso = ?
                              LIMIT 1";

                $stmtExiste = mysqli_prepare(
                    $conexion,
                    $sqlExiste
                );

                $asignado = false;

                if ($stmtExiste) {

                    mysqli_stmt_bind_param(
                        $stmtExiste,
                        "ii",
                        $idDocente,
                        $idCurso
                    );

                    mysqli_stmt_execute($stmtExiste);

                    $resultadoExiste =
                        mysqli_stmt_get_result(
                            $stmtExiste
                        );

                    if (
                        mysqli_fetch_assoc(
                            $resultadoExiste
                        )
                    ) {
                        $asignado = true;
                    }

                    mysqli_stmt_close($stmtExiste);
                }

                if ($asignado) {

                    $yaAsignados++;
                    continue;
                }

                /* Insertar asignación */

                $sqlInsertar = "INSERT INTO docente_curso
                                (
                                    id_usuario,
                                    id_curso,
                                    fecha_asignacion
                                )
                                VALUES
                                (
                                    ?,
                                    ?,
                                    NOW()
                                )";

                $stmtInsertar = mysqli_prepare(
                    $conexion,
                    $sqlInsertar
                );

                if ($stmtInsertar) {

                    mysqli_stmt_bind_param(
                        $stmtInsertar,
                        "ii",
                        $idDocente,
                        $idCurso
                    );

                    if (
                        mysqli_stmt_execute(
                            $stmtInsertar
                        )
                    ) {

                        $asignados++;

                    } else {

                        $errores++;
                    }

                    mysqli_stmt_close(
                        $stmtInsertar
                    );
                }
            }

            if ($asignados > 0) {

                $mensaje =
                    $asignados === 1
                    ? '1 curso asignado correctamente.'
                    : $asignados . ' cursos asignados correctamente.';

                if ($yaAsignados > 0) {

                    $mensaje .=
                        ' ' .
                        $yaAsignados .
                        (
                            $yaAsignados === 1
                            ? ' ya estaba asignado.'
                            : ' ya estaban asignados.'
                        );
                }

                $tipoMensaje = 'success';

            } elseif ($yaAsignados > 0) {

                $mensaje =
                    $yaAsignados === 1
                    ? 'El curso seleccionado ya estaba asignado.'
                    : 'Los cursos seleccionados ya estaban asignados.';

                $tipoMensaje = 'error';

            } else {

                $mensaje =
                    'No fue posible asignar los cursos.';

                $tipoMensaje = 'error';
            }
        }

    } else {

        $mensaje =
            'Selecciona al menos un curso para asignar.';

        $tipoMensaje = 'error';
    }
}

/* =========================================================
   DESASIGNAR CURSO
========================================================= */

if (isset($_POST['desasignar_curso'])) {

    $idDocenteCurso = (int)(
        $_POST['id_docente_curso'] ?? 0
    );

    if ($idDocenteCurso > 0) {

        $sql = "DELETE FROM docente_curso
                WHERE id_docente_curso = ?";

        $stmt = mysqli_prepare(
            $conexion,
            $sql
        );

        if ($stmt) {

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $idDocenteCurso
            );

            if (mysqli_stmt_execute($stmt)) {

                $mensaje =
                    'Curso desasignado correctamente.';

                $tipoMensaje = 'success';

            } else {

                $mensaje =
                    'No fue posible desasignar el curso.';

                $tipoMensaje = 'error';
            }

            mysqli_stmt_close($stmt);
        }
    }
}

/* =========================================================
   DOCENTE SELECCIONADO
========================================================= */

$idDocenteSeleccionado = isset($_GET['docente'])
    ? (int)$_GET['docente']
    : 0;

$docenteSeleccionado = null;

if ($idDocenteSeleccionado > 0) {

    $sqlDocente = "SELECT
                        id_usuario,
                        nombre,
                        apellido,
                        usuario,
                        estado,
                        fecha_creacion
                   FROM usuarios
                   WHERE id_usuario = ?
                   AND id_rol = 2
                   LIMIT 1";

    $stmtDocente = mysqli_prepare(
        $conexion,
        $sqlDocente
    );

    if ($stmtDocente) {

        mysqli_stmt_bind_param(
            $stmtDocente,
            "i",
            $idDocenteSeleccionado
        );

        mysqli_stmt_execute(
            $stmtDocente
        );

        $resultadoDocente =
            mysqli_stmt_get_result(
                $stmtDocente
            );

        $docenteSeleccionado =
            mysqli_fetch_assoc(
                $resultadoDocente
            );

        mysqli_stmt_close(
            $stmtDocente
        );
    }
}

/* =========================================================
   TODOS LOS DOCENTES
========================================================= */

$docentes = [];

$sqlDocentes = "SELECT
                    u.id_usuario,
                    u.nombre,
                    u.apellido,
                    u.usuario,
                    u.estado,
                    u.fecha_creacion,

                    COUNT(dc.id_docente_curso)
                        AS total_cursos,

                    GROUP_CONCAT(
                        DISTINCT c.nombre_curso
                        ORDER BY
                            CAST(c.nombre_curso AS UNSIGNED) ASC,
                            c.nombre_curso ASC
                        SEPARATOR ' · '
                    ) AS cursos_asignados

                FROM usuarios u

                LEFT JOIN docente_curso dc
                    ON dc.id_usuario = u.id_usuario

                LEFT JOIN cursos c
                    ON c.id_curso = dc.id_curso

                WHERE u.id_rol = 2

                GROUP BY
                    u.id_usuario,
                    u.nombre,
                    u.apellido,
                    u.usuario,
                    u.estado,
                    u.fecha_creacion

                ORDER BY
                    u.nombre ASC,
                    u.apellido ASC";

$resultadoDocentes = mysqli_query(
    $conexion,
    $sqlDocentes
);

if ($resultadoDocentes) {

    while (
        $fila = mysqli_fetch_assoc(
            $resultadoDocentes
        )
    ) {

        $docentes[] = $fila;
    }
}

/* =========================================================
   TODOS LOS CURSOS
========================================================= */

$cursos = [];

$sqlCursos = "SELECT
                id_curso,
                nombre_curso,
                estado
              FROM cursos
              ORDER BY
                CAST(nombre_curso AS UNSIGNED) ASC,
                nombre_curso ASC";

$resultadoCursos = mysqli_query(
    $conexion,
    $sqlCursos
);

if ($resultadoCursos) {

    while (
        $fila = mysqli_fetch_assoc(
            $resultadoCursos
        )
    ) {

        $cursos[] = $fila;
    }
}

/* =========================================================
   CURSOS DEL DOCENTE SELECCIONADO
========================================================= */

$cursosDocente = [];

if ($docenteSeleccionado) {

    $sqlCursosDocente = "SELECT
                            dc.id_docente_curso,
                            dc.id_usuario,
                            dc.id_curso,
                            dc.fecha_asignacion,
                            c.nombre_curso,
                            c.estado
                         FROM docente_curso dc
                         INNER JOIN cursos c
                            ON c.id_curso = dc.id_curso
                         WHERE dc.id_usuario = ?
                         ORDER BY
                            CAST(
                                c.nombre_curso
                                AS UNSIGNED
                            ) ASC,
                            c.nombre_curso ASC";

    $stmtCursosDocente = mysqli_prepare(
        $conexion,
        $sqlCursosDocente
    );

    if ($stmtCursosDocente) {

        mysqli_stmt_bind_param(
            $stmtCursosDocente,
            "i",
            $idDocenteSeleccionado
        );

        mysqli_stmt_execute(
            $stmtCursosDocente
        );

        $resultadoCursosDocente =
            mysqli_stmt_get_result(
                $stmtCursosDocente
            );

        while (
            $fila = mysqli_fetch_assoc(
                $resultadoCursosDocente
            )
        ) {

            $cursosDocente[] = $fila;
        }

        mysqli_stmt_close(
            $stmtCursosDocente
        );
    }
}

/* =========================================================
   ESTADÍSTICAS
========================================================= */

$totalDocentes = count($docentes);

$totalActivos = 0;
$totalInactivos = 0;

foreach ($docentes as $docente) {

    if ($docente['estado'] === 'ACTIVO') {
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

<title>Asistencia QR | Docentes</title>

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
        0 25px 65px rgba(55,113,129,.10);
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
        0 12px 30px rgba(55,113,129,.10);
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

    background:rgba(242,143,150,.08);
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
        0 16px 42px rgba(55,113,129,.065);
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

/* =========================================================
   CONTENT
========================================================= */

.content-card{
    padding:28px;

    border:1px solid rgba(255,255,255,.94);

    border-radius:27px;

    background:rgba(255,255,255,.74);

    box-shadow:
        0 20px 48px rgba(55,113,129,.07);
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
        0 9px 20px rgba(24,216,206,.18);

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

    background:rgba(66,205,161,.10);

    border:1px solid rgba(66,205,161,.18);
}

.alert.error{
    color:#a35e68;

    background:rgba(242,143,150,.10);

    border:1px solid rgba(242,143,150,.18);
}

/* =========================================================
   ESTADISTICAS
========================================================= */

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

    padding:0 16px 0 45px;

    border:1px solid rgba(180,215,220,.48);

    border-radius:15px;

    outline:none;

    color:#416f7e;

    background:rgba(255,255,255,.70);

    font-family:inherit;

    font-size:13px;
    font-weight:700;
}

/* =========================================================
   GRID DOCENTES
========================================================= */

.teacher-grid{
    display:grid;

    grid-template-columns:
        repeat(
            auto-fill,
            minmax(275px,1fr)
        );

    gap:15px;
}

.teacher-card{
    position:relative;

    overflow:hidden;

    padding:18px;

    border:1px solid rgba(255,255,255,.92);

    border-radius:20px;

    background:rgba(255,255,255,.70);

    box-shadow:
        0 12px 30px rgba(55,113,129,.055);

    transition:.22s;
}

.teacher-card:hover{
    transform:translateY(-4px);
}

.teacher-top{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;

    gap:12px;
}

.teacher-avatar{
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

    font-size:16px;
    font-weight:950;
}

.teacher-status{
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
}

.teacher-status.active{
    color:#218b6c;
    background:rgba(66,205,161,.11);
}

.teacher-status.inactive{
    color:#aa7279;
    background:rgba(242,143,150,.11);
}

.teacher-status.active .status-dot{
    background:#42cda1;
}

.teacher-status.inactive .status-dot{
    background:#e99a9f;
}

.teacher-name{
    margin-top:15px;

    color:#315f70;

    font-size:20px;
    font-weight:950;
}

.teacher-user{
    display:flex;
    align-items:center;

    gap:8px;

    margin-top:7px;

    color:#7b99a2;

    font-size:11px;
    font-weight:700;
}

.teacher-user i{
    color:#0b9f9c;
    font-size:14px;
}

.teacher-courses{
    display:flex;
    align-items:center;

    gap:8px;

    margin-top:15px;

    padding:10px;

    border-radius:11px;

    color:#668995;

    background:rgba(24,216,206,.055);

    font-size:11px;
    font-weight:800;
}

.assigned-courses{
    display:flex;
    flex-direction:column;
    gap:3px;

    min-width:0;
}

.assigned-label{
    color:#7898a1;

    font-size:9px;
    font-weight:850;

    text-transform:uppercase;
    letter-spacing:.5px;
}

.assigned-course-names{
    color:#087d92;

    font-size:13px;
    font-weight:950;

    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

.no-assigned-courses{
    color:#8aa3aa;

    font-size:11px;
    font-weight:700;
}

.teacher-courses i{
    color:#0b9f9c;
    font-size:15px;
}

.teacher-actions{
    display:grid;

    grid-template-columns:1fr 1fr;

    gap:8px;

    margin-top:14px;
}

.teacher-action{
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

.action-courses{
    color:#087d92;
    background:rgba(24,216,206,.10);
}

.action-courses:hover{
    background:rgba(24,216,206,.17);
}

.action-edit{
    color:#7569c2;
    background:rgba(133,121,210,.10);
}

.action-edit:hover{
    background:rgba(133,121,210,.17);
}

.action-status-on{
    color:#b45e68;
    background:rgba(242,143,150,.10);
}

.action-status-off{
    color:#218b6c;
    background:rgba(66,205,161,.11);
}

/* =========================================================
   SELECCIONADO
========================================================= */

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

.selected-teacher{
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

    border:1px solid rgba(255,255,255,.90);
}

.selected-icon{
    width:56px;
    height:56px;

    display:flex;
    align-items:center;
    justify-content:center;

    border-radius:16px;

    color:#087d92;

    background:rgba(24,216,206,.11);

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

/* =========================================================
   TABLA
========================================================= */

.table-wrapper{
    overflow-x:auto;

    border-radius:17px;
}

/* =========================================================
   TABLA DE CURSOS DEL DOCENTE - CENTRADA
========================================================= */

.courses-table{
    width:100%;
    border-collapse:collapse;
    text-align:center;
}

.courses-table thead{
    background:rgba(24,216,206,.055);
}

.courses-table th{
    padding:13px 14px;

    text-align:center;

    vertical-align:middle;

    color:#668995;

    font-size:10px;

    font-weight:950;

    letter-spacing:.4px;
}

.courses-table td{
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

/* Curso */
.courses-table .course-link{
    display:inline-flex;

    align-items:center;
    justify-content:center;

    gap:8px;

    color:#416f7e;

    text-decoration:none;
}

/* Número/nombre del curso */
.courses-table .course-number-table{
    display:inline-flex;

    align-items:center;
    justify-content:center;

    gap:7px;

    font-weight:900;
}

/* Flecha del curso */
.courses-table .course-link-arrow{
    margin-left:3px;
}

/* Estado */
.courses-table .teacher-status{
    align-items:center;
    justify-content:center;

    gap:6px;
}

/* Acciones */
.courses-table .table-actions{
    display:flex;

    align-items:center;
    justify-content:center;

    gap:9px;

    flex-wrap:wrap;
}

/* Formulario de acciones */
.courses-table .table-actions form{
    display:flex;

    justify-content:center;

    margin:0 !important;
}

.course-number-table{
    display:inline-flex;

    align-items:center;
    justify-content:center;

    gap:7px;

    min-width:52px;
    min-height:34px;

    padding:0 10px;

    border-radius:10px;

    color:#087d92;

    background:rgba(24,216,206,.10);

    font-size:12px;
    font-weight:950;
}

.course-link{
    display:inline-flex;
    align-items:center;
    gap:9px;

    text-decoration:none;

    color:inherit;

    transition:.22s;
}

.course-link-arrow{
    color:#7bb4bd;

    font-size:15px;

    opacity:.65;

    transition:.22s;
}

.course-link:hover .course-number-table{
    color:#fff;

    background:
        linear-gradient(
            135deg,
            #18cfc6,
            #159fae
        );

    transform:translateX(3px);
}

.course-link:hover .course-link-arrow{
    color:#159fae;

    opacity:1;

    transform:translateX(3px);
}

.table-actions{
    display:flex;
    align-items:center;

    gap:9px;
}

.table-btn{
    min-height:42px;

    display:inline-flex;
    align-items:center;
    justify-content:center;

    gap:7px;

    padding:0 15px;

    border:none;

    border-radius:11px;

    font-family:inherit;

    font-size:11px;
    font-weight:900;

    cursor:pointer;

    text-decoration:none;
}

.btn-remove{
    color:#b45e68;
    background:rgba(242,143,150,.10);
}

.btn-remove:hover{
    background:rgba(242,143,150,.17);
}

/* =========================================================
   EMPTY
========================================================= */

.empty-state{
    padding:50px 20px;

    text-align:center;
}

.empty-state i{
    display:block;

    margin-bottom:10px;

    color:#8dc6c5;

    font-size:40px;
}

.empty-state strong{
    color:#527b87;

    font-size:15px;
}

.empty-state p{
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

    background:rgba(32,89,109,.18);

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

    border:1px solid rgba(255,255,255,.95);

    border-radius:24px;

    background:rgba(255,255,255,.96);

    box-shadow:
        0 30px 80px rgba(55,113,129,.18);
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

    background:rgba(120,170,180,.08);

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

    border:1px solid rgba(180,215,220,.50);

    border-radius:12px;

    color:#416f7e;

    background:rgba(248,253,252,.90);

    font-family:inherit;

    font-size:13px;
    font-weight:700;
}

.form-help{
    display:block;

    margin-top:5px;

    color:#8aa3aa;

    font-size:10px;
}

/* =========================================================
   SELECCION MULTIPLE DE CURSOS
========================================================= */

.course-select-header{
    display:flex;
    align-items:center;
    justify-content:space-between;

    gap:10px;

    margin-bottom:8px;
}

.course-counter{
    color:#159fae;

    font-size:10px;
    font-weight:900;
}

.course-select-actions{
    display:flex;
    gap:7px;

    margin-bottom:8px;
}

.course-select-action{
    min-height:32px;

    padding:0 10px;

    border:none;
    border-radius:9px;

    color:#087d92;

    background:rgba(24,216,206,.09);

    font-family:inherit;

    font-size:9px;
    font-weight:900;

    cursor:pointer;
}

.course-select-action:hover{
    background:rgba(24,216,206,.16);
}

.course-select-list{
    max-height:300px;

    overflow-y:auto;

    padding:5px;

    border-radius:14px;

    background:rgba(248,253,252,.80);

    border:1px solid rgba(180,215,220,.35);
}

.course-option{
    display:flex;
    align-items:center;

    gap:10px;

    padding:11px;

    border-radius:10px;

    cursor:pointer;

    transition:.2s;
}

.course-option:hover{
    background:rgba(24,216,206,.07);
}

.course-option.disabled{
    cursor:not-allowed;

    opacity:.52;
}

.course-option input{
    width:18px;
    height:18px;

    accent-color:#18cfc6;

    cursor:pointer;
}

.course-option input:disabled{
    cursor:not-allowed;
}

.course-option span{
    color:#527b87;

    font-size:12px;
    font-weight:850;
}

.course-option small{
    margin-left:auto;

    color:#8aa3aa;

    font-size:9px;
    font-weight:900;
}

/* =========================================================
   MODAL ACTIONS
========================================================= */

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

    background:rgba(120,170,180,.09);

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

    .teacher-grid{
        grid-template-columns:1fr;
    }

    .selected-teacher{
        align-items:flex-start;
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
    class="nav-link"
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
    class="nav-link active"
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

<!-- PERFIL -->

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
     MAIN
====================================================== -->

<main class="main">

<header class="topbar">

<div class="page-info">

<div class="page-indicator"></div>

<div class="page-title">

<h1>
    Docentes
</h1>

<p>
    Administración de docentes y asignación de cursos
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

<div class="alert <?= htmlspecialchars($tipoMensaje) ?>">

<i
class="bi
<?= $tipoMensaje === 'success'
    ? 'bi-check-circle-fill'
    : 'bi-exclamation-circle-fill'
?>"
></i>

<?= htmlspecialchars($mensaje) ?>

</div>

<?php endif; ?>


<?php if (!$docenteSeleccionado): ?>

<div class="content-heading">

<div class="heading-left">

<h2>
    Docentes registrados
</h2>

<p>
    Administra la información y los cursos asignados a cada docente.
</p>

</div>

<button
    type="button"
    class="btn-primary"
    onclick="abrirModalDocente()"
>

<i class="bi bi-person-plus-fill"></i>

Nuevo docente

</button>

</div>

<div class="mini-stats">

<div class="mini-stat">

<div class="mini-stat-icon">
    <i class="bi bi-people-fill"></i>
</div>

<div>

<span>
    Total docentes
</span>

<strong>
    <?= $totalDocentes ?>
</strong>

</div>

</div>

<div class="mini-stat">

<div class="mini-stat-icon">
    <i class="bi bi-check-circle-fill"></i>
</div>

<div>

<span>
    Docentes activos
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
    Docentes inactivos
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
    id="buscadorDocentes"
    placeholder="Buscar docente por nombre, apellido o usuario..."
    autocomplete="off"
>

</div>

<div
    class="teacher-grid"
    id="listaDocentes"
>

<?php foreach ($docentes as $docente): ?>

<?php

$nombreCompleto =
    trim(
        $docente['nombre']
        . ' '
        . $docente['apellido']
    );

$partesDocente =
    preg_split(
        '/\s+/',
        $nombreCompleto
    );

$inicialesDocente = '';

foreach (
    array_slice(
        $partesDocente,
        0,
        2
    ) as $parte
) {

    $inicialesDocente .=
        strtoupper(
            substr(
                $parte,
                0,
                1
            )
        );
}

?>

<div
    class="teacher-card"
    data-docente="<?= htmlspecialchars(
        strtolower(
            $nombreCompleto
            . ' '
            . $docente['usuario']
        )
    ) ?>"
>

<div class="teacher-top">

<div class="teacher-avatar">

<?= htmlspecialchars(
    $inicialesDocente
) ?>

</div>

<div
class="teacher-status
<?= $docente['estado'] === 'ACTIVO'
    ? 'active'
    : 'inactive'
?>"
>

<span class="status-dot"></span>

<?= htmlspecialchars(
    $docente['estado']
) ?>

</div>

</div>

<div class="teacher-name">

<?= htmlspecialchars(
    $nombreCompleto
) ?>

</div>

<div class="teacher-user">

<i class="bi bi-envelope"></i>

<?= htmlspecialchars(
    $docente['usuario']
) ?>

</div>

<div class="teacher-courses">

<i class="bi bi-mortarboard-fill"></i>

<div class="assigned-courses">

<span class="assigned-label">
    Cursos asignados
</span>

<?php if (!empty($docente['cursos_asignados'])): ?>

<strong class="assigned-course-names">

<?= htmlspecialchars(
    $docente['cursos_asignados']
) ?>

</strong>

<?php else: ?>

<span class="no-assigned-courses">
    Sin cursos asignados
</span>

<?php endif; ?>

</div>

</div>

<div class="teacher-actions">

<a
    href="docentes.php?docente=<?= (int)$docente['id_usuario'] ?>"
    class="teacher-action action-courses"
>

<i class="bi bi-mortarboard"></i>

Cursos

</a>

<button
    type="button"
    class="teacher-action action-edit"
    onclick='editarDocente(
<?= json_encode(
    $docente,
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

</div>

<div
style="
    display:grid;
    grid-template-columns:1fr;
    margin-top:8px;
"
>

<form
    method="POST"
    style="margin:0;"
>

<input
    type="hidden"
    name="id_usuario"
    value="<?= (int)$docente['id_usuario'] ?>"
>

<input
    type="hidden"
    name="nuevo_estado"
    value="<?= $docente['estado'] === 'ACTIVO'
        ? 'INACTIVO'
        : 'ACTIVO'
    ?>"
>

<button
    type="submit"
    name="cambiar_estado_docente"
    class="teacher-action
<?= $docente['estado'] === 'ACTIVO'
    ? 'action-status-on'
    : 'action-status-off'
?>"
    style="width:100%;"
>

<i
class="bi
<?= $docente['estado'] === 'ACTIVO'
    ? 'bi-pause-circle'
    : 'bi-play-circle'
?>"
></i>

<?= $docente['estado'] === 'ACTIVO'
    ? 'Desactivar'
    : 'Activar'
?>

</button>

</form>

</div>

</div>

<?php endforeach; ?>

</div>

<?php if (count($docentes) === 0): ?>

<div class="empty-state">

<i class="bi bi-person-workspace"></i>

<strong>
    No hay docentes registrados
</strong>

<p>
    Utiliza el botón "Nuevo docente" para registrar el primero.
</p>

</div>

<?php endif; ?>


<?php else: ?>

<!-- =====================================================
     DOCENTE SELECCIONADO
====================================================== -->

<a
    href="docentes.php"
    class="back-link"
>

<i class="bi bi-arrow-left"></i>

Volver a docentes

</a>

<?php

$nombreSeleccionado =
    trim(
        $docenteSeleccionado['nombre']
        . ' '
        . $docenteSeleccionado['apellido']
    );

?>

<div class="selected-teacher">

<div class="selected-icon">
    <i class="bi bi-person-workspace"></i>
</div>

<div class="selected-info">

<strong>

<?= htmlspecialchars(
    $nombreSeleccionado
) ?>

</strong>

<small>

<?= htmlspecialchars(
    $docenteSeleccionado['usuario']
) ?>

·

<?= count($cursosDocente) ?>

curso<?= count($cursosDocente) === 1
    ? ''
    : 's'
?>

</small>

</div>

<div
class="teacher-status
<?= $docenteSeleccionado['estado'] === 'ACTIVO'
    ? 'active'
    : 'inactive'
?>"
>

<span class="status-dot"></span>

<?= htmlspecialchars(
    $docenteSeleccionado['estado']
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
    onclick="abrirModalCursos()"
>

<i class="bi bi-plus-lg"></i>

Asignar cursos

</button>

</div>


<?php if (count($cursosDocente) > 0): ?>

<div class="table-wrapper">

<table class="courses-table">

<thead>

<tr>

<th>
    CURSO
</th>

<th>
    ESTADO
</th>

<th>
    ASIGNACIÓN
</th>

<th>
    ACCIONES
</th>

</tr>

</thead>

<tbody>

<?php foreach ($cursosDocente as $curso): ?>

<tr>

<td>

<a
    href="curso_estudiantes.php?curso=<?= (int)$curso['id_curso'] ?>"
    class="course-link"
>

<span class="course-number-table">

<i class="bi bi-mortarboard-fill"></i>

<?= htmlspecialchars(
    $curso['nombre_curso']
) ?>

</span>

<i class="bi bi-arrow-right-circle-fill course-link-arrow"></i>

</a>

</td>

<td>

<div
class="teacher-status
<?= $curso['estado'] === 'ACTIVO'
    ? 'active'
    : 'inactive'
?>"
style="display:inline-flex;"
>

<span class="status-dot"></span>

<?= htmlspecialchars(
    $curso['estado']
) ?>

</div>

</td>

<td>

<?= htmlspecialchars(
    date(
        'd/m/Y',
        strtotime(
            $curso['fecha_asignacion']
        )
    )
) ?>

</td>

<td>

<div class="table-actions">

<form
    method="POST"
    style="margin:0;"
    onsubmit="return confirm(
        '¿Seguro que deseas desasignar este curso del docente?'
    );"
>

<input
    type="hidden"
    name="id_docente_curso"
    value="<?= (int)$curso['id_docente_curso'] ?>"
>

<button
    type="submit"
    name="desasignar_curso"
    class="table-btn btn-remove"
>

<i class="bi bi-link-45deg"></i>

Desasignar

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

<div class="empty-state">

<i class="bi bi-mortarboard"></i>

<strong>
    Este docente no tiene cursos asignados
</strong>

<p>
    Utiliza el botón "Asignar cursos" para asignarle cursos.
</p>

</div>

<?php endif; ?>

<?php endif; ?>

</section>

</main>

</div>


<!-- =====================================================
     MODAL NUEVO DOCENTE
====================================================== -->

<div
    class="modal"
    id="modalDocente"
>

<div class="modal-card">

<div class="modal-header">

<h3>
    Nuevo docente
</h3>

<button
    type="button"
    class="modal-close"
    onclick="cerrarModalDocente()"
>

<i class="bi bi-x-lg"></i>

</button>

</div>

<form method="POST">

<div class="form-group">

<label
    class="form-label"
    for="nombreNuevo"
>

Nombre

</label>

<input
    type="text"
    class="form-input"
    id="nombreNuevo"
    name="nombre"
    placeholder="Nombre del docente"
    required
>

</div>

<div class="form-group">

<label
    class="form-label"
    for="apellidoNuevo"
>

Apellido

</label>

<input
    type="text"
    class="form-input"
    id="apellidoNuevo"
    name="apellido"
    placeholder="Apellido del docente"
    required
>

</div>

<div class="form-group">

<label
    class="form-label"
    for="usuarioNuevo"
>

Usuario / correo

</label>

<input
    type="email"
    class="form-input"
    id="usuarioNuevo"
    name="usuario"
    placeholder="docente@colegio.edu.co"
    required
>

</div>

<div class="form-group">

<label
    class="form-label"
    for="passwordNuevo"
>

Contraseña

</label>

<input
    type="password"
    class="form-input"
    id="passwordNuevo"
    name="password"
    placeholder="Contraseña"
    required
>

</div>

<div class="form-help">

El docente será creado automáticamente como
<strong>DOCENTE</strong> y quedará en estado
<strong>ACTIVO</strong>.

</div>

<div class="modal-actions">

<button
    type="button"
    class="btn-cancel"
    onclick="cerrarModalDocente()"
>

Cancelar

</button>

<button
    type="submit"
    name="agregar_docente"
    class="btn-save"
>

<i class="bi bi-person-plus"></i>

Crear docente

</button>

</div>

</form>

</div>

</div>


<!-- =====================================================
     MODAL EDITAR DOCENTE
====================================================== -->

<div
    class="modal"
    id="modalEditarDocente"
>

<div class="modal-card">

<div class="modal-header">

<h3>
    Editar docente
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
    id="editarId"
>

<div class="form-group">

<label
    class="form-label"
    for="editarNombre"
>

Nombre

</label>

<input
    type="text"
    class="form-input"
    id="editarNombre"
    name="nombre"
    required
>

</div>

<div class="form-group">

<label
    class="form-label"
    for="editarApellido"
>

Apellido

</label>

<input
    type="text"
    class="form-input"
    id="editarApellido"
    name="apellido"
    required
>

</div>

<div class="form-group">

<label
    class="form-label"
    for="editarUsuario"
>

Usuario / correo

</label>

<input
    type="email"
    class="form-input"
    id="editarUsuario"
    name="usuario"
    required
>

</div>

<div class="form-group">

<label
    class="form-label"
    for="editarPassword"
>

Nueva contraseña

</label>

<input
    type="password"
    class="form-input"
    id="editarPassword"
    name="password"
    placeholder="Dejar vacío para conservar la actual"
>

<span class="form-help">

Si no deseas cambiar la contraseña,
déjala vacía.

</span>

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
    name="editar_docente"
    class="btn-save"
>

<i class="bi bi-check-lg"></i>

Guardar cambios

</button>

</div>

</form>

</div>

</div>


<?php if ($docenteSeleccionado): ?>

<!-- =====================================================
     MODAL ASIGNAR VARIOS CURSOS
====================================================== -->

<div
    class="modal"
    id="modalCursos"
>

<div class="modal-card">

<div class="modal-header">

<h3>
    Asignar cursos
</h3>

<button
    type="button"
    class="modal-close"
    onclick="cerrarModalCursos()"
>

<i class="bi bi-x-lg"></i>

</button>

</div>

<form method="POST">

<input
    type="hidden"
    name="id_usuario"
    value="<?= (int)$docenteSeleccionado['id_usuario'] ?>"
>

<div class="form-group">

<div class="course-select-header">

<label class="form-label" style="margin:0;">
    Selecciona los cursos
</label>

<span
    class="course-counter"
    id="contadorCursos"
>
    0 seleccionados
</span>

</div>

<div class="course-select-actions">

<button
    type="button"
    class="course-select-action"
    onclick="seleccionarCursosDisponibles()"
>

<i class="bi bi-check2-all"></i>

Seleccionar disponibles

</button>

<button
    type="button"
    class="course-select-action"
    onclick="limpiarSeleccionCursos()"
>

<i class="bi bi-x-lg"></i>

Limpiar

</button>

</div>

<div class="course-select-list">

<?php

$idsCursosAsignados = [];

foreach ($cursosDocente as $cursoAsignado) {

    $idsCursosAsignados[] =
        (int)$cursoAsignado['id_curso'];
}

?>

<?php if (count($cursos) > 0): ?>

<?php foreach ($cursos as $curso): ?>

<?php

$yaAsignado =
    in_array(
        (int)$curso['id_curso'],
        $idsCursosAsignados,
        true
    );

?>

<label
    class="course-option <?= $yaAsignado ? 'disabled' : '' ?>"
>

<input
    type="checkbox"
    name="id_cursos[]"
    value="<?= (int)$curso['id_curso'] ?>"
    class="curso-checkbox"
    <?= $yaAsignado ? 'disabled' : '' ?>
>

<span>

<?= htmlspecialchars(
    $curso['nombre_curso']
) ?>

</span>

<small>

<?= $yaAsignado
    ? 'YA ASIGNADO'
    : htmlspecialchars(
        $curso['estado']
    )
?>

</small>

</label>

<?php endforeach; ?>

<?php else: ?>

<div class="empty-state">

<i class="bi bi-mortarboard"></i>

<strong>
    No hay cursos registrados
</strong>

<p>
    Primero crea un curso desde Cursos.
</p>

</div>

<?php endif; ?>

</div>

</div>

<div class="form-help">

Puedes seleccionar uno o varios cursos.
Los cursos que ya pertenecen a este docente
aparecen bloqueados para evitar duplicados.

</div>

<div class="modal-actions">

<button
    type="button"
    class="btn-cancel"
    onclick="cerrarModalCursos()"
>

Cancelar

</button>

<?php if (
    count($cursos) >
    count($idsCursosAsignados)
): ?>

<button
    type="submit"
    name="asignar_cursos"
    class="btn-save"
>

<i class="bi bi-link-45deg"></i>

Asignar cursos

</button>

<?php endif; ?>

</div>

</form>

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
   BUSCADOR DOCENTES
========================================================= */

const buscador =
    document.getElementById(
        'buscadorDocentes'
    );

const tarjetas =
    document.querySelectorAll(
        '.teacher-card'
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

                    const docente =
                        tarjeta.dataset.docente
                        || '';

                    if(
                        docente.includes(
                            texto
                        )
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
   MODAL NUEVO DOCENTE
========================================================= */

function abrirModalDocente(){

    const modal =
        document.getElementById(
            'modalDocente'
        );

    if(modal){

        modal.classList.add(
            'show'
        );

        const input =
            document.getElementById(
                'nombreNuevo'
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

function cerrarModalDocente(){

    const modal =
        document.getElementById(
            'modalDocente'
        );

    if(modal){

        modal.classList.remove(
            'show'
        );
    }
}


/* =========================================================
   EDITAR DOCENTE
========================================================= */

function editarDocente(docente){

    const modal =
        document.getElementById(
            'modalEditarDocente'
        );

    if(!modal){
        return;
    }

    document.getElementById(
        'editarId'
    ).value =
        docente.id_usuario;

    document.getElementById(
        'editarNombre'
    ).value =
        docente.nombre;

    document.getElementById(
        'editarApellido'
    ).value =
        docente.apellido;

    document.getElementById(
        'editarUsuario'
    ).value =
        docente.usuario;

    document.getElementById(
        'editarPassword'
    ).value = '';

    modal.classList.add(
        'show'
    );
}

function cerrarModalEditar(){

    const modal =
        document.getElementById(
            'modalEditarDocente'
        );

    if(modal){

        modal.classList.remove(
            'show'
        );
    }
}


/* =========================================================
   MODAL CURSOS
========================================================= */

function abrirModalCursos(){

    const modal =
        document.getElementById(
            'modalCursos'
        );

    if(modal){

        modal.classList.add(
            'show'
        );

        actualizarContadorCursos();
    }
}

function cerrarModalCursos(){

    const modal =
        document.getElementById(
            'modalCursos'
        );

    if(modal){

        modal.classList.remove(
            'show'
        );
    }
}


/* =========================================================
   CONTADOR DE CURSOS SELECCIONADOS
========================================================= */

function actualizarContadorCursos(){

    const checkboxes =
        document.querySelectorAll(
            '#modalCursos .curso-checkbox:not(:disabled)'
        );

    let seleccionados = 0;

    checkboxes.forEach(
        function(checkbox){

            if(checkbox.checked){
                seleccionados++;
            }

        }
    );

    const contador =
        document.getElementById(
            'contadorCursos'
        );

    if(contador){

        contador.textContent =
            seleccionados +
            (
                seleccionados === 1
                ? ' seleccionado'
                : ' seleccionados'
            );
    }
}


/* =========================================================
   EVENTOS CHECKBOX
========================================================= */

document.addEventListener(
    'change',
    function(event){

        if(
            event.target.classList.contains(
                'curso-checkbox'
            )
        ){

            actualizarContadorCursos();
        }

    }
);


/* =========================================================
   SELECCIONAR TODOS LOS DISPONIBLES
========================================================= */

function seleccionarCursosDisponibles(){

    const checkboxes =
        document.querySelectorAll(
            '#modalCursos .curso-checkbox:not(:disabled)'
        );

    checkboxes.forEach(
        function(checkbox){

            checkbox.checked = true;

        }
    );

    actualizarContadorCursos();
}


/* =========================================================
   LIMPIAR SELECCION
========================================================= */

function limpiarSeleccionCursos(){

    const checkboxes =
        document.querySelectorAll(
            '#modalCursos .curso-checkbox:not(:disabled)'
        );

    checkboxes.forEach(
        function(checkbox){

            checkbox.checked = false;

        }
    );

    actualizarContadorCursos();
}


/* =========================================================
   CERRAR AL HACER CLICK AFUERA
========================================================= */

document.addEventListener(
    'click',
    function(event){

        const modalDocente =
            document.getElementById(
                'modalDocente'
            );

        const modalEditar =
            document.getElementById(
                'modalEditarDocente'
            );

        const modalCursos =
            document.getElementById(
                'modalCursos'
            );

        if(
            modalDocente &&
            event.target === modalDocente
        ){

            cerrarModalDocente();

        }

        if(
            modalEditar &&
            event.target === modalEditar
        ){

            cerrarModalEditar();

        }

        if(
            modalCursos &&
            event.target === modalCursos
        ){

            cerrarModalCursos();

        }

    }
);


/* =========================================================
   ESC
========================================================= */

document.addEventListener(
    'keydown',
    function(event){

        if(event.key === 'Escape'){

            cerrarModalDocente();

            cerrarModalEditar();

            cerrarModalCursos();

        }

    }
);

</script>

</body>

</html>
