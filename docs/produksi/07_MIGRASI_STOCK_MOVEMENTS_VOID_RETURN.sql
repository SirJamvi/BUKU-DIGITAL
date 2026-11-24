-- DOKUMEN PRODUKSI #07: Menambahkan tipe void_return ke ENUM kolom type di tabel stock_movements
-- Jalankan query ini di phpMyAdmin atau MySQL Console jika migrasi belum dijalankan via artisan

ALTER TABLE `stock_movements` 
MODIFY COLUMN `type` ENUM('in','out','adjustment','waste','sale','transfer_out','transfer_in','void_return') NOT NULL;
