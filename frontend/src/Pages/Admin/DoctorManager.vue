<!--
  ====================================================================
  DoctorManager — Admin panel for managing doctors (CRUD + verify)
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { getInitials, getAvatarColor } from '@/lib/mockData';

interface Doctor {
  profile_id: string;
  user_id: string;
  name: string;
  last_name: string;
  email: string;
  timezone: string;
  status: string;
  consultation_fee: number;
  description: string;
  years_experience: number;
  university: string;
  license_number: string;
  specialties: string[];
  specialty_ids: string[];
  created_at: string;
}

interface Specialty {
  id: string;
  name: string;
}

const loading = ref(true);
const doctors = ref<Doctor[]>([]);
const specialties = ref<Specialty[]>([]);
const error = ref('');
const successMsg = ref('');
const showForm = ref(false);
const filterStatus = ref('all');

// Edit modal
const editModal = ref(false);
const editDoc = ref<Doctor | null>(null);
const editForm = ref({
  status: 'approved',
  consultation_fee: 0,
  description: '',
  years_experience: 0,
  university: '',
});
const editSaving = ref(false);

// Form state
const form = ref({
  name: '',
  last_name: '',
  email: '',
  password: '',
  timezone: 'America/Santo_Domingo',
  license_number: '',
  consultation_fee: 50000,
  description: '',
  years_experience: 0,
  university: '',
  specialty_ids: [] as string[],
  status: 'approved',
});

const saving = ref(false);

const filteredDoctors = computed(() => {
  if (filterStatus.value === 'all') return doctors.value;
  return doctors.value.filter(d => d.status === filterStatus.value);
});

const statusCounts = computed(() => {
  const counts = { all: 0, pending: 0, approved: 0, rejected: 0 };
  counts.all = doctors.value.length;
  doctors.value.forEach(d => {
    if (d.status in counts) counts[d.status as keyof typeof counts]++;
  });
  return counts;
});

