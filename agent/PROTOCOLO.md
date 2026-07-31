<!-- ================================================================== -->
<!-- PROTOCOLO SDD — versión 3.2 — copiado el ____-__-__                -->
<!-- ORIGEN CANÓNICO: no editar esta copia. Ver sección 0.3.            -->
<!-- ================================================================== -->

# PROTOCOLO SDD v3.2 — Reglas para agentes de código

> **Autor:** Rafael Marín · **Aplica a:** AntiGravity, Claude, Codex, Cursor y cualquier asistente de código.
> **Este archivo se lee completo antes de escribir código.** Es el *cómo*. El *qué* está en `docs/PRD.md`.
> **Documentos completos:** Plan de Implementación SDD v3.2 · Manual de Patrones y Estructuras v1.1.

---

## 0. Cómo funciona este documento

### 0.1 Jerarquía de autoridad

| Ámbito | Manda | Si hay conflicto |
|---|---|---|
| **El cómo** — proceso, estructura, capas, gates | **Este archivo** | Si el PRD contradice una regla de proceso, es un error del PRD |
| **El qué** — alcance, reglas de negocio, contratos | **`docs/PRD.md`** | Este archivo no opina sobre el producto |
| **La forma de un archivo** | **`EJEMPLARES.md`** y los archivos en `ejemplares/` | Ver reglas X |
| **El estilo del código** | Sección 10, reglas `C` | Las marcadas [auto] las verifica el linter |

**Excepción legítima:** el PRD puede desviarse de este protocolo **si lo declara explícitamente con su motivo** (ej.: *«motor MySQL impuesto por el cliente»*). Una desviación declarada es una decisión. Una desviación silenciosa es deuda.

### 0.2 Regla de paro general

```
Si para avanzar necesitás inventar un dato que no está en el PRD, en este
archivo ni en los ejemplares: PARÁ y preguntá. No lo inventes.
```

### 0.3 Este archivo es una copia

Es idéntico en todos los proyectos del portafolio. **No lo edites nunca desde un proyecto.** Si una regla está mal, decilo y esperá: se corrige en el origen canónico y se recopia. Editarlo acá crea una versión divergente invisible.

---

## 1. Arquitectura — reglas `A`

```
A1. Combos de stack aprobados, sin cruzarlos:
      (a) Laravel 11 + Vue 3 + Inertia
      (b) Express 5 + TypeScript + React 19
      (c) FastAPI — SOLO para servicios de IA
    Cache y colas: Redis. Colas y jobs asíncronos: Laravel Horizon.
    NO mezclar combos: nada de Laravel+React ni Express+Vue.

A2. MOTOR DE BASE DE DATOS = DATO DE ENTRADA, NO REGLA.
    Está declarado en el encabezado del PRD, junto con si fue elegido o impuesto.
    - Si es propio: PostgreSQL 16 + pgvector.
    - Si viene impuesto (cliente, hosting, proyecto construido): es válido y no
      requiere justificación. Sus consecuencias están en el bloque 10 del PRD.
    NUNCA cambies el motor por tu cuenta.

A3. FastAPI es un servicio delgado de IA: sin ORM, sin auth propia (red interna
    + secreto compartido), sin plantillas, sin panel. Si una tarea necesita
    lógica de negocio, va en Laravel o en Express.

A4. PROHIBIDO sin aprobación humana escrita:
      Django · Celery · Next.js · Kafka · CQRS · event sourcing ·
      LangChain / CrewAI / LangGraph · bases vectoriales externas ·
      Redux por defecto · Atomic Design completo ·
      Clean Architecture / Hexagonal completas.

A5. Tipado estricto obligatorio:
      Node/TS  -> "strict": true
      PHP      -> declare(strict_types=1) + PHPStan nivel 6 o superior
      Python   -> type hints + mypy

A6. Sugerí paquetes DE UNO EN UNO, con justificación técnica: peso, estado de
    mantenimiento, última publicación, y la alternativa que descartaste.
    Nunca instales nada. Nunca edites lockfiles.
```

---

## 2. Estructura congelada — reglas `S`

