# PLANTILLA PRD — SDD v3.2 (rev. 2)

> **Para qué sirve:** que ningún PRD nuevo nazca con huecos. Esta plantilla es preventiva; `INSTRUCCIONES_CORRECCION_PRD.md` es correctiva, para los PRDs viejos.
>
> **Cómo se usa:** normalmente no a mano — se le pasa a AntiGravity el runbook `INSTRUCCIONES_INIT_PROYECTO.md`, que la usa en el Paso 3. Si querés hacerlo suelto, pegá la **PARTE 0** de abajo junto con esta plantilla.
>
> Los comentarios `<!-- ... -->` son instrucciones para quien la llena: se borran en el PRD final.

---

## PARTE 0 — Instrucción de generación

```
TAREA: Generar DOS documentos a partir del material de origen (propuesta del
cliente, pliego, conversación, notas):

  A) docs/PRD.md              en el repositorio del código, usando esta plantilla
  B) <Proyecto>/README.md     en la carpeta del portafolio, usando PLANTILLA_README.md

El README NO se escribe por separado: SE DERIVA del PRD. Su ficha técnica sale
del encabezado del PRD y de las tablas que existen de verdad en la base. Si los
generás juntos no pueden divergir; si los escribís por separado, divergen.

ANTES DE ESCRIBIR NADA, hacé estas preguntas y esperá las respuestas:
  1. Nombre del proyecto, y qué problema real resuelve para quién.
  2. Combo de stack: (a) Laravel 11 + Vue 3   (b) Express 5 + TS + React 19
     ¿Requiere servicio de IA en FastAPI? Sí / No.
  3. MOTOR DE BASE DE DATOS, y si fue ELEGIDO o IMPUESTO (y por quién).
  4. Perfil de marca: PORTAFOLIO (nombre real y enlace) o CLIENTE (anónimo).
  5. ¿Toca dinero, stock, cupos, agenda, datos personales o multi-inquilino?
     (Define el nivel de rigor y obliga el bloque 10.)
  6. Tres cosas que este proyecto explícitamente NO va a hacer.
  7. ¿Hay un sistema o dato existente con el que deba convivir?

REGLAS DE REDACCIÓN:
  R1. Todo requerimiento funcional lleva criterio de aceptación en Gherkin.
      Sin excepción. Mínimo dos escenarios: camino feliz y caso de error.
  R2. Todo número en un RNF lleva método de medición. Prohibido "más del 90%",
      "rápido", "escalable", "buena UX" sin decir medido cómo y sobre qué.
  R3. El "Entonces" de cada escenario debe ser observable desde fuera:
      código HTTP, fila en la base, elemento visible en pantalla.
      Prohibido "el sistema funciona correctamente".
  R4. Si te falta un dato para completar un bloque obligatorio, NO lo inventes:
          > **[DECISIÓN PENDIENTE — HUMANO]** <pregunta concreta>
      Un PRD con quince pendientes marcados es utilizable;
      uno con quince supuestos inventados es una trampa.
  R5. NO copies el protocolo dentro del PRD. Referencialo (bloque 11).
  R6. Ningún secreto, credencial ni URL de producción en el PRD.
  R7. El README del portafolio es ficha de vitrina y puntero, nunca documentación
      técnica. Máximo una pantalla. Si crece, es que estás duplicando el PRD.
  R8. Si el proyecto todavía NO tiene repositorio de código, NO generes README:
      el PRD se queda en la carpeta del portafolio y es la fuente única ahí. El
      README aparece recién cuando el PRD se muda a un repo.

STACK: combo (a) o (b) · Redis · pnpm/composer/uv · TypeScript estricto ·
estructura modular por feature congelada.

MOTOR DE BASE DE DATOS = DATO DE ENTRADA, NO REGLA.
  - Si la decisión es propia (proyecto de portafolio): PostgreSQL 16 + pgvector.
  - Si viene impuesto por el cliente, por el hosting o por un proyecto ya
    construido (MySQL, MariaDB, SQL Server): es válido y NO requiere
    justificación. Requiere que se declaren sus consecuencias en el bloque 10.
  - Preguntá el motor. No lo asumas. No lo cambies.

PROHIBIDO sin aprobación escrita: Django, Celery, Next.js, Kafka, CQRS,
LangChain/CrewAI/LangGraph, Clean/Hexagonal completas, Redux por defecto,
Atomic Design completo.
```

### Consecuencias por motor — completar el bloque 10 con esto

