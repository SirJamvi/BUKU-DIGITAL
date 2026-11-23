# Dokumentasi Lengkap Logika Finansial & Operasional Kasir (Buku Digital)

Selamat datang di pusat dokumentasi resmi arsitektur finansial, logika perhitungan POS kasir, manajemen cash flow, dan rekonsiliasi audit untuk sistem **Buku Digital POS & Manajemen Pabrik Es Kristal**.

Dokumentasi ini disusun secara komprehensif untuk pemilik bisnis (*owner*), akuntan/admin finance, developer, dan kasir guna memahami bagaimana setiap rupiah dihitung, dicatat, diamankan dari manipulasi, dan diverifikasi di dalam database dan aplikasi.

---

## 📚 Struktur Dokumen

Dokumentasi dibagi menjadi beberapa bagian utama:

1. [**01_ARSITEKTUR_DAN_LOGIKA_KASIR.md**](./01_ARSITEKTUR_DAN_LOGIKA_KASIR.md)
   - **Prinsip Dual-Metric**: Pemisahan tegas Saldo Fisik Laci (*Cash on Hand*) vs Profitabilitas Shift (*Shift Net Profit*).
   - **SSOT Arus Kas (`flow_type`)**: Standarisasi 7 tipe mutasi kas baku (`sale_direct`, `debt_settlement`, `expense`, `stock_purchase`, `cash_handover`, `cash_refund`, `salary_external`).
   - **Proteksi Histori Kredit**: Kolom `initial_payment_method`, `settlement_payment_method`, dan `settled_at` pada tabel `transactions`.
   - **Soft Void & Contra-Entry**: Meniadakan hard-delete destruktif; pembatalan transaksi menghasilkan `status = 'cancelled'`, `StockMovement` tipe `void_return`, dan contra-entry `cash_refund` di `cash_flow`.
   - **Tutup Shift Kasir Harian (`cashier_shift_closings`)**: Pelacakan selisih fisik kas (*Cash Over/Short*) secara permanen.
   - **Kunci Periode Pembukuan Bulanan (`AccountingPeriodGuard`)**: Proteksi anti-mutasi pada bulan yang sudah di-close bulanan.

2. [**02_SIMULASI_OPERASIONAL_KASIR.md**](./02_SIMULASI_OPERASIONAL_KASIR.md)
   - **Studi Kasus 12 Kejadian Kronologis**: Simulasi satu hari kerja penuh kasir (07:00 – 17:15 WIB).
   - Tracing matematis per detik: Modal Awal, Penjualan Meja Tunai & Non-Tunai, Delivery Trip Kasbon, Pengeluaran Toko, Pembelian Es Luar Darurat, Pelunasan Tunai & Non-Tunai, Sync Gaji Karyawan, Soft Void Contra-Entry, Setoran Fisik ke Bank (*Cash Handover*), dan Rekonsiliasi Tutup Shift Kasir.

3. [**03_REFERENSI_SQL_DAN_QUERY_KASIR.md**](./03_REFERENSI_SQL_DAN_QUERY_KASIR.md)
   - Kumpulan query SQL siap pakai untuk audit database langsung (HeidiSQL, phpMyAdmin, DBeaver).
   - Query master rekapitulasi laci kasir berbasis SSOT baru (`flow_type = 'debt_settlement'`).
   - Query audit Over/Short tutup shift harian.
   - Query audit pembatalan nota (Void) & contra-entry.
   - Definisi SQL database view `v_cashier_shift_drawer_summary`.

---

## ⚡ Ringkasan Rumus Baku Kasir (SSOT)

### 1. Saldo Fisik Laci Kasir (Cash on Hand / Expected Drawer Cash)
Uang fisik nyata (lembaran rupiah) yang wajib ada di laci saat dihitung:

$$\text{Expected Drawer Cash} = \text{Modal Awal} + \text{Penjualan Tunai Meja} + \text{Pelunasan Tunai Masuk} - \text{Total Kas Keluar Laci}$$

Dimana $\text{Total Kas Keluar Laci}$ adalah:
$$\text{Kas Keluar Laci} = \text{Beban Operasional Laci} + \text{Pembelian Stok Laci} + \text{Setoran Handover} + \text{Contra Refund}$$

### 2. Selisih Uang Fisik Kasir (Cash Over / Short)
$$\text{Variance} = \text{Actual Cash Counted} - \text{Expected Drawer Cash}$$

- **Variance = 0**: Saldo Pas / Klop.
- **Variance > 0**: Kas Lebih (*Cash Over*).
- **Variance < 0**: Kas Kurang (*Cash Short* / Kasir tekor).

### 3. Estimasi Laba Bersih Shift (Shift Net Profit)
$$\text{Laba Bersih Shift} = \text{Total Omset Penjualan} - \text{Total HPP Produk (COGS)} - \text{Beban Operasional Cabang}$$

*Catatan: Gaji Karyawan eksternal dan mutasi kas internal (Handover & Refund) tidak memotong Laba Bersih Shift.*

---

## 🛡️ Kebijakan Khusus & Proteksi Sistem

1. **Non-Tunai (Transfer Bank / DANA / QRIS)**: Masuk langsung ke rekening Owner (`funding_source = owner`). Dicatat dalam omset pendapatan, namun **Rp 0** ke laci fisik kasir.
2. **Gaji Karyawan (SOP Tgl 15 & 30)**: Dibayarkan dari uang bos/eksternal. Dicatat sebagai beban usaha bulanan (`funding_source = 'external'`, `affects_drawer = 0`, `flow_type = 'salary_external'`), **TIDAK MEMOTONG** uang fisik di laci kasir real-time.
3. **Pelunasan Lintas Cabang (*Cross-Branch*)**: Jika kasbon dibuat di Cabang A namun dilunasi tunai di Cabang B, uang fisik kas bertambah di laci kasir Cabang B (cabang penerima uang).
4. **Proteksi Tutup Buku Bulanan**: Transaksi pada periode yang sudah di-close bulanan di `branch_monthly_closings` tidak dapat diubah, dilunasi, atau di-void kecuali setelah di-reopen oleh Administrator.
