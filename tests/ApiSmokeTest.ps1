<#
.SYNOPSIS
    Uji coba API backend POS (smoke test) terhadap server yang sedang berjalan.

.DESCRIPTION
    Menjalankan 61 request ke seluruh endpoint dan membandingkan status HTTP
    dengan ekspektasi. Hasil lengkap ditulis ke storage/hasil.txt.

.PREREQUISITE
    - MySQL (Laragon) menyala
    - php artisan migrate:fresh --seed --force
    - php artisan serve

.EXAMPLE
    powershell -NoProfile -ExecutionPolicy Bypass -File tests/ApiSmokeTest.ps1
#>

$ErrorActionPreference = 'Continue'
$base = 'http://127.0.0.1:8000/api/v1'
$out  = 'storage\hasil.txt'
$script:results = @()

function Call-Api {
    param([string]$Id, [string]$Method, [string]$Path, [string]$Body, [string]$Token, [int]$Expect)

    $headers = @{ 'Accept' = 'application/json' }
    if ($Token) { $headers['Authorization'] = "Bearer $Token" }

    $params = @{
        Uri         = "$base$Path"
        Method      = $Method
        Headers     = $headers
        ContentType = 'application/json'
    }
    if ($Body) { $params['Body'] = $Body }

    try {
        $resp = Invoke-WebRequest @params -UseBasicParsing
        $code = [int]$resp.StatusCode
        $text = $resp.Content
    } catch {
        $r = $_.Exception.Response

        if ($r -ne $null) {
            $code = [int]$r.StatusCode
            $stream = $r.GetResponseStream()
            $reader = New-Object System.IO.StreamReader($stream)
            $text = $reader.ReadToEnd()
            $reader.Close()
        } else {
            $code = 0
            $text = $_.Exception.Message
        }
    }

    $status = if ($code -eq $Expect) { 'PASS' } else { 'FAIL' }

    $script:results += [pscustomobject]@{
        Id = $Id; Status = $status; Method = $Method; Path = $Path
        Expect = $Expect; Actual = $code; Body = $text
    }
}

function Header($title) {
    $script:results += [pscustomobject]@{
        Id = ''; Status = ''; Method = ''; Path = "=== $title ==="
        Expect = ''; Actual = ''; Body = ''
    }
}

function Token-Of($id) {
    $row = $script:results | Where-Object { $_.Id -eq $id } | Select-Object -First 1
    if (-not $row) { return '' }
    try { return ($row.Body | ConvertFrom-Json).data.token } catch { return '' }
}

function Id-Of($id, $field) {
    $row = $script:results | Where-Object { $_.Id -eq $id } | Select-Object -First 1
    if (-not $row) { return '' }
    try { return ($row.Body | ConvertFrom-Json).data.$field } catch { return '' }
}

# ============ A. AUTENTIKASI ============
Header 'A. AUTENTIKASI'
Call-Api 'A1' 'GET'  '/health' '' '' 200
Call-Api 'A2' 'POST' '/auth/login' '{"email":"owner@pos.local","password":"password"}' '' 200
Call-Api 'A3' 'POST' '/auth/login' '{"email":"kasir@pos.local","password":"password"}' '' 200
Call-Api 'A4' 'POST' '/auth/login' '{"email":"gudang@pos.local","password":"password"}' '' 200
Call-Api 'A5' 'POST' '/auth/login' '{"email":"owner@pos.local","password":"salahsekali"}' '' 422
Call-Api 'A6' 'POST' '/auth/login' '{"email":""}' '' 422

$MT = Token-Of 'A2'
$KT = Token-Of 'A3'
$GT = Token-Of 'A4'

Call-Api 'A7'  'GET' '/auth/me' '' $MT 200
Call-Api 'A8'  'GET' '/auth/me' '' ''  401
Call-Api 'A9'  'GET' '/auth/me' '' $KT 200
Call-Api 'A10' 'GET' '/auth/me' '' $GT 200

