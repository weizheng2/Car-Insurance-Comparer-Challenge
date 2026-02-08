# API de Comparación de Cotizaciones de Seguros CHECK24

API REST basada en Symfony para comparar cotizaciones de seguros de coche de múltiples proveedores. Desarrollada como parte del CHECK24 Fullstack Code Challenge.

## Resumen del Caso Técnico

El backend recibe los datos del cliente desde el formulario del frontend, llama a las APIs de los proveedores (simulando aseguradoras externas), agrega y normaliza sus respuestas, aplica el descuento de campaña cuando está activa y devuelve las ofertas ordenadas.

**Requisitos principales:**
- Endpoint principal `POST /calculate` que agrega cotizaciones de todos los proveedores
- APIs mock de proveedores (cada uno con formato distinto: JSON, XML, CSV)
- Descuento de campaña del 5% cuando está activa
- Validar input, manejar errores, devolver resultados ordenados
- Tests automatizados: cálculos de precios por proveedor, lógica de comparación/ordenación, descuento de campaña

**Extras de perfil senior (implementados):**
- Peticiones paralelas a los proveedores
- Documentación OpenAPI/Swagger
- Manejo robusto de errores para proveedores no disponibles
- Logging con Monolog
- Tercer proveedor con formato distinto (CSV)
- Configuración Docker sencilla

---

## Enfoque de Implementación

Este fue mi primer proyecto con Symfony. Me centré en **buenas prácticas generales de código** más que en herramientas específicas del framework:

- **Principios SOLID** — servicios modulares, responsabilidad única
- **Código legible y testeable** — nombres claros, acoplamiento mínimo
- **Manejo explícito de errores** — desacoplado, respuestas consistentes
- **Evitar sobreingeniería** — soluciones simples, trade-offs razonables

