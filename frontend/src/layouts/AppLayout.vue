<!--
  ====================================================================
  AppLayout — Main application layout including sidebar and main content area
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<template>
  <div class="layout-wrapper">
    <DemoBanner />
    <div class="app-layout">
      <AppSidebar @switch-role="handleRoleSwitch" @start-booking="triggerBooking" />
      <main class="app-layout__main">
        <slot />
      </main>
    </div>
    <AppFooter />
    <FloatingAssistant ref="assistantRef" />
  </div>
</template>

<script setup lang="ts">
import { ref, provide, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppSidebar from '@/components/app/AppSidebar.vue'
import DemoBanner from '@/components/DemoBanner.vue'
import AppFooter from '@/components/AppFooter.vue'
import FloatingAssistant from '@/components/FloatingAssistant.vue'

const page = usePage()
const user = computed(() => (page.props as any).auth?.user || {})

const activeViewRole = ref(user.value.role || 'patient')
const assistantRef = ref<InstanceType<typeof FloatingAssistant> | null>(null)

const handleRoleSwitch = (role: string) => {
  activeViewRole.value = role
}

const triggerBooking = () => {
  assistantRef.value?.startBookingFlow()
}

provide('activeViewRole', activeViewRole)
provide('startBooking', triggerBooking)
</script>

<style scoped>
.layout-wrapper {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

.app-layout {
  display: flex;
  flex: 1;
  background-color: var(--color-page-bg, #FAF5EE);
  min-height: 100vh;
}

.app-layout__main {
  flex: 1;
  padding: var(--spacing-8, 2rem);
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}

@media (max-width: 919px) {
  .app-layout {
    flex-direction: column;
  }
  
  .app-layout__main {
    padding: var(--spacing-4, 1rem);
  }
}
</style>