# ============ B. STOK - BACA ============
Header 'B. STOK - BACA'
Call-Api 'B1' 'GET' '/stok' '' $MT 200
Call-Api 'B2' 'GET' '/stok?per_page=5' '' $MT 200
Call-Api 'B3' 'GET' '/stok?search=besi' '' $MT 200
Call-Api 'B4' 'GET' '/stok?tersedia=1' '' $MT 200
Call-Api 'B5' 'GET' '/stok/1' '' $MT 200
Call-Api 'B6' 'GET' '/stok/99999' '' $MT 404
Call-Api 'B7' 'GET' '/stok/1' '' ''  401
Call-Api 'B8' 'GET' '/stok/1' '' $KT 200
Call-Api 'B9' 'GET' '/stok/1' '' $GT 200

# ============ C. STOK - SCAN ============
Header 'C. STOK - SCAN'
Call-Api 'C1' 'GET' '/stok/scan?kode_sku=SEMEN' '' $MT 200
Call-Api 'C2' 'GET' '/stok/scan?kode_sku=TIDAK-ADA' '' $MT 404
Call-Api 'C3' 'GET' '/stok/scan' '' $MT 422

# ============ D. STOK - TULIS ============
Header 'D. STOK - TULIS (CRUD)'
Call-Api 'D1' 'POST' '/stok' '{"nama_produk":"Uji Semen Baru","harga":70000,"kuantitas":20}' $GT 201
$NID = Id-Of 'D1' 'id_produk'
Call-Api 'D2' 'POST' '/stok' '{"kode_sku":"SEMEN","nama_produk":"Duplikat SKU","harga":1000}' $GT 422
Call-Api 'D3' 'POST' '/stok' '{"harga":1000}' $GT 422
Call-Api 'D4' 'POST' '/stok' '{"nama_produk":"Harga Negatif","harga":-500}' $GT 422
Call-Api 'D5' 'PUT'  "/stok/$NID" '{"harga":75000,"kuantitas":25}' $GT 200
Call-Api 'D6' 'POST' '/stok' '{"nama_produk":"Kasir Tidak Boleh","harga":1000}' $KT 403

# ============ E. TRANSAKSI - CHECKOUT ============
Header 'E. TRANSAKSI - CHECKOUT'
Call-Api 'E1' 'POST' '/transaksi' '{"detail":[{"id_produk":1,"jumlah":2},{"id_produk":2,"jumlah":1}],"jenis_pembayaran":"TUNAI","jumlah_bayar":500000}' $KT 201
$T1 = Id-Of 'E1' 'id_transaksi'
Call-Api 'E2' 'POST' '/transaksi' '{"detail":[{"id_produk":4,"jumlah":3}],"jenis_pembayaran":"QRIS"}' $KT 201
Call-Api 'E3' 'POST' '/transaksi' '{"detail":[{"id_produk":5,"jumlah":1}],"jenis_pembayaran":"TRANSFER"}' $KT 201
Call-Api 'E4' 'POST' '/transaksi' '{"detail":[{"id_produk":1,"jumlah":99999}],"jenis_pembayaran":"TUNAI"}' $KT 422
Call-Api 'E5' 'POST' '/transaksi' '{"detail":[{"id_produk":1,"jumlah":1}],"jenis_pembayaran":"TUNAI","jumlah_bayar":1000}' $KT 422
Call-Api 'E6' 'POST' '/transaksi' '{"detail":[],"jenis_pembayaran":"TUNAI"}' $KT 422
Call-Api 'E7' 'POST' '/transaksi' '{"detail":[{"id_produk":1,"jumlah":0}],"jenis_pembayaran":"TUNAI"}' $KT 422
Call-Api 'E8' 'POST' '/transaksi' '{"detail":[{"id_produk":99999,"jumlah":1}],"jenis_pembayaran":"TUNAI"}' $KT 422
Call-Api 'E9' 'POST' '/transaksi' '{"detail":[{"id_produk":1,"jumlah":1}],"jenis_pembayaran":"KARTU_KREDIT"}' $KT 422
Call-Api 'E10' 'POST' '/transaksi' '{"detail":[{"id_produk":1,"jumlah":1},{"id_produk":1,"jumlah":2}],"jenis_pembayaran":"TUNAI"}' $KT 422
Call-Api 'E11' 'POST' '/transaksi' '{"detail":[{"id_produk":1,"jumlah":1}],"jenis_pembayaran":"TUNAI","jumlah_bayar":100000}' $GT 403
Call-Api 'E12' 'POST' '/transaksi' '{"detail":[{"id_produk":1,"jumlah":1}],"jenis_pembayaran":"TUNAI"}' '' 401