Seguí la [documentación oficial de Symfony](https://symfony.com/doc) y las [Best Practices](https://symfony.com/doc/current/best_practices.html), y usé el [Symfony Demo](https://github.com/symfony/demo) como referencia de estructura.

---

## Arquitectura

```
src/
├── Controller/
│   ├── CalculateController.php      # Recibe request, valida, delega al servicio
│   └── Provider/
│       ├── ProviderASimulator.php   # API mock JSON (2s, 10% errores)
│       ├── ProviderBSimulator.php   # API mock XML (5s, 1% timeout)
│       └── ProviderCSimulator.php   # API mock CSV (3s, 5% errores)
├── DTO/
│   ├── Request/
│   │   └── QuoteRequest.php         # Validación de input, type safety
│   └── Response/
│       ├── CalculateResponse.php   # Estructura de respuesta agregada
│       └── Quote.php               # Cotización individual con datos de precio
├── Enum/
│   ├── CarType.php                 # turismo, suv, compacto
│   └── CarUse.php                  # private, commercial
├── Service/
│   ├── Campaign/
│   │   └── CampaignService.php      # Activar/desactivar descuento, aplicar 5%
│   ├── Provider/
│   │   ├── ProviderInterface.php   # Contrato para todos los proveedores
│   │   ├── ProviderAService.php    # Cliente HTTP + mapeo JSON
│   │   ├── ProviderBService.php    # Cliente HTTP + mapeo XML
│   │   └── ProviderCService.php    # Cliente HTTP + mapeo CSV
│   └── Quote/
│       └── QuoteComparisonService.php  # Orquesta proveedores, ordena, aplica campaña
├── HttpClient/
│   ├── InternalHttpClient.php      # Optimiza llamadas a localhost (sub-requests internas)
│   └── InternalResponse.php
├── Exception/
│   └── ProviderException.php       # Errores de proveedor estandarizados
└── EventSubscriber/
    └── ExceptionSubscriber.php    # Manejo global de errores API (JSON, logging)
```

### Flujo de Diseño

1. **Controller** — Recibe y valida el input, delega a `QuoteComparisonService`, devuelve JSON.
2. **QuoteComparisonService** — Llama a todos los servicios de proveedores en paralelo vía `HttpClient::stream()`, normaliza respuestas, aplica descuento de campaña, ordena por precio.
3. **Provider Services** — Cada uno implementa `ProviderInterface`: envía la petición en formato del proveedor, parsea la respuesta a nuestros DTOs.
4. **Provider Simulators** — Controladores separados que simulan APIs externas (latencia, errores aleatorios).

Cada proveedor gestiona su propio mapeo (formato request/response). Los DTOs compartidos garantizan contratos internos consistentes.

---

## Decisiones de Diseño

### Enums (CarType, CarUse)

- **Por qué:** Type safety, autocompletado en IDE, sin strings mágicos.
- **Trade-off:** Algo más de boilerplate a cambio de un modelo de dominio más claro.

### DTOs (QuoteRequest, Quote, CalculateResponse)

- **Por qué:** Validación centralizada en los límites, type safety, contratos de API claros.
- **Trade-off:** Clases extra a cambio de contratos explícitos y mantenimiento más fácil.

### Manejo de Errores (ExceptionSubscriber)

- Desacoplado de la lógica de negocio; un único punto para respuestas JSON consistentes.
- Registra con severidad apropiada; en producción oculta detalles internos.
- Patrón similar a middleware de .NET o manejadores de excepciones de Laravel.

### Campaña: Variable de Entorno

- **Por qué:** Simple para un demo, fácil de cambiar por entorno (dev/prod).
- **Alternativas consideradas:**
  - **Base de datos:** Flexible, control en runtime, pero añade dependencia de BD.
  - **Servicio externo (LaunchDarkly, Unleash):** A/B testing, segmentación, pero coste externo.
- **Para producción a escala:** Un sistema de feature flags en BD o externo permitiría A/B testing, segmentación geográfica/por usuario y campañas temporales sin redespliegues.

### Peticiones Paralelas a Proveedores

- Usa `HttpClient::stream()` de Symfony para peticiones concurrentes.
- Referencia: [Boosting performance with Symfony HttpClient and parallel requests](https://dev.to/victorprdh/boosting-performance-with-symfony-httpclient-and-parallel-requests-14g7)
- Timeout de 10 segundos por proveedor; los fallos no bloquean los resultados exitosos.

### Internal HTTP Client

- Cuando los proveedores apuntan a localhost, usa sub-requests internas en lugar de HTTP real (más rápido, sin red).
- Fallback a HTTP real para URLs externas.

### Sin Frontend

- Enfoque en calidad del backend y la API.
- OpenAPI/Swagger UI usado para mostrar y probar la API.

---

## Requisitos

- PHP 8.4+
- Composer
- Docker y Docker Compose (opcional)

---

## Inicio Rápido

### Opción 1: Docker (Recomendado)

```bash
cd coding-challenge
docker-compose up -d --build

# API disponible en http://localhost:8080
```

### Opción 2: Desarrollo Local

```bash
cd coding-challenge
composer install

# Iniciar servidor (el puerto 8080 debe coincidir con PROVIDER_*_URL en .env)
composer serve
# O: php -S localhost:8080 -t public/
```

> **Nota:** El endpoint `/calculate` llama internamente a las URLs de los proveedores. Asegúrate de que el servidor corre en el puerto configurado en `.env` (`PROVIDER_A_URL`, etc.).

---

## Probar la API

### 1. REST Client (VS Code / Cursor)

Abre `HttpTestScripts/test-api.http` y ejecuta cada petición con "Send Request".

### 2. PowerShell

```powershell
.\HttpTestScripts\test-api.ps1

# Otro puerto
.\HttpTestScripts\test-api.ps1 -BaseUrl "http://localhost:8000"
```

### 3. Shell (Linux / Mac)

```bash
chmod +x HttpTestScripts/test-api.sh
./HttpTestScripts/test-api.sh
```

### 4. curl manual

```bash
# Proveedor A
curl -X POST http://localhost:8080/api/provider-a/quote \
  -H "Content-Type: application/json" \
  -d '{"driver_age":30,"car_form":"compact","car_use":"private"}'

# Calculate (agrega todos los proveedores)
curl -X POST http://localhost:8080/api/calculate \
  -H "Content-Type: application/json" \
  -d '{"driver_age":30,"car_type":"turismo","car_use":"private"}'
```

---

## Endpoints de la API

### Endpoints Principales

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/api/calculate` | Compara cotizaciones de todos los proveedores |
| GET | `/api/doc` | Documentación OpenAPI (Swagger UI) |

### Endpoints de Simulación de Proveedores

| Método | Endpoint | Formato | Latencia | % Errores |
|--------|----------|---------|----------|-----------|
| POST | `/api/provider-a/quote` | JSON | ~2s | 10% |
| POST | `/api/provider-b/quote` | XML | ~5s | 1% timeout |
| POST | `/api/provider-c/quote` | CSV | ~3s | 5% |

---

## Ejemplo de Uso

### Request

```bash
curl -X POST http://localhost:8080/api/calculate \
  -H "Content-Type: application/json" \
  -d '{
    "driver_birthday": "1994-05-15",
    "car_type": "turismo",
    "car_use": "private"
  }'
```

O con `driver_age`:

```bash
curl -X POST http://localhost:8080/api/calculate \
  -H "Content-Type: application/json" \
  -d '{
    "driver_age": 30,
    "car_type": "turismo",
    "car_use": "private"
  }'
