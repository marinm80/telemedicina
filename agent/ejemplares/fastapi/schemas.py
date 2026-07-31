"""
====================================================================
EJEMPLAR CANÓNICO — Esquemas Pydantic (FastAPI)
AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
====================================================================

QUÉ FIJA ESTE EJEMPLAR
La frontera del servicio de IA. Entrada y **salida** validadas: el esquema de
salida es obligatorio y es lo que convierte la respuesta de un modelo en un
dato con el que se puede trabajar.

REGLA: ninguna salida de un LLM llega a la base de datos ni al cliente sin
pasar por un esquema. Es el patrón Guardrails del Manual, sección 4.
"""

from __future__ import annotations

from typing import Literal

from pydantic import BaseModel, ConfigDict, Field


class ExtraerOrdenRequest(BaseModel):
    """Entrada del endpoint. `extra='forbid'` rechaza campos no declarados:
    un campo de más suele ser un cliente desactualizado o un intento de abuso."""

    model_config = ConfigDict(extra="forbid")

    documento_url: str = Field(description="URL firmada, de vida corta")
    idioma: Literal["es", "en"] = "es"


class LineaOrden(BaseModel):
    """Una línea extraída del documento.

    `confianza` NO es decorativa: es lo que permite decidir qué líneas necesitan
    revisión humana en lugar de aceptar todo el resultado en bloque.
    """

    sku_sugerido: str | None = Field(default=None, description="None si no hubo coincidencia")
    nombre_leido: str = Field(min_length=1, max_length=300)
    cantidad: int = Field(gt=0, le=100_000)
    precio_leido: float | None = Field(default=None, ge=0)
    confianza: float = Field(ge=0.0, le=1.0)


class ExtraerOrdenResponse(BaseModel):
    """ESQUEMA DE SALIDA OBLIGATORIO — se le pasa al modelo como formato exigido
    y además se valida la respuesta contra él. Las dos cosas, no una."""

    lineas: list[LineaOrden]
    requiere_revision: bool = Field(
        description="True si alguna línea quedó por debajo del umbral de confianza"
    )
    tokens_usados: int = Field(ge=0, description="Para el control de presupuesto por operación")
    modelo: str = Field(description="Modelo y versión exactos que produjeron esto")
