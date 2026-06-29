// ── Helpers ──────────────────────────────────────────────────────────────────

const API = '';

function getToken() { return localStorage.getItem('mizan_token'); }
function setToken(t) { localStorage.setItem('mizan_token', t); }
function clearToken() { localStorage.removeItem('mizan_token'); localStorage.removeItem('mizan_user'); }
function getUser() { try { return JSON.parse(localStorage.getItem('mizan_user')); } catch { return null; } }
function setUser(u) { localStorage.setItem('mizan_user', JSON.stringify(u)); }

async function apiFetch(path, options = {}) {
  const token = getToken();
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  if (token) headers['Authorization'] = `Bearer ${token}`;
  const res = await fetch(API + path, { ...options, headers });
  if (res.status === 401) { clearToken(); window.location.href = '/'; return; }
  const data = res.status === 204 ? null : await res.json();
  if (!res.ok) throw new Error(data?.detail || 'حدث خطأ');
  return data;
}

function toast(msg, type = 'info') {
  const c = document.getElementById('toast-container');
  const el = document.createElement('div');
  el.className = `toast toast-${type}`;
  el.textContent = msg;
  c.appendChild(el);
  setTimeout(() => el.remove(), 3500);
}

function formatDate(iso) {
  if (!iso) return '';
  return new Date(iso).toLocaleDateString('ar-SA', { year: 'numeric', month: 'short', day: 'numeric' });
}

function showModal(id) { document.getElementById(id).classList.remove('hidden'); }
function hideModal(id) { document.getElementById(id).classList.add('hidden'); }

// Close modal on overlay click
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.add('hidden');
  }
});

// ── Auth Guard ────────────────────────────────────────────────────────────────

function requireAuth() {
  if (!getToken()) { window.location.href = '/'; return false; }
  return true;
}

// ── Page Router ───────────────────────────────────────────────────────────────

const pages = {};

function registerPage(name, fn) { pages[name] = fn; }

function navigateTo(name) {
  document.querySelectorAll('.nav-item').forEach(el => {
    el.classList.toggle('active', el.dataset.page === name);
  });
  const content = document.getElementById('page-content');
  const title = document.getElementById('page-title');
  if (pages[name]) {
    pages[name](content, title);
    localStorage.setItem('mizan_current_page', name);
  }
}

// ── Dashboard Page ────────────────────────────────────────────────────────────

registerPage('dashboard', async (container, title) => {
  title.textContent = 'لوحة التحكم';
  container.innerHTML = `
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon blue">📄</div>
        <div><div class="stat-num" id="stat-docs">-</div><div class="stat-label">المستندات</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon green">📋</div>
        <div><div class="stat-num" id="stat-templates">-</div><div class="stat-label">القوالب</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon orange">⚖️</div>
        <div><div class="stat-num">∞</div><div class="stat-label">استشارات AI</div></div>
      </div>
    </div>
    <div class="grid-2">
      <div class="card">
        <div class="card-header"><h3>آخر المستندات</h3></div>
        <div class="card-body" id="recent-docs"><div class="text-muted text-sm">جاري التحميل...</div></div>
      </div>
      <div class="card">
        <div class="card-header"><h3>إجراءات سريعة</h3></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:10px">
          <button class="btn btn-primary" onclick="navigateTo('new-document')">➕ مستند جديد</button>
          <button class="btn btn-outline" onclick="navigateTo('templates')">📋 إدارة القوالب</button>
          <button class="btn btn-outline" onclick="navigateTo('chat')">🤖 استشارة قانونية</button>
        </div>
      </div>
    </div>`;
  try {
    const [docs, templates] = await Promise.all([
      apiFetch('/api/documents'),
      apiFetch('/api/templates'),
    ]);
    document.getElementById('stat-docs').textContent = docs.length;
    document.getElementById('stat-templates').textContent = templates.length;
    const recentEl = document.getElementById('recent-docs');
    if (docs.length === 0) {
      recentEl.innerHTML = '<div class="empty-state"><div class="icon">📄</div><p>لا توجد مستندات بعد</p></div>';
    } else {
      recentEl.innerHTML = docs.slice(0, 5).map(d => `
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border)">
          <div>
            <div style="font-weight:500">${d.title}</div>
            <div class="text-muted text-sm">${formatDate(d.updated_at)}</div>
          </div>
          <button class="btn btn-sm btn-outline" onclick="openDocument(${d.id})">فتح</button>
        </div>`).join('');
    }
  } catch (e) { toast(e.message, 'error'); }
});

