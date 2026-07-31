# Plantilla — README de carpeta de portafolio

> **Qué es:** el archivo que queda en `6_Portafolio_Tecnico/<Proyecto>/` cuando el PRD se mudó al repositorio del código.
> **Qué no es:** documentación técnica. Eso vive en el repo.
>
> Dos trabajos, y ninguno más: que entiendas el proyecto en veinte segundos, y que nadie edite el PRD en el lugar equivocado.

---

## Plantilla

```markdown
# Proyecto NN — <Nombre>

<Una línea: qué hace y para quién.>

> **Esta carpeta es material de vitrina.** No se desarrolla acá.

## Dónde está cada cosa

| | Ubicación |
|---|---|
| **Código** | `Workspace/projects/<repo>/` — repositorio git propio |
| **PRD (fuente única)** | `<repo>/docs/PRD.md` |
| **Protocolo y ejemplares** | `<repo>/agent/` |
| **Material comercial** | esta carpeta |

## Reglas

- **El PRD se edita únicamente en el repositorio del código.** No se copia acá.
  Si aparece un `PRD.md` en esta carpeta, es una copia vieja: borrala.
- Los cambios de alcance suben la versión del encabezado del PRD y agregan una
  línea a su changelog.

## Ficha técnica

| | |
|---|---|
| Stack | <combo + motor> |
| Motor de BD | <motor> — <elegido \| impuesto por…> |
| Perfil de marca | PORTAFOLIO \| CLIENTE |
| Rigor | MÁXIMO \| ALTO \| MEDIO \| BAJO |
| Estado | CONSTRUIDO \| PARCIAL \| ESPECIFICADO — con el detalle de qué sí y qué no |
| Tablas reales | <lista de las tablas que existen de verdad> |
| Desviaciones declaradas | <reglas del protocolo de las que se aparta> |

## Pendientes conocidos

- [ ] <…>
```

---

## Las tres columnas que no son obvias, y por qué están

**`Estado`, con el detalle de qué sí y qué no.** No alcanza "construido". Ese fue el error de OM: el PRD describía un módulo de órdenes que no existía, y el brief decía "construido". Acá se escribe la verdad partida en dos: qué funciona y qué está solo especificado.

**`Tablas reales`.** Es el chequeo más rápido y más difícil de falsear que existe. Si el PRD habla de pedidos y no hay tabla de pedidos, no hay pedidos. Una lista de seis palabras que te ahorra media hora de lectura optimista.

**`Desviaciones declaradas`.** Para que al abrir la carpeta en tres meses sepas de entrada por qué ese proyecto no cumple el protocolo, sin tener que ir al bloque 11.2 del PRD a buscarlo.

---

## Casos donde el README es distinto

**Proyecto sin código todavía** (11, 12, 13, 14, 06): **no lleva README.** El `PRD.md` se queda en la carpeta del portafolio y es la fuente única ahí, sin ambigüedad. El README aparece recién cuando el PRD se muda a un repo.

**Propuesta comercial, no software** (05 Infraestructura VPS, 07 Hetzner + Coolify): no van a tener repo nunca. Su documento se queda acá, y honestamente conviene renombrarlo: son **propuestas**, no PRD. La ficha técnica se reemplaza por lo que sí importa: a qué cliente se ofreció, en qué estado está la negociación, y qué material hay para reutilizar.
