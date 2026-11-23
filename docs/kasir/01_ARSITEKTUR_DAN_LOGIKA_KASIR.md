# Bab 1: Arsitektur & Logika Perhitungan Kasir (Buku Digital)

Dokumen ini menjelaskan seluruh fondasi matematika, aturan bisnis (*business rules*), skema database, dan alur data dari sistem kasir pada aplikasi **Buku Digital POS & Manajemen Pabrik Es Kristal**.

---

## 1. Prinsip Utama: Dual-Metric Kasir

Sistem Buku Digital memisahkan perhitungan kasir menjadi dua dimensi metrik yang **berbeda tujuan namun saling melengkapi**:

```mermaid
flowchart TD
    subgraph METRIK_1["DIMENSI 1: KAS FISIK LACI (Cash on Hand)"]
        direction TB
        A1["Modal Awal Kasir (Kas Kemarin)"] --> M1["(+) Saldo Laci Kasir"]
        A2["Penjualan Tunai Meja (POS Langsung Lunas)"] --> M1
        A3["Pelunasan Kasbon & Setoran Driver Tunai (debt_settlement)"] --> M1
        A4["Pengeluaran Kas Laci (expense + stock_purchase)"] -->|(-) Kurang| M1
        A5["Setoran Kas ke Owner/Bank (cash_handover)"] -->|(-) Kurang| M1
        A6["Contra-Entry Pembatalan Nota (cash_refund)"] -->|(-) Kurang| M1
        M1 ==> HASIL1["Wajib Sama Persis dengan Lembaran Uang Fisik di Laci"]
    end

    subgraph METRIK_2["DIMENSI 2: PROFITABILITAS SHIFT (Laba Bersih Shift)"]
        direction TB
        B1["Total Omset Penjualan (Tunai + Transfer + Kasbon)"] --> M2["Performa Penjualan"]
        B2["Total HPP Produk Terjual (COGS Snapshot)"] -->|(-) Kurang| M2
        B3["Beban Operasional Cabang (is_cogs = 0 & category_type = Operasional)"] -->|(-) Kurang| M2
        M2 ==> HASIL2["Laba Bersih yang Dihasilkan pada Shift Ini"]
    end
```

| Parameter | Metrik 1: Saldo Fisik Laci (*Drawer Cash*) | Metrik 2: Laba Bersih Shift (*Shift Net Profit*) |
| :--- | :--- | :--- |
| **Fokus Utama** | Akuntabilitas uang kas fisik di meja kasir. | Performa finansial dan profitabilitas bisnis. |
| **Transaksi Non-Tunai (Transfer/DANA)** | **Rp 0** (Tidak masuk ke laci, langsung ke rekening owner). | **Dihitung 100%** sebagai omset pendapatan. |
| **Transaksi Kasbon Baru** | **Rp 0** (Belum ada uang fisik yang diterima). | **Dihitung 100%** sebagai omset penjualan. |
| **Pelunasan Kasbon Lama** | **Dihitung 100%** (Uang fisik baru masuk hari ini). | **Rp 0** (Omset sudah diakui saat nota dibuat). |
| **Modal Awal Laci** | **Dihitung** sebagai saldo awal kembalian. | **Tidak dihitung** (Bukan pendapatan operasional). |
| **HPP Produk (COGS)** | **Tidak memotong** uang laci (dihitung saat terjual). | **Memotong** margin laba kotor. |
| **Beban Gaji Karyawan** | **Tidak memotong** laci kasir (uang bos/eksternal). | **Memotong** laba rugi bulanan (bukan shift harian). |
| **Cash Handover (Setoran Bank)** | **Memotong** uang fisik laci kasir. | **Tidak memotong** (Mutasi internal, bukan beban). |
| **Pembatalan Nota (Soft Void)** | **Memotong** via contra-entry `cash_refund`. | **Mengurangi** omset dan mengembalikan stok. |

---

## 2. Standarisasi SSOT Arus Kas (`flow_type` pada `cash_flow`)

