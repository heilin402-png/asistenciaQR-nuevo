<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once("../config/conexion.php");

date_default_timezone_set('America/Bogota');

header('Content-Type: application/json; charset=utf-8');


/* =====================================================
   VERIFICAR SESIÓN
   ===================================================== */

if (!isset($_SESSION["id_usuario"])) {

    echo json_encode([
        "success" => false,
        "message" => "La sesión del docente no está activa."
    ]);

    exit();

}


/* =====================================================
   VERIFICAR ROL DOCENTE
   ===================================================== */

if (!isset($_SESSION["id_rol"]) || $_SESSION["id_rol"] != 2) {

    echo json_encode([
        "success" => false,
        "message" => "No tienes permisos para registrar asistencia."
    ]);

    exit();

}


$idDocente = (int) $_SESSION["id_usuario"];


/* =====================================================
   VERIFICAR MÉTODO POST
   ===================================================== */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "success" => false,
        "message" => "Solicitud no válida."
    ]);

    exit();

}


/* =====================================================
   RECIBIR DATOS
   ===================================================== */

$documento = trim($_POST["documento"] ?? "");
$idSesion = (int) ($_POST["id_sesion"] ?? 0);


/* =====================================================
   VALIDAR DATOS
   ===================================================== */

if ($documento === "") {

    echo json_encode([
        "success" => false,
        "message" => "No se recibió el documento del estudiante."
    ]);

    exit();

}

if ($idSesion <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "No se seleccionó una sesión válida."
    ]);

    exit();

}


/* =====================================================
   BUSCAR SESIÓN
   ===================================================== */

$sqlSesion = "
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
    WHERE s.id_sesion = ?
    AND s.id_docente = ?
    LIMIT 1
";

$stmtSesion = $conexion->prepare($sqlSesion);

if (!$stmtSesion) {

    echo json_encode([
        "success" => false,
        "message" => "Error preparando la consulta de la sesión."
    ]);

    exit();

}

$stmtSesion->bind_param(
    "ii",
    $idSesion,
    $idDocente
);

$stmtSesion->execute();

$resultadoSesion = $stmtSesion->get_result();

$sesion = $resultadoSesion->fetch_assoc();

$stmtSesion->close();


/* =====================================================
   VERIFICAR SESIÓN
   ===================================================== */

if (!$sesion) {

    echo json_encode([
        "success" => false,
        "message" => "La sesión no existe o no pertenece al docente."
    ]);

    exit();

}


/* =====================================================
   VERIFICAR QUE LA SESIÓN ESTÉ ABIERTA
   ===================================================== */

if ($sesion["estado"] !== "ABIERTA") {

    echo json_encode([
        "success" => false,
        "message" => "La sesión está cerrada."
    ]);

    exit();

}


$idCurso = (int) $sesion["id_curso"];


/* =====================================================
   BUSCAR ESTUDIANTE
   ===================================================== */

$sqlEstudiante = "
    SELECT
        id_estudiante,
        documento,
        nombres,
        apellidos,
        id_curso,
        estado
    FROM estudiantes
    WHERE documento = ?
    AND estado = 'ACTIVO'
    LIMIT 1
";

$stmtEstudiante = $conexion->prepare($sqlEstudiante);

if (!$stmtEstudiante) {

    echo json_encode([
        "success" => false,
        "message" => "Error preparando la consulta del estudiante."
    ]);

    exit();

}

$stmtEstudiante->bind_param(
    "s",
    $documento
);

$stmtEstudiante->execute();

$resultadoEstudiante = $stmtEstudiante->get_result();

$estudiante = $resultadoEstudiante->fetch_assoc();

$stmtEstudiante->close();


/* =====================================================
   VERIFICAR ESTUDIANTE
   ===================================================== */

if (!$estudiante) {

    echo json_encode([
        "success" => false,
        "message" => "No se encontró un estudiante activo con ese documento."
    ]);

    exit();

}


/* =====================================================
   VERIFICAR QUE EL ESTUDIANTE PERTENEZCA
   AL CURSO DE LA SESIÓN
   ===================================================== */

