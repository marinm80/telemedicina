<!--
  ====================================================================
  AdminPanel — Unified admin control panel with tabs
  Merges: DoctorManager + ScheduleManager + SettingsManager
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { getInitials, getAvatarColor } from '@/lib/mockData';

// === Types ===
interface Doctor {
  profile_id: string; user_id: string; name: string; last_name: string;
  email: string; timezone: string; status: string; consultation_fee: number;
  description: string; years_experience: number; university: string;
  license_number: string; specialties: string[]; specialty_ids: string[];
  created_at: string; photo_url: string | null;
}
interface Specialty { id: string; name: string; }
interface UserInfo { id: string; name: string; last_name: string; email: string; timezone: string; role: string; created_at: string; }
interface ScheduleEntry { id: string; day_of_week: number; franja_inicio: string; franja_fin: string; slot_duration: number; }

// === State ===
const activeTab = ref<'doctors' | 'users' | 'config'>('doctors');
const loading = ref(true);
const error = ref('');
const successMsg = ref('');

// Doctors
const doctors = ref<Doctor[]>([]);
const specialties = ref<Specialty[]>([]);
const showCreateForm = ref(false);
const saving = ref(false);
const filterStatus = ref('all');
const createForm = ref({ name: '', last_name: '', email: '', password: '', timezone: 'America/Santo_Domingo', license_number: '', consultation_fee: 50, description: '', years_experience: 0, university: '', specialty_ids: [] as string[], status: 'approved' });
const photoFile = ref<File | null>(null);
const photoPreview = ref<string | null>(null);
function onPhotoSelected(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0] ?? null;
  photoFile.value = file;
  if (photoPreview.value) URL.revokeObjectURL(photoPreview.value);
  photoPreview.value = file ? URL.createObjectURL(file) : null;
}

// Edit modal
const editModal = ref(false);
const editDoc = ref<Doctor | null>(null);
const editForm = ref({ status: 'approved', consultation_fee: 0, description: '', years_experience: 0, university: '' });
const editSaving = ref(false);

// Schedule management (inline per doctor)
const expandedDoctor = ref<string | null>(null);
const doctorSchedules = ref<ScheduleEntry[]>([]);
const loadingSchedules = ref(false);
const scheduleForm = ref({ day_of_week: 1, inicio: '08:00', fin: '17:00', slot_duration: 30 });

// Users
const users = ref<UserInfo[]>([]);
const userSearch = ref('');
const userFilterRole = ref('all');
const pwdModal = ref(false);
const pwdUserId = ref(''); const pwdUserName = ref(''); const pwdNew = ref(''); const pwdConfirm = ref(''); const pwdSaving = ref(false);

const dayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

function csrf(): string { return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''; }

const filteredDoctors = computed(() => {
  if (filterStatus.value === 'all') return doctors.value;
  return doctors.value.filter(d => d.status === filterStatus.value);
});
const statusCounts = computed(() => {
  const c = { all: doctors.value.length, pending: 0, approved: 0, rejected: 0 };
  doctors.value.forEach(d => { if (d.status in c) (c as any)[d.status]++; });
  return c;
});
const filteredUsers = computed(() => {
  let r = users.value;
  if (userFilterRole.value !== 'all') r = r.filter(u => u.role === userFilterRole.value);
  if (userSearch.value.trim()) { const q = userSearch.value.toLowerCase(); r = r.filter(u => `${u.name} ${u.last_name}`.toLowerCase().includes(q) || u.email.toLowerCase().includes(q)); }
  return r;
});