```
S1. La estructura del repositorio está CONGELADA. Adaptate a ella.
    NUNCA propongas otra distribución de carpetas, ni siquiera si la considerás
    mejor. Si crees que hace falta un cambio estructural, preguntá y esperá.

S2. Express:  src/modules/<feature>/{<f>.routes.ts, <f>.controller.ts,
                                    <f>.service.ts, <f>.repo.ts, <f>.schema.ts}
              + src/shared/{middleware,db,config,lib}
    FastAPI:  app/modules/<feature>/{router.py, service.py, schemas.py}
              + app/shared/{settings,security,logging,errors}.py
    Laravel:  convención del framework + app/Actions (una clase por operación)

S3. PROHIBIDO crear carpetas controllers/ services/ models/ en la raíz.
    Feature-first, siempre. Prohibido crear utils/ o helpers/ genéricos.

S4. REGLAS DE CAPAS — se verifican automáticamente, no son opinables:
    - El controlador NO accede a la base de datos. Máximo ~20 líneas por método.
    - El servicio NO recibe ni devuelve req/res. No importa el framework HTTP.
    - Solo los repositorios/modelos tocan la base de datos.
    - Ningún dato llega a un servicio sin pasar por su esquema de validación.
    - Un módulo NO importa los internos de otro. Lo compartido va a shared/.
    - shared/ NO depende de modules/.

S5. ERRORES: lanzá las clases de error del proyecto (NotFoundError,
    ConflictError, ...). NUNCA uses res.status() ni equivalentes dentro de un
    servicio. La traducción a HTTP ocurre en un único manejador central.

S6. NO agregues envoltorios de promesas tipo asyncHandler: Express 5 reenvía las
    rechazadas al manejador de errores automáticamente. Es código muerto.

S7. NO uses console.log: usá el logger del proyecto.
    NO importes variables de entorno fuera del archivo de configuración
    validado. Si necesitás una variable nueva, agregala a ese esquema y al
    archivo de ejemplo.
```

---

## 3. Gateways — reglas `G`

```
G0.   PRD. El proyecto arranca del PRD aprobado en docs/PRD.md. Si un requisito
      no está ahí, no existe.

G1.   SETUP. Sugerís estructura y comandos; los ejecuta el humano.
      Gestores: pnpm (Node) · composer (PHP) · uv o pip (Python). Uno por proyecto.

G1.5  PLANO ARQUITECTÓNICO — APROBACIÓN OBLIGATORIA.
      Entregá MAPA_ARQUITECTURA.md junto con el prototipo de UI:
        - estructura completa de carpetas, ajustada a S2
        - matriz de responsabilidad única: una línea por archivo, qué hace y
          qué NO hace. Si necesita dos líneas, está mal dividido.
        - diccionario de servicios y métodos principales con su firma
        - mapa de dependencias entre módulos con la dirección permitida
      PARO: sin aprobación no se genera boilerplate de backend, base de datos
      ni integraciones externas.

G2A   PROTOTIPO UI/UX — APROBACIÓN OBLIGATORIA.
      Wireframe en Markdown, árbol de componentes, jerarquía visual, y los
      CUATRO ESTADOS OBLIGATORIOS declarados: cargando, error (con reintento),
      vacío, sin permisos.
      PARO: sin aprobación explícita NO escribas código de interfaz.

G2B   ESQUEMA DE BASE DE DATOS — APROBACIÓN OBLIGATORIA.
      Presentá: tablas, columnas, tipos, PK/FK, restricciones, relaciones ERD,
      e índices con la consulta que cada uno atiende.
      OBLIGATORIO ADEMÁS: el SQL CRUDO que ejecutará la migración
        Prisma   -> el archivo prisma/migrations/<fecha>/migration.sql
        Laravel  -> php artisan migrate --pretend
        Django   -> python manage.py sqlmigrate
      OBLIGATORIA LA DECLARACIÓN DE RIESGO: reescrituras completas de tabla,
      índices creados sin CONCURRENTLY, backfills sin lotes, operaciones
      destructivas, impacto estimado de memoria y bloqueo, y plan de reversa.
      Recordá: PostgreSQL no indexa claves foráneas automáticamente.
      PARO: sin aprobar esquema Y SQL, no generes modelos ORM, migraciones ni
      controladores.

G2C   CONTRATOS DE API — APROBACIÓN OBLIGATORIA.
      Por endpoint: método, ruta, cuerpo de petición y respuesta, códigos de
      estado, forma exacta del objeto de error, comportamiento ante entrada
      inválida/ausente/maliciosa, y requisitos de autenticación y autorización
      por recurso.
      PARO: sin contrato aprobado no implementes el endpoint.

G3    IMPLEMENTACIÓN. Con 1.5, 2A, 2B y 2C aprobados, generás el código.
      Estás autorizado a autogenerar el boilerplate de seguridad (CORS, cabeceras,
      límite de peticiones, manejador de errores global), marcándolo para
      inspección. UN MÓDULO DE FEATURE POR ENTREGA. No cinco.

G3.5  EL GAUNTLET. Antes de decir que terminaste:
      1. Corré la verificación completa del proyecto y pegá el resultado
         (typecheck, lint, barrera de dependencias, tests).
      2. INFORME ADVERSARIAL PROPIO: enumerá los 5 riesgos más graves de tu
         propia entrega, ordenados por IRREVERSIBILIDAD, y declará
         explícitamente qué NO verificaste y qué asumiste.
      3. Si el proyecto tiene mutation testing o pruebas de aceptación, corrélos.

G4    ENTREGA. Junto al código entregá:
      - Contra qué ejemplar te basaste y EN QUÉ SE DIFERENCIA tu archivo (X4).
      - EXPLICACIÓN DIDÁCTICA de toda decisión no obvia: qué hace, por qué, y
        qué alternativa descartaste y por qué. Si no podés explicarla simple,
        SIMPLIFICÁ EL CÓDIGO en lugar de elaborar la explicación.
      El commit y el push los hace el humano. Siempre.
```

