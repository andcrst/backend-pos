$base = 'http://127.0.0.1:8000/api/v1'
$lines = @()

function Tok($email) {
    $r = Invoke-WebRequest -Uri "$base/auth/login" -Method POST -UseBasicParsing `
        -ContentType 'application/json' -Headers @{ Accept = 'application/json' } `
        -Body "{`"email`":`"$email`",`"password`":`"password`"}"
    return ($r.Content | ConvertFrom-Json).data.token
}

function Req($id, $method, $path, $token, $expect, $body) {
    $args = @{ Uri = "$base$path"; Method = $method; UseBasicParsing = $true
               Headers = @{ Accept = 'application/json' } }
    if ($token) { $args.Headers.Authorization = "Bearer $token" }
    if ($body) { $args.ContentType = 'application/json'; $args.Body = $body }
    try {
        $r = Invoke-WebRequest @args
        $code = [int]$r.StatusCode; $res = $r.Content
    } catch {
        $resp = $_.Exception.Response
        if ($resp) {
            $code = [int]$resp.StatusCode
            $rd = New-Object System.IO.StreamReader($resp.GetResponseStream())
            $res = $rd.ReadToEnd(); $rd.Close()
        } else { $code = 0; $res = $_.Exception.Message }
    }
    $st = if ($code -eq $expect) { 'PASS' } else { 'FAIL' }
    $script:lines += "[$id] $st $method $path -- expect $expect got $code"
    $script:lines += $res
}

$mt = Tok 'owner@pos.local'
$kt = Tok 'kasir@pos.local'
$gt = Tok 'gudang@pos.local'

Req 'K1' 'GET' '/kategori' $mt 200 $null
Req 'K2' 'GET' '/kategori?search=pipa' $mt 200 $null
Req 'K3' 'POST' '/kategori' $mt 201 '{"nama_kategori":"Uji Coba"}'
Req 'K4' 'POST' '/kategori' $mt 422 '{"nama_kategori":"Pipa"}'
Req 'K5' 'GET' '/stok?id_kategori=6' $mt 200 $null
Req 'K6' 'GET' '/stok?search=besi%204%20mm' $mt 200 $null
Req 'K7' 'POST' '/kategori' $kt 403 '{"nama_kategori":"Dari Kasir"}'
Req 'K8' 'GET' '/kategori' '' 401 $null

# hapus kategori uji coba
$all = (Invoke-WebRequest -Uri "$base/kategori?search=Uji%20Coba" -UseBasicParsing `
        -Headers @{ Authorization = "Bearer $mt" }).Content | ConvertFrom-Json
$idUji = $all.data[0].id_kategori
Req 'K9' 'DELETE' "/kategori/$idUji" $gt 200 $null

# kategori yang masih dipakai produk
Req 'K10' 'DELETE' '/kategori/1' $gt 409 $null

$script:lines | Set-Content -Path 'storage\uji-kategori.txt' -Encoding UTF8

$total  = ($script:lines | Where-Object { $_ -match '^\[K\d+\]' }).Count
$passed = ($script:lines | Where-Object { $_ -match '^\[K\d+\] PASS' }).Count
$failed = ($script:lines | Where-Object { $_ -match '^\[K\d+\] FAIL' }).Count
Write-Host "TOTAL=$total PASS=$passed FAIL=$failed"
