<!--
  ====================================================================
  Register — Creación de Cuenta para Pacientes
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
  Formulario de Inertia (navegación, no mutación de API).
  Validación client-side que espeja las reglas del servidor.
-->
<script setup lang="ts">
import LandingLayout from '@/layouts/LandingLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { validateRegisterClient } from '@/lib/registerValidation';
import type { RegisterValidationErrors } from '@/lib/registerValidation';

const form = useForm({
  name: '',
  last_name: '',
  email: '',
  password: '',
  password_confirmation: '',
  timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
});

let localErrors: RegisterValidationErrors = {};

function handleSubmit() {
  localErrors = validateRegisterClient(
    form.name,
    form.last_name,
    form.email,
    form.password,
    form.password_confirmation
  );

  if (Object.keys(localErrors).length > 0) {
    for (const [key, value] of Object.entries(localErrors)) {
      form.setError(key as keyof RegisterValidationErrors, value!);
    }
    return;
  }

  form.clearErrors();

  form.post('/register', {
    onFinish: () => {
      form.reset('password', 'password_confirmation');
    },
  });
}
</script>

<template>
  <LandingLayout>
    <div class="auth-page">
      <div class="auth-card">
        <div class="auth-card__header">
          <div class="auth-card__logo">
            <i class="pi pi-user-plus" aria-hidden="true" />
          </div>
          <h1 class="auth-card__title">Crear Cuenta de Paciente</h1>
          <p class="auth-card__subtitle">
            Regístrate para agendar tus consultas médicas online
          </p>
        </div>

        <!-- Alerta de Éxito -->
        <div v-if="form.recentlySuccessful" class="auth-alert auth-alert--success" role="status">
          <i class="pi pi-check-circle" aria-hidden="true" />
          <span>¡Cuenta creada con éxito! Iniciando sesión…</span>
        </div>

        <form class="auth-form" @submit.prevent="handleSubmit" novalidate>
          <!-- Nombre y Apellido -->
          <div class="auth-form__row">
            <div class="auth-form__field">
              <label class="auth-form__label" for="reg-name">Nombre</label>
              <div class="auth-form__input-wrapper">
                <i class="pi pi-user auth-form__input-icon" aria-hidden="true" />
                <input
                  id="reg-name"
                  v-model="form.name"
                  type="text"
                  class="auth-form__input"
                  :class="{ 'auth-form__input--error': form.errors.name }"
                  placeholder="Juan"
                  autocomplete="given-name"
                  :disabled="form.processing"
                  @input="form.clearErrors('name')"
                />
              </div>
              <span v-if="form.errors.name" class="auth-form__error">{{ form.errors.name }}</span>
            </div>

            <div class="auth-form__field">
              <label class="auth-form__label" for="reg-last-name">Apellido</label>
              <div class="auth-form__input-wrapper">
                <i class="pi pi-user auth-form__input-icon" aria-hidden="true" />
                <input
                  id="reg-last-name"
                  v-model="form.last_name"
                  type="text"
                  class="auth-form__input"
                  :class="{ 'auth-form__input--error': form.errors.last_name }"
                  placeholder="Pérez"
                  autocomplete="family-name"
                  :disabled="form.processing"
                  @input="form.clearErrors('last_name')"
                />
              </div>
              <span v-if="form.errors.last_name" class="auth-form__error">{{ form.errors.last_name }}</span>
            </div>
          </div>

          <!-- Correo Electrónico -->
          <div class="auth-form__field">
            <label class="auth-form__label" for="reg-email">Correo Electrónico</label>
            <div class="auth-form__input-wrapper">
              <i class="pi pi-envelope auth-form__input-icon" aria-hidden="true" />
              <input
                id="reg-email"
                v-model="form.email"
                type="email"
                class="auth-form__input"
                :class="{ 'auth-form__input--error': form.errors.email }"
                placeholder="tu@email.com"
                autocomplete="email"
                :disabled="form.processing"
                @input="form.clearErrors('email')"
              />
            </div>
            <span v-if="form.errors.email" class="auth-form__error">{{ form.errors.email }}</span>
          </div>

          <!-- Contraseña -->
          <div class="auth-form__field">
            <label class="auth-form__label" for="reg-password">Contraseña</label>
            <div class="auth-form__input-wrapper">
              <i class="pi pi-lock auth-form__input-icon" aria-hidden="true" />
              <input
                id="reg-password"
                v-model="form.password"
                type="password"
                class="auth-form__input"
                :class="{ 'auth-form__input--error': form.errors.password }"
                placeholder="Mínimo 8 caracteres"
                autocomplete="new-password"
                :disabled="form.processing"
                @input="form.clearErrors('password')"
              />
            </div>
            <span v-if="form.errors.password" class="auth-form__error">{{ form.errors.password }}</span>
          </div>

          <!-- Confirmar Contraseña -->
          <div class="auth-form__field">
            <label class="auth-form__label" for="reg-password-confirm">Confirmar Contraseña</label>
            <div class="auth-form__input-wrapper">
              <i class="pi pi-lock-open auth-form__input-icon" aria-hidden="true" />
              <input
                id="reg-password-confirm"
                v-model="form.password_confirmation"
                type="password"
                class="auth-form__input"
                :class="{ 'auth-form__input--error': form.errors.password_confirmation }"
                placeholder="Repite tu contraseña"
                autocomplete="new-password"
                :disabled="form.processing"
                @input="form.clearErrors('password_confirmation')"
              />
            </div>
            <span v-if="form.errors.password_confirmation" class="auth-form__error">
              {{ form.errors.password_confirmation }}
            </span>
          </div>

          <!-- Botón de Registro -->
          <button
            type="submit"
            class="auth-form__submit"
            :disabled="form.processing"
          >
            <i v-if="form.processing" class="pi pi-spin pi-spinner" aria-hidden="true" />
            <template v-else>
              <i class="pi pi-check-circle" aria-hidden="true" />
              Crear mi Cuenta
            </template>
          </button>
        </form>

        <div class="auth-card__footer">
          <p class="auth-card__link-text">
            ¿Ya tienes una cuenta?
            <a href="/login" class="auth-card__link">Inicia sesión aquí</a>
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
  max-width: 28rem;
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
  padding: var(--spacing-6) var(--spacing-6) var(--spacing-4);
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

.auth-alert {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  padding: var(--spacing-3) var(--spacing-4);
  font-size: var(--text-sm);
  background-color: var(--color-success-50);
  color: var(--color-success-800);
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
  padding: var(--spacing-2) var(--spacing-4) var(--spacing-2) 2.5rem;
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  font-family: var(--font-body);
  color: var(--color-text-strong);
  background-color: var(--color-surface-0);
  transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
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
  margin-top: var(--spacing-2);
}

.auth-form__submit:hover:not(:disabled) {
  background-color: var(--color-primary-600);
}

.auth-form__submit:disabled {
  opacity: 0.7;
  cursor: not-allowed;
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

@media (max-width: 520px) {
  .auth-form__row {
    grid-template-columns: 1fr;
  }
}
</style>
