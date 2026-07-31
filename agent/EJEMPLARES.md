# Registro de ejemplares canónicos — SDD v3.2

> **Autor:** Rafael Marín · **Documentos matriz:** Plan de Implementación SDD v3.2 · Protocolo Reglas SDD v3.1 · Manual de Patrones v1.1
> **Esqueleto Express:** `backend_ex/`

---

## Para qué existe esto

Un ejemplar canónico es **la forma correcta de un tipo de archivo, escrita una vez**. Cuando le pedís algo al agente, no le describís la arquitectura: le señalás el ejemplar.

La razón es mecánica. El modelo completa patrones: si le pones en contexto un archivo correcto del mismo repositorio, continúa ese patrón. Si le das una descripción en prosa, tiene que **traducirla** a una forma, y cada traducción es una oportunidad de desviarse. Tres párrafos sobre arquitectura en capas producen cinco interpretaciones; un archivo produce una.

### La división de trabajo

| | Transmite | No puede transmitir |
|---|---|---|
| **El ejemplar** | La forma: nombres, orden, imports, manejo de errores, estructura del test, qué se exporta | Prohibiciones. Un ejemplo solo muestra lo que **sí** se hace |
| **La regla** | La frontera y el motivo: qué no hacer, límites, por qué la decisión es esa | La forma, sin volverse un manual de cincuenta páginas |

> **Ejemplar = la forma. Regla = la frontera y la razón.**

Por eso el bloque del agente en `backend_ex/README.md` tiene doce reglas y casi ninguna describe estructura: la estructura la señala con *"copiá la forma de `src/modules/users/`"*. Las reglas son casi todas prohibiciones.

---

## El mapeo: petición → ejemplar

| Cuando pidas… | Ejemplar canónico | Estado |
|---|---|---|
| Módulo backend Express (routes/controller/service/repo/schema) | `ejemplares/express/modules-users/` | ✅ |
| Test de servicio Express | `ejemplares/express/modules-users/users.test.ts` | ✅ |
| Errores de negocio y validación en la frontera | `ejemplares/express/shared/` | ✅ |
| Componente Vue 3 | `ejemplares/vue/UserList.vue` | ✅ |
| Composable Vue | `ejemplares/vue/useUsers.ts` | ✅ |
| Componente React 19 | `ejemplares/react/UserList.tsx` | ✅ |
| Hook React | `ejemplares/react/useUsers.ts` | ✅ |
| Validación Laravel | `ejemplares/laravel/StoreUserRequest.php` | ✅ |
| Lógica de negocio Laravel | `ejemplares/laravel/CreateUserAction.php` | ✅ |
| Controlador Laravel | `ejemplares/laravel/UserController.php` | ✅ |
| Esquemas FastAPI (entrada y **salida**) | `ejemplares/fastapi/schemas.py` | ✅ |
| Servicio de IA FastAPI | `ejemplares/fastapi/service.py` | ✅ |
| Router FastAPI | `ejemplares/fastapi/router.py` | ✅ |
| Migración con declaración de riesgo | `ejemplares/migracion/EJEMPLAR_migracion.sql` | ✅ |
| Componente de formulario complejo | — | ⬜ pendiente |
| Job de cola (Horizon) | — | ⬜ pendiente |
| Conjunto de evaluación de IA | — | ⬜ pendiente |

**Uno por tipo. Ni dos, ni ninguno.**

### Ejemplar contra semilla — no son lo mismo

| | Qué es | Dónde | Se copia |
|---|---|---|---|
| **Ejemplar** | Archivo de **referencia para leer**. No compila fuera de un proyecto. | `agent/ejemplares/` | Con `agent/`, a **todos** los repositorios |
| **Semilla** | Proyecto **ejecutable** completo: `package.json`, Docker, configuración del gauntlet. | `templates/backend_ex/` | **Una vez**, al crear un proyecto Express |

El módulo `users/` está en los dos lugares a propósito, y no es una contradicción de la regla de la fuente única: en `templates/` es código que corre, en `ejemplares/` es la referencia que se lee. Si corregís el esqueleto, **actualizá la copia del ejemplar** — es la única sincronización manual que este sistema pide.

Y el motivo de tener la copia: si el ejemplar viviera solo en `templates/backend_ex/`, la referencia se rompería en cuanto el proyecto se llame `backend/` en lugar de `backend_ex/`, y no existiría en absoluto en un proyecto Laravel o FastAPI.

---

## La regla de paro

```
Si no existe ejemplar canónico para lo que te estoy pidiendo,
PARÁ y preguntá. No inventes una forma nueva.
```

Esto convierte la ausencia de un ejemplar en un **gate** en lugar de una invitación a improvisar. Es lo que impide la deriva justo en el caso que no previste — que es donde siempre aparece.

---

## Auditoría por delta

Es la consecuencia más valiosa del sistema, y cambia el Gate 4.

