<script setup lang="ts">
import { ref, onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

// Define the shape of the props
interface Patient {
  id: string;
  name: string;
  last_name: string;
  email: string;
}

interface Doctor {
  id: string;
  name: string;
  last_name: string;
}

interface Appointment {
  franja_start: string;
  franja_end: string;
  status: string;
}

interface Consultation {
  id: string;
  appointment_id: string;
  started_at: string | null;
  ended_at: string | null;
  patient: Patient;
  doctor: Doctor;
  appointment: Appointment;
  notes: any | null;
}

const props = defineProps<{
  consultation: Consultation;
}>();

// Form State
const formData = ref({
  motivo: '',
  sintomas: '',
  historial: '',
  medicinas: [] as { nombre: string; dosis: string; frecuencia: string }[],
  examen_fisico: '',
  diagnostico: '',
  tratamiento: '',
  seguimiento: {
    programar: false,
    intervalo: '1 semana'
  }
});

// ── Referral State ──
interface ReferralEntry {
  specialty_name: string;
  reason: string;
  priority: 'normal' | 'urgente';
  notes: string;
}

const referrals = ref<ReferralEntry[]>([]);
const referralSaving = ref(false);

const availableSpecialties = [
  'Cardiología', 'Dermatología', 'Pediatría', 'Neurología',
  'Traumatología', 'Psiquiatría', 'Ginecología', 'Endocrinología',
  'Gastroenterología', 'Neumología', 'Urología', 'Oftalmología',
  'Otorrinolaringología', 'Reumatología', 'Oncología',
];

const isSaving = ref(false);
const message = ref({ text: '', type: '' });

const followUpIntervals = [
  '1 semana', '2 semanas', '1 mes', '2 meses', '3 meses', '6 meses'
];

// Initialize form from existing notes if they exist
onMounted(() => {
  if (props.consultation.notes) {
    try {
      let notesData = props.consultation.notes;
      if (typeof notesData === 'string') {
        notesData = JSON.parse(notesData);
      }
      formData.value = { ...formData.value, ...notesData };
    } catch (e) {
      console.error('Error parsing notes:', e);
    }
  }
});

const addMedicine = () => {
  formData.value.medicinas.push({ nombre: '', dosis: '', frecuencia: '' });
};

const removeMedicine = (index: number) => {
  formData.value.medicinas.splice(index, 1);
};

const getCsrfToken = () => {
  const token = document.head.querySelector('meta[name="csrf-token"]');
  return token ? token.getAttribute('content') : '';
};

const showMessage = (text: string, type: 'success' | 'error') => {
  message.value = { text, type };
  setTimeout(() => {
    message.value = { text: '', type: '' };
  }, 4000);
};

const saveDraft = async () => {
  isSaving.value = true;
  try {
    const response = await fetch(`/api/consultations/${props.consultation.id}/form`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken() || '',
      },
      body: JSON.stringify(formData.value)
    });

    if (response.ok) {
      showMessage('Borrador guardado exitosamente', 'success');
    } else {
      throw new Error('Error saving draft');
    }
  } catch (error) {
    showMessage('Error al guardar el borrador', 'error');
  } finally {
    isSaving.value = false;
  }
};

const archiveConsultation = async () => {
  isSaving.value = true;
  try {
    const response = await fetch(`/api/consultations/${props.consultation.id}/archive`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken() || '',
      },
      body: JSON.stringify({
        ...formData.value,
        follow_up: formData.value.seguimiento.programar ? formData.value.seguimiento.intervalo : null
      })
    });

    if (response.ok) {
      showMessage('Consulta archivada exitosamente. Redirigiendo...', 'success');
      setTimeout(() => {
        window.location.href = '/appointments';
      }, 1500);
    } else {
      throw new Error('Error archiving consultation');
    }
  } catch (error) {
    showMessage('Error al archivar la consulta', 'error');
  } finally {
    isSaving.value = false;
  }
};

