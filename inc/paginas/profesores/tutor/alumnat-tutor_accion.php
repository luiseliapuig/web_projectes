<?php
declare(strict_types=1);

if (!esTutor()) {
    http_response_code(403);
    die('Accés no permès');
}
$profesorId = (int) $_SESSION['professor_id'];
$redirigir = static function (string $curso, string $sufijo=''): never {
    if (!preg_match('/^[0-9]{4}-[0-9]{2}$/', $curso)) $curso=cursoAcademicoActual();
    $url='/index.php?main=alumnat-tutor&curso=' . rawurlencode($curso) . $sufijo;
    echo '<script>location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
};
$redirigirFormulario = static function (string $curso): never {
    if (!preg_match('/^[0-9]{4}-[0-9]{2}$/', $curso)) $curso=cursoAcademicoActual();
    $url='/index.php?main=alumnat-tutor_form&curso=' . rawurlencode($curso);
    echo '<script>location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
};
$accion=isset($_POST['accio']) && is_string($_POST['accio']) ? trim($_POST['accio']) : '';
$returnCurso=isset($_POST['return_curso']) && is_string($_POST['return_curso']) ? trim($_POST['return_curso']) : cursoAcademicoActual();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !in_array($accion, ['guardar','eliminar'], true) || !validarTokenCsrf($_POST['csrf_token'] ?? null)) {
    $_SESSION['alumnat_tutor_error']='La sol·licitud no és vàlida o ha caducat.';
    $redirigir($returnCurso);
}
$id=isset($_POST['id_alumno']) ? (int) $_POST['id_alumno'] : 0;

$matriculaPropia = static function (PDO $pdo, int $profesorId, int $alumnoId, string $curso, bool $bloquear=false): ?int {
    $sql="
        SELECT rag.grupo_id
        FROM app.rel_alumnos_grupos rag
        INNER JOIN app.rel_profesores_grupos rpg
            ON rpg.grupo_id=rag.grupo_id AND rpg.curso_academico=rag.curso_academico
        WHERE rag.alumno_id=:alumno_id AND rag.curso_academico=:curso AND rpg.profesor_id=:profesor_id
        LIMIT 1
    ";
    if ($bloquear) $sql .= ' FOR UPDATE OF rag';
    $stmt=$pdo->prepare($sql);
    $stmt->execute([':alumno_id'=>$alumnoId, ':curso'=>$curso, ':profesor_id'=>$profesorId]);
    $grupo=$stmt->fetchColumn();
    return $grupo === false ? null : (int) $grupo;
};

if ($accion === 'eliminar') {
    if ($id <= 0 || !preg_match('/^[0-9]{4}-[0-9]{2}$/', $returnCurso)) {
        $_SESSION['alumnat_tutor_error']='L’alumne indicat no és vàlid.';
        $redirigir($returnCurso);
    }
    try {
        $pdo->beginTransaction();
        if ($matriculaPropia($pdo, $profesorId, $id, $returnCurso, true) === null) throw new DomainException('sin_permiso');
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM app.rel_proyectos_alumnos WHERE alumno_id=:id');
        $stmt->execute([':id'=>$id]);
        if ((int) $stmt->fetchColumn() > 0) throw new DomainException('con_proyectos');
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM app.rel_alumnos_grupos WHERE alumno_id=:id AND curso_academico<>:curso');
        $stmt->execute([':id'=>$id, ':curso'=>$returnCurso]);
        if ((int) $stmt->fetchColumn() > 0) throw new DomainException('con_historial');
        $pdo->prepare('DELETE FROM app.rel_alumnos_grupos WHERE alumno_id=:id AND curso_academico=:curso')->execute([':id'=>$id, ':curso'=>$returnCurso]);
        $stmt=$pdo->prepare('DELETE FROM app.alumnos WHERE id_alumno=:id RETURNING id_alumno');
        $stmt->execute([':id'=>$id]);
        if (!$stmt->fetchColumn()) throw new RuntimeException('no_encontrado');
        $pdo->commit();
        $redirigir($returnCurso, '&msg=eliminat');
    } catch (DomainException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['alumnat_tutor_error']=match($e->getMessage()) {
            'con_proyectos' => 'No es pot eliminar l’alumne perquè forma part d’un projecte. Pots desactivar-lo.',
            'con_historial' => 'No es pot eliminar l’alumne perquè conserva matrícules d’altres cursos. Pots desactivar-lo.',
            default => 'No tens permís per gestionar aquest alumne.',
        };
        $redirigir($returnCurso);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Error gestionant alumne des del panell de tutor: ' . $e->getMessage());
        $_SESSION['alumnat_tutor_error']='No s’ha pogut completar l’operació.';
        $redirigir($returnCurso);
    }
}

