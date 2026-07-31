# Corrección de PRDs viejos — SDD v3.2 (rev. 2)

> **Reemplaza la versión del 2026-07-28.** Su regla S1 ordenaba migrar el motor de base de datos a PostgreSQL: **eso ya no aplica.** El motor es un dato de entrada, no una regla. Si tenés la copia vieja, borrala.
>
> **Qué es:** instrucciones para llevar un PRD **ya existente** a la plantilla v3.2. Para PRDs nuevos se usa `PLANTILLA_PRD.md` + `INSTRUCCIONES_INIT_PROYECTO.md`.
>
> **Cómo se usa:** pegar este archivo completo junto con el PRD a corregir. Reutilizable para los catorce.

---

## PARTE A — Tarea

```
TAREA: Corregir y completar un PRD existente para que cumpla la plantilla SDD v3.2.

NO reescribas el PRD desde cero. NO cambies el tono ni la numeración existente.
Trabajá por adición y corrección puntual, conservando todo lo correcto que ya está.

ENTREGABLE: el PRD corregido completo, más un changelog al final listando cada
cambio con su justificación en una línea.

REGLA DE PARO: si para completar un bloque obligatorio necesitás inventar un dato
que no está en el PRD ni en estas instrucciones, NO lo inventes. Insertá:

    > **[DECISIÓN PENDIENTE — HUMANO]** <la pregunta concreta que hay que responder>

y seguí. Es preferible un PRD con quince decisiones pendientes marcadas que uno
con quince supuestos inventados que nadie aprobó.
```

---

## PARTE B — Reglas globales de stack

```
[STACK SDD v3.2 — CORREGIR SOLO LO QUE CORRESPONDA]

S1. MOTOR DE BASE DE DATOS = DATO DE ENTRADA, NO REGLA.
    ⚠ NO cambies el motor de un PRD. Nunca. El motor puede venir impuesto por el
    cliente, por el hosting o por un proyecto ya construido, y eso es válido.
    - Si el PRD declara un motor: agregá al encabezado si fue ELEGIDO o IMPUESTO,
      y por quién. Si no se puede determinar, marcalo como DECISIÓN PENDIENTE.
    - PostgreSQL 16 + pgvector es el DEFECTO solo cuando la decisión es propia
      (proyecto de portafolio nuevo).
    - Si el motor NO es PostgreSQL, no lo corrijas: DECLARÁ SUS CONSECUENCIAS en
      el bloque B10, usando la tabla de sustitución de PLANTILLA_PRD.md.
      Las cuatro que más importan:
        * búsqueda difusa: sin pg_trgm hay que aceptar menor calidad (declararlo
          en el RNF) o agregar un motor de búsqueda aparte
        * no solapamiento de rangos: MySQL no tiene restricciones de exclusión;
          se resuelve con SELECT FOR UPDATE sobre la fila padre + UNIQUE
        * RAG: en MySQL Community no es viable dentro de la base (DISTANCE() e
          índice vectorial requieren HeatWave); el servicio FastAPI usa su propio
          PostgreSQL separado
        * DDL: en MySQL no se envuelven varias sentencias en una transacción
          revertible; migraciones de una sentencia y snapshot previo obligatorio
    - Lo que NO cambia con el motor: esqueleto, reglas de capas, contratos,
      gauntlet y pruebas. El motor cambia; la arquitectura no.

S2. Combos de stack permitidos, sin cruzarlos:
    (a) Laravel 11 + Vue 3 + Inertia   (b) Express 5 + TypeScript + React 19
    (c) FastAPI — SOLO para servicios de IA.   Cache y colas: Redis + Horizon.
    Si el PRD cruza combos (Laravel+React, Express+Vue) en un proyecto NUEVO,
    señalalo. Si el proyecto ya está construido, se declara como desviación.

S3. PROHIBIDO en PRDs nuevos sin aprobación humana escrita:
    Django, Celery, Next.js, Kafka, CQRS, event sourcing,
    LangChain/CrewAI/LangGraph, bases vectoriales externas, Redux por defecto,
    Atomic Design completo, Clean Architecture / Hexagonal completas.

S4. Gestores: pnpm (Node) · composer (PHP) · uv o pip (Python). Uno por proyecto.

S5. Tipado estricto: TS "strict": true · PHP declare(strict_types=1) + PHPStan 6+
    · Python type hints + mypy.

S6. Estructura congelada (regla S2 de PROTOCOLO.md):
    Express:  src/modules/<feature>/{routes,controller,service,repo,schema}
    FastAPI:  app/modules/<feature>/{router,service,schemas}
    Laravel:  convención del framework + app/Actions
    PROHIBIDO: carpetas controllers/ services/ models/ en la raíz.
```