// Formatting helpers
const formatDateTime = (dateString: string) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return new Intl.DateTimeFormat('es-ES', {
    day: '2-digit', month: 'long', year: 'numeric',
    hour: '2-digit', minute: '2-digit'
  }).format(date);
};

// ── Referral Functions ──
const addReferral = () => {
  referrals.value.push({ specialty_name: '', reason: '', priority: 'normal', notes: '' });
};

const removeReferral = (index: number) => {
  referrals.value.splice(index, 1);
};

const saveReferrals = async () => {
  const valid = referrals.value.filter(r => r.specialty_name && r.reason.trim().length >= 3);
  if (valid.length === 0) {
    showMessage('Agrega al menos un referido con especialidad y motivo', 'error');
    return;
  }
  referralSaving.value = true;
  let successCount = 0;
  for (const ref of valid) {
    try {
      const res = await fetch('/api/referrals', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() || '' },
        body: JSON.stringify({ consultation_id: props.consultation.id, ...ref }),
      });
      if (res.ok) successCount++;
    } catch { /* skip */ }
  }
  referralSaving.value = false;
  if (successCount > 0) {
    showMessage(`${successCount} referido(s) guardado(s) exitosamente`, 'success');
  } else {
    showMessage('Error al guardar los referidos', 'error');
  }
};
</script>

