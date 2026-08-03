<!--
  ====================================================================
  Register — Registro de Usuario
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
  Registro autónomo de pacientes (PRD §3).
  Los médicos también se registran aquí pero quedan en status 'pending'
  hasta aprobación por admin.
-->
<script setup lang="ts">
import { ref, inject, computed } from 'vue';
import LandingLayout from '@/layouts/LandingLayout.vue';
import { i18nKey } from '@/i18n/plugin';
import type { UserRole } from '@/types/auth.types';

const t = inject(i18nKey)!;

type RegisterRole = Extract<UserRole, 'patient' | 'doctor'>;

const form = ref({
  name: '',
  last_name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: 'patient' as RegisterRole,
  timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
});

const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);

const ROLES: { id: RegisterRole; label: string; description: string; icon: string }[] = [
  {
    id: 'patient',
    label: 'Paciente',
    description: 'Busca y reserva citas con especialistas',
    icon: 'pi-user',
  },
  {
    id: 'doctor',
    label: 'Médico',
    description: 'Ofrece consultas y gestiona tu agenda',
    icon: 'pi-briefcase',
  },
];

function validate(): boolean {
  errors.value = {};

  if (!form.value.name.trim()) {
    errors.value.name = 'El nombre es obligatorio.';
  }
  if (!form.value.last_name.trim()) {
    errors.value.last_name = 'El apellido es obligatorio.';
  }
  if (!form.value.email.trim()) {
    errors.value.email = 'El correo electrónico es obligatorio.';
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)) {
    errors.value.email = 'Ingresa un correo electrónico válido.';
  }
  if (!form.value.password) {
    errors.value.password = 'La contraseña es obligatoria.';
  } else if (form.value.password.length < 8) {
    errors.value.password = 'La contraseña debe tener al menos 8 caracteres.';
  }
  if (form.value.password !== form.value.password_confirmation) {
    errors.value.password_confirmation = 'Las contraseñas no coinciden.';
  }

  return Object.keys(errors.value).length === 0;
}

function handleSubmit() {
  if (!validate()) return;

  isSubmitting.value = true;

  // TODO: Inertia router.post('/register', form.value)
  setTimeout(() => {
    isSubmitting.value = false;
  }, 1500);
}

const detectedTimezone = computed(() => form.value.timezone);
</script>

