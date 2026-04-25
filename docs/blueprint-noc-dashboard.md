# Blueprint — NOC Dashboard Web App

## Target
Membangun dashboard **Network Operation Control / Center** berbasis web app untuk `noc.anantasatriya.my.id` yang dapat menjadi panel operasional jaringan multi-device.

## Tujuan inti
- Monitoring perangkat jaringan dari satu dashboard
- Mendukung banyak jenis koneksi: API, SNMP, dan bila perlu collector/CLI helper
- Menyediakan pencarian cepat untuk device, BGP, route, interface, dan status layanan
- Menyediakan backup konfigurasi otomatis untuk MikroTik (`.rsc`) dan OLT bila memungkinkan
- Menjadi fondasi operasional yang bisa dikembangkan bertahap sampai usable real

## Jenis perangkat target
- MikroTik router
- OLT GPON/EPON (ZTE / HSGQ / HS AIRPRO GLOBAL / vendor lain bila feasible)
- Switch manageable
- Server / monitoring nodes
- Device lain yang expose SNMP/API

## Arsitektur yang disarankan

### 1. Frontend dashboard
Web app yang menampilkan:
- ringkasan status perangkat
- device explorer
- panel BGP summary
- route finder
- backup center
- alert feed
- activity log
- halaman settings / credential mapping

### 2. Backend app/API
Layer aplikasi untuk:
- menyimpan device registry
- menyimpan mapping credential per device
- mengeksekusi request ke collector
- menyediakan endpoint UI
- audit log dan permission model

### 3. Collector / connector layer
Service modular terpisah untuk koneksi jaringan:
- SNMP poller
- MikroTik API connector
- SSH / CLI backup worker
- OLT-specific adapters
- scheduler untuk backup dan polling berkala

### 4. Storage layer
Tahap awal:
- JSON + SQLite / MySQL

Tahap lanjut:
- relational DB penuh + object/file storage untuk backup config

## Modul utama

### A. Overview dashboard
- total devices
- up/down/warning
- device by vendor/type
- backup success/failure terakhir
- top alerts
- collector health

### B. Device registry
Field penting:
- nama device
- tipe device
- vendor
- hostname / IP
- site / lokasi
- metode koneksi: API / SNMP / SSH / hybrid
- port API custom
- SNMP version/community/security
- credential profile
- tag
- status enabled

### C. SNMP monitoring
- polling custom OID per vendor/device profile
- interface traffic
- cpu / memory / uptime
- optical/pon data bila tersedia
- status device online/offline

### D. MikroTik operations
- connect via RouterOS API
- lihat identity, resource, interface, queue, PPP secret bila diizinkan
- ringkasan BGP peer
- route lookup
- backup config `.rsc`
- scheduler backup manual / otomatis

### E. BGP panel
- daftar peer
- state established / idle / active
- prefix count in/out bila accessible
- last flap / uptime peer bila accessible
- ringkasan route policy dasar

### F. Route finder
- search IP / prefix
- tampil best match route
- gateway / interface / distance / routing table
- origin device
- multi-device lookup jika dibutuhkan

### G. Config backup center
- daftar backup terakhir per device
- status sukses / gagal
- ukuran file
- timestamp
- tombol backup manual
- retensi file
- target format:
  - MikroTik: `.rsc`
  - vendor lain: raw text / cfg / export sesuai kemampuan

### H. OLT support
Untuk OLT, pendekatan harus modular:
- SNMP bila MIB/OID tersedia dan reachable
- SSH/Telnet dump bila user izinkan dan vendor memungkinkan
- backup support tergantung vendor/command
- jangan hardcode asumsi lintas vendor

## Skema data awal

### devices
- id
- name
- vendor
- device_type
- host
- site
- connection_mode
- api_port
- snmp_port
- snmp_version
- snmp_profile
- credential_profile
- enabled
- created_at
- updated_at

### bgp_peers
- id
- device_id
- peer_name
- peer_address
- remote_asn
- state
- prefixes_in
- prefixes_out
- last_seen_at

### route_snapshots
- id
- device_id
- query
- result_json
- created_at

### backups
- id
- device_id
- backup_type
- filename
- status
- size_bytes
- storage_path
- started_at
- finished_at
- message

### poll_runs
- id
- device_id
- poll_type
- status
- started_at
- finished_at
- summary

### alerts
- id
- device_id
- severity
- title
- body
- status
- created_at
- resolved_at

## Kebutuhan keamanan
- credential tidak ditaruh plaintext di frontend
- backend menyimpan credential encrypted/obfuscated semampunya stack awal
- audit log untuk aksi sensitif
- role minimal: admin / operator / viewer
- rate limit untuk aksi backup/polling

## Strategi delivery

### Fase 1 — MVP live cepat
- dashboard shell
- blueprint arsitektur
- data demo realistis
- struktur menu final-ish
- siap di-review user

### Fase 2 — Device registry + connector skeleton
- CRUD device
- profile koneksi
- endpoint API lokal
- mock collector

### Fase 3 — MikroTik first
- koneksi RouterOS API
- BGP summary
- route lookup
- backup `.rsc`

### Fase 4 — SNMP multi-vendor
- polling generic SNMP
- profile per vendor
- metrics snapshot

### Fase 5 — OLT integrations
- adapter per vendor
- backup/dump bila feasible
- OLT explorer

## Catatan engineering
- Port API tiap router bisa berbeda, jadi `api_port` wajib configurable per device
- SNMP port/version/security juga wajib configurable per device
- Backup OLT tidak boleh diasumsikan seragam; harus lewat adapter vendor-specific
- Untuk hosting shared/PHP, collector berat kemungkinan lebih ideal dipisah ke VM/service lain
- Web app tetap bisa jadi panel utama walau collector berjalan di host lain

## Rekomendasi stack

### Jalur cepat MVP
- Frontend: static HTML/CSS/JS
- Data: JSON mock + endpoint ringan
- Deploy: FTP hosting

### Jalur production bertahap
- Frontend: tetap ringan
- Backend: PHP atau Node/Python service sesuai konektivitas device
- Collector network: Python/Go/Node terpisah di VM bila dibutuhkan

## Deliverable awal yang harus ada
- halaman dashboard live
- halaman devices
- halaman BGP & route explorer (mock shell)
- halaman backup center
- blueprint tertulis
- repo GitHub siap dipakai iterasi
