<script setup lang="ts">
interface AdminDashboardProps {
  total_users: number
  pending_doctor_approvals: number
  monthly_appointments_count: number
  total_revenue: number
}

defineProps<AdminDashboardProps>()
</script>

<template>
  <div class="admin-dashboard p-6">
    <h1 class="text-2xl font-bold mb-4">Panel de Administración General</h1>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="p-4 bg-white shadow rounded-lg">
        <span class="text-gray-500 text-sm">Usuarios Totales</span>
        <p class="text-3xl font-bold">{{ total_users }}</p>
      </div>
      <div class="p-4 bg-white shadow rounded-lg">
        <span class="text-gray-500 text-sm">Médicos Pendientes</span>
        <p class="text-3xl font-bold text-amber-600">{{ pending_doctor_approvals }}</p>
      </div>
      <div class="p-4 bg-white shadow rounded-lg">
        <span class="text-gray-500 text-sm">Citas del Mes</span>
        <p class="text-3xl font-bold">{{ monthly_appointments_count }}</p>
      </div>
      <div class="p-4 bg-white shadow rounded-lg">
        <span class="text-gray-500 text-sm">Ingresos Totales</span>
        <p class="text-3xl font-bold text-emerald-600">${{ total_revenue.toLocaleString() }}</p>
      </div>
    </div>
  </div>
</template>