Si el motor **no** es PostgreSQL, estas sustituciones se declaran en el PRD, no se descubren durante el desarrollo:

| Capacidad | PostgreSQL | MySQL / InnoDB | Sustitución obligatoria |
|---|---|---|---|
| Búsqueda difusa | `pg_trgm` + GIN, con puntaje y umbral | `FULLTEXT` sin similitud por trigramas; `SOUNDEX` malo en español | Aceptar menor calidad **declarada en el RNF**, o motor aparte (Meilisearch, Typesense) |
| JSON indexado | `JSONB` + GIN | Columnas `JSON` sin índice sobre el contenido | Columnas generadas para los campos consultados, e indexar esas |
| Vectores / RAG | `pgvector` + HNSW | Tipo `VECTOR` desde MySQL 9, pero `DISTANCE()` y el índice vectorial **requieren HeatWave / MySQL AI — no están en Community** | En MySQL Community autoalojado **RAG no es viable en la base**: el servicio FastAPI usa su propio PostgreSQL, separado |
| No solapamiento de rangos | `EXCLUDE USING gist` + `btree_gist` | **No existen restricciones de exclusión** | `SELECT ... FOR UPDATE` sobre la fila padre + `UNIQUE` sobre franja discretizada |
| *Write skew* | `SERIALIZABLE` (SSI): aborta con `40001` y se reintenta | `REPEATABLE READ` con *next-key locks*: `FOR UPDATE` bloquea también el hueco del fantasma | Bloqueo pesimista explícito; no confiar en el nivel por defecto |
| Índice en FK | **No se crea solo** | InnoDB lo crea automáticamente | Único punto donde MySQL sale mejor |
| DDL transaccional | Sí, varias sentencias revertibles | Atómico por sentencia; **no se envuelven varias DDL** | Migraciones de una sentencia, **snapshot previo obligatorio**, declarado en el Gate 2B |

<!-- Vigencia verificada en julio de 2026. Antes de comprometer capacidad vectorial sobre MySQL,
     confirmar edición y versión exactas del cliente: Community vs HeatWave decide si el
     proyecto es viable tal como está especificado. -->

**Lo que NO cambia con el motor:** el esqueleto congelado, las reglas de capas, los contratos, el gauntlet y las pruebas. **El motor cambia; la arquitectura no.** Lo que absorbe el cambio es la capa de repositorio.

---
---

# PRD — <NOMBRE DEL PROYECTO>

> **Versión:** 1.0
> **Fecha:** AAAA-MM-DD
> **Autor:** <según perfil de marca del bloque 11>
> **Estado:** Borrador | En revisión | Aprobado
> **Combo de stack:** <Laravel 11 + Vue 3 | Express 5 + TS + React 19> + <FastAPI si aplica> + Redis
> **Motor de base de datos:** <PostgreSQL 16 | MySQL 8 | MySQL 9 | MariaDB | otro> — **¿elegido o impuesto?** <propio | impuesto por: cliente / hosting / proyecto existente>
> **Perfil de marca:** PORTAFOLIO | CLIENTE
> **Nivel de rigor:** MÁXIMO | ALTO | MEDIO | BAJO <!-- matriz de reversibilidad -->
> **Protocolo del repositorio:** `PROTOCOLO.md` — sello v\_\_\_\_ copiado el \_\_\_\_-\_\_-\_\_ <!-- verificar que sea la versión vigente -->
> **Ejemplares:** `EJEMPLARES.md` + carpeta `ejemplares/`
> **Plano arquitectónico:** `MAPA_ARQUITECTURA.md` <!-- Gate 1.5 -->
> **Desviaciones del protocolo:** <ninguna | ver bloque 11.2>

---

## 1. Descripción y problema

<!-- El problema en términos del usuario, no de la solución. Quién sufre qué, hoy, y cuánto le cuesta. -->

**Problema que resuelve:**

**Diferenciadores clave:**
-

---

## 2. Alcance

### 2.1 Incluido
-

### 2.2 Fuera de alcance
<!-- EL BLOQUE MÁS VALIOSO DEL DOCUMENTO. Mínimo tres entradas.
     Es lo único que impide que un agente invente funcionalidad. -->
-
-
-

---

## 3. Actores y permisos

| Actor | Descripción | Puede | NO puede |
|---|---|---|---|
| | | | |

<!-- La columna "NO puede" es el insumo directo de las pruebas de autorización. -->