<template>
  <LandingLayout>
    <div class="auth-page">
      <div class="auth-card">
        <div class="auth-card__header">
          <div class="auth-card__logo">
            <i class="pi pi-heart-fill" aria-hidden="true" />
          </div>
          <h1 class="auth-card__title">Crear Cuenta</h1>
          <p class="auth-card__subtitle">
            Regístrate para acceder a la plataforma de telemedicina
          </p>
        </div>

        <form class="auth-form" @submit.prevent="handleSubmit" novalidate>
          <!-- Selector de rol -->
          <div class="role-selector">
            <span class="auth-form__label">Tipo de cuenta</span>
            <div class="role-selector__options">
              <label
                v-for="role in ROLES"
                :key="role.id"
                :class="['role-option', { 'role-option--active': form.role === role.id }]"
              >
                <input
                  type="radio"
                  :value="role.id"
                  v-model="form.role"
                  class="role-option__radio"
                />
                <i :class="['pi', role.icon, 'role-option__icon']" aria-hidden="true" />
                <span class="role-option__label">{{ role.label }}</span>
                <span class="role-option__description">{{ role.description }}</span>
              </label>
            </div>
          </div>

          <!-- Nombre y Apellido -->
          <div class="auth-form__row">
            <div class="auth-form__field">
              <label class="auth-form__label" for="reg-name">Nombre</label>
              <input
                id="reg-name"
                v-model="form.name"
                type="text"
                class="auth-form__input"
                :class="{ 'auth-form__input--error': errors.name }"
                placeholder="Juan"
                autocomplete="given-name"
              />
              <span v-if="errors.name" class="auth-form__error">
                {{ errors.name }}
              </span>
            </div>
            <div class="auth-form__field">
              <label class="auth-form__label" for="reg-lastname">Apellido</label>
              <input
                id="reg-lastname"
                v-model="form.last_name"
                type="text"
                class="auth-form__input"
                :class="{ 'auth-form__input--error': errors.last_name }"
                placeholder="Pérez"
                autocomplete="family-name"
              />
              <span v-if="errors.last_name" class="auth-form__error">
                {{ errors.last_name }}
              </span>
            </div>
          </div>

          <!-- Email -->
          <div class="auth-form__field">
            <label class="auth-form__label" for="reg-email">
              Correo electrónico
            </label>
            <div class="auth-form__input-wrapper">
              <i class="pi pi-envelope auth-form__input-icon" aria-hidden="true" />
              <input
                id="reg-email"
                v-model="form.email"
                type="email"
                class="auth-form__input auth-form__input--with-icon"
                :class="{ 'auth-form__input--error': errors.email }"
                placeholder="tu@email.com"
                autocomplete="email"
              />
            </div>
            <span v-if="errors.email" class="auth-form__error">
              {{ errors.email }}
            </span>
          </div>

          <!-- Contraseña -->
          <div class="auth-form__field">
            <label class="auth-form__label" for="reg-password">
              Contraseña
            </label>
            <div class="auth-form__input-wrapper">
              <i class="pi pi-lock auth-form__input-icon" aria-hidden="true" />
              <input
                id="reg-password"
                v-model="form.password"
                type="password"
                class="auth-form__input auth-form__input--with-icon"
                :class="{ 'auth-form__input--error': errors.password }"
                placeholder="Mínimo 8 caracteres"
                autocomplete="new-password"
              />
            </div>
            <span v-if="errors.password" class="auth-form__error">
              {{ errors.password }}
            </span>
          </div>

          <!-- Confirmar contraseña -->
          <div class="auth-form__field">
            <label class="auth-form__label" for="reg-confirm">
              Confirmar contraseña
            </label>
            <div class="auth-form__input-wrapper">
              <i class="pi pi-lock auth-form__input-icon" aria-hidden="true" />
              <input
                id="reg-confirm"
                v-model="form.password_confirmation"
                type="password"
                class="auth-form__input auth-form__input--with-icon"
                :class="{ 'auth-form__input--error': errors.password_confirmation }"
                placeholder="Repite tu contraseña"
                autocomplete="new-password"
              />
            </div>
            <span v-if="errors.password_confirmation" class="auth-form__error">
              {{ errors.password_confirmation }}
            </span>
          </div>

          <!-- Timezone detectado -->
          <div class="auth-form__timezone">
            <i class="pi pi-globe" aria-hidden="true" />
            <span>Zona horaria detectada: <strong>{{ detectedTimezone }}</strong></span>
          </div>

          <!-- Doctor warning -->
          <div v-if="form.role === 'doctor'" class="auth-form__notice">
            <i class="pi pi-info-circle" aria-hidden="true" />
            <span>
              Las cuentas médicas requieren verificación. Tu perfil quedará en
              estado <strong>pendiente</strong> hasta revisión del administrador.
            </span>
          </div>

          <button
            type="submit"
            class="auth-form__submit"
            :disabled="isSubmitting"
          >
            <i v-if="isSubmitting" class="pi pi-spin pi-spinner" aria-hidden="true" />
            <template v-else>Crear Cuenta</template>
          </button>
        </form>

        <div class="auth-card__footer">
          <p class="auth-card__link-text">
            ¿Ya tienes cuenta?
            <a href="/login" class="auth-card__link">Inicia sesión</a>
          </p>
        </div>
      </div>
    </div>
  </LandingLayout>
</template>

<style scoped>
.auth-page {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: calc(100vh - 8rem);
  padding: var(--spacing-4);
}

.auth-card {
  width: 100%;
  max-width: 30rem;
  background-color: var(--color-surface-0);
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg);
  overflow: hidden;
}

.auth-card__header {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--spacing-2);
  padding: var(--spacing-5) var(--spacing-6) var(--spacing-4);
  background: linear-gradient(135deg, var(--color-primary-700) 0%, var(--color-primary-900) 100%);
  color: var(--color-surface-0);
}

.auth-card__logo {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 3.5rem;
  height: 3.5rem;
  border-radius: var(--radius-full);
  background-color: rgba(255, 255, 255, 0.15);
  font-size: var(--text-xl);
}

