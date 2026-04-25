const STORAGE_KEY = 'noc_app_data_v1';
const SESSION_KEY = 'noc_admin_session_v1';
const DEFAULT_USERNAME = 'admin';
const DEFAULT_PASSWORD = '260200';

const defaultData = {
  app: { name: 'NOC App', version: '1.0.0', updatedAt: new Date().toISOString() },
  auth: { username: DEFAULT_USERNAME, passwordHash: null },
  devices: [
    { id: uid(), name:'MKR-BORDER-01', vendor:'MikroTik', type:'Router', site:'Core', host:'103.196.85.103', mode:'API+SNMP', apiPort:29031, snmpPort:161, status:'up', username:'Robot', tag:'core,bgp', notes:'Border router utama', createdAt:nowText() },
    { id: uid(), name:'OLT-ZTE-C320-01', vendor:'ZTE', type:'OLT', site:'POP Donomulyo', host:'103.196.85.37', mode:'SNMP/CLI', apiPort:'', snmpPort:1500, status:'warning', username:'', tag:'olt,gpon', notes:'Target collector SNMP', createdAt:nowText() }
  ],
  bgp: [
    { id: uid(), peer:'ID-IX-A', device:'MKR-BORDER-01', state:'Established', prefixIn:1245, prefixOut:88 },
    { id: uid(), peer:'TRANSIT-1', device:'MKR-BORDER-01', state:'Established', prefixIn:921, prefixOut:54 }
  ],
  backups: [
    { id: uid(), device:'MKR-BORDER-01', type:'mikrotik-rsc', status:'success', time: nowText() }
  ],
  routes: []
};

function uid(){ return 'id-' + Math.random().toString(36).slice(2,10) + Date.now().toString(36); }
function nowText(){ return new Date().toLocaleString('id-ID'); }
async function sha256(text){
  const buf = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(text));
  return Array.from(new Uint8Array(buf)).map(b=>b.toString(16).padStart(2,'0')).join('');
}
async function ensureData(){
  let data = loadData();
  if(!data){
    data = structuredClone(defaultData);
    data.auth.passwordHash = await sha256(DEFAULT_PASSWORD);
    saveData(data);
  } else if(!data.auth?.passwordHash){
    data.auth = data.auth || {};
    data.auth.username = data.auth.username || DEFAULT_USERNAME;
    data.auth.passwordHash = await sha256(DEFAULT_PASSWORD);
    saveData(data);
  }
  return data;
}
function loadData(){ try { return JSON.parse(localStorage.getItem(STORAGE_KEY)); } catch { return null; } }
function saveData(data){ data.app.updatedAt = new Date().toISOString(); localStorage.setItem(STORAGE_KEY, JSON.stringify(data)); }
function setSession(v){ sessionStorage.setItem(SESSION_KEY, v ? '1' : ''); }
function isLoggedIn(){ return sessionStorage.getItem(SESSION_KEY) === '1'; }
function clearSession(){ sessionStorage.removeItem(SESSION_KEY); }
function statusClass(status){ return status === 'up' ? 'status-up' : status === 'warning' ? 'status-warning' : 'status-down'; }
function esc(v){ return String(v ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

let state = null;
let activeTab = 'dashboard';

document.addEventListener('DOMContentLoaded', async () => {
  state = await ensureData();
  renderApp();
});

function renderApp(){
  const root = document.getElementById('appRoot');
  root.innerHTML = isLoggedIn() ? document.getElementById('dashboardTemplate').innerHTML : document.getElementById('loginTemplate').innerHTML;
  if(isLoggedIn()) bindDashboard(); else bindLogin();
}

function bindLogin(){
  document.getElementById('togglePassword').onclick = () => {
    const input = document.getElementById('loginPassword');
    input.type = input.type === 'password' ? 'text' : 'password';
  };
  document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const user = document.getElementById('loginUsername').value.trim();
    const pass = document.getElementById('loginPassword').value;
    const err = document.getElementById('loginError');
    const hash = await sha256(pass);
    if(user !== (state.auth.username || DEFAULT_USERNAME) || hash !== state.auth.passwordHash){
      err.textContent = 'Username atau password salah.';
      err.classList.remove('d-none');
      return;
    }
    setSession(true);
    renderApp();
  });
}

function bindDashboard(){
  bindTabs();
  renderSummary();
  renderDeviceTable();
  renderDashboardDeviceList();
  renderBgpTable();
  renderBackupTable();
  fillDeviceSelects();
  bindDeviceForm();
  bindRouteForm();
  bindBackupForm();
  bindSettings();
  bindTopbar();
}

