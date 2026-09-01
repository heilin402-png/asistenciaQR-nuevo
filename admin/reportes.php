```php
<?php

session_start();

/* =========================================================
   PROTECCIÓN DE SESIÓN
========================================================= */

if (!isset($_SESSION['id_usuario'])) {

    header("Location: ../auth/login.php");
    exit();

}


/* =========================================================
   PROTECCIÓN DE ROL
   ADMINISTRADOR = 1
========================================================= */

if (
    !isset($_SESSION['id_rol']) ||
    (int)$_SESSION['id_rol'] !== 1
) {

    header("Location: ../auth/login.php");
    exit();

}


/* =========================================================
   CONEXIÓN
========================================================= */

require_once "../config/conexion.php";

date_default_timezone_set('America/Bogota');


/* =========================================================
   DATOS DEL USUARIO
========================================================= */

$nombreUsuario =
    $_SESSION['nombre']
    ?? 'Administrador Sistema';


$partesNombre =
    preg_split(
        '/\s+/',
        trim($nombreUsuario)
    );


$primerNombre =
    $partesNombre[0]
    ?? 'Administrador';


$iniciales = '';


foreach (
    array_slice(
        $partesNombre,
        0,
        2
    )
    as $parte
) {

    $iniciales .=
        strtoupper(
            substr(
                $parte,
                0,
                1
            )
        );

}


if ($iniciales === '') {

    $iniciales = 'AS';

}


/* =========================================================
   FECHA Y HORA
========================================================= */

$horaActual =
    date('H:i:s');


$fechaActual =
    date('d/m/Y');


$fechaHoy =
    date('Y-m-d');


/* =========================================================
   FILTROS
========================================================= */

$fechaInicio =
    $_GET['fecha_inicio']
    ?? date(
        'Y-m-d',
        strtotime('-6 days')
    );


$fechaFin =
    $_GET['fecha_fin']
    ?? $fechaHoy;


$idCursoFiltro =
    (int)(
        $_GET['curso']
        ?? 0
    );


$idDocenteFiltro =
    (int)(
        $_GET['docente']
        ?? 0
    );


/* =========================================================
   VALIDAR FECHAS
========================================================= */

if (
    !preg_match(
        '/^\d{4}-\d{2}-\d{2}$/',
        $fechaInicio
    )
) {

    $fechaInicio =
        date(
            'Y-m-d',
            strtotime('-6 days')
        );

}


if (
    !preg_match(
        '/^\d{4}-\d{2}-\d{2}$/',
        $fechaFin
    )
) {

    $fechaFin =
        $fechaHoy;

}


if ($fechaInicio > $fechaFin) {

    $temporal =
        $fechaInicio;

    $fechaInicio =
        $fechaFin;

    $fechaFin =
        $temporal;

}


/* =========================================================
   LISTA DE CURSOS
========================================================= */

$cursos = [];


$sqlCursos = "

    SELECT
        id_curso,
        nombre_curso

    FROM cursos

    WHERE estado = 'ACTIVO'

    ORDER BY
        nombre_curso ASC

";


$resultadoCursos =
    mysqli_query(
        $conexion,
        $sqlCursos
    );


if ($resultadoCursos) {

    while (
        $fila =
            mysqli_fetch_assoc(
                $resultadoCursos
            )
    ) {

        $cursos[] =
            $fila;

    }

}


/* =========================================================
   LISTA DE DOCENTES
========================================================= */

$docentes = [];


$sqlDocentes = "

    SELECT
        id_usuario,
        nombre,
        apellido

    FROM usuarios

    WHERE id_rol = 2

    AND estado = 'ACTIVO'

    ORDER BY
        nombre ASC,
        apellido ASC

";


$resultadoDocentes =
    mysqli_query(
        $conexion,
        $sqlDocentes
    );


if ($resultadoDocentes) {

    while (
        $fila =
            mysqli_fetch_assoc(
                $resultadoDocentes
            )
    ) {

        $docentes[] =
            $fila;

    }

}


/* =========================================================
   CONSTRUIR FILTROS SQL
========================================================= */

$condiciones = [];

$parametros = [];

$tipos = '';


$condiciones[] =
    "s.fecha BETWEEN ? AND ?";


$parametros[] =
    $fechaInicio;

$parametros[] =
    $fechaFin;

$tipos .= 'ss';


if ($idCursoFiltro > 0) {

    $condiciones[] =
        "s.id_curso = ?";

    $parametros[] =
        $idCursoFiltro;

    $tipos .= 'i';

}


if ($idDocenteFiltro > 0) {

    $condiciones[] =
        "s.id_docente = ?";

    $parametros[] =
        $idDocenteFiltro;

    $tipos .= 'i';

}


$whereSQL =
    implode(
        ' AND ',
        $condiciones
    );


/* =========================================================
   REPORTE POR SESIÓN
========================================================= */

$sesiones = [];


$sqlReporte = "

    SELECT

        s.id_sesion,

        s.fecha,

        s.hora_inicio,

        s.estado AS estado_sesion,

        s.id_curso,

        c.nombre_curso,

        s.id_docente,

        CONCAT(
            COALESCE(u.nombre, ''),
            ' ',
            COALESCE(u.apellido, '')
        ) AS docente,

        (

            SELECT COUNT(*)

            FROM estudiantes e1

            WHERE e1.id_curso = s.id_curso

            AND e1.estado = 'ACTIVO'

        ) AS estudiantes_curso,


        COUNT(
            DISTINCT a.id_estudiante
        ) AS registrados,


        COUNT(
            DISTINCT CASE

                WHEN UPPER(
                    TRIM(
                        COALESCE(
                            a.estado,
                            ''
                        )
                    )
                ) IN (
                    'PRESENTE',
                    'ASISTIO',
                    'ASISTENCIA'
                )

                THEN a.id_estudiante

            END
        ) AS presentes,


        COUNT(
            DISTINCT CASE

                WHEN UPPER(
                    TRIM(
                        COALESCE(
                            a.estado,
                            ''
                        )
                    )
                ) IN (
                    'AUSENTE',
                    'FALTA'
                )

                THEN a.id_estudiante

            END
        ) AS ausentes_registrados,


        COUNT(
            DISTINCT CASE

                WHEN
                    a.estado_excusa IS NOT NULL

                    AND TRIM(
                        a.estado_excusa
                    ) <> ''

                THEN a.id_estudiante

            END
        ) AS excusas


    FROM sesiones_clase s


    INNER JOIN cursos c

        ON c.id_curso =
           s.id_curso


    LEFT JOIN usuarios u

        ON u.id_usuario =
           s.id_docente


    LEFT JOIN asistencia_clase a

        ON a.id_sesion =
           s.id_sesion


    WHERE
        $whereSQL


    GROUP BY

        s.id_sesion,
        s.fecha,
        s.hora_inicio,
        s.estado,
        s.id_curso,
        c.nombre_curso,
        s.id_docente,
        u.nombre,
        u.apellido


    ORDER BY

        s.fecha DESC,
        s.hora_inicio DESC

";


$stmtReporte =
    mysqli_prepare(
        $conexion,
        $sqlReporte
    );


if ($stmtReporte) {

    mysqli_stmt_bind_param(
        $stmtReporte,
        $tipos,
        ...$parametros
    );


    mysqli_stmt_execute(
        $stmtReporte
    );


    $resultadoReporte =
        mysqli_stmt_get_result(
            $stmtReporte
        );


    while (
        $fila =
            mysqli_fetch_assoc(
                $resultadoReporte
            )
    ) {

        $fila['estudiantes_curso'] =
            (int)(
                $fila[
                    'estudiantes_curso'
                ]
                ?? 0
            );


        $fila['registrados'] =
            (int)(
                $fila[
                    'registrados'
                ]
                ?? 0
            );


        $fila['presentes'] =
            (int)(
                $fila[
                    'presentes'
                ]
                ?? 0
            );


        $fila['ausentes_registrados'] =
            (int)(
                $fila[
                    'ausentes_registrados'
                ]
                ?? 0
            );


        $fila['excusas'] =
            (int)(
                $fila[
                    'excusas'
                ]
                ?? 0
            );


        /*
           Ausentes calculados:
           estudiantes activos del curso
           menos estudiantes registrados.
        */

        $ausentesCalculados =
            $fila[
                'estudiantes_curso'
            ]
            -
            $fila[
                'registrados'
            ];


        if ($ausentesCalculados < 0) {

            $ausentesCalculados = 0;

        }


        $fila[
            'ausentes_calculados'
        ] =
            $ausentesCalculados;


        /*
           Porcentaje de asistencia
        */

        if (
            $fila[
                'estudiantes_curso'
            ] > 0
        ) {

            $fila['porcentaje'] =
                round(
                    (
                        $fila['presentes']
                        /
                        $fila[
                            'estudiantes_curso'
                        ]
                    ) * 100,
                    1
                );

        } else {

            $fila['porcentaje'] = 0;

        }


        $sesiones[] =
            $fila;

    }


    mysqli_stmt_close(
        $stmtReporte
    );

}


/* =========================================================
   TOTALES GENERALES
========================================================= */

$totalSesiones =
    count($sesiones);


$totalEstudiantesRegistrados = 0;

$totalPresentes = 0;

$totalAusentes = 0;

$totalExcusas = 0;

$totalEstudiantesEsperados = 0;


foreach (
    $sesiones
    as $sesion
) {

    $totalEstudiantesRegistrados +=
        $sesion['registrados'];


    $totalPresentes +=
        $sesion['presentes'];


    $totalAusentes +=
        $sesion['ausentes_calculados'];


    $totalExcusas +=
        $sesion['excusas'];


    $totalEstudiantesEsperados +=
        $sesion['estudiantes_curso'];

}


if (
    $totalEstudiantesEsperados > 0
) {

    $porcentajeGeneral =
        round(
            (
                $totalPresentes
                /
                $totalEstudiantesEsperados
            ) * 100,
            1
        );

} else {

    $porcentajeGeneral = 0;

}


/* =========================================================
   GRÁFICA POR DÍA
========================================================= */

$grafica = [];


$dias = [

    'Mon' => 'Lun',
    'Tue' => 'Mar',
    'Wed' => 'Mié',
    'Thu' => 'Jue',
    'Fri' => 'Vie',
    'Sat' => 'Sáb',
    'Sun' => 'Dom'

];


$fechaGraficaInicio =
    $fechaInicio;


$fechaGraficaFin =
    $fechaFin;


/*
   Limitar la gráfica a los últimos
   7 días para mantenerla limpia.
*/

$inicioTimestamp =
    strtotime(
        $fechaGraficaFin
    );


$fechaGraficaInicio =
    date(
        'Y-m-d',
        strtotime(
            '-6 days',
            $inicioTimestamp
        )
    );


if (
    $fechaGraficaInicio < $fechaInicio
) {

    $fechaGraficaInicio =
        $fechaInicio;

}


$cursor =
    strtotime(
        $fechaGraficaInicio
    );


$finGrafica =
    strtotime(
        $fechaGraficaFin
    );


while (
    $cursor <= $finGrafica
) {

    $fecha =
        date(
            'Y-m-d',
            $cursor
        );


    $dia =
        date(
            'D',
            $cursor
        );


    $grafica[$fecha] = [

        'fecha' =>
            $fecha,

        'dia' =>
            $dias[$dia]
            ?? $dia,

        'sesiones' => 0,

        'presentes' => 0,

        'esperados' => 0,

        'porcentaje' => 0

    ];


    $cursor =
        strtotime(
            '+1 day',
            $cursor
        );

}


/* =========================================================
   LLENAR GRÁFICA
========================================================= */

foreach (
    $sesiones
    as $sesion
) {

    $fecha =
        $sesion['fecha'];


    if (
        !isset(
            $grafica[$fecha]
        )
    ) {

        continue;

    }


    $grafica[$fecha]['sesiones']++;

    $grafica[$fecha]['presentes'] +=
        $sesion['presentes'];

    $grafica[$fecha]['esperados'] +=
        $sesion['estudiantes_curso'];

}


/* =========================================================
   PORCENTAJES DE GRÁFICA
========================================================= */

foreach (
    $grafica
    as &$diaGrafica
) {

    if (
        $diaGrafica['esperados'] > 0
    ) {

        $diaGrafica['porcentaje'] =
            round(
                (
                    $diaGrafica['presentes']
                    /
                    $diaGrafica['esperados']
                ) * 100,
                1
            );

    }

}


unset($diaGrafica);


/* =========================================================
   MÁXIMO DE GRÁFICA
========================================================= */

$maxGrafica = 1;


foreach (
    $grafica
    as $diaGrafica
) {

    if (
        $diaGrafica['presentes']
        >
        $maxGrafica
    ) {

        $maxGrafica =
            $diaGrafica['presentes'];

    }

}


/* =========================================================
   FORMATEAR FECHAS
========================================================= */

function fechaBonita($fecha)
{

    $timestamp =
        strtotime($fecha);


    if (!$timestamp) {

        return $fecha;

    }


    return date(
        'd/m/Y',
        $timestamp
    );

}


/* =========================================================
   ESTADO SESIÓN
========================================================= */

function claseEstadoSesion($estado)
{

    $estado =
        strtoupper(
            trim(
                $estado
            )
        );


    if ($estado === 'CERRADA') {

        return 'status-closed';

    }


    return 'status-active';

}


function textoEstadoSesion($estado)
{

    $estado =
        strtoupper(
            trim(
                $estado
            )
        );


    if ($estado === 'CERRADA') {

        return 'Cerrada';

    }


    return 'Activa';

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

<title>
    Asistencia QR | Reportes
</title>


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

    --yellow:#d6a451;

    --text:#3e6f7d;

    --dark:#20596d;

    --muted:#7897a0;

}


/* =========================================================
   RESET
========================================================= */

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


/* =========================================================
   SIDEBAR HEADER
========================================================= */

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


/* =========================================================
   NAVEGACIÓN
========================================================= */

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


.nav-icon.people{

    color:#488da1;

    background:
        rgba(105,184,213,.10);

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


.nav-icon.restaurant{

    color:#d99a24;

    background:
        rgba(245,190,70,.14);

}


.nav-icon.audit{

    color:#7569c2;

    background:
        rgba(133,121,210,.11);

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


/* =========================================================
   RELOJ
========================================================= */

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
   FILTROS
========================================================= */

.filter-card{

    padding:
        23px 25px;

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


.filter-heading{

    display:flex;

    align-items:center;

    gap:12px;

    margin-bottom:18px;

}


.filter-heading-icon{

    width:43px;
    height:43px;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:13px;

    color:#087d92;

    background:
        rgba(24,216,206,.10);

    font-size:20px;

}


.filter-heading h3{

    color:#416f7e;

    font-size:18px;

    font-weight:950;

}


.filter-heading p{

    margin-top:3px;

    color:#819ca4;

    font-size:11px;

    font-weight:650;

}


.filters{

    display:grid;

    grid-template-columns:
        repeat(4,minmax(0,1fr));

    gap:13px;

}


.filter-group{

    display:flex;

    flex-direction:column;

    gap:7px;

}


.filter-group label{

    color:#6d8d97;

    font-size:11px;

    font-weight:900;

}


.filter-group input,
.filter-group select{

    width:100%;

    height:45px;

    padding:
        0 12px;

    border:
        1px solid
        rgba(125,174,183,.18);

    outline:none;

    border-radius:12px;

    color:#416f7e;

    background:
        rgba(255,255,255,.72);

    font-family:inherit;

    font-size:12px;

    font-weight:750;

    transition:.2s;

}


.filter-group input:focus,
.filter-group select:focus{

    border-color:
        rgba(24,216,206,.45);

    box-shadow:
        0 0 0 4px
        rgba(24,216,206,.07);

}


.filter-actions{

    display:flex;

    align-items:flex-end;

    gap:8px;

}


.btn{

    height:45px;

    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:7px;

    padding:
        0 17px;

    border:none;

    border-radius:12px;

    text-decoration:none;

    cursor:pointer;

    font-family:inherit;

    font-size:12px;

    font-weight:900;

    transition:.2s;

}


.btn-primary{

    color:#fff;

    background:
        linear-gradient(
            135deg,
            #18cfc6,
            #168fa6
        );

    box-shadow:
        0 9px 20px
        rgba(24,216,206,.16);

}


.btn-primary:hover{

    transform:
        translateY(-2px);

}


.btn-light{

    color:#668892;

    background:
        rgba(232,247,247,.72);

    border:
        1px solid
        rgba(255,255,255,.95);

}


.btn-light:hover{

    background:#fff;

}


/* =========================================================
   RESUMEN
========================================================= */

.summary-grid{

    display:grid;

    grid-template-columns:
        repeat(5,minmax(0,1fr));

    gap:13px;

}


.summary-item{

    position:relative;

    overflow:hidden;

    min-height:125px;

    display:flex;

    align-items:center;

    gap:14px;

    padding:
        20px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:21px;

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


.summary-item::after{

    content:"";

    position:absolute;

    right:-35px;
    bottom:-40px;

    width:90px;
    height:90px;

    border-radius:50%;

    background:
        rgba(24,216,206,.05);

}


.summary-icon{

    width:56px;
    height:56px;

    display:flex;

    align-items:center;
    justify-content:center;

    flex-shrink:0;

    border-radius:16px;

    color:#0a9995;

    background:
        rgba(24,216,206,.10);

    font-size:24px;

}


.summary-item:nth-child(2)
.summary-icon{

    color:#5578ca;

    background:
        rgba(105,184,213,.12);

}


.summary-item:nth-child(3)
.summary-icon{

    color:#7569c2;

    background:
        rgba(133,121,210,.10);

}


.summary-item:nth-child(4)
.summary-icon{

    color:#d17864;

    background:
        rgba(233,154,120,.12);

}


.summary-item:nth-child(5)
.summary-icon{

    color:#3aa47d;

    background:
        rgba(66,205,161,.10);

}


.summary-text span{

    display:block;

    color:#819da5;

    font-size:10px;

    font-weight:850;

}


.summary-text strong{

    display:block;

    margin-top:5px;

    color:#315f70;

    font-size:26px;

    font-weight:950;

    line-height:1;

}


/* =========================================================
   ANALYTICS
========================================================= */

.analytics-layout{

    display:grid;

    grid-template-columns:
        minmax(0,1.65fr)
        minmax(280px,.75fr);

    gap:16px;

}


.chart-card,
.info-card{

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:25px;

    background:
        rgba(255,255,255,.74);

    box-shadow:
        0 18px 42px
        rgba(55,113,129,.065);

}


.chart-card{

    padding:
        24px 25px 21px;

}


.section-heading{

    display:flex;

    align-items:flex-start;

    justify-content:space-between;

    gap:15px;

    margin-bottom:20px;

}


.section-heading h3{

    color:#416f7e;

    font-size:18px;

    font-weight:950;

}


.section-heading p{

    margin-top:5px;

    color:#819ca4;

    font-size:12px;

    font-weight:650;

}


.week-label{

    display:flex;

    align-items:center;

    gap:7px;

    padding:
        7px 10px;

    border-radius:10px;

    color:#17867f;

    background:
        rgba(24,216,206,.08);

    font-size:10px;

    font-weight:900;

}


.chart-area{

    height:225px;

    display:flex;

    align-items:flex-end;

    gap:10px;

    padding:
        8px 4px 0;

}


.chart-column{

    flex:1;

    height:100%;

    display:flex;

    flex-direction:column;

    align-items:center;

    justify-content:flex-end;

    gap:7px;

}


.chart-value{

    min-height:17px;

    color:#668995;

    font-size:10px;

    font-weight:850;

}


.chart-bar-wrapper{

    width:100%;

    height:170px;

    display:flex;

    align-items:flex-end;

    justify-content:center;

}


.chart-bar{

    width:
        min(48px,72%);

    min-height:6px;

    border-radius:
        12px 12px 6px 6px;

    background:
        linear-gradient(
            180deg,
            #18d8ce,
            #69b8d5
        );

    box-shadow:
        0 8px 18px
        rgba(24,216,206,.13);

    transition:
        height .5s ease;

}


.chart-day{

    color:#7897a0;

    font-size:10px;

    font-weight:850;

}


/* =========================================================
   INFO
========================================================= */

.info-card{

    padding:
        24px;

    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,.80),
            rgba(236,250,248,.68)
        );

}


.info-card h3{

    color:#416f7e;

    font-size:18px;

    font-weight:950;

}


.info-card > p{

    margin-top:5px;

    color:#819ca4;

    font-size:12px;

    font-weight:650;

}


.info-list{

    display:flex;

    flex-direction:column;

    gap:10px;

    margin-top:19px;

}


.info-row{

    display:flex;

    align-items:center;

    gap:11px;

    padding:
        12px;

    border-radius:14px;

    background:
        rgba(255,255,255,.68);

    border:
        1px solid
        rgba(255,255,255,.82);

}


.info-row-icon{

    width:37px;
    height:37px;

    display:flex;

    align-items:center;
    justify-content:center;

    flex-shrink:0;

    border-radius:11px;

    color:#087d92;

    background:
        rgba(24,216,206,.09);

}


.info-row-text{

    flex:1;

}


.info-row-text span{

    display:block;

    color:#8aa2a9;

    font-size:10px;

    font-weight:750;

}


.info-row-text strong{

    display:block;

    margin-top:2px;

    color:#456f7c;

    font-size:13px;

    font-weight:950;

}


/* =========================================================
   TABLA
========================================================= */

.table-card{

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


.table-heading{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:15px;

    padding:
        23px 25px;

    border-bottom:
        1px solid
        rgba(80,130,140,.08);

}


.table-heading h3{

    color:#416f7e;

    font-size:18px;

    font-weight:950;

}


.table-heading p{

    margin-top:4px;

    color:#819ca4;

    font-size:11px;

    font-weight:650;

}


.table-count{

    display:inline-flex;

    align-items:center;

    gap:6px;

    padding:
        8px 11px;

    border-radius:10px;

    color:#087d82;

    background:
        rgba(24,216,206,.08);

    font-size:10px;

    font-weight:900;

}


.table-wrapper{

    width:100%;

    overflow-x:auto;

}


.report-table{

    width:100%;

    border-collapse:collapse;

    min-width:900px;

}


.report-table th{

    padding:
        13px 15px;

    text-align:left;

    color:#7898a2;

    background:
        rgba(235,248,248,.48);

    font-size:10px;

    font-weight:950;

    letter-spacing:.4px;

    white-space:nowrap;

}


.report-table td{

    padding:
        14px 15px;

    border-top:
        1px solid
        rgba(80,130,140,.065);

    color:#557d88;

    font-size:11px;

    font-weight:750;

    white-space:nowrap;

}


.report-table tbody tr{

    transition:.18s;

}


.report-table tbody tr:hover{

    background:
        rgba(24,216,206,.035);

}


.session-date{

    color:#416f7e;

    font-weight:950;

}


.course-name{

    color:#416f7e;

    font-weight:900;

}


.teacher-name{

    color:#7898a2;

}


.number{

    text-align:center;

    font-weight:950;

}


.present{

    color:#2caa7d !important;

}


.absent{

    color:#c77a80 !important;

}


.excused{

    color:#b28b43 !important;

}


.percentage{

    display:inline-flex;

    align-items:center;

    justify-content:center;

    min-width:58px;

    padding:
        6px 8px;

    border-radius:9px;

    color:#16877f;

    background:
        rgba(24,216,206,.08);

    font-size:10px;

    font-weight:950;

}


.status{

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:
        5px 8px;

    border-radius:8px;

    font-size:9px;

    font-weight:900;

}


.status-active{

    color:#2d9d78;

    background:
        rgba(66,205,161,.10);

}


.status-closed{

    color:#8a7a9b;

    background:
        rgba(133,121,210,.09);

}


.empty-state{

    padding:
        55px 20px;

    text-align:center;

}


.empty-state i{

    display:block;

    margin-bottom:10px;

    color:#8dc6c5;

    font-size:42px;

}


.empty-state strong{

    display:block;

    color:#5f8490;

    font-size:15px;

    font-weight:900;

}


.empty-state p{

    margin-top:5px;

    color:#8aa3aa;

    font-size:11px;

    font-weight:650;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1250px){

    .summary-grid{

        grid-template-columns:
            repeat(3,1fr);

    }

    .filters{

        grid-template-columns:
            repeat(2,1fr);

    }

}


@media(max-width:1050px){

    .analytics-layout{

        grid-template-columns:1fr;

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

}


@media(max-width:700px){

    .summary-grid{

        grid-template-columns:1fr;

    }

    .filters{

        grid-template-columns:1fr;

    }

    .topbar{

        align-items:flex-start;

        flex-direction:column;

    }

    .clock-box{

        width:100%;

    }

}


@media(max-width:650px){

    .navigation{

        grid-template-columns:1fr;

    }

    .menu-label{

        grid-column:auto;

    }

    .page-title h1{

        font-size:23px;

    }

    .chart-area{

        gap:5px;

    }

    .chart-bar{

        width:75%;

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
                class="nav-link"
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
                class="nav-link active"
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


    <!-- PERFIL -->

    <div class="sidebar-bottom">


        <div class="profile-card">


            <div class="profile-avatar">

                <?= htmlspecialchars(
                    $iniciales
                ) ?>

            </div>


            <div class="profile-info">

                <strong>

                    <?= htmlspecialchars(
                        $nombreUsuario
                    ) ?>

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


<!-- =====================================================
     TOPBAR
====================================================== -->

<header class="topbar">


    <div class="page-info">


        <div class="page-indicator"></div>


        <div class="page-title">

            <h1>
                Reportes
            </h1>

            <p>
                Consulta y analiza la asistencia académica
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


<!-- =====================================================
     FILTROS
====================================================== -->

<section class="filter-card">


    <div class="filter-heading">


        <div class="filter-heading-icon">

            <i class="bi bi-funnel"></i>

        </div>


        <div>

            <h3>
                Filtros del reporte
            </h3>

            <p>
                Selecciona el período y los datos que deseas consultar
            </p>

        </div>


    </div>


    <form
        method="GET"
        action="reportes.php"
    >


        <div class="filters">


            <!-- FECHA INICIAL -->

            <div class="filter-group">

                <label>
                    Fecha inicial
                </label>

                <input
                    type="date"
                    name="fecha_inicio"
                    value="<?= htmlspecialchars(
                        $fechaInicio
                    ) ?>"
                >

            </div>


            <!-- FECHA FINAL -->

            <div class="filter-group">

                <label>
                    Fecha final
                </label>

                <input
                    type="date"
                    name="fecha_fin"
                    value="<?= htmlspecialchars(
                        $fechaFin
                    ) ?>"
                >

            </div>


            <!-- CURSO -->

            <div class="filter-group">

                <label>
                    Curso
                </label>

                <select
                    name="curso"
                >

                    <option value="0">

                        Todos los cursos

                    </option>


                    <?php foreach (
                        $cursos
                        as $curso
                    ): ?>


                        <option
                            value="<?= (int)$curso['id_curso'] ?>"

                            <?= $idCursoFiltro ===
                                (int)$curso['id_curso']
                                ? 'selected'
                                : '' ?>
                        >

                            <?= htmlspecialchars(
                                $curso['nombre_curso']
                            ) ?>

                        </option>


                    <?php endforeach; ?>


                </select>

            </div>


            <!-- DOCENTE -->

            <div class="filter-group">

                <label>
                    Docente
                </label>

                <select
                    name="docente"
                >

                    <option value="0">

                        Todos los docentes

                    </option>


                    <?php foreach (
                        $docentes
                        as $docente
                    ): ?>


                        <option
                            value="<?= (int)$docente['id_usuario'] ?>"

                            <?= $idDocenteFiltro ===
                                (int)$docente['id_usuario']
                                ? 'selected'
                                : '' ?>
                        >

                            <?= htmlspecialchars(
                                trim(
                                    $docente['nombre']
                                    . ' '
                                    .
                                    $docente['apellido']
                                )
                            ) ?>

                        </option>


                    <?php endforeach; ?>


                </select>

            </div>


            <!-- BOTONES -->

            <div class="filter-actions">


                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    <i class="bi bi-search"></i>

                    Consultar

                </button>


                <a
                    href="reportes.php"
                    class="btn btn-light"
                >

                    <i class="bi bi-arrow-counterclockwise"></i>

                    Limpiar

                </a>


            </div>


        </div>


    </form>


</section>


<!-- =====================================================
     RESUMEN
====================================================== -->

<section class="summary-grid">


    <!-- SESIONES -->

    <div class="summary-item">


        <div class="summary-icon">

            <i class="bi bi-calendar2-week"></i>

        </div>


        <div class="summary-text">

            <span>
                Sesiones
            </span>

            <strong>
                <?= $totalSesiones ?>
            </strong>

        </div>


    </div>


    <!-- REGISTROS -->

    <div class="summary-item">


        <div class="summary-icon">

            <i class="bi bi-person-check"></i>

        </div>


        <div class="summary-text">

            <span>
                Registros
            </span>

            <strong>
                <?= $totalEstudiantesRegistrados ?>
            </strong>

        </div>


    </div>


    <!-- PRESENTES -->

    <div class="summary-item">


        <div class="summary-icon">

            <i class="bi bi-check-circle"></i>

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


    <!-- AUSENTES -->

    <div class="summary-item">


        <div class="summary-icon">

            <i class="bi bi-person-x"></i>

        </div>


        <div class="summary-text">

            <span>
                Ausentes
            </span>

            <strong>
                <?= $totalAusentes ?>
            </strong>

        </div>


    </div>


    <!-- PORCENTAJE -->

    <div class="summary-item">


        <div class="summary-icon">

            <i class="bi bi-percent"></i>

        </div>


        <div class="summary-text">

            <span>
                Asistencia
            </span>

            <strong>
                <?= $porcentajeGeneral ?>%
            </strong>

        </div>


    </div>


</section>


<!-- =====================================================
     ANALYTICS
====================================================== -->

<section class="analytics-layout">


    <!-- GRÁFICA -->

    <div class="chart-card">


        <div class="section-heading">


            <div>

                <h3>
                    Comportamiento de asistencia
                </h3>

                <p>
                    Estudiantes presentes durante el período seleccionado
                </p>

            </div>


            <div class="week-label">

                <i class="bi bi-activity"></i>

                ÚLTIMOS DÍAS

            </div>


        </div>


        <div class="chart-area">


            <?php if (
                count($grafica) > 0
            ): ?>


                <?php foreach (
                    $grafica
                    as $diaGrafica
                ): ?>


                    <?php

                    $altura = 0;

                    if (
                        $maxGrafica > 0 &&
                        $diaGrafica['presentes'] > 0
                    ) {

                        $altura =
                            (
                                $diaGrafica['presentes']
                                /
                                $maxGrafica
                            ) * 100;

                    }

                    if (
                        $diaGrafica['presentes'] > 0 &&
                        $altura < 8
                    ) {

                        $altura = 8;

                    }

                    ?>


                    <div class="chart-column">


                        <div class="chart-value">

                            <?= $diaGrafica['presentes'] ?>

                        </div>


                        <div class="chart-bar-wrapper">


                            <div
                                class="chart-bar"
                                style="
                                    height: <?= $altura ?>%;
                                "
                                title="
                                    <?= htmlspecialchars(
                                        $diaGrafica['fecha']
                                    ) ?>:
                                    <?= $diaGrafica['presentes'] ?>
                                    presentes
                                "
                            ></div>


                        </div>


                        <div class="chart-day">

                            <?= htmlspecialchars(
                                $diaGrafica['dia']
                            ) ?>

                        </div>


                    </div>


                <?php endforeach; ?>


            <?php else: ?>


                <div class="empty-state">

                    <i class="bi bi-bar-chart"></i>

                    <strong>
                        No hay datos
                    </strong>

                </div>


            <?php endif; ?>


        </div>


    </div>


    <!-- INFORMACIÓN -->

    <div class="info-card">


        <h3>
            Resumen del período
        </h3>


        <p>
            Información general del reporte actual
        </p>


        <div class="info-list">


            <div class="info-row">


                <div class="info-row-icon">

                    <i class="bi bi-calendar-event"></i>

                </div>


                <div class="info-row-text">

                    <span>
                        Período
                    </span>

                    <strong>

                        <?= fechaBonita(
                            $fechaInicio
                        ) ?>

                        -

                        <?= fechaBonita(
                            $fechaFin
                        ) ?>

                    </strong>

                </div>


            </div>


            <div class="info-row">


                <div class="info-row-icon">

                    <i class="bi bi-person-check-fill"></i>

                </div>


                <div class="info-row-text">

                    <span>
                        Presentes
                    </span>

                    <strong>
                        <?= $totalPresentes ?>
                    </strong>

                </div>


            </div>


            <div class="info-row">


                <div class="info-row-icon">

                    <i class="bi bi-person-x-fill"></i>

                </div>


                <div class="info-row-text">

                    <span>
                        Ausencias calculadas
                    </span>

                    <strong>
                        <?= $totalAusentes ?>
                    </strong>

                </div>


            </div>


            <div class="info-row">


                <div class="info-row-icon">

                    <i class="bi bi-file-earmark-check"></i>

                </div>


                <div class="info-row-text">

                    <span>
                        Excusas
                    </span>

                    <strong>
                        <?= $totalExcusas ?>
                    </strong>

                </div>


            </div>


            <div class="info-row">


                <div class="info-row-icon">

                    <i class="bi bi-graph-up-arrow"></i>

                </div>


                <div class="info-row-text">

                    <span>
                        Porcentaje general
                    </span>

                    <strong>
                        <?= $porcentajeGeneral ?>%
                    </strong>

                </div>


            </div>


        </div>


    </div>


</section>


<!-- =====================================================
     DETALLE
====================================================== -->

<section class="table-card">


    <div class="table-heading">


        <div>

            <h3>
                Detalle de sesiones
            </h3>

            <p>
                Registro consolidado de asistencia por clase
            </p>

        </div>


        <div class="table-count">

            <i class="bi bi-list-check"></i>

            <?= $totalSesiones ?>

            sesiones

        </div>


    </div>


    <?php if (
        count($sesiones) > 0
    ): ?>


        <div class="table-wrapper">


            <table class="report-table">


                <thead>

                    <tr>

                        <th>
                            FECHA
                        </th>

                        <th>
                            HORA
                        </th>

                        <th>
                            CURSO
                        </th>

                        <th>
                            DOCENTE
                        </th>

                        <th>
                            ESTUDIANTES
                        </th>

                        <th>
                            PRESENTES
                        </th>

                        <th>
                            AUSENTES
                        </th>

                        <th>
                            EXCUSAS
                        </th>

                        <th>
                            ASISTENCIA
                        </th>

                        <th>
                            ESTADO
                        </th>

                    </tr>

                </thead>


                <tbody>


                    <?php foreach (
                        $sesiones
                        as $sesion
                    ): ?>


                        <tr>


                            <td class="session-date">

                                <?= fechaBonita(
                                    $sesion['fecha']
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    substr(
                                        $sesion[
                                            'hora_inicio'
                                        ],
                                        0,
                                        5
                                    )
                                ) ?>

                            </td>


                            <td class="course-name">

                                <?= htmlspecialchars(
                                    $sesion[
                                        'nombre_curso'
                                    ]
                                ) ?>

                            </td>


                            <td class="teacher-name">

                                <?= htmlspecialchars(
                                    trim(
                                        $sesion[
                                            'docente'
                                        ]
                                    )
                                    ?:
                                    'Sin docente'
                                ) ?>

                            </td>


                            <td class="number">

                                <?= $sesion[
                                    'estudiantes_curso'
                                ] ?>

                            </td>


                            <td class="number present">

                                <?= $sesion[
                                    'presentes'
                                ] ?>

                            </td>


                            <td class="number absent">

                                <?= $sesion[
                                    'ausentes_calculados'
                                ] ?>

                            </td>


                            <td class="number excused">

                                <?= $sesion[
                                    'excusas'
                                ] ?>

                            </td>


                            <td>

                                <span class="percentage">

                                    <?= $sesion[
                                        'porcentaje'
                                    ] ?>%

                                </span>

                            </td>


                            <td>

                                <span
                                    class="status
                                    <?= claseEstadoSesion(
                                        $sesion[
                                            'estado_sesion'
                                        ]
                                    ) ?>"
                                >

                                    <i
                                        class="bi
                                        <?= strtoupper(
                                            trim(
                                                $sesion[
                                                    'estado_sesion'
                                                ]
                                            )
                                        ) === 'CERRADA'
                                            ? 'bi-lock-fill'
                                            : 'bi-circle-fill'
                                        ?>"
                                    ></i>

                                    <?= textoEstadoSesion(
                                        $sesion[
                                            'estado_sesion'
                                        ]
                                    ) ?>

                                </span>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                </tbody>


            </table>


        </div>


    <?php else: ?>


        <div class="empty-state">


            <i class="bi bi-file-earmark-bar-graph"></i>


            <strong>
                No hay información para mostrar
            </strong>


            <p>
                No se encontraron sesiones con los filtros seleccionados.
            </p>


        </div>


    <?php endif; ?>


</section>


</main>


</div>


<script>

/* =========================================================
   RELOJ
========================================================= */

function actualizarReloj()
{

    const ahora =
        new Date();


    const horas =
        String(
            ahora.getHours()
        ).padStart(
            2,
            '0'
        );


    const minutos =
        String(
            ahora.getMinutes()
        ).padStart(
            2,
            '0'
        );


    const segundos =
        String(
            ahora.getSeconds()
        ).padStart(
            2,
            '0'
        );


    const reloj =
        document.getElementById(
            'reloj'
        );


    if (reloj) {

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


/* =========================================================
   VALIDACIÓN DE FECHAS
========================================================= */

const formulario =
    document.querySelector(
        'form'
    );


if (formulario) {

    formulario.addEventListener(
        'submit',
        function(event)
        {

            const inicio =
                document.querySelector(
                    '[name="fecha_inicio"]'
                );


            const fin =
                document.querySelector(
                    '[name="fecha_fin"]'
                );


            if (
                inicio &&
                fin &&
                inicio.value &&
                fin.value &&
                inicio.value > fin.value
            ) {

                event.preventDefault();


                alert(
                    'La fecha inicial no puede ser posterior a la fecha final.'
                );

            }

        }
    );

}

</script>


</body>

</html>
