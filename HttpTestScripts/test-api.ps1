# Script para probar los endpoints de la API
# Uso: .\scripts\test-api.ps1
#      .\scripts\test-api.ps1 -BaseUrl "http://localhost:8000"

param(
    [string]$BaseUrl = "http://localhost:8080"
)

Write-Host "=== Probando API en $BaseUrl ===" -ForegroundColor Cyan

# 1. Health Check
Write-Host "`n1. Health Check" -ForegroundColor Yellow
try {
    $health = Invoke-RestMethod -Uri "$BaseUrl/api/health" -Method Get
    $health | ConvertTo-Json
} catch {
    Write-Host "Error: $_" -ForegroundColor Red
}

# 2. Provider A (JSON) - ~2s de espera
Write-Host "`n2. Provider A (JSON) - puede tardar ~2s" -ForegroundColor Yellow
$bodyA = @{ driver_age = 30; car_form = "compact"; car_use = "private" } | ConvertTo-Json
try {
    $resultA = Invoke-RestMethod -Uri "$BaseUrl/api/provider-a/quote" -Method Post -Body $bodyA -ContentType "application/json"
    $resultA | ConvertTo-Json
} catch {
    Write-Host "Error: $_" -ForegroundColor Red
}

# 3. Provider B (XML) - ~5s de espera
Write-Host "`n3. Provider B (XML) - puede tardar ~5s" -ForegroundColor Yellow
$bodyB = @"
<SolicitudCotizacion>
  <EdadConductor>30</EdadConductor>
  <TipoCoche>turismo</TipoCoche>
  <UsoCoche>privado</UsoCoche>
  <ConductorOcasional>NO</ConductorOcasional>
</SolicitudCotizacion>
"@
try {
    $resultB = Invoke-WebRequest -Uri "$BaseUrl/api/provider-b/quote" -Method Post -Body $bodyB -ContentType "application/xml" -UseBasicParsing
    Write-Host $resultB.Content
} catch {
    Write-Host "Error: $_" -ForegroundColor Red
}

# 4. Provider C (CSV)
Write-Host "`n4. Provider C (CSV)" -ForegroundColor Yellow
$bodyC = "driver_age,car_type,car_use`n30,T,P"
try {
    $resultC = Invoke-WebRequest -Uri "$BaseUrl/api/provider-c/quote" -Method Post -Body $bodyC -ContentType "text/csv" -UseBasicParsing
    Write-Host $resultC.Content
} catch {
    Write-Host "Error: $_" -ForegroundColor Red
}

# 5. Calculate (llama a los 3 providers - puede tardar ~5-10s)
Write-Host "`n5. Calculate (comparar presupuestos) - puede tardar ~10s" -ForegroundColor Yellow
$bodyCalc = @{ driver_age = 30; car_type = "turismo"; car_use = "private" } | ConvertTo-Json
try {
    $resultCalc = Invoke-RestMethod -Uri "$BaseUrl/api/calculate" -Method Post -Body $bodyCalc -ContentType "application/json"
    $resultCalc | ConvertTo-Json -Depth 5
} catch {
    Write-Host "Error: $_" -ForegroundColor Red
}

Write-Host "`n=== Fin pruebas ===" -ForegroundColor Cyan