function bindTabs(){
  document.querySelectorAll('#sidebarTabs .nav-link').forEach(btn => {
    btn.addEventListener('click', () => {
      activeTab = btn.dataset.tab;
      document.querySelectorAll('#sidebarTabs .nav-link').forEach(b => b.classList.toggle('active', b === btn));
      document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.toggle('active', panel.dataset.panel === activeTab));
      document.getElementById('pageTitle').textContent = btn.textContent.trim();
    });
  });
}

function renderSummary(){
  const total = state.devices.length;
  const up = state.devices.filter(d => d.status === 'up').length;
  const warning = state.devices.filter(d => d.status === 'warning').length;
  const down = state.devices.filter(d => d.status === 'down').length;
  const cards = [
    ['Total Device', total, 'router / OLT / switch / node'],
    ['UP', up, 'sehat / reachable'],
    ['Warning', warning, 'butuh perhatian'],
    ['Down', down, 'gangguan aktif'],
    ['BGP Peer', state.bgp.length, 'peer tercatat'],
    ['Backup Jobs', state.backups.length, 'riwayat backup']
  ];
  document.getElementById('summaryCards').innerHTML = cards.map(([label,val,note]) => `
    <div class="col-6 col-xl-2">
      <div class="metric-card">
        <div class="small text-secondary">${label}</div>
        <div class="value">${val}</div>
        <div class="small text-secondary">${note}</div>
      </div>
    </div>
  `).join('');
}

function renderDashboardDeviceList(){
  const wrap = document.getElementById('dashboardDeviceList');
  wrap.innerHTML = state.devices.length ? state.devices.slice(0,6).map(d => `
    <div class="stack-item">
      <div class="d-flex justify-content-between gap-3 flex-wrap align-items-start">
        <div>
          <strong>${esc(d.name)}</strong>
          <span>${esc(d.vendor)} • ${esc(d.type)} • ${esc(d.site || '-')} • ${esc(d.host || '-')}</span>
        </div>
        <span class="status-pill ${statusClass(d.status)}">${esc(d.status)}</span>
      </div>
      <div class="device-meta mt-2">
        <span class="meta-chip">${esc(d.mode || '-')}</span>
        <span class="meta-chip">API ${esc(d.apiPort || '-')}</span>
        <span class="meta-chip">SNMP ${esc(d.snmpPort || '-')}</span>
      </div>
    </div>
  `).join('') : `<div class="stack-item"><strong>Belum ada device</strong><span>Tambahkan router/device pertama dari tab Devices.</span></div>`;
}

