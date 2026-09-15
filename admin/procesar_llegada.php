
<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

date_default_timezone_set('America/Bogota');

require_once "../config/conexion.php";

header("Content-Type: application/json; charset=UTF-8");


/* =========================================================
   VERIFICAR SESIÓN
========================================================= */

if (!isset($_SESSION['id_usuario'])) {

    echo json_encode([
        "ok" => false,
        "mensaje" => "La sesión ha expirado."
    ]);

    exit();
}


/* =========================================================
   VERIFICAR ADMINISTRADOR
========================================================= */

if (
    !isset($_SESSION['id_rol']) ||
    (int)$_SESSION['id_rol'] !== 1
) {

    echo json_encode([
        "ok" => false,
        "mensaje" => "No tienes permisos para realizar esta acción."
    ]);

    exit();
}


/* =========================================================
   RECIBIR DOCUMENTO
========================================================= */

$documento = trim(
    $_POST['documento'] ?? ''
);

if ($documento === '') {

    echo json_encode([
        "ok" => false,
        "mensaje" => "No se recibió el documento del estudiante."
    ]);

    exit();
}


/* =========================================================
   BUSCAR ESTUDIANTE ACTIVO
========================================================= */

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

$stmtEstudiante = mysqli_prepare(
    $conexion,
    $sqlEstudiante
);

if (!$stmtEstudiante) {

    echo json_encode([
        "ok" => false,
        "mensaje" => "No fue posible consultar el estudiante."
    ]);

    exit();
}

mysqli_stmt_bind_param(
    $stmtEstudiante,
    "s",
    $documento
);

mysqli_stmt_execute(
    $stmtEstudiante
);

$resultadoEstudiante =
    mysqli_stmt_get_result(
        $stmtEstudiante
    );

$estudiante =
    mysqli_fetch_assoc(
        $resultadoEstudiante
    );

mysqli_stmt_close(
    $stmtEstudiante
);


/* =========================================================
   VERIFICAR QUE EXISTA
========================================================= */

if (!$estudiante) {

    echo json_encode([
        "ok" => false,
        "mensaje" => "No se encontró un estudiante activo con ese documento."
    ]);

    exit();
}


/* =========================================================
   DATOS DEL ESTUDIANTE
========================================================= */

$idEstudiante =
    (int)$estudiante['id_estudiante'];

$idCurso =
    (int)$estudiante['id_curso'];

$nombreEstudiante =
    trim(
        $estudiante['nombres'] . ' ' .
        $estudiante['apellidos']
    );

$documentoEstudiante =
    $estudiante['documento'];


/* =========================================================
   OBTENER CURSO
========================================================= */

$nombreCurso = 'Sin curso';

$estadoCurso = 'INACTIVO';

$sqlCurso = "
    SELECT
        id_curso,
        nombre_curso,
        estado
    FROM cursos
    WHERE id_curso = ?
    LIMIT 1
";

$stmtCurso = mysqli_prepare(
    $conexion,
    $sqlCurso
);

if ($stmtCurso) {

    mysqli_stmt_bind_param(
        $stmtCurso,
        "i",
        $idCurso
    );

    mysqli_stmt_execute(
        $stmtCurso
    );

    $resultadoCurso =
        mysqli_stmt_get_result(
            $stmtCurso
        );

    $curso =
        mysqli_fetch_assoc(
            $resultadoCurso
        );

    if ($curso) {

        $nombreCurso =
            $curso['nombre_curso'];

        $estadoCurso =
            $curso['estado'];
    }

    mysqli_stmt_close(
        $stmtCurso
    );
}


/* =========================================================
   VERIFICAR QUE EL CURSO ESTÉ ACTIVO
========================================================= */

if ($estadoCurso !== 'ACTIVO') {

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "El curso del estudiante está inactivo.",
        "estudiante" =>
            $nombreEstudiante,
        "documento" =>
            $documentoEstudiante,
        "curso" =>
            $nombreCurso
    ]);

    exit();
}


/* =========================================================
   OBTENER DIRECTOR DEL CURSO
========================================================= */

$docenteNombre = 'Sin director asignado';

