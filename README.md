# Queiroz Prime CRM
CRM fullstack para relojoaria Queiroz Prime com cadastros, pedidos, envios e dashboards.

## Stack
- Frontend: Next.js (App Router) + React + TypeScript
- Backend: Laravel 12 (PHP 8.2+)
- Banco: MySQL (via Docker) ou SQLite local (desenvolvimento)

## Estrutura do Projeto
```
watch-crm/
├─ frontend/                  # Next.js (UI/CRM)
│  └─ src/features/crm/        # Core do CRM
│     ├─ CrmApp.tsx            # Container principal
│     ├─ views/                # Telas (Dashboard, Pedidos, Envios, Clientes, Produtos)
│     ├─ ui/                   # Componentes base
│     ├─ types.ts              # Tipos do domínio
│     └─ helpers.ts            # Cálculos e formatação
├─ backend/                    # Laravel API
│  ├─ app/Http/Controllers/Api # Controllers da API
│  ├─ app/Models               # Models Eloquent
│  ├─ database/migrations      # Migrations
│  └─ database/seeders         # Seeders
├─ docker-compose.yml          # Orquestração Docker (front/back/mysql)
├─ DOCUMENTACAO.md             # Documentação funcional e endpoints
└─ crm-relogios.jsx            # MVP base de referência
```

## Funcionalidades
- Clientes: cadastro e listagem
- Produtos/Estoque: cadastro e listagem
- Pedidos: listagem e detalhes
- Envios: fila de separação e status
- Dashboard: métricas de vendas e performance

## Endpoints Principais
- GET `/api/customers`
- POST `/api/customers`
- PUT `/api/customers/{id}`
- PATCH `/api/customers/{id}`
- DELETE `/api/customers/{id}`
- GET `/api/products`
- POST `/api/products`
- PUT `/api/products/{id}`
- PATCH `/api/products/{id}`
- DELETE `/api/products/{id}`
- GET `/api/orders`

## Rodar em Desenvolvimento (Local)
1. Backend
   - `cd backend`
   - `composer install`
   - `php artisan migrate:fresh --seed`
   - `php artisan serve` (http://localhost:8000)
2. Frontend
   - `cd frontend`
   - `npm install`
   - `npm run dev -- -p 4001` (http://localhost:4001)
3. Variável do frontend
   - `NEXT_PUBLIC_API_BASE_URL` (default: http://localhost:8000/api)

## Rodar com Docker
- `docker compose up --build`
- Frontend: http://localhost:4001
- Backend: http://localhost:8000/api
- MySQL:
  - Host: `localhost`
  - Porta: `3306`
  - Database: `queiroz_prime`
  - User: `queiroz`
  - Password: `secret`

## Observações
- CORS permite `http://localhost:4001`.
- Documentação completa em `DOCUMENTACAO.md`.
