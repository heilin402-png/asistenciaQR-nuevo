<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

date_default_timezone_set("America/Bogota");

header("Content-Type: application/json; charset=UTF-8");


/* =========================================================
   VERIFICAR SESIÓN
========================================================= */

if (!isset($_SESSION["id_usuario"])) {

    echo json_encode([
        "ok" => false,
        "tipo" => "sesion",
        "mensaje" => "La sesión ha expirado. Inicia sesión nuevamente."
    ]);

    exit();
}


/* =========================================================
   VERIFICAR ROL RESTAURANTE
   Rol 3 = Restaurante
========================================================= */

if (!isset($_SESSION["id_rol"]) || (int)$_SESSION["id_rol"] !== 3) {

    echo json_encode([
        "ok" => false,
        "tipo" => "permiso",
        "mensaje" => "No tienes permisos para realizar esta acción."
    ]);

    exit();
}


/* =========================================================
   CONEXIÓN
========================================================= */

require_once("../config/conexion.php");


/* =========================================================
   RECIBIR DOCUMENTO DEL QR
========================================================= */

$documento = trim($_POST["documento"] ?? "");


if ($documento === "") {

    echo json_encode([
        "ok" => false,
        "tipo" => "qr",
        "mensaje" => "El código QR está vacío."
    ]);

    exit();
}


/* =========================================================
   BUSCAR ESTUDIANTE
========================================================= */

$sqlEstudiante = "
    SELECT
        id_estudiante,
        documento,
        nombres,
        apellidos
    FROM estudiantes
    WHERE documento = ?
      AND estado = 'ACTIVO'
    LIMIT 1
";

$stmtEstudiante = $conexion->prepare($sqlEstudiante);

if (!$stmtEstudiante) {

    echo json_encode([
        "ok" => false,
        "tipo" => "error",
        "mensaje" => "No fue posible preparar la consulta del estudiante."
    ]);

    exit();
}

$stmtEstudiante->bind_param("s", $documento);

$stmtEstudiante->execute();

$resultadoEstudiante = $stmtEstudiante->get_result();

$estudiante = $resultadoEstudiante->fetch_assoc();

$stmtEstudiante->close();


/* =========================================================
   ESTUDIANTE NO ENCONTRADO
========================================================= */

if (!$estudiante) {

    echo json_encode([
        "ok" => false,
        "tipo" => "no_encontrado",
        "mensaje" => "No se encontró un estudiante activo con ese documento.",
        "documento" => $documento
    ]);

    exit();
}


/* =========================================================
   DATOS DEL ESTUDIANTE
========================================================= */

$idEstudiante = (int)$estudiante["id_estudiante"];

$nombreCompleto = trim(
    $estudiante["nombres"] . " " . $estudiante["apellidos"]
);

$fechaHoy = date("Y-m-d");

$horaHoy = date("H:i:s");


/* =========================================================
   VERIFICAR SI YA TIENE ASISTENCIA HOY
========================================================= */

$sqlExiste = "
    SELECT
        id_asistencia_restaurante,
        hora,
        estado
    FROM asistencia_restaurante
    WHERE id_estudiante = ?
      AND fecha = ?
    LIMIT 1
";

$stmtExiste = $conexion->prepare($sqlExiste);

if (!$stmtExiste) {

    echo json_encode([
        "ok" => false,
        "tipo" => "error",
        "mensaje" => "No fue posible verificar la asistencia del estudiante."
    ]);

    exit();
}

$stmtExiste->bind_param(
    "is",
    $idEstudiante,
    $fechaHoy
);

$stmtExiste->execute();

$resultadoExiste = $stmtExiste->get_result();

$asistenciaExistente = $resultadoExiste->fetch_assoc();

$stmtExiste->close();


/* =========================================================
   YA REGISTRADO HOY
========================================================= */

if ($asistenciaExistente) {

    echo json_encode([
        "ok" => false,
        "tipo" => "duplicado",
        "mensaje" => "Este estudiante ya tiene asistencia registrada hoy.",
        "estudiante" => [
            "id" => $idEstudiante,
            "documento" => $estudiante["documento"],
            "nombre" => $nombreCompleto
        ],
        "asistencia" => [
            "hora" => $asistenciaExistente["hora"],
            "estado" => $asistenciaExistente["estado"]
        ]
    ]);

    exit();
}


/* =========================================================
   REGISTRAR ASISTENCIA
========================================================= */

$sqlInsertar = "
    INSERT INTO asistencia_restaurante
    (
        id_estudiante,
        fecha,
        hora,
        estado
    )
    VALUES
    (
        ?,
        ?,
        ?,
        'REGISTRADO'
    )
";

$stmtInsertar = $conexion->prepare($sqlInsertar);

if (!$stmtInsertar) {

    echo json_encode([
        "ok" => false,
        "tipo" => "error",
        "mensaje" => "No fue posible preparar el registro de asistencia."
    ]);

    exit();
}

$stmtInsertar->bind_param(
    "iss",
    $idEstudiante,
    $fechaHoy,
    $horaHoy
);

$registroCorrecto = $stmtInsertar->execute();

$idAsistencia = $stmtInsertar->insert_id;

$stmtInsertar->close();


/* =========================================================
   ERROR AL INSERTAR
========================================================= */

if (!$registroCorrecto) {

    echo json_encode([
        "ok" => false,
        "tipo" => "error",
        "mensaje" => "No fue posible registrar la asistencia."
    ]);

    exit();
}


/* =========================================================
   CONTAR ASISTENCIAS DEL DÍA
========================================================= */

$sqlTotal = "
    SELECT COUNT(*) AS total
    FROM asistencia_restaurante
    WHERE fecha = ?
";

$stmtTotal = $conexion->prepare($sqlTotal);

$totalHoy = 0;

if ($stmtTotal) {

    $stmtTotal->bind_param("s", $fechaHoy);

    $stmtTotal->execute();

    $resultadoTotal = $stmtTotal->get_result();

    $filaTotal = $resultadoTotal->fetch_assoc();

    $totalHoy = (int)($filaTotal["total"] ?? 0);

    $stmtTotal->close();
}


/* =========================================================
   RESPUESTA EXITOSA
========================================================= */

echo json_encode([
    "ok" => true,
    "tipo" => "registrado",
    "mensaje" => "Asistencia registrada correctamente.",
    "estudiante" => [
        "id" => $idEstudiante,
        "documento" => $estudiante["documento"],
        "nombre" => $nombreCompleto
    ],
    "asistencia" => [
        "id" => $idAsistencia,
        "fecha" => $fechaHoy,
        "hora" => $horaHoy,
        "estado" => "REGISTRADO"
    ],
    "total_hoy" => $totalHoy
]);

exit();
?>