// ── Documents Page ────────────────────────────────────────────────────────────

registerPage('documents', async (container, title) => {
  title.textContent = 'المستندات';
  container.innerHTML = `
    <div class="card">
      <div class="card-header">
        <h3>جميع المستندات</h3>
        <button class="btn btn-primary btn-sm" onclick="navigateTo('new-document')">➕ مستند جديد</button>
      </div>
      <div class="card-body">
        <div class="table-wrap">
          <table>
            <thead><tr><th>العنوان</th><th>تاريخ الإنشاء</th><th>آخر تعديل</th><th>إجراءات</th></tr></thead>
            <tbody id="docs-tbody"><tr><td colspan="4" class="text-muted text-sm" style="text-align:center;padding:30px">جاري التحميل...</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>`;
  try {
    const docs = await apiFetch('/api/documents');
    const tbody = document.getElementById('docs-tbody');
    if (docs.length === 0) {
      tbody.innerHTML = '<tr><td colspan="4"><div class="empty-state"><div class="icon">📄</div><p>لا توجد مستندات بعد</p></div></td></tr>';
    } else {
      tbody.innerHTML = docs.map(d => `
        <tr>
          <td><strong>${d.title}</strong></td>
          <td>${formatDate(d.created_at)}</td>
          <td>${formatDate(d.updated_at)}</td>
          <td>
            <div class="flex gap-2">
              <button class="btn btn-sm btn-outline" onclick="openDocument(${d.id})">✏️ تعديل</button>
              <a class="btn btn-sm btn-success" href="/api/export/word/${d.id}" onclick="addAuthToLink(event, this)">⬇️ Word</a>
              <button class="btn btn-sm btn-danger" onclick="deleteDocument(${d.id})">🗑️</button>
            </div>
          </td>
        </tr>`).join('');
    }
  } catch (e) { toast(e.message, 'error'); }
});

async function addAuthToLink(e, el) {
  e.preventDefault();
  const res = await fetch(el.href, { headers: { Authorization: `Bearer ${getToken()}` } });
  if (!res.ok) { toast('فشل التصدير', 'error'); return; }
  const blob = await res.blob();
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url; a.download = el.href.split('/').pop() + '.docx';
  a.click(); URL.revokeObjectURL(url);
}

async function deleteDocument(id) {
  if (!confirm('هل تريد حذف هذا المستند؟')) return;
  try {
    await apiFetch(`/api/documents/${id}`, { method: 'DELETE' });
    toast('تم الحذف', 'success');
    navigateTo('documents');
  } catch (e) { toast(e.message, 'error'); }
}

function openDocument(id) {
  localStorage.setItem('mizan_open_doc', id);
  navigateTo('editor');
}

// ── New Document Page ─────────────────────────────────────────────────────────

registerPage('new-document', async (container, title) => {
  title.textContent = 'مستند جديد';
  let templates = [];
  try { templates = await apiFetch('/api/templates'); } catch {}

  container.innerHTML = `
    <div class="card" style="max-width:680px">
      <div class="card-header"><h3>إنشاء مستند جديد</h3></div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">عنوان المستند *</label>
          <input class="form-control" id="nd-title" placeholder="مثال: عقد إيجار - أحمد علي">
        </div>
        <div class="form-group">
          <label class="form-label">اختر قالباً (اختياري)</label>
          <select class="form-control" id="nd-template" onchange="loadTemplateVars()">
            <option value="">-- بدون قالب --</option>
            ${templates.map(t => `<option value="${t.id}">${t.name}</option>`).join('')}
          </select>
        </div>
        <div id="nd-vars"></div>
        <button class="btn btn-primary" onclick="createDocument()">إنشاء المستند</button>
      </div>
    </div>`;

  window._templates = templates;
});

