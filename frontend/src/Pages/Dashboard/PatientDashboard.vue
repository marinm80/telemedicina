<script setup lang="ts">
interface AppointmentItem {
  id: string;
  patient_id: string;
  doctor_id: string;
  status: string;
  franja_start: string;
  franja_end: string;
}

interface PatientDashboardProps {
  upcoming_appointments: AppointmentItem[]
  past_consultations_count: number
  active_prescriptions_count: number
}

defineProps<PatientDashboardProps>()
</script>

<template>
  <div class="patient-dashboard p-6">
    <h1 class="text-2xl font-bold mb-4">Mi Panel de Paciente</h1>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      <div class="p-4 bg-white shadow rounded-lg">
        <span class="text-gray-500 text-sm">Próximas Citas</span>
        <p class="text-3xl font-bold text-blue-600">{{ upcoming_appointments.length }}</p>
      </div>
      <div class="p-4 bg-white shadow rounded-lg">
        <span class="text-gray-500 text-sm">Consultas Anteriores</span>
        <p class="text-3xl font-bold">{{ past_consultations_count }}</p>
      </div>
      <div class="p-4 bg-white shadow rounded-lg">
        <span class="text-gray-500 text-sm">Recetas Activas</span>
        <p class="text-3xl font-bold text-emerald-600">{{ active_prescriptions_count }}</p>
      </div>
    </div>
  </div>
</template>
