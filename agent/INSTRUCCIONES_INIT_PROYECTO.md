# Inicialización de proyecto — instrucciones para AntiGravity

> **Qué es:** el runbook que se le pasa al agente al abrir un proyecto nuevo, para que quede con todos los documentos en su lugar antes de escribir una línea de código.
>
> **Cómo se usa:** pegá la **PARTE 1** en AntiGravity junto con los archivos de origen (`PROTOCOLO.md`, `EJEMPLARES.md`, `ejemplares/`, `PLANTILLA_PRD.md`). El agente hace el resto y para en cada gate.
>
> **Protocolo de referencia:** SDD v3.2

---

## Regla que gobierna todo este runbook

```
HAY DOS CLASES DE DOCUMENTO Y NO SE TRATAN IGUAL:

  COPIAR LITERAL  →  PROTOCOLO.md · EJEMPLARES.md · ejemplares/*
                     Son idénticos en los 14 proyectos. NO se generan,
                     NO se resumen, NO se adaptan, NO se "mejoran".
                     Se copian byte por byte y se les pone el sello de versión.

  GENERAR         →  docs/PRD.md · README.md del portafolio ·
                     MAPA_ARQUITECTURA.md · el stub de reglas
                     Son propios del proyecto. Se generan PREGUNTANDO.
```

Un agente que "adapta" el protocolo al proyecto produce catorce protocolos distintos, que es exactamente el problema que este sistema existe para evitar.

---

## PARTE 1 — Instrucción de inicialización (pegar esto)