// === Fetch ===
async function fetchDoctors() {
  try {
    const res = await fetch('/api/admin/doctors', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
    if (res.ok) { const d = await res.json(); doctors.value = d.data || []; }
  } catch (e: any) { error.value = e.message; }
}
async function fetchUsers() {
  try {
    const res = await fetch('/api/admin/users', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
    if (res.ok) { const d = await res.json(); users.value = d.data || []; }
  } catch (e: any) { error.value = e.message; }
}
async function fetchSpecialties() {
  try {
    const page = usePage();
    const booking = (page.props as any)?.booking;
    if (booking?.specialties?.length) specialties.value = booking.specialties;
  } catch (e) {}
}
onMounted(async () => {
  await Promise.all([fetchDoctors(), fetchUsers(), fetchSpecialties()]);
  loading.value = false;
});

// === Doctor CRUD ===
async function createDoctor() {
  if (!createForm.value.name || !createForm.value.email || createForm.value.specialty_ids.length === 0) { error.value = 'Completa los campos obligatorios'; return; }
  saving.value = true; error.value = '';
  try {
    // FormData, no JSON: hay un archivo. No se fija Content-Type a mano
    // — el navegador pone el boundary de multipart solo si se lo deja.
    const body = new FormData();
    for (const [key, value] of Object.entries(createForm.value)) {
      if (key === 'specialty_ids') {
        (value as string[]).forEach((id) => body.append('specialty_ids[]', id));
      } else if (value !== null && value !== undefined) {
        body.append(key, String(value));
      }
    }
    if (photoFile.value) body.append('photo', photoFile.value);

    const res = await fetch('/api/admin/doctors', { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() }, body });
    if (res.ok) {
      successMsg.value = '✅ Médico creado'; showCreateForm.value = false;
      createForm.value = { name: '', last_name: '', email: '', password: '', timezone: 'America/Santo_Domingo', license_number: '', consultation_fee: 50, description: '', years_experience: 0, university: '', specialty_ids: [], status: 'approved' };
      if (photoPreview.value) URL.revokeObjectURL(photoPreview.value);
      photoFile.value = null; photoPreview.value = null;
      await fetchDoctors(); setTimeout(() => successMsg.value = '', 4000);
    }
    else { const d = await res.json().catch(() => ({})); error.value = d.message || 'Error'; }
  } catch (e: any) { error.value = e.message; } finally { saving.value = false; }
}
function openEdit(doc: Doctor) { editDoc.value = doc; editForm.value = { status: doc.status, consultation_fee: doc.consultation_fee, description: doc.description || '', years_experience: doc.years_experience || 0, university: doc.university || '' }; editModal.value = true; }
async function saveEdit() {
  if (!editDoc.value) return; editSaving.value = true; error.value = '';
  try {
    const res = await fetch(`/api/admin/doctors/${editDoc.value.profile_id}/status`, { method: 'PATCH', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify(editForm.value) });
    if (res.ok) { successMsg.value = '✅ Médico actualizado'; editModal.value = false; await fetchDoctors(); setTimeout(() => successMsg.value = '', 4000); }
    else { const d = await res.json().catch(() => ({})); error.value = d.message || 'Error'; }
  } catch (e: any) { error.value = e.message; } finally { editSaving.value = false; }
}

// === Schedules (per doctor) ===
async function toggleSchedules(profileId: string) {
  if (expandedDoctor.value === profileId) { expandedDoctor.value = null; return; }
  expandedDoctor.value = profileId; loadingSchedules.value = true;
  try {
    const res = await fetch('/api/admin/schedules', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
    if (res.ok) {
      const data = await res.json();
      const allSchedules = data.data || [];
      doctorSchedules.value = allSchedules.filter((s: any) => s.doctor_profile_id === profileId).map((s: any) => {
        const franja = (s.franja || '').replace(/[\[\]()]/g, '');
        const parts = franja.split(',');
        return { id: s.id, day_of_week: s.day_of_week, franja_inicio: (parts[0] || '').trim(), franja_fin: (parts[1] || '').trim(), slot_duration: s.slot_duration };
      });
    }
  } catch (e: any) { error.value = e.message; } finally { loadingSchedules.value = false; }
}
async function addScheduleForDoctor() {
  if (!expandedDoctor.value) return; saving.value = true; error.value = '';
  try {
    const res = await fetch('/api/admin/schedules', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ doctor_profile_id: expandedDoctor.value, day_of_week: scheduleForm.value.day_of_week, inicio: scheduleForm.value.inicio + ':00', fin: scheduleForm.value.fin + ':00', slot_duration: scheduleForm.value.slot_duration }) });
    if (res.ok) { successMsg.value = '✅ Horario agregado'; await toggleSchedules(expandedDoctor.value); setTimeout(() => successMsg.value = '', 3000); }
    else { const d = await res.json().catch(() => ({})); error.value = d.message || 'Error al crear horario'; }
  } catch (e: any) { error.value = e.message; } finally { saving.value = false; }
}
async function deleteScheduleEntry(id: string) {
  if (!confirm('¿Eliminar este horario?')) return;
  try {
    const res = await fetch(`/api/admin/schedules/${id}`, { method: 'DELETE', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() } });
    if (res.ok && expandedDoctor.value) { successMsg.value = '✅ Eliminado'; await toggleSchedules(expandedDoctor.value); setTimeout(() => successMsg.value = '', 3000); }
  } catch (e: any) { error.value = e.message; }
}

// === Users ===
function openPwdModal(u: UserInfo) { pwdUserId.value = u.id; pwdUserName.value = `${u.name} ${u.last_name}`; pwdNew.value = ''; pwdConfirm.value = ''; pwdModal.value = true; }
async function changePwd() {
  if (pwdNew.value.length < 8) { error.value = 'Mínimo 8 caracteres'; return; }
  if (pwdNew.value !== pwdConfirm.value) { error.value = 'Las contraseñas no coinciden'; return; }
  pwdSaving.value = true; error.value = '';
  try {
    const res = await fetch(`/api/admin/users/${pwdUserId.value}/password`, { method: 'PATCH', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ password: pwdNew.value, password_confirmation: pwdConfirm.value }) });
    if (res.ok) { successMsg.value = `✅ Contraseña de ${pwdUserName.value} actualizada`; pwdModal.value = false; setTimeout(() => successMsg.value = '', 4000); }
    else { const d = await res.json().catch(() => ({})); error.value = d.message || 'Error'; }
  } catch (e: any) { error.value = e.message; } finally { pwdSaving.value = false; }
}

