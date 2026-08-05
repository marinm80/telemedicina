<!--
  ====================================================================
  PreConsultation â€” Cuestionario pre-consulta de 8 secciones
  AUTHOR: Rafael MarÃ­n Â· PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
  RF-13 Cuestionario Pre-consulta
  Ruta: /appointments/:id/pre-consultation (autenticado, paciente)
  Endpoint: POST /api/appointments/{id}/pre-consultation
  ====================================================================
-->
<script setup lang="ts">
import { ref, computed, type Ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { getCsrfToken } from '@/lib/appointmentHelpers';
import type { PreConsultationPayload } from '@/types/api.types';

const props = defineProps<{
  appointmentId: string;
}>();

// â”€â”€ Wizard state â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const currentSection = ref(1);
const totalSections = 8;
const isSubmitting = ref(false);
const submitSuccess = ref(false);
const submitError = ref('');

// â”€â”€ Form data (8 secciones) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const generalInfo = ref({
  full_name: '',
  birth_date: '',
  phone: '',
});

const currentSymptoms = ref({
  symptoms: '',
  onset_date: '',
  pain_level: 5,
});

const medicalHistory = ref({
  chronic_diseases: [] as string[],
  allergies: [] as string[],
});

const familyHistory = ref({
  hereditary_diseases: [] as string[],
});

const lifestyle = ref({
  smoking: 'no',
  alcohol: 'no',
});

const reproductiveData = ref<Record<string, unknown>>({});
const warningSigns = ref<string[]>([]);
const additionalDocs = ref<string[]>([]);

// â”€â”€ Input helpers for arrays â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const newDisease = ref('');
const newAllergy = ref('');
const newHereditaryDisease = ref('');
const newWarningSign = ref('');
const newDocUrl = ref('');

function addToList(list: string[], value: string, resetFn: () => void) {
  const trimmed = value.trim();
  if (trimmed && !list.includes(trimmed)) {
    list.push(trimmed);
    resetFn();
  }
}

function removeFromList(list: string[], index: number) {
  list.splice(index, 1);
}

// â”€â”€ SecciÃ³n info â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const sections = [
  { num: 1, label: 'Datos Generales', icon: 'pi-user' },
  { num: 2, label: 'SÃ­ntomas Actuales', icon: 'pi-heart' },
  { num: 3, label: 'Historial MÃ©dico', icon: 'pi-file' },
  { num: 4, label: 'Historial Familiar', icon: 'pi-users' },
  { num: 5, label: 'Estilo de Vida', icon: 'pi-sun' },
  { num: 6, label: 'Datos Reproductivos', icon: 'pi-shield' },
  { num: 7, label: 'SeÃ±ales de Alerta', icon: 'pi-exclamation-triangle' },
  { num: 8, label: 'Documentos Adicionales', icon: 'pi-paperclip' },
];

const progress = computed(() => Math.round((currentSection.value / totalSections) * 100));

// â”€â”€ Navigation â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function nextSection() {
  if (currentSection.value < totalSections) currentSection.value++;
}

function prevSection() {
  if (currentSection.value > 1) currentSection.value--;
}

function goToSection(num: number) {
  currentSection.value = num;
}

// â”€â”€ Submit â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
async function submitQuestionnaire() {
  isSubmitting.value = true;
  submitError.value = '';

  const payload: PreConsultationPayload = {
    general_info: { ...generalInfo.value },
    current_symptoms: { ...currentSymptoms.value },
    medical_history: {
      chronic_diseases: [...medicalHistory.value.chronic_diseases],
      allergies: [...medicalHistory.value.allergies],
    },
    family_history: {
      hereditary_diseases: [...familyHistory.value.hereditary_diseases],
    },
    lifestyle: { ...lifestyle.value },
    reproductive_data: { ...reproductiveData.value },
    warning_signs: [...warningSigns.value],
    additional_docs: [...additionalDocs.value],
  };

  try {
    const res = await fetch(`/api/appointments/${props.appointmentId}/pre-consultation`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    });

    if (res.ok) {
      submitSuccess.value = true;
    } else if (res.status === 422) {
      const json = await res.json();
      submitError.value = json.message ?? 'Error de validaciÃ³n.';
    } else {
      submitError.value = 'Error al enviar el cuestionario.';
    }
  } catch {
    submitError.value = 'Error de red. Verifica tu conexiÃ³n.';
  } finally {
    isSubmitting.value = false;
  }
}
</script>