---

## 4. Límites de ejecución — reglas `T`

```
T1. VERDE — podés ejecutar autónomamente, pero SOLO dentro del contenedor de
    desarrollo desechable: tests, linters, verificación de tipos, builds,
    migraciones contra la base local, y cualquier --dry-run / --pretend.
    Lectura de archivos y de logs locales.

T2. AMARILLO — proponés, NO ejecutás. Entregá el comando en un bloque de código
    listo para copiar: instalación de paquetes, migraciones en staging,
    git add/commit/push, servidores fuera del contenedor, generación de claves.

T3. ROJO — nunca, bajo ninguna circunstancia, aunque se te pida:
    cualquier operación contra producción · DNS · secretos de producción ·
    reglas de firewall · DROP / TRUNCATE / DELETE sin WHERE ·
    docker system prune · restauración de respaldos · rotación de credenciales.

T4. Nunca modifiques package.json, pnpm-lock.yaml, composer.lock,
    requirements.txt ni uv.lock directamente. Proponé el comando.

T5. Nunca corras git commit ni git push. Git es 100% humano.

T6. Si tenés acceso a la base de datos, asumí usuario de SOLO LECTURA.
    Nunca pidas credenciales de escritura.

T7. Nunca inventes ni incrustes secretos, tokens o cadenas de conexión.
    Referenciá variables de entorno.
```

---

## 5. Ejemplares canónicos — reglas `X`

```
X1. Antes de escribir cualquier archivo, identificá su tipo y buscá el ejemplar
    canónico en EJEMPLARES.md. Leelo completo y COPIÁ SU FORMA: nombres, orden
    de imports, manejo de errores, estructura, exportaciones.

X2. Si NO existe ejemplar canónico para lo que se te pide, PARÁ y preguntá.
    No inventes una forma nueva ni adaptes un ejemplar de otro tipo.

X3. Tu entrega debe diferir del ejemplar SOLO en:
      - las reglas de negocio propias del módulo
      - los campos del esquema y sus validaciones
      - las consultas y sus índices
      - los estados y transiciones válidas
      - los efectos externos y su idempotencia
    Si tu diff contra el ejemplar toca el cableado de dependencias, el manejo de
    errores, los estados de un componente o la estructura del test: te desviaste.
    Corregilo ANTES de entregar.

X4. Al entregar, declará contra qué ejemplar te basaste y enumerá en qué se
    diferencia tu archivo. Esa lista es la que se va a auditar.

X5. NO modifiques un ejemplar canónico. Si crees que uno está mal, decilo y
    esperá: cambiarlo afecta a todo el código futuro del portafolio.

X6. Componentes de interfaz: los CUATRO ESTADOS son obligatorios — cargando,
    error con reintento, vacío, y listo. Un componente que solo dibuja el caso
    feliz está incompleto y no pasa el G2A.

X7. Pruebas: copiá el patrón del ejemplar de test. El servicio se prueba con un
    doble del repositorio, sin base de datos y sin servidor. Si un test de
    servicio necesita levantar el framework o la base, rompiste una regla de S4.
```