---

## 4. Requerimientos funcionales

<!-- Por cada RF: fila en la tabla + bloque Gherkin. Sin el Gherkin, el RF no está terminado. -->

| ID | Nombre | Descripción | Prioridad | Rigor | **Estado** |
|---|---|---|---|---|---|
| RF-01 | | | Alta | | CONSTRUIDO \| PARCIAL \| PENDIENTE |

<!-- La columna ESTADO es obligatoria y separa la especificación de la realidad.
     Un PRD sin ella hace que el agente asuma que existe código donde no hay, y
     hace que vos audites defensas de funcionalidad que nunca se implementó.
     Se verifica contra el código, no contra la intención: si el RF habla de una
     tabla, esa tabla tiene que existir para marcarlo CONSTRUIDO. -->


**Criterio de aceptación — RF-01**
```gherkin
Escenario: <camino feliz>
  Dado <estado inicial concreto, con datos reales>
  Cuando <acción concreta del actor>
  Entonces <resultado observable: código HTTP, fila en BD, elemento visible>

Escenario: <caso de error o límite>
  Dado ...
  Cuando ...
  Entonces ...
```

---

## 5. Requerimientos no funcionales

| ID | Categoría | Requisito | **Medido cómo** | **Sobre qué conjunto** | **Línea base** | **Umbral** |
|---|---|---|---|---|---|---|
| RNF-01 | | | | | | |

<!-- Si no podés llenar las cuatro últimas columnas, no es un RNF: es un deseo.
     Marcalo como DECISIÓN PENDIENTE en lugar de dejarlo vago. -->

---

## 6. Reglas de negocio

| ID | Regla | **Punto de aplicación** | Detalle |
|---|---|---|---|
| BR-01 | | Restricción en BD / Servicio / Interfaz | |

<!-- Toda regla que proteja dinero, stock, cupos o unicidad DEBE tener su defensa
     en la base de datos (UNIQUE, CHECK, EXCLUDE, FK), no solo en el servicio.
     Una validación en código se puede olvidar; una restricción del esquema no. -->

---

## 7. Contratos de API

<!-- Gate 2C. Uno por endpoint. Permite verificar sin leer el controlador. -->

### `MÉTODO /api/ruta`
| | |
|---|---|
| **Auth** | |
| **Autorización** | |
| **Petición** | ```json``` |
| **Respuesta 2xx** | ```json``` |
| **Errores** | `400` … · `403` … · `409` … · `422` … |
| **Entrada inválida** | |
| **Idempotente** | Sí / No — y cómo |

---

## 8. Gobernanza de IA

<!-- OBLIGATORIO si el proyecto usa modelos. Los siete puntos, sin saltar ninguno. -->

| # | Punto | Definición |
|---|---|---|
| 8.1 | **Modelo y versión exactos** | \<modelo\> · verificado el \<fecha\> |
| 8.2 | **Esquema de salida** | ver abajo — validado con Pydantic/Zod **antes** de tocar la base |
| 8.3 | **Si el modelo falla** | timeout · reintentos: N · tope · camino alternativo determinista: |
| 8.4 | **Presupuesto** | tokens y costo **por operación**, no solo mensual |
| 8.5 | **Retención de datos** | qué se envía al modelo · cuánto se conserva · borrado efectivo |
| 8.6 | **Prompt** | archivo versionado en `prompts/<nombre>.md`, nunca cadena embebida |
| 8.7 | **Conjunto de evaluación** | N casos · métrica **por campo** · línea base · umbral de aceptación |

**Esquema de salida obligatorio:**
```json

```

<!-- 8.5 no es opcional: si se envían documentos, fotos o datos de personas al modelo,
     el plazo de conservación y el borrado son requisito, no buena práctica. -->

---

## 9. Plano arquitectónico

<!-- Gate 1.5. Se entrega junto al prototipo de UI y bloquea el boilerplate. -->

Referencia: `MAPA_ARQUITECTURA.md`, que debe contener:
- Estructura completa de carpetas, ajustada al esqueleto congelado (regla S2 del protocolo).
- **Matriz de responsabilidad única:** una línea por archivo — qué hace y qué **no** hace. Si necesita dos líneas, está mal dividido.
- Diccionario de servicios y métodos principales con su firma.
- **Dirección permitida de dependencias entre módulos** — insumo de la barrera automatizada del gauntlet.
- Por cada módulo, **contra qué ejemplar canónico** se va a construir.