<template>
  <AppLayout>
    <div class="precon">
      <h1 class="precon__title">
        <i class="pi pi-clipboard" aria-hidden="true" />
        Cuestionario Pre-Consulta
      </h1>

      <!-- Success state -->
      <div v-if="submitSuccess" class="precon__success" role="status">
        <i class="pi pi-check-circle precon__success-icon" aria-hidden="true" />
        <h2>Â¡Cuestionario Enviado!</h2>
        <p>Tu mÃ©dico revisarÃ¡ esta informaciÃ³n antes de la consulta.</p>
      </div>

      <template v-else>
        <!-- Progress bar -->
        <div class="precon__progress">
          <div class="precon__progress-bar" :style="{ width: progress + '%' }" />
        </div>
        <p class="precon__progress-label">SecciÃ³n {{ currentSection }} de {{ totalSections }}</p>

        <!-- Section nav pills -->
        <div class="precon__nav">
          <button
            v-for="sec in sections"
            :key="sec.num"
            type="button"
            class="precon__pill"
            :class="{
              'precon__pill--active': sec.num === currentSection,
              'precon__pill--done': sec.num < currentSection,
            }"
            @click="goToSection(sec.num)"
          >
            <i :class="'pi ' + sec.icon" aria-hidden="true" />
            <span class="precon__pill-label">{{ sec.label }}</span>
          </button>
        </div>

        <!-- Section 1: Datos Generales -->
        <div v-show="currentSection === 1" class="precon__section">
          <h2 class="precon__section-title">Datos Generales</h2>
          <div class="precon__field">
            <label for="full_name">Nombre Completo</label>
            <input id="full_name" v-model="generalInfo.full_name" type="text" class="precon__input" required />
          </div>
          <div class="precon__field">
            <label for="birth_date">Fecha de Nacimiento</label>
            <input id="birth_date" v-model="generalInfo.birth_date" type="date" class="precon__input" required />
          </div>
          <div class="precon__field">
            <label for="phone">TelÃ©fono</label>
            <input id="phone" v-model="generalInfo.phone" type="tel" class="precon__input" />
          </div>
        </div>

        <!-- Section 2: SÃ­ntomas Actuales -->
        <div v-show="currentSection === 2" class="precon__section">
          <h2 class="precon__section-title">SÃ­ntomas Actuales</h2>
          <div class="precon__field">
            <label for="symptoms">Describa sus sÃ­ntomas</label>
            <textarea id="symptoms" v-model="currentSymptoms.symptoms" class="precon__textarea" rows="4" />
          </div>
          <div class="precon__field">
            <label for="onset_date">Â¿Desde cuÃ¡ndo los presenta?</label>
            <input id="onset_date" v-model="currentSymptoms.onset_date" type="date" class="precon__input" />
          </div>
          <div class="precon__field">
            <label for="pain_level">Nivel de dolor (1-10): <strong>{{ currentSymptoms.pain_level }}</strong></label>
            <input
              id="pain_level"
              v-model.number="currentSymptoms.pain_level"
              type="range" min="1" max="10" step="1"
              class="precon__range"
            />
            <div class="precon__range-labels">
              <span>Leve</span><span>Moderado</span><span>Severo</span>
            </div>
          </div>
        </div>

        <!-- Section 3: Historial MÃ©dico -->
        <div v-show="currentSection === 3" class="precon__section">
          <h2 class="precon__section-title">Historial MÃ©dico</h2>
          <div class="precon__field">
            <label>Enfermedades CrÃ³nicas</label>
            <div class="precon__tag-input">
              <input v-model="newDisease" type="text" class="precon__input" placeholder="Ej: Diabetes"
                @keydown.enter.prevent="addToList(medicalHistory.chronic_diseases, newDisease, () => newDisease = '')" />
              <button type="button" class="precon__add-btn" @click="addToList(medicalHistory.chronic_diseases, newDisease, () => newDisease = '')">
                <i class="pi pi-plus" aria-hidden="true" />
              </button>
            </div>
            <div class="precon__tags">
              <span v-for="(d, i) in medicalHistory.chronic_diseases" :key="d" class="precon__tag">
                {{ d }}
                <button type="button" class="precon__tag-remove" @click="removeFromList(medicalHistory.chronic_diseases, i)">Ã—</button>
              </span>
            </div>
          </div>
          <div class="precon__field">
            <label>Alergias</label>
            <div class="precon__tag-input">
              <input v-model="newAllergy" type="text" class="precon__input" placeholder="Ej: Penicilina"
                @keydown.enter.prevent="addToList(medicalHistory.allergies, newAllergy, () => newAllergy = '')" />
              <button type="button" class="precon__add-btn" @click="addToList(medicalHistory.allergies, newAllergy, () => newAllergy = '')">
                <i class="pi pi-plus" aria-hidden="true" />
              </button>
            </div>
            <div class="precon__tags">
              <span v-for="(a, i) in medicalHistory.allergies" :key="a" class="precon__tag">
                {{ a }}
                <button type="button" class="precon__tag-remove" @click="removeFromList(medicalHistory.allergies, i)">Ã—</button>
              </span>
            </div>
          </div>
        </div>

        <!-- Section 4: Historial Familiar -->
        <div v-show="currentSection === 4" class="precon__section">
          <h2 class="precon__section-title">Historial Familiar</h2>
          <div class="precon__field">
            <label>Enfermedades Hereditarias</label>
            <div class="precon__tag-input">
              <input v-model="newHereditaryDisease" type="text" class="precon__input" placeholder="Ej: HipertensiÃ³n"
                @keydown.enter.prevent="addToList(familyHistory.hereditary_diseases, newHereditaryDisease, () => newHereditaryDisease = '')" />
              <button type="button" class="precon__add-btn" @click="addToList(familyHistory.hereditary_diseases, newHereditaryDisease, () => newHereditaryDisease = '')">
                <i class="pi pi-plus" aria-hidden="true" />
              </button>
            </div>
            <div class="precon__tags">
              <span v-for="(d, i) in familyHistory.hereditary_diseases" :key="d" class="precon__tag">
                {{ d }}
                <button type="button" class="precon__tag-remove" @click="removeFromList(familyHistory.hereditary_diseases, i)">Ã—</button>
              </span>
            </div>
          </div>
        </div>

        <!-- Section 5: Estilo de Vida -->
        <div v-show="currentSection === 5" class="precon__section">
          <h2 class="precon__section-title">Estilo de Vida</h2>
          <div class="precon__field">
            <label for="smoking">Â¿Fuma?</label>
            <select id="smoking" v-model="lifestyle.smoking" class="precon__select">
              <option value="no">No</option>
              <option value="ocasional">Ocasionalmente</option>
              <option value="regular">Regularmente</option>
              <option value="ex">Ex fumador</option>
            </select>
          </div>
          <div class="precon__field">
            <label for="alcohol">Â¿Consume alcohol?</label>
            <select id="alcohol" v-model="lifestyle.alcohol" class="precon__select">
              <option value="no">No</option>
              <option value="ocasional">Ocasionalmente</option>
              <option value="regular">Regularmente</option>
            </select>
          </div>
        </div>

        <!-- Section 6: Datos Reproductivos -->
        <div v-show="currentSection === 6" class="precon__section">
          <h2 class="precon__section-title">Datos Reproductivos</h2>
          <p class="precon__hint">Completar solo si es relevante para su consulta.</p>
          <div class="precon__field">
            <label for="repro_notes">Observaciones</label>
            <textarea id="repro_notes" class="precon__textarea" rows="3"
              @input="reproductiveData.notes = ($event.target as HTMLTextAreaElement).value" />
          </div>
        </div>

        <!-- Section 7: SeÃ±ales de Alerta -->
        <div v-show="currentSection === 7" class="precon__section">
          <h2 class="precon__section-title">SeÃ±ales de Alerta</h2>
          <p class="precon__hint">Indique si ha experimentado alguna de estas seÃ±ales recientemente.</p>
          <div class="precon__field">
            <div class="precon__tag-input">
              <input v-model="newWarningSign" type="text" class="precon__input" placeholder="Ej: Dolor de pecho"
                @keydown.enter.prevent="addToList(warningSigns, newWarningSign, () => newWarningSign = '')" />
              <button type="button" class="precon__add-btn" @click="addToList(warningSigns, newWarningSign, () => newWarningSign = '')">
                <i class="pi pi-plus" aria-hidden="true" />
              </button>
            </div>
            <div class="precon__tags">
              <span v-for="(s, i) in warningSigns" :key="s" class="precon__tag precon__tag--warning">
                {{ s }}
                <button type="button" class="precon__tag-remove" @click="removeFromList(warningSigns, i)">Ã—</button>
              </span>
            </div>
          </div>
        </div>

        <!-- Section 8: Documentos Adicionales -->
        <div v-show="currentSection === 8" class="precon__section">
          <h2 class="precon__section-title">Documentos Adicionales</h2>
          <p class="precon__hint">
            Puede adjuntar URLs de documentos o resultados de laboratorio relevantes.
          </p>
          <div class="precon__field">
            <label for="doc_url">URL del documento</label>
            <div class="precon__tag-input">
              <input id="doc_url" v-model="newDocUrl" type="url" class="precon__input" placeholder="https://..."
                @keydown.enter.prevent="addToList(additionalDocs, newDocUrl, () => newDocUrl = '')" />
              <button type="button" class="precon__add-btn" @click="addToList(additionalDocs, newDocUrl, () => newDocUrl = '')">
                <i class="pi pi-plus" aria-hidden="true" />
              </button>
            </div>
          </div>
          <div class="precon__tags">
            <span v-for="(doc, i) in additionalDocs" :key="doc" class="precon__tag">
              {{ doc }}
              <button type="button" class="precon__tag-remove" @click="removeFromList(additionalDocs, i)">Ã—</button>
            </span>
          </div>
        </div>

        <!-- Error -->
        <p v-if="submitError" class="precon__error" role="alert">
          <i class="pi pi-exclamation-circle" aria-hidden="true" />
          {{ submitError }}
        </p>

        <!-- Footer navigation -->
        <div class="precon__footer">
          <button v-if="currentSection > 1" type="button" class="precon__btn precon__btn--outline" @click="prevSection">
            <i class="pi pi-arrow-left" aria-hidden="true" />
            Anterior
          </button>
          <span v-else />
          <button
            v-if="currentSection < totalSections"
            type="button"
            class="precon__btn precon__btn--primary"
            @click="nextSection"
          >
            Siguiente
            <i class="pi pi-arrow-right" aria-hidden="true" />
          </button>
          <button
            v-else
            type="button"
            class="precon__btn precon__btn--primary"
            :disabled="isSubmitting"
            @click="submitQuestionnaire"
          >
            <i v-if="isSubmitting" class="pi pi-spinner pi-spin" aria-hidden="true" />
            <template v-else>
              <i class="pi pi-send" aria-hidden="true" />
              Enviar Cuestionario
            </template>
          </button>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<style scoped>
