<?php
session_start();

define('APP_STORAGE', __DIR__ . '/data/app-storage.json');
define('DEFAULT_USERNAME', 'admin');
define('DEFAULT_PASSWORD', '260200');
define('APP_NAME', 'NOC ISP Tools');

date_default_timezone_set('Asia/Jakarta');
if (file_exists(__DIR__ . '/lib/routeros_api.class.php')) { require_once __DIR__ . '/lib/routeros_api.class.php'; }

function now_iso() { return date('c'); }
function now_text() { return date('d M Y H:i:s'); }
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function post($key, $default = '') { return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default; }
function getv($arr, $key, $default = '') { return (is_array($arr) && isset($arr[$key])) ? $arr[$key] : $default; }
function uid($prefix) { return uniqid($prefix . '_', true); }
function flash($type, $message) { $_SESSION['flash'] = ['type' => $type, 'message' => $message]; }
function get_flash() { if (!isset($_SESSION['flash'])) return null; $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f; }
function is_logged_in() { return !empty($_SESSION['noc_admin']); }
function require_login() { if (!is_logged_in()) { header('Location: ./'); exit; } }
function redirect_to($tab, $extra = []) { $params = array_merge(['tab' => $tab], $extra); header('Location: ./?' . http_build_query($params)); exit; }
function status_badge_class($status) {
    $status = strtolower((string)$status);
    if (in_array($status, ['up','open','success','established'], true)) return 'text-bg-success';
    if (in_array($status, ['warning','queued','template','active'], true)) return 'text-bg-warning';
    return 'text-bg-danger';
}
function status_text($status) { return strtoupper((string)$status); }

function seed_data() {
    return [
        'auth' => [
            'username' => DEFAULT_USERNAME,
            'password_hash' => password_hash(DEFAULT_PASSWORD, PASSWORD_DEFAULT),
            'updated_at' => now_iso(),
        ],
        'meta' => [
            'app_name' => APP_NAME,
            'version' => '3.0.0-professional-hosting',
            'updated_at' => now_iso(),
            'stack' => 'LiteSpeed + PHP 7.2 + JSON Storage',
            'focus' => 'MikroTik + ZTE C320/C300',
        ],
        'devices' => [
            [
                'id' => uid('dev'),
                'name' => 'MKR-BORDER-01',
                'vendor' => 'MikroTik',
                'device_type' => 'Router',
                'model' => 'CCR / RouterOS',
                'site' => 'Core',
                'host' => '103.196.85.103',
                'connection_mode' => 'API+SNMP',
                'api_port' => '29031',
                'api_ssl_port' => '29032',
                'snmp_port' => '161',
                'snmp_version' => '2c',
                'api_username' => 'Robot',
                'api_password' => '',
                'api_timeout' => '5',
                'status' => 'up',
                'role' => 'Border / BGP',
                'tags' => 'mikrotik,bgp,core,api,snmp',
                'notes' => 'Fokus untuk RouterOS API, BGP summary, route lookup, dan backup .rsc.',
                'features' => [
                    'RouterOS API profile', 'BGP peer summary', 'Route finder shell', 'Backup .rsc queue', 'API/SNMP reachability probe'
                ],
                'last_probe' => [],
                'created_at' => now_iso(),
                'updated_at' => now_iso(),
            ],
            [
                'id' => uid('dev'),
                'name' => 'OLT-ZTE-C320-01',
                'vendor' => 'ZTE',
                'device_type' => 'OLT',
                'model' => 'C320/C300',
                'site' => 'POP Donomulyo',
                'host' => '103.196.85.37',
                'connection_mode' => 'SNMP/CLI',
                'api_port' => '',
                'api_ssl_port' => '',
                'snmp_port' => '1500',
                'snmp_version' => '2c',
                'api_username' => '',
                'api_password' => '',
                'api_timeout' => '5',
                'status' => 'warning',
                'role' => 'GPON / OLT',
                'tags' => 'zte,c320,c300,olt,snmp,gpon',
                'notes' => 'Fokus untuk monitoring ONU/OLT, optics, provisioning helper, dan queue export/dump konfigurasi.',
                'features' => [
                    'SNMP reachability', 'OLT capability profile', 'ONU/optical placeholder modules', 'Backup/export queue', 'ZTE-specific notes panel'
                ],
                'last_probe' => [],
                'created_at' => now_iso(),
                'updated_at' => now_iso(),
            ],
        ],
        'probe_logs' => [],
        'backup_jobs' => [
            [
                'id' => uid('bkp'),
                'device_name' => 'MKR-BORDER-01',
                'backup_type' => 'mikrotik-rsc',
                'status' => 'queued',
                'message' => 'Queue awal untuk export .rsc / binary backup saat integrasi real dipasang.',
                'created_at' => now_iso(),
            ]
        ],
        'bgp_peers' => [
            [
                'id' => uid('bgp'),
                'device_name' => 'MKR-BORDER-01',
                'peer_name' => 'ID-IX-A',
                'peer_address' => '103.1.2.1',
                'remote_asn' => '65001',
                'state' => 'Template',
                'prefix_in' => '—',
                'prefix_out' => '—',
                'notes' => 'Panel siap untuk integrasi real BGP summary dari MikroTik.',
            ]
        ],
        'route_queries' => [],
        'tool_logs' => [],
        'toolkits' => [
            ['name' => 'Port Reachability Probe', 'scope' => 'API / API SSL / SNMP', 'status' => 'ready'],
            ['name' => 'Device Detail Page', 'scope' => 'MikroTik + ZTE OLT', 'status' => 'ready'],
            ['name' => 'Backup Queue', 'scope' => '.rsc / dump / generic config', 'status' => 'ready'],
            ['name' => 'BGP Panel', 'scope' => 'MikroTik focused', 'status' => 'ready'],
            ['name' => 'Route Query Log', 'scope' => 'best-match workflow shell', 'status' => 'ready'],
            ['name' => 'Capability Matrix', 'scope' => 'hosting-native operational model', 'status' => 'ready'],
            ['name' => 'Credential-ready Device Registry', 'scope' => 'API/SNMP field structure', 'status' => 'ready'],
        ],
    ];
}