function getCsrfToken(): string {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

async function fetchDoctors() {
  loading.value = true;
  error.value = '';
  try {
    const res = await fetch('/api/admin/doctors', {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    doctors.value = data.data || [];
  } catch (e: any) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
}

async function fetchSpecialties() {
  try {
    const { usePage } = await import('@inertiajs/vue3');
    const page = usePage();
    const booking = (page.props as any)?.booking;
    if (booking?.specialties?.length) {
      specialties.value = booking.specialties;
    }
  } catch (e) {
    // silent
  }
}

onMounted(async () => {
  await Promise.all([fetchDoctors(), fetchSpecialties()]);
});

async function createDoctor() {
  if (!form.value.name || !form.value.email || !form.value.license_number || form.value.specialty_ids.length === 0) {
    error.value = 'Completa todos los campos obligatorios';
    return;
  }

  saving.value = true;
  error.value = '';

  try {
    const res = await fetch('/api/admin/doctors', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      body: JSON.stringify(form.value),
    });

    if (res.ok) {
      successMsg.value = '✅ Médico creado exitosamente';
      showForm.value = false;
      resetForm();
      await fetchDoctors();
      setTimeout(() => { successMsg.value = ''; }, 4000);
    } else {
      const data = await res.json().catch(() => ({}));
      error.value = data.message || 'Error al crear médico';
    }
  } catch (e: any) {
    error.value = e.message;
  } finally {
    saving.value = false;
  }
}

function openEditModal(doc: Doctor) {
  editDoc.value = doc;
  editForm.value = {
    status: doc.status,
    consultation_fee: doc.consultation_fee,
    description: doc.description || '',
    years_experience: doc.years_experience || 0,
    university: doc.university || '',
  };
  editModal.value = true;
}

async function saveEdit() {
  if (!editDoc.value) return;
  editSaving.value = true;
  error.value = '';

  try {
    // Update status
    const res = await fetch(`/api/admin/doctors/${editDoc.value.profile_id}/status`, {
      method: 'PATCH',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      body: JSON.stringify({ status: editForm.value.status }),
    });

    if (res.ok) {
      successMsg.value = '✅ Médico actualizado exitosamente';
      editModal.value = false;
      await fetchDoctors();
      setTimeout(() => { successMsg.value = ''; }, 4000);
    } else {
      const data = await res.json().catch(() => ({}));
      error.value = data.message || 'Error al actualizar';
    }
  } catch (e: any) {
    error.value = e.message;
  } finally {
    editSaving.value = false;
  }
}

function resetForm() {
  form.value = {
    name: '', last_name: '', email: '', password: '',
    timezone: 'America/Santo_Domingo', license_number: '',
    consultation_fee: 50000, description: '', years_experience: 0,
    university: '', specialty_ids: [], status: 'approved',
  };
}

function toggleSpecialty(id: string) {
  const idx = form.value.specialty_ids.indexOf(id);
  if (idx >= 0) form.value.specialty_ids.splice(idx, 1);
  else form.value.specialty_ids.push(id);
}

function getStatusLabel(status: string) {
  return { pending: 'Pendiente', approved: 'Aprobado', rejected: 'Rechazado' }[status] || status;
}

function getStatusClass(status: string) {
  return {
    pending: 'badge--warning',
    approved: 'badge--success',
    rejected: 'badge--danger',
  }[status] || '';
}

function formatDate(dateStr: string) {
  return new Date(dateStr).toLocaleDateString('es-ES', {
    year: 'numeric', month: 'short', day: 'numeric',
  });
}
</script>

<template>
  <AppLayout>
    <div class="doc-mgr">
      <header class="doc-mgr__header">
        <div>
          <h1 class="doc-mgr__title">Verificación de Médicos</h1>
          <p class="doc-mgr__subtitle">Gestiona y verifica médicos de la plataforma</p>
        </div>
        <button class="btn-primary" @click="showForm = !showForm">
          <i class="pi" :class="showForm ? 'pi-times' : 'pi-plus'"></i>
          {{ showForm ? 'Cancelar' : 'Nuevo Médico' }}
        </button>
      </header>

      <!-- Alerts -->
      <Transition name="fade">
        <div v-if="successMsg" class="alert alert--success">{{ successMsg }}</div>
      </Transition>
      <Transition name="fade">
        <div v-if="error" class="alert alert--error">
          {{ error }}
          <button class="alert__close" @click="error = ''">×</button>
        </div>
      </Transition>

      <!-- Create Doctor Form -->
      <Transition name="slide-down">
        <div v-if="showForm" class="form-card">
          <h3 class="form-card__title">Registrar Nuevo Médico</h3>

          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Nombre *</label>
              <input v-model="form.name" class="form-input" placeholder="Nombre" />
            </div>
            <div class="form-group">
              <label class="form-label">Apellido *</label>
              <input v-model="form.last_name" class="form-input" placeholder="Apellido" />
            </div>
            <div class="form-group">
              <label class="form-label">Correo electrónico *</label>
              <input v-model="form.email" type="email" class="form-input" placeholder="doctor@email.com" />
            </div>
            <div class="form-group">
              <label class="form-label">Contraseña *</label>
              <input v-model="form.password" type="password" class="form-input" placeholder="Mínimo 8 caracteres" />
            </div>
            <div class="form-group">
              <label class="form-label">Número de Licencia *</label>
              <input v-model="form.license_number" class="form-input" placeholder="MED-XXXXXXXX" />
            </div>
            <div class="form-group">
              <label class="form-label">Universidad</label>
              <input v-model="form.university" class="form-input" placeholder="Universidad" />
            </div>
            <div class="form-group">
              <label class="form-label">Años de experiencia</label>
              <input v-model.number="form.years_experience" type="number" min="0" class="form-input" />
            </div>
            <div class="form-group">
              <label class="form-label">Tarifa consulta (COP)</label>
              <input v-model.number="form.consultation_fee" type="number" min="0" class="form-input" />
            </div>
            <div class="form-group">
              <label class="form-label">Zona horaria</label>
              <select v-model="form.timezone" class="form-select">
                <option value="America/Santo_Domingo">Santo Domingo (AST)</option>
                <option value="America/Bogota">Bogotá (COT)</option>
                <option value="America/Mexico_City">México (CST)</option>
                <option value="America/Argentina/Buenos_Aires">Buenos Aires (ART)</option>
                <option value="America/Tegucigalpa">Tegucigalpa (CST)</option>
                <option value="America/Santiago">Santiago (CLT)</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Estado inicial</label>
              <select v-model="form.status" class="form-select">
                <option value="approved">Aprobado</option>
                <option value="pending">Pendiente</option>
              </select>
            </div>
          </div>

          <div class="form-group" style="margin-top: 1rem;">
            <label class="form-label">Descripción</label>
            <textarea v-model="form.description" class="form-input form-textarea" placeholder="Breve descripción del médico..." rows="3"></textarea>
          </div>

          <div class="form-group" style="margin-top: 1rem;">
            <label class="form-label">Especialidades *</label>
            <div class="specialty-selector">
              <button
                v-for="spec in specialties"
                :key="spec.id"
                type="button"
                :class="['spec-btn', { 'spec-btn--active': form.specialty_ids.includes(spec.id) }]"
                @click="toggleSpecialty(spec.id)"
              >
                {{ spec.name }}
              </button>
            </div>
          </div>

          <div class="form-actions">
            <button class="btn-secondary" @click="showForm = false">Cancelar</button>
            <button class="btn-primary" @click="createDoctor" :disabled="saving">
              {{ saving ? 'Creando...' : '👨‍⚕️ Crear Médico' }}
            </button>
          </div>
        </div>
      </Transition>

      <!-- Status Filter Tabs -->
      <div class="filter-tabs">
        <button
          v-for="tab in [
            { key: 'all', label: 'Todos' },
            { key: 'pending', label: '⏳ Pendientes' },
            { key: 'approved', label: '✅ Aprobados' },
            { key: 'rejected', label: '❌ Rechazados' },
          ]"
          :key="tab.key"
          :class="['filter-tab', { 'filter-tab--active': filterStatus === tab.key }]"
          @click="filterStatus = tab.key"
        >
          {{ tab.label }}
          <span class="filter-tab__count">{{ statusCounts[tab.key as keyof typeof statusCounts] }}</span>
        </button>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="loading-state">
        <i class="pi pi-spin pi-spinner" style="font-size: 2rem; color: var(--color-primary, #0E5D52);"></i>
        <p>Cargando médicos...</p>
      </div>

      <!-- Empty state -->
      <div v-else-if="filteredDoctors.length === 0" class="empty-state">
        <i class="pi pi-users" style="font-size: 3rem; color: #9CA3AF;"></i>
        <p>No hay médicos en esta categoría.</p>
      </div>

      <!-- Doctor Cards -->
      <div v-else class="doctor-grid">
        <div
          v-for="doc in filteredDoctors"
          :key="doc.profile_id"
          :class="['doctor-card', `doctor-card--${doc.status}`]"
        >
          <div class="doctor-card__top">
            <div class="doctor-avatar" :style="{ backgroundColor: getAvatarColor(doc.name + ' ' + doc.last_name) }">
              {{ getInitials(doc.name + ' ' + doc.last_name) }}
            </div>
            <div class="doctor-card__info">
              <h3 class="doctor-card__name">{{ doc.name }} {{ doc.last_name }}</h3>
              <span class="doctor-card__email">{{ doc.email }}</span>
              <span :class="['badge', getStatusClass(doc.status)]">{{ getStatusLabel(doc.status) }}</span>
            </div>
          </div>

          <div class="doctor-card__details">
            <div v-if="doc.specialties.length" class="doctor-card__specs">
              <span v-for="spec in doc.specialties" :key="spec" class="spec-tag">{{ spec }}</span>
            </div>
            <div class="doctor-card__meta">
              <span v-if="doc.license_number"><i class="pi pi-id-card"></i> {{ doc.license_number }}</span>
              <span v-if="doc.university"><i class="pi pi-building"></i> {{ doc.university }}</span>
              <span v-if="doc.years_experience"><i class="pi pi-clock"></i> {{ doc.years_experience }} años exp.</span>
              <span><i class="pi pi-calendar"></i> {{ formatDate(doc.created_at) }}</span>
            </div>
          </div>

          <div class="doctor-card__actions">
            <button class="action-btn action-btn--edit" @click="openEditModal(doc)">
              <i class="pi pi-pencil"></i> Editar Ficha
            </button>
          </div>
        </div>
      </div>
      <!-- Edit Doctor Modal -->
      <Teleport to="body">
        <Transition name="fade">
          <div v-if="editModal && editDoc" class="modal-overlay" @click.self="editModal = false">
            <div class="modal-card">
              <div class="modal-header">
                <div class="modal-header__avatar" :style="{ backgroundColor: getAvatarColor(editDoc.name + ' ' + editDoc.last_name) }">
                  {{ getInitials(editDoc.name + ' ' + editDoc.last_name) }}
                </div>
                <div>
                  <h3 class="modal-title">{{ editDoc.name }} {{ editDoc.last_name }}</h3>
                  <p class="modal-subtitle">{{ editDoc.email }}</p>
                </div>
                <button class="modal-close" @click="editModal = false">×</button>
              </div>

              <div class="modal-body">
                <div class="modal-section">
                  <h4>📋 Información</h4>
                  <div class="info-grid">
                    <div class="info-item"><span class="info-label">Licencia</span><span class="info-value">{{ editDoc.license_number }}</span></div>
                    <div class="info-item"><span class="info-label">Especialidades</span><span class="info-value">{{ editDoc.specialties.join(', ') || '—' }}</span></div>
                    <div class="info-item"><span class="info-label">Zona horaria</span><span class="info-value">{{ editDoc.timezone }}</span></div>
                    <div class="info-item"><span class="info-label">Registrado</span><span class="info-value">{{ formatDate(editDoc.created_at) }}</span></div>
                  </div>
                </div>

                <div class="modal-section">
                  <h4>⚙️ Configuración</h4>
                  <div class="edit-grid">
                    <div class="form-group">
                      <label class="form-label">Estado</label>
                      <div class="status-toggle">
                        <button
                          v-for="opt in [
                            { value: 'approved', label: '✅ Aprobado', cls: 'st--approved' },
                            { value: 'pending', label: '⏳ Pendiente', cls: 'st--pending' },
                            { value: 'rejected', label: '❌ Rechazado', cls: 'st--rejected' },
                          ]"
                          :key="opt.value"
                          :class="['st-btn', opt.cls, { 'st-btn--active': editForm.status === opt.value }]"
                          @click="editForm.status = opt.value"
                        >{{ opt.label }}</button>
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Universidad</label>
                      <input v-model="editForm.university" class="form-input" />
                    </div>
                    <div class="form-group">
                      <label class="form-label">Años de experiencia</label>
                      <input v-model.number="editForm.years_experience" type="number" min="0" class="form-input" />
                    </div>
                    <div class="form-group">
                      <label class="form-label">Tarifa consulta</label>
                      <input v-model.number="editForm.consultation_fee" type="number" min="0" class="form-input" />
                    </div>
                  </div>
                  <div class="form-group" style="margin-top: 12px;">
                    <label class="form-label">Descripción</label>
                    <textarea v-model="editForm.description" class="form-input form-textarea" rows="3"></textarea>
                  </div>
                </div>
              </div>

              <div class="modal-footer">
                <button class="btn-secondary" @click="editModal = false">Cancelar</button>
                <button class="btn-primary" @click="saveEdit" :disabled="editSaving">
                  {{ editSaving ? 'Guardando...' : '💾 Guardar Cambios' }}
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </Teleport>
    </div>
  </AppLayout>
