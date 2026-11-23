# Bab 2: Simulasi Operasional & Perhitungan Kasir End-to-End

Dokumen ini menyajikan simulasi operasional lengkap dari jam buka toko hingga tutup shift (Closing) untuk menguji dan memverifikasi seluruh rumus matematika dan logika database sistem kasir **Buku Digital POS & Manajemen Pabrik Es Kristal**.

---

## 1. Profil Simulasi

| Parameter | Keterangan |
| :--- | :--- |
| **Nama Kasir** | Dika (`adincrystal43@gmail.com`) |
| **Cabang Operasional** | Cabang Utama (`CB-01`) |
| **Tanggal Simulasi** | Senin, 23 November 2026 |
| **Jam Shift** | 07:00 – 17:15 WIB |
| **Daftar Produk** | 1. **Es Kristal Cube 10kg**: Harga Jual **Rp 20.000**, HPP **Rp 12.000**<br>2. **Es Kristal Tube 5kg**: Harga Jual **Rp 12.000**, HPP **Rp 7.000** |

---

## 2. Rangkaian 10 Kejadian Kronologis Sepanjang Shift

```mermaid
timeline
    title Kronologi Kejadian Shift Kasir Dika (07:00 - 17:15 WIB)
    07:00 : Input Modal Awal Rp 200.000
    08:30 : Penjualan Tunai Meja (+Rp 100.000)
    09:15 : Penjualan Transfer Bank (+Rp 200.000 Non-Tunai)
    10:00 : Driver Berangkat Kirim (Kasbon Rp 240.000)
    11:30 : Kas Keluar Beli Bensin (-Rp 20.000)
    12:30 : Beli Es Luar Pakai Uang Laci (-Rp 80.000)
    13:00 : Driver Kembali Setor Tunai (+Rp 240.000)
    14:00 : Pelunasan Kasbon Lama via Bank (+Rp 150.000 Non-Tunai)
    15:00 : Sync Gaji Karyawan Rp 500.000 (Uang Bos - Rp 0 Laci)
    15:30 : Pembatalan Nota Tunai Meja (-Rp 20.000 Void Contra-Entry)
    16:30 : Setor Kas ke Rekening Owner (-Rp 300.000 Handover)
    17:15 : Closing Kasir & Rekonsiliasi Fisik
```

---

### Kejadian 1 — Jam 07:00: Input Modal Awal Laci
- **Aksi Kasir**: Dika membuka laci kasir, menghitung uang kembalian kemarin, dan menginput modal awal sebesar **Rp 200.000**.
- **Pencatatan Database**:
  - `cashier_opening_balances`: `amount = 200000`, `date = '2026-11-23'`, `user_id = Dika`, `branch_id = 1`.
- **Saldo Laci**:
  $$\text{Expected Drawer} = \text{Rp } 200.000$$

---

### Kejadian 2 — Jam 08:30: Penjualan Tunai Meja (Walk-in Lunas)
- **Aksi Kasir**: Pelanggan *Warung Bu Siti* membeli tunai 5 bag Es Cube 10kg $\times$ Rp 20.000 = **Rp 100.000**.
- **Pencatatan Database**:
  - `transactions`: `type = 'sale'`, `payment_status = 'paid'`, `payment_method = 'cash'`, `initial_payment_method = 'cash'`, `status = 'completed'`, `total_amount = 100000`.
  - `cash_flow`: `type = 'income'`, `flow_type = 'sale_direct'`, `reference_type = 'transaction'`, `amount = 100000`, `funding_source = 'drawer'`, `affects_drawer = 1`.
  - HPP Produk: 5 bag $\times$ Rp 12.000 = Rp 60.000.
- **Saldo Laci**:
  $$\text{Expected Drawer} = 200.000 + 100.000 = \text{Rp } 300.000$$

---

### Kejadian 3 — Jam 09:15: Penjualan Non-Tunai (Transfer Bank BCA)
- **Aksi Kasir**: Pelanggan *Kafe Senja* membeli 10 bag Es Cube $\times$ Rp 20.000 = **Rp 200.000** via Transfer Bank.
- **Pencatatan Database**:
  - `transactions`: `type = 'sale'`, `payment_status = 'paid'`, `payment_method = 'transfer-bank'`, `initial_payment_method = 'transfer-bank'`, `total_amount = 200000`.
  - `cash_flow`: `type = 'income'`, `flow_type = 'sale_direct'`, `payment_method = 'transfer-bank'`, `funding_source = 'owner'`, `affects_drawer = 0`.