.precon {
  max-width: 48rem;
  margin: 0 auto;
  padding: var(--spacing-4);
}

.precon__title {
  font-family: var(--font-heading);
  font-size: var(--text-2xl);
  font-weight: var(--font-bold);
  color: var(--color-text-strong);
  margin: 0 0 var(--spacing-4) 0;
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
}

.precon__title i { color: var(--color-primary-700); }

/* Progress */
.precon__progress {
  height: 6px;
  background-color: var(--color-surface-200);
  border-radius: var(--radius-full);
  overflow: hidden;
  margin-bottom: var(--spacing-1);
}

.precon__progress-bar {
  height: 100%;
  background: linear-gradient(90deg, var(--color-primary-600), var(--color-primary-700));
  border-radius: var(--radius-full);
  transition: width var(--transition-base);
}

.precon__progress-label {
  font-size: var(--text-xs);
  color: var(--color-text-muted);
  margin: 0 0 var(--spacing-4) 0;
}

/* Nav pills */
.precon__nav {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-1);
  margin-bottom: var(--spacing-5);
}

.precon__pill {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-1);
  padding: var(--spacing-1) var(--spacing-2);
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-full);
  background-color: var(--color-surface-0);
  color: var(--color-text-muted);
  font-size: var(--text-xs);
  font-family: var(--font-body);
  cursor: pointer;
  transition: all var(--transition-fast);
}

