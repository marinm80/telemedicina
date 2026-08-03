<!--
  ====================================================================
  Login — Inicio de Sesión
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<script setup lang="ts">
import { ref, inject } from 'vue';
import LandingLayout from '@/layouts/LandingLayout.vue';
import { i18nKey } from '@/i18n/plugin';

const t = inject(i18nKey)!;

const form = ref({
  email: '',
  password: '',
});

const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);

function handleSubmit() {
  errors.value = {};

  if (!form.value.email.trim()) {
    errors.value.email = 'El correo electrónico es obligatorio.';
  }
  if (!form.value.password) {
    errors.value.password = 'La contraseña es obligatoria.';
  }

  if (Object.keys(errors.value).length > 0) return;

  isSubmitting.value = true;

  // TODO: Inertia router.post('/login', form.value)
  setTimeout(() => {
    isSubmitting.value = false;
  }, 1500);
}
</script>

<template>
  <LandingLayout>
    <div class="auth-page">
      <div class="auth-card">
        <div class="auth-card__header">
          <div class="auth-card__logo">
            <i class="pi pi-heart-fill" aria-hidden="true" />
          </div>
          <h1 class="auth-card__title">Iniciar Sesión</h1>
          <p class="auth-card__subtitle">
            Accede a tu cuenta para gestionar tus citas médicas
          </p>
        </div>

        <form class="auth-form" @submit.prevent="handleSubmit" novalidate>
          <div class="auth-form__field">
            <label class="auth-form__label" for="login-email">
              Correo electrónico
            </label>
            <div class="auth-form__input-wrapper">
              <i class="pi pi-envelope auth-form__input-icon" aria-hidden="true" />
              <input
                id="login-email"
                v-model="form.email"
                type="email"
                class="auth-form__input"
                :class="{ 'auth-form__input--error': errors.email }"
                placeholder="tu@email.com"
                autocomplete="email"
              />
            </div>
            <span v-if="errors.email" class="auth-form__error">
              {{ errors.email }}
            </span>
          </div>

          <div class="auth-form__field">
            <label class="auth-form__label" for="login-password">
              Contraseña
            </label>
            <div class="auth-form__input-wrapper">
              <i class="pi pi-lock auth-form__input-icon" aria-hidden="true" />
              <input
                id="login-password"
                v-model="form.password"
                type="password"
                class="auth-form__input"
                :class="{ 'auth-form__input--error': errors.password }"
                placeholder="••••••••"
                autocomplete="current-password"
              />
            </div>
            <span v-if="errors.password" class="auth-form__error">
              {{ errors.password }}
            </span>
          </div>

          <button
            type="submit"
            class="auth-form__submit"
            :disabled="isSubmitting"
          >
            <i v-if="isSubmitting" class="pi pi-spin pi-spinner" aria-hidden="true" />
            <template v-else>Iniciar Sesión</template>
          </button>
        </form>

        <div class="auth-card__footer">
          <p class="auth-card__link-text">
            ¿No tienes cuenta?
            <a href="/register" class="auth-card__link">Regístrate aquí</a>
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
  max-width: 26rem;
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

.auth-form {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-4);
  padding: var(--spacing-5) var(--spacing-6);
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

.auth-form__input--error:focus {
  box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.2);
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