async function loadTemplateVars() {
  const tid = document.getElementById('nd-template').value;
  const container = document.getElementById('nd-vars');
  if (!tid) { container.innerHTML = ''; return; }
  const tmpl = window._templates.find(t => t.id == tid);
  if (!tmpl || !tmpl.variables.length) { container.innerHTML = ''; return; }
  container.innerHTML = `<hr style="margin:16px 0"><p class="text-sm text-muted mb-4">متغيرات القالب:</p>` +
    tmpl.variables.map(v => `
      <div class="form-group">
        <label class="form-label">${v.label}</label>
        <input class="form-control" id="var-${v.key}" placeholder="${v.label}" data-key="${v.key}">
      </div>`).join('');
}

async function createDocument() {
  const title = document.getElementById('nd-title').value.trim();
  if (!title) { toast('أدخل عنوان المستند', 'error'); return; }
  const tid = document.getElementById('nd-template').value;
  const varInputs = document.querySelectorAll('[data-key]');
  const variable_values = {};
  varInputs.forEach(el => { variable_values[el.dataset.key] = el.value; });
  try {
    const doc = await apiFetch('/api/documents', {
      method: 'POST',
      body: JSON.stringify({ title, template_id: tid ? parseInt(tid) : null, variable_values }),
    });
    toast('تم إنشاء المستند', 'success');
    openDocument(doc.id);
  } catch (e) { toast(e.message, 'error'); }
}

// ── Document Editor Page ──────────────────────────────────────────────────────

registerPage('editor', async (container, title) => {
  title.textContent = 'محرر المستند';
  const docId = localStorage.getItem('mizan_open_doc');
  if (!docId) { navigateTo('documents'); return; }
  let doc;
  try { doc = await apiFetch(`/api/documents/${docId}`); } catch (e) { toast(e.message, 'error'); navigateTo('documents'); return; }

  container.innerHTML = `
    <div style="display:flex;gap:20px;align-items:flex-start">
      <div style="flex:1">
        <div class="card">
          <div class="card-header">
            <input class="form-control" id="ed-title" value="${doc.title}" style="max-width:360px;border:none;font-size:1.05rem;font-weight:600;padding:0">
            <div class="flex gap-2">
              <button class="btn btn-primary btn-sm" onclick="saveDocument(${doc.id})">💾 حفظ</button>
              <a class="btn btn-success btn-sm" href="/api/export/word/${doc.id}" onclick="addAuthToLink(event,this)">⬇️ Word</a>
            </div>
          </div>
          <div class="card-body" style="padding:0">
            <textarea class="form-control" id="ed-content" style="border:none;border-radius:0;min-height:500px;padding:20px;font-size:.95rem">${doc.content || ''}</textarea>
          </div>
        </div>
      </div>
      <div style="width:300px;flex-shrink:0">
        <div class="card">
          <div class="card-header"><h3>🤖 مساعد AI</h3></div>
          <div style="height:340px;overflow-y:auto;padding:12px;display:flex;flex-direction:column;gap:10px" id="doc-chat-msgs"></div>
          <div style="padding:12px;border-top:1px solid var(--border);display:flex;gap:8px">
            <input class="form-control" id="doc-chat-input" placeholder="اسأل عن المستند..." style="flex:1">
            <button class="btn btn-primary btn-sm" onclick="sendDocChat(${doc.id})">إرسال</button>
          </div>
        </div>
      </div>
    </div>`;

  document.getElementById('doc-chat-input').addEventListener('keydown', e => {
    if (e.key === 'Enter') sendDocChat(doc.id);
  });
});

async function saveDocument(id) {
  const title = document.getElementById('ed-title').value;
  const content = document.getElementById('ed-content').value;
  try {
    await apiFetch(`/api/documents/${id}`, { method: 'PATCH', body: JSON.stringify({ title, content }) });
    toast('تم الحفظ', 'success');
  } catch (e) { toast(e.message, 'error'); }
}

