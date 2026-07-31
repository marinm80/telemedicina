"""
====================================================================
EJEMPLAR CANÓNICO — Router (FastAPI)
AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
====================================================================

QUÉ FIJA ESTE EJEMPLAR
El router es el equivalente del controlador: traduce HTTP y nada más.
  1. `response_model` declarado: FastAPI valida la salida y documenta el contrato.
  2. Traduce las excepciones del servicio a códigos HTTP. El servicio no los conoce.
  3. Autenticación de red interna por secreto compartido: este servicio NO se
     expone a internet, solo lo llama Laravel o Express por la red privada.
  4. Sin lógica de negocio. Ninguna.
"""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, Header, HTTPException, status

from .schemas import ExtraerOrdenRequest, ExtraerOrdenResponse
from .service import ErrorDeExtraccion, OrdenesService

router = APIRouter(prefix="/ordenes", tags=["ordenes"])


async def verificar_secreto_interno(
    x_internal_token: Annotated[str | None, Header()] = None,
) -> None:
    """El servicio de IA no tiene autenticación de usuarios: vive en la red
    interna y confía en un secreto compartido. Quien autentica al usuario es
    Laravel o Express, que son los únicos que llaman acá."""
    from ..shared.settings import settings  # noqa: PLC0415

    if x_internal_token != settings.internal_token:
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Token interno inválido")


def obtener_service() -> OrdenesService:
    from ..shared.llm import cliente_llm  # noqa: PLC0415

    return OrdenesService(cliente_llm)


@router.post(
    "/extraer",
    response_model=ExtraerOrdenResponse,
    status_code=status.HTTP_200_OK,
    summary="Extrae las líneas de una orden desde una imagen o PDF",
    dependencies=[Depends(verificar_secreto_interno)],
)
async def extraer_orden(
    payload: ExtraerOrdenRequest,
    service: Annotated[OrdenesService, Depends(obtener_service)],
) -> ExtraerOrdenResponse:
    try:
        return await service.extraer(payload.documento_url, payload.idioma)
    except ErrorDeExtraccion as exc:
        # 422: la forma de la petición era válida, la operación no se pudo completar.
        raise HTTPException(status.HTTP_422_UNPROCESSABLE_ENTITY, str(exc)) from exc