---

## 6. Concurrencia y datos — reglas `D`

```
D1. Buscá y declará todo patrón VERIFICAR-Y-DESPUÉS-ESCRIBIR: comprobar si hay
    stock y luego descontar; comprobar si el horario está libre y luego reservar;
    comprobar si no existe y luego crear. Cada uno es una condición de carrera
    hasta que declares su defensa.

D2. La defensa de una regla que protege dinero, stock, cupos o unicidad va EN LA
    BASE DE DATOS: UNIQUE, CHECK, EXCLUDE, FK. Una validación que vive solo en el
    código se puede olvidar; una restricción del esquema no.
    La comprobación previa en el servicio existe solo para dar un mensaje claro.

D3. Toda operación con efecto externo (cobros, webhooks, envío de mensajes)
    necesita CLAVE DE IDEMPOTENCIA con restricción UNIQUE. Los webhooks
    garantizan entrega al menos una vez: van a duplicar.

D4. NUNCA hagas una llamada de red (LLM, pasarela de pago, API externa) dentro
    de una transacción abierta. Mantiene filas bloqueadas esperando la red,
    agota el pool de conexiones y tumba la aplicación. La transacción se confirma
    primero; el efecto externo se despacha después (afterCommit o cola).

D5. Recorré resultados grandes con iteradores o cursores, nunca cargando todo
    en memoria.

D6. Toda columna de dinero: decimal con precisión explícita. NUNCA float.
```

---

## 7. Gobernanza de IA — reglas `I`

Aplican solo si el proyecto usa modelos. Los siete puntos están definidos en el bloque 8 del PRD.

```
I1. Toda salida de un modelo se valida contra un ESQUEMA declarado (Pydantic /
    Zod) antes de tocar la base de datos o llegar al cliente. Exigirle el
    esquema al modelo y validar la respuesta son DOS defensas distintas: se
    aplican las dos.

I2. El prompt de sistema es un ARCHIVO VERSIONADO en el repositorio, nunca una
    cadena embebida en el código.

I3. Toda llamada a un modelo lleva: timeout explícito, tope de reintentos, y un
    camino alternativo determinista cuando se agotan. El reintento se hace CON
    CORRECCIÓN: devolvele el error de validación concreto.

I4. Tope de tokens y de costo POR OPERACIÓN, no solo mensual. Al alcanzarlo se
    degrada o se avisa; nunca se sigue en silencio.

I5. Tope duro de iteraciones y de tiempo en cualquier bucle de agente.
    Un agente sin techo de iteraciones es una factura sin techo.

I6. Cada herramienta expuesta a un modelo es superficie de ataque: lista blanca
    explícita, validación de argumentos, permisos mínimos.

I7. Registrá de cada llamada: versión del prompt, modelo, tokens de entrada y
    salida, latencia, costo y salida cruda. Sin eso no se puede depurar nada.

I8. No cambies un prompt de producción sin correr el conjunto de evaluación.
    Un prompt es código: tiene versión, tiene pruebas y entra por el G4.
```

---

## 8. Soporte de auditoría — reglas `Q`

Respondé estas preguntas cuando se te pidan, de forma concisa y sin adornos.

```
Q1. Listar todos los endpoints con método, ruta, servicio que invocan y si
    exigen autenticación.
Q2. Trazar una petición de punta a punta, archivo por archivo, en orden de
    ejecución.
Q3. Listar qué controladores y servicios tocan una tabla dada.
Q4. Listar las operaciones del diff actual que NO se pueden deshacer.
Q5. Listar los bucles que ejecutan una consulta a la base adentro (N+1).
Q6. Explicar cualquier archivo como si el lector nunca lo hubiera visto,
    sin jerga.
Q7. NUNCA respondas una pregunta de auditoría con una suposición.
    Si no lo verificaste, decilo.
```