</template>

<style scoped>
.doc-mgr { max-width: 1100px; margin: 0 auto; padding: 1rem; }

.doc-mgr__header {
  display: flex; justify-content: space-between; align-items: flex-start;
  margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;
}
.doc-mgr__title { font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0; }
.doc-mgr__subtitle { font-size: 0.875rem; color: #6B7280; margin: 4px 0 0; }

.btn-primary {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 10px 20px; background: var(--color-primary, #0E5D52); color: #FFF;
  border: none; border-radius: 8px; font-size: 0.9rem; font-weight: 600;
  cursor: pointer; transition: all 0.2s;
}
.btn-primary:hover:not(:disabled) { filter: brightness(1.15); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-secondary {
  padding: 10px 20px; background: #F3F4F6; color: #374151;
  border: 1px solid #D1D5DB; border-radius: 8px; font-size: 0.9rem;
  font-weight: 500; cursor: pointer; transition: all 0.2s;
}
.btn-secondary:hover { background: #E5E7EB; }

.alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; display: flex; justify-content: space-between; align-items: center; }
.alert--success { background: #D1FAE5; color: #065F46; }
.alert--error { background: #FEE2E2; color: #991B1B; }
.alert__close { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: inherit; }

.form-card { background: #FFF; border: 1px solid #E5E7EB; border-radius: 12px; padding: 24px; margin-bottom: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.form-card__title { margin: 0 0 1rem; font-size: 1.1rem; color: #111827; }

.form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; }
.form-group { display: flex; flex-direction: column; gap: 4px; }
.form-label { font-size: 0.82rem; font-weight: 600; color: #374151; }
.form-input, .form-select {
  padding: 8px 12px; border: 1px solid #D1D5DB; border-radius: 8px;
  font-size: 0.9rem; outline: none; transition: border-color 0.2s;
}
.form-input:focus, .form-select:focus { border-color: var(--color-primary, #0E5D52); }
.form-textarea { resize: vertical; min-height: 70px; }

.specialty-selector { display: flex; flex-wrap: wrap; gap: 6px; }
.spec-btn {
  padding: 6px 14px; border: 1px solid #D1D5DB; background: #FFF;
  color: #374151; border-radius: 20px; font-size: 0.82rem; font-weight: 500;
  cursor: pointer; transition: all 0.2s;
}
.spec-btn--active { background: var(--color-primary, #0E5D52); color: #FFF; border-color: var(--color-primary, #0E5D52); }

.form-actions { margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 10px; }

.filter-tabs { display: flex; gap: 4px; margin-bottom: 1.5rem; flex-wrap: wrap; }
.filter-tab {
  padding: 8px 16px; background: #F3F4F6; border: 1px solid transparent;
  border-radius: 8px; font-size: 0.85rem; color: #374151; cursor: pointer;
  transition: all 0.2s; display: flex; align-items: center; gap: 6px;
}
.filter-tab--active { background: var(--color-primary, #0E5D52); color: #FFF; }
.filter-tab__count {
  background: rgba(0,0,0,0.1); padding: 1px 8px; border-radius: 10px;
  font-size: 0.75rem; font-weight: 700;
}
.filter-tab--active .filter-tab__count { background: rgba(255,255,255,0.25); }

.loading-state, .empty-state {
  display: flex; flex-direction: column; align-items: center;
  gap: 12px; padding: 3rem; color: #6B7280;
}

.doctor-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1rem; }

.doctor-card {
  background: #FFF; border: 1px solid #E5E7EB; border-radius: 12px;
  padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);
  transition: transform 0.2s, box-shadow 0.2s;
  display: flex; flex-direction: column; gap: 12px;
}
.doctor-card:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
.doctor-card--pending { border-left: 4px solid #F59E0B; }
.doctor-card--approved { border-left: 4px solid #10B981; }
.doctor-card--rejected { border-left: 4px solid #EF4444; }

.doctor-card__top { display: flex; align-items: center; gap: 12px; }
.doctor-avatar {
  width: 48px; height: 48px; border-radius: 50%; display: flex;
  align-items: center; justify-content: center; color: #FFF;
  font-weight: 700; font-size: 0.9rem; flex-shrink: 0;
}
.doctor-card__info { display: flex; flex-direction: column; gap: 2px; flex: 1; min-width: 0; }
.doctor-card__name { margin: 0; font-size: 1rem; font-weight: 600; color: #111827; }
.doctor-card__email { font-size: 0.8rem; color: #6B7280; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.badge {
  display: inline-block; padding: 2px 10px; border-radius: 10px;
  font-size: 0.72rem; font-weight: 600; width: fit-content; margin-top: 2px;
}
.badge--success { background: #D1FAE5; color: #065F46; }
.badge--warning { background: #FEF3C7; color: #92400E; }
.badge--danger { background: #FEE2E2; color: #991B1B; }

.doctor-card__details { display: flex; flex-direction: column; gap: 8px; }
.doctor-card__specs { display: flex; flex-wrap: wrap; gap: 4px; }
.spec-tag {
  padding: 2px 10px; background: #EFF6FF; color: #1D4ED8;
  border-radius: 10px; font-size: 0.75rem; font-weight: 500;
}

.doctor-card__meta {
  display: flex; flex-wrap: wrap; gap: 10px;
  font-size: 0.78rem; color: #6B7280;
}
.doctor-card__meta span { display: flex; align-items: center; gap: 4px; }
.doctor-card__meta i { font-size: 0.75rem; }

.doctor-card__actions { display: flex; gap: 6px; margin-top: auto; padding-top: 8px; border-top: 1px solid #F3F4F6; }
.action-btn {
  flex: 1; padding: 8px 10px; border: 1px solid; border-radius: 8px;
  font-size: 0.82rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
  display: flex; align-items: center; justify-content: center; gap: 6px;
}
.action-btn--edit { background: #F0F9FF; color: #0369A1; border-color: #7DD3FC; }
.action-btn--edit:hover { background: #E0F2FE; }

/* Modal */
.modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex;
  align-items: center; justify-content: center; z-index: 9999; padding: 1rem;
}
.modal-card {
  background: #FFF; border-radius: 16px; width: 100%;
  max-width: 560px; max-height: 90vh; overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.modal-header {
  display: flex; align-items: center; gap: 12px; padding: 24px 24px 0;
}
.modal-header__avatar {
  width: 48px; height: 48px; border-radius: 50%; display: flex;
  align-items: center; justify-content: center; color: #FFF;
  font-weight: 700; font-size: 0.9rem; flex-shrink: 0;
}
.modal-title { margin: 0; font-size: 1.1rem; color: #111827; }
.modal-subtitle { margin: 2px 0 0; font-size: 0.82rem; color: #6B7280; }
.modal-close {
  margin-left: auto; background: none; border: none; font-size: 1.5rem;
  color: #9CA3AF; cursor: pointer; line-height: 1;
}
.modal-close:hover { color: #374151; }

.modal-body { padding: 20px 24px; }
.modal-section { margin-bottom: 20px; }
.modal-section h4 { margin: 0 0 10px; font-size: 0.95rem; color: #111827; }

.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.info-item { background: #F9FAFB; border-radius: 8px; padding: 8px 12px; }
.info-label { display: block; font-size: 0.72rem; color: #6B7280; font-weight: 500; text-transform: uppercase; letter-spacing: 0.03em; }
.info-value { font-size: 0.88rem; color: #111827; font-weight: 500; }

.edit-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

.status-toggle { display: flex; gap: 4px; }
.st-btn {
  flex: 1; padding: 8px 6px; border: 2px solid #E5E7EB; border-radius: 8px;
  font-size: 0.78rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
  background: #FFF; color: #374151;
}
.st-btn--active.st--approved { background: #D1FAE5; border-color: #10B981; color: #065F46; }
.st-btn--active.st--pending { background: #FEF3C7; border-color: #F59E0B; color: #92400E; }
.st-btn--active.st--rejected { background: #FEE2E2; border-color: #EF4444; color: #991B1B; }
.st-btn:hover { border-color: #9CA3AF; }

.modal-footer {
  padding: 16px 24px 24px; display: flex; justify-content: flex-end; gap: 10px;
  border-top: 1px solid #F3F4F6;
}

.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.slide-down-enter-active, .slide-down-leave-active { transition: all 0.3s; }
.slide-down-enter-from, .slide-down-leave-to { transform: translateY(-10px); opacity: 0; }

@media (max-width: 768px) {
  .doctor-grid { grid-template-columns: 1fr; }
  .doc-mgr__header { flex-direction: column; }
  .filter-tabs { overflow-x: auto; flex-wrap: nowrap; }
  .info-grid, .edit-grid { grid-template-columns: 1fr; }
  .status-toggle { flex-direction: column; }
}
</style>
