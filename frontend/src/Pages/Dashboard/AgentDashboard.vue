<script setup lang="ts">
interface AppointmentItem {
  id: string;
  patient_id: string;
  doctor_id: string;
  status: string;
}

interface AgentDashboardProps {
  pending_appointments_count: number
  unassigned_requests_count: number
  active_doctors_count: number
  recent_appointments: AppointmentItem[]
}

defineProps<AgentDashboardProps>()
</script>

<template>
  <div class="agent-dashboard p-6">
    <h1 class="text-2xl font-bold mb-4">Panel de Recepción / Agente</h1>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      <div class="p-4 bg-white shadow rounded-lg">
        <span class="text-gray-500 text-sm">Citas Pendientes</span>
        <p class="text-3xl font-bold text-amber-600">{{ pending_appointments_count }}</p>
      </div>
      <div class="p-4 bg-white shadow rounded-lg">
        <span class="text-gray-500 text-sm">Solicitudes Sin Asignar</span>
        <p class="text-3xl font-bold">{{ unassigned_requests_count }}</p>
      </div>
      <div class="p-4 bg-white shadow rounded-lg">
        <span class="text-gray-500 text-sm">Médicos Activos</span>
        <p class="text-3xl font-bold text-emerald-600">{{ active_doctors_count }}</p>
      </div>
    </div>
  </div>
</template>
