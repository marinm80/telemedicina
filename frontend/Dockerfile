# ====================================================================
# Dockerfile — Frontend Vite dev server
# AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
# ====================================================================
#
# Imagen ligera para desarrollo local. Corre `pnpm dev` y expone el
# puerto 5173 con HMR. NO es imagen de producción.
#
# DECISIÓN: node:22-alpine
# - Alpine por tamaño (~50MB vs ~350MB de node:22)
# - Node 22 por import.meta.dirname (usado en vite.config.ts)
# - pnpm instalado globalmente porque es el package manager del proyecto

FROM node:22-alpine

# pnpm
RUN corepack enable && corepack prepare pnpm@latest --activate

WORKDIR /app

# Instalar dependencias primero (cache de capa Docker)
COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile

# Copiar código fuente
COPY . .

# Vite dev server
EXPOSE 5173

CMD ["pnpm", "dev", "--host", "0.0.0.0"]
