<?php
declare(strict_types=1);

namespace App\Actions\Assistant;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class PublicAssistantAction
{
    /**
     * Procesar consulta informativa en la landing pública (RF-23).
     * Modo de lectura pura consultando v_doctor_directory y specialties con 0 escrituras en BD.
     * Respuestas automáticas guiando al visitante a registrarse/iniciar sesión.
     *
     * @param  string  $query
     * @param  string|null  $specialty
     * @return array<string, mixed>
     */
    public function handle(string $query, ?string $specialty = null): array
    {
        $normalizedQuery = MB_strtolower(trim($query));

        // 1. Obtener especialidades activas desde la base de datos
        $specialtiesList = DB::table('specialties')
            ->where('is_active', true)
            ->pluck('name')
            ->toArray();

        if (empty($specialtiesList)) {
            $specialtiesList = ['Cardiología', 'Medicina General', 'Pediatría', 'Dermatología', 'Neurología'];
        }

        // 2. Horario de atención al cliente
        $isAskingHours = Str::contains($normalizedQuery, [
            'horario', 'horarios', 'atencion', 'atención', 'hora', 'abierto', 'cuando atienden', 'cuándo atienden'
        ]);

        if ($isAskingHours) {
            return [
                'reply'            => "Nuestra plataforma de atención médica online funciona las 24 horas, los 7 días de la semana para consultas agendadas. El servicio de soporte al cliente y atención administrativa opera de Lunes a Viernes de 08:00 a 20:00 hrs y Sábados de 09:00 a 14:00 hrs. Te invitamos a registrarte o iniciar sesión para agendar tu hora.",
                'suggested_action' => 'register_or_login',
                'schedule_info'    => [
                    'medical_care' => '24/7 Online',
                    'support'      => 'Lun-Vie 08:00 - 20:00 hrs | Sáb 09:00 - 14:00 hrs',
                ],
            ];
        }

        // 3. Dirección y Ubicación física
        $isAskingLocation = Str::contains($normalizedQuery, [
            'direccion', 'dirección', 'ubicacion', 'ubicación', 'donde estan', 'dónde están', 'donde quedan', 'dónde quedan', 'sede', 'sucursal', 'donde se encuentran'
        ]);

        if ($isAskingLocation) {
            return [
                'reply'            => "Nuestra sede central administrativa se encuentra ubicada en Av. Andrés Bello 2457, Providencia, Santiago. Sin embargo, todas nuestras consultas médicas son 100% online y se realizan por telemedicina de alta definición desde la comodidad de tu hogar. Te invitamos a registrarte o iniciar sesión para comenzar.",
                'suggested_action' => 'register_or_login',
                'location_info'    => [
                    'address' => 'Av. Andrés Bello 2457, Providencia, Santiago',
                    'modality' => '100% Telemedicina Online',
                ],
            ];
        }

        // 4. Cómo agendar / Pasos
        $isAskingBookingProcess = Str::contains($normalizedQuery, [
            'como agendar', 'cómo agendar', 'como reservo', 'cómo reservo', 'pasos', 'como funciona', 'cómo funciona'
        ]);

        if ($isAskingBookingProcess) {
            return [
                'reply'            => "Agendar tu cita es muy sencillo: 1) Regístrate o inicia sesión en la plataforma, 2) Selecciona la especialidad o médico de tu preferencia, 3) Elige el horario disponible que más te acomode y confirma. Te invitamos a crear tu cuenta ahora para reservar.",
                'suggested_action' => 'register_or_login',
            ];
        }

        // 5. Lista de especialidades
        $isAskingSpecialties = Str::contains($normalizedQuery, [
            'especialidad', 'especialidades', 'servicios', 'que tienen', 'qué tienen', 'ofrecen', 'cobertura'
        ]);

        if ($isAskingSpecialties) {
            $specialtiesString = implode(', ', $specialtiesList);

            return [
                'reply'            => sprintf(
                    "Contamos con las siguientes especialidades médicas en nuestra plataforma: %s. Para consultar la disponibilidad de cada profesional y agendar tu hora médica, te invitamos a registrarte o iniciar sesión.",
                    $specialtiesString
                ),
                'suggested_action' => 'register_or_login',
                'specialties'      => $specialtiesList,
            ];
        }

        // 6. Búsqueda por especialidad o médico en v_doctor_directory
        $detectedSpecialty = $specialty;
        if (!$detectedSpecialty) {
            foreach ($specialtiesList as $spec) {
                if (Str::contains($normalizedQuery, mb_strtolower($spec))) {
                    $detectedSpecialty = $spec;
                    break;
                }
            }
            if (!$detectedSpecialty && (Str::contains($normalizedQuery, ['cardiolog', 'cardiólog']))) {
                $detectedSpecialty = 'Cardiología';
            }
        }

        $dbQuery = DB::table('v_doctor_directory');

        if ($detectedSpecialty) {
            $dbQuery->where(function ($q) use ($detectedSpecialty) {
                $q->where('description', 'ILIKE', '%' . $detectedSpecialty . '%')
                  ->orWhere('university', 'ILIKE', '%' . $detectedSpecialty . '%');
            });
        }

        $doctors = $dbQuery->limit(5)->get();

        $doctorList = $doctors->map(fn ($d) => [
            'user_id'          => $d->user_id,
            'name'             => $d->name . ' ' . $d->last_name,
            'description'      => $d->description,
            'university'       => $d->university,
            'years_experience' => $d->years_experience,
            'consultation_fee' => $d->consultation_fee,
        ])->toArray();

        if ($detectedSpecialty && count($doctorList) > 0) {
            $doctorNames = implode(', ', array_map(fn ($d) => $d['name'], $doctorList));
            return [
                'reply'            => sprintf(
                    "Sí, disponemos de especialistas en %s (como %s). Para seleccionar tu horario de preferencia y confirmar tu cita, por favor regístrate o inicia sesión en tu cuenta.",
                    $detectedSpecialty,
                    $doctorNames
                ),
                'suggested_action' => 'register_or_login',
                'doctors'          => $doctorList,
            ];
        }

        if (count($doctorList) > 0) {
            $doctorNames = implode(', ', array_map(fn ($d) => $d['name'], $doctorList));
            return [
                'reply'            => sprintf(
                    "Contamos con profesionales médicos registrados como %s. Para agendar una consulta médica online, regístrate o inicia sesión en la plataforma.",
                    $doctorNames
                ),
                'suggested_action' => 'register_or_login',
                'doctors'          => $doctorList,
            ];
        }

        // 7. Respuesta orientativa por defecto guiando al registro/login
        return [
            'reply'            => "En la Plataforma de Telemedicina puedes consultar con médicos especialistas de forma 100% online. Para explorar la disponibilidad de horarios, conocer las tarifas y agendar tu cita, por favor regístrate o inicia sesión.",
            'suggested_action' => 'register_or_login',
            'specialties'      => $specialtiesList,
        ];
    }
}