---

## PARTE C — Los once bloques obligatorios

```
[VERIFICAR QUE EL PRD CONTENGA LOS ONCE BLOQUES. AGREGAR LOS QUE FALTEN.]

B1.  Descripción y problema — en términos del usuario, no de la solución.

B2.  Alcance: incluido Y FUERA DE ALCANCE.
     El bloque más valioso. Si falta "fuera de alcance", agregalo: es lo que
     impide que un agente invente funcionalidad. Mínimo tres entradas.

B3.  Actores y permisos — con columna "NO puede". Es el insumo directo de las
     pruebas de autorización.

B4.  Requerimientos funcionales CON CRITERIO DE ACEPTACIÓN.
     >>> EL CAMBIO MÁS IMPORTANTE <<<
     Cada RF lleva, además de su descripción, un criterio verificable en formato
     Dado / Cuando / Entonces. Una descripción no se puede automatizar; un
     criterio sí, y de ahí sale el escenario Gherkin directo.

     **Criterio de aceptación RF-0X**
     ```gherkin
     Escenario: <caso principal>
       Dado <estado inicial concreto, con datos>
       Cuando <acción concreta del actor>
       Entonces <resultado observable y medible>

     Escenario: <caso límite o de error>
       Dado ... / Cuando ... / Entonces ...
     ```
     Mínimo dos escenarios por RF. El "Entonces" debe ser observable desde
     fuera (respuesta HTTP, fila en la base, elemento visible), nunca "el
     sistema funciona correctamente".

B5.  Requerimientos no funcionales MEDIBLES.
     Todo RNF con un número declara: medido cómo, sobre qué conjunto, línea base
     y umbral. PROHIBIDO "más del 90% de exactitud", "debe ser rápido", "alta
     disponibilidad", "buena experiencia de usuario" sin método de medición.
     Reescribilo como métrica verificable o marcalo como DECISIÓN PENDIENTE.

B6.  Reglas de negocio CON SU PUNTO DE APLICACIÓN.
     Por cada regla, dónde se hace cumplir: restricción en la base de datos
     (UNIQUE, CHECK, EXCLUDE, FK) / capa de servicio / interfaz.
     Toda regla que proteja dinero, stock, cupos o unicidad DEBE tener su
     defensa en la base de datos, no solo en el servicio.

B7.  Contratos de API (Gate 2C) — incluidos o referenciados.
     Por endpoint: método, ruta, cuerpo de petición y respuesta, códigos de
     estado, forma exacta del error, comportamiento ante entrada inválida o
     ausente, y requisitos de autenticación y autorización.

B8.  GOBERNANZA DE IA — obligatorio si el proyecto usa modelos. Los siete puntos:
     1. Esquema de salida obligatorio, ESCRITO LITERALMENTE en el PRD.
     2. Qué ocurre si el modelo falla, agota el tiempo o devuelve algo inválido:
        timeout, reintentos, tope y camino alternativo determinista.
     3. Presupuesto de tokens y costo POR OPERACIÓN, no solo mensual.
     4. Retención y borrado de los datos enviados al modelo. Si son datos de
        personas o de clientes: plazo explícito y borrado efectivo.
     5. Prompt de sistema como archivo versionado, no cadena embebida.
     6. Conjunto de evaluación: cantidad de casos, métrica POR CAMPO, línea base
        y umbral mínimo para aceptar un cambio de prompt.
     7. Modelo y versión exactos, con fecha de verificación de vigencia.

B9.  Plano arquitectónico (Gate 1.5) — referencia a MAPA_ARQUITECTURA.md con
     estructura de carpetas, matriz de responsabilidad única por archivo (qué
     hace y qué NO hace), diccionario de servicios, dirección permitida de
     dependencias, y contra qué ejemplar canónico se construye cada módulo.

B10. CONCURRENCIA E IDEMPOTENCIA.
     Por cada operación que toque stock, precios, dinero, cupos, agenda u
     horarios: ¿qué pasa si se ejecuta dos veces? ¿si dos usuarios la ejecutan
     en simultáneo?
     Buscá todo patrón "verificar y después escribir" (comprobar stock y luego
     descontar; comprobar horario libre y luego reservar; comprobar que no
     existe y luego crear) y declará su defensa: restricción en la base,
     SELECT FOR UPDATE, o SERIALIZABLE con reintento.
     Toda operación con efecto externo necesita clave de idempotencia con UNIQUE.
     Si el motor no es PostgreSQL, acá van las consecuencias de S1.

B11. Marca, políticas y desviaciones.
     - Perfil de marca: PORTAFOLIO (nombre real y enlace, cintillo de demo) o
       CLIENTE (firma genérica y anónima, sin elementos visibles). No mezclar
       los dos en un mismo repositorio.
     - JERARQUÍA: en el cómo manda PROTOCOLO.md; en el qué manda el PRD; la
       forma de un archivo la manda EJEMPLARES.md.
     - Las políticas se REFERENCIAN a PROTOCOLO.md y EJEMPLARES.md.
       ⚠ Si el PRD tiene una sección con las políticas SDD copiadas dentro,
       REEMPLAZALA por la referencia. Catorce copias del protocolo significan
       trece versiones desactualizadas que nadie va a revisar.
     - TABLA DE DESVIACIONES DECLARADAS: por cada regla del protocolo que este
       proyecto no cumpla, una fila con regla, desviación, motivo y condición
       para volver a la norma. Una desviación declarada es una decisión; una
       silenciosa es deuda.
```