.precon__pill--active {
  background-color: var(--color-primary-700);
  color: var(--color-surface-0);
  border-color: var(--color-primary-700);
}

.precon__pill--done {
  background-color: var(--color-success-50);
  color: var(--color-success-800);
  border-color: var(--color-success-200);
}

.precon__pill-label {
  display: none;
}

@media (min-width: 640px) {
  .precon__pill-label { display: inline; }
}

/* Section */
.precon__section {
  background-color: var(--color-surface-0);
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-lg);
  padding: var(--spacing-5);
  margin-bottom: var(--spacing-4);
}

.precon__section-title {
  font-family: var(--font-heading);
  font-size: var(--text-lg);
  font-weight: var(--font-bold);
  color: var(--color-text-strong);
  margin: 0 0 var(--spacing-4) 0;
}

.precon__hint {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  margin: 0 0 var(--spacing-3) 0;
}

/* Fields */
.precon__field {
  margin-bottom: var(--spacing-4);
}

.precon__field label {
  display: block;
  font-size: var(--text-sm);
  font-weight: var(--font-semibold);
  color: var(--color-text-strong);
  margin-bottom: var(--spacing-1);
}

.precon__input,
.precon__textarea,
.precon__select {
  width: 100%;
  padding: var(--spacing-2) var(--spacing-3);
  border: 1px solid var(--color-surface-300);
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  font-family: var(--font-body);
  color: var(--color-text-strong);
  background-color: var(--color-surface-0);
  transition: border-color var(--transition-fast);
}

