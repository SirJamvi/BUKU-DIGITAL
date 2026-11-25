<#
.SYNOPSIS
    Script untuk mengunggah sisa file Deposit ke Server Staging SPB BUKU-DIGITAL
    Jalankan setelah jeda rate-limit firewall cPanel (2-5 menit).
#>
$baseLocal = "E:\SYSTEM\BUKU-DIGITAL"
$ftpHost   = "ftp://ftp.resdigaza.my.id"
$username  = "dev-spb@spb.resdigaza.my.id"
$password  = "Javi3r123123123!"

$files = @(
    "database/migrations/2026_11_24_140000_add_deposit_system_tables_and_columns.php",
    "database/migrations/2026_11_24_140100_update_v_cashier_shift_drawer_summary_view_for_deposits.php",
    "app/Models/CustomerDepositLog.php",
    "app/Services/Accounting/CustomerDepositService.php",
    "app/Support/CashFlowPresentation.php",
    "app/Console/Commands/SeedStagingFinanceSimulationCommand.php"
)

$credentials = New-Object System.Net.NetworkCredential($username, $password)

# Buat direktori app/Services/Accounting jika belum ada
try {
    $dirReq = [System.Net.FtpWebRequest]::Create("$ftpHost/app/Services/Accounting")
    $dirReq.Credentials = $credentials
    $dirReq.Method = [System.Net.WebRequestMethods+Ftp]::MakeDirectory
    $dirReq.UsePassive = $true
    $dirReq.Timeout = 5000
    $resp = $dirReq.GetResponse()
    $resp.Close()
} catch {}

$wc = New-Object System.Net.WebClient
$wc.Credentials = $credentials

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "🚀 Mengunggah berkas deposit ke Staging BUKU-DIGITAL..." -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

foreach ($f in $files) {
    $localPath = Join-Path $baseLocal $f
    if (Test-Path $localPath) {
        $remoteUri = "$ftpHost/$f"
        try {
            Write-Host "Mengunggah: $f ... " -NoNewline
            $wc.UploadFile($remoteUri, $localPath)
            Write-Host "[BERHASIL]" -ForegroundColor Green
        } catch {
            Write-Host "[GAGAL: $_]" -ForegroundColor Red
        }
    }
}

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "Selesai!" -ForegroundColor Cyan