<template>
  <AppLayout>
    <div class="consultation-view-container">
      
      <!-- Top Message Bar -->
      <transition name="fade">
        <div v-if="message.text" class="status-message" :class="message.type">
          {{ message.text }}
        </div>
      </transition>

      <!-- Header Section -->
      <div class="card header-card">
        <div class="header-info">
          <div class="patient-avatar">
            <i class="pi pi-user"></i>
          </div>
          <div>
            <h1>{{ consultation.patient.name }} {{ consultation.patient.last_name }}</h1>
            <p class="subtitle"><i class="pi pi-envelope"></i> {{ consultation.patient.email }}</p>
          </div>
        </div>
        <div class="header-meta">
          <div class="meta-item">
            <span class="meta-label">Fecha:</span>
            <span class="meta-value">{{ formatDateTime(consultation.appointment.franja_start) }}</span>
          </div>
          <div class="meta-item">
            <span class="meta-label">Estado:</span>
            <span class="status-badge" :class="consultation.appointment.status.toLowerCase()">
              {{ consultation.appointment.status }}
            </span>
          </div>
        </div>
      </div>

      <!-- Main Form Sections -->
      <div class="form-grid">
        
        <!-- Left Column -->
        <div class="column">
          <!-- Motivo de Consulta -->
          <div class="card section-card">
            <h2 class="section-title"><i class="pi pi-question-circle"></i> Motivo de consulta</h2>
            <textarea v-model="formData.motivo" rows="3" placeholder="Razón principal de la visita..."></textarea>
          </div>

          <!-- Síntomas -->
          <div class="card section-card">
            <h2 class="section-title"><i class="pi pi-list"></i> Síntomas</h2>
            <textarea v-model="formData.sintomas" rows="4" placeholder="Describa los síntomas presentados..."></textarea>
          </div>

          <!-- Historial Médico -->
          <div class="card section-card">
            <h2 class="section-title"><i class="pi pi-history"></i> Historial médico</h2>
            <textarea v-model="formData.historial" rows="4" placeholder="Alergias, cirugías previas, condiciones crónicas..."></textarea>
          </div>
          
          <!-- Examen Físico -->
          <div class="card section-card">
            <h2 class="section-title"><i class="pi pi-heart-fill"></i> Examen físico</h2>
            <textarea v-model="formData.examen_fisico" rows="4" placeholder="Hallazgos del examen físico, signos vitales..."></textarea>
          </div>
        </div>

        <!-- Right Column -->
        <div class="column">
          
          <!-- Medicinas Actuales -->
          <div class="card section-card">
            <div class="section-header-flex">
              <h2 class="section-title"><i class="pi pi-box"></i> Medicinas actuales</h2>
              <button @click="addMedicine" class="btn-outline btn-sm"><i class="pi pi-plus"></i> Añadir</button>
            </div>
            
            <div v-if="formData.medicinas.length === 0" class="empty-state">
              No hay medicamentos registrados.
            </div>
            
            <div class="medicine-list" v-else>
              <div v-for="(med, index) in formData.medicinas" :key="index" class="medicine-item">
                <input type="text" v-model="med.nombre" placeholder="Nombre" class="input-med">
                <input type="text" v-model="med.dosis" placeholder="Dosis" class="input-med">
                <input type="text" v-model="med.frecuencia" placeholder="Frecuencia" class="input-med">
                <button @click="removeMedicine(index)" class="btn-icon btn-danger"><i class="pi pi-trash"></i></button>
              </div>
            </div>
          </div>
          
          <!-- Diagnóstico -->
          <div class="card section-card highlight-card">
            <h2 class="section-title text-primary"><i class="pi pi-info-circle"></i> Diagnóstico</h2>
            <textarea v-model="formData.diagnostico" rows="3" placeholder="Diagnóstico médico clínico..."></textarea>
          </div>

          <!-- Tratamiento -->
          <div class="card section-card highlight-card">
            <h2 class="section-title text-primary"><i class="pi pi-file-edit"></i> Plan de tratamiento</h2>
            <textarea v-model="formData.tratamiento" rows="4" placeholder="Indicaciones, recetas, reposo médico..."></textarea>
          </div>

          <!-- Laboratorio (UI Only) -->
          <div class="card section-card">
            <h2 class="section-title"><i class="pi pi-file-pdf"></i> Resultados de laboratorio</h2>
            <div class="upload-area">
              <i class="pi pi-cloud-upload upload-icon"></i>
              <p>Arrastre archivos aquí o haga clic para subir</p>
              <button class="btn-outline btn-sm mt-2">Seleccionar archivo</button>
            </div>
          </div>
          
          <!-- Seguimiento -->
          <div class="card section-card bg-light">
            <h2 class="section-title"><i class="pi pi-calendar-plus"></i> Seguimiento</h2>
            
            <label class="toggle-container">
              <input type="checkbox" v-model="formData.seguimiento.programar">
              <span class="toggle-slider"></span>
              <span class="toggle-label">¿Programar cita de seguimiento?</span>
            </label>

            <div v-if="formData.seguimiento.programar" class="follow-up-options mt-3">
              <label>Agendar en:</label>
              <select v-model="formData.seguimiento.intervalo" class="w-full">
                <option v-for="opt in followUpIntervals" :key="opt" :value="opt">{{ opt }}</option>
              </select>
            </div>
          </div>
          <!-- Referir a Especialista -->
          <div class="card section-card referral-section">
            <div class="section-header-flex">
              <h2 class="section-title"><i class="pi pi-share-alt"></i> Referir a Especialista</h2>
              <button class="btn-outline btn-sm" @click="addReferral">
                <i class="pi pi-plus"></i> Agregar Referido
              </button>
            </div>

            <div v-if="referrals.length === 0" class="referral-empty">
              <i class="pi pi-info-circle"></i>
              <span>Si el paciente necesita atención especializada, agrega un referido aquí.</span>
            </div>

            <div v-for="(ref, idx) in referrals" :key="idx" class="referral-card">
              <div class="referral-card__header">
                <span class="referral-card__number">#{{ idx + 1 }}</span>
                <button class="referral-card__remove" @click="removeReferral(idx)" title="Eliminar referido">
                  <i class="pi pi-times"></i>
                </button>
              </div>
              <div class="referral-card__body">
                <div class="form-row">
                  <div class="form-group">
                    <label>Especialidad *</label>
                    <select v-model="ref.specialty_name" class="w-full">
                      <option value="" disabled>Seleccionar especialidad</option>
                      <option v-for="sp in availableSpecialties" :key="sp" :value="sp">{{ sp }}</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>Prioridad</label>
                    <select v-model="ref.priority" class="w-full">
                      <option value="normal">Normal</option>
                      <option value="urgente">🔴 Urgente</option>
                    </select>
                  </div>
                </div>
                <div class="form-group">
                  <label>Motivo de la referencia *</label>
                  <textarea v-model="ref.reason" rows="2" placeholder="Motivo clínico de la derivación..." class="w-full"></textarea>
                </div>
                <div class="form-group">
                  <label>Notas adicionales</label>
                  <textarea v-model="ref.notes" rows="1" placeholder="Observaciones opcionales..." class="w-full"></textarea>
                </div>
              </div>
            </div>

            <div v-if="referrals.length > 0" class="referral-actions">
              <button class="btn-primary btn-sm" @click="saveReferrals" :disabled="referralSaving">
                <i class="pi" :class="referralSaving ? 'pi-spin pi-spinner' : 'pi-check'"></i>
                {{ referralSaving ? 'Guardando...' : 'Guardar Referidos' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Actions Bar -->
      <div class="actions-bar card">
        <button class="btn-secondary" @click="saveDraft" :disabled="isSaving">
          <i class="pi pi-save" :class="{'pi-spin pi-spinner': isSaving}"></i> Guardar borrador
        </button>
        <button class="btn-primary" @click="archiveConsultation" :disabled="isSaving">
          <i class="pi pi-check-square"></i> Firmar y Archivar
        </button>
      </div>

    </div>
  </AppLayout>
</template>

<style scoped>
/* Base Variables & Theme */
:root {
  --color-primary: #0E5D52;
  --color-primary-light: #168a7b;
  --color-primary-dark: #0a4038;
  --color-bg: #f8fafc;
  --color-surface: #ffffff;
  --color-text: #1e293b;
  --color-text-muted: #64748b;
  --color-border: #e2e8f0;
  --color-success: #10b981;
  --color-danger: #ef4444;
  --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  --radius-md: 8px;
  --radius-lg: 12px;
}

/* Base Styles */
.consultation-view-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 1.5rem;
  color: var(--color-text, #1e293b);
  background-color: var(--color-bg, #f8fafc);
  font-family: 'Inter', system-ui, sans-serif;
}

.text-primary {
  color: var(--color-primary, #0E5D52) !important;
}

.bg-light {
  background-color: #f1f5f9 !important;
}

.mt-2 { margin-top: 0.5rem; }
.mt-3 { margin-top: 1rem; }
.w-full { width: 100%; }

/* Cards */
.card {
  background: var(--color-surface, #ffffff);
  border-radius: var(--radius-lg, 12px);
  box-shadow: var(--shadow-sm, 0 1px 2px 0 rgba(0,0,0,0.05));
  border: 1px solid var(--color-border, #e2e8f0);
  margin-bottom: 1.5rem;
}

.section-card {
  padding: 1.5rem;
  transition: box-shadow 0.2s ease;
}

.section-card:hover {
  box-shadow: var(--shadow-md, 0 4px 6px -1px rgba(0,0,0,0.1));
}

.highlight-card {
  border-top: 4px solid var(--color-primary, #0E5D52);
}

.section-title {
  font-size: 1.1rem;
  font-weight: 600;
  margin-top: 0;
  margin-bottom: 1rem;
  color: var(--color-text, #1e293b);
  display: flex;
  align-items: center;
  gap: 0.5rem;
  border-bottom: 1px solid var(--color-border, #e2e8f0);
  padding-bottom: 0.75rem;
}

.section-header-flex {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--color-border, #e2e8f0);
  padding-bottom: 0.75rem;
  margin-bottom: 1rem;
}
.section-header-flex .section-title {
  border-bottom: none;
  margin-bottom: 0;
  padding-bottom: 0;
}

/* Header specific */
.header-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem 2rem;
  background: linear-gradient(to right, #ffffff, #f8fafc);
  border-left: 5px solid var(--color-primary, #0E5D52);
}

.header-info {
  display: flex;
  align-items: center;
  gap: 1.5rem;
}

.patient-avatar {
  width: 60px;
  height: 60px;
  background-color: var(--color-primary, #0E5D52);
  color: white;
  border-radius: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
  font-size: 1.5rem;
}

.header-info h1 {
  margin: 0 0 0.25rem 0;
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--color-primary, #0E5D52);
}

.subtitle {
  margin: 0;
  color: var(--color-text-muted, #64748b);
  font-size: 0.95rem;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.header-meta {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  text-align: right;
}

.meta-item {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9rem;
}

.meta-label {
  color: var(--color-text-muted, #64748b);
  font-weight: 500;
}

.meta-value {
  font-weight: 600;
}

.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 99px;
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  background: #e2e8f0;
  color: #475569;
}

.status-badge.completada, .status-badge.completed { background: #dcfce7; color: #166534; }
.status-badge.pendiente, .status-badge.pending { background: #fef9c3; color: #854d0e; }

/* Grid Layout */
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
}

@media (max-width: 900px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
  .header-card {
    flex-direction: column;
    align-items: flex-start;
    gap: 1.5rem;
  }
  .header-meta {
    text-align: left;
    width: 100%;
  }
  .meta-item {
    justify-content: flex-start;
  }
}

/* Form Controls */
textarea, select, input[type="text"] {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid var(--color-border, #e2e8f0);
  border-radius: var(--radius-md, 8px);
  font-family: inherit;
  font-size: 0.95rem;
  resize: vertical;
  background-color: #f8fafc;
  transition: all 0.2s ease;
  box-sizing: border-box;
}

textarea:focus, select:focus, input:focus {
  outline: none;
  border-color: var(--color-primary, #0E5D52);
  box-shadow: 0 0 0 3px rgba(14, 93, 82, 0.1);
  background-color: #ffffff;
}

/* Dynamic List */
.medicine-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.medicine-item {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr auto;
  gap: 0.5rem;
  align-items: center;
}

.input-med {
  padding: 0.5rem;
  font-size: 0.9rem;
}

.empty-state {
  text-align: center;
  padding: 1.5rem;
  color: var(--color-text-muted, #64748b);
  background: #f8fafc;
  border-radius: var(--radius-md, 8px);
  border: 1px dashed var(--color-border, #e2e8f0);
  font-size: 0.9rem;
}

/* Upload Area */
.upload-area {
  border: 2px dashed var(--color-border, #e2e8f0);
  border-radius: var(--radius-md, 8px);
  padding: 2rem 1rem;
  text-align: center;
  color: var(--color-text-muted, #64748b);
  background-color: #f8fafc;
  transition: all 0.2s ease;
  cursor: pointer;
}

.upload-area:hover {
  border-color: var(--color-primary, #0E5D52);
  background-color: rgba(14, 93, 82, 0.02);
}

.upload-icon {
  font-size: 2.5rem;
  color: var(--color-primary, #0E5D52);
  margin-bottom: 0.5rem;
}

/* Toggle Switch */
.toggle-container {
  display: flex;
  align-items: center;
  cursor: pointer;
  user-select: none;
}

.toggle-container input {
  display: none;
}

.toggle-slider {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;
  background-color: #cbd5e1;
  border-radius: 34px;
  transition: .4s;
  margin-right: 10px;
}

.toggle-slider:before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  border-radius: 50%;
  transition: .4s;
}

.toggle-container input:checked + .toggle-slider {
  background-color: var(--color-primary, #0E5D52);
}

.toggle-container input:checked + .toggle-slider:before {
  transform: translateX(20px);
}

.toggle-label {
  font-weight: 500;
}

/* Buttons */
button {
  cursor: pointer;
  font-family: inherit;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  transition: all 0.2s ease;
}

button:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.btn-primary {
  background-color: var(--color-primary, #0E5D52);
  color: white;
  border: none;
  padding: 0.75rem 1.5rem;
  border-radius: var(--radius-md, 8px);
  font-size: 1rem;
  box-shadow: var(--shadow-sm);
}

.btn-primary:hover:not(:disabled) {
  background-color: var(--color-primary-dark, #0a4038);
  box-shadow: var(--shadow-md);
}

.btn-secondary {
  background-color: white;
  color: var(--color-text, #1e293b);
  border: 1px solid var(--color-border, #e2e8f0);
  padding: 0.75rem 1.5rem;
  border-radius: var(--radius-md, 8px);
  font-size: 1rem;
}

.btn-secondary:hover:not(:disabled) {
  background-color: #f1f5f9;
}

.btn-outline {
  background: transparent;
  color: var(--color-primary, #0E5D52);
  border: 1px solid var(--color-primary, #0E5D52);
  padding: 0.5rem 1rem;
  border-radius: var(--radius-md, 8px);
}

.btn-outline:hover:not(:disabled) {
  background: rgba(14, 93, 82, 0.05);
}

.btn-sm {
  padding: 0.4rem 0.8rem;
  font-size: 0.85rem;
}

.btn-icon {
  width: 36px;
  height: 36px;
  border-radius: var(--radius-md, 8px);
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-danger {
  background-color: #fee2e2;
  color: var(--color-danger, #ef4444);
}

.btn-danger:hover {
  background-color: #fecaca;
}

/* Actions Bar */
.actions-bar {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding: 1.25rem 2rem;
  position: sticky;
  bottom: 1rem;
  z-index: 10;
  box-shadow: 0 -4px 10px rgba(0,0,0,0.05);
}

@media (max-width: 600px) {
  .actions-bar {
    flex-direction: column;
    padding: 1rem;
  }
  .actions-bar button {
    width: 100%;
  }
  .medicine-item {
    grid-template-columns: 1fr;
  }
  .medicine-item .btn-icon {
    width: 100%;
    margin-top: 0.5rem;
  }
}

/* Toast Messages */
.status-message {
  position: fixed;
  top: 1.5rem;
  right: 1.5rem;
  padding: 1rem 1.5rem;
  border-radius: var(--radius-md, 8px);
  box-shadow: var(--shadow-md);
  z-index: 100;
  font-weight: 500;
}

.status-message.success {
  background-color: #ecfdf5;
  color: #065f46;
  border-left: 4px solid var(--color-success, #10b981);
}

.status-message.error {
  background-color: #fef2f2;
  color: #991b1b;
  border-left: 4px solid var(--color-danger, #ef4444);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease, transform 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-20px);
}

/* Referral Section */
.referral-section {
  border-top: 4px solid #0E9AA7;
}
.referral-empty {
  display: flex; align-items: center; gap: 8px;
  padding: 16px; color: var(--color-text-muted, #64748b);
  font-size: 0.9rem; font-style: italic;
  background: #f8fafc; border-radius: 8px;
}
.referral-card {
  border: 1px solid var(--color-border, #e2e8f0);
  border-radius: 10px; margin-bottom: 12px;
  overflow: hidden; transition: box-shadow 0.2s;
}
.referral-card:hover { box-shadow: 0 2px 8px rgba(14, 154, 167, 0.12); }
.referral-card__header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 8px 14px; background: #f0fdfa;
  border-bottom: 1px solid #e2e8f0;
}
.referral-card__number {
  font-size: 0.82rem; font-weight: 700; color: #0E5D52;
}
.referral-card__remove {
  background: none; border: none; color: var(--color-danger, #ef4444);
  cursor: pointer; padding: 4px; border-radius: 4px;
}
.referral-card__remove:hover { background: #fef2f2; }
.referral-card__body { padding: 14px; display: flex; flex-direction: column; gap: 10px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 4px; }
.form-group label {
  font-size: 0.82rem; font-weight: 600; color: var(--color-text, #1e293b);
}
.referral-actions {
  display: flex; justify-content: flex-end; margin-top: 8px;
}
</style>