```
TAREA: Inicializar la documentación de un proyecto nuevo bajo el Protocolo SDD v3.2.

NO escribas código de aplicación en esta tarea. Solo documentos.

────────────────────────────────────────────────────────────────────────
PASO 0 — PREGUNTAR. No avances sin estas respuestas.
────────────────────────────────────────────────────────────────────────
  0.1  Nombre del proyecto y qué problema resuelve, para quién.
  0.2  Combo de stack:  (a) Laravel 11 + Vue 3   (b) Express 5 + TS + React 19
       ¿Necesita servicio de IA en FastAPI?  Sí / No
  0.3  MOTOR DE BASE DE DATOS, y si fue ELEGIDO o IMPUESTO (y por quién:
       cliente / hosting / proyecto ya construido).
  0.4  Perfil de marca:  PORTAFOLIO (nombre real y enlace)  o  CLIENTE (anónimo).
  0.5  ¿Toca dinero, stock, cupos, agenda, datos personales o multi-inquilino?
       (Define el nivel de rigor y obliga el bloque 10 del PRD.)
  0.6  Tres cosas que este proyecto explícitamente NO va a hacer.
  0.7  ¿Convive con algún sistema o dato existente?
  0.8  ¿Qué archivo de instrucciones carga tu herramienta automáticamente?
       (AGENTS.md / CLAUDE.md / .cursor/rules/ / instrucciones de proyecto)

────────────────────────────────────────────────────────────────────────
PASO 1 — COPIAR LITERAL. Sin modificar nada.
────────────────────────────────────────────────────────────────────────
  1.1  Copiá PROTOCOLO.md a la raíz del repositorio, TAL CUAL.
       Única edición permitida: completar la fecha en la primera línea del
       encabezado ->  <!-- PROTOCOLO SDD — versión 3.2 — copiado el AAAA-MM-DD -->
       PROHIBIDO: resumirlo, reordenarlo, traducirlo, adaptarlo al proyecto,
       agregarle reglas o quitarle reglas.

  1.2  Copiá EJEMPLARES.md a la raíz, TAL CUAL, con su sello de fecha.

  1.3  Copiá la carpeta ejemplares/ completa. No borres los ejemplares de
       stacks que este proyecto no use: si mañana se agrega un servicio de IA,
       el ejemplar tiene que estar ahí.

  1.4  Actualizá en EJEMPLARES.md la columna Estado marcando qué ejemplares
       aplican a este proyecto. NO borres filas: marcá "no aplica".

────────────────────────────────────────────────────────────────────────
PASO 2 — STUB DE REGLAS (3 líneas). Nombre según la respuesta 0.8.
────────────────────────────────────────────────────────────────────────
  Creá el archivo con este contenido EXACTO y nada más:

      Este proyecto se rige por PROTOCOLO.md y por EJEMPLARES.md.
      Leelos completos antes de escribir código.
      El PRD del proyecto está en docs/PRD.md.

  NO dupliques reglas acá. El stub es un puntero, no una copia.

────────────────────────────────────────────────────────────────────────
PASO 3 — GENERAR docs/PRD.md + README.md del portafolio
────────────────────────────────────────────────────────────────────────
  3.1  Usá la plantilla completa: los once bloques, ninguno omitido.
  3.2  Llená el encabezado con las respuestas del Paso 0, incluido el motor de
       base de datos y si fue elegido o impuesto.
  3.3  Si el motor NO es PostgreSQL, completá el bloque 10 con las
       sustituciones de la tabla de consecuencias de la plantilla.
  3.4  Cada requerimiento funcional lleva su criterio de aceptación en Gherkin,
       con MÍNIMO dos escenarios: camino feliz y caso de error o límite.
       El "Entonces" debe ser observable desde fuera: código HTTP, fila en la
       base, elemento visible. Prohibido "el sistema funciona correctamente".
  3.5  Todo RNF con un número lleva: medido cómo, sobre qué conjunto, línea base
       y umbral. Si no se puede llenar, marcalo como decisión pendiente.
  3.6  Todo lo que no puedas determinar va así, y seguís:
           > **[DECISIÓN PENDIENTE — HUMANO]** <la pregunta concreta>
       Un PRD con quince pendientes marcados es utilizable. Uno con quince
       supuestos inventados es una trampa.
  3.7  El bloque 11 REFERENCIA a PROTOCOLO.md y EJEMPLARES.md. No copia reglas.
  3.8  Marcá la columna ESTADO de cada RF (construido / parcial / pendiente),
       verificada CONTRA EL CÓDIGO y no contra la intención: por cada RF, nombrá
       qué tabla o qué archivo lo prueba. Si no podés nombrar uno, es PENDIENTE.
  3.9  GENERÁ TAMBIÉN el README del portafolio, en
       6_Portafolio_Tecnico/<Proyecto>/README.md, usando PLANTILLA_README.md.
       Se DERIVA del PRD: su ficha técnica sale del encabezado del PRD y de las
       tablas reales. No lo escribas por separado.
       Reparto de contenido, sin excepción:
         - docs/PRD.md (en el repo)  = el qué construir. Fuente única.
         - README.md (en portafolio) = ficha de vitrina + puntero al repo.
       EXCEPCIÓN: si el proyecto todavía no tiene repositorio de código, NO
       generes README. El PRD se queda en la carpeta del portafolio.

  ══ PARO ══ Entregá los dos documentos y ESPERÁ aprobación. No avances al Paso 4.

────────────────────────────────────────────────────────────────────────
PASO 4 — GENERAR MAPA_ARQUITECTURA.md  (Gate 1.5)
────────────────────────────────────────────────────────────────────────
  Solo con el PRD aprobado. Debe contener:
  4.1  Estructura completa de carpetas y archivos, ajustada a la regla S2 del
       protocolo. Nada de carpetas por tipo en la raíz.
  4.2  MATRIZ DE RESPONSABILIDAD ÚNICA: una fila por archivo con dos columnas,
       "qué hace" y "qué NO hace". Si un archivo necesita dos líneas en la
       primera columna, está mal dividido: partilo.
  4.3  Diccionario de servicios y métodos principales, con su firma.
  4.4  Mapa de dependencias entre módulos, declarando la dirección permitida.
       Este mapa es el insumo de la barrera automatizada de dependencias.
  4.5  Por cada módulo, contra qué ejemplar canónico se va a construir.

  ══ PARO ══ Entregá el mapa junto con el prototipo de UI (Gate 2A) y ESPERÁ.
             Sin aprobación no se genera boilerplate de backend, base de datos
             ni integraciones externas.

────────────────────────────────────────────────────────────────────────
PASO 5 — PREFLIGHT. Entregá esta tabla completada.
────────────────────────────────────────────────────────────────────────
  Recorré el preflight de PLANTILLA_PRD.md (16 preguntas) y devolvelo con cada
  casilla marcada o con la razón de por qué no. Si alguna respuesta es "no",
  DECILO EXPLÍCITAMENTE: el proyecto no arranca hasta resolverla.

────────────────────────────────────────────────────────────────────────
ENTREGABLE FINAL DE ESTA TAREA
────────────────────────────────────────────────────────────────────────
  Árbol de archivos creados, la lista consolidada de decisiones pendientes, y
  el preflight completado. Nada de código de aplicación.
```

