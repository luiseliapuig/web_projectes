# Checklist de entrega

Usa los apartados aplicables al cambio.

## Alcance y arquitectura

- [ ] El cambio responde a la petición sin refactors ajenos.
- [ ] Los archivos están en el área correcta: `public`, `alumnos`, `profesores` o `admin`.
- [ ] Tutor y tribunal se tratan como funciones contextuales del profesor.
- [ ] El router solo admite rutas incluidas en su mapa o lista permitida.
- [ ] Páginas y acciones tienen la misma protección de área.
- [ ] Las URLs públicas existentes siguen funcionando cuando debían conservarse.

## PHP e interfaz

- [ ] Sintaxis PHP comprobada.
- [ ] Entradas validadas en servidor.
- [ ] Salidas escapadas según contexto.
- [ ] Formulario y acción concuerdan en campos, modos e IDs.
- [ ] La redirección tiene destino permitido y termina con `exit`.
- [ ] No quedan mensajes de depuración.

## Datos

- [ ] SQL preparado y parametrizado.
- [ ] Se respetan claves foráneas, unicidad, rangos y nulabilidad.
- [ ] Operaciones dependientes usan transacción.
- [ ] Se ha considerado el efecto sobre datos existentes.

## Acceso

- [ ] Funciona para el actor autorizado.
- [ ] Falla de forma segura sin sesión.
- [ ] Falla con tipo de usuario o rol insuficiente.
- [ ] Manipular ID o UUID no permite acceder a otro proyecto.
- [ ] El tutor se valida mediante `rel_proyectos_profesores` y no solo por estar en el área.
- [ ] El tribunal se valida mediante su asignación real.
- [ ] La recuperación no revela si un email pertenece a un profesor.
- [ ] Los tokens de contraseña son aleatorios, caducan, se almacenan con hash y solo se usan una vez.
- [ ] Las acciones repiten la autorización en servidor.

## Archivos

- [ ] Tipo, tamaño y contenido se validan en servidor.
- [ ] Nombre y destino los controla el servidor.
- [ ] El archivo no puede ejecutarse como PHP.
- [ ] Reemplazo y borrado no dejan referencias inválidas.

## Entrega

- [ ] Diff revisado.
- [ ] Se indican comprobaciones ejecutadas y no ejecutadas.
- [ ] Se documentan migraciones, riesgos y decisiones pendientes.