function toggleSpec(id: string) { const idx = createForm.value.specialty_ids.indexOf(id); if (idx >= 0) createForm.value.specialty_ids.splice(idx, 1); else createForm.value.specialty_ids.push(id); }
function fmtDate(d: string) { return new Date(d).toLocaleDateString('es-ES', { year: 'numeric', month: 'short', day: 'numeric' }); }
function fmtTime(t: string) { return (t || '').substring(0, 5); }
function roleLabel(r: string) { return { admin: 'Admin', doctor: 'Médico', patient: 'Paciente', agent: 'Agente' }[r] || r; }
function roleClass(r: string) { return { admin: 'rb--admin', doctor: 'rb--doctor', patient: 'rb--patient', agent: 'rb--agent' }[r] || ''; }
function statusLabel(s: string) { return { pending: 'Pendiente', approved: 'Aprobado', rejected: 'Rechazado' }[s] || s; }
function statusClass(s: string) { return { pending: 'badge--warning', approved: 'badge--success', rejected: 'badge--danger' }[s] || ''; }
</script>

<template>
  <AppLayout>
    <div class="panel">
      <header class="panel__header">
        <h1>⚙️ Panel de Control</h1>
        <p class="panel__subtitle">Gestión centralizada de médicos, usuarios y sistema</p>
      </header>

      <Transition name="fade"><div v-if="successMsg" class="alert alert--success">{{ successMsg }}</div></Transition>
      <Transition name="fade"><div v-if="error" class="alert alert--error">{{ error }}<button @click="error = ''">×</button></div></Transition>

      <!-- Tabs -->
      <div class="tabs">
        <button :class="['tab', { 'tab--active': activeTab === 'doctors' }]" @click="activeTab = 'doctors'"><i class="pi pi-users"></i> Médicos <span v-if="statusCounts.pending" class="tab-badge">{{ statusCounts.pending }}</span></button>
        <button :class="['tab', { 'tab--active': activeTab === 'users' }]" @click="activeTab = 'users'"><i class="pi pi-user"></i> Usuarios</button>
        <button :class="['tab', { 'tab--active': activeTab === 'config' }]" @click="activeTab = 'config'"><i class="pi pi-cog"></i> Configuración</button>
      </div>

      <!-- ═══ TAB: DOCTORS ═══ -->
      <div v-if="activeTab === 'doctors'">
        <div class="toolbar">
          <div class="status-filters">
            <button v-for="t in [{k:'all',l:'Todos'},{k:'pending',l:'⏳ Pendientes'},{k:'approved',l:'✅ Aprobados'},{k:'rejected',l:'❌ Rechazados'}]" :key="t.k" :class="['pill', { 'pill--active': filterStatus === t.k }]" @click="filterStatus = t.k">{{ t.l }} <span class="pill__c">{{ (statusCounts as any)[t.k] }}</span></button>
          </div>
          <button class="btn-primary" @click="showCreateForm = !showCreateForm"><i class="pi" :class="showCreateForm ? 'pi-times' : 'pi-plus'"></i> {{ showCreateForm ? 'Cancelar' : 'Nuevo Médico' }}</button>
        </div>

        <!-- Create form -->
        <Transition name="slide-down">
          <div v-if="showCreateForm" class="form-card">
            <h3>Registrar Nuevo Médico</h3>
            <div class="fg fg--photo">
              <label>Foto de perfil</label>
              <div class="photo-picker">
                <img v-if="photoPreview" :src="photoPreview" alt="Vista previa" class="photo-picker__preview" />
                <div v-else class="photo-picker__placeholder"><i class="pi pi-user"></i></div>
                <input type="file" accept="image/*" class="fi" @change="onPhotoSelected" />
              </div>
            </div>
            <div class="form-grid">
              <div class="fg"><label>Nombre *</label><input v-model="createForm.name" class="fi" /></div>
              <div class="fg"><label>Apellido *</label><input v-model="createForm.last_name" class="fi" /></div>
              <div class="fg"><label>Email *</label><input v-model="createForm.email" type="email" class="fi" /></div>
              <div class="fg"><label>Contraseña *</label><input v-model="createForm.password" type="password" class="fi" /></div>
              <div class="fg"><label>Licencia *</label><input v-model="createForm.license_number" class="fi" /></div>
              <div class="fg"><label>Universidad</label><input v-model="createForm.university" class="fi" /></div>
              <div class="fg"><label>Años exp.</label><input v-model.number="createForm.years_experience" type="number" min="0" class="fi" /></div>
              <div class="fg"><label>Tarifa (USD)</label><input v-model.number="createForm.consultation_fee" type="number" min="0" class="fi" /></div>
            </div>
            <div class="fg" style="margin-top:12px"><label>Especialidades *</label>
              <div class="spec-sel"><button v-for="sp in specialties" :key="sp.id" type="button" :class="['sp-btn', { 'sp-btn--a': createForm.specialty_ids.includes(sp.id) }]" @click="toggleSpec(sp.id)">{{ sp.name }}</button></div>
            </div>
            <div class="form-actions"><button class="btn-sec" @click="showCreateForm = false">Cancelar</button><button class="btn-primary" @click="createDoctor" :disabled="saving">{{ saving ? 'Creando...' : '👨‍⚕️ Crear' }}</button></div>
          </div>
        </Transition>

        <!-- Doctor list -->
        <div v-if="loading" class="loading"><i class="pi pi-spin pi-spinner"></i> Cargando...</div>
        <div v-else-if="filteredDoctors.length === 0" class="empty"><i class="pi pi-users"></i><p>Sin médicos</p></div>
        <div v-else class="doc-list">
          <div v-for="doc in filteredDoctors" :key="doc.profile_id" :class="['dcard', `dcard--${doc.status}`]">
            <div class="dcard__top">
              <img v-if="doc.photo_url" :src="doc.photo_url" :alt="doc.name+' '+doc.last_name" class="dcard__av dcard__av--photo" />
              <div v-else class="dcard__av" :style="{ background: getAvatarColor(doc.name+' '+doc.last_name) }">{{ getInitials(doc.name+' '+doc.last_name) }}</div>
              <div class="dcard__info">
                <h3>{{ doc.name }} {{ doc.last_name }}</h3>
                <span class="dcard__email">{{ doc.email }}</span>
                <span :class="['badge', statusClass(doc.status)]">{{ statusLabel(doc.status) }}</span>
              </div>
            </div>
            <div v-if="doc.specialties.length" class="dcard__specs"><span v-for="s in doc.specialties" :key="s" class="stag">{{ s }}</span></div>
            <div class="dcard__meta">
              <span v-if="doc.license_number"><i class="pi pi-id-card"></i> {{ doc.license_number }}</span>
              <span v-if="doc.university"><i class="pi pi-building"></i> {{ doc.university }}</span>
              <span v-if="doc.years_experience"><i class="pi pi-clock"></i> {{ doc.years_experience }} años</span>
            </div>
            <div class="dcard__actions">
              <button class="abtn abtn--edit" @click="openEdit(doc)"><i class="pi pi-pencil"></i> Editar</button>
              <button class="abtn abtn--sched" @click="toggleSchedules(doc.profile_id)"><i class="pi pi-clock"></i> {{ expandedDoctor === doc.profile_id ? 'Ocultar' : 'Horarios' }}</button>
            </div>
            <!-- Schedule section (expanded) -->
            <Transition name="slide-down">
              <div v-if="expandedDoctor === doc.profile_id" class="sched-panel">
                <div v-if="loadingSchedules" class="loading"><i class="pi pi-spin pi-spinner"></i></div>
                <div v-else>
                  <div class="sched-grid">
                    <div v-for="d in [1,2,3,4,5,6,0]" :key="d" class="sched-day">
                      <div class="sched-day__h">{{ dayNames[d] }}</div>
                      <div v-if="doctorSchedules.filter(s => s.day_of_week === d).length === 0" class="sched-day__empty">—</div>
                      <div v-for="s in doctorSchedules.filter(s => s.day_of_week === d)" :key="s.id" class="sched-slot">
                        {{ fmtTime(s.franja_inicio) }}-{{ fmtTime(s.franja_fin) }}
                        <button class="sched-del" @click="deleteScheduleEntry(s.id)"><i class="pi pi-trash"></i></button>
                      </div>
                    </div>
                  </div>
                  <div class="sched-add">
                    <select v-model.number="scheduleForm.day_of_week" class="fi fi-sm"><option v-for="d in [1,2,3,4,5,6,0]" :key="d" :value="d">{{ dayNames[d] }}</option></select>
                    <input v-model="scheduleForm.inicio" type="time" class="fi fi-sm" />
                    <input v-model="scheduleForm.fin" type="time" class="fi fi-sm" />
                    <button class="btn-primary btn-sm" @click="addScheduleForDoctor" :disabled="saving">+ Agregar</button>
                  </div>
                </div>
              </div>
            </Transition>
          </div>
        </div>
      </div>

      <!-- ═══ TAB: USERS ═══ -->
      <div v-if="activeTab === 'users'">
        <div class="toolbar">
          <div class="search-box"><i class="pi pi-search"></i><input v-model="userSearch" class="search-input" placeholder="Buscar..." /></div>
          <div class="status-filters">
            <button v-for="t in [{k:'all',l:'Todos'},{k:'admin',l:'Admin'},{k:'doctor',l:'Médicos'},{k:'patient',l:'Pacientes'}]" :key="t.k" :class="['pill', { 'pill--active': userFilterRole === t.k }]" @click="userFilterRole = t.k">{{ t.l }}</button>
          </div>
        </div>
        <div class="table-wrap">
          <table class="utable">
            <thead><tr><th>Usuario</th><th>Correo</th><th>Rol</th><th>Registrado</th><th>Acciones</th></tr></thead>
            <tbody>
              <tr v-for="u in filteredUsers" :key="u.id">
                <td><div class="ucell"><div class="ucell__av" :style="{ background: getAvatarColor(u.name+' '+(u.last_name||'')) }">{{ getInitials(u.name+' '+(u.last_name||'')) }}</div><span>{{ u.name }} {{ u.last_name }}</span></div></td>
                <td class="td-email">{{ u.email }}</td>
                <td><span :class="['rb', roleClass(u.role)]">{{ roleLabel(u.role) }}</span></td>
                <td class="td-date">{{ fmtDate(u.created_at) }}</td>
                <td><button class="ibtn" title="Cambiar contraseña" @click="openPwdModal(u)"><i class="pi pi-key"></i></button></td>
              </tr>
              <tr v-if="filteredUsers.length === 0"><td colspan="5" class="td-empty">Sin resultados</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ═══ TAB: CONFIG ═══ -->
      <div v-if="activeTab === 'config'" class="config-section">
        <div class="ccard"><h3><i class="pi pi-globe"></i> Sistema</h3>
          <div class="cgrid">
            <div class="ci"><span class="cl">Plataforma</span><span class="cv">Salvia Telemedicina v1.0</span></div>
            <div class="ci"><span class="cl">Backend</span><span class="cv">Laravel 12 + PHP 8.4</span></div>
            <div class="ci"><span class="cl">Frontend</span><span class="cv">Vue 3 + Inertia.js</span></div>
            <div class="ci"><span class="cl">Base de datos</span><span class="cv">PostgreSQL 17 (RLS)</span></div>
            <div class="ci"><span class="cl">Usuarios</span><span class="cv">{{ users.length }}</span></div>
            <div class="ci"><span class="cl">Médicos</span><span class="cv">{{ doctors.length }}</span></div>
          </div>
        </div>
        <div class="ccard"><h3><i class="pi pi-shield"></i> Seguridad</h3>
          <div class="cgrid">
            <div class="ci"><span class="cl">Row Level Security</span><span class="cv cv--ok">✅ Activo</span></div>
            <div class="ci"><span class="cl">Autenticación</span><span class="cv cv--ok">✅ Sesión + CSRF</span></div>
            <div class="ci"><span class="cl">Encriptación</span><span class="cv cv--ok">✅ Bcrypt</span></div>
            <div class="ci"><span class="cl">GIST Anti-solapamiento</span><span class="cv cv--ok">✅ Activo</span></div>
          </div>
        </div>
      </div>

      <!-- Edit Doctor Modal -->
      <Teleport to="body">
        <Transition name="fade">
          <div v-if="editModal && editDoc" class="modal-overlay" @click.self="editModal = false">
            <div class="modal-card">
              <div class="modal-hdr"><div class="modal-av" :style="{ background: getAvatarColor(editDoc.name+' '+editDoc.last_name) }">{{ getInitials(editDoc.name+' '+editDoc.last_name) }}</div><div><h3>{{ editDoc.name }} {{ editDoc.last_name }}</h3><p>{{ editDoc.email }}</p></div><button class="modal-x" @click="editModal = false">×</button></div>
              <div class="modal-body">
                <div class="msec"><h4>📋 Info</h4><div class="igrid"><div class="ii"><span class="il">Licencia</span><span class="iv">{{ editDoc.license_number }}</span></div><div class="ii"><span class="il">Especialidades</span><span class="iv">{{ editDoc.specialties.join(', ') }}</span></div><div class="ii"><span class="il">Timezone</span><span class="iv">{{ editDoc.timezone }}</span></div><div class="ii"><span class="il">Registrado</span><span class="iv">{{ fmtDate(editDoc.created_at) }}</span></div></div></div>
                <div class="msec"><h4>⚙️ Configuración</h4>
                  <div class="fg"><label>Estado</label><div class="st-toggle"><button v-for="o in [{v:'approved',l:'✅ Aprobado',c:'st--ok'},{v:'pending',l:'⏳ Pendiente',c:'st--warn'},{v:'rejected',l:'❌ Rechazado',c:'st--err'}]" :key="o.v" :class="['st-btn',o.c,{'st-btn--a':editForm.status===o.v}]" @click="editForm.status=o.v">{{ o.l }}</button></div></div>
                  <div class="egrid">
                    <div class="fg"><label>Universidad</label><input v-model="editForm.university" class="fi" /></div>
                    <div class="fg"><label>Años exp.</label><input v-model.number="editForm.years_experience" type="number" min="0" class="fi" /></div>
                    <div class="fg"><label>Tarifa (USD)</label><input v-model.number="editForm.consultation_fee" type="number" min="0" class="fi" /></div>
                  </div>
                  <div class="fg" style="margin-top:12px"><label>Descripción</label><textarea v-model="editForm.description" class="fi fi-ta" rows="3"></textarea></div>
                </div>
              </div>
              <div class="modal-ft"><button class="btn-sec" @click="editModal = false">Cancelar</button><button class="btn-primary" @click="saveEdit" :disabled="editSaving">{{ editSaving ? 'Guardando...' : '💾 Guardar' }}</button></div>
            </div>
          </div>
        </Transition>
      </Teleport>

      <!-- Password Modal -->
      <Teleport to="body">
        <Transition name="fade">
          <div v-if="pwdModal" class="modal-overlay" @click.self="pwdModal = false">
            <div class="modal-card modal-card--sm">
              <h3>🔑 Cambiar Contraseña</h3><p class="modal-sub">{{ pwdUserName }}</p>
              <div class="fg"><label>Nueva contraseña</label><input v-model="pwdNew" type="password" class="fi" /></div>
              <div class="fg" style="margin-top:8px"><label>Confirmar</label><input v-model="pwdConfirm" type="password" class="fi" /></div>
              <div class="modal-ft"><button class="btn-sec" @click="pwdModal = false">Cancelar</button><button class="btn-primary" @click="changePwd" :disabled="pwdSaving">{{ pwdSaving ? '...' : 'Cambiar' }}</button></div>
            </div>
          </div>
        </Transition>
      </Teleport>
    </div>
  </AppLayout>