if ((int)$estudiante["id_curso"] !== $idCurso) {

    echo json_encode([
        "success" => false,
        "message" => "El estudiante no pertenece al curso de esta sesión."
    ]);

    exit();

}


/* =====================================================
   VERIFICAR QUE EL DOCENTE TENGA ASIGNADO EL CURSO
   ===================================================== */

$sqlAsignacion = "
    SELECT id_docente_curso
    FROM docente_curso
    WHERE id_usuario = ?
    AND id_curso = ?
    LIMIT 1
";

$stmtAsignacion = $conexion->prepare($sqlAsignacion);

if (!$stmtAsignacion) {

    echo json_encode([
        "success" => false,
        "message" => "Error verificando la asignación del docente."
    ]);

    exit();

}

$stmtAsignacion->bind_param(
    "ii",
    $idDocente,
    $idCurso
);

$stmtAsignacion->execute();

$resultadoAsignacion = $stmtAsignacion->get_result();

$asignacion = $resultadoAsignacion->fetch_assoc();

$stmtAsignacion->close();


if (!$asignacion) {

    echo json_encode([
        "success" => false,
        "message" => "El docente no tiene asignado este curso."
    ]);

    exit();

}


/* =====================================================
   VERIFICAR SI YA TIENE ASISTENCIA
   ===================================================== */

$sqlExiste = "
    SELECT
        id_asistencia,
        estado,
        hora_registro
    FROM asistencia_clase
    WHERE id_sesion = ?
    AND id_estudiante = ?
    LIMIT 1
";

$stmtExiste = $conexion->prepare($sqlExiste);

if (!$stmtExiste) {

    echo json_encode([
        "success" => false,
        "message" => "Error verificando la asistencia."
    ]);

    exit();

}

$idEstudiante = (int) $estudiante["id_estudiante"];

$stmtExiste->bind_param(
    "ii",
    $idSesion,
    $idEstudiante
);

$stmtExiste->execute();

$resultadoExiste = $stmtExiste->get_result();

$asistenciaExiste = $resultadoExiste->fetch_assoc();

$stmtExiste->close();


/* =====================================================
   SI YA ESTÁ REGISTRADO
   ===================================================== */

if ($asistenciaExiste) {

    echo json_encode([
        "success" => true,
        "duplicado" => true,
        "message" => "El estudiante ya tiene asistencia registrada.",
        "estudiante" =>
            $estudiante["nombres"] . " " . $estudiante["apellidos"],
        "documento" => $estudiante["documento"],
        "curso" => $sesion["nombre_curso"],
        "estado" => $asistenciaExiste["estado"],
        "hora" => $asistenciaExiste["hora_registro"]
    ]);

    exit();

}


/* =====================================================
   REGISTRAR ASISTENCIA
   ===================================================== */

$estado = "PRESENTE";

$sqlInsertar = "
    INSERT INTO asistencia_clase
    (
        id_sesion,
        id_estudiante,
        estado
    )
    VALUES (?, ?, ?)
";

$stmtInsertar = $conexion->prepare($sqlInsertar);

if (!$stmtInsertar) {

    echo json_encode([
        "success" => false,
        "message" => "Error preparando el registro de asistencia."
    ]);

    exit();

}

$stmtInsertar->bind_param(
    "iis",
    $idSesion,
    $idEstudiante,
    $estado
);

if (!$stmtInsertar->execute()) {

    echo json_encode([
        "success" => false,
        "message" => "No fue posible registrar la asistencia."
    ]);

    $stmtInsertar->close();

    exit();

}

$stmtInsertar->close();


/* =====================================================
   RESPUESTA EXITOSA
   ===================================================== */

echo json_encode([
    "success" => true,
    "duplicado" => false,
    "message" => "Asistencia registrada correctamente.",
    "estudiante" =>
        $estudiante["nombres"] . " " . $estudiante["apellidos"],
    "documento" => $estudiante["documento"],
    "curso" => $sesion["nombre_curso"],
    "estado" => "PRESENTE",
    "hora" => date("Y-m-d H:i:s")
]);

exit();

?>