Sistem mengeliminasi ketergantungan pencocokan string teks (`LIKE '%Pelunasan%'`) dan menggantinya dengan kolom enum terindeks `flow_type`:

| `flow_type` | Keterangan & Tujuan | `affects_drawer` | Masuk Laba Rugi? |
| :--- | :--- | :---: | :---: |
| `sale_direct` | Penjualan langsung lunas dari kasir meja (POS). | 1 (Cash) / 0 (Non-Cash) | Ya (Omset) |
| `debt_settlement` | Pelunasan piutang kasbon pelanggan / setoran uang driver. | 1 (Cash) / 0 (Non-Cash) | Tidak (Omset diakui di awal) |
| `expense` | Beban operasional murni (bensin, konsumsi, plastik, ATK). | 1 (Laci) / 0 (Owner) | Ya (Jika `is_cogs=0` & `Operasional`) |
| `stock_purchase` | Pembelian stok luar / refill es batu darurat (HPP). | 1 (Laci) / 0 (Owner) | Tidak langsung (Diakui lewat COGS) |
| `cash_handover` | Setoran fisik uang laci ke Owner/Bank (*cash drop*). | 1 | **TIDAK** (Mutasi kas internal) |
| `cash_refund` | Jurnal pembalik (*contra-entry*) saat nota dibatalkan/void. | 1 (Cash) / 0 (Non-Cash) | Mengurangi kas & laba |
| `salary_external` | Sinkronisasi beban gaji karyawan dari sistem absensi (Tgl 15/30). | **0** (Uang Bos) | Ya (Laporan Bulanan Owner) |

---

## 3. Logika Matematis Saldo Fisik Laci Kasir (SSOT)

Formula baku yang digunakan di seluruh aplikasi (pada `DashboardService`, `AcsCashDrawerSyncService`, view `v_cashier_shift_drawer_summary`, dan `CashierClosingService`):

$$\text{Expected Drawer Cash} = \text{Modal Awal} + \text{Penjualan Tunai Meja} + \text{Pelunasan Tunai} - \text{Total Kas Keluar Laci}$$

Dimana $\text{Total Kas Keluar Laci}$ mencakup:
$$\text{Kas Keluar Laci} = \text{Beban Operasional Laci} + \text{Pembelian Stok Laci} + \text{Setoran Handover} + \text{Contra Refund}$$

### Rincian Komponen Pembentuk Laci:

### A. Modal Awal Laci (`opening_cash`)
- **Tabel Sumber**: `cashier_opening_balances`
- **Unique Constraint**: `['business_id', 'branch_id', 'user_id', 'date']` (Mendukung kasir bantu lintas cabang tanpa error duplikat).
- Diinput kasir saat pembukaan shift. Jika kasir belum input, sistem mengasumsikan Rp 0.

### B. Penjualan Tunai Langsung Lunas Meja (`cash_sales_amount`)
- **Tabel Sumber**: `transactions`
- **Kondisi Filter**:
  ```sql
  WHERE t.type = 'sale'
    AND t.payment_status = 'paid'
    AND LOWER(t.payment_method) = 'cash'
    AND t.status = 'completed'
    AND t.transaction_date BETWEEN :startOfDay AND :endOfDay
    AND t.created_by = :cashierId
    AND NOT EXISTS (
        SELECT 1 FROM cash_flow cf 
        WHERE cf.reference_id = CAST(t.id AS CHAR) 
          AND cf.reference_type = 'transaction'
          AND cf.flow_type = 'debt_settlement'
    )
  ```
- **Pengecualian**: Transaksi yang dilunasi belakangan dikecualikan dari subquery ini karena uang fisiknya masuk lewat komponen C (`debt_settlement`), mencegah *double-counting*.

### C. Pelunasan Kasbon & Setoran Driver Tunai (`debt_collection_cash`)
- **Tabel Sumber**: `cash_flow`
- **Kondisi Filter**:
  ```sql
  WHERE cf.type = 'income'
    AND LOWER(cf.payment_method) = 'cash'
    AND cf.affects_drawer = 1
    AND cf.flow_type = 'debt_settlement'
    AND cf.date = :targetDate
    AND cf.created_by = :cashierId
  ```
