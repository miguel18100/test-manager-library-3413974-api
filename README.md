# Manager Library · API

Backend de Manager Library en **PHP 8.3 con Laravel**. Implementa el contrato de
[`api-contract.md`](../manager-library-docs/spec-python/api-contract.md) y el modelo de
[`data-model.md`](../manager-library-docs/spec-python/data-model.md) sobre PostgreSQL 16.

## Arquitectura hexagonal

```
app/Domain        objetos de valor y agregados (Isbn, Quantity, DateRange, Book, Loan, Member, User)
app/Application   casos de uso (Services) y puertos de salida (Ports, solo interfaces)
app/Adapters      Persistence (PostgreSQL), Security (JWT, hash), Infrastructure (reloj, UUID, portadas)
app/Http          controlador y middleware JWT: traduce HTTP a casos de uso
app/Providers     AppServiceProvider: único punto donde los puertos se enlazan con los adaptadores
```

Regla de dependencia (artículos I–III): el dominio no importa nada de afuera, la aplicación no
importa adaptadores ni Laravel. Lo vigila `tests/Architecture`.

## Variables de entorno

| Variable | Uso |
|---|---|
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | PostgreSQL (esquema `library`) |
| `APP_KEY` | Clave de Laravel |
| `JWT_SECRET` | Firma de tokens (8 h). **Obligatoria**: sin ella la API no emite tokens |
| `ADMIN_USERNAME`, `ADMIN_PASSWORD` | Administrador inicial, creado por `php artisan library:bootstrap-admin` |
| `CORS_ORIGIN` | Único origen permitido (el frontend) |
| `COVERS_DIR` | Carpeta de portadas |
| `ALLOW_SIMULATED_TIME` | `false` por defecto. Solo para demostración: deja que el sembrador fije la hora de un préstamo |

## Base de datos

`php artisan migrate` aplica dos migraciones:

1. `…000001_create_library_schema`: seis tablas, claves foráneas, índices y las cinco categorías (T-02).
2. `…000002_add_domain_invariants`: `ck_book_total_positive` y `ck_user_role` (T-18).

El motor arranca vacío; todo el DDL vive aquí (ADR-001).

## Pruebas

```
composer install
php artisan test
```

- `tests/Unit`: objetos de valor, `Book`, `Loan`, `User`/`Member` y `LoanService` contra fakes (T-03 a T-10).
- `tests/Architecture`: reglas de dependencia entre capas (T-19).
- `tests/Contract`: las rutas expuestas son exactamente las del contrato y el cuerpo de error es único (T-16).

Las pruebas de integración contra PostgreSQL (T-12, T-13, T-18) y la prueba concurrente del último
ejemplar (RNF-2) requieren el motor levantado con `manager-library-infra`.