---

## 10. Concurrencia e idempotencia

<!-- OBLIGATORIO si el proyecto toca dinero, stock, cupos, agenda u horarios.
     Aquí vive el write skew: el bug que solo aparece en producción. -->

| Operación | ¿Dos veces? | ¿Dos usuarios a la vez? | Defensa |
|---|---|---|---|
| | | | Restricción en BD / `SELECT FOR UPDATE` / `SERIALIZABLE` + reintento / clave de idempotencia |

**Patrones verificar-y-después-escribir identificados:**
<!-- Buscá en todo el PRD: "si hay stock, descontar", "si el horario está libre, reservar",
     "si no existe, crear". Cada uno es una condición de carrera hasta que se declare su defensa. -->
-

**Efectos externos y su clave de idempotencia:**
<!-- Webhooks, cobros, envío de mensajes. Todos garantizan entrega "al menos una vez": van a duplicar. -->
-

**Consecuencias del motor elegido:**
<!-- Si no es PostgreSQL, copiar acá las filas aplicables de la tabla de consecuencias de la PARTE 0. -->
-

---

## 11. Marca y políticas

**Perfil de marca:** PORTAFOLIO | CLIENTE

| | PORTAFOLIO | CLIENTE |
|---|---|---|
| Firma en código | Nombre real + enlace al portafolio | Genérica y anónima |
| Elementos visibles | Cintillo de demo + footer de créditos | Ninguno |

<!-- No mezclar los dos perfiles en un mismo repositorio. -->

**Políticas de desarrollo:** este proyecto se rige por **`PROTOCOLO.md`** y **`EJEMPLARES.md`**, que están en la raíz del repositorio. **No se duplican aquí ni se resumen** — si se copian, en tres proyectos hay tres versiones distintas y ninguna es la vigente.

### 11.1 Jerarquía de autoridad

| Ámbito | Manda | Si hay conflicto |
|---|---|---|
| **El cómo** — proceso, estructura, capas, gates | `PROTOCOLO.md` | Si este PRD contradice una regla de proceso, **es un error de este PRD** |
| **El qué** — alcance, reglas de negocio, contratos | **Este documento** | El protocolo no opina sobre el producto |
| **La forma de un archivo** | `EJEMPLARES.md` | Ver reglas X del protocolo |

### 11.2 Desviaciones declaradas del protocolo

<!-- Una desviación declarada es una decisión. Una desviación silenciosa es deuda. -->

| # | Regla del protocolo | Desviación | Motivo | Condición para volver a la norma |
|---|---|---|---|---|
| 1 | A2 — motor por defecto PostgreSQL | Motor MySQL 8 | Impuesto por el cliente | Si el cliente migra su infraestructura |

### 11.3 Específico de este proyecto
-

---

## 12. Seguridad

| Superficie | Aplica | Definición |
|---|---|---|
| **Subida de archivos** | Sí/No | tamaño · **MIME por contenido, nunca por extensión** · lista blanca · límite de páginas/dimensiones · nombre saneado · almacenamiento **fuera del directorio público** · retención · idempotencia por hash |
| **Autenticación** | | mecanismo · vida del token · rotación · almacenamiento · cierre de sesión y revocación |
| **Autorización** | | por endpoint y por recurso. Si es multi-inquilino: **toda** consulta filtra por `tenant_id`, con índice compuesto que lo lleve primero |
| **CORS y cabeceras** | | origen permitido exacto · límite de peticiones |
| **Secretos** | | solo nombres de variables de entorno |

---

## 13. Decisiones pendientes

<!-- Todo lo que quedó marcado como [DECISIÓN PENDIENTE — HUMANO], consolidado.
     El PRD no pasa a "Aprobado" con pendientes que bloqueen un gate. -->

1.

---
---

# PREFLIGHT — 18 preguntas antes de escribir la primera línea de código

> Si alguna respuesta es "no", el PRD no está listo y **el proyecto no arranca**.
> Lo que falte acá lo va a rellenar el agente con supuestos que nadie aprobó.