**Sin ejemplares:** cada módulo es nuevo. Tenés que leerlo entero. No escala.

**Con ejemplares:** cada módulo es *ejemplar + delta*. El boilerplate es, por definición, idéntico — no hay nada que leer ahí. **Solo se lee la diferencia contra el ejemplar.**

```bash
# El Gate 4, en un comando
diff -u backend_ex/src/modules/users/users.service.ts \
        src/modules/pedidos/pedidos.service.ts
```

Lo que aparece en el delta es exactamente lo que hay que auditar. Lo que no aparece, ya lo auditaste una vez.

| El delta SIEMPRE contiene | El delta NUNCA debería contener |
|---|---|
| Las reglas de negocio propias del módulo | El cableado de dependencias |
| Los campos del esquema y sus validaciones | La forma del manejo de errores |
| Las consultas y sus índices | Los cuatro estados de un componente |
| Los estados y transiciones válidas | La estructura del test |
| Los efectos externos y su idempotencia | El orden de los imports |

**Si aparece algo de la columna derecha en el delta, es una señal de alarma:** el agente se desvió del ejemplar, y eso se rechaza antes de leer la lógica. Es un filtro que cuesta treinta segundos y atrapa la deriva completa.

Esto refina la *superficie de auditoría dirigida* del Plan v3.2 §8: no es solo "los archivos de interés", es **el diff contra el ejemplar canónico**. El esfuerzo de revisión pasa a escalar con la lógica real del proyecto, no con el volumen de código generado.

---

## Las dos trampas

**1. El ejemplar podrido se propaga en silencio.** Si `users.service.ts` tiene un error de criterio, lo heredan los cuarenta módulos siguientes sin que ningún gate individual lo note — cada entrega es "consistente con el ejemplar". Por eso:

> Los ejemplares canónicos son el código de mayor valor del repositorio y van a **rigor máximo**, aunque sean un CRUD trivial. Un cambio en un ejemplar se revisa como si tocara producción, porque en efecto toca todo lo que venga después.

**2. Dos ejemplares del mismo tipo son peor que ninguno.** Si hay tres componentes de lista con estilos distintos, el agente elige uno al azar y volviste al punto de partida. Uno por tipo, marcado; los demás fuera del alcance del agente.

---

## Bloque para pegarle al agente

```
[EJEMPLARES CANÓNICOS — SDD v3.2]

X1. Antes de escribir cualquier archivo, identificá su tipo y buscá el ejemplar
    canónico correspondiente en EJEMPLARES.md. Leelo completo y COPIÁ SU FORMA:
    nombres, orden de imports, manejo de errores, estructura, exportaciones.

X2. Si no existe ejemplar canónico para lo que te estoy pidiendo, PARÁ y
    preguntá. NO inventes una forma nueva ni adaptes un ejemplar de otro tipo.

X3. Tu entrega debe diferir del ejemplar SOLO en:
      - las reglas de negocio propias del módulo
      - los campos del esquema y sus validaciones
      - las consultas y sus índices
      - los estados y transiciones válidas
    Si tu diff contra el ejemplar toca el cableado, el manejo de errores, los
    estados de un componente o la estructura del test, te desviaste. Corregilo
    antes de entregar.

X4. Al entregar, decime explícitamente contra qué ejemplar te basaste y
    enumerá en qué se diferencia tu archivo. Esa lista es lo que voy a auditar.

X5. NO modifiques un ejemplar canónico. Si crees que uno está mal, decilo y
    esperá: cambiarlo afecta a todo el código futuro del portafolio.

X6. Componentes de interfaz: los CUATRO ESTADOS son obligatorios — cargando,
    error (con reintento), vacío, y listo. Un componente que solo dibuja el caso
    feliz está incompleto y no pasa el Gate 2A.

X7. Servicios: nunca reciben ni devuelven objetos de petición o respuesta. Si
    necesitás señalar un error, lanzá la clase correspondiente y dejá que la capa
    de transporte lo traduzca.
```

---

## Mantenimiento

1. **Un ejemplar nuevo se agrega cuando un tipo de archivo se pide por segunda vez.** La primera vez se escribe a mano y se audita; la segunda se convierte en ejemplar.
2. **Todo ejemplar lleva su bloque `QUÉ FIJA ESTE EJEMPLAR` y su `ALTERNATIVA DESCARTADA`.** Sin eso es solo código de muestra: la explicación es lo que permite al agente decidir bien en el caso que el ejemplar no cubre.
3. **Los ejemplares son mínimos a propósito.** Un ejemplar largo transmite ruido junto con la forma. Si un ejemplar pasa de ~120 líneas, probablemente está fijando dos cosas a la vez y hay que partirlo.
4. **Cuando corrijas un ejemplar, anotá qué módulos ya nacidos de la versión vieja quedan desalineados.** Esa lista va al backlog, no se arregla en el momento.