</template>

<style scoped>
.panel { max-width:1100px; margin:0 auto; padding:1rem; }
.panel__header h1 { font-size:1.5rem; font-weight:700; color:#111827; margin:0; }
.panel__subtitle { font-size:.875rem; color:#6B7280; margin:4px 0 0; }
.alert { padding:12px 16px; border-radius:8px; margin:1rem 0; font-size:.9rem; display:flex; justify-content:space-between; align-items:center; }
.alert--success { background:#D1FAE5; color:#065F46; }
.alert--error { background:#FEE2E2; color:#991B1B; }
.alert button { background:none; border:none; font-size:1.2rem; cursor:pointer; color:inherit; }

.tabs { display:flex; gap:4px; margin:1.5rem 0; border-bottom:2px solid #E5E7EB; }
.tab { padding:10px 20px; background:none; border:none; border-bottom:2px solid transparent; margin-bottom:-2px; font-size:.9rem; color:#6B7280; cursor:pointer; display:flex; align-items:center; gap:6px; transition:all .2s; }
.tab--active { color:var(--color-primary,#0E5D52); border-bottom-color:var(--color-primary,#0E5D52); font-weight:600; }
.tab-badge { background:#EF4444; color:#FFF; font-size:.65rem; padding:1px 6px; border-radius:8px; font-weight:700; }

.toolbar { display:flex; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap; align-items:center; justify-content:space-between; }
.status-filters { display:flex; gap:4px; flex-wrap:wrap; }
.pill { padding:6px 14px; background:#F3F4F6; border:1px solid transparent; border-radius:20px; font-size:.8rem; color:#374151; cursor:pointer; transition:all .2s; display:flex; align-items:center; gap:4px; }
.pill--active { background:var(--color-primary,#0E5D52); color:#FFF; }
.pill__c { background:rgba(0,0,0,.08); padding:0 6px; border-radius:8px; font-size:.72rem; font-weight:700; }
.pill--active .pill__c { background:rgba(255,255,255,.25); }

.btn-primary { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; background:var(--color-primary,#0E5D52); color:#FFF; border:none; border-radius:8px; font-size:.85rem; font-weight:600; cursor:pointer; transition:all .2s; }
.btn-primary:hover:not(:disabled) { filter:brightness(1.15); }
.btn-primary:disabled { opacity:.5; cursor:not-allowed; }
.btn-primary.btn-sm { padding:6px 12px; font-size:.78rem; }
.btn-sec { padding:8px 16px; background:#F3F4F6; color:#374151; border:1px solid #D1D5DB; border-radius:8px; font-size:.85rem; font-weight:500; cursor:pointer; }

.form-card { background:#FFF; border:1px solid #E5E7EB; border-radius:12px; padding:20px; margin-bottom:1.5rem; box-shadow:0 2px 8px rgba(0,0,0,.04); }
.form-card h3 { margin:0 0 1rem; font-size:1rem; }
.form-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:1rem; }
.fg { display:flex; flex-direction:column; gap:4px; }
.fg label { font-size:.78rem; font-weight:600; color:#374151; }
.fg--photo { margin-bottom:14px; }
.photo-picker { display:flex; align-items:center; gap:14px; }
.photo-picker__preview { width:60px; height:60px; border-radius:50%; object-fit:cover; object-position:top; flex-shrink:0; }
.photo-picker__placeholder { width:60px; height:60px; border-radius:50%; background:#F3F4F6; color:#9CA3AF; display:flex; align-items:center; justify-content:center; font-size:1.3rem; flex-shrink:0; }
.fi { padding:7px 10px; border:1px solid #D1D5DB; border-radius:8px; font-size:.88rem; outline:none; }
.fi:focus { border-color:var(--color-primary,#0E5D52); }
.fi-sm { padding:5px 8px; font-size:.82rem; max-width:120px; }
.fi-ta { resize:vertical; min-height:60px; }
.form-actions { margin-top:1rem; display:flex; justify-content:flex-end; gap:10px; }

.spec-sel { display:flex; flex-wrap:wrap; gap:6px; }
.sp-btn { padding:5px 12px; border:1px solid #D1D5DB; background:#FFF; color:#374151; border-radius:20px; font-size:.78rem; font-weight:500; cursor:pointer; transition:all .2s; }
.sp-btn--a { background:var(--color-primary,#0E5D52); color:#FFF; border-color:var(--color-primary,#0E5D52); }

.loading { display:flex; align-items:center; gap:8px; justify-content:center; padding:2rem; color:#6B7280; }
.empty { display:flex; flex-direction:column; align-items:center; gap:8px; padding:3rem; color:#9CA3AF; }

.doc-list { display:flex; flex-direction:column; gap:1rem; }
.dcard { background:#FFF; border:1px solid #E5E7EB; border-radius:12px; padding:16px; box-shadow:0 2px 8px rgba(0,0,0,.04); }
.dcard--pending { border-left:4px solid #F59E0B; }
.dcard--approved { border-left:4px solid #10B981; }
.dcard--rejected { border-left:4px solid #EF4444; }
.dcard__top { display:flex; align-items:center; gap:12px; margin-bottom:8px; }
.dcard__av { width:42px; height:42px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#FFF; font-weight:700; font-size:.82rem; flex-shrink:0; }
.dcard__av--photo { object-fit:cover; object-position:top; }
.dcard__info { flex:1; }
.dcard__info h3 { margin:0; font-size:.95rem; font-weight:600; color:#111827; }
.dcard__email { font-size:.78rem; color:#6B7280; }
.badge { display:inline-block; padding:1px 8px; border-radius:8px; font-size:.68rem; font-weight:600; margin-left:6px; }
.badge--success { background:#D1FAE5; color:#065F46; }
.badge--warning { background:#FEF3C7; color:#92400E; }
.badge--danger { background:#FEE2E2; color:#991B1B; }
.dcard__specs { display:flex; flex-wrap:wrap; gap:4px; margin-bottom:6px; }
.stag { padding:1px 8px; background:#EFF6FF; color:#1D4ED8; border-radius:8px; font-size:.7rem; font-weight:600; }
.dcard__meta { display:flex; flex-wrap:wrap; gap:10px; font-size:.75rem; color:#6B7280; margin-bottom:8px; }
.dcard__meta span { display:flex; align-items:center; gap:3px; }
.dcard__actions { display:flex; gap:6px; padding-top:8px; border-top:1px solid #F3F4F6; }
.abtn { padding:6px 14px; border:1px solid; border-radius:6px; font-size:.78rem; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:4px; transition:all .2s; }
.abtn--edit { background:#F0F9FF; color:#0369A1; border-color:#7DD3FC; }
.abtn--edit:hover { background:#E0F2FE; }
.abtn--sched { background:#FFF7ED; color:#9A3412; border-color:#FDBA74; }
.abtn--sched:hover { background:#FFF1E0; }

.sched-panel { margin-top:12px; padding:12px; background:#FAFAFA; border-radius:8px; border:1px solid #E5E7EB; }
.sched-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:4px; margin-bottom:10px; }
.sched-day { text-align:center; }
.sched-day__h { font-size:.72rem; font-weight:700; color:#374151; padding:4px; background:#E5E7EB; border-radius:4px 4px 0 0; }
.sched-day__empty { font-size:.75rem; color:#9CA3AF; padding:8px 0; }
.sched-slot { font-size:.75rem; font-weight:500; color:#065F46; background:#D1FAE5; border-radius:4px; padding:4px; margin-top:2px; display:flex; align-items:center; justify-content:space-between; }
.sched-del { background:none; border:none; color:#9CA3AF; cursor:pointer; font-size:.7rem; padding:1px; }
.sched-del:hover { color:#EF4444; }
.sched-add { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }

.search-box { display:flex; align-items:center; gap:8px; background:#FFF; border:1px solid #D1D5DB; border-radius:8px; padding:6px 12px; flex:1; min-width:200px; }
.search-box i { color:#9CA3AF; font-size:.85rem; }
.search-input { border:none; outline:none; font-size:.9rem; width:100%; background:transparent; }

.table-wrap { background:#FFF; border:1px solid #E5E7EB; border-radius:12px; overflow-x:auto; box-shadow:0 2px 8px rgba(0,0,0,.04); }
.utable { width:100%; border-collapse:collapse; font-size:.85rem; }
.utable thead { background:#F9FAFB; }
.utable th { padding:10px 14px; text-align:left; font-weight:600; color:#374151; font-size:.78rem; text-transform:uppercase; }
.utable td { padding:10px 14px; border-top:1px solid #F3F4F6; color:#374151; }
.utable tbody tr:hover { background:#FAFAFA; }
.ucell { display:flex; align-items:center; gap:8px; }
.ucell__av { width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#FFF; font-weight:700; font-size:.65rem; flex-shrink:0; }
.td-email { color:#6B7280; font-size:.8rem; }
.td-date { font-size:.78rem; color:#9CA3AF; }
.td-empty { text-align:center; color:#9CA3AF; padding:2rem!important; }
.rb { display:inline-block; padding:2px 8px; border-radius:8px; font-size:.7rem; font-weight:600; }
.rb--admin { background:#EDE9FE; color:#6D28D9; }
.rb--doctor { background:#DBEAFE; color:#1D4ED8; }
.rb--patient { background:#D1FAE5; color:#065F46; }
.rb--agent { background:#FEF3C7; color:#92400E; }
.ibtn { width:30px; height:30px; border-radius:6px; border:1px solid #E5E7EB; background:#FFF; color:#374151; cursor:pointer; display:flex; align-items:center; justify-content:center; }
.ibtn:hover { border-color:var(--color-primary,#0E5D52); color:var(--color-primary,#0E5D52); }

.config-section { display:flex; flex-direction:column; gap:1.5rem; }
.ccard { background:#FFF; border:1px solid #E5E7EB; border-radius:12px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,.04); }
.ccard h3 { margin:0 0 14px; font-size:1rem; display:flex; align-items:center; gap:8px; }
.cgrid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:10px; }
.ci { display:flex; flex-direction:column; gap:2px; padding:8px; background:#F9FAFB; border-radius:6px; }
.cl { font-size:.72rem; color:#6B7280; font-weight:500; }
.cv { font-size:.85rem; color:#111827; font-weight:600; }
.cv--ok { color:#065F46; }

.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); display:flex; align-items:center; justify-content:center; z-index:9999; padding:1rem; }
.modal-card { background:#FFF; border-radius:14px; width:100%; max-width:540px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.2); }
.modal-card--sm { max-width:400px; padding:24px; }
.modal-hdr { display:flex; align-items:center; gap:12px; padding:20px 24px 0; }
.modal-av { width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#FFF; font-weight:700; font-size:.85rem; flex-shrink:0; }
.modal-hdr h3 { margin:0; font-size:1.05rem; }
.modal-hdr p { margin:2px 0 0; font-size:.8rem; color:#6B7280; }
.modal-sub { margin:4px 0 16px; color:#6B7280; font-size:.88rem; }
.modal-x { margin-left:auto; background:none; border:none; font-size:1.5rem; color:#9CA3AF; cursor:pointer; }
.modal-body { padding:16px 24px; }
.modal-ft { padding:12px 24px 20px; display:flex; justify-content:flex-end; gap:10px; border-top:1px solid #F3F4F6; }
.msec { margin-bottom:16px; }
.msec h4 { margin:0 0 8px; font-size:.9rem; }
.igrid { display:grid; grid-template-columns:1fr 1fr; gap:6px; }
.ii { background:#F9FAFB; border-radius:6px; padding:6px 10px; }
.il { display:block; font-size:.68rem; color:#6B7280; text-transform:uppercase; }
.iv { font-size:.85rem; color:#111827; font-weight:500; }
.egrid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-top:8px; }
.st-toggle { display:flex; gap:4px; }
.st-btn { flex:1; padding:7px 4px; border:2px solid #E5E7EB; border-radius:6px; font-size:.75rem; font-weight:600; cursor:pointer; background:#FFF; color:#374151; transition:all .2s; }
.st-btn--a.st--ok { background:#D1FAE5; border-color:#10B981; color:#065F46; }
.st-btn--a.st--warn { background:#FEF3C7; border-color:#F59E0B; color:#92400E; }
.st-btn--a.st--err { background:#FEE2E2; border-color:#EF4444; color:#991B1B; }

.fade-enter-active,.fade-leave-active { transition:opacity .3s; }
.fade-enter-from,.fade-leave-to { opacity:0; }
.slide-down-enter-active,.slide-down-leave-active { transition:all .3s; }
.slide-down-enter-from,.slide-down-leave-to { transform:translateY(-10px); opacity:0; }

@media (max-width:768px) {
  .sched-grid { grid-template-columns:repeat(3,1fr); }
  .toolbar { flex-direction:column; }
  .igrid,.egrid { grid-template-columns:1fr; }
}
</style>