---

## 9. Convenciones de salida — reglas `O`

```
O1. Todo archivo fuente principal empieza con el bloque de firma de autoría
    declarado en el bloque 11 del PRD (perfil PORTAFOLIO o CLIENTE).

O2. Perfil PORTAFOLIO: la interfaz de demostración incluye el cintillo o el
    footer de créditos, con todo enlace externo como
    target="_blank" rel="noopener noreferrer".
    Perfil CLIENTE: ningún elemento de marca, firma genérica y anónima.

O3. Diffs chicos y acotados a un solo módulo de feature.

O4. Los comandos de terminal siempre en bloques de código limpios, listos para
    copiar, un propósito por bloque.

O5. Escribí el código y los comentarios en español, salvo los identificadores
    del lenguaje y los términos técnicos que no tienen traducción establecida.
```

---

## 10. Reglas de código — reglas `C`

Aplican a todo el código, en todos los stacks. Las marcadas **[auto]** las verifica el linter y fallan el pipeline: no dependen de que nadie las recuerde.

### Nombres

```
C1.  Identificadores en INGLÉS. Comentarios y documentación en español.
     Un solo idioma por capa: nada de `usuariosDesactivados` conviviendo con
     `products` en el mismo esquema.

C2.  PascalCase   -> clases, interfaces, tipos, enums, componentes de UI
     camelCase    -> funciones, variables, métodos, propiedades          [auto]
     UPPER_SNAKE  -> constantes de módulo
     Archivos de código: minúsculas con el rol al final (users.service.ts).
     Archivos de componente: PascalCase (UserList.vue, UserList.tsx).

C3.  BASE DE DATOS: snake_case, tablas en plural -> `usuarios_desactivados`.
     El ORM hace el mapeo a camelCase en el código (@map / @@map en Prisma).
     MOTIVO TÉCNICO, no estético: PostgreSQL pliega a minúsculas todo
     identificador no citado, así que camelCase obliga a citar "asi" en cada
     consulta para siempre. En MySQL la sensibilidad depende del sistema de
     archivos: el mismo esquema se comporta distinto en Windows y en Linux.
     PROHIBIDO usar identificadores citados para conservar mayúsculas.
```

### Tipos

```
C4.  PROHIBIDO `any`. Si el tipo no se conoce, `unknown` y se estrecha.      [auto]
     PHP: declare(strict_types=1) + PHPStan 6+. Python: type hints + mypy.

C5.  Los tipos se DERIVAN del esquema de validación (`z.infer`, Pydantic),
     nunca se escriben dos veces. La misma información duplicada se
     desincroniza y el tipo termina mintiendo.

C6.  NO agregues `'use strict'`. Los módulos ES ya son estrictos por
     especificación: es ruido. Lo que manda es "strict": true en tsconfig,
     con noUncheckedIndexedAccess y exactOptionalPropertyTypes.
```

### Control de flujo

```
C7.  Cláusulas de guarda con retorno temprano en lugar de `if` anidados.
     Máximo 3 niveles de anidamiento.                                       [auto]

C8.  Ternario SOLO para asignar un valor en una línea.
     PROHIBIDO anidarlo. PROHIBIDO con efectos secundarios.                 [auto]
     Un ternario anidado es peor que el `if` que reemplazó.

C9.  PROHIBIDO números y cadenas mágicas. Constante con nombre.             [auto]
     `if (estado === 3)` es indefendible en un Gate 4.

C10. async/await siempre. PROHIBIDO `.then()` encadenado.                    [auto]
     PROHIBIDO `await` dentro de un `for` cuando se puede paralelizar.       [auto]
     Y PROHIBIDO `Promise.all` sin límite de concurrencia sobre colecciones
     grandes: mil promesas simultáneas agotan el pool de conexiones.
```

### Errores

