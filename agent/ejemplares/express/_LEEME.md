# Ejemplar canónico — módulo backend Express

**Esto es referencia de lectura, no código ejecutable.** Los imports apuntan a rutas relativas que solo resuelven dentro de un proyecto real; acá están para que se vea la forma, no para compilar.

**El esqueleto completo y ejecutable está en `templates/backend_ex/`.** Esa es la semilla que se copia una vez al crear un proyecto Express nuevo. Este ejemplar es la copia de su módulo `users/`, puesta acá para que la referencia siga siendo válida desde cualquier repositorio, incluso los que no son Express.

---

## Qué fija este ejemplar

| Archivo | Qué fija |
|---|---|
| `modules-users/users.schema.ts` | Zod en la frontera. **Los tipos se derivan del esquema** con `z.infer`, nunca se escriben dos veces. |
| `modules-users/users.repo.ts` | La única capa que habla con la base. `select` explícito de campos, no la fila completa. Inyección por constructor. |
| `modules-users/users.service.ts` | Toda la lógica de negocio. **No recibe ni devuelve `req`/`res`.** Lanza clases de error, nunca responde HTTP. |
| `modules-users/users.controller.ts` | Traduce HTTP y nada más. **No toca la base. Máximo ~20 líneas por método.** Métodos como propiedades flecha para no perder `this`. |
| `modules-users/users.routes.ts` | El cableado a mano y visible: `repo → service → controller`. Sin contenedor de inyección, sin decoradores. |
| `modules-users/users.test.ts` | **El patrón de pruebas.** El servicio se prueba con un doble del repositorio, sin base de datos y sin servidor. |
| `shared/httpError.ts` | El vocabulario de errores de negocio que el servicio lanza y que un único manejador central traduce a HTTP. |
| `shared/validate.ts` | La fábrica de middleware de validación. Ningún dato llega al servicio sin pasar por su esquema. |

## Alternativas descartadas

- **Contenedor de inyección de dependencias o decoradores.** Menos código a la vista, pero resolución en tiempo de ejecución: magia que hay que auditar. El cableado escrito se sigue con el dedo.
- **`asyncHandler` envolviendo cada handler.** Express 5 reenvía las promesas rechazadas al manejador de errores automáticamente. Ese envoltorio es código muerto, y una IA lo va a agregar por costumbre: rechazalo.
- **`res.status(404)` dentro del servicio.** Acopla la lógica de negocio al transporte e impide probarla sin levantar un servidor.
- **Comprobar duplicados solo con un `if` en el servicio.** Entre leer y escribir hay una carrera. La defensa real es el `UNIQUE` del esquema; el `if` existe solo para dar un mensaje claro.

## Cómo se usa

Para un módulo nuevo: **copiá esta forma.** Tu diff contra este ejemplar debe tocar únicamente las reglas de negocio, los campos del esquema, las consultas y los estados. Si toca el cableado, el manejo de errores o la estructura del test, te desviaste.