- **Saldo Laci**:
  $$\text{Expected Drawer} = \text{Tetap Rp } 300.000 \quad (\text{Uang masuk rekening Owner, Rp 0 ke laci})$$

---

### Kejadian 4 — Jam 10:00: Pengiriman Rombongan Driver (Delivery Trip Berjalan)
- **Aksi Kasir**: Resto Bebek memesan 20 bag Es Tube 5kg $\times$ Rp 12.000 = **Rp 240.000**. Dibawa Driver Anton.
- **Pencatatan Database**:
  - `transactions`: `type = 'sale'`, `payment_status = 'pending'`, `payment_method = 'kasbon'`, `initial_payment_method = 'kasbon'`, `delivery_status = 'delivering'`, `total_amount = 240000`.
  - `cash_flow`: *Belum ada (uang belum diterima).*
- **Saldo Laci**:
  $$\text{Expected Drawer} = \text{Tetap Rp } 300.000$$

---

### Kejadian 5 — Jam 11:30: Pengeluaran Kas Fisik Laci (Beli Bensin)
- **Aksi Kasir**: Dika mengeluarkan **Rp 20.000** dari laci untuk bensin darurat motor toko.
- **Pencatatan Database**:
  - `cash_flow`: `type = 'expense'`, `flow_type = 'expense'`, `category_type = 'Operasional'`, `is_cogs = 0`, `amount = 20000`, `funding_source = 'drawer'`, `affects_drawer = 1`.
- **Saldo Laci**:
  $$\text{Expected Drawer} = 300.000 - 20.000 = \text{Rp } 280.000$$

---

### Kejadian 6 — Jam 12:30: Pembelian Es Luar Darurat Pakai Uang Laci (is_cogs = 1)
- **Aksi Kasir**: Stok habis mendadak, Dika membeli 10 bag es luar $\times$ Rp 8.000 = **Rp 80.000** dari uang laci.
- **Pencatatan Database**:
  - `cash_flow`: `type = 'expense'`, `flow_type = 'stock_purchase'`, `category_type = 'HPP'`, `is_cogs = 1`, `amount = 80000`, `funding_source = 'drawer'`, `affects_drawer = 1`.
- **Saldo Laci**:
  $$\text{Expected Drawer} = 280.000 - 80.000 = \text{Rp } 200.000$$
  *(Catatan: Biaya Rp 80.000 ini dikecualikan dari beban operasional P&L murni karena diakui via COGS).*

---

### Kejadian 7 — Jam 13:00: Driver Kembali & Setor Tunai (Pelunasan Trip)
- **Aksi Kasir**: Driver Anton kembali menyerahkan uang tunai **Rp 240.000**. Dika menekan tombol "Selesaikan Pengiriman".
- **Pencatatan Database**:
  - `transactions`: `payment_status = 'paid'`, `initial_payment_method = 'kasbon'`, `settlement_payment_method = 'cash'`, `settled_at = now()`.
  - `cash_flow`: `type = 'income'`, `flow_type = 'debt_settlement'`, `payment_method = 'cash'`, `funding_source = 'drawer'`, `affects_drawer = 1`, `amount = 240000`.
- **Saldo Laci**:
  $$\text{Expected Drawer} = 200.000 + 240.000 = \text{Rp } 440.000$$

---

### Kejadian 8 — Jam 14:00: Pelunasan Kasbon Lama via Transfer Bank
- **Aksi Kasir**: Pelanggan melunasi kasbon tempo lama Rp 150.000 via transfer bank owner.
- **Pencatatan Database**:
  - `transactions`: `payment_status = 'paid'`, `initial_payment_method = 'kasbon'`, `settlement_payment_method = 'transfer-bank'`.
  - `cash_flow`: `type = 'income'`, `flow_type = 'debt_settlement'`, `payment_method = 'transfer-bank'`, `funding_source = 'owner'`, `affects_drawer = 0`, `amount = 150000`.
- **Saldo Laci**:
  $$\text{Expected Drawer} = \text{Tetap Rp } 440.000 \quad (\text{Non-tunai tidak menambah laci fisik})$$

