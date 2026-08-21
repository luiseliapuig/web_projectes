# Código en cuarentena

Esta carpeta conserva temporalmente flujos heredados que ya no forman parte de
las páginas activas ni deben exponerse desde el router.

`evaluaciones/` contiene la antigua autoevaluación, las valoraciones de tutor y
tribunal, el cálculo de nota final y sus acciones de escritura. Se mantiene como
referencia para reconstruir esos flujos con el nuevo modelo de permisos.

No se deben incluir estos archivos desde `inc/paginas/`. Cuando se rehaga una
función, debe volver a su área autorizada bajo `inc/paginas/` y declararse de
forma explícita en el router.
