"""
====================================================================
EJEMPLAR CANÓNICO — Servicio de IA (FastAPI)
AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
====================================================================

QUÉ FIJA ESTE EJEMPLAR
Los cinco cinturones de seguridad de toda llamada a un modelo, en el orden en
que hay que aplicarlos (Manual de Patrones, secciones 4 y 4.3):

  1. Prompt como ARCHIVO VERSIONADO, no cadena embebida.
  2. Esquema de salida exigido al modelo Y validado al recibir.
  3. Timeout explícito.
  4. Reintento con corrección: se le devuelve el error de validación concreto.
  5. Tope de intentos y camino alternativo determinista.

REGLA: el servicio no conoce HTTP. No importa FastAPI, no recibe Request.
Igual que en Express: eso es lo que permite probarlo sin levantar el servidor.
"""

from __future__ import annotations

import asyncio
import json
from pathlib import Path

from pydantic import ValidationError

from .schemas import ExtraerOrdenResponse

RUTA_PROMPTS = Path(__file__).parent / "prompts"
UMBRAL_CONFIANZA = 0.80
MAX_INTENTOS = 2
TIMEOUT_SEGUNDOS = 45.0


class ErrorDeExtraccion(Exception):
    """Falla esperada del servicio. El router la traduce a un código HTTP:
    el servicio no sabe cuál es ese código, y así debe ser."""


class OrdenesService:
    def __init__(self, cliente_llm, presupuesto_tokens: int = 20_000) -> None:
        # Inyección por constructor, igual que en el esqueleto Express.
        # `cliente_llm` es un adaptador propio, no el SDK crudo: así cambiar de
        # proveedor no toca este archivo (patrón Adapter, Manual sección 2).
        self._llm = cliente_llm
        self._presupuesto = presupuesto_tokens

    def _cargar_prompt(self, nombre: str) -> str:
        """El prompt es un artefacto versionado en el repositorio. Va a la lista
        de archivos de interés del Gate 4, igual que el código."""
        return (RUTA_PROMPTS / f"{nombre}.md").read_text(encoding="utf-8")

    async def extraer(self, documento_url: str, idioma: str) -> ExtraerOrdenResponse:
        prompt = self._cargar_prompt("extraer_orden")
        ultimo_error: str | None = None

        for intento in range(1, MAX_INTENTOS + 1):
            try:
                crudo = await asyncio.wait_for(
                    self._llm.generar(
                        prompt=prompt,
                        documento_url=documento_url,
                        idioma=idioma,
                        # El esquema se le EXIGE al modelo, no se espera que adivine.
                        formato_salida=ExtraerOrdenResponse.model_json_schema(),
                        correccion=ultimo_error,
                        max_tokens=self._presupuesto,
                    ),
                    timeout=TIMEOUT_SEGUNDOS,
                )
            except TimeoutError as exc:
                raise ErrorDeExtraccion("El modelo no respondió en el tiempo límite") from exc

            try:
                # Y ADEMÁS se valida al recibir. Exigir el esquema no garantiza
                # que lo respete: son dos defensas distintas.
                resultado = ExtraerOrdenResponse.model_validate(
                    crudo if isinstance(crudo, dict) else json.loads(crudo)
                )
            except (ValidationError, json.JSONDecodeError) as exc:
                # Reintento CON CORRECCIÓN: se le pasa el error concreto, no se
                # repite la misma petición esperando otro resultado.
                ultimo_error = str(exc)[:1500]
                if intento == MAX_INTENTOS:
                    raise ErrorDeExtraccion(
                        "El modelo no produjo una salida válida tras varios intentos"
                    ) from exc
                continue

            if resultado.tokens_usados > self._presupuesto:
                raise ErrorDeExtraccion("Presupuesto de tokens excedido")

            # Regla de negocio: decide el servicio, no el modelo.
            resultado.requiere_revision = any(
                linea.confianza < UMBRAL_CONFIANZA or linea.sku_sugerido is None
                for linea in resultado.lineas
            )
            return resultado

        raise ErrorDeExtraccion("Extracción fallida")  # inalcanzable; explícito a propósito
