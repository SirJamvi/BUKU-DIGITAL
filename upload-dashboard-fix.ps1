$baseLocal = "E:\SYSTEM\BUKU-DIGITAL"
$ftpHost   = "ftp://ftp.resdigaza.my.id"
$username  = "dev-spb@spb.resdigaza.my.id"
$password  = "Javi3r123123123!"

$files = @(
    "resources/views/kasir/dashboard/index.blade.php",
    "app/Services/Kasir/DashboardService.php"
)

$credentials = New-Object System.Net.NetworkCredential($username, $password)
$wc = New-Object System.Net.WebClient
$wc.Credentials = $credentials

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "🚀 Mengunggah berkas perbaikan Dashboard ke Staging..." -ForegroundColor Cyan
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
Write-Host "Selesai!" -ForegroundColor Cyan