---

### Kejadian 9 — Jam 15:00: Sinkronisasi Gaji Karyawan (SOP Tgl 15/30)
- **Sistem Event**: API `/api/v1/sync/salary` menyinkronkan gaji karyawan Rp 500.000 yang ditransfer bos.
- **Pencatatan Database**:
  - `cash_flow`: `type = 'expense'`, `flow_type = 'salary_external'`, `reference_type = 'attendance_salary'`, `funding_source = 'external'`, `affects_drawer = 0`, `amount = 500000`.
- **Saldo Laci**:
  $$\text{Expected Drawer} = \text{Tetap Rp } 440.000 \quad (\text{TIDAK memotong kas laci})$$

---

### Kejadian 10 — Jam 15:30: Pembatalan / Refund Transaksi Meja (Soft Void & Contra-Entry)
- **Konteks & Aksi Kasir**: Pelanggan dari transaksi tunai meja sebelumnya (Kejadian 2) datang kembali membawa struk pembelian untuk membatalkan 1 bag Es Cube 10kg senilai **Rp 20.000** karena salah membawa wadah penyimpanan es.
- **Tindakan Kasir**:
  - Dika membuka menu Transaksi, memilih nota tersebut, dan melakukan **Void / Pembatalan Nota** dengan alasan *"Salah bawa wadah"*.
  - Kasir mengambil uang tunai **Rp 20.000** dari laci kasir dan mengembalikannya kepada pelanggan (*cash refund*).
- **Pencatatan Database (SSOT Cash Flow)**:
  - `transactions`: Status nota diubah menjadi `status = 'cancelled'`, dicatat `void_reason = 'Salah bawa wadah'`, `voided_by = 1`, `voided_at = '2026-11-23 15:30:00'`.
  - `stock_movements`: Stok gudang bertambah kembali 1 bag dengan tipe mutasi `'void_return'`.
  - `cash_flow` (Contra-Entry): Record penjualan asal (`flow_type = 'sale_direct'`) **TIDAK DIHAPUS** (tetap tercatat Rp 100.000). Sistem membuat jurnal pembalik contra-entry baru:
    `type = 'expense'`, `flow_type = 'cash_refund'`, `amount = 20000`, `payment_method = 'cash'`, `funding_source = 'drawer'`, `affects_drawer = 1`.
- **Status Kas Fisik Laci**:
  $$\text{Expected Drawer} = 440.000 - 20.000 = \text{Rp } 420.000$$

---

### Kejadian 11 — Jam 16:30: Setoran Kas Fisik Laci ke Rekening Owner (Cash Handover)
- **Aksi Kasir**: Karena uang fisik di laci sudah menumpuk, Dika menyetorkan **Rp 300.000** ke rekening BCA Owner via CDM.
- **Pencatatan Database**:
  - `cash_flow`: `type = 'expense'`, `flow_type = 'cash_handover'`, `reference_type = 'handover'`, `category_type = 'Non-usaha'`, `amount = 300000`, `affects_drawer = 1`.
- **Saldo Laci**:
  $$\text{Expected Drawer} = 420.000 - 300.000 = \text{Rp } 120.000$$

---

### Kejadian 12 — Jam 17:15: Tutup Shift & Rekonsiliasi Fisik (`cashier_shift_closings`)
- **Aksi Kasir**: Dika menghitung uang fisik di laci:
  - Ditemukan lembaran fisik uang pas: **Rp 120.000**.
  - Kasir menginput di menu Tutup Shift: `actual_cash_counted = 120000`.
- **Perhitungan Sistem**:
  - `expected_drawer_cash` = Rp 120.000.
  - `variance` = $120.000 - 120.000 = \mathbf{Rp\ 0}$ (Klop / Nihil).
- **Hasil Akhir di `cashier_shift_closings`**:
  - `opening_cash` = Rp 200.000
  - `cash_sales` = Rp 100.000
  - `cash_debt_collection` = Rp 240.000
  - `cash_expenses` = Rp 100.000 (Bensin 20k + Es Luar 80k)
  - `cash_handover_amount` = Rp 300.000
  - `cash_refunds` = Rp 20.000
  - `expected_drawer_cash` = Rp 120.000
  - `actual_cash_counted` = Rp 120.000
  - `variance` = Rp 0.00 (Status: PAS)
