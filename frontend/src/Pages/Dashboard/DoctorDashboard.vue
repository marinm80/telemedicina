<script setup lang="ts">
interface AppointmentItem {
  id: string;
  patient_id: string;
  doctor_id: string;
  status: string;
  franja_start: string;
  franja_end: string;
}

interface DoctorDashboardProps {
  profile_status: 'pending' | 'approved' | 'rejected'
  today_appointments: AppointmentItem[]
  pending_notes_count: number
  month_earnings: number
}

defineProps<DoctorDashboardProps>()
</script>

<template>
  <div class="doctor-dashboard p-6">
    <h1 class="text-2xl font-bold mb-4">Panel del Médico</h1>
    <div v-if="profile_status === 'pending'" class="p-4 mb-4 bg-amber-100 text-amber-800 rounded-lg">
      Tu perfil médico está pendiente de aprobación por el equipo administrativo.
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      <div class="p-4 bg-white shadow rounded-lg">
        <span class="text-gray-500 text-sm">Citas de Hoy</span>
        <p class="text-3xl font-bold">{{ today_appointments.length }}</p>
      </div>
      <div class="p-4 bg-white shadow rounded-lg">
        <span class="text-gray-500 text-sm">Notas Pendientes</span>
        <p class="text-3xl font-bold text-amber-600">{{ pending_notes_count }}</p>
      </div>
      <div class="p-4 bg-white shadow rounded-lg">
        <span class="text-gray-500 text-sm">Ganancias del Mes</span>
        <p class="text-3xl font-bold text-emerald-600">${{ month_earnings.toLocaleString() }}</p>
      </div>
    </div>
  </div>
</template>