async function sendDocChat(docId) {
  const input = document.getElementById('doc-chat-input');
  const msg = input.value.trim();
  if (!msg) return;
  input.value = '';
  const msgs = document.getElementById('doc-chat-msgs');
  msgs.innerHTML += `<div style="background:var(--primary);color:#fff;padding:8px 12px;border-radius:10px;font-size:.88rem;align-self:flex-end;max-width:90%">${msg}</div>`;
  msgs.scrollTop = msgs.scrollHeight;
  try {
    const res = await apiFetch('/api/chat', { method: 'POST', body: JSON.stringify({ message: msg, document_id: docId }) });
    msgs.innerHTML += `<div style="background:#f1f5f9;padding:8px 12px;border-radius:10px;font-size:.88rem;max-width:90%">${res.answer}</div>`;
    msgs.scrollTop = msgs.scrollHeight;
  } catch (e) { toast(e.message, 'error'); }
}

// ── Templates Page ────────────────────────────────────────────────────────────

registerPage('templates', async (container, title) => {
  title.textContent = 'القوالب القانونية';
  container.innerHTML = `
    <div class="card">
      <div class="card-header">
        <h3>القوالب</h3>
        <button class="btn btn-primary btn-sm" onclick="showModal('modal-template')">➕ قالب جديد</button>
      </div>
      <div class="card-body">
        <div class="table-wrap">
          <table>
            <thead><tr><th>اسم القالب</th><th>الوصف</th><th>المتغيرات</th><th>التاريخ</th><th>إجراءات</th></tr></thead>
            <tbody id="tpl-tbody"></tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="modal-overlay hidden" id="modal-template">
      <div class="modal">
        <div class="modal-header">
          <h3>قالب جديد</h3>
          <button class="btn-icon" onclick="hideModal('modal-template')">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">اسم القالب *</label>
            <input class="form-control" id="tpl-name" placeholder="مثال: عقد إيجار">
          </div>
          <div class="form-group">
            <label class="form-label">الوصف</label>
            <input class="form-control" id="tpl-desc" placeholder="وصف مختصر">
          </div>
          <div class="form-group">
            <label class="form-label">المتغيرات <span class="text-muted text-sm">(استخدم {{اسم_المتغير}} في المحتوى)</span></label>
            <div id="tpl-vars-list"></div>
            <button class="btn btn-outline btn-sm mt-2" onclick="addVarRow()">➕ إضافة متغير</button>
          </div>
          <div class="form-group">
            <label class="form-label">محتوى القالب *</label>
            <textarea class="form-control" id="tpl-content" style="min-height:200px" placeholder="اكتب محتوى القالب هنا. استخدم {{اسم_المتغير}} للمتغيرات..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" onclick="hideModal('modal-template')">إلغاء</button>
          <button class="btn btn-primary" onclick="saveTemplate()">حفظ القالب</button>
        </div>
      </div>
    </div>`;

  await refreshTemplates();
});

async function refreshTemplates() {
  try {
    const templates = await apiFetch('/api/templates');
    const tbody = document.getElementById('tpl-tbody');
    if (!tbody) return;
    if (templates.length === 0) {
      tbody.innerHTML = '<tr><td colspan="5"><div class="empty-state"><div class="icon">📋</div><p>لا توجد قوالب بعد</p></div></td></tr>';
    } else {
      tbody.innerHTML = templates.map(t => `
        <tr>
          <td><strong>${t.name}</strong></td>
          <td>${t.description || '-'}</td>
          <td><span class="badge badge-info">${t.variables.length} متغير</span></td>
          <td>${formatDate(t.created_at)}</td>
          <td>
            <button class="btn btn-sm btn-danger" onclick="deleteTemplate(${t.id})">🗑️ حذف</button>
          </td>
        </tr>`).join('');
    }
  } catch (e) { toast(e.message, 'error'); }
}

function addVarRow() {
  const list = document.getElementById('tpl-vars-list');
  const row = document.createElement('div');
  row.className = 'var-row';
  row.innerHTML = `
    <input class="form-control" placeholder="المفتاح (بالإنجليزية)" data-var-key style="flex:1">
    <input class="form-control" placeholder="التسمية (بالعربية)" data-var-label style="flex:1">
    <button class="btn-icon" onclick="this.parentElement.remove()">✕</button>`;
  list.appendChild(row);
}