- **Aturan Lintas Cabang (*Cross-Branch Settlement*)**:
  Jika kasbon dibuat di Cabang A namun uang tunai diserahkan ke kasir di Cabang B, uang fisik masuk **100% ke laci kasir Cabang B** (`cf.branch_id = Cabang B`).

### D. Total Pengeluaran Kas Fisik dari Laci Kasir (`cash_expenses`)
- **Tabel Sumber**: `cash_flow`
- **Kondisi Filter**:
  ```sql
  WHERE cf.type = 'expense'
    AND LOWER(cf.payment_method) = 'cash'
    AND cf.affects_drawer = 1
    AND cf.flow_type IN ('expense', 'stock_purchase', 'cash_handover', 'cash_refund')
    AND cf.date = :targetDate
    AND cf.created_by = :cashierId
  ```

---

## 4. Proteksi Histori Kredit & Audit Void Transaksi

### A. Pelacakan Histori Kredit (Kasbon)
Saat transaksi dibuat sebagai kasbon atau trip pengiriman driver, kolom-kolom berikut dicatat:
- `initial_payment_method`: Menyimpan metode asal transaksi (`'kasbon'`). Kolom ini **tidak pernah ditimpa** saat transaksi dilunasi.
- `settlement_payment_method`: Menyimpan metode saat pelunasan (`'cash'`, `'transfer-bank'`, `'dana'`, dll).
- `settled_at`: Timestamp persis saat kasir mengklik tombol pelunasan.

### B. Mekanisme Soft Void & Contra-Entry (Tanpa Hard Delete)
Sistem melarang keras penghapusan destruktif (`delete()`) pada transaksi yang sudah selesai:
1. Status transaksi diubah menjadi `status = 'cancelled'`.
2. Alasan pembatalan dan audit trail dicatat: `void_reason`, `voided_by = Auth::id()`, `voided_at = now()`.
3. Stok produk dikembalikan secara otomatis dengan mutasi `StockMovement` bertipe `'void_return'`.
4. Jika transaksi berstatus lunas dan uang pernah masuk, sistem membuat **contra-entry pembalik** di `cash_flow`:
   - `type = 'expense'`
   - `flow_type = 'cash_refund'`
   - `amount = $transaction->total_amount`
   - `affects_drawer = ($payment_method === 'cash') ? 1 : 0`
   - `date = now()`
   - Record `cash_flow` lama tetap utuh untuk kebutuhan jejak audit forensik.

---

## 5. Tutup Shift Kasir Harian & Cash Over/Short (`cashier_shift_closings`)

Setiap kasir yang menyelesaikan jam kerja wajib melakukan Tutup Shift (*Shift Closing*).
Sistem menghitung selisih fisik kas (*variance*):

$$\text{Variance (Over/Short)} = \text{Actual Cash Counted} - \text{Expected Drawer Cash}$$

- **Variance = 0**: Saldo fisik kas klop / pas.
- **Variance > 0**: Kas Lebih (*Cash Over*).
- **Variance < 0**: Kas Kurang (*Cash Short* / Kasir tekor).

Data disimpan permanen di tabel `cashier_shift_closings` bersama seluruh rincian komponen laci.

---

## 6. Kunci Periode Pembukuan Bulanan (*Accounting Period Lock*)

Untuk menjaga integritas laporan Laba Rugi bulanan yang sudah disahkan oleh Owner:
1. Saat `BranchMonthlyClosing` berstatus `'completed'`, kelas `AccountingPeriodGuard` akan menolak segala bentuk mutasi baru, perubahan transaksi, pelunasan nota, atau pembatalan transaksi pada bulan tersebut dengan melemparkan `PeriodClosedException` (HTTP 422).
2. Jika perlu perbaikan nota susulan, Owner/Admin wajib memanggil method `FinancialService::reopenBranchClosing()`, yang mengubah status closing menjadi `'draft'` dan mencatat audit trail alasan pembukaan kembali.
