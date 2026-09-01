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
    'alumne-fases-projecte' => ['archivo' => __DIR__ . '/paginas/alumnos/fases_projecte.php', 'area' => 'alumno'],
    // Fases 1-7: implementació de l'arquitectura 'informatica' (vegeu
    // inc/fases/funciones.php i inc/paginas/alumnos/informatica/fases.php).
    // Les URLs públiques es mantenen; només canvia la ubicació física.
    'alumne-fase-1' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-1.php', 'area' => 'alumno'],
    'alumne-fase-1-grup-form' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-1_grup_form.php', 'area' => 'alumno'],
    'alumne-fase-1-grup-accion' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-1_grup_accion.php', 'area' => 'alumno'],
    'alumne-fase-1-compromis-form' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-1_compromis_form.php', 'area' => 'alumno'],
    'alumne-fase-1-compromis-accion' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-1_compromis_accion.php', 'area' => 'alumno'],
    'alumne-fase-2' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-2.php', 'area' => 'alumno'],
    'alumne-fase-2-proposta' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-2_proposta.php', 'area' => 'alumno'],
    'alumne-fase-2-accion' => [
        'archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-2_accion.php',
        'area' => 'alumno',
        'tipo' => 'api',
    ],
    'alumne-fase-3' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-3.php', 'area' => 'alumno'],
    'alumne-fase-3-document-funcional' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-3_document_funcional.php', 'area' => 'alumno'],
    'alumne-fase-3-accion' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-3_accion.php', 'area' => 'alumno', 'tipo' => 'api'],
    'alumne-fase-4' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-4.php', 'area' => 'alumno'],
    'alumne-fase-4-planificacio' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-4_planificacio.php', 'area' => 'alumno'],
    'alumne-fase-4-gestio' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-4_gestio.php', 'area' => 'alumno'],
    'alumne-fase-4-accion' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-4_accion.php', 'area' => 'alumno', 'tipo' => 'api'],
    'alumne-fase-5' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-5.php', 'area' => 'alumno'],
    'alumne-fase-5-preparacio-entorn' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-5_preparacio_entorn.php', 'area' => 'alumno'],
    'alumne-fase-5-repositoris' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-5_repositoris.php', 'area' => 'alumno'],
    'alumne-fase-5-tecnologies-eines' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-5_tecnologies_eines.php', 'area' => 'alumno'],
    'alumne-fase-5-stack-accion' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-5_stack_accion.php', 'area' => 'alumno', 'tipo' => 'api'],
    'alumne-fase-5-projecte-produccio' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-5_projecte_produccio.php', 'area' => 'alumno'],
    'alumne-fase-5-produccio-accio' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-5_produccio_accion.php', 'area' => 'alumno', 'tipo' => 'api'],
    'alumne-fase-5-autoavaluacio' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-5_autoavaluacio.php', 'area' => 'alumno'],
    'alumne-fase-5-autoavaluacio-accio' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-5_autoavaluacio_accion.php', 'area' => 'alumno', 'tipo' => 'api'],
    'alumne-fase-5-repositoris-accion' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-5_repositoris_accion.php', 'area' => 'alumno', 'tipo' => 'api'],
    'alumne-fase-5-entorn-accion' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-5_entorn_accion.php', 'area' => 'alumno', 'tipo' => 'api'],
    'alumne-fase-6' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-6.php', 'area' => 'alumno'],
    'alumne-fase-6-document-memoria' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-6_document_memoria.php', 'area' => 'alumno'],
    'alumne-fase-6-memoria-accio' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-6_memoria_accion.php', 'area' => 'alumno', 'tipo' => 'api'],
    'alumne-fase-6-fitxa-publica' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-6_fitxa_publica.php', 'area' => 'alumno'],
    'alumne-fase-6-fitxa-publica-accio' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-6_fitxa_publica_accion.php', 'area' => 'alumno', 'tipo' => 'api'],
    'alumne-fase-6-entrega-memoria' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-6_entrega_memoria.php', 'area' => 'alumno'],
    'alumne-fase-6-entrega-memoria-accio' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-6_entrega_memoria_accion.php', 'area' => 'alumno', 'tipo' => 'api'],
      'alumne-fase-7' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-7.php', 'area' => 'alumno'],
      'alumne-fase-7-presentacio-defensa' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-7_presentacio_defensa.php', 'area' => 'alumno'],
      'alumne-fase-7-presentacio-defensa-accio' => ['archivo' => __DIR__ . '/paginas/alumnos/informatica/fase-7_presentacio_defensa_accion.php', 'area' => 'alumno', 'tipo' => 'api'],
    'alumne-autoseguiment' => ['archivo' => __DIR__ . '/paginas/alumnos/autoseguiment.php', 'area' => 'alumno'],
    'alumne-autoseguiment-accion' => ['archivo' => __DIR__ . '/paginas/alumnos/autoseguiment_accion.php', 'area' => 'alumno'],
    'alumne-memoria' => ['archivo' => __DIR__ . '/paginas/alumnos/memoria.php', 'area' => 'alumno'],
    'alumne-memoria-accion' => [
        'archivo' => __DIR__ . '/paginas/alumnos/memoria_accion.php',
        'area' => 'alumno',
        'tipo' => 'api',
    ],
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
    'autoseguiment-control' => [
        'archivo' => __DIR__ . '/paginas/admin/autoseguiment-control.php',
        'area' => 'admin',
    ],
    'autoseguiment-control_accion' => [
        'archivo' => __DIR__ . '/paginas/admin/autoseguiment-control_accion.php',
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
    'memoria-estructura' => [
        'archivo' => __DIR__ . '/paginas/admin/memoria-estructura.php',
        'area' => 'admin',
    ],
    'memoria-estructura_form' => [
        'archivo' => __DIR__ . '/paginas/admin/memoria-estructura_form.php',
        'area' => 'admin',
    ],
    'memoria-estructura_accion' => [
        'archivo' => __DIR__ . '/paginas/admin/memoria-estructura_accion.php',
        'area' => 'admin',
    ],
    'memoria-estructura_orden_accion' => [
        'archivo' => __DIR__ . '/paginas/admin/memoria-estructura_orden_accion.php',
        'area' => 'admin',
        'tipo' => 'api',
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
    // Vista del tutor sobre l'Autoseguiment del seu alumnat (setmanes tancades).
    'autoseguiment-tutor' => [
        'archivo' => __DIR__ . '/paginas/profesores/tutor/autoseguiment-tutor.php',
        'area' => 'profesor',
    ],
    // "Resum": pantalla principal del professorat (abans anomenada
    // "Dashboard"). Mapa general dels seus projectes i la feina pendent.
    'resum-tutor' => [
        'archivo' => __DIR__ . '/paginas/profesores/tutor/resum-tutor.php',
        'area' => 'profesor',
    ],
    // Navegació contextual genèrica del professorat pel recorregut de fases
    // d'un projecte (proyecto_id + fase per GET). Substitueix l'antic
    // 'fase-2-tutor', específic d'una sola fase.
    // Equivalent contextual, per al professorat, de "Fases del projecte":
    // llistat general de les 7 fases d'UN projecte (proyecto_id per GET).
    'fases-tutor' => [
        'archivo' => __DIR__ . '/paginas/profesores/tutor/fases-tutor.php',
        'area' => 'profesor',
    ],
    'fase-tutor' => [
        'archivo' => __DIR__ . '/paginas/profesores/tutor/fase-tutor.php',
        'area' => 'profesor',
    ],
    'fase-1-tutor-compromis' => [
        'archivo' => __DIR__ . '/paginas/profesores/tutor/fase-1-tutor_compromis.php',
        'area' => 'profesor',
    ],
    'fase-2-tutor-proposta' => [
        'archivo' => __DIR__ . '/paginas/profesores/tutor/fase-2-tutor_proposta.php',
        'area' => 'profesor',
    ],
    'fase-2-tutor_accion' => [
        'archivo' => __DIR__ . '/paginas/profesores/tutor/fase-2-tutor_accion.php',
        'area' => 'profesor',
        'tipo' => 'api',
    ],
    'stack-tecnologies' => ['archivo' => __DIR__ . '/paginas/admin/stack-tecnologies.php', 'area' => 'admin'],
    'stack-tecnologies_form' => ['archivo' => __DIR__ . '/paginas/admin/stack-tecnologies_form.php', 'area' => 'admin'],
    'stack-tecnologies_accion' => ['archivo' => __DIR__ . '/paginas/admin/stack-tecnologies_accion.php', 'area' => 'admin'],
    'stack-eines' => ['archivo' => __DIR__ . '/paginas/admin/stack-eines.php', 'area' => 'admin'],
    'stack-eines_form' => ['archivo' => __DIR__ . '/paginas/admin/stack-eines_form.php', 'area' => 'admin'],
    'stack-eines_accion' => ['archivo' => __DIR__ . '/paginas/admin/stack-eines_accion.php', 'area' => 'admin'],
    'fase-3-tutor-document-funcional' => ['archivo' => __DIR__ . '/paginas/profesores/tutor/fase-3-tutor_document_funcional.php', 'area' => 'profesor'],
    'fase-3-tutor_accion' => ['archivo' => __DIR__ . '/paginas/profesores/tutor/fase-3-tutor_accion.php', 'area' => 'profesor', 'tipo' => 'api'],
    'fase-4-tutor-planificacio' => ['archivo' => __DIR__ . '/paginas/profesores/tutor/fase-4-tutor_planificacio.php', 'area' => 'profesor'],
    'fase-4-tutor-gestio' => ['archivo' => __DIR__ . '/paginas/profesores/tutor/fase-4-tutor_gestio.php', 'area' => 'profesor'],
    'fase-5-tutor-preparacio-entorn' => ['archivo' => __DIR__ . '/paginas/profesores/tutor/fase-5-tutor_preparacio_entorn.php', 'area' => 'profesor'],
    'fase-5-tutor-repositoris' => ['archivo' => __DIR__ . '/paginas/profesores/tutor/fase-5-tutor_repositoris.php', 'area' => 'profesor'],
    'fase-5-tutor-tecnologies-eines' => ['archivo' => __DIR__ . '/paginas/profesores/tutor/fase-5-tutor_tecnologies_eines.php', 'area' => 'profesor'],
    'fase-5-tutor-projecte-produccio' => ['archivo' => __DIR__ . '/paginas/profesores/tutor/fase-5-tutor_projecte_produccio.php', 'area' => 'profesor'],
    'fase-5-tutor-autoavaluacio' => ['archivo' => __DIR__ . '/paginas/profesores/tutor/fase-5-tutor_autoavaluacio.php', 'area' => 'profesor'],
    'fase-5-tutor-entorn-accion' => ['archivo' => __DIR__ . '/paginas/profesores/tutor/fase-5-tutor_entorn_accion.php', 'area' => 'profesor', 'tipo' => 'api'],
    'fase-6-tutor-document-memoria' => ['archivo' => __DIR__ . '/paginas/profesores/tutor/fase-6-tutor_document_memoria.php', 'area' => 'profesor'],
    'fase-6-tutor-fitxa-publica' => ['archivo' => __DIR__ . '/paginas/profesores/tutor/fase-6-tutor_fitxa_publica.php', 'area' => 'profesor'],
      'fase-6-tutor-entrega-memoria' => ['archivo' => __DIR__ . '/paginas/profesores/tutor/fase-6-tutor_entrega_memoria.php', 'area' => 'profesor'],
      'fase-7-tutor-presentacio-defensa' => ['archivo' => __DIR__ . '/paginas/profesores/tutor/fase-7-tutor_presentacio_defensa.php', 'area' => 'profesor'],
    'memoria-tutor' => [
        'archivo' => __DIR__ . '/paginas/profesores/tutor/memoria-tutor.php',
        'area' => 'profesor',
    ],
    'memoria-tutor_accion' => [
        'archivo' => __DIR__ . '/paginas/profesores/tutor/memoria-tutor_accion.php',
        'area' => 'profesor',
        'tipo' => 'api',
    ],
    'autoseguiment-tutor_accion' => [
        'archivo' => __DIR__ . '/paginas/profesores/tutor/autoseguiment-tutor_accion.php',
        'area' => 'profesor',
        'tipo' => 'api',
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
