# CHECK24 Insurance Quote Comparison API

A Symfony-based REST API for comparing car insurance quotes from multiple providers.

## Features

- **Multi-provider comparison**: Aggregates quotes from 3 insurance providers (A, B, C)
- **Campaign discount**: Automatic 5% discount when campaign is active
- **Parallel requests**: Fetches quotes from all providers simultaneously for optimal performance
- **Graceful degradation**: Continues to function even when some providers fail
- **OpenAPI documentation**: Interactive API documentation via Swagger UI
- **Comprehensive testing**: Unit and integration tests for all business logic

## Architecture

```
src/
├── Controller/Api/          # API endpoints (thin controllers)
├── DTO/                     # Data Transfer Objects
│   ├── Request/            # Input DTOs with validation
│   └── Response/           # Output DTOs
├── Enum/                   # Type-safe enumerations
├── Service/                # Business logic
│   ├── Calculator/         # Quote aggregation
│   ├── Campaign/          # Discount management
│   ├── Pricing/           # Provider pricing logic
│   └── Provider/          # Provider HTTP clients
├── Exception/              # Custom exceptions
└── EventSubscriber/        # Global error handling
```

## Requirements

- PHP 8.4+
- Composer
- Docker & Docker Compose (optional, for containerized setup)

## Quick Start

### Option 1: Docker (Recommended)

```bash
# Build and start the container
docker-compose up -d --build

# The API is now available at http://localhost:8080
```

### Option 2: Local Development

```bash
# Install dependencies
composer install

# Start the Symfony development server
symfony server:start

# Or use PHP's built-in server
php -S localhost:8080 -t public/
```

## API Endpoints

### Main Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/calculate` | Compare insurance quotes |
| GET | `/api/health` | Health check endpoint |
| GET | `/api/doc` | OpenAPI documentation (Swagger UI) |

### Provider Simulation Endpoints

| Method | Endpoint | Format | Latency | Error Rate |
|--------|----------|--------|---------|------------|
| POST | `/api/provider-a/quote` | JSON | ~2s | 10% |
| POST | `/api/provider-b/quote` | XML | ~5s | 1% timeout |
| POST | `/api/provider-c/quote` | CSV | ~3s | 5% |

## Usage Example

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

Or with driver_age directly:

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
  "message": null,
  "metadata": {
    "request_id": "calc_...",
    "elapsed_ms": 5234.12,
    "providers_queried": 3,
    "timestamp": "2024-01-15T10:30:00+00:00"
  }
}
```

## Configuration

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `CAMPAIGN_ACTIVE` | `true` | Enable/disable 5% discount campaign |
| `PROVIDER_A_URL` | `http://localhost:8080/api/provider-a/quote` | Provider A endpoint |
| `PROVIDER_B_URL` | `http://localhost:8080/api/provider-b/quote` | Provider B endpoint |
| `PROVIDER_C_URL` | `http://localhost:8080/api/provider-c/quote` | Provider C endpoint |
| `PROVIDER_TIMEOUT` | `10` | HTTP request timeout in seconds |

### Campaign Toggle

The campaign can be enabled/disabled via the `CAMPAIGN_ACTIVE` environment variable:

```bash
# Enable campaign (5% discount)
CAMPAIGN_ACTIVE=true

# Disable campaign (no discount)
CAMPAIGN_ACTIVE=false
```

## Testing

```bash
# Run all tests
./vendor/bin/phpunit

# Run only unit tests
./vendor/bin/phpunit --testsuite Unit

# Run with coverage report
./vendor/bin/phpunit --coverage-html coverage/
```

## Pricing Logic

### Provider A (JSON API)
- Base: €217
- Age: 18-24 (+€70), 25-55 (+€0), 56+ (+€90)
- Vehicle: SUV (+€100), Compact (+€10)
- Commercial: +15%

### Provider B (XML API)
- Base: €250
- Age: 18-29 (+€50), 30-59 (+€20), 60+ (+€100)
- Vehicle: Turismo (+€30), SUV (+€200), Compacto (+€0)
- Commercial: No adjustment

### Provider C (CSV API)
- Base: €195
- Age: 18-25 (+€80), 26-45 (+€10), 46-65 (+€30), 66+ (+€120)
- Vehicle: Turismo (+€25), SUV (+€150), Compacto (+€5)
- Commercial: +20%

## Design Decisions

### Why Environment Variables for Campaign?
- Simple for demo/coding challenge
- CI/CD friendly
- Per-environment configuration

**Production alternative**: Database-backed feature flags (e.g., LaunchDarkly) for runtime control, A/B testing, and user targeting.

### Why Separate Pricing Calculators?
- Single Responsibility Principle
- Easy to test pricing logic in isolation
- Easy to add new providers (Open/Closed Principle)

### Why DTOs?
- Type safety
- Validation at boundaries
- Clear API contracts
- IDE autocomplete support

## Future Improvements

With more time, the following could be added:

1. **Caching**: Redis/Memcached for provider responses
2. **Circuit Breaker**: Prevent cascading failures from unreliable providers
3. **Rate Limiting**: Protect against API abuse
4. **Async Processing**: Queue-based architecture for high load
5. **Database**: Persist quotes for analytics and history
6. **Monitoring**: APM integration (Datadog, New Relic)

## Author

CHECK24 Coding Challenge Submission
