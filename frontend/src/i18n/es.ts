/**
 * ====================================================================
 * Diccionario de traducciones (español) — Plataforma de Telemedicina
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Claves agrupadas por feature. Textos exactos de UI_PROTOTYPE.md §2
 * cuando aplica. Las claves de features fuera de alcance están vacías
 * pero presentes para que la estructura de claves esté preparada.
 */

const es: Record<string, string> = {
  // === COMMON ===
  'common.retry': 'Reintentar',
  'common.save': 'Guardar',
  'common.cancel': 'Cancelar',
  'common.close': 'Cerrar',
  'common.loading': 'Cargando…',
  'common.confirm': 'Confirmar',
  'common.back': 'Volver',
  'common.next': 'Siguiente',
  'common.search': 'Buscar',
  'common.clear_filters': 'Limpiar Filtros',

  // === ERRORS (genéricos) ===
  'errors.unknown': 'Ocurrió un error inesperado.',
  'errors.network': 'Error de conexión. Verifique su internet.',
  'errors.not_found': 'El recurso solicitado no fue encontrado.',
  'errors.forbidden': 'No tiene permisos para realizar esta acción.',

  // === DIRECTORY (Buscador de médicos) — UI_PROTOTYPE.md §2 ===
  'directory.loading': 'Cargando directorio de médicos…',
  'directory.empty': 'No encontramos médicos disponibles con las especialidades o filtros seleccionados.',
  'directory.empty_action': 'Limpiar Filtros',
  'directory.error': 'Error al recuperar el directorio de médicos.',
  'directory.no_permission': 'Acceso restringido. Inicie sesión para ver perfiles.',

  // === AGENDA (Configuración de agenda del médico) — UI_PROTOTYPE.md §2 ===
  'agenda.loading': 'Cargando agenda…',
  'agenda.empty': 'Aún no has configurado tu agenda de atención. Los pacientes no podrán reservar citas contigo.',
  'agenda.empty_action': 'Configurar Horario',
  'agenda.error': 'Error al guardar o recuperar tus franjas horarias de agenda.',
  'agenda.pending_approval': 'Tu perfil está PENDIENTE de aprobación. La agenda se activará al ser aprobado.',

  // === BOOKING (Reserva de citas del paciente) — UI_PROTOTYPE.md §2 ===
  'booking.loading': 'Cargando disponibilidad…',
  'booking.empty': 'El médico no posee slots libres para la semana seleccionada.',
  'booking.empty_action': 'Ver Siguiente Semana',
  'booking.error': 'No se pudo calcular la disponibilidad de la agenda médica.',
  'booking.no_permission': 'Verifique su dirección de email para poder reservar citas.',

  // === CLINICAL (Ficha clínica) — UI_PROTOTYPE.md §2 ===
  'clinical.allergies_empty': 'Sin alergias conocidas declaradas ni confirmadas.',
  'clinical.conditions_empty': 'Sin condiciones de salud registradas.',
  'clinical.medications_empty': 'Sin medicación habitual reportada.',
  'clinical.error': 'Falla parcial: Error al recuperar los antecedentes médicos del paciente.',
  'clinical.no_permission': 'Acceso denegado. El personal administrativo no tiene acceso a la ficha clínica.',

  // === CONSULTATION (Sala de consulta) — UI_PROTOTYPE.md §2 ===
  'consultation.chat_empty': 'El canal de chat ha sido abierto. Salude al paciente para iniciar la consulta.',
  'consultation.chat_error': 'Se perdió la conexión con el servidor de chat en vivo.',
  'consultation.chat_reconnect': 'Reconectar Chat',
  'consultation.soap_empty': 'La nota SOAP está vacía. Inicia la redacción del diagnóstico.',
  'consultation.soap_error': 'Error al autoguardar el borrador clínico. Cambios locales no sincronizados.',
  'consultation.soap_force_save': 'Forzar Guardado',
  'consultation.no_permission': 'Acceso denegado. No eres el médico asignado a esta cita.',

  // === HISTORY (Historial de citas) — UI_PROTOTYPE.md §2 ===
  'history.loading': 'Cargando historial de citas…',
  'history.empty': 'Aún no has reservado ninguna cita médica en la plataforma.',
  'history.empty_action': 'Buscar Especialista',
  'history.error': 'Error al recuperar tu historial de citas.',
  'history.past_empty': 'Este paciente no registra consultas previas en la plataforma.',
  'history.past_error': 'No pudimos recuperar el historial de consultas del paciente.',

  // === DEMO BANNER ===
  'demo.banner': 'Modo de Demostración Activo.',
  'demo.portfolio_link': 'Enlace al Portafolio',

  // === FOOTER ===
  'footer.credits': 'Portafolio de Telemedicina © 2026',
  'footer.author': 'Rafael Marín',
  'footer.rights': 'Todos los derechos reservados.',
};

export default es;