```
C11. Se lanzan las clases de error del proyecto (NotFoundError, ConflictError).
     PROHIBIDO `catch` vacío o que solo registre y siga.                     [auto]
     Se captura por TRES motivos: traducir el error, agregarle contexto, o
     reintentar. Si no hacés ninguno, no captures: dejá que suba.

C12. UN SOLO lugar traduce un error a respuesta HTTP. Ningún servicio responde.
```

### Datos y seguridad

```
C13. JAMÁS concatenar entrada de usuario en una consulta. Ni una vez, ni
     "porque es un número". Consultas parametrizadas o el ORM, siempre.
     ATENCIÓN: la validación NO previene la inyección SQL. Un campo puede
     pasar toda la validación y aun así inyectar si concatenás cadenas.

C14. TRES capas de validación con roles DISTINTOS, y no son intercambiables:
       frontend -> experiencia de usuario. NUNCA es seguridad: se salta
                   con curl en dos segundos.
       backend  -> LA DEFENSA REAL. Esquema en la frontera, siempre.
       base     -> UNIQUE, CHECK, NOT NULL, FK. Lo que el código puede
                   olvidar, el esquema no.

C15. Fechas en UTC con zona horaria en la base (timestamptz). La conversión a
     hora local ocurre SOLO al presentar. Nunca se guarda hora local.

C16. Dinero en decimal con precisión explícita. NUNCA float.
     0.1 + 0.2 !== 0.3, y en un cobro eso es un descuadre real.

C17. Ni datos personales ni secretos en los logs. Lista de campos censurados
     configurada en el logger, y revisada cuando se agrega un campo sensible.
```

### Organización

```
C18. DATOS CONSTANTES en archivo aparte y se importan: catálogos fijos, listas
     de opciones, mapas de traducción, tablas de tasas. Nunca embebidos en el
     archivo que los recorre.
     Los datos de TIEMPO DE EJECUCIÓN (resultados de consultas, respuestas de
     API) se recorren en el SERVICIO — nunca en el controlador ni en el
     componente.

C19. CERO lógica en JSX ni en plantillas Vue. Si hay un cálculo en el marcado,
     sube a una variable arriba, a un composable o a un hook.

C20. PROHIBIDAS las rutas relativas de más de dos niveles (`../../../`).     [auto]
     Alias de path. Tres niveles arriba significa que el archivo está en el
     lugar equivocado.

C21. Exports NOMBRADOS. Sin `export default` en servicios, repositorios ni     [auto]
     utilidades: se renombran y se autocompletan mejor, y `default` invita a
     que cada archivo lo importe con otro nombre.

C22. Un archivo, una responsabilidad. Prueba práctica: si el nombre del
     archivo necesita una "y", está mal partido.

C23. `TODO` con dueño y fecha, o no va. Un TODO anónimo es basura permanente.
```

---

## 11. Lista de comprobación antes de entregar

Recorré esto y pegá el resultado:

- [ ] ¿Atravesé los gates que correspondían, con aprobación explícita en cada uno?
- [ ] ¿Respeté la estructura congelada, sin carpetas por tipo en la raíz?
- [ ] ¿Ningún controlador consulta la base ni pasa de ~20 líneas?
- [ ] ¿Ningún servicio recibe o devuelve objetos de petición/respuesta?
- [ ] ¿Copié la forma del ejemplar canónico y mi diff solo toca lo permitido por X3?
- [ ] ¿Declaré contra qué ejemplar me basé y en qué me diferencio?
- [ ] ¿Presenté el SQL crudo con su declaración de riesgo, si hubo cambios de esquema?
- [ ] ¿Identifiqué todo patrón verificar-y-después-escribir y declaré su defensa?
- [ ] ¿Ninguna llamada de red quedó dentro de una transacción?
- [ ] ¿Corrí la verificación del proyecto y pegué el resultado?
- [ ] ¿Entregué el informe adversarial de los 5 riesgos por irreversibilidad?
- [ ] ¿Entregué la explicación didáctica de cada decisión no obvia?
- [ ] ¿Dejé los comandos de nivel Amarillo para que los ejecute el humano?
- [ ] ¿Cumplí las reglas `C`? En particular: sin `any`, sin ternarios anidados, sin números mágicos, sin `catch` vacío, sin concatenar entrada en consultas, y tablas en `snake_case`.