# ============ F. TRANSAKSI - BACA ============
Header 'F. TRANSAKSI - BACA'
Call-Api 'F1' 'GET' '/transaksi' '' $MT 200
Call-Api 'F2' 'GET' "/transaksi/$T1" '' $MT 200
Call-Api 'F3' 'GET' "/transaksi/$T1/struk" '' $KT 200
Call-Api 'F4' 'GET' '/transaksi/99999' '' $MT 404
Call-Api 'F5' 'GET' '/transaksi?dari=2020-01-01&sampai=2020-12-31' '' $MT 200
Call-Api 'F6' 'GET' '/transaksi?jenis_pembayaran=QRIS' '' $MT 200
Call-Api 'F7' 'GET' '/transaksi?id_pengguna=2' '' $MT 200
Call-Api 'F8' 'GET' '/transaksi?per_page=2' '' $MT 200

# ============ G. PEMBATALAN ============
Header 'G. PEMBATALAN & NOMOR STRUK'
Call-Api 'G1' 'DELETE' "/transaksi/$T1" '' $KT 403
Call-Api 'G2' 'DELETE' "/transaksi/$T1" '' $MT 200
Call-Api 'G3' 'GET' '/stok/1' '' $MT 200
Call-Api 'G4' 'POST' '/transaksi' '{"detail":[{"id_produk":1,"jumlah":1}],"jenis_pembayaran":"TUNAI","jumlah_bayar":65000}' $KT 201
Call-Api 'G5' 'GET' "/transaksi/$T1" '' $MT 404

# ============ H. HAPUS STOK ============
Header 'H. HAPUS STOK'
Call-Api 'H1' 'DELETE' '/stok/1' '' $MT 409
Call-Api 'H2' 'DELETE' "/stok/$NID" '' $GT 200
Call-Api 'H3' 'GET' "/stok/$NID" '' $MT 404
Call-Api 'H4' 'DELETE' '/stok/1' '' $KT 403

# ============ I. GANTI PASSWORD ============
Header 'I. GANTI PASSWORD'
Call-Api 'I1' 'PATCH' '/auth/password' '{"current_password":"salah","password":"newpass123","password_confirmation":"newpass123"}' $KT 422
Call-Api 'I2' 'PATCH' '/auth/password' '{"current_password":"password","password":"newpass123","password_confirmation":"beda"}' $KT 422

# ============ J. LOGOUT ============
Header 'J. LOGOUT'
Call-Api 'J1' 'POST' '/auth/logout' '' $MT 200
Call-Api 'J2' 'GET' '/auth/me' '' $MT 401

# ============ TULIS HASIL ============
$lines = @()

foreach ($r in $script:results) {
    if ($r.Id -eq '') {
        $lines += ''
        $lines += $r.Path
        continue
    }
    $lines += ''
    $lines += "[$($r.Id)] $($r.Status) $($r.Method) $($r.Path) -- expected $($r.Expect), got $($r.Actual)"
    $lines += $r.Body
}

$total  = ($script:results | Where-Object { $_.Id -ne '' }).Count
$passed = ($script:results | Where-Object { $_.Status -eq 'PASS' }).Count
$failed = ($script:results | Where-Object { $_.Status -eq 'FAIL' }).Count
$lines += ''
$lines += "TOTAL=$total PASS=$passed FAIL=$failed"

$lines | Set-Content -Path $out -Encoding UTF8

Write-Host "TOTAL=$total PASS=$passed FAIL=$failed"
$script:results | Where-Object { $_.Status -eq 'FAIL' } | ForEach-Object {
    Write-Host "FAIL: $($_.Id) $($_.Method) $($_.Path) expected=$($_.Expect) got=$($_.Actual)"
}