function renderDeviceTable(){
  const search = (document.getElementById('deviceSearch')?.value || '').toLowerCase();
  const rows = state.devices.filter(d => JSON.stringify(d).toLowerCase().includes(search));
  document.getElementById('deviceTableWrap').innerHTML = `
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead><tr><th>Device</th><th>Koneksi</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
          ${rows.map(d => `
            <tr>
              <td>
                <div class="fw-semibold">${esc(d.name)}</div>
                <div class="small text-secondary">${esc(d.vendor)} • ${esc(d.type)} • ${esc(d.host || '-')}</div>
                <div class="small text-secondary">${esc(d.site || '-')} • tag: ${esc(d.tag || '-')}</div>
              </td>
              <td>
                <div class="small">Mode: ${esc(d.mode || '-')}</div>
                <div class="small text-secondary">API: ${esc(d.apiPort || '-')} • SNMP: ${esc(d.snmpPort || '-')}</div>
              </td>
              <td><span class="status-pill ${statusClass(d.status)}">${esc(d.status)}</span></td>
              <td>
                <div class="card-actions">
                  <button class="btn btn-sm btn-outline-info" data-action="edit-device" data-id="${d.id}"><i class="bi bi-pencil"></i></button>
                  <button class="btn btn-sm btn-outline-danger" data-action="delete-device" data-id="${d.id}"><i class="bi bi-trash"></i></button>
                </div>
              </td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>`;
  document.querySelectorAll('[data-action="edit-device"]').forEach(btn => btn.onclick = () => fillDeviceForm(btn.dataset.id));
  document.querySelectorAll('[data-action="delete-device"]').forEach(btn => btn.onclick = () => deleteDevice(btn.dataset.id));
  document.getElementById('deviceSearch').oninput = () => renderDeviceTable();
}

function bindDeviceForm(){
  document.getElementById('deviceForm').addEventListener('submit', e => {
    e.preventDefault();
    const id = document.getElementById('deviceId').value || uid();
    const payload = {
      id,
      name: document.getElementById('deviceName').value.trim(),
      vendor: document.getElementById('deviceVendor').value.trim(),
      type: document.getElementById('deviceType').value.trim(),
      site: document.getElementById('deviceSite').value.trim(),
      host: document.getElementById('deviceHost').value.trim(),
      mode: document.getElementById('deviceMode').value,
      apiPort: document.getElementById('deviceApiPort').value.trim(),
      snmpPort: document.getElementById('deviceSnmpPort').value.trim(),
      status: document.getElementById('deviceStatus').value,
      username: document.getElementById('deviceUsername').value.trim(),
      tag: document.getElementById('deviceTag').value.trim(),
      notes: document.getElementById('deviceNotes').value.trim(),
      createdAt: nowText()
    };
    const idx = state.devices.findIndex(d => d.id === id);
    if(idx >= 0) state.devices[idx] = { ...state.devices[idx], ...payload };
    else state.devices.unshift(payload);
    saveAndRefresh();
    resetDeviceForm();
    activeTab = 'devices';
  });
  document.getElementById('resetDeviceForm').onclick = resetDeviceForm;
}

function resetDeviceForm(){
  document.getElementById('deviceForm').reset();
  document.getElementById('deviceId').value = '';
  document.getElementById('deviceMode').value = 'API+SNMP';
  document.getElementById('deviceStatus').value = 'up';
}

function fillDeviceForm(id){
  const d = state.devices.find(x => x.id === id); if(!d) return;
  document.getElementById('deviceId').value = d.id;
  document.getElementById('deviceName').value = d.name || '';
  document.getElementById('deviceVendor').value = d.vendor || '';
  document.getElementById('deviceType').value = d.type || '';
  document.getElementById('deviceSite').value = d.site || '';
  document.getElementById('deviceHost').value = d.host || '';
  document.getElementById('deviceMode').value = d.mode || 'API+SNMP';
  document.getElementById('deviceApiPort').value = d.apiPort || '';
  document.getElementById('deviceSnmpPort').value = d.snmpPort || '';
  document.getElementById('deviceStatus').value = d.status || 'up';
  document.getElementById('deviceUsername').value = d.username || '';
  document.getElementById('deviceTag').value = d.tag || '';
  document.getElementById('deviceNotes').value = d.notes || '';
  document.querySelector('[data-tab="devices"]').click();
}

function deleteDevice(id){
  if(!confirm('Hapus device ini?')) return;
  const device = state.devices.find(d => d.id === id);
  state.devices = state.devices.filter(d => d.id !== id);
  if(device){
    state.bgp = state.bgp.filter(b => b.device !== device.name);
    state.backups = state.backups.filter(b => b.device !== device.name);
  }
  saveAndRefresh();
}

function renderBgpTable(){
  document.getElementById('bgpTableWrap').innerHTML = `
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead><tr><th>Peer</th><th>Device</th><th>State</th><th>Prefixes</th></tr></thead>
        <tbody>
          ${state.bgp.length ? state.bgp.map(b => `
            <tr>
              <td><div class="fw-semibold">${esc(b.peer)}</div></td>
              <td>${esc(b.device)}</td>
              <td><span class="status-pill ${b.state === 'Established' ? 'status-up' : b.state === 'Active' ? 'status-warning' : 'status-down'}">${esc(b.state)}</span></td>
              <td><div class="small">In: ${esc(b.prefixIn)}</div><div class="small text-secondary">Out: ${esc(b.prefixOut)}</div></td>
            </tr>
          `).join('') : `<tr><td colspan="4" class="text-secondary">Belum ada data BGP.</td></tr>`}
        </tbody>
      </table>
    </div>`;
  document.getElementById('seedBgpBtn').onclick = () => {
    if(state.bgp.length > 0 && !confirm('Tambahkan contoh BGP lagi?')) return;
    const dev = state.devices[0]?.name || 'MKR-BORDER-01';
    state.bgp.push({ id: uid(), peer:'UPSTREAM-AUTO', device:dev, state:'Active', prefixIn:0, prefixOut:0 });
    saveAndRefresh();
  };
}

function fillDeviceSelects(){
  const opts = state.devices.map(d => `<option value="${esc(d.name)}">${esc(d.name)} — ${esc(d.host || d.vendor)}</option>`).join('');
  const empty = `<option value="">Belum ada device</option>`;
  document.getElementById('routeDeviceSelect').innerHTML = opts || empty;
  document.getElementById('backupDeviceSelect').innerHTML = opts || empty;
}

function bindRouteForm(){
  document.getElementById('routeForm').addEventListener('submit', e => {
    e.preventDefault();
    const query = document.getElementById('routeQuery').value.trim();
    const device = document.getElementById('routeDeviceSelect').value || 'Unknown';
    const mock = {
      query, device,
      result: `Best match route untuk ${query} pada ${device}: gateway 10.10.10.1, interface ether1/uplink, distance 1, table main. Ini masih simulasi dan siap diganti ke query real API/router.`
    };
    state.routes.unshift({ id: uid(), ...mock, createdAt: nowText() });
    document.getElementById('routeResult').innerHTML = `<strong>${esc(device)}</strong><br><span class="text-secondary">Query:</span> <code>${esc(query)}</code><br><div class="mt-2">${esc(mock.result)}</div>`;
    saveData(state);
  });
}

function bindBackupForm(){
  document.getElementById('backupForm').addEventListener('submit', e => {
    e.preventDefault();
    const device = document.getElementById('backupDeviceSelect').value;
    const type = document.getElementById('backupType').value;
    if(!device) return alert('Tambahkan device dulu.');
    state.backups.unshift({ id: uid(), device, type, status:'queued', time: nowText() });
    saveAndRefresh();
  });
  document.getElementById('seedBackupBtn').onclick = () => {
    const device = state.devices[0]?.name || 'MKR-BORDER-01';
    state.backups.unshift({ id: uid(), device, type:'olt-export', status:'failed', time: nowText() });
    saveAndRefresh();
  };
}

function renderBackupTable(){
  document.getElementById('backupTableWrap').innerHTML = `
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead><tr><th>Device</th><th>Tipe</th><th>Status</th><th>Waktu</th></tr></thead>
        <tbody>
          ${state.backups.length ? state.backups.map(b => `
            <tr>
              <td class="fw-semibold">${esc(b.device)}</td>
              <td>${esc(b.type)}</td>
              <td><span class="status-pill ${b.status === 'success' ? 'status-up' : b.status === 'queued' ? 'status-warning' : 'status-down'}">${esc(b.status)}</span></td>
              <td class="text-secondary">${esc(b.time)}</td>
            </tr>
          `).join('') : `<tr><td colspan="4" class="text-secondary">Belum ada job backup.</td></tr>`}
        </tbody>
      </table>
    </div>`;
}

function bindSettings(){
  document.getElementById('passwordForm').addEventListener('submit', async e => {
    e.preventDefault();
    const oldPass = document.getElementById('oldPassword').value;
    const newPass = document.getElementById('newPassword').value;
    const confirmPass = document.getElementById('confirmPassword').value;
    const alertBox = document.getElementById('passwordAlert');
    if(await sha256(oldPass) !== state.auth.passwordHash){
      alertBox.innerHTML = `<div class="alert alert-danger mb-0">Password lama salah.</div>`; return;
    }
    if(newPass.length < 4){ alertBox.innerHTML = `<div class="alert alert-warning mb-0">Password baru minimal 4 karakter.</div>`; return; }
    if(newPass !== confirmPass){ alertBox.innerHTML = `<div class="alert alert-warning mb-0">Konfirmasi password tidak sama.</div>`; return; }
    state.auth.passwordHash = await sha256(newPass);
    saveData(state);
    alertBox.innerHTML = `<div class="alert alert-success mb-0">Password admin berhasil diubah.</div>`;
    document.getElementById('passwordForm').reset();
  });
  document.getElementById('resetDemoDataBtn').onclick = async () => {
    if(!confirm('Reset ke data awal demo?')) return;
    state = structuredClone(defaultData);
    state.auth.passwordHash = await sha256(DEFAULT_PASSWORD);
    saveData(state);
    renderApp();
  };
  document.getElementById('clearAllDataBtn').onclick = () => {
    if(!confirm('Hapus semua data device, bgp, dan backup?')) return;
    state.devices = []; state.bgp = []; state.backups = []; state.routes = [];
    saveAndRefresh();
  };
}

function bindTopbar(){
  document.getElementById('logoutBtn').onclick = () => { clearSession(); renderApp(); };
  document.getElementById('exportDataBtn').onclick = () => {
    const blob = new Blob([JSON.stringify(state, null, 2)], { type:'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `noc-app-export-${Date.now()}.json`;
    a.click();
    URL.revokeObjectURL(a.href);
  };
  document.getElementById('importDataInput').addEventListener('change', async e => {
    const file = e.target.files[0]; if(!file) return;
    try {
      const json = JSON.parse(await file.text());
      if(!json.devices || !json.auth) throw new Error('Format tidak valid');
      state = json;
      if(!state.auth.passwordHash) state.auth.passwordHash = await sha256(DEFAULT_PASSWORD);
      saveData(state);
      renderApp();
    } catch(err){ alert('Import gagal: ' + err.message); }
  });
}

function saveAndRefresh(){ saveData(state); renderApp(); }
