/**
 * ====================================================================
 * Demo Message Generator — Vista previa de email/SMS de confirmación
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Genera el contenido del mensaje de confirmación que se mostraría
 * al paciente y al médico. En modo demo/portafolio, se muestra en
 * pantalla en vez de enviarse por email/SMS.
 */

import type { AgentContext } from './agentStateMachine';

export interface DemoMessage {
  subject: string;
  recipientPatient: string;
  recipientDoctor: string;
  bodyHtml: string;
  bodyPlainText: string;
  generatedAt: string;
  isDemo: true;
}

export function generateDemoMessage(ctx: AgentContext, appointmentId?: string): DemoMessage {
  const dateObj = ctx.bookingData.slotStart ? new Date(ctx.bookingData.slotStart) : new Date();
  const dateFormatted = dateObj.toLocaleDateString('es-ES', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
  });
  const timeFormatted = ctx.bookingData.slotLocalTime || dateObj.toLocaleTimeString('es-ES', {
    hour: '2-digit', minute: '2-digit', hour12: false,
  });

  const patientName = ctx.userName || 'Paciente';
  const doctorName = ctx.bookingData.doctorName || 'Médico asignado';
  const specialty = ctx.bookingData.specialtyName || 'General';
  const motivo = ctx.patientData.motivo || 'Consulta general';
  const allergies = ctx.patientData.allergies || 'No reportadas';
  const medications = ctx.patientData.currentMedications || 'No reportados';
  const severity = ctx.patientData.symptomsSeverity || 'No evaluada';
  const onset = ctx.patientData.symptomsOnset || 'No reportado';
  const duration = ctx.patientData.symptomsDuration || 'No reportada';
  const fakeLink = `https://salvia.health/teleconsulta/${appointmentId || 'demo-session'}`;

  const subject = `Confirmación de cita con Dr. ${doctorName} — ${dateFormatted}`;

  const bodyHtml = `
    <div style="font-family: 'Inter', sans-serif; max-width: 600px; margin: 0 auto;">
      <div style="background: linear-gradient(135deg, #0E5D52, #148071); color: #FFF; padding: 20px 24px; border-radius: 12px 12px 0 0;">
        <h2 style="margin: 0; font-size: 1.1rem;">🌿 Salvia — Confirmación de Cita</h2>
      </div>

      <div style="background: #FFF; padding: 24px; border: 1px solid #E5E7EB; border-top: none; border-radius: 0 0 12px 12px;">
        <p>Hola <strong>${patientName}</strong>,</p>
        <p>Tu cita de telemedicina ha sido confirmada.</p>

        <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
          <tr><td style="padding: 8px 0; color: #6B7280; width: 140px;">👨‍⚕️ Médico</td><td style="padding: 8px 0; font-weight: 600;">Dr. ${doctorName}</td></tr>
          <tr><td style="padding: 8px 0; color: #6B7280;">🏥 Especialidad</td><td style="padding: 8px 0;">${specialty}</td></tr>
          <tr><td style="padding: 8px 0; color: #6B7280;">📅 Fecha</td><td style="padding: 8px 0; font-weight: 600;">${dateFormatted}</td></tr>
          <tr><td style="padding: 8px 0; color: #6B7280;">🕐 Hora</td><td style="padding: 8px 0; font-weight: 600;">${timeFormatted}</td></tr>
          <tr><td style="padding: 8px 0; color: #6B7280;">📋 Motivo</td><td style="padding: 8px 0;">${motivo}</td></tr>
          <tr><td style="padding: 8px 0; color: #6B7280;">💻 Modalidad</td><td style="padding: 8px 0;">${ctx.modality === 'presencial' ? '🏢 Presencial (en sitio)' : '💻 Teleconsulta (remota)'}</td></tr>
        </table>

        <div style="background: #F0FDF4; border: 1px solid #86EFAC; border-radius: 8px; padding: 14px; margin: 16px 0;">
          <strong>🔗 Enlace de la sesión:</strong><br>
          <a href="#" style="color: #0E5D52; word-break: break-all;">${fakeLink}</a>
        </div>

        <div style="background: #F9FAFB; border-radius: 8px; padding: 14px; margin: 16px 0;">
          <strong>📋 Instrucciones previas:</strong>
          <ul style="margin: 8px 0; padding-left: 20px; font-size: 0.9rem; color: #374151;">
            <li>Complete el formulario pre-consulta si aún no lo ha hecho</li>
            <li>Pruebe cámara y micrófono 10 minutos antes</li>
            <li>Tenga a mano su documentación médica relevante</li>
            <li>Busque un lugar tranquilo y con buena conexión a internet</li>
          </ul>
        </div>

        <div style="background: #FFF7ED; border: 1px solid #FDBA74; border-radius: 8px; padding: 14px; margin: 16px 0;">
          <strong>🩺 Datos clínicos recolectados:</strong>
          <div style="font-size: 0.85rem; margin-top: 8px; color: #374151;">
            <div><strong>Inicio síntomas:</strong> ${onset}</div>
            <div><strong>Severidad:</strong> ${severity}</div>
            <div><strong>Duración:</strong> ${duration}</div>
            <div><strong>Alergias:</strong> ${allergies}</div>
            <div><strong>Medicación actual:</strong> ${medications}</div>
          </div>
        </div>

        <hr style="border: none; border-top: 1px solid #E5E7EB; margin: 20px 0;">
        <p style="font-size: 0.82rem; color: #9CA3AF;">
          ⚠️ Si crees que estás en una emergencia médica, llama al 911 ahora.
          <br>Este mensaje es una demostración del sistema Salvia; no se ha enviado realmente.
        </p>
      </div>
    </div>
  `.trim();

  const bodyPlainText = `
CONFIRMACIÓN DE CITA — SALVIA

Hola ${patientName},
Tu cita de telemedicina ha sido confirmada.

👨‍⚕️ Médico: Dr. ${doctorName}
🏥 Especialidad: ${specialty}
📅 Fecha: ${dateFormatted}
🕐 Hora: ${timeFormatted}
📋 Motivo: ${motivo}
💻 Modalidad: Teleconsulta

🔗 Enlace: ${fakeLink}

INSTRUCCIONES PREVIAS:
- Complete el formulario pre-consulta
- Pruebe cámara y micrófono 10 min antes
- Tenga documentación médica a mano
- Busque lugar tranquilo con buena conexión

DATOS CLÍNICOS:
- Inicio síntomas: ${onset}
- Severidad: ${severity}
- Duración: ${duration}
- Alergias: ${allergies}
- Medicación: ${medications}

Si crees que estás en una emergencia médica, llama al 911 ahora.
Este mensaje es una demostración — no se ha enviado realmente.
  `.trim();

  return {
    subject,
    recipientPatient: `${patientName.toLowerCase().replace(/\s/g, '.')}@example.com`,
    recipientDoctor: `dr.${doctorName.toLowerCase().replace(/\s/g, '.')}@salvia.health`,
    bodyHtml,
    bodyPlainText,
    generatedAt: new Date().toISOString(),
    isDemo: true,
  };
}