$nombre=isset($_POST['nombre']) && is_string($_POST['nombre']) ? trim($_POST['nombre']) : '';
$apellidos=isset($_POST['apellidos']) && is_string($_POST['apellidos']) ? trim($_POST['apellidos']) : '';
$email=isset($_POST['email']) && is_string($_POST['email']) ? normalizarCorreoInstitucional($_POST['email']) : '';
$curso=isset($_POST['curso_academico']) && is_string($_POST['curso_academico']) ? trim($_POST['curso_academico']) : '';
$cursoOriginal=isset($_POST['curso_original']) && is_string($_POST['curso_original']) ? trim($_POST['curso_original']) : $curso;
$grupoId=isset($_POST['grupo_id']) ? (int) $_POST['grupo_id'] : 0;
$activo=isset($_POST['activo']) ? 1 : 0;
$enviarInvitacion=$id <= 0 && isset($_POST['enviar_invitacion']);
if ($nombre==='' || $apellidos==='' || mb_strlen($nombre)>100 || mb_strlen($apellidos)>150 || mb_strlen($email)>255 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[0-9]{4}-[0-9]{2}$/',$curso) || $curso!==$cursoOriginal || $grupoId<=0) {
    $_SESSION['alumnat_tutor_error']='Revisa els camps obligatoris de l’alumne.';
    $redirigir($curso !== '' ? $curso : $returnCurso);
}
$stmt=$pdo->prepare("
    SELECT g.id_grupo, g.id_ciclo, g.grupo, c.abr AS ciclo
    FROM app.rel_profesores_grupos rpg
    INNER JOIN app.grupos g ON g.id_grupo=rpg.grupo_id
    INNER JOIN app.ciclos c ON c.id_ciclo=g.id_ciclo
    WHERE rpg.profesor_id=:profesor_id AND rpg.grupo_id=:grupo_id AND rpg.curso_academico=:curso
    LIMIT 1
");
$stmt->execute([':profesor_id'=>$profesorId, ':grupo_id'=>$grupoId, ':curso'=>$curso]);
$grupo=$stmt->fetch(PDO::FETCH_ASSOC);
if (!$grupo) {
    $_SESSION['alumnat_tutor_error']='El grup seleccionat no està entre els teus grups.';
    $redirigir($curso);
}
$_SESSION['tutor_filtres']['curso'] = $curso;
$_SESSION['tutor_filtres']['por_curso'][$curso] = [
    'ciclo_id' => (int) $grupo['id_ciclo'],
    'grupo_id' => $grupoId,
];

$esAlta=$id<=0;
$sinPassword=false;
try {
    $pdo->beginTransaction();
    if (!$esAlta) {
        if ($matriculaPropia($pdo,$profesorId,$id,$curso,true)===null) throw new DomainException('sin_permiso');
        $stmt=$pdo->prepare('SELECT 1 FROM app.alumnos WHERE lower(email)=:email AND id_alumno<>:id LIMIT 1');
        $stmt->execute([':email'=>$email, ':id'=>$id]);
        if ($stmt->fetchColumn()) throw new DomainException('email_duplicado');
    } else {
        $stmt=$pdo->prepare('SELECT id_alumno,password_hash FROM app.alumnos WHERE lower(email)=:email LIMIT 1 FOR UPDATE');
        $stmt->execute([':email'=>$email]);
        $existente=$stmt->fetch(PDO::FETCH_ASSOC);
        if ($existente) {
            throw new DomainException('email_duplicado');
        }
    }
    if ($id>0) {
        $stmt=$pdo->prepare("UPDATE app.alumnos SET nombre=:nombre,apellidos=:apellidos,email=:email,activo=:activo,ciclo=:ciclo,grupo=:grupo,curso_academico=CASE WHEN curso_academico IS NULL OR curso_academico<=:curso THEN :curso ELSE curso_academico END WHERE id_alumno=:id");
        $stmt->execute([':nombre'=>$nombre,':apellidos'=>$apellidos,':email'=>$email,':activo'=>$activo,':ciclo'=>$grupo['ciclo'],':grupo'=>$grupo['grupo'],':curso'=>$curso,':id'=>$id]);
    } else {
        $stmt=$pdo->prepare('INSERT INTO app.alumnos (nombre,apellidos,email,ciclo,grupo,curso_academico,activo) VALUES (:nombre,:apellidos,:email,:ciclo,:grupo,:curso,:activo) RETURNING id_alumno');
        $stmt->execute([':nombre'=>$nombre,':apellidos'=>$apellidos,':email'=>$email,':ciclo'=>$grupo['ciclo'],':grupo'=>$grupo['grupo'],':curso'=>$curso,':activo'=>$activo]);
        $id=(int)$stmt->fetchColumn();
        $sinPassword=true;
    }
    $stmt=$pdo->prepare('INSERT INTO app.rel_alumnos_grupos (alumno_id,grupo_id,curso_academico) VALUES (:id,:grupo,:curso) ON CONFLICT (alumno_id,curso_academico) DO UPDATE SET grupo_id=EXCLUDED.grupo_id');
    $stmt->execute([':id'=>$id,':grupo'=>$grupoId,':curso'=>$curso]);
    $pdo->commit();
} catch (DomainException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($e->getMessage() === 'email_duplicado' && $esAlta) {
        $_SESSION['alumnat_tutor_form_error']='Aquest email ja pertany a un alumne introduït al sistema.';
        $_SESSION['alumnat_tutor_form_old']=[
            'nombre'=>$nombre,
            'apellidos'=>$apellidos,
            'email'=>$email,
            'activo'=>$activo,
            'grupo_id'=>$grupoId,
        ];
        $redirigirFormulario($curso);
    }
    $_SESSION['alumnat_tutor_error']=match($e->getMessage()) {
        'email_duplicado'=>'Ja existeix un altre alumne amb aquest email.',
        'matricula_ajena'=>'Aquest alumne ja està matriculat en un grup que no tens assignat.',
        default=>'No tens permís per gestionar aquest alumne.',
    };
    $redirigir($curso);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Error guardant alumne des del panell de tutor: '.$e->getMessage());
    $_SESSION['alumnat_tutor_error']='No s’ha pogut guardar l’alumne.';
    $redirigir($curso);
}

if ($esAlta && $enviarInvitacion && $activo && $sinPassword) {
    try {
        require_once dirname(__DIR__,3).'/email/bootstrap.php';
        (new StudentInvitation($pdo,new EmailService(EmailConfig::fromEnvironment())))->send($id);
        $redirigir($curso,'&msg=creat-invitat');
    } catch (Throwable $e) {
        error_log('No se pudo invitar al alumno #'.$id.' desde el panel de tutor: '.$e->getMessage());
        $_SESSION['alumnat_tutor_warning']='L’alumne s’ha guardat, però no s’ha pogut enviar la invitació.';
    }
}
$redirigir($curso,'&msg=guardat');