.auth-card__title {
  font-family: var(--font-heading);
  font-size: var(--text-xl);
  font-weight: var(--font-bold);
  margin: 0;
}

.auth-card__subtitle {
  font-size: var(--text-sm);
  color: var(--color-primary-100);
  text-align: center;
  margin: 0;
}

.auth-form {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-4);
  padding: var(--spacing-5) var(--spacing-6);
}

.auth-form__row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--spacing-3);
}

.auth-form__field {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-1);
}

.auth-form__label {
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
  color: var(--color-text-strong);
}

.auth-form__input-wrapper {
  position: relative;
}

.auth-form__input-icon {
  position: absolute;
  left: var(--spacing-3);
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-text-subtle);
  font-size: var(--text-sm);
}

.auth-form__input {
  width: 100%;
  padding: var(--spacing-2) var(--spacing-3);
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  font-family: var(--font-body);
  color: var(--color-text-strong);
  background-color: var(--color-surface-0);
  transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
}

.auth-form__input--with-icon {
  padding-left: 2.5rem;
}

.auth-form__input:focus {
  outline: none;
  border-color: var(--color-primary-500);
  box-shadow: 0 0 0 2px var(--color-focus-ring);
}

.auth-form__input--error {
  border-color: var(--color-error-600);
}

.auth-form__error {
  font-size: var(--text-xs);
  color: var(--color-error-700);
}

/* Role selector */
.role-selector {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-2);
}

.role-selector__options {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--spacing-2);
}

.role-option {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: var(--spacing-3);
  border: 2px solid var(--color-surface-200);
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: all var(--transition-fast);
  text-align: center;
}

.role-option:hover {
  border-color: var(--color-primary-500);
  background-color: var(--color-primary-50);
}

.role-option--active {
  border-color: var(--color-primary-700);
  background-color: var(--color-primary-50);
}

.role-option__radio {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.role-option__icon {
  font-size: var(--text-xl);
  color: var(--color-primary-700);
}

.role-option__label {
  font-size: var(--text-sm);
  font-weight: var(--font-semibold);
  color: var(--color-text-strong);
}

.role-option__description {
  font-size: var(--text-xs);
  color: var(--color-text-muted);
  line-height: var(--leading-tight);
}

/* Timezone */
.auth-form__timezone {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  padding: var(--spacing-2) var(--spacing-3);
  background-color: var(--color-surface-100);
  border-radius: var(--radius-md);
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}

.auth-form__timezone i {
  color: var(--color-primary-500);
}

/* Doctor notice */
.auth-form__notice {
  display: flex;
  align-items: flex-start;
  gap: var(--spacing-2);
  padding: var(--spacing-3);
  background-color: var(--color-warning-50);
  border-radius: var(--radius-md);
  font-size: var(--text-xs);
  color: var(--color-warning-800);
  line-height: var(--leading-normal);
}

.auth-form__notice i {
  flex-shrink: 0;
  margin-top: 2px;
}

.auth-form__submit {
  width: 100%;
  padding: var(--spacing-3);
  background-color: var(--color-primary-700);
  color: var(--color-surface-0);
  border: none;
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  font-weight: var(--font-semibold);
  font-family: var(--font-body);
  cursor: pointer;
  transition: background-color var(--transition-fast);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-2);
  min-height: 2.75rem;
}

.auth-form__submit:hover:not(:disabled) {
  background-color: var(--color-primary-600);
}

.auth-form__submit:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.auth-form__submit:focus-visible {
  outline: 2px solid var(--color-focus-ring);
  outline-offset: 2px;
}

.auth-card__footer {
  padding: var(--spacing-4) var(--spacing-6);
  border-top: 1px solid var(--color-surface-200);
  text-align: center;
}

.auth-card__link-text {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  margin: 0;
}

.auth-card__link {
  color: var(--color-primary-600);
  font-weight: var(--font-medium);
  text-decoration: none;
}

.auth-card__link:hover {
  text-decoration: underline;
}

.auth-card__link:focus-visible {
  outline: 2px solid var(--color-focus-ring);
  outline-offset: 2px;
}
</style>
