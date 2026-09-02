<?php

session_start();

header(
    'Content-Type: application/json; charset=utf-8'
);


/* =========================================================
   FUNCIÓN PARA RESPONDER JSON
========================================================= */

function responder(
    bool $success,
    string $mensaje,
    array $extra = []
): void {

    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'mensaje' => $mensaje
            ],
            $extra
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit();
}


/* =========================================================
   VERIFICAR SESIÓN DEL DOCENTE
========================================================= */

if (!isset($_SESSION['id_usuario'])) {

    responder(
        false,
        'La sesión del docente ha expirado.'
    );
}


require_once "../config/conexion.php";

date_default_timezone_set(
    'America/Bogota'
);


$idDocente =
    (int)$_SESSION['id_usuario'];


/* =========================================================
   RECIBIR DATOS
========================================================= */

$documento =
    trim(
        $_POST['documento'] ?? ''
    );

$idSesion =
    (int)(
        $_POST['id_sesion'] ?? 0
    );


/* =========================================================
   VALIDAR DATOS
========================================================= */

if ($documento === '') {

    responder(
        false,
        'No se recibió el documento del estudiante.'
    );
}


if ($idSesion <= 0) {

    responder(
        false,
        'No se indicó la sesión de clase.'
    );
}


/* =========================================================
   VERIFICAR SESIÓN
========================================================= */

$sqlSesion = "

    SELECT
        s.id_sesion,
        s.id_curso,
        s.estado,
        c.nombre_curso

    FROM sesiones_clase s

    INNER JOIN cursos c
        ON c.id_curso = s.id_curso

    WHERE s.id_sesion = ?
    AND s.id_docente = ?

    LIMIT 1

";


$stmtSesion =
    mysqli_prepare(
        $conexion,
        $sqlSesion
    );


if (!$stmtSesion) {

    responder(
        false,
        'No fue posible consultar la sesión.'
    );
}


mysqli_stmt_bind_param(
    $stmtSesion,
    "ii",
    $idSesion,
    $idDocente
);


if (!mysqli_stmt_execute($stmtSesion)) {

    mysqli_stmt_close(
        $stmtSesion
    );

    responder(
        false,
        'No fue posible verificar la sesión.'
    );
}


$resultadoSesion =
    mysqli_stmt_get_result(
        $stmtSesion
    );


$sesion =
    mysqli_fetch_assoc(
        $resultadoSesion
    );


mysqli_stmt_close(
    $stmtSesion
);


if (!$sesion) {

    responder(
        false,
        'La sesión no existe o no pertenece al docente.'
    );
}


/* =========================================================
   VERIFICAR QUE ESTÉ ABIERTA
========================================================= */

if (
    strtoupper(
        trim(
            $sesion['estado']
        )
    ) !== 'ABIERTA'
) {

    responder(
        false,
        'La sesión de asistencia está cerrada.'
    );
}


/* =========================================================
   BUSCAR ESTUDIANTE POR DOCUMENTO
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

    LIMIT 1

";


$stmtEstudiante =
    mysqli_prepare(
        $conexion,
        $sqlEstudiante
    );


if (!$stmtEstudiante) {

    responder(
        false,
        'No fue posible consultar el estudiante.'
    );
}


mysqli_stmt_bind_param(
    $stmtEstudiante,
    "s",
    $documento
);


if (!mysqli_stmt_execute($stmtEstudiante)) {

    mysqli_stmt_close(
        $stmtEstudiante
    );

    responder(
        false,
        'No fue posible buscar el estudiante.'
    );
}


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
   ESTUDIANTE NO ENCONTRADO
========================================================= */

if (!$estudiante) {

    responder(
        false,
        'No se encontró un estudiante con ese documento.'
    );
}


/* =========================================================
   VERIFICAR ESTADO DEL ESTUDIANTE
========================================================= */

$estadoEstudiante =
    strtoupper(
        trim(
            (string)$estudiante['estado']
        )
    );


if ($estadoEstudiante !== 'ACTIVO') {

    responder(
        false,
        'El estudiante no se encuentra activo.'
    );
}


/* =========================================================
   VERIFICAR CURSO
========================================================= */

if (
    (int)$estudiante['id_curso']
    !==
    (int)$sesion['id_curso']
) {

    responder(
        false,
        'El estudiante no pertenece al curso de esta sesión.'
    );
}


/* =========================================================
   VERIFICAR SI YA TIENE ASISTENCIA
========================================================= */

$idEstudiante =
    (int)$estudiante['id_estudiante'];


$sqlExiste = "

    SELECT
        id_asistencia,
        hora_registro

    FROM asistencia_clase

    WHERE id_sesion = ?
    AND id_estudiante = ?

    LIMIT 1

";


$stmtExiste =
    mysqli_prepare(
        $conexion,
        $sqlExiste
    );


if (!$stmtExiste) {

    responder(
        false,
        'No fue posible verificar la asistencia anterior.'
    );
}


mysqli_stmt_bind_param(
    $stmtExiste,
    "ii",
    $idSesion,
    $idEstudiante
);


if (!mysqli_stmt_execute($stmtExiste)) {

    mysqli_stmt_close(
        $stmtExiste
    );

    responder(
        false,
        'No fue posible verificar la asistencia.'
    );
}


$resultadoExiste =
    mysqli_stmt_get_result(
        $stmtExiste
    );


$registroExiste =
    mysqli_fetch_assoc(
        $resultadoExiste
    );


mysqli_stmt_close(
    $stmtExiste
);


/* =========================================================
   ASISTENCIA DUPLICADA
========================================================= */

if ($registroExiste) {

    $horaAnterior =
        date(
            'H:i:s',
            strtotime(
                $registroExiste['hora_registro']
            )
        );


    responder(
        false,
        'La asistencia de ' .
        $estudiante['nombres'] .
        ' ' .
        $estudiante['apellidos'] .
        ' ya estaba registrada.',
        [
            'duplicado' => true,
            'hora' => $horaAnterior
        ]
    );
}


/* =========================================================
   INSERTAR ASISTENCIA
========================================================= */

$estado =
    'PRESENTE';


$sqlInsertar = "

    INSERT INTO asistencia_clase
    (
        id_sesion,
        id_estudiante,
        estado,
        estado_excusa,
        hora_registro
    )

    VALUES
    (
        ?,
        ?,
        ?,
        NULL,
        NOW()
    )

";


$stmtInsertar =
    mysqli_prepare(
        $conexion,
        $sqlInsertar
    );


if (!$stmtInsertar) {

    responder(
        false,
        'No fue posible preparar el registro de asistencia.'
    );
}


mysqli_stmt_bind_param(
    $stmtInsertar,
    "iis",
    $idSesion,
    $idEstudiante,
    $estado
);


if (!mysqli_stmt_execute($stmtInsertar)) {

    $errorMysql =
        mysqli_stmt_error(
            $stmtInsertar
        );

    mysqli_stmt_close(
        $stmtInsertar
    );


    /*
     * Esto aparecerá en la consola
     * mientras estamos probando el sistema.
     */

    responder(
        false,
        'No fue posible registrar la asistencia: ' .
        $errorMysql
    );
}


mysqli_stmt_close(
    $stmtInsertar
);


/* =========================================================
   ÉXITO
========================================================= */

responder(
    true,
    'Asistencia registrada correctamente.',
    [
        'estudiante' =>
            $estudiante['nombres'] .
            ' ' .
            $estudiante['apellidos'],

        'documento' =>
            $estudiante['documento'],

        'curso' =>
            $sesion['nombre_curso'],

        'hora' =>
            date('H:i:s')
    ]
);