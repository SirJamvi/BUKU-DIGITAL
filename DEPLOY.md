# RUNBOOK DEPLOYMENT: AI WHATSAPP ORDER SYSTEM (BUKUDIG)

Panduan ini berisi langkah-langkah deployment untuk administrator dan developer dalam mempublikasikan pembaruan **AI WhatsApp Order System (BukuDig - Adin Crystal)** ke lingkungan **Staging** dan **Produksi**.

> [!CAUTION]
> **PERINGATAN KEAMANAN**:
> Jangan pernah menuliskan password, secret key, atau API token ke dalam repositori, dokumentasi, atau log git. Selalu gunakan *environment variables* di server target.

---

## 1. DAFTAR FILE YANG DIPERBARUI / DITAMBAHKAN

Pastikan berkas-berkas berikut terunggah ke direktori server tujuan:

```text
config/
└── ai_order.php
app/
├── Http/Controllers/Api/V1/AiOrderWebhookController.php
├── Models/
│   ├── AiOrderDraft.php
│   └── TemporaryOrder.php
├── Services/
│   ├── Ai/
│   │   ├── AdminGroupNotifier.php
│   │   ├── AiOrderDebouncer.php
│   │   ├── AiOrderProcessor.php
│   │   ├── LlmOrderService.php
│   │   └── ReplyTemplates.php
│   └── Whatsapp/
│       └── PhoneNumberHelper.php
database/
└── migrations/
    ├── 2026_11_06_100000_create_ai_order_drafts_table.php
    ├── 2026_11_06_110000_update_status_enum_in_temporary_orders_table.php
    └── 2026_11_06_120000_add_is_new_customer_to_temporary_orders_table.php
tests/
└── Feature/
    └── AiOrderSystemTest.php
DOCS_AI_ORDER_SYSTEM.md
DEPLOY.md
```

---

## 2. CHECKLIST DEPLOYMENT KE STAGING (`spb.resdigaza.my.id`)

### Langkah 1: Verifikasi Host
Pastikan koneksi mengarah ke domain staging:
- **Host**: `spb.resdigaza.my.id`
- **FTP Host**: `ftp.resdigaza.my.id` (Port 21, Passive EPSV)
- **User**: Gunakan akun developer staging yang tersimpan di laptop lokal.

### Langkah 2: Upload File Terkait
Unggah seluruh berkas yang tercantum pada Bagian 1 melalui SFTP / FTP client Anda.

### Langkah 3: Eksekusi Migrasi Database & Reset Cache
Jalankan perintah berikut di lingkungan staging (melalui SSH, terminal cPanel, atau helper endpoint):
```bash
# 1. Jalankan migrasi tabel ai_order_drafts
php artisan migrate --force

# 2. Bersihkan cache aplikasi & konfigurasi
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# 3. Optimasi class autoloader
composer dump-autoload -o
```

### Langkah 4: Pengaturan `.env` di Staging
Pastikan variabel berikut ada di file `.env` staging:
```ini
AI_ORDER_DEBOUNCE_SECONDS=7
AI_ORDER_AFTER_RESPONSE=true
AI_ORDER_DRY_RUN=false
AI_ORDER_RATE_LIMIT=20
AI_ORDER_FOLLOWUP_MINUTES=5
AI_ORDER_ACK_MINUTES=10
AI_ORDER_ACK_REPLY=true
AI_ORDER_ADMIN_GROUP_ID=
AI_ORDER_ADMIN_NOTIFY=true
GEMINI_MODEL=gemini-flash-latest
CACHE_STORE=database
```
*(Catatan: Aktifkan `AI_ORDER_DRY_RUN=true` sementara bila ingin menguji alur tanpa mengirim pesan WhatsApp sungguhan).*

---

## 3. CHECKLIST DEPLOYMENT KE PRODUKSI (`pb.resdigaza.my.id`)

> [!IMPORTANT]
> Deployment ke server produksi wajib dilakukan secara manual oleh engineer yang berwenang setelah pengujian di staging terverifikasi 100% stabil.

- [ ] **1. Backup Database Produksi**:
  Lakukan dump database sebelum menjalankan migrasi:
  ```bash
  mysqldump -u $DB_USER -p $DB_NAME > backup_pre_ai_refactor_$(date +%Y%m%d).sql
  ```
- [ ] **2. Verifikasi Host Produksi**:
  Pastikan host adalah `pb.resdigaza.my.id`.
- [ ] **3. Upload Berkas**:
  Unggah berkas kode sesuai daftar Bagian 1.
- [ ] **4. Jalankan Migrasi**:
  ```bash
  php artisan migrate --force
  ```
- [ ] **5. Verifikasi Variabel `.env` Produksi**:
  - `GEMINI_MODEL=gemini-flash-latest` (pastikan kuota Gemini Tier 1 berbayar aktif).
  - `FONNTE_TOKEN=...` (token device WhatsApp resmi Adin Crystal).
  - `AI_ORDER_DEBOUNCE_SECONDS=7`.
  - `AI_ORDER_AFTER_RESPONSE=true`.
  - `AI_ORDER_DRY_RUN=false`.
  - `AI_ORDER_RATE_LIMIT=20`.
  - `AI_ORDER_FOLLOWUP_MINUTES=5`.
  - `AI_ORDER_ACK_MINUTES=10`.
  - `AI_ORDER_ACK_REPLY=true`.
  - `AI_ORDER_ADMIN_GROUP_ID=` (ID grup WA kasir/admin toko).
  - `AI_ORDER_ADMIN_NOTIFY=true`.
  - `CACHE_STORE=database` (atau `redis`).
- [ ] **6. Reset Cache di Produksi**:
  ```bash
  php artisan config:clear
  php artisan route:clear
  php artisan cache:clear
  php artisan config:cache
  php artisan route:cache
  ```
- [ ] **7. Verifikasi Layar Kasir**:
  Buka modul POS Kasir dan pastikan antrean pesanan AI (`/api/v1/kasir/ai-orders`) menampilkan order dengan format yang benar.
- [ ] **8. Uji Chat Tunggal dari Nomor Internal**:
  Kirim pesan uji coba dari nomor internal (misal: "3 ball yang 20 jam 4 sore") dan verifikasi balasan serta status order pada database.

---

## 4. ROLLBACK PLAN

Bila terjadi kendala kritis pada sistem pemesanan AI:
1. Kembalikan berkas `AiOrderWebhookController.php` dan `LlmOrderService.php` ke versi backup sebelumnya.
2. Jalankan pembersihan cache:
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan cache:clear
   ```
3. Pesanan yang masuk melalui WhatsApp tetap dapat dilayani secara manual oleh admin dengan mengaktifkan kata kunci `"admin"` (Human Mode).

