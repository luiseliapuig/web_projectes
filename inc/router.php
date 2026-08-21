<?php
declare(strict_types=1);

$rutaTipo = 'pagina';
$rutaApiDenegada = false;

// La sesión provisional del profesorado solo puede completar su contraseña o salir.
$rutasMigradas = [
    // Catálogo público: portada, listado por ciclo y ficha de proyecto.
    'main' => [
        'archivo' => __DIR__ . '/paginas/public/main.php',
        'area' => 'public',
    ],
    'llistat-projectes' => [
        'archivo' => __DIR__ . '/paginas/public/llistat-projectes.php',
        'area' => 'public',
    ],
    'ficha' => [
        'archivo' => __DIR__ . '/paginas/public/ficha.php',
        'area' => 'public',
    ],
    // Autenticación: pantallas y acciones públicas agrupadas bajo un único flujo.
    'login' => [
        'archivo' => __DIR__ . '/paginas/public/login/login.php',
        'area' => 'public',
    ],
    'login_accion' => [
        'archivo' => __DIR__ . '/paginas/public/login/login_accion.php',
        'area' => 'public',
    ],
    'recuperar_password' => [
        'archivo' => __DIR__ . '/paginas/public/login/recuperar_password.php',
        'area' => 'public',
    ],
    'recuperar_password_accion' => [
        'archivo' => __DIR__ . '/paginas/public/login/recuperar_password_accion.php',
        'area' => 'public',
    ],
    'restablecer_password' => [
        'archivo' => __DIR__ . '/paginas/public/login/restablecer_password.php',
        'area' => 'public',
    ],
    'restablecer_password_accion' => [
        'archivo' => __DIR__ . '/paginas/public/login/restablecer_password_accion.php',
        'area' => 'public',
    ],
    'logout_accion' => [
        'archivo' => __DIR__ . '/paginas/public/login/logout_accion.php',
        'area' => 'public',
    ],
    // Área del alumnado: dashboard por fases, edición y entrega final del proyecto vinculado.
    'alumne-projecte' => ['archivo' => __DIR__ . '/paginas/alumnos/projecte.php', 'area' => 'alumno'],
    'alumne-fase-1' => ['archivo' => __DIR__ . '/paginas/alumnos/fase-1.php', 'area' => 'alumno'],
    'alumne-fase-1-grup-form' => ['archivo' => __DIR__ . '/paginas/alumnos/fase-1_grup_form.php', 'area' => 'alumno'],
    'alumne-fase-1-grup-accion' => ['archivo' => __DIR__ . '/paginas/alumnos/fase-1_grup_accion.php', 'area' => 'alumno'],
    'alumne-fase-1-compromis-form' => ['archivo' => __DIR__ . '/paginas/alumnos/fase-1_compromis_form.php', 'area' => 'alumno'],
    'alumne-fase-1-compromis-accion' => ['archivo' => __DIR__ . '/paginas/alumnos/fase-1_compromis_accion.php', 'area' => 'alumno'],
    'alumne-fase-2' => ['archivo' => __DIR__ . '/paginas/alumnos/fase-2.php', 'area' => 'alumno'],
    'alumne-fase-3' => ['archivo' => __DIR__ . '/paginas/alumnos/fase-3.php', 'area' => 'alumno'],
    'alumne-fase-4' => ['archivo' => __DIR__ . '/paginas/alumnos/fase-4.php', 'area' => 'alumno'],
    'alumne-fase-5' => ['archivo' => __DIR__ . '/paginas/alumnos/fase-5.php', 'area' => 'alumno'],
    'alumne-fase-6' => ['archivo' => __DIR__ . '/paginas/alumnos/fase-6.php', 'area' => 'alumno'],
    'alumne-fase-7' => ['archivo' => __DIR__ . '/paginas/alumnos/fase-7.php', 'area' => 'alumno'],
    'ficha_proyecto_form' => [
        'archivo' => __DIR__ . '/paginas/alumnos/ficha_proyecto_form.php',
        'area' => 'alumno',
    ],
    'ficha_proyecto_accion' => [
        'archivo' => __DIR__ . '/paginas/alumnos/ficha_proyecto_accion.php',
        'area' => 'alumno',
    ],
    'ficha_proyecto_adjunto_accion' => [
        'archivo' => __DIR__ . '/paginas/alumnos/ficha_proyecto_adjunto_accion.php',
        'area' => 'alumno',
        'tipo' => 'api',
    ],
    'ficha_proyecto_defensa_form' => [
        'archivo' => __DIR__ . '/paginas/alumnos/ficha_proyecto_defensa_form.php',
        'area' => 'alumno',
    ],
    'ficha_proyecto_defensa_accion' => [
        'archivo' => __DIR__ . '/paginas/alumnos/ficha_proyecto_defensa_accion.php',
        'area' => 'alumno',
    ],
    'enviar-emails-profesorado' => [
        'archivo' => __DIR__ . '/paginas/admin/enviar-emails-profesorado.php',
        'area' => 'admin',
    ],
    'emails' => [
        'archivo' => __DIR__ . '/paginas/admin/emails.php',
        'area' => 'admin',
    ],
    'emails_accion' => [
        'archivo' => __DIR__ . '/paginas/admin/emails_accion.php',
        'area' => 'admin',
    ],
    'enviar-emails-tutores' => [
        'archivo' => __DIR__ . '/paginas/admin/enviar-emails-tutores.php',
        'area' => 'admin',
    ],
    'enviar-emails-alumnado' => [
        'archivo' => __DIR__ . '/paginas/admin/enviar-emails-alumnado.php',
        'area' => 'admin',
    ],
    'lista-emails-profesorado' => [
        'archivo' => __DIR__ . '/paginas/admin/lista-emails-profesorado.php',
        'area' => 'admin',
    ],
    'lista-emails-tutores' => [
        'archivo' => __DIR__ . '/paginas/admin/lista-emails-tutores.php',
        'area' => 'admin',
    ],
    'lista-emails-alumnado' => [
        'archivo' => __DIR__ . '/paginas/admin/lista-emails-alumnado.php',
        'area' => 'admin',
    ],
    'professorat' => [
        'archivo' => __DIR__ . '/paginas/admin/professorat.php',
        'area' => 'admin',
    ],
    'professorat_form' => [
        'archivo' => __DIR__ . '/paginas/admin/professorat_form.php',
        'area' => 'admin',
    ],
    'professorat_accion' => [
        'archivo' => __DIR__ . '/paginas/admin/professorat_accion.php',
        'area' => 'admin',
    ],
    'professorat_invitacion_accion' => [
        'archivo' => __DIR__ . '/paginas/admin/professorat_invitacion_accion.php',
        'area' => 'admin',
    ],
    'alumnat' => [
        'archivo' => __DIR__ . '/paginas/admin/alumnat.php',
        'area' => 'admin',
    ],
    'alumnat_form' => [
        'archivo' => __DIR__ . '/paginas/admin/alumnat_form.php',
        'area' => 'admin',
    ],
    'alumnat_accion' => [
        'archivo' => __DIR__ . '/paginas/admin/alumnat_accion.php',
        'area' => 'admin',
    ],
    'configuracion' => [
        'archivo' => __DIR__ . '/paginas/admin/configuracion.php',
        'area' => 'admin',
    ],
    'configuracion_form' => [
        'archivo' => __DIR__ . '/paginas/admin/configuracion_form.php',
        'area' => 'admin',
    ],
    'configuracion_accion' => [
        'archivo' => __DIR__ . '/paginas/admin/configuracion_accion.php',
        'area' => 'admin',
    ],
    'planificacio' => [
        'archivo' => __DIR__ . '/paginas/admin/defensas/planificacion.php',
        'area' => 'admin',
    ],
    'planificacio_simular' => [
        'archivo' => __DIR__ . '/paginas/admin/defensas/planificacion_simular.php',
        'area' => 'admin',
        'tipo' => 'api',
    ],
    'planificacio_accion' => [
        'archivo' => __DIR__ . '/paginas/admin/defensas/planificacion_generar.php',
        'area' => 'admin',
        'tipo' => 'api',
    ],
    'planificacio_eliminar' => [
        'archivo' => __DIR__ . '/paginas/admin/defensas/planificacion_eliminar.php',
        'area' => 'admin',
        'tipo' => 'api',
    ],
    'calendari_drag' => [
        'archivo' => __DIR__ . '/paginas/admin/defensas/calendario.php',
        'area' => 'admin',
    ],
    'calendari_drag_dades' => [
        'archivo' => __DIR__ . '/paginas/admin/defensas/calendario_datos.php',
        'area' => 'admin',
        'tipo' => 'api',
    ],
    'calendari_modificar' => [
        'archivo' => __DIR__ . '/paginas/admin/defensas/calendario_mover.php',
        'area' => 'admin',
        'tipo' => 'api',
    ],
    'defensas_print' => [
        'archivo' => __DIR__ . '/paginas/admin/defensas/imprimir.php',
        'area' => 'admin',
    ],
    'aules' => [
        'archivo' => __DIR__ . '/paginas/admin/aules.php',
        'area' => 'admin',
    ],
    'aules_form' => [
        'archivo' => __DIR__ . '/paginas/admin/aules_form.php',
        'area' => 'admin',
    ],
    'aules_accion' => [
        'archivo' => __DIR__ . '/paginas/admin/aules_accion.php',
        'area' => 'admin',
    ],
    'grups-cicle' => [
        'archivo' => __DIR__ . '/paginas/admin/grups-cicle.php',
        'area' => 'admin',
    ],
    'grups-cicle_form' => [
        'archivo' => __DIR__ . '/paginas/admin/grups-cicle_form.php',
        'area' => 'admin',
    ],
    'grups-cicle_accion' => [
        'archivo' => __DIR__ . '/paginas/admin/grups-cicle_accion.php',
        'area' => 'admin',
    ],
    'cicles' => [
        'archivo' => __DIR__ . '/paginas/admin/cicles.php',
        'area' => 'admin',
    ],
    'cicles_form' => [
        'archivo' => __DIR__ . '/paginas/admin/cicles_form.php',
        'area' => 'admin',
    ],
    'cicles_accion' => [
        'archivo' => __DIR__ . '/paginas/admin/cicles_accion.php',
        'area' => 'admin',
    ],
    'families-cicles' => [
        'archivo' => __DIR__ . '/paginas/admin/families-cicles.php',
        'area' => 'admin',
    ],
    'families-cicles_form' => [
        'archivo' => __DIR__ . '/paginas/admin/families-cicles_form.php',
        'area' => 'admin',
    ],
    'families-cicles_accion' => [
        'archivo' => __DIR__ . '/paginas/admin/families-cicles_accion.php',
        'area' => 'admin',
    ],
    'categories-projectes' => [
        'archivo' => __DIR__ . '/paginas/admin/categories-projectes.php',
        'area' => 'admin',
    ],
    'categories-projectes_form' => [
        'archivo' => __DIR__ . '/paginas/admin/categories-projectes_form.php',
        'area' => 'admin',
    ],
    'categories-projectes_accion' => [
        'archivo' => __DIR__ . '/paginas/admin/categories-projectes_accion.php',
        'area' => 'admin',
    ],
    'tipus-projectes' => [
        'archivo' => __DIR__ . '/paginas/admin/tipus-projectes.php',
        'area' => 'admin',
    ],
    'tipus-projectes_form' => [
        'archivo' => __DIR__ . '/paginas/admin/tipus-projectes_form.php',
        'area' => 'admin',
    ],
    'tipus-projectes_accion' => [
        'archivo' => __DIR__ . '/paginas/admin/tipus-projectes_accion.php',
        'area' => 'admin',
    ],
    // Supervisió global dels projectes i edició administrativa excepcional.
    'proyectos' => [
        'archivo' => __DIR__ . '/paginas/admin/proyectos.php',
        'area' => 'admin',
    ],
    'proyectos_form' => [
        'archivo' => __DIR__ . '/paginas/admin/proyectos_form.php',
        'area' => 'admin',
    ],
    'proyectos_accion' => [
        'archivo' => __DIR__ . '/paginas/admin/proyectos_accion.php',
        'area' => 'admin',
    ],
    'projectes-grup' => [
        'archivo' => __DIR__ . '/paginas/profesores/tutor/projectes-grup.php',
        'area' => 'profesor',
    ],
    'cambiar_password' => [
        'archivo' => __DIR__ . '/paginas/public/login/cambiar_password.php',
        'area' => 'public',
    ],
    'cambiar_password_accion' => [
        'archivo' => __DIR__ . '/paginas/public/login/cambiar_password_accion.php',
        'area' => 'public',
    ],
    'projectes-grup_form' => [
        'archivo' => __DIR__ . '/paginas/profesores/tutor/projectes-grup_form.php',
        'area' => 'profesor',
    ],
    'projectes-grup_accion' => [
        'archivo' => __DIR__ . '/paginas/profesores/tutor/projectes-grup_accion.php',
        'area' => 'profesor',
    ],
    'alumnat-tutor' => [
        'archivo' => __DIR__ . '/paginas/profesores/tutor/alumnat-tutor.php',
        'area' => 'profesor',
    ],
    'alumnat-tutor_form' => [
        'archivo' => __DIR__ . '/paginas/profesores/tutor/alumnat-tutor_form.php',
        'area' => 'profesor',
    ],
    'alumnat-tutor_accion' => [
        'archivo' => __DIR__ . '/paginas/profesores/tutor/alumnat-tutor_accion.php',
        'area' => 'profesor',
    ],
    'alumnat-tutor_invitaciones_accion' => [
        'archivo' => __DIR__ . '/paginas/profesores/tutor/alumnat-tutor_invitaciones_accion.php',
        'area' => 'profesor',
    ],
    // Projectes vinculats al professor: vista principal de fitxes i alternativa en llista.
    'proyectos-tutorizados' => [
        'archivo' => __DIR__ . '/paginas/profesores/tutor/proyectos-tutorizados.php',
        'area' => 'profesor',
    ],
    'proyectos-tutorizados-lista' => [
        'archivo' => __DIR__ . '/paginas/profesores/tutor/proyectos-tutorizados-lista.php',
        'area' => 'profesor',
    ],
    // Consulta general de notas para el profesorado cuando la regla lo permite.
    'notes-finals' => [
        'archivo' => __DIR__ . '/paginas/profesores/notas-finales.php',
        'area' => 'profesor',
    ],
    // Tribunals: consulta del calendari i inscripció del professorat.
    'assignar-defenses' => [
        'archivo' => __DIR__ . '/paginas/profesores/tribunal/assignar-defenses.php',
        'area' => 'profesor',
    ],
    'assignar-defenses-dades' => [
        'archivo' => __DIR__ . '/paginas/profesores/tribunal/assignar-defenses-dades.php',
        'area' => 'profesor',
        'tipo' => 'api',
    ],
    'assignar-defenses-accio' => [
        'archivo' => __DIR__ . '/paginas/profesores/tribunal/assignar-defenses-accio.php',
        'area' => 'profesor',
        'tipo' => 'api',
    ],
    'les-meves-defenses' => [
        'archivo' => __DIR__ . '/paginas/profesores/tribunal/mis-defensas.php',
        'area' => 'profesor',
    ],
    'les-meves-defenses-lista' => [
        'archivo' => __DIR__ . '/paginas/profesores/tribunal/mis-defensas-lista.php',
        'area' => 'profesor',
    ],
];

if (isset($rutasMigradas[$main])) {
    $ruta = $rutasMigradas[$main];
    $rutaTipo = $ruta['tipo'] ?? 'pagina';

    if (
        $ruta['area'] === 'admin'
        && !esSuperadmin()
    ) {
        if ($rutaTipo === 'api') {
            $rutaApiDenegada = true;
        }
        return __DIR__ . '/paginas/public/main.php';
    }

    if (
        $ruta['area'] === 'profesor'
        && !esProfesor()
    ) {
        if ($rutaTipo === 'api') {
            $rutaApiDenegada = true;
        }
        return __DIR__ . '/paginas/public/main.php';
    }

    if (
        $ruta['area'] === 'alumno'
        && !esAlumno()
    ) {
        if ($rutaTipo === 'api') {
            $rutaApiDenegada = true;
        }
        return __DIR__ . '/paginas/public/main.php';
    }

    return $ruta['archivo'];
}

return __DIR__ . '/paginas/public/main.php';
