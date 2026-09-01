```php
<?php

session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id_usuario'])) {

    echo json_encode([
        'success' => false,
        'mensaje' => 'La sesión del docente ha expirado.'
    ]);

    exit();
}


require_once "../config/conexion.php";

date_default_timezone_set('America/Bogota');


$idDocente =
    (int)$_SESSION['id_usuario'];


/* =========================================================
   RECIBIR DATOS
========================================================= */

$documento =
    trim(
        $_POST['documento']
        ?? ''
    );

$idSesion =
    (int)(
        $_POST['id_sesion']
        ?? 0
    );


if ($documento === '') {

    echo json_encode([
        'success' => false,
        'mensaje' => 'No se recibió el documento del estudiante.'
    ]);

    exit();
}


if ($idSesion <= 0) {

    echo json_encode([
        'success' => false,
        'mensaje' => 'No se indicó la sesión de clase.'
    ]);

    exit();
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

    echo json_encode([
        'success' => false,
        'mensaje' => 'No fue posible consultar la sesión.'
    ]);

    exit();
}


mysqli_stmt_bind_param(
    $stmtSesion,
    "ii",
    $idSesion,
    $idDocente
);


mysqli_stmt_execute(
    $stmtSesion
);


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

    echo json_encode([
        'success' => false,
        'mensaje' => 'La sesión no pertenece al docente.'
    ]);

    exit();
}


if (
    $sesion['estado']
    !== 'ABIERTA'
) {

    echo json_encode([
        'success' => false,
        'mensaje' => 'La sesión de asistencia está cerrada.'
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

    echo json_encode([
        'success' => false,
        'mensaje' => 'No fue posible consultar el estudiante.'
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


if (!$estudiante) {

    echo json_encode([
        'success' => false,
        'mensaje' => 'No se encontró un estudiante con ese documento.'
    ]);

    exit();
}


/* =========================================================
   VERIFICAR ESTADO DEL ESTUDIANTE
========================================================= */

if (
    strtoupper(
        $estudiante['estado']
    ) !== 'ACTIVO'
) {

    echo json_encode([
        'success' => false,
        'mensaje' => 'El estudiante no se encuentra activo.'
    ]);

    exit();
}


/* =========================================================
   VERIFICAR QUE PERTENEZCA AL CURSO
========================================================= */

if (
    (int)$estudiante['id_curso']
    !== (int)$sesion['id_curso']
) {

    echo json_encode([
        'success' => false,
        'mensaje' =>
            'El estudiante no pertenece al curso de esta sesión.'
    ]);

    exit();
}


/* =========================================================
   VERIFICAR DUPLICADO
========================================================= */

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

    echo json_encode([
        'success' => false,
        'mensaje' => 'No fue posible verificar la asistencia.'
    ]);

    exit();
}


$idEstudiante =
    (int)$estudiante['id_estudiante'];


mysqli_stmt_bind_param(
    $stmtExiste,
    "ii",
    $idSesion,
    $idEstudiante
);


mysqli_stmt_execute(
    $stmtExiste
);


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


if ($registroExiste) {

    echo json_encode([
        'success' => false,
        'duplicado' => true,
        'mensaje' =>
            'La asistencia de '
            . $estudiante['nombres']
            . ' '
            . $estudiante['apellidos']
            . ' ya estaba registrada.',
        'hora' =>
            date(
                'H:i:s',
                strtotime(
                    $registroExiste['hora_registro']
                )
            )
    ]);

    exit();
}


/* =========================================================
   REGISTRAR ASISTENCIA
========================================================= */

$estado = 'PRESENTE';

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

    echo json_encode([
        'success' => false,
        'mensaje' =>
            'No fue posible preparar el registro de asistencia.'
    ]);

    exit();
}


mysqli_stmt_bind_param(
    $stmtInsertar,
    "iis",
    $idSesion,
    $idEstudiante,
    $estado
);


if (
    mysqli_stmt_execute(
        $stmtInsertar
    )
) {

    mysqli_stmt_close(
        $stmtInsertar
    );

    echo json_encode([
        'success' => true,
        'mensaje' =>
            'Asistencia registrada correctamente.',
        'estudiante' =>
            $estudiante['nombres']
            . ' '
            . $estudiante['apellidos'],
        'documento' =>
            $estudiante['documento'],
        'hora' =>
            date('H:i:s')
    ]);

    exit();
}


mysqli_stmt_close(
    $stmtInsertar
);


echo json_encode([
    'success' => false,
    'mensaje' =>
        'No fue posible registrar la asistencia.'
]);

exit();
```
