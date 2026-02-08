#!/bin/bash
# Script para probar los endpoints de la API
# Uso: ./scripts/test-api.sh
#      BASE_URL=http://localhost:8000 ./scripts/test-api.sh

BASE_URL="${BASE_URL:-http://localhost:8080}"

echo "=== Probando API en $BASE_URL ==="

# 1. Health Check
echo -e "\n1. Health Check"
curl -s "$BASE_URL/api/health" | jq .

# 2. Provider A
echo -e "\n2. Provider A (JSON)"
curl -s -X POST "$BASE_URL/api/provider-a/quote" \
  -H "Content-Type: application/json" \
  -d '{"driver_age":30,"car_form":"compact","car_use":"private"}' | jq .

# 3. Provider B
echo -e "\n3. Provider B (XML)"
curl -s -X POST "$BASE_URL/api/provider-b/quote" \
  -H "Content-Type: application/xml" \
  -d '<SolicitudCotizacion><EdadConductor>30</EdadConductor><TipoCoche>turismo</TipoCoche><UsoCoche>privado</UsoCoche><ConductorOcasional>NO</ConductorOcasional></SolicitudCotizacion>'

# 4. Provider C
echo -e "\n4. Provider C (CSV)"
curl -s -X POST "$BASE_URL/api/provider-c/quote" \
  -H "Content-Type: text/csv" \
  -d "driver_age,car_type,car_use
30,T,P"

# 5. Calculate
echo -e "\n5. Calculate (comparar presupuestos)"
curl -s -X POST "$BASE_URL/api/calculate" \
  -H "Content-Type: application/json" \
  -d '{"driver_age":30,"car_type":"turismo","car_use":"private"}' | jq .

echo -e "\n=== Fin pruebas ==="