async function saveTemplate() {
  const name = document.getElementById('tpl-name').value.trim();
  const content = document.getElementById('tpl-content').value.trim();
  if (!name || !content) { toast('أدخل الاسم والمحتوى', 'error'); return; }
  const variables = [];
  document.querySelectorAll('.var-row').forEach(row => {
    const key = row.querySelector('[data-var-key]').value.trim();
    const label = row.querySelector('[data-var-label]').value.trim();
    if (key && label) variables.push({ key, label, type: 'text' });
  });
  try {
    await apiFetch('/api/templates', {
      method: 'POST',
      body: JSON.stringify({ name, description: document.getElementById('tpl-desc').value, variables, content }),
    });
    hideModal('modal-template');
    toast('تم حفظ القالب', 'success');
    await refreshTemplates();
  } catch (e) { toast(e.message, 'error'); }
}

async function deleteTemplate(id) {
  if (!confirm('حذف هذا القالب؟')) return;
  try {
    await apiFetch(`/api/templates/${id}`, { method: 'DELETE' });
    toast('تم الحذف', 'success');
    await refreshTemplates();
  } catch (e) { toast(e.message, 'error'); }
}

// ── Chat Page ─────────────────────────────────────────────────────────────────

registerPage('chat', (container, title) => {
  title.textContent = 'الاستشارة القانونية بالذكاء الاصطناعي';
  container.innerHTML = `
    <div class="chat-wrap">
      <div class="chat-messages" id="chat-msgs">
        <div class="msg msg-ai">
          <div class="msg-bubble">مرحباً! أنا مساعدك القانوني. كيف يمكنني مساعدتك اليوم؟</div>
          <div class="msg-time">الآن</div>
        </div>
      </div>
      <div class="chat-input-row">
        <input class="form-control" id="chat-input" placeholder="اكتب سؤالك القانوني هنا...">
        <button class="btn btn-primary" id="chat-send-btn" onclick="sendChat()">إرسال</button>
      </div>
    </div>`;
  document.getElementById('chat-input').addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendChat(); }
  });
});

let chatSessionId = null;

async function sendChat() {
  const input = document.getElementById('chat-input');
  const msg = input.value.trim();
  if (!msg) return;
  input.value = '';

  const msgs = document.getElementById('chat-msgs');
  const now = new Date().toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit' });
  msgs.innerHTML += `<div class="msg msg-user"><div class="msg-bubble">${msg}</div><div class="msg-time">${now}</div></div>`;
  msgs.innerHTML += `<div class="msg msg-ai" id="ai-typing"><div class="msg-bubble">...</div></div>`;
  msgs.scrollTop = msgs.scrollHeight;

  try {
    const res = await apiFetch('/api/chat', {
      method: 'POST',
      body: JSON.stringify({ message: msg, session_id: chatSessionId }),
    });
    chatSessionId = res.session_id;
    document.getElementById('ai-typing').outerHTML = `
      <div class="msg msg-ai"><div class="msg-bubble">${res.answer}</div><div class="msg-time">${now}</div></div>`;
  } catch (e) {
    document.getElementById('ai-typing').outerHTML = `
      <div class="msg msg-ai"><div class="msg-bubble" style="color:var(--danger)">حدث خطأ: ${e.message}</div></div>`;
  }
  msgs.scrollTop = msgs.scrollHeight;
}

// ── Init ──────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
  const token = getToken();
  const isAuthPage = document.getElementById('auth-page');

  if (isAuthPage) {
    if (token) window.location.href = '/dashboard';
    return;
  }

  if (!token) { window.location.href = '/'; return; }

  const user = getUser();
  if (user) {
    const nameEl = document.getElementById('user-name');
    const emailEl = document.getElementById('user-email');
    if (nameEl) nameEl.textContent = user.full_name || user.email;
    if (emailEl) emailEl.textContent = user.email;
  }

  const saved = localStorage.getItem('mizan_current_page') || 'dashboard';
  navigateTo(saved);
});