$sqlDirector = "
    SELECT
        u.id_usuario,
        u.nombre,
        u.apellido
    FROM director_curso dc

    INNER JOIN usuarios u
        ON u.id_usuario = dc.id_usuario

    WHERE dc.id_curso = ?
      AND u.id_rol = 2
      AND u.estado = 'ACTIVO'

    LIMIT 1
";

$stmtDirector = mysqli_prepare(
    $conexion,
    $sqlDirector
);

if ($stmtDirector) {

    mysqli_stmt_bind_param(
        $stmtDirector,
        "i",
        $idCurso
    );

    mysqli_stmt_execute(
        $stmtDirector
    );

    $resultadoDirector =
        mysqli_stmt_get_result(
            $stmtDirector
        );

    $director =
        mysqli_fetch_assoc(
            $resultadoDirector
        );

    if ($director) {

        $docenteNombre =
            trim(
                $director['nombre'] . ' ' .
                $director['apellido']
            );
    }

    mysqli_stmt_close(
        $stmtDirector
    );
}


/* =========================================================
   FECHA Y HORA
========================================================= */

$fecha =
    date('Y-m-d');

$hora =
    date('H:i:s');


/* =========================================================
   VERIFICAR SI YA REGISTRÓ LLEGADA HOY
========================================================= */

$sqlDuplicado = "
    SELECT
        id_llegada,
        hora_llegada
    FROM asistencia_llegada
    WHERE id_estudiante = ?
      AND fecha = ?
    LIMIT 1
";

$stmtDuplicado = mysqli_prepare(
    $conexion,
    $sqlDuplicado
);

if (!$stmtDuplicado) {

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "No fue posible verificar la asistencia."
    ]);

    exit();
}

mysqli_stmt_bind_param(
    $stmtDuplicado,
    "is",
    $idEstudiante,
    $fecha
);

mysqli_stmt_execute(
    $stmtDuplicado
);

$resultadoDuplicado =
    mysqli_stmt_get_result(
        $stmtDuplicado
    );

$registroExistente =
    mysqli_fetch_assoc(
        $resultadoDuplicado
    );

mysqli_stmt_close(
    $stmtDuplicado
);


/* =========================================================
   SI YA ESTÁ REGISTRADO
========================================================= */

if ($registroExistente) {

    echo json_encode([

        "ok" => true,

        "duplicado" => true,

        "mensaje" =>
            "Este estudiante ya tiene registrada su llegada tarde hoy.",

        "estudiante" =>
            $nombreEstudiante,

        "documento" =>
            $documentoEstudiante,

        "curso" =>
            $nombreCurso,

        "docente" =>
            $docenteNombre,

        "fecha" =>
            $fecha,

        "hora" =>
            $registroExistente['hora_llegada'],

        "estado" =>
            "LLEGADA TARDE"
    ]);

    exit();
}


/* =========================================================
   REGISTRAR LLEGADA TARDE
========================================================= */

$sqlInsertar = "
    INSERT INTO asistencia_llegada
    (
        id_estudiante,
        fecha,
        hora_llegada,
        estado
    )
    VALUES (?, ?, ?, 'LLEGADA TARDE')
";

$stmtInsertar = mysqli_prepare(
    $conexion,
    $sqlInsertar
);

if (!$stmtInsertar) {

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "No fue posible preparar el registro de llegada."
    ]);

    exit();
}

mysqli_stmt_bind_param(
    $stmtInsertar,
    "iss",
    $idEstudiante,
    $fecha,
    $hora
);

$guardado =
    mysqli_stmt_execute(
        $stmtInsertar
    );

mysqli_stmt_close(
    $stmtInsertar
);


/* =========================================================
   VERIFICAR REGISTRO
========================================================= */

if (!$guardado) {

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "No fue posible registrar la llegada tarde."
    ]);

    exit();
}


/* =========================================================
   RESPUESTA EXITOSA
========================================================= */

echo json_encode([

    "ok" => true,

    "duplicado" => false,

    "mensaje" =>
        "Llegada tarde registrada correctamente.",

    "estudiante" =>
        $nombreEstudiante,

    "documento" =>
        $documentoEstudiante,

    "curso" =>
        $nombreCurso,

    "docente" =>
        $docenteNombre,

    "fecha" =>
        $fecha,

    "hora" =>
        $hora,

    "estado" =>
        "LLEGADA TARDE"

]);

exit();