---

## PARTE D — Auditoría de seguridad del PRD

```
[REVISAR Y COMPLETAR SI EL PROYECTO TIENE ALGUNA DE ESTAS SUPERFICIES]

D1. Subida de archivos. Debe declarar:
    - límite de tamaño
    - validación de tipo MIME real POR CONTENIDO, nunca por extensión
    - lista blanca de tipos permitidos
    - límite de páginas o dimensiones para PDF e imágenes
    - nombre de archivo saneado y almacenamiento FUERA del directorio público
    - qué se hace con el archivo después de procesarlo (retención)
    - idempotencia: si se sube dos veces el mismo archivo, qué pasa

D2. Autenticación: mecanismo, vida de los tokens, rotación, almacenamiento, y
    qué ocurre al cerrar sesión y al revocar.

D3. Autorización: por endpoint y por recurso. Si el sistema es multi-inquilino,
    declarar que TODA consulta filtra por tenant_id y que existe índice
    compuesto con tenant_id en primera posición.

D4. CORS, cabeceras de seguridad y límite de peticiones, con el origen
    permitido exacto. Nunca '*'.

D5. Secretos: ninguno en el PRD, ninguno en el código. Solo nombres de
    variables de entorno.
```

---

## PARTE E — Formato del changelog de salida

```
Al final del PRD corregido, agregar:

## Changelog de corrección SDD v3.2
| # | Bloque | Cambio aplicado | Motivo |
|---|--------|-----------------|--------|
| 1 | B4 | Criterios de aceptación Gherkin en RF-01..RF-08 | Sin criterio verificable no se puede derivar la prueba de aceptación |
| 2 | B11 | Políticas SDD embebidas reemplazadas por referencia a PROTOCOLO.md | Evita copias desactualizadas del protocolo |
| ... |

## Decisiones pendientes para el humano
1. <pregunta concreta>
```

---
---

# ANEXO — Correcciones específicas del PRD de OM Distribution (Proyecto 09) v2.0

Pasar junto con las partes A–E.

