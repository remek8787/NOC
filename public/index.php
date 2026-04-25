<?php
session_start();

define('APP_STORAGE', __DIR__ . '/data/app-storage.json');
define('DEFAULT_USERNAME', 'admin');
define('DEFAULT_PASSWORD', '260200');

define('APP_NAME', 'NOC ISP Tools');

date_default_timezone_set('Asia/Makassar');

function now_iso() {
    return date('c');
}

function now_text() {
    return date('d M Y H:i:s');
}

function app_bootstrap() {
    if (!is_dir(__DIR__ . '/data')) {
        @mkdir(__DIR__ . '/data', 0775, true);
    }
    if (!file_exists(APP_STORAGE)) {
        $seed = [
            'auth' => [
                'username' => DEFAULT_USERNAME,
                'password_hash' => password_hash(DEFAULT_PASSWORD, PASSWORD_DEFAULT),
                'updated_at' => now_iso(),
            ],
            'meta' => [
                'app_name' => APP_NAME,
                'version' => '2.0.0-hosting-native',
                'updated_at' => now_iso(),
            ],
            'devices' => [
                [
                    'id' => uniqid('dev_', true),
                    'name' => 'MKR-BORDER-01',
                    'vendor' => 'MikroTik',
                    'device_type' => 'Router',
                    'site' => 'Core',
                    'host' => '103.196.85.103',
                    'connection_mode' => 'API+SNMP',
                    'api_port' => '29031',
                    'snmp_port' => '161',
                    'snmp_version' => '2c',
                    'api_username' => 'Robot',
                    'status' => 'up',
                    'tags' => 'core,bgp',
                    'notes' => 'Contoh border router MikroTik',
                    'last_probe' => null,
                    'created_at' => now_iso(),
                    'updated_at' => now_iso(),
                ],
                [
                    'id' => uniqid('dev_', true),
                    'name' => 'OLT-ZTE-C320-01',
                    'vendor' => 'ZTE',
                    'device_type' => 'OLT',
                    'site' => 'POP Donomulyo',
                    'host' => '103.196.85.37',
                    'connection_mode' => 'SNMP/CLI',
                    'api_port' => '',
                    'snmp_port' => '1500',
                    'snmp_version' => '2c',
                    'api_username' => '',
                    'status' => 'warning',
                    'tags' => 'olt,gpon',
                    'notes' => 'Contoh OLT target SNMP',
                    'last_probe' => null,
                    'created_at' => now_iso(),
                    'updated_at' => now_iso(),
                ],
            ],
            'probe_logs' => [],
            'backup_jobs' => [
                [
                    'id' => uniqid('bkp_', true),
                    'device_name' => 'MKR-BORDER-01',
                    'backup_type' => 'mikrotik-rsc',
                    'status' => 'queued',
                    'message' => 'Template job awal — siap disambung ke real export/backup command bila hosting dan vendor memungkinkan.',
                    'created_at' => now_iso(),
                ]
            ],
            'bgp_peers' => [
                [
                    'id' => uniqid('bgp_', true),
                    'device_name' => 'MKR-BORDER-01',
                    'peer_name' => 'ID-IX-A',
                    'state' => 'Template',
                    'prefix_in' => '—',
                    'prefix_out' => '—',
                    'notes' => 'Shell panel siap untuk integrasi real nanti.'
                ]
            ],
            'route_queries' => [],
        ];
        file_put_contents(APP_STORAGE, json_encode($seed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

function app_load() {
    app_bootstrap();
    $raw = @file_get_contents(APP_STORAGE);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function app_save($data) {
    $data['meta']['updated_at'] = now_iso();
    file_put_contents(APP_STORAGE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function flash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash() {
    if (!isset($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function is_logged_in() {
    return !empty($_SESSION['noc_admin']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: ./');
        exit;
    }
}

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function post($key, $default = '') {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}

function redirect_tab($tab = 'dashboard') {
    header('Location: ./?tab=' . urlencode($tab));
    exit;
}

function status_badge_class($status) {
    $status = strtolower((string)$status);
    if ($status === 'up' || $status === 'open' || $status === 'success') return 'text-bg-success';
    if ($status === 'warning' || $status === 'queued' || $status === 'template') return 'text-bg-warning';
    return 'text-bg-danger';
}

function run_port_probe($host, $port, $label) {
    $result = [
        'label' => $label,
        'host' => $host,
        'port' => $port,
        'status' => 'closed',
        'message' => 'Port tidak dapat dijangkau',
        'checked_at' => now_iso(),
    ];
    if ($host === '' || $port === '') {
        $result['status'] = 'warning';
        $result['message'] = 'Host/port kosong';
        return $result;
    }
    $errno = 0;
    $errstr = '';
    $fp = @fsockopen($host, (int)$port, $errno, $errstr, 3);
    if ($fp) {
        fclose($fp);
        $result['status'] = 'open';
        $result['message'] = 'Port terbuka / reachable dari hosting';
    } else {
        $result['status'] = 'closed';
        $result['message'] = $errstr !== '' ? $errstr : ('Connection failed #' . $errno);
    }
    return $result;
}

$data = app_load();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'login') {
        $username = post('username');
        $password = post('password');
        if ($username === ($data['auth']['username'] ?? DEFAULT_USERNAME) && password_verify($password, $data['auth']['password_hash'])) {
            $_SESSION['noc_admin'] = true;
            flash('success', 'Login berhasil.');
            redirect_tab('dashboard');
        }
        flash('danger', 'Username atau password salah.');
        redirect_tab('login');
    }

    if ($action === 'logout') {
        session_destroy();
        session_start();
        flash('success', 'Logout berhasil.');
        header('Location: ./');
        exit;
    }

    require_login();

    if ($action === 'save_device') {
        $id = post('id');
        $payload = [
            'id' => $id !== '' ? $id : uniqid('dev_', true),
            'name' => post('name'),
            'vendor' => post('vendor'),
            'device_type' => post('device_type'),
            'site' => post('site'),
            'host' => post('host'),
            'connection_mode' => post('connection_mode'),
            'api_port' => post('api_port'),
            'snmp_port' => post('snmp_port'),
            'snmp_version' => post('snmp_version'),
            'api_username' => post('api_username'),
            'status' => post('status', 'up'),
            'tags' => post('tags'),
            'notes' => post('notes'),
            'last_probe' => null,
            'created_at' => now_iso(),
            'updated_at' => now_iso(),
        ];

        $updated = false;
        foreach ($data['devices'] as $idx => $device) {
            if ($device['id'] === $payload['id']) {
                $payload['created_at'] = $device['created_at'] ?? now_iso();
                $payload['last_probe'] = $device['last_probe'] ?? null;
                $data['devices'][$idx] = $payload;
                $updated = true;
                break;
            }
        }
        if (!$updated) {
            array_unshift($data['devices'], $payload);
        }
        app_save($data);
        flash('success', 'Device berhasil disimpan.');
        redirect_tab('devices');
    }

    if ($action === 'delete_device') {
        $id = post('id');
        $deletedName = '';
        $data['devices'] = array_values(array_filter($data['devices'], function ($device) use ($id, &$deletedName) {
            if ($device['id'] === $id) {
                $deletedName = $device['name'] ?? '';
                return false;
            }
            return true;
        }));
        if ($deletedName !== '') {
            $data['bgp_peers'] = array_values(array_filter($data['bgp_peers'], function ($peer) use ($deletedName) {
                return ($peer['device_name'] ?? '') !== $deletedName;
            }));
            $data['backup_jobs'] = array_values(array_filter($data['backup_jobs'], function ($job) use ($deletedName) {
                return ($job['device_name'] ?? '') !== $deletedName;
            }));
        }
        app_save($data);
        flash('success', 'Device berhasil dihapus.');
        redirect_tab('devices');
    }

    if ($action === 'probe_device') {
        $id = post('id');
        foreach ($data['devices'] as $idx => $device) {
            if ($device['id'] === $id) {
                $probeSet = [];
                if (!empty($device['api_port'])) {
                    $probeSet[] = run_port_probe($device['host'], $device['api_port'], 'API');
                }
                if (!empty($device['snmp_port'])) {
                    $probeSet[] = run_port_probe($device['host'], $device['snmp_port'], 'SNMP');
                }
                if (!$probeSet) {
                    $probeSet[] = [
                        'label' => 'GENERAL',
                        'host' => $device['host'],
                        'port' => '',
                        'status' => 'warning',
                        'message' => 'Tidak ada API/SNMP port yang diisi.',
                        'checked_at' => now_iso(),
                    ];
                }
                $device['last_probe'] = $probeSet;
                $device['updated_at'] = now_iso();
                $data['devices'][$idx] = $device;
                foreach ($probeSet as $probe) {
                    array_unshift($data['probe_logs'], [
                        'id' => uniqid('probe_', true),
                        'device_name' => $device['name'],
                        'vendor' => $device['vendor'],
                        'host' => $device['host'],
                        'label' => $probe['label'],
                        'port' => $probe['port'],
                        'status' => $probe['status'],
                        'message' => $probe['message'],
                        'checked_at' => $probe['checked_at'],
                    ]);
                }
                $data['probe_logs'] = array_slice($data['probe_logs'], 0, 80);
                app_save($data);
                flash('success', 'Probe device selesai dijalankan dari hosting.');
                redirect_tab('devices');
            }
        }
        flash('danger', 'Device tidak ditemukan untuk probe.');
        redirect_tab('devices');
    }

    if ($action === 'queue_backup') {
        $deviceName = post('device_name');
        $backupType = post('backup_type');
        if ($deviceName === '') {
            flash('danger', 'Pilih device dulu.');
            redirect_tab('backups');
        }
        array_unshift($data['backup_jobs'], [
            'id' => uniqid('bkp_', true),
            'device_name' => $deviceName,
            'backup_type' => $backupType,
            'status' => 'queued',
            'message' => 'Job tersimpan di hosting. Tahap berikutnya tinggal sambungkan executor native sesuai vendor/perintah yang memungkinkan.',
            'created_at' => now_iso(),
        ]);
        $data['backup_jobs'] = array_slice($data['backup_jobs'], 0, 80);
        app_save($data);
        flash('success', 'Backup job berhasil ditambahkan ke queue lokal hosting.');
        redirect_tab('backups');
    }

    if ($action === 'save_bgp_template') {
        array_unshift($data['bgp_peers'], [
            'id' => uniqid('bgp_', true),
            'device_name' => post('device_name'),
            'peer_name' => post('peer_name'),
            'state' => post('state', 'Template'),
            'prefix_in' => post('prefix_in', '—'),
            'prefix_out' => post('prefix_out', '—'),
            'notes' => post('notes'),
        ]);
        $data['bgp_peers'] = array_slice($data['bgp_peers'], 0, 80);
        app_save($data);
        flash('success', 'Template peer BGP berhasil disimpan.');
        redirect_tab('bgp');
    }

    if ($action === 'save_route_query') {
        array_unshift($data['route_queries'], [
            'id' => uniqid('route_', true),
            'device_name' => post('device_name'),
            'query' => post('query'),
            'result' => 'Best match route akan diambil dari API/CLI device target. Tahap ini masih shell server-side agar alur kerja NOC sudah siap dipakai.',
            'created_at' => now_iso(),
        ]);
        $data['route_queries'] = array_slice($data['route_queries'], 0, 80);
        app_save($data);
        flash('success', 'Route query tersimpan di log.');
        redirect_tab('routes');
    }

    if ($action === 'change_password') {
        $old = post('old_password');
        $new = post('new_password');
        $confirm = post('confirm_password');
        if (!password_verify($old, $data['auth']['password_hash'])) {
            flash('danger', 'Password lama salah.');
            redirect_tab('settings');
        }
        if ($new === '' || strlen($new) < 4) {
            flash('danger', 'Password baru minimal 4 karakter.');
            redirect_tab('settings');
        }
        if ($new !== $confirm) {
            flash('danger', 'Konfirmasi password tidak sama.');
            redirect_tab('settings');
        }
        $data['auth']['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
        $data['auth']['updated_at'] = now_iso();
        app_save($data);
        flash('success', 'Password admin berhasil diubah.');
        redirect_tab('settings');
    }

    if ($action === 'reset_seed') {
        @unlink(APP_STORAGE);
        $data = app_load();
        flash('success', 'Data berhasil direset ke seed awal.');
        redirect_tab('dashboard');
    }
}

$flash = get_flash();
$tab = isset($_GET['tab']) ? (string)$_GET['tab'] : (is_logged_in() ? 'dashboard' : 'login');
$editId = isset($_GET['edit']) ? (string)$_GET['edit'] : '';
$editDevice = null;
foreach (($data['devices'] ?? []) as $device) {
    if (($device['id'] ?? '') === $editId) {
        $editDevice = $device;
        break;
    }
}

$devices = $data['devices'] ?? [];
$probeLogs = $data['probe_logs'] ?? [];
$backupJobs = $data['backup_jobs'] ?? [];
$bgpPeers = $data['bgp_peers'] ?? [];
$routeQueries = $data['route_queries'] ?? [];
$totalDevices = count($devices);
$upCount = count(array_filter($devices, function($d){ return (isset($d['status']) ? $d['status'] : '') === 'up'; }));
$warningCount = count(array_filter($devices, function($d){ return (isset($d['status']) ? $d['status'] : '') === 'warning'; }));
$downCount = count(array_filter($devices, function($d){ return (isset($d['status']) ? $d['status'] : '') === 'down'; }));
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(APP_NAME) ?> — Hosting Native</title>
  <meta name="theme-color" content="#0b1220">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root{--bg:#07111c;--bg2:#0b1423;--panel:rgba(15,23,42,.92);--border:rgba(148,163,184,.14);--muted:#94a3b8}
    body{background:radial-gradient(circle at top left, rgba(56,189,248,.16), transparent 18%),radial-gradient(circle at bottom right, rgba(59,130,246,.18), transparent 22%),linear-gradient(180deg,var(--bg) 0%,var(--bg2) 100%);color:#e5eefc;min-height:100vh}
    .sidebar{background:rgba(4,8,16,.86);backdrop-filter:blur(12px);border-right:1px solid var(--border);min-height:100vh;padding:24px 18px}
    .shell-card,.metric-card{background:linear-gradient(180deg, rgba(15,23,42,.96), rgba(10,16,28,.98));border:1px solid var(--border);box-shadow:0 20px 48px rgba(0,0,0,.22)}
    .metric-card{border-radius:20px;padding:18px;height:100%}.metric-value{font-size:2rem;font-weight:800}.muted{color:var(--muted)}
    .nav-pills .nav-link{color:#cbd5e1;background:rgba(255,255,255,.03);border-radius:14px;text-align:left;padding:12px 14px}.nav-pills .nav-link.active,.nav-pills .nav-link:hover{background:rgba(56,189,248,.14);color:#fff}
    .brand-box{width:56px;height:56px;border-radius:18px;display:grid;place-items:center;background:linear-gradient(135deg,#0ea5e9,#2563eb);font-size:1.35rem}
    .kicker{font-size:.72rem;letter-spacing:.18em;text-transform:uppercase;color:#7dd3fc;font-weight:700}.table{--bs-table-bg:transparent;--bs-table-color:#e5eefc;--bs-table-border-color:rgba(255,255,255,.06)}
    .table td,.table th{vertical-align:middle}.badge-soft{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08)}
    .mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace}.login-wrap{max-width:520px}.sticky-topbar{position:sticky;top:0;z-index:5;background:rgba(7,17,28,.75);backdrop-filter:blur(10px);border-bottom:1px solid var(--border)}
    code{color:#93c5fd}.preline{white-space:pre-line}
  </style>
</head>
<body>
<?php if (!is_logged_in()): ?>
  <div class="container py-5">
    <div class="row justify-content-center align-items-center min-vh-100">
      <div class="col-12 login-wrap">
        <div class="card shell-card border-0 rounded-4">
          <div class="card-body p-4 p-lg-5">
            <div class="d-flex align-items-center gap-3 mb-4">
              <div class="brand-box"><i class="bi bi-router-fill"></i></div>
              <div>
                <div class="kicker">noc.anantasatriya.my.id</div>
                <h1 class="h3 mb-0">NOC ISP Tools</h1>
              </div>
            </div>
            <p class="muted">Versi hosting-native (PHP + session + JSON storage) untuk shared hosting/LiteSpeed tanpa server tambahan.</p>
            <?php if ($flash): ?>
              <div class="alert alert-<?= e($flash['type']) ?> py-2"><?= e($flash['message']) ?></div>
            <?php endif; ?>
            <form method="post" class="vstack gap-3">
              <input type="hidden" name="action" value="login">
              <div>
                <label class="form-label">Username</label>
                <input name="username" class="form-control form-control-lg" value="admin" required>
              </div>
              <div>
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control form-control-lg" required>
                <div class="form-text text-secondary">Password default saat ini: <strong class="text-warning">260200</strong></div>
              </div>
              <button class="btn btn-info btn-lg fw-semibold"><i class="bi bi-shield-lock me-2"></i>Masuk</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="container-fluid px-0">
    <div class="row g-0">
      <div class="col-12 col-lg-3 col-xl-2 sidebar">
        <div class="d-flex align-items-center gap-3 mb-4">
          <div class="brand-box"><i class="bi bi-broadcast-pin"></i></div>
          <div>
            <div class="kicker">Hosting Native</div>
            <div class="fw-bold">NOC ISP Tools</div>
          </div>
        </div>
        <div class="nav nav-pills flex-column gap-2">
          <a class="nav-link <?= $tab==='dashboard'?'active':'' ?>" href="?tab=dashboard"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
          <a class="nav-link <?= $tab==='devices'?'active':'' ?>" href="?tab=devices"><i class="bi bi-hdd-network me-2"></i>Devices</a>
          <a class="nav-link <?= $tab==='bgp'?'active':'' ?>" href="?tab=bgp"><i class="bi bi-diagram-3 me-2"></i>BGP</a>
          <a class="nav-link <?= $tab==='routes'?'active':'' ?>" href="?tab=routes"><i class="bi bi-signpost-split me-2"></i>Routes</a>
          <a class="nav-link <?= $tab==='backups'?'active':'' ?>" href="?tab=backups"><i class="bi bi-cloud-arrow-down me-2"></i>Backups</a>
          <a class="nav-link <?= $tab==='settings'?'active':'' ?>" href="?tab=settings"><i class="bi bi-gear me-2"></i>Settings</a>
        </div>
        <div class="mt-4 pt-3 border-top border-secondary-subtle small muted">
          <div>Web server: <strong>LiteSpeed</strong></div>
          <div>Mode: <strong>PHP + JSON storage</strong></div>
          <div>Tanpa server tambahan</div>
        </div>
      </div>
      <div class="col-12 col-lg-9 col-xl-10">
        <div class="sticky-topbar px-3 px-lg-4 py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
          <div>
            <div class="kicker">Network Operation Control</div>
            <h2 class="h4 mb-0"><?= e(ucfirst($tab)) ?></h2>
          </div>
          <div class="d-flex gap-2 flex-wrap align-items-center">
            <span class="badge badge-soft rounded-pill">Version <?= e($data['meta']['version'] ?? '-') ?></span>
            <span class="badge text-bg-success rounded-pill">Ready</span>
            <form method="post" class="m-0">
              <input type="hidden" name="action" value="logout">
              <button class="btn btn-sm btn-danger"><i class="bi bi-box-arrow-right me-2"></i>Logout</button>
            </form>
          </div>
        </div>
        <div class="p-3 p-lg-4">
          <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?> py-2 mb-3"><?= e($flash['message']) ?></div>
          <?php endif; ?>

          <?php if ($tab === 'dashboard'): ?>
            <div class="row g-3 mb-4">
              <div class="col-6 col-xl-2"><div class="metric-card"><div class="muted small">Total Device</div><div class="metric-value"><?= e($totalDevices) ?></div><div class="muted small">router / OLT / switch</div></div></div>
              <div class="col-6 col-xl-2"><div class="metric-card"><div class="muted small">UP</div><div class="metric-value"><?= e($upCount) ?></div><div class="muted small">sehat / reachable</div></div></div>
              <div class="col-6 col-xl-2"><div class="metric-card"><div class="muted small">Warning</div><div class="metric-value"><?= e($warningCount) ?></div><div class="muted small">perlu perhatian</div></div></div>
              <div class="col-6 col-xl-2"><div class="metric-card"><div class="muted small">Down</div><div class="metric-value"><?= e($downCount) ?></div><div class="muted small">gangguan aktif</div></div></div>
              <div class="col-6 col-xl-2"><div class="metric-card"><div class="muted small">BGP Peers</div><div class="metric-value"><?= e(count($bgpPeers)) ?></div><div class="muted small">template / real-ready</div></div></div>
              <div class="col-6 col-xl-2"><div class="metric-card"><div class="muted small">Backup Jobs</div><div class="metric-value"><?= e(count($backupJobs)) ?></div><div class="muted small">queue hosting</div></div></div>
            </div>
            <div class="row g-3">
              <div class="col-12 col-xl-7">
                <div class="card shell-card border-0 rounded-4"><div class="card-body">
                  <div class="kicker mb-2">Device capability matrix</div>
                  <div class="table-responsive">
                    <table class="table table-hover align-middle">
                      <thead><tr><th>Device</th><th>Mode</th><th>API</th><th>SNMP</th><th>Status</th></tr></thead>
                      <tbody>
                        <?php foreach ($devices as $device): ?>
                          <tr>
                            <td>
                              <div class="fw-semibold"><?= e($device['name']) ?></div>
                              <div class="small muted"><?= e($device['vendor']) ?> • <?= e($device['device_type']) ?> • <?= e($device['host']) ?></div>
                            </td>
                            <td><?= e($device['connection_mode']) ?></td>
                            <td><?= e($device['api_port'] !== '' ? ('Port ' . $device['api_port']) : '-') ?></td>
                            <td><?= e($device['snmp_port'] !== '' ? ('Port ' . $device['snmp_port']) : '-') ?></td>
                            <td><span class="badge <?= e(status_badge_class($device['status'])) ?>"><?= e(strtoupper($device['status'])) ?></span></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </div></div>
              </div>
              <div class="col-12 col-xl-5">
                <div class="card shell-card border-0 rounded-4"><div class="card-body">
                  <div class="kicker mb-2">Blueprint fokus hosting</div>
                  <ul class="mb-0 muted preline">
                    <li>Tanpa server tambahan: semua berjalan di hosting PHP/LiteSpeed.</li>
                    <li>Data persisten di file JSON hosting.</li>
                    <li>Tool realistis di hosting: registry device, port probe API/SNMP, queue backup, BGP/route shell.</li>
                    <li>Integrasi real bisa ditambah bertahap selama protokol/vendor memungkinkan dari PHP hosting.</li>
                  </ul>
                </div></div>
              </div>
            </div>

          <?php elseif ($tab === 'devices'): ?>
            <div class="row g-3">
              <div class="col-12 col-xl-5">
                <div class="card shell-card border-0 rounded-4"><div class="card-body">
                  <div class="kicker mb-2">Device form</div>
                  <h3 class="h5">Tambah / edit device</h3>
                  <form method="post" class="row g-3 mt-1">
                    <input type="hidden" name="action" value="save_device">
                    <input type="hidden" name="id" value="<?= e($editDevice['id'] ?? '') ?>">
                    <div class="col-md-6"><label class="form-label">Nama Device</label><input name="name" class="form-control" required value="<?= e($editDevice['name'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label">Vendor</label><input name="vendor" class="form-control" required value="<?= e($editDevice['vendor'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label">Tipe</label><input name="device_type" class="form-control" required value="<?= e($editDevice['device_type'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label">Site</label><input name="site" class="form-control" value="<?= e($editDevice['site'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label">Host / IP</label><input name="host" class="form-control" value="<?= e($editDevice['host'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label">Mode Koneksi</label><input name="connection_mode" class="form-control" value="<?= e($editDevice['connection_mode'] ?? 'API+SNMP') ?>"></div>
                    <div class="col-md-4"><label class="form-label">API Port</label><input name="api_port" class="form-control" value="<?= e($editDevice['api_port'] ?? '') ?>"></div>
                    <div class="col-md-4"><label class="form-label">SNMP Port</label><input name="snmp_port" class="form-control" value="<?= e($editDevice['snmp_port'] ?? '') ?>"></div>
                    <div class="col-md-4"><label class="form-label">SNMP Ver</label><input name="snmp_version" class="form-control" value="<?= e($editDevice['snmp_version'] ?? '2c') ?>"></div>
                    <div class="col-md-6"><label class="form-label">API User</label><input name="api_username" class="form-control" value="<?= e($editDevice['api_username'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label">Status</label>
                      <select name="status" class="form-select">
                        <?php foreach (['up','warning','down'] as $opt): ?>
                          <option value="<?= e($opt) ?>" <?= (($editDevice['status'] ?? 'up') === $opt) ? 'selected' : '' ?>><?= e(strtoupper($opt)) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-12"><label class="form-label">Tags</label><input name="tags" class="form-control" value="<?= e($editDevice['tags'] ?? '') ?>"></div>
                    <div class="col-12"><label class="form-label">Catatan</label><textarea name="notes" class="form-control" rows="3"><?= e($editDevice['notes'] ?? '') ?></textarea></div>
                    <div class="col-12 d-flex gap-2 flex-wrap">
                      <button class="btn btn-info"><i class="bi bi-save me-2"></i>Simpan Device</button>
                      <a class="btn btn-outline-light" href="?tab=devices"><i class="bi bi-arrow-counterclockwise me-2"></i>Reset</a>
                    </div>
                  </form>
                </div></div>
              </div>
              <div class="col-12 col-xl-7">
                <div class="card shell-card border-0 rounded-4"><div class="card-body">
                  <div class="kicker mb-2">Device registry</div>
                  <div class="table-responsive">
                    <table class="table table-hover align-middle">
                      <thead><tr><th>Device</th><th>Capability</th><th>Probe</th><th>Aksi</th></tr></thead>
                      <tbody>
                        <?php foreach ($devices as $device): ?>
                          <tr>
                            <td>
                              <div class="fw-semibold"><?= e($device['name']) ?></div>
                              <div class="small muted"><?= e($device['vendor']) ?> • <?= e($device['device_type']) ?> • <?= e($device['host']) ?></div>
                              <div class="small muted"><?= e($device['site']) ?> • <?= e($device['tags']) ?></div>
                            </td>
                            <td>
                              <div class="small"><?= e($device['connection_mode']) ?></div>
                              <div class="small muted">API <?= e($device['api_port'] ?: '-') ?> • SNMP <?= e($device['snmp_port'] ?: '-') ?></div>
                              <div><span class="badge <?= e(status_badge_class($device['status'])) ?> mt-1"><?= e(strtoupper($device['status'])) ?></span></div>
                            </td>
                            <td>
                              <?php if (!empty($device['last_probe']) && is_array($device['last_probe'])): ?>
                                <?php foreach ($device['last_probe'] as $probe): ?>
                                  <div class="small"><span class="badge <?= e(status_badge_class($probe['status'])) ?>"><?= e($probe['label']) ?> <?= e(strtoupper($probe['status'])) ?></span></div>
                                <?php endforeach; ?>
                              <?php else: ?>
                                <span class="small muted">Belum pernah diprobe</span>
                              <?php endif; ?>
                            </td>
                            <td>
                              <div class="d-flex gap-2 flex-wrap">
                                <a class="btn btn-sm btn-outline-info" href="?tab=devices&edit=<?= urlencode($device['id']) ?>"><i class="bi bi-pencil"></i></a>
                                <form method="post" class="d-inline"><input type="hidden" name="action" value="probe_device"><input type="hidden" name="id" value="<?= e($device['id']) ?>"><button class="btn btn-sm btn-outline-light"><i class="bi bi-activity"></i></button></form>
                                <form method="post" class="d-inline" onsubmit="return confirm('Hapus device ini?')"><input type="hidden" name="action" value="delete_device"><input type="hidden" name="id" value="<?= e($device['id']) ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                              </div>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </div></div>
              </div>
            </div>
            <div class="card shell-card border-0 rounded-4 mt-3"><div class="card-body">
              <div class="kicker mb-2">Probe logs</div>
              <div class="table-responsive">
                <table class="table table-hover align-middle">
                  <thead><tr><th>Waktu</th><th>Device</th><th>Check</th><th>Hasil</th></tr></thead>
                  <tbody>
                    <?php foreach (array_slice($probeLogs, 0, 20) as $log): ?>
                      <tr>
                        <td class="small mono"><?= e($log['checked_at']) ?></td>
                        <td><?= e($log['device_name']) ?><div class="small muted"><?= e($log['host']) ?></div></td>
                        <td><?= e($log['label']) ?> : <?= e($log['port']) ?></td>
                        <td><span class="badge <?= e(status_badge_class($log['status'])) ?>"><?= e(strtoupper($log['status'])) ?></span><div class="small muted mt-1"><?= e($log['message']) ?></div></td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if (!$probeLogs): ?><tr><td colspan="4" class="muted">Belum ada probe log.</td></tr><?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div></div>

          <?php elseif ($tab === 'bgp'): ?>
            <div class="row g-3">
              <div class="col-12 col-xl-4">
                <div class="card shell-card border-0 rounded-4"><div class="card-body">
                  <div class="kicker mb-2">BGP template</div>
                  <form method="post" class="vstack gap-3">
                    <input type="hidden" name="action" value="save_bgp_template">
                    <div><label class="form-label">Device</label><select name="device_name" class="form-select"><?php foreach ($devices as $device): ?><option value="<?= e($device['name']) ?>"><?= e($device['name']) ?></option><?php endforeach; ?></select></div>
                    <div><label class="form-label">Peer name</label><input name="peer_name" class="form-control" required></div>
                    <div><label class="form-label">State</label><input name="state" class="form-control" value="Template"></div>
                    <div class="row g-2"><div class="col"><label class="form-label">Prefix In</label><input name="prefix_in" class="form-control" value="—"></div><div class="col"><label class="form-label">Prefix Out</label><input name="prefix_out" class="form-control" value="—"></div></div>
                    <div><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3"></textarea></div>
                    <button class="btn btn-info">Simpan Template BGP</button>
                  </form>
                </div></div>
              </div>
              <div class="col-12 col-xl-8">
                <div class="card shell-card border-0 rounded-4"><div class="card-body">
                  <div class="kicker mb-2">BGP panel</div>
                  <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Peer</th><th>Device</th><th>Status</th><th>Prefix</th><th>Notes</th></tr></thead><tbody>
                    <?php foreach ($bgpPeers as $peer): ?>
                      <tr>
                        <td class="fw-semibold"><?= e($peer['peer_name']) ?></td>
                        <td><?= e($peer['device_name']) ?></td>
                        <td><span class="badge <?= e(status_badge_class($peer['state'])) ?>"><?= e($peer['state']) ?></span></td>
                        <td>In: <?= e($peer['prefix_in']) ?><br>Out: <?= e($peer['prefix_out']) ?></td>
                        <td class="small muted"><?= e($peer['notes']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody></table></div>
                </div></div>
              </div>
            </div>

          <?php elseif ($tab === 'routes'): ?>
            <div class="row g-3">
              <div class="col-12 col-xl-4">
                <div class="card shell-card border-0 rounded-4"><div class="card-body">
                  <div class="kicker mb-2">Route query</div>
                  <form method="post" class="vstack gap-3">
                    <input type="hidden" name="action" value="save_route_query">
                    <div><label class="form-label">Query IP / Prefix</label><input name="query" class="form-control" placeholder="8.8.8.8 atau 103.196.85.0/24" required></div>
                    <div><label class="form-label">Target device</label><select name="device_name" class="form-select"><?php foreach ($devices as $device): ?><option value="<?= e($device['name']) ?>"><?= e($device['name']) ?></option><?php endforeach; ?></select></div>
                    <button class="btn btn-info">Simpan Route Query</button>
                  </form>
                </div></div>
              </div>
              <div class="col-12 col-xl-8">
                <div class="card shell-card border-0 rounded-4"><div class="card-body">
                  <div class="kicker mb-2">Route explorer log</div>
                  <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Waktu</th><th>Device</th><th>Query</th><th>Hasil</th></tr></thead><tbody>
                    <?php foreach ($routeQueries as $route): ?>
                      <tr>
                        <td class="small mono"><?= e($route['created_at']) ?></td>
                        <td><?= e($route['device_name']) ?></td>
                        <td><code><?= e($route['query']) ?></code></td>
                        <td class="small muted"><?= e($route['result']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if (!$routeQueries): ?><tr><td colspan="4" class="muted">Belum ada query route.</td></tr><?php endif; ?>
                  </tbody></table></div>
                </div></div>
              </div>
            </div>

          <?php elseif ($tab === 'backups'): ?>
            <div class="row g-3">
              <div class="col-12 col-xl-4">
                <div class="card shell-card border-0 rounded-4"><div class="card-body">
                  <div class="kicker mb-2">Queue backup</div>
                  <form method="post" class="vstack gap-3">
                    <input type="hidden" name="action" value="queue_backup">
                    <div><label class="form-label">Device</label><select name="device_name" class="form-select"><?php foreach ($devices as $device): ?><option value="<?= e($device['name']) ?>"><?= e($device['name']) ?></option><?php endforeach; ?></select></div>
                    <div><label class="form-label">Backup Type</label>
                      <select name="backup_type" class="form-select">
                        <option value="mikrotik-rsc">MikroTik .rsc</option>
                        <option value="mikrotik-backup">MikroTik binary backup</option>
                        <option value="olt-export">OLT export / dump</option>
                        <option value="config-text">Generic config text</option>
                      </select>
                    </div>
                    <button class="btn btn-info">Tambah Queue Backup</button>
                  </form>
                </div></div>
              </div>
              <div class="col-12 col-xl-8">
                <div class="card shell-card border-0 rounded-4"><div class="card-body">
                  <div class="kicker mb-2">Backup center</div>
                  <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Waktu</th><th>Device</th><th>Tipe</th><th>Status</th><th>Catatan</th></tr></thead><tbody>
                    <?php foreach ($backupJobs as $job): ?>
                      <tr>
                        <td class="small mono"><?= e($job['created_at']) ?></td>
                        <td><?= e($job['device_name']) ?></td>
                        <td><?= e($job['backup_type']) ?></td>
                        <td><span class="badge <?= e(status_badge_class($job['status'])) ?>"><?= e(strtoupper($job['status'])) ?></span></td>
                        <td class="small muted"><?= e($job['message']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody></table></div>
                </div></div>
              </div>
            </div>

          <?php elseif ($tab === 'settings'): ?>
            <div class="row g-3">
              <div class="col-12 col-xl-6">
                <div class="card shell-card border-0 rounded-4"><div class="card-body">
                  <div class="kicker mb-2">Security</div>
                  <form method="post" class="vstack gap-3">
                    <input type="hidden" name="action" value="change_password">
                    <div><label class="form-label">Password lama</label><input type="password" name="old_password" class="form-control" required></div>
                    <div><label class="form-label">Password baru</label><input type="password" name="new_password" class="form-control" required></div>
                    <div><label class="form-label">Konfirmasi password baru</label><input type="password" name="confirm_password" class="form-control" required></div>
                    <button class="btn btn-info">Ubah Password</button>
                  </form>
                </div></div>
              </div>
              <div class="col-12 col-xl-6">
                <div class="card shell-card border-0 rounded-4"><div class="card-body">
                  <div class="kicker mb-2">Storage & app</div>
                  <div class="small muted mb-3">Data tersimpan di file JSON hosting: <code><?= e(APP_STORAGE) ?></code></div>
                  <div class="d-flex gap-2 flex-wrap">
                    <form method="post" onsubmit="return confirm('Reset semua data ke seed awal?')"><input type="hidden" name="action" value="reset_seed"><button class="btn btn-outline-warning">Reset Seed Data</button></form>
                    <a href="./docs/blueprint-noc-dashboard.md" class="btn btn-outline-light" target="_blank">Lihat Blueprint</a>
                  </div>
                </div></div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>
</body>
</html>