| # | Pregunta | ✔ |
|---|---|---|
| 1 | ¿Se puede derivar **el conjunto completo** de pruebas de aceptación leyendo solo el PRD, sin preguntar nada? | |
| 2 | ¿Todos los RF tienen Gherkin con al menos dos escenarios? | |
| 3 | ¿Todos los RNF con número declaran cómo se miden y sobre qué conjunto? | |
| 4 | ¿"Fuera de alcance" tiene al menos tres entradas concretas? | |
| 5 | ¿Cada regla de negocio dice **dónde** se hace cumplir? | |
| 6 | ¿Las reglas sobre dinero, stock o unicidad tienen defensa **en la base de datos**? | |
| 7 | ¿Está el contrato de cada endpoint, con errores y entrada inválida? | |
| 8 | Si usa IA: ¿están los siete puntos del bloque 8, incluido **retención de datos**? | |
| 9 | Si usa IA: ¿está escrito el **esquema de salida literal**? | |
| 10 | ¿Está identificado cada patrón verificar-y-después-escribir, con su defensa? | |
| 11 | ¿Está declarado el motor de BD y si fue **elegido o impuesto**? Si no es PostgreSQL, ¿están declaradas las sustituciones? | |
| 12 | ¿Existe `MAPA_ARQUITECTURA.md` aprobado (Gate 1.5)? | |
| 13 | ¿Está declarado el perfil de marca? | |
| 14 | ¿Está declarado el nivel de rigor según la matriz de reversibilidad? | |
| 15 | ¿El sello de `PROTOCOLO.md` en este repo coincide con la **versión vigente**? | |
| 16 | ¿Existe **ejemplar canónico** para cada tipo de archivo que este proyecto va a necesitar? Si falta uno, ¿está anotado? | |
| 17 | ¿Toda desviación del protocolo está **declarada** en 11.2, con motivo y condición de retorno? | |
| 18 | ¿Cada RF tiene su columna **Estado** verificada contra el código real, y no contra la intención? | |

---
---

# Cómo se crea todo esto en un proyecto nuevo

No se escribe a mano. Se le pasa a AntiGravity el archivo **`INSTRUCCIONES_INIT_PROYECTO.md`**, que lo guía en cinco pasos y para en cada gate.

**La regla que gobierna ese runbook:**

| | Documentos | Trato |
|---|---|---|
| **COPIAR LITERAL** | `PROTOCOLO.md` · `EJEMPLARES.md` · `ejemplares/` | Idénticos en los 14 proyectos. **No se generan, no se resumen, no se adaptan.** Solo se les completa el sello de fecha. |
| **GENERAR** | `docs/PRD.md` · `MAPA_ARQUITECTURA.md` · el stub de reglas | Propios del proyecto. Se generan **preguntando**. |

Un agente que "adapta" el protocolo al proyecto produce catorce protocolos distintos — que es el problema que este sistema existe para evitar.

**Estructura resultante:**

```
mi-proyecto/
├── <AGENTS.md | CLAUDE.md | …>   ← stub de 3 líneas, solo puntero
├── PROTOCOLO.md                  ← copia literal, con sello de versión
├── EJEMPLARES.md                 ← copia literal, con sello
├── ejemplares/                   ← copia literal
├── MAPA_ARQUITECTURA.md          ← generado (Gate 1.5)
├── docs/PRD.md                   ← generado (Gate 0), este documento
└── src/
```

**Solo dos archivos son propios del proyecto:** este PRD y el mapa arquitectónico. Todo lo demás es idéntico en el portafolio, y su sello de versión es lo que hace visible cuál copia quedó atrás.

---

## Mecanismo de mejora continua

Cada hueco que encuentres en un PRD viejo es **un bloque que debería ser obligatorio en esta plantilla**. Cuando aparezca uno que la plantilla no cubre, se agrega acá y se sube la versión.

Es el mismo mecanismo del *backlog de conocimiento pendiente*, aplicado a los documentos: **los PRDs viejos son una auditoría gratuita del protocolo actual.**

**Historial:**
- **rev. 1** — versión inicial. Los bloques 8 (gobernanza de IA), 10 (concurrencia) y las columnas de medición del bloque 5 salieron de los huecos detectados en el PRD de OM Distribution v2.0.
- **rev. 2** — sello de versión del protocolo en el encabezado; bloque 11 reescrito con jerarquía de autoridad y desviaciones declaradas; el motor de BD pasa a dato de entrada con su tabla de consecuencias; preflight de 14 a 18 preguntas; apéndice de inicialización.
- **rev. 3** — columna **Estado** obligatoria en los requerimientos funcionales, y pregunta 18 del preflight. Salió de detectar que el PRD de OM Distribution especificaba un módulo de órdenes y descuento de stock que el código no tiene: el backend solo maneja catálogo, contactos y autenticación.