```
[CORRECCIONES ESPECÍFICAS — PRD OM DISTRIBUTION v2.0 -> v3.0]

C1. MOTOR: SE MANTIENE MySQL 8. NO MIGRAR.
    El proyecto ya está construido y es demostración de portafolio; cambiar el
    motor no aporta nada visible para un cliente. Agregar al encabezado:
    "Motor: MySQL 8 — impuesto por proyecto ya construido. No se migra."
    Declarar en B10 las dos consecuencias que aplican:
      - RF-05 (coincidencia difusa): sin pg_trgm no hay puntaje de similitud por
        trigramas. Se acepta la calidad actual y se declara como limitación
        conocida en el RNF de precisión. Si en el futuro no alcanza, la salida es
        un motor de búsqueda aparte, no migrar la base.
      - Migraciones: en MySQL no se pueden envolver varias sentencias DDL en una
        transacción revertible. Migraciones de una sentencia y snapshot previo.

C2. RNF-02 NO ES VERIFICABLE. Reescribir.
    Actual: "garantizando más del 90% de exactitud en la lectura".
    Reemplazar por un RNF medible que declare:
      - conjunto de evaluación: N órdenes reales anonimizadas (fotos de listas
        manuscritas, capturas de WhatsApp y PDFs), versionado en el repositorio
      - métrica POR CAMPO y no global: exactitud de SKU, de cantidad y de precio
        medidas por separado — un error de cantidad y uno de precio no cuestan
        lo mismo
      - línea base medida antes de optimizar
      - umbral mínimo para aceptar un cambio de prompt o de modelo
    Marcar N y los umbrales como DECISIÓN PENDIENTE — HUMANO.

C3. FALTA EL BLOQUE B8 (gobernanza de IA) COMPLETO. Agregarlo, y en particular:
    - RF-04 dice "retorna una estructura JSON". ESCRIBIR EL ESQUEMA LITERAL de
      salida (productos, sku_sugerido, nombre_leido, cantidad, precio_leido,
      confianza) y declarar que toda salida se valida contra ese esquema con Zod
      antes de tocar la base de datos.
    - Qué ocurre si Gemini falla, tarda demasiado o devuelve algo inválido:
      timeout, reintentos con corrección, tope, y camino alternativo (carga
      manual).
    - Presupuesto de tokens y costo por documento procesado.
    - RETENCIÓN DE ARCHIVOS: las órdenes subidas contienen datos comerciales de
      clientes de la distribuidora. Declarar plazo de conservación y borrado
      efectivo. Hoy el PRD no dice nada.
    - Prompt de extracción como archivo versionado, no incrustado en el código.
    - Verificar si "Gemini 1.5 Flash/Pro" sigue vigente a la fecha de revisión;
      si no, actualizar modelo y versión.

C4. FALTA EL BLOQUE B10 (concurrencia). Hay dos condiciones de carrera reales:
    - BR-02 "si la cantidad pedida supera el stock disponible" es un patrón
      verificar-y-después-escribir. Sin bloqueo ni restricción, dos órdenes
      simultáneas pasan la validación y dejan el stock en negativo.
      Defensa: SELECT ... FOR UPDATE sobre las filas de producto dentro de la
      transacción, más CHECK (stock >= 0) en la tabla. Ambos existen en InnoDB
      (CHECK desde 8.0.16): no hace falta migrar de motor para cerrar esto.
    - Subir dos veces el mismo archivo debe ser idempotente: clave única sobre
      el hash SHA-256 del archivo por cliente, para no crear dos órdenes iguales.

C5. FALTA EL BLOQUE B7 (contratos de API). Agregar contrato de, como mínimo:
    POST /api/ordenes/analizar · POST /api/ordenes/confirmar ·
    GET /api/productos/buscar · POST /api/catalogo/importar.

C6. RNF-01 (seguridad) ESTÁ INCOMPLETO. Tiene límite de 5 MB y CORS. Agregar
    todo el bloque D1: validación de MIME por contenido y no por extensión,
    lista blanca (PNG, JPEG, PDF), límite de páginas del PDF, nombre saneado,
    almacenamiento fuera del directorio público, y retención.

C7. CRITERIOS DE ACEPTACIÓN: agregar Gherkin a RF-01..RF-08. Nivel de concreción
    esperado:
      RF-06: Dado un producto con precio 12.50 en el catálogo, cuando la orden
             extraída trae precio 11.00 para ese SKU, entonces la fila se marca
             como discrepancia y el pedido NO se puede confirmar sin
             autorización explícita del administrador.
      RF-05: Dado el producto "Harina P.A.N. 1kg" con SKU HAR-001, cuando la IA
             extrae el texto "arina pan 1 kilo", entonces el sistema propone
             HAR-001 con puntaje de similitud >= al umbral configurado.

C8. AÑADIR PATRÓN STATE para el ciclo de vida del pedido. El PRD maneja pedidos
    pero no declara sus estados ni las transiciones válidas
    (subido -> extraído -> en revisión -> confirmado -> despachado / anulado).
    Sin eso, el estado se maneja con condicionales dispersos.

C9. BLOQUE B11: el PRD usa firma genérica anónima ("Desarrollador Freelance",
    "github.com/tuusuario-demo"). Es correcto, pero hay que DECLARARLO
    explícitamente como PERFIL CLIENTE para que no entre en conflicto con la
    firma de perfil PORTAFOLIO del protocolo.

C10. Corregir "This design debe ser auditado" -> "Este diseño debe ser auditado"
     (quedó mezclado en inglés).

C11. Reemplazar la sección "Políticas Generales de Desarrollo (Metodología SDD)"
     por una REFERENCIA a PROTOCOLO.md y EJEMPLARES.md, conservando solo lo
     específico del proyecto. Las políticas no se duplican dentro de cada PRD:
     se referencian, para que al actualizar el protocolo no queden catorce
     copias desactualizadas.
```
