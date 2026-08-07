/**
 * ====================================================================
 * Tipos públicos — sin sesión
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Vista pública de un especialista. Campos exactos del endpoint público.
 * SIN email. SIN license_number. A propósito — son datos protegidos.
 */

export interface PublicDoctor {
  id: string;
  name: string;
  last_name: string;
  description: string;
  university: string;
  years_experience: number;
  consultation_fee: number;
  specialty: string;
  photo_url: string | null;
}
