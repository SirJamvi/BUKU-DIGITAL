<?php

namespace App\Services;

use App\Jobs\SendNotification;
use App\Models\Transaction;
use App\Models\Product;
use App\Models\User;
use App\Notifications\LowStockAlert;
use App\Notifications\NewTransaction;
use App\Mail\TransactionReceipt;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Mengirim notifikasi tentang transaksi baru ke semua admin.
     *
     * @param Transaction $transaction
     * @return void
     */
    public function sendNewTransactionNotification(Transaction $transaction): void
    {
        $admins = User::where('role', 'admin')->where('is_active', true)->get();

        if ($admins->isNotEmpty()) {
            SendNotification::dispatch($admins, new NewTransaction($transaction));
        }
    }

    /**
     * Mengirim notifikasi stok rendah ke semua admin.
     *
     * @param Product $product
     * @return void
     */
    public function sendLowStockAlert(Product $product): void
    {
        $admins = User::where('role', 'admin')->where('is_active', true)->get();

        if ($admins->isNotEmpty()) {
            SendNotification::dispatch($admins, new LowStockAlert($product));
        }
    }

    /**
     * Mengirim struk transaksi melalui email kepada pelanggan.
     *
     * @param Transaction $transaction
     * @return void
     */
    public function sendTransactionReceipt(Transaction $transaction): void
    {
        if ($transaction->customer && $transaction->customer->email) {
            Mail::to($transaction->customer->email)->queue(new TransactionReceipt($transaction));
        }
    }
}