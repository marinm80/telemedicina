# Brief de arranque — plantilla

> **Qué es:** el mensaje corto que se le pasa al agente al empezar a trabajar en un proyecto.
> **Qué NO es:** una copia de las reglas. Esas están en `PROTOCOLO.md` y `EJEMPLARES.md`.

---

## La regla que gobierna este archivo

> **Repetí solo las restricciones específicas del proyecto que el agente tendería a violar.
> Nunca repitas las reglas generales de proceso.**

Las reglas de proceso —gates, límites de terminal, un módulo por entrega, formato de entrega— están completas en `PROTOCOLO.md`. Repetirlas acá crea una segunda fuente que se desactualiza en silencio el día que cambie el protocolo.

Pero hay una tensión real que conviene reconocer: **un modelo pesa más lo que le acabás de decir que un archivo que le pediste leer.** Por eso sí van, con énfasis, las desviaciones declaradas del proyecto: no están en el protocolo (están en el bloque 11.2 del PRD) y son justo las que un agente "corrige" por iniciativa propia.

---

## VARIANTE A — Proyecto nuevo, desde el esqueleto

```
Leé completos: agent/PROTOCOLO.md, agent/EJEMPLARES.md, docs/PRD.md.
Ahí están todas las reglas de trabajo. No las repito acá.

ESTADO: proyecto nuevo. El código base viene de templates/backend_ex, que YA
cumple la estructura congelada y las reglas de capas. El ejemplar canónico de
módulo backend es src/modules/users/ — copiá SU forma.

Las decisiones pendientes de la sección 13 del PRD las resuelvo yo.
No las rellenes con supuestos.

PLAN, un paso por vez, parás al final de cada uno:
  1. Gate 1.5 — MAPA_ARQUITECTURA.md: estructura de carpetas, matriz de
     responsabilidad única por archivo, diccionario de servicios, dirección de
     dependencias, y contra qué ejemplar se construye cada módulo.
     Entregalo junto con el prototipo de UI (Gate 2A). NO generes boilerplate.
  2. Gate 2B — esquema de base de datos + SQL crudo + declaración de riesgo.
  3. Gate 2C — contratos de los endpoints del primer módulo.
  4. Primer módulo de feature, copiando la forma de src/modules/users/.

Arrancá con el paso 1.
```

---

## VARIANTE B — Proyecto que ya existe

```
Leé completos: agent/PROTOCOLO.md, agent/EJEMPLARES.md, docs/PRD.md.
Ahí están todas las reglas de trabajo. No las repito acá.

ESTADO: <construido | parcial> · <stack> · <motor de base de datos>

DOS RESTRICCIONES DE ESTE PROYECTO, no negociables (ver PRD sección 11.2):
  1. <desviación 1>. NO la corrijas ni propongas corregirla.
  2. <desviación 2>. NO la corrijas ni propongas corregirla.

Las decisiones pendientes de la sección 13 del PRD las resuelvo yo.
No las rellenes con supuestos.

PLAN, un paso por vez, parás al final de cada uno:
  1. EJEMPLAR CANÓNICO DE ESTE REPOSITORIO. Este proyecto no nació del
     esqueleto, así que no tiene ejemplar propio. Revisá el código y elegí el
     conjunto de archivos de la feature MEJOR RESUELTA que ya exista.
     Proponémelo con tu razonamiento: por qué ese y no otro, y qué defectos
     tiene que habría que limpiar antes de convertirlo en referencia.
     NO escribas código todavía.
  2. GAUNTLET. Proponé los comandos de verificación adaptados a la estructura
     real de este repositorio. Los ejecuto yo.
  3. <primer trabajo concreto>
  4. <segundo trabajo concreto>

Arrancá con el paso 1.
```

---

## Por qué el paso 1 de la variante B va primero

Sin ejemplar **de ese repositorio**, cualquier módulo nuevo va a salir con la forma del esqueleto —modular por feature— y vas a terminar con dos convenciones conviviendo. Peor que la que ya tenías.

Y fijate que el paso pide que el agente diga **qué defectos tiene** el candidato antes de consagrarlo. Es la única defensa contra el ejemplar podrido: si vas a copiar esa forma cuarenta veces, conviene saber qué estás copiando.

---

## Ejemplo completo — OM Distribution (proyecto 09)

```
Leé completos: agent/PROTOCOLO.md, agent/EJEMPLARES.md, docs/PRD.md.
Ahí están todas las reglas de trabajo. No las repito acá.

ESTADO: PARCIAL. Backend Express 5 + TypeScript, frontend React 19, MySQL 8,
docker-compose. PRD migrado a SDD v3.2.
  Construido:     landing, catálogo de productos, categorías, contactos, auth.
                  Tablas reales: products, categories, product_categories,
                  contacts, users, refresh_tokens.
  NO construido:  el módulo de órdenes. No existen tablas de pedidos ni líneas,
                  y el analizador multimodal está especificado, no implementado.
  ATENCIÓN: el PRD se escribió DESPUÉS del código y describe funcionalidad que
  no existe. No asumas que hay código donde el PRD dice que hay.

DOS RESTRICCIONES DE ESTE PROYECTO, no negociables (ver PRD sección 11.2):
  1. Motor MySQL 8, impuesto por proyecto ya construido. NO propongas migrar
     a PostgreSQL. Nunca.
  2. backend/src está organizado POR TIPO (controllers/, services/,
     repositories/, routes/, middlewares/), no por feature. Es la convención
     vigente de este repositorio. NO propongas reorganizarlo.

Las 15 decisiones pendientes de la sección 13 del PRD las resuelvo yo.

PLAN, un paso por vez, parás al final de cada uno:
  1. Ejemplar canónico de este repositorio: elegí el mejor triplete
     controller + service + repository que ya exista en backend/src.
     Proponémelo con tu razonamiento y sus defectos. NO escribas código.
  2. Gauntlet: comandos de verificación adaptados a la estructura por tipo.
  3. Marcar la columna ESTADO de cada RF del PRD (construido / parcial /
     pendiente), verificada contra el código real y no contra la intención:
     por cada RF, nombrá qué tabla o qué archivo lo prueba. Si no podés nombrar
     uno, es PENDIENTE. Después decime qué RF quedaron pendientes para que yo
     decida si son funcionalidad futura o si sobran del PRD.
  4. Endurecer la subida de archivos: backend/src/routes/upload.routes.ts usa
     multer sin validación de MIME por contenido, sin límite de páginas de PDF,
     sin nombre saneado, sin idempotencia y sin política de retención.
     Faltan las cinco.

Arrancá con el paso 1.
```

---

## Qué va y qué no va en un brief

| Va | No va |
|---|---|
| Puntero a los tres archivos | Las reglas de proceso (están en `PROTOCOLO.md`) |
| Estado real del proyecto | El formato de entrega (regla O del protocolo) |
| Las desviaciones declaradas, con énfasis | Los límites de terminal (regla T) |
| El plan, en orden, con paros | La lista de gates completa (regla G) |
| Quién resuelve las decisiones pendientes | Los requisitos funcionales (están en el PRD) |

Si el brief pasa de unas veinte líneas, es que estás duplicando algo que ya vive en otro archivo.