---

## PARTE 2 — Estructura resultante

```
mi-proyecto/
├── <AGENTS.md | CLAUDE.md | .cursor/rules/…>   ← stub de 3 líneas
├── PROTOCOLO.md                                ← COPIA literal, con sello
├── EJEMPLARES.md                               ← COPIA literal, con sello
├── ejemplares/                                 ← COPIA literal
│   ├── vue/  react/  laravel/  fastapi/  migracion/
├── MAPA_ARQUITECTURA.md                        ← generado (Gate 1.5)
├── docs/
│   └── PRD.md                                  ← generado (Gate 0)
└── src/                                        ← nada todavía
```

Cinco archivos y una carpeta. **Solo dos son propios del proyecto:** el PRD y el mapa arquitectónico.

---

## PARTE 3 — Cuando el protocolo se actualiza

No se puede referenciar `PROTOCOLO.md` desde fuera del repositorio: el agente lee el repo, no tu disco. **La copia es inevitable.** La salida no es evitarla, es volverla visible.

```
INSTRUCCIÓN DE ACTUALIZACIÓN DE PROTOCOLO

Contexto: el protocolo pasó de la versión X a la versión Y.

1. Leé el sello de la primera línea de PROTOCOLO.md en este repositorio.
2. Si coincide con la versión vigente, no hay nada que hacer. Decilo y terminá.
3. Si es anterior:
   a) Reemplazá PROTOCOLO.md por la versión nueva, LITERAL, con sello nuevo.
   b) Compará las reglas viejas con las nuevas y listá QUÉ CAMBIÓ.
   c) Por cada regla nueva o modificada, listá qué archivos EXISTENTES del
      proyecto quedan desalineados. NO los corrijas.
   d) Entregá esa lista para el backlog. Se decide qué se arregla y cuándo;
      no se refactoriza el proyecto entero de golpe.
```

Un proyecto congelado o entregado **no** debería cambiar de reglas: se queda con su sello viejo y eso es correcto.

---

## PARTE 4 — Cuando el repositorio no es tuyo

En un proyecto de cliente que no nació del esqueleto, el orden cambia:

```
1. NO copies ejemplares/ todavía. El agente imitaría dos formas distintas: la
   tuya y la del código existente. Peor que ninguna.

2. Elegí el módulo mejor hecho que ya exista en ese repositorio, o escribí uno
   vos, y convertilo en el ejemplar canónico DE ESE REPO.
   Lleva su bloque "QUÉ FIJA ESTE EJEMPLAR" y su "ALTERNATIVA DESCARTADA".

3. Recién entonces creá EJEMPLARES.md apuntando a los ejemplares de ese repo.

4. PROTOCOLO.md se copia igual, salvo las reglas que el proyecto no pueda
   cumplir. Esas se declaran como DESVIACIÓN en el bloque 11 del PRD, con su
   motivo. Una desviación declarada es una decisión; una silenciosa es deuda.
```

Sin ejemplar propio del repositorio, el agente va a imitar el código existente **incluidos sus vicios**. Es el escenario en que más rápido se degrada todo el método.