```

### Response

```json
{
  "success": true,
  "campaign_active": true,
  "discount_percentage": 5.0,
  "quotes": [
    {
      "provider": "provider-c",
      "provider_name": "Provider C",
      "original_price": 230.00,
      "final_price": 218.50,
      "discount_amount": 11.50,
      "has_discount": true,
      "currency": "EUR"
    },
    {
      "provider": "provider-a",
      "provider_name": "Provider A",
      "original_price": 227.00,
      "final_price": 215.65,
      "discount_amount": 11.35,
      "has_discount": true,
      "currency": "EUR"
    }
  ],
  "cheapest_provider": "provider-a",
  "failed_providers": [],
  "message": null
}
```

---

## Configuración

### Variables de Entorno

| Variable | Por defecto | Descripción |
|----------|-------------|-------------|
| `CAMPAIGN_ACTIVE` | `true` | Activar/desactivar descuento de campaña del 5% |
| `CAMPAIGN_DISCOUNT_RATE` | `0.05` | Tasa de descuento cuando la campaña está activa (0.05 = 5%) |
| `ENABLE_PROVIDER_ERRORS` | `true` | Activar latencia y errores aleatorios en simuladores (desactivar para tests) |
| `APP_INTERNAL_BASE_URL` | `http://localhost:8080` | URL base para sub-requests internas |
| `PROVIDER_A_URL` | `http://localhost:8080/api/provider-a/quote` | Endpoint proveedor A |
| `PROVIDER_B_URL` | `http://localhost:8080/api/provider-b/quote` | Endpoint proveedor B |
| `PROVIDER_C_URL` | `http://localhost:8080/api/provider-c/quote` | Endpoint proveedor C |
| `PROVIDER_TIMEOUT` | `10` | Timeout de peticiones HTTP en segundos |

### Toggle de Campaña

```bash
# Activar campaña (5% descuento)
CAMPAIGN_ACTIVE=true

# Desactivar campaña (sin descuento)
CAMPAIGN_ACTIVE=false
```

---

## Tests

```bash
# Ejecutar todos los tests
./vendor/bin/phpunit

# Solo tests unitarios
./vendor/bin/phpunit --testsuite Unit

# Solo tests de integración
./vendor/bin/phpunit --testsuite Integration

# Informe de cobertura
./vendor/bin/phpunit --coverage-html coverage/
```

**Cobertura de tests:**
- **Cálculos de precios por proveedor** — `ProviderPriceCalculationTest`
- **Lógica de comparación y ordenación** — `QuoteComparisonServiceTest`
- **Aplicación de descuento de campaña** — `CampaignServiceTest`
- **Endpoint Calculate** — `CalculateEndpointTest`

---

## Lógica de Precios

### Proveedor A (API JSON)
- Base: 217€
- Edad: 18-24 (+70€), 25-55 (+0€), 56+ (+90€)
- Vehículo: SUV (+100€), Compact (+10€) — Turismo y Compacto se mapean a compact
- Uso comercial: +15%

### Proveedor B (API XML)
- Base: 250€
- Edad: 18-29 (+50€), 30-59 (+20€), 60+ (+100€)
- Vehículo: Turismo (+30€), SUV (+200€), Compacto (+0€)
- Uso comercial: Sin ajuste

### Proveedor C (API CSV)
- Base: 195€
- Edad: 18-25 (+80€), 26-45 (+10€), 46-65 (+30€), 66+ (+120€)
- Vehículo: Turismo (+25€), SUV (+150€), Compacto (+5€)
- Uso comercial: +20%

---

## Mejoras Futuras

- **Caché:** Redis/Memcached para respuestas de proveedores
- **Circuit breaker:** Limitar impacto de proveedores poco fiables
- **Rate limiting:** Protección contra abuso
- **Procesamiento async:** Arquitectura basada en colas para alta carga
- **Base de datos:** Persistir cotizaciones para análisis
- **Monitoring:** APM (Datadog, New Relic)

---

## Autor

CHECK24 Coding Challenge Submission