.precon__input:focus,
.precon__textarea:focus,
.precon__select:focus {
  outline: none;
  border-color: var(--color-primary-500);
  box-shadow: 0 0 0 3px rgba(0, 128, 128, 0.1);
}

.precon__range {
  width: 100%;
  accent-color: var(--color-primary-700);
}

.precon__range-labels {
  display: flex;
  justify-content: space-between;
  font-size: var(--text-xs);
  color: var(--color-text-subtle);
}

/* Tag input */
.precon__tag-input {
  display: flex;
  gap: var(--spacing-2);
}

.precon__tag-input .precon__input {
  flex: 1;
}

.precon__add-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  border: 1px solid var(--color-primary-500);
  border-radius: var(--radius-md);
  background-color: var(--color-primary-50);
  color: var(--color-primary-700);
  cursor: pointer;
  transition: background-color var(--transition-fast);
  font-family: var(--font-body);
}

.precon__add-btn:hover {
  background-color: var(--color-primary-100);
}

.precon__tags {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-1);
  margin-top: var(--spacing-2);
}

.precon__tag {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-1);
  padding: var(--spacing-1) var(--spacing-2);
  background-color: var(--color-primary-50);
  color: var(--color-primary-800);
  border-radius: var(--radius-full);
  font-size: var(--text-xs);
}

.precon__tag--warning {
  background-color: var(--color-warning-50);
  color: var(--color-warning-800);
}

.precon__tag-remove {
  background: none;
  border: none;
  color: inherit;
  cursor: pointer;
  font-size: var(--text-sm);
  padding: 0;
  line-height: 1;
  font-family: var(--font-body);
}

/* Footer */
.precon__footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: var(--spacing-4);
}

.precon__btn {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-2);
  padding: var(--spacing-2) var(--spacing-4);
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  font-weight: var(--font-semibold);
  font-family: var(--font-body);
  cursor: pointer;
  transition: all var(--transition-fast);
}

.precon__btn--primary {
  background-color: var(--color-primary-700);
  color: var(--color-surface-0);
  border: none;
}

.precon__btn--primary:hover:not(:disabled) {
  background-color: var(--color-primary-600);
}

.precon__btn--primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.precon__btn--outline {
  background-color: transparent;
  color: var(--color-text-strong);
  border: 1px solid var(--color-surface-300);
}

.precon__btn--outline:hover {
  background-color: var(--color-surface-100);
}

.precon__btn:focus-visible {
  outline: 2px solid var(--color-focus-ring);
  outline-offset: 2px;
}

/* Error */
.precon__error {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  padding: var(--spacing-3);
  background-color: var(--color-danger-50);
  color: var(--color-danger-800);
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  margin: var(--spacing-3) 0;
}

/* Success */
.precon__success {
  text-align: center;
  padding: var(--spacing-8);
}

.precon__success-icon {
  font-size: 3rem;
  color: var(--color-success-600);
  margin-bottom: var(--spacing-3);
}

.precon__success h2 {
  font-family: var(--font-heading);
  font-size: var(--text-xl);
  color: var(--color-text-strong);
  margin: 0 0 var(--spacing-2) 0;
}

.precon__success p {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  margin: 0;
}
</style>