function app_bootstrap() {
    if (!is_dir(__DIR__ . '/data')) @mkdir(__DIR__ . '/data', 0775, true);
    if (!file_exists(APP_STORAGE)) {
        file_put_contents(APP_STORAGE, json_encode(seed_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

function app_load() {
    app_bootstrap();
    $raw = @file_get_contents(APP_STORAGE);
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = [];
    $seed = seed_data();
    if (!isset($data['auth']) || !isset($data['auth']['password_hash']) || !isset($data['auth']['username'])) {
        $data = $seed;
        file_put_contents(APP_STORAGE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $data;
    }
    foreach ($seed as $key => $value) {
        if (!isset($data[$key])) $data[$key] = $value;
    }
    if (empty($data['devices']) || !is_array($data['devices'])) $data['devices'] = $seed['devices'];
    if (empty($data['toolkits']) || !is_array($data['toolkits'])) $data['toolkits'] = $seed['toolkits'];
    return $data;
}

function app_save($data) {
    $data['meta']['updated_at'] = now_iso();
    file_put_contents(APP_STORAGE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function run_port_probe($host, $port, $label) {
    $result = [
        'label' => $label,
        'host' => $host,
        'port' => $port,
        'status' => 'closed',
        'message' => 'Port tidak dapat dijangkau dari hosting',
        'checked_at' => now_iso(),
    ];
    if ($host === '' || $port === '') {
        $result['status'] = 'warning';
        $result['message'] = 'Host/port kosong';
        return $result;
    }
    $errno = 0; $errstr = '';
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


function normalize_ros_rows($rows, $limit = 80) {
    if (!is_array($rows)) return [];
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $clean = [];
        foreach ($row as $k => $v) {
            if (is_scalar($v) || $v === null) $clean[(string)$k] = (string)$v;
        }
        $out[] = $clean;
        if (count($out) >= $limit) break;
    }
    return $out;
}

function mikrotik_api_call($device, $command, $args = []) {
    if (!class_exists('RouterosAPI')) {
        return ['ok' => false, 'message' => 'RouterOS API library tidak tersedia di hosting.', 'rows' => []];
    }
    $host = getv($device, 'host');
    $port = getv($device, 'api_port');
    $user = getv($device, 'api_username');
    $pass = getv($device, 'api_password');
    $timeout = (int)getv($device, 'api_timeout', '5');
    if ($host === '' || $port === '' || $user === '') {
        return ['ok' => false, 'message' => 'Host/API port/API username belum lengkap.', 'rows' => []];
    }
    if ($pass === '') {
        return ['ok' => false, 'message' => 'API password belum diisi pada device. Isi dulu di form device.', 'rows' => []];
    }
    $api = new RouterosAPI();
    $api->debug = false;
    $api->port = (int)$port;
    $api->timeout = $timeout > 0 ? $timeout : 5;
    $api->attempts = 1;
    $api->delay = 1;
    try {
        if (!$api->connect($host, $user, $pass)) {
            return ['ok' => false, 'message' => 'Gagal konek ke MikroTik API. Cek host, port, user, password, dan firewall/allowed address.', 'rows' => []];
        }
        $rows = $api->comm($command, $args);
        $api->disconnect();
        return ['ok' => true, 'message' => 'Command berhasil: ' . $command, 'rows' => normalize_ros_rows($rows, 120)];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Exception: ' . $e->getMessage(), 'rows' => []];
    } catch (Exception $e) {
        return ['ok' => false, 'message' => 'Exception: ' . $e->getMessage(), 'rows' => []];
    }
}

function find_device_by_name($data, $name) {
    foreach (getv($data, 'devices', []) as $device) {
        if (getv($device, 'name') === $name) return $device;
    }
    return null;
}

function tool_log_push(&$data, $entry) {
    if (!isset($data['tool_logs']) || !is_array($data['tool_logs'])) $data['tool_logs'] = [];
    array_unshift($data['tool_logs'], array_merge(['id' => uid('tool'), 'created_at' => now_iso()], $entry));
    $data['tool_logs'] = array_slice($data['tool_logs'], 0, 120);
}

$data = app_load();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'login') {
        $username = post('username');
        $password = post('password');
        if ($username === getv($data['auth'], 'username', DEFAULT_USERNAME) && password_verify($password, getv($data['auth'], 'password_hash'))) {
            $_SESSION['noc_admin'] = true;
            flash('success', 'Login berhasil.');
            redirect_to('dashboard');
        }
        flash('danger', 'Username atau password salah. Username default: admin');
        redirect_to('login');
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
        $featureText = post('features');
        $features = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $featureText))));
        $payload = [
            'id' => $id !== '' ? $id : uid('dev'),
            'name' => post('name'),
            'vendor' => post('vendor'),
            'device_type' => post('device_type'),
            'model' => post('model'),
            'site' => post('site'),
            'host' => post('host'),
            'connection_mode' => post('connection_mode'),
            'api_port' => post('api_port'),
            'api_ssl_port' => post('api_ssl_port'),
            'snmp_port' => post('snmp_port'),
            'snmp_version' => post('snmp_version'),
            'api_username' => post('api_username'),
            'api_password' => post('api_password'),
            'api_timeout' => post('api_timeout', '5'),
            'status' => post('status', 'up'),
            'role' => post('role'),
            'tags' => post('tags'),
            'notes' => post('notes'),
            'features' => $features,
            'last_probe' => [],
            'created_at' => now_iso(),
            'updated_at' => now_iso(),
        ];
        $updated = false;
        foreach ($data['devices'] as $idx => $device) {
            if ($device['id'] === $payload['id']) {
                $payload['created_at'] = getv($device, 'created_at', now_iso());
                $payload['last_probe'] = getv($device, 'last_probe', []);
                $data['devices'][$idx] = $payload;
                $updated = true;
                break;
            }
        }
        if (!$updated) array_unshift($data['devices'], $payload);
        app_save($data);
        flash('success', 'Device berhasil disimpan.');
        redirect_to('devices');
    }

    if ($action === 'delete_device') {
        $id = post('id');
        $deletedName = '';
        $data['devices'] = array_values(array_filter($data['devices'], function ($device) use ($id, &$deletedName) {
            if (getv($device, 'id') === $id) { $deletedName = getv($device, 'name'); return false; }
            return true;
        }));
        if ($deletedName !== '') {
            $data['bgp_peers'] = array_values(array_filter($data['bgp_peers'], function ($peer) use ($deletedName) { return getv($peer, 'device_name') !== $deletedName; }));
            $data['backup_jobs'] = array_values(array_filter($data['backup_jobs'], function ($job) use ($deletedName) { return getv($job, 'device_name') !== $deletedName; }));
        }
        app_save($data);
        flash('success', 'Device berhasil dihapus.');
        redirect_to('devices');
    }

    if ($action === 'probe_device') {
        $id = post('id');
        foreach ($data['devices'] as $idx => $device) {
            if (getv($device, 'id') === $id) {
                $probeSet = [];
                if (getv($device, 'api_port') !== '') $probeSet[] = run_port_probe(getv($device, 'host'), getv($device, 'api_port'), 'API');
                if (getv($device, 'api_ssl_port') !== '') $probeSet[] = run_port_probe(getv($device, 'host'), getv($device, 'api_ssl_port'), 'API SSL');
                if (getv($device, 'snmp_port') !== '') $probeSet[] = run_port_probe(getv($device, 'host'), getv($device, 'snmp_port'), 'SNMP');
                if (!$probeSet) $probeSet[] = ['label' => 'GENERAL', 'host' => getv($device, 'host'), 'port' => '', 'status' => 'warning', 'message' => 'Tidak ada port terisi', 'checked_at' => now_iso()];
                $device['last_probe'] = $probeSet;
                $device['updated_at'] = now_iso();
                $data['devices'][$idx] = $device;
                foreach ($probeSet as $probe) {
                    array_unshift($data['probe_logs'], [
                        'id' => uid('probe'),
                        'device_name' => getv($device, 'name'),
                        'host' => getv($device, 'host'),
                        'vendor' => getv($device, 'vendor'),
                        'label' => getv($probe, 'label'),
                        'port' => getv($probe, 'port'),
                        'status' => getv($probe, 'status'),
                        'message' => getv($probe, 'message'),
                        'checked_at' => getv($probe, 'checked_at'),
                    ]);
                }
                $data['probe_logs'] = array_slice($data['probe_logs'], 0, 120);
                app_save($data);
                flash('success', 'Probe dijalankan dari hosting untuk API/SNMP device.');
                redirect_to('devices', ['detail' => $id]);
            }
        }
        flash('danger', 'Device untuk probe tidak ditemukan.');
        redirect_to('devices');
    }

    if ($action === 'queue_backup') {
        array_unshift($data['backup_jobs'], [
            'id' => uid('bkp'),
            'device_name' => post('device_name'),
            'backup_type' => post('backup_type'),
            'status' => 'queued',
            'message' => post('message', 'Queue backup tersimpan. Siap disambung ke executor hosting-native sesuai device/protokol yang memungkinkan.'),
            'created_at' => now_iso(),
        ]);
        $data['backup_jobs'] = array_slice($data['backup_jobs'], 0, 120);
        app_save($data);
        flash('success', 'Backup job berhasil ditambahkan.');
        redirect_to('backups');
    }

    if ($action === 'save_bgp_template') {
        array_unshift($data['bgp_peers'], [
            'id' => uid('bgp'),
            'device_name' => post('device_name'),
            'peer_name' => post('peer_name'),
            'peer_address' => post('peer_address'),
            'remote_asn' => post('remote_asn'),
            'state' => post('state', 'Template'),
            'prefix_in' => post('prefix_in', '—'),
            'prefix_out' => post('prefix_out', '—'),
            'notes' => post('notes'),
        ]);
        $data['bgp_peers'] = array_slice($data['bgp_peers'], 0, 120);
        app_save($data);
        flash('success', 'Template BGP berhasil disimpan.');
        redirect_to('bgp');
    }

    if ($action === 'save_route_query') {
        $deviceName = post('device_name');
        $query = post('query');
        $deviceType = '';
        foreach ($data['devices'] as $device) if (getv($device, 'name') === $deviceName) $deviceType = getv($device, 'vendor') . ' ' . getv($device, 'device_type');
        $result = 'Best-match route workflow siap. Tahap live ini menyimpan query di hosting agar operator punya jejak kerja, lalu bisa disambungkan ke API/CLI device target pada fase integrasi berikutnya.';
        array_unshift($data['route_queries'], [
            'id' => uid('route'),
            'device_name' => $deviceName,
            'device_type' => $deviceType,
            'query' => $query,
            'result' => $result,
            'created_at' => now_iso(),
        ]);
        $data['route_queries'] = array_slice($data['route_queries'], 0, 120);
        app_save($data);
        flash('success', 'Route query berhasil dicatat.');
        redirect_to('routes');
    }


    if ($action === 'mikrotik_tool') {
        $deviceName = post('device_name');
        $tool = post('tool');
        $device = find_device_by_name($data, $deviceName);
        if (!$device) { flash('danger', 'Device tidak ditemukan.'); redirect_to('tools'); }
        if (strtolower(getv($device, 'vendor')) !== 'mikrotik') { flash('danger', 'Tool ini fokus untuk MikroTik. Pilih device MikroTik.'); redirect_to('tools'); }
        $command = '';
        $args = [];
        $label = '';
        if ($tool === 'ping') {
            $address = post('address');
            if ($address === '') { flash('danger', 'Alamat ping wajib diisi.'); redirect_to('tools'); }
            $command = '/ping';
            $args = ['address' => $address, 'count' => post('count', '4')];
            $label = 'Ping ' . $address;
        } elseif ($tool === 'interfaces') {
            $command = '/interface/print';
            $args = [];
            $label = 'Interface List';
        } elseif ($tool === 'ppp_secrets') {
            $command = '/ppp/secret/print';
            $args = [];
            $label = 'PPP Secret List';
        } elseif ($tool === 'ip_addresses') {
            $command = '/ip/address/print';
            $args = [];
            $label = 'IP Address List';
        } elseif ($tool === 'routes') {
            $command = '/ip/route/print';
            $args = [];
            $label = 'IP Route List';
        } else {
            flash('danger', 'Tool tidak dikenal.'); redirect_to('tools');
        }
        $result = mikrotik_api_call($device, $command, $args);
        tool_log_push($data, [
            'device_name' => $deviceName,
            'tool' => $tool,
            'label' => $label,
            'status' => $result['ok'] ? 'success' : 'failed',
            'message' => $result['message'],
            'rows' => $result['rows'],
        ]);
        app_save($data);
        flash($result['ok'] ? 'success' : 'danger', $result['message']);
        redirect_to('tools');
    }

    if ($action === 'change_password') {
        $old = post('old_password');
        $new = post('new_password');
        $confirm = post('confirm_password');
        if (!password_verify($old, getv($data['auth'], 'password_hash'))) { flash('danger', 'Password lama salah.'); redirect_to('settings'); }
        if ($new === '' || strlen($new) < 4) { flash('danger', 'Password baru minimal 4 karakter.'); redirect_to('settings'); }
        if ($new !== $confirm) { flash('danger', 'Konfirmasi password tidak sama.'); redirect_to('settings'); }
        $data['auth']['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
        $data['auth']['updated_at'] = now_iso();
        app_save($data);
        flash('success', 'Password admin berhasil diubah.');
        redirect_to('settings');
    }

    if ($action === 'reset_seed') {
        @unlink(APP_STORAGE);
        app_bootstrap();
        flash('success', 'App berhasil direset ke versi seed profesional. Username tetap admin, password 260200.');
        redirect_to('dashboard');
    }
}


$data = app_load();
$flash = get_flash();
$tab = isset($_GET['tab']) ? (string)$_GET['tab'] : (is_logged_in() ? 'dashboard' : 'login');
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$detailId = isset($_GET['detail']) ? (string)$_GET['detail'] : '';
$editId = isset($_GET['edit']) ? (string)$_GET['edit'] : '';

$devices = getv($data, 'devices', []);
$bgpPeers = getv($data, 'bgp_peers', []);
$backupJobs = getv($data, 'backup_jobs', []);
$routeQueries = getv($data, 'route_queries', []);
$probeLogs = getv($data, 'probe_logs', []);
$toolkits = getv($data, 'toolkits', []);
$toolLogs = getv($data, 'tool_logs', []);

if ($q !== '') {
    $devices = array_values(array_filter($devices, function ($d) use ($q) {
        $hay = strtolower(json_encode($d));
        return strpos($hay, strtolower($q)) !== false;
    }));
}

$detailDevice = null; $editDevice = null;
foreach (getv($data, 'devices', []) as $device) {
    if (getv($device, 'id') === $detailId) $detailDevice = $device;
    if (getv($device, 'id') === $editId) $editDevice = $device;
}

$totalDevices = count(getv($data, 'devices', []));
$mikrotikCount = count(array_filter(getv($data, 'devices', []), function($d){ return strtolower(getv($d,'vendor')) === 'mikrotik'; }));
$zteCount = count(array_filter(getv($data, 'devices', []), function($d){ return strtolower(getv($d,'vendor')) === 'zte'; }));
$upCount = count(array_filter(getv($data, 'devices', []), function($d){ return strtolower(getv($d,'status')) === 'up'; }));
$warningCount = count(array_filter(getv($data, 'devices', []), function($d){ return strtolower(getv($d,'status')) === 'warning'; }));
$downCount = count(array_filter(getv($data, 'devices', []), function($d){ return strtolower(getv($d,'status')) === 'down'; }));
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(APP_NAME) ?> — Professional Hosting Suite</title>
  <meta name="theme-color" content="#07111c">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root{--bg:#07111c;--bg2:#0b1525;--panel:rgba(15,23,42,.94);--panel2:rgba(11,18,32,.95);--border:rgba(148,163,184,.14);--muted:#94a3b8;--accent:#38bdf8;--accent2:#60a5fa}
    body{background:radial-gradient(circle at top left, rgba(56,189,248,.14), transparent 18%),radial-gradient(circle at bottom right, rgba(96,165,250,.16), transparent 22%),linear-gradient(180deg,var(--bg) 0%,var(--bg2) 100%);color:#e5eefc;min-height:100vh}
    .sidebar{background:rgba(4,8,16,.88);backdrop-filter:blur(14px);border-right:1px solid var(--border);min-height:100vh;padding:24px 18px}
    .app-card,.metric-card,.hero-card{background:linear-gradient(180deg, rgba(15,23,42,.96), rgba(10,16,28,.98));border:1px solid var(--border);box-shadow:0 20px 48px rgba(0,0,0,.24)}
    .metric-card{border-radius:22px;padding:18px;height:100%}.metric-value{font-size:2rem;font-weight:800;line-height:1.05}.muted{color:var(--muted)}
    .nav-pills .nav-link{color:#cbd5e1;background:rgba(255,255,255,.03);border-radius:14px;text-align:left;padding:12px 14px}.nav-pills .nav-link.active,.nav-pills .nav-link:hover{background:rgba(56,189,248,.14);color:#fff}
    .brand-box{width:56px;height:56px;border-radius:18px;display:grid;place-items:center;background:linear-gradient(135deg,#0ea5e9,#2563eb);font-size:1.35rem;box-shadow:0 18px 36px rgba(37,99,235,.34)}
    .kicker{font-size:.72rem;letter-spacing:.18em;text-transform:uppercase;color:#7dd3fc;font-weight:700}.table{--bs-table-bg:transparent;--bs-table-color:#e5eefc;--bs-table-border-color:rgba(255,255,255,.06)}
    .table td,.table th{vertical-align:middle}.badge-soft{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08)}
    .mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace}.sticky-topbar{position:sticky;top:0;z-index:5;background:rgba(7,17,28,.72);backdrop-filter:blur(12px);border-bottom:1px solid var(--border)}
    code{color:#93c5fd}.feature-chip{display:inline-flex;padding:7px 10px;border-radius:999px;background:rgba(56,189,248,.1);border:1px solid rgba(56,189,248,.14);font-size:.76rem;color:#dbefff}
    .hero-card{border-radius:28px;padding:24px}.login-wrap{max-width:560px}.device-card{border-radius:22px}.list-clean{padding-left:18px}.section-gap{margin-top:1.15rem}
    .toolkit-item{border:1px solid rgba(255,255,255,.08);border-radius:18px;background:rgba(255,255,255,.03);padding:14px 16px;height:100%}
    .small-code{font-size:.8rem}.nowrap{white-space:nowrap}.detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    @media (max-width: 991px){.sidebar{min-height:auto}.detail-grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
<?php if (!is_logged_in()): ?>
  <div class="container py-5">
    <div class="row justify-content-center align-items-center min-vh-100">
      <div class="col-12 login-wrap">
        <div class="card hero-card border-0">
          <div class="row g-4 align-items-center">
            <div class="col-12 col-lg-7">
              <div class="d-flex align-items-center gap-3 mb-4">
                <div class="brand-box"><i class="bi bi-broadcast-pin"></i></div>
                <div>
                  <div class="kicker">Professional Hosting Suite</div>
                  <h1 class="h3 mb-0"><?= e(APP_NAME) ?></h1>
                </div>
              </div>
              <p class="muted mb-3">Rombakan fokus NOC profesional berbasis hosting untuk <strong>MikroTik</strong> dan <strong>ZTE C320/C300</strong>. Tanpa server tambahan, berjalan di LiteSpeed + PHP + JSON storage.</p>
              <div class="d-flex gap-2 flex-wrap mb-3">
                <span class="feature-chip">Device Registry</span>
                <span class="feature-chip">Detail Page</span>
                <span class="feature-chip">API/SNMP Probe</span>
                <span class="feature-chip">BGP Panel</span>
                <span class="feature-chip">Route Workflow</span>
                <span class="feature-chip">Backup Queue</span>
                <span class="feature-chip">Settings</span>
              </div>
              <div class="small muted">Username default: <strong class="text-white">admin</strong> • Password default: <strong class="text-warning">260200</strong></div>
            </div>
            <div class="col-12 col-lg-5">
              <?php if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?> py-2"><?= e($flash['message']) ?></div><?php endif; ?>
              <form method="post" class="vstack gap-3">
                <input type="hidden" name="action" value="login">
                <div><label class="form-label">Username</label><input name="username" class="form-control form-control-lg" value="admin" required></div>
                <div><label class="form-label">Password</label><input type="password" name="password" class="form-control form-control-lg" required></div>
                <button class="btn btn-info btn-lg fw-semibold"><i class="bi bi-shield-lock me-2"></i>Masuk ke NOC</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="container-fluid px-0">
    <div class="row g-0">
      <div class="col-12 col-lg-3 col-xl-2 sidebar">
        <div>
          <div class="d-flex align-items-center gap-3 mb-4">
            <div class="brand-box"><i class="bi bi-router-fill"></i></div>
            <div>
              <div class="kicker">NOC Professional</div>
              <div class="fw-bold"><?= e(APP_NAME) ?></div>
            </div>
          </div>
          <div class="nav nav-pills flex-column gap-2">
            <a class="nav-link <?= $tab==='dashboard'?'active':'' ?>" href="?tab=dashboard"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
            <a class="nav-link <?= $tab==='devices'?'active':'' ?>" href="?tab=devices"><i class="bi bi-hdd-network me-2"></i>Devices</a>
            <a class="nav-link <?= $tab==='tools'?'active':'' ?>" href="?tab=tools"><i class="bi bi-terminal me-2"></i>MikroTik Tools</a>
            <a class="nav-link <?= $tab==='bgp'?'active':'' ?>" href="?tab=bgp"><i class="bi bi-diagram-3 me-2"></i>BGP</a>
            <a class="nav-link <?= $tab==='routes'?'active':'' ?>" href="?tab=routes"><i class="bi bi-signpost-split me-2"></i>Routes</a>
            <a class="nav-link <?= $tab==='backups'?'active':'' ?>" href="?tab=backups"><i class="bi bi-cloud-arrow-down me-2"></i>Backups</a>
            <a class="nav-link <?= $tab==='settings'?'active':'' ?>" href="?tab=settings"><i class="bi bi-gear me-2"></i>Settings</a>
          </div>
        </div>
        <div class="mt-4 pt-3 border-top border-secondary-subtle small muted">
          <div>Web Server: <strong>LiteSpeed</strong></div>
          <div>Runtime: <strong>PHP 7.2</strong></div>
          <div>Mode: <strong>Hosting Native</strong></div>
          <div>Storage: <strong>JSON persistent</strong></div>
        </div>
      </div>
      <div class="col-12 col-lg-9 col-xl-10">
        <div class="sticky-topbar px-3 px-lg-4 py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
          <div>
            <div class="kicker">Network Operation Control</div>
            <h2 class="h4 mb-1"><?= e(ucfirst($tab)) ?></h2>
            <div class="muted small">Fokus perangkat: MikroTik & ZTE C320/C300 • Pure hosting tanpa server tambahan</div>
          </div>
          <div class="d-flex gap-2 flex-wrap align-items-center">
            <span class="badge badge-soft rounded-pill">Version <?= e(getv($data['meta'], 'version')) ?></span>
            <span class="badge text-bg-success rounded-pill">Ready</span>
            <form method="post" class="m-0"><input type="hidden" name="action" value="logout"><button class="btn btn-sm btn-danger"><i class="bi bi-box-arrow-right me-2"></i>Logout</button></form>
          </div>
        </div>
        <div class="p-3 p-lg-4">
          <?php if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?> py-2 mb-3"><?= e($flash['message']) ?></div><?php endif; ?>

          <?php if ($tab === 'dashboard'): ?>
            <div class="card hero-card border-0 mb-4">
              <div class="row g-4 align-items-center">
                <div class="col-12 col-xl-8">
                  <div class="kicker mb-2">Executive overview</div>
                  <h3 class="h2 mb-3">Suite NOC hosting-native untuk operasional ISP kecil/menengah.</h3>
                  <p class="muted mb-3">Versi ini dirapikan agar terasa seperti tool NOC profesional: punya dashboard utama, registry perangkat, halaman detail, probe API/SNMP dari hosting, BGP template panel, route workflow, queue backup, dan pengelolaan password admin.</p>
                  <div class="d-flex gap-2 flex-wrap">
                    <span class="feature-chip">MikroTik Router Focus</span>
                    <span class="feature-chip">ZTE C320/C300 Focus</span>
                    <span class="feature-chip">Device Detail</span>
                    <span class="feature-chip">Operational Probe</span>
                    <span class="feature-chip">Hosting-only Model</span>
                  </div>
                </div>
                <div class="col-12 col-xl-4">
                  <div class="app-card rounded-4 p-3 h-100">
                    <div class="small muted">Active stack</div>
                    <div class="fw-bold mb-2"><?= e(getv($data['meta'], 'stack')) ?></div>
                    <div class="small muted">Updated</div>
                    <div class="mono small-code"><?= e(getv($data['meta'], 'updated_at')) ?></div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row g-3 mb-4">
              <div class="col-6 col-xl-2"><div class="metric-card"><div class="muted small">Total</div><div class="metric-value"><?= e($totalDevices) ?></div><div class="muted small">device</div></div></div>
              <div class="col-6 col-xl-2"><div class="metric-card"><div class="muted small">MikroTik</div><div class="metric-value"><?= e($mikrotikCount) ?></div><div class="muted small">router/api</div></div></div>
              <div class="col-6 col-xl-2"><div class="metric-card"><div class="muted small">ZTE OLT</div><div class="metric-value"><?= e($zteCount) ?></div><div class="muted small">c320/c300</div></div></div>
              <div class="col-6 col-xl-2"><div class="metric-card"><div class="muted small">UP</div><div class="metric-value"><?= e($upCount) ?></div><div class="muted small">healthy</div></div></div>
              <div class="col-6 col-xl-2"><div class="metric-card"><div class="muted small">Warning</div><div class="metric-value"><?= e($warningCount) ?></div><div class="muted small">attention</div></div></div>
              <div class="col-6 col-xl-2"><div class="metric-card"><div class="muted small">Backup Queue</div><div class="metric-value"><?= e(count($backupJobs)) ?></div><div class="muted small">jobs</div></div></div>
            </div>

            <div class="row g-3">
              <div class="col-12 col-xl-7">
                <div class="app-card rounded-4 p-3 h-100">
                  <div class="d-flex justify-content-between align-items-center mb-3"><div><div class="kicker">Capability matrix</div><h3 class="h5 mb-0">Device focus sekarang</h3></div><a href="?tab=devices" class="btn btn-sm btn-outline-light">Kelola Devices</a></div>
                  <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Device</th><th>Profile</th><th>Ports</th><th>Status</th></tr></thead><tbody>
                    <?php foreach (getv($data, 'devices', []) as $device): ?>
                      <tr>
                        <td>
                          <div class="fw-semibold"><?= e(getv($device,'name')) ?></div>
                          <div class="small muted"><?= e(getv($device,'vendor')) ?> • <?= e(getv($device,'model')) ?> • <?= e(getv($device,'site')) ?></div>
                        </td>
                        <td>
                          <div class="small"><?= e(getv($device,'role')) ?></div>
                          <div class="small muted"><?= e(getv($device,'connection_mode')) ?></div>
                        </td>
                        <td class="small">API <?= e(getv($device,'api_port') ?: '-') ?><br>SSL <?= e(getv($device,'api_ssl_port') ?: '-') ?><br>SNMP <?= e(getv($device,'snmp_port') ?: '-') ?></td>
                        <td><span class="badge <?= e(status_badge_class(getv($device,'status'))) ?>"><?= e(status_text(getv($device,'status'))) ?></span></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody></table></div>
                </div>
              </div>
              <div class="col-12 col-xl-5">
                <div class="app-card rounded-4 p-3 h-100">
                  <div class="kicker mb-2">7 toolkit utama</div>
                  <div class="row g-2">
                    <?php foreach ($toolkits as $tool): ?>
                      <div class="col-12">
                        <div class="toolkit-item">
                          <div class="d-flex justify-content-between gap-2 align-items-start"><strong><?= e(getv($tool,'name')) ?></strong><span class="badge text-bg-success"><?= e(getv($tool,'status')) ?></span></div>
                          <div class="small muted mt-2"><?= e(getv($tool,'scope')) ?></div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            </div>

          <?php elseif ($tab === 'devices'): ?>
            <div class="row g-3">
              <div class="col-12 col-xl-5">
                <div class="app-card rounded-4 p-3 h-100">
                  <div class="kicker mb-2">Device composer</div>
                  <h3 class="h5 mb-3"><?= $editDevice ? 'Edit Device' : 'Tambah Device Baru' ?></h3>
                  <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="save_device">
                    <input type="hidden" name="id" value="<?= e(getv($editDevice,'id')) ?>">
                    <div class="col-md-6"><label class="form-label">Nama Device</label><input name="name" class="form-control" required value="<?= e(getv($editDevice,'name')) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Vendor</label><select name="vendor" class="form-select"><option <?= getv($editDevice,'vendor')==='MikroTik'?'selected':'' ?>>MikroTik</option><option <?= getv($editDevice,'vendor')==='ZTE'?'selected':'' ?>>ZTE</option><option <?= getv($editDevice,'vendor')==='Cisco'?'selected':'' ?>>Cisco</option><option <?= getv($editDevice,'vendor')==='Other'?'selected':'' ?>>Other</option></select></div>
                    <div class="col-md-6"><label class="form-label">Tipe</label><input name="device_type" class="form-control" value="<?= e(getv($editDevice,'device_type','Router')) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Model</label><input name="model" class="form-control" value="<?= e(getv($editDevice,'model')) ?>" placeholder="CCR / C320 / C300"></div>
                    <div class="col-md-6"><label class="form-label">Site</label><input name="site" class="form-control" value="<?= e(getv($editDevice,'site')) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Role</label><input name="role" class="form-control" value="<?= e(getv($editDevice,'role')) ?>" placeholder="Border / GPON / Core"></div>
                    <div class="col-md-6"><label class="form-label">Host/IP</label><input name="host" class="form-control" value="<?= e(getv($editDevice,'host')) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Mode Koneksi</label><input name="connection_mode" class="form-control" value="<?= e(getv($editDevice,'connection_mode','API+SNMP')) ?>"></div>
                    <div class="col-md-4"><label class="form-label">API Port</label><input name="api_port" class="form-control" value="<?= e(getv($editDevice,'api_port')) ?>"></div>
                    <div class="col-md-4"><label class="form-label">API SSL</label><input name="api_ssl_port" class="form-control" value="<?= e(getv($editDevice,'api_ssl_port')) ?>"></div>
                    <div class="col-md-4"><label class="form-label">SNMP Port</label><input name="snmp_port" class="form-control" value="<?= e(getv($editDevice,'snmp_port')) ?>"></div>
                    <div class="col-md-6"><label class="form-label">SNMP Version</label><input name="snmp_version" class="form-control" value="<?= e(getv($editDevice,'snmp_version','2c')) ?>"></div>
                    <div class="col-md-6"><label class="form-label">API Username</label><input name="api_username" class="form-control" value="<?= e(getv($editDevice,'api_username')) ?>"></div>
                    <div class="col-md-6"><label class="form-label">API Password</label><input name="api_password" type="password" class="form-control" value="<?= e(getv($editDevice,'api_password')) ?>" placeholder="isi untuk tool real"></div>
                    <div class="col-md-6"><label class="form-label">API Timeout</label><input name="api_timeout" class="form-control" value="<?= e(getv($editDevice,'api_timeout','5')) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><?php foreach (['up','warning','down'] as $opt): ?><option value="<?= e($opt) ?>" <?= getv($editDevice,'status','up')===$opt?'selected':'' ?>><?= e(status_text($opt)) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label">Tags</label><input name="tags" class="form-control" value="<?= e(getv($editDevice,'tags')) ?>"></div>
                    <div class="col-12"><label class="form-label">Fitur / kapabilitas (1 baris 1 fitur)</label><textarea name="features" rows="4" class="form-control"><?= e(implode("\n", is_array(getv($editDevice,'features',[])) ? getv($editDevice,'features',[]) : [])) ?></textarea></div>
                    <div class="col-12"><label class="form-label">Catatan</label><textarea name="notes" rows="3" class="form-control"><?= e(getv($editDevice,'notes')) ?></textarea></div>
                    <div class="col-12 d-flex gap-2 flex-wrap"><button class="btn btn-info"><i class="bi bi-save me-2"></i>Simpan Device</button><a class="btn btn-outline-light" href="?tab=devices"><i class="bi bi-arrow-counterclockwise me-2"></i>Reset</a></div>
                  </form>
                </div>
              </div>
              <div class="col-12 col-xl-7">
                <div class="app-card rounded-4 p-3">
                  <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                    <div><div class="kicker">Device registry</div><h3 class="h5 mb-0">MikroTik & ZTE focus</h3></div>
                    <form method="get" class="d-flex gap-2 flex-wrap">
                      <input type="hidden" name="tab" value="devices">
                      <input name="q" class="form-control" placeholder="Cari device, vendor, host, tag..." value="<?= e($q) ?>">
                      <button class="btn btn-outline-light">Cari</button>
                    </form>
                  </div>
                  <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Device</th><th>Capability</th><th>Probe</th><th>Aksi</th></tr></thead><tbody>
                    <?php foreach ($devices as $device): ?>
                      <tr>
                        <td>
                          <div class="fw-semibold"><?= e(getv($device,'name')) ?></div>
                          <div class="small muted"><?= e(getv($device,'vendor')) ?> • <?= e(getv($device,'model')) ?> • <?= e(getv($device,'host')) ?></div>
                          <div class="small muted"><?= e(getv($device,'role')) ?> • <?= e(getv($device,'site')) ?></div>
                        </td>
                        <td>
                          <div class="small"><?= e(getv($device,'connection_mode')) ?></div>
                          <div class="small muted">API <?= e(getv($device,'api_port') ?: '-') ?> • SSL <?= e(getv($device,'api_ssl_port') ?: '-') ?> • SNMP <?= e(getv($device,'snmp_port') ?: '-') ?></div>
                          <span class="badge <?= e(status_badge_class(getv($device,'status'))) ?> mt-1"><?= e(status_text(getv($device,'status'))) ?></span>
                        </td>
                        <td>
                          <?php if (is_array(getv($device,'last_probe',[])) && count(getv($device,'last_probe',[]))): ?>
                            <?php foreach (getv($device,'last_probe',[]) as $probe): ?>
                              <div class="small"><span class="badge <?= e(status_badge_class(getv($probe,'status'))) ?>"><?= e(getv($probe,'label')) ?> <?= e(status_text(getv($probe,'status'))) ?></span></div>
                            <?php endforeach; ?>
                          <?php else: ?>
                            <span class="small muted">Belum ada probe</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <div class="d-flex gap-2 flex-wrap">
                            <a class="btn btn-sm btn-outline-info" href="?tab=devices&detail=<?= urlencode(getv($device,'id')) ?>"><i class="bi bi-eye"></i></a>
                            <a class="btn btn-sm btn-outline-light" href="?tab=devices&edit=<?= urlencode(getv($device,'id')) ?>"><i class="bi bi-pencil"></i></a>
                            <form method="post" class="d-inline"><input type="hidden" name="action" value="probe_device"><input type="hidden" name="id" value="<?= e(getv($device,'id')) ?>"><button class="btn btn-sm btn-outline-success"><i class="bi bi-activity"></i></button></form>
                            <form method="post" class="d-inline" onsubmit="return confirm('Hapus device ini?')"><input type="hidden" name="action" value="delete_device"><input type="hidden" name="id" value="<?= e(getv($device,'id')) ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if (!$devices): ?><tr><td colspan="4" class="muted">Tidak ada device sesuai pencarian.</td></tr><?php endif; ?>
                  </tbody></table></div>
                </div>
              </div>
            </div>

            <?php if ($detailDevice): ?>
              <div class="app-card rounded-4 p-3 mt-3">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                  <div>
                    <div class="kicker">Device detail</div>
                    <h3 class="h4 mb-1"><?= e(getv($detailDevice,'name')) ?></h3>
                    <div class="muted"><?= e(getv($detailDevice,'vendor')) ?> • <?= e(getv($detailDevice,'model')) ?> • <?= e(getv($detailDevice,'device_type')) ?> • <?= e(getv($detailDevice,'host')) ?></div>
                  </div>
                  <span class="badge <?= e(status_badge_class(getv($detailDevice,'status'))) ?>"><?= e(status_text(getv($detailDevice,'status'))) ?></span>
                </div>
                <div class="detail-grid mb-3">
                  <div class="toolkit-item"><strong>Role</strong><div class="small muted mt-2"><?= e(getv($detailDevice,'role')) ?></div></div>
                  <div class="toolkit-item"><strong>Site</strong><div class="small muted mt-2"><?= e(getv($detailDevice,'site')) ?></div></div>
                  <div class="toolkit-item"><strong>Connection Profile</strong><div class="small muted mt-2"><?= e(getv($detailDevice,'connection_mode')) ?></div></div>
                  <div class="toolkit-item"><strong>Ports</strong><div class="small muted mt-2">API <?= e(getv($detailDevice,'api_port') ?: '-') ?> • SSL <?= e(getv($detailDevice,'api_ssl_port') ?: '-') ?> • SNMP <?= e(getv($detailDevice,'snmp_port') ?: '-') ?></div></div>
                </div>
                <div class="row g-3">
                  <div class="col-12 col-xl-6">
                    <div class="toolkit-item h-100"><strong>Kapabilitas / fitur</strong>
                      <ul class="list-clean mt-3 small muted mb-0">
                        <?php foreach (getv($detailDevice,'features',[]) as $feature): ?><li><?= e($feature) ?></li><?php endforeach; ?>
                      </ul>
                    </div>
                  </div>
                  <div class="col-12 col-xl-6">
                    <div class="toolkit-item h-100"><strong>Catatan operasional</strong><div class="small muted mt-3 preline"><?= e(getv($detailDevice,'notes')) ?></div></div>
                  </div>
                </div>
                <div class="section-gap">
                  <div class="kicker mb-2">Last probe results</div>
                  <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Label</th><th>Port</th><th>Status</th><th>Message</th></tr></thead><tbody>
                    <?php foreach (getv($detailDevice,'last_probe',[]) as $probe): ?>
                      <tr><td><?= e(getv($probe,'label')) ?></td><td><?= e(getv($probe,'port')) ?></td><td><span class="badge <?= e(status_badge_class(getv($probe,'status'))) ?>"><?= e(status_text(getv($probe,'status'))) ?></span></td><td class="small muted"><?= e(getv($probe,'message')) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!count(getv($detailDevice,'last_probe',[]))): ?><tr><td colspan="4" class="muted">Belum ada hasil probe. Klik tombol probe di daftar devices.</td></tr><?php endif; ?>
                  </tbody></table></div>
                </div>
              </div>
            <?php endif; ?>

            <div class="app-card rounded-4 p-3 mt-3">
              <div class="kicker mb-2">Probe logs</div>
              <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Waktu</th><th>Device</th><th>Check</th><th>Result</th></tr></thead><tbody>
                <?php foreach (array_slice($probeLogs, 0, 20) as $log): ?>
                  <tr>
                    <td class="mono small-code nowrap"><?= e(getv($log,'checked_at')) ?></td>
                    <td><?= e(getv($log,'device_name')) ?><div class="small muted"><?= e(getv($log,'host')) ?></div></td>
                    <td><?= e(getv($log,'label')) ?> : <?= e(getv($log,'port')) ?></td>
                    <td><span class="badge <?= e(status_badge_class(getv($log,'status'))) ?>"><?= e(status_text(getv($log,'status'))) ?></span><div class="small muted mt-1"><?= e(getv($log,'message')) ?></div></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$probeLogs): ?><tr><td colspan="4" class="muted">Belum ada probe log.</td></tr><?php endif; ?>
              </tbody></table></div>
            </div>


          <?php elseif ($tab === 'tools'): ?>
            <div class="row g-3">
              <div class="col-12 col-xl-4">
                <div class="app-card rounded-4 p-3 h-100">
                  <div class="kicker mb-2">MikroTik live tools</div>
                  <h3 class="h5 mb-3">Winbox-style Web Tools</h3>
                  <form method="post" class="vstack gap-3">
                    <input type="hidden" name="action" value="mikrotik_tool">
                    <div>
                      <label class="form-label">Device MikroTik</label>
                      <select name="device_name" class="form-select">
                        <?php foreach (getv($data,'devices',[]) as $device): ?>
                          <?php if (strtolower(getv($device,'vendor')) === 'mikrotik'): ?>
                            <option value="<?= e(getv($device,'name')) ?>"><?= e(getv($device,'name')) ?> — <?= e(getv($device,'host')) ?></option>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div>
                      <label class="form-label">Tool</label>
                      <select name="tool" class="form-select" id="toolSelect" onchange="document.getElementById('pingFields').style.display=this.value==='ping'?'block':'none'">
                        <option value="ping">Cek Ping</option>
                        <option value="interfaces">Cek Interface</option>
                        <option value="ppp_secrets">Cek PPP Secret</option>
                        <option value="ip_addresses">Cek IP Address</option>
                        <option value="routes">Cek IP Routes</option>
                      </select>
                    </div>
                    <div id="pingFields">
                      <label class="form-label">Target Ping</label>
                      <input name="address" class="form-control" placeholder="8.8.8.8 / gateway / IP pelanggan">
                      <label class="form-label mt-2">Count</label>
                      <input name="count" class="form-control" value="4">
                    </div>
                    <button class="btn btn-info"><i class="bi bi-play-circle me-2"></i>Jalankan Tool</button>
                  </form>
                  <div class="alert alert-info small mt-3 mb-0">Agar 100% real, isi API Password di detail device MikroTik. Hosting akan connect langsung ke RouterOS API port device.</div>
                </div>
              </div>
              <div class="col-12 col-xl-8">
                <div class="app-card rounded-4 p-3 h-100">
                  <div class="d-flex justify-content-between gap-2 flex-wrap mb-3">
                    <div><div class="kicker">Tool result log</div><h3 class="h5 mb-0">Hasil command MikroTik</h3></div>
                    <span class="badge badge-soft">Ping • Interface • PPP Secret • Routes</span>
                  </div>
                  <?php if (!$toolLogs): ?>
                    <div class="muted">Belum ada hasil tool. Jalankan tool dari panel kiri.</div>
                  <?php endif; ?>
                  <?php foreach (array_slice($toolLogs, 0, 10) as $log): ?>
                    <div class="toolkit-item mb-3">
                      <div class="d-flex justify-content-between gap-2 flex-wrap align-items-start">
                        <div>
                          <strong><?= e(getv($log,'label')) ?></strong>
                          <div class="small muted"><?= e(getv($log,'device_name')) ?> • <?= e(getv($log,'created_at')) ?></div>
                        </div>
                        <span class="badge <?= e(status_badge_class(getv($log,'status'))) ?>"><?= e(status_text(getv($log,'status'))) ?></span>
                      </div>
                      <div class="small muted mt-2"><?= e(getv($log,'message')) ?></div>
                      <?php $rows = getv($log, 'rows', []); if (is_array($rows) && count($rows)): ?>
                        <div class="table-responsive mt-3">
                          <table class="table table-sm table-hover align-middle">
                            <thead><tr>
                              <?php $first = $rows[0]; $cols = array_slice(array_keys($first), 0, 8); foreach ($cols as $col): ?><th><?= e($col) ?></th><?php endforeach; ?>
                            </tr></thead>
                            <tbody>
                              <?php foreach (array_slice($rows, 0, 30) as $row): ?><tr><?php foreach ($cols as $col): ?><td class="small"><?= e(getv($row,$col)) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
                            </tbody>
                          </table>
                        </div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

          <?php elseif ($tab === 'bgp'): ?>
            <div class="row g-3">
              <div class="col-12 col-xl-4">
                <div class="app-card rounded-4 p-3 h-100">
                  <div class="kicker mb-2">MikroTik BGP template</div>
                  <form method="post" class="vstack gap-3">
                    <input type="hidden" name="action" value="save_bgp_template">
                    <div><label class="form-label">Device</label><select name="device_name" class="form-select"><?php foreach (getv($data,'devices',[]) as $device): ?><option value="<?= e(getv($device,'name')) ?>"><?= e(getv($device,'name')) ?></option><?php endforeach; ?></select></div>
                    <div><label class="form-label">Peer name</label><input name="peer_name" class="form-control" required></div>
                    <div><label class="form-label">Peer address</label><input name="peer_address" class="form-control"></div>
                    <div><label class="form-label">Remote ASN</label><input name="remote_asn" class="form-control"></div>
                    <div><label class="form-label">State</label><input name="state" class="form-control" value="Template"></div>
                    <div class="row g-2"><div class="col"><label class="form-label">Prefix In</label><input name="prefix_in" class="form-control" value="—"></div><div class="col"><label class="form-label">Prefix Out</label><input name="prefix_out" class="form-control" value="—"></div></div>
                    <div><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3"></textarea></div>
                    <button class="btn btn-info">Simpan Template BGP</button>
                  </form>
                </div>
              </div>
              <div class="col-12 col-xl-8">
                <div class="app-card rounded-4 p-3 h-100">
                  <div class="kicker mb-2">BGP summary panel</div>
                  <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Peer</th><th>Device</th><th>Remote ASN</th><th>State</th><th>Prefix</th></tr></thead><tbody>
                    <?php foreach ($bgpPeers as $peer): ?>
                      <tr>
                        <td><div class="fw-semibold"><?= e(getv($peer,'peer_name')) ?></div><div class="small muted"><?= e(getv($peer,'peer_address')) ?></div></td>
                        <td><?= e(getv($peer,'device_name')) ?></td>
                        <td><?= e(getv($peer,'remote_asn')) ?></td>
                        <td><span class="badge <?= e(status_badge_class(getv($peer,'state'))) ?>"><?= e(getv($peer,'state')) ?></span></td>
                        <td>In: <?= e(getv($peer,'prefix_in')) ?><br>Out: <?= e(getv($peer,'prefix_out')) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody></table></div>
                </div>
              </div>
            </div>

          <?php elseif ($tab === 'routes'): ?>
            <div class="row g-3">
              <div class="col-12 col-xl-4">
                <div class="app-card rounded-4 p-3 h-100">
                  <div class="kicker mb-2">Route workflow</div>
                  <form method="post" class="vstack gap-3">
                    <input type="hidden" name="action" value="save_route_query">
                    <div><label class="form-label">Query IP / Prefix</label><input name="query" class="form-control" placeholder="8.8.8.8 atau 103.196.85.0/24" required></div>
                    <div><label class="form-label">Target device</label><select name="device_name" class="form-select"><?php foreach (getv($data,'devices',[]) as $device): ?><option value="<?= e(getv($device,'name')) ?>"><?= e(getv($device,'name')) ?></option><?php endforeach; ?></select></div>
                    <button class="btn btn-info">Simpan Route Query</button>
                  </form>
                </div>
              </div>
              <div class="col-12 col-xl-8">
                <div class="app-card rounded-4 p-3 h-100">
                  <div class="kicker mb-2">Route query log</div>
                  <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Waktu</th><th>Target</th><th>Query</th><th>Result</th></tr></thead><tbody>
                    <?php foreach ($routeQueries as $route): ?>
                      <tr>
                        <td class="mono small-code"><?= e(getv($route,'created_at')) ?></td>
                        <td><?= e(getv($route,'device_name')) ?><div class="small muted"><?= e(getv($route,'device_type')) ?></div></td>
                        <td><code><?= e(getv($route,'query')) ?></code></td>
                        <td class="small muted"><?= e(getv($route,'result')) ?></td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if (!$routeQueries): ?><tr><td colspan="4" class="muted">Belum ada route query.</td></tr><?php endif; ?>
                  </tbody></table></div>
                </div>
              </div>
            </div>

          <?php elseif ($tab === 'backups'): ?>
            <div class="row g-3">
              <div class="col-12 col-xl-4">
                <div class="app-card rounded-4 p-3 h-100">
                  <div class="kicker mb-2">Backup queue composer</div>
                  <form method="post" class="vstack gap-3">
                    <input type="hidden" name="action" value="queue_backup">
                    <div><label class="form-label">Target device</label><select name="device_name" class="form-select"><?php foreach (getv($data,'devices',[]) as $device): ?><option value="<?= e(getv($device,'name')) ?>"><?= e(getv($device,'name')) ?></option><?php endforeach; ?></select></div>
                    <div><label class="form-label">Backup type</label><select name="backup_type" class="form-select"><option value="mikrotik-rsc">MikroTik .rsc</option><option value="mikrotik-backup">MikroTik binary backup</option><option value="olt-export">OLT export / dump</option><option value="config-text">Generic config text</option></select></div>
                    <div><label class="form-label">Message</label><textarea name="message" rows="3" class="form-control" placeholder="catatan job backup"></textarea></div>
                    <button class="btn btn-info">Tambah Queue Backup</button>
                  </form>
                </div>
              </div>
              <div class="col-12 col-xl-8">
                <div class="app-card rounded-4 p-3 h-100">
                  <div class="kicker mb-2">Backup center</div>
                  <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Waktu</th><th>Device</th><th>Tipe</th><th>Status</th><th>Catatan</th></tr></thead><tbody>
                    <?php foreach ($backupJobs as $job): ?>
                      <tr>
                        <td class="mono small-code"><?= e(getv($job,'created_at')) ?></td>
                        <td><?= e(getv($job,'device_name')) ?></td>
                        <td><?= e(getv($job,'backup_type')) ?></td>
                        <td><span class="badge <?= e(status_badge_class(getv($job,'status'))) ?>"><?= e(status_text(getv($job,'status'))) ?></span></td>
                        <td class="small muted"><?= e(getv($job,'message')) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody></table></div>
                </div>
              </div>
            </div>

          <?php elseif ($tab === 'settings'): ?>
            <div class="row g-3">
              <div class="col-12 col-xl-6">
                <div class="app-card rounded-4 p-3 h-100">
                  <div class="kicker mb-2">Admin security</div>
                  <form method="post" class="vstack gap-3">
                    <input type="hidden" name="action" value="change_password">
                    <div><label class="form-label">Password lama</label><input type="password" name="old_password" class="form-control" required></div>
                    <div><label class="form-label">Password baru</label><input type="password" name="new_password" class="form-control" required></div>
                    <div><label class="form-label">Konfirmasi password baru</label><input type="password" name="confirm_password" class="form-control" required></div>
                    <button class="btn btn-info">Update Password</button>
                  </form>
                </div>
              </div>
              <div class="col-12 col-xl-6">
                <div class="app-card rounded-4 p-3 h-100">
                  <div class="kicker mb-2">System info</div>
                  <div class="small muted mb-2">App name: <strong class="text-white"><?= e(getv($data['meta'],'app_name')) ?></strong></div>
                  <div class="small muted mb-2">Focus: <strong class="text-white"><?= e(getv($data['meta'],'focus')) ?></strong></div>
                  <div class="small muted mb-3">Storage: <code><?= e(APP_STORAGE) ?></code></div>
                  <div class="d-flex gap-2 flex-wrap">
                    <form method="post" onsubmit="return confirm('Reset semua data ke seed profesional?')"><input type="hidden" name="action" value="reset_seed"><button class="btn btn-outline-warning">Reset Seed</button></form>
                    <a href="./docs/blueprint-noc-dashboard.md" target="_blank" class="btn btn-outline-light">Lihat Blueprint</a>
                  </div>
                </div>
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
