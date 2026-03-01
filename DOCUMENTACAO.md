# Queiroz Prime CRM — Documentação

## Visão Geral
- Frontend: Next.js (app router) + React + TypeScript localizado em `frontend/`.
- Backend: Laravel 12 (PHP 8.2+) localizado em `backend/`.
- Objetivo: CRM para relojoaria com cadastros de clientes e produtos, pedidos, funil, fila de envios e dashboards.

## Arquitetura
- Front
  - Entrada: `frontend/src/app/page.tsx` monta o app `CrmApp`.
  - Features: `frontend/src/features/crm/*`
    - `CrmApp.tsx`: container principal (navegação e estados).
    - `views/*`: telas (Dashboard, Pedidos, Envios, Clientes, Produtos).
    - `ui/Primitives.tsx`: componentes base (Card, Badge, etc.).
    - `types.ts`: tipagens do domínio (Customer, Product, Order).
    - `helpers.ts`: formatação e cálculos (BRL, margem, lucro).
    - `data/mock.ts`: NAV e listas de referência (mantido apenas para metadados; dados vêm da API).
  - Carregamento de dados: `CrmApp` consome a API através de `fetch` em `NEXT_PUBLIC_API_BASE_URL` (padrão `http://localhost:8000/api`).

- Back
  - Rotas API: `backend/routes/api.php`
    - GET `/api/customers`, `/api/products`, `/api/orders`
  - Controladores: `backend/app/Http/Controllers/Api/*Controller.php`
  - Modelos: `backend/app/Models/{Customer,Product,Order}.php`
  - Migrations: `backend/database/migrations/*create_{customers,products,orders}_table.php`
  - Seeders: `backend/database/seeders/{CustomerSeeder,ProductSeeder,OrderSeeder,DatabaseSeeder}.php`
  - CORS: `backend/config/cors.php` autoriza `http://localhost:4001`.

## Modelos e Campos
- Customers
  - id, name, phone, email?, instagram?, timestamps
- Products
  - id, brand, model, cost (decimal), price (decimal), stock ['IN_STOCK','SUPPLIER'], qty (int), timestamps
- Orders
  - id, customer_id (FK), product_id (FK), product_name, channel, seller, status, sale_price, cost, discount, freight, channel_fee, payment_method?, shipping_method, tracking_code?, sale_date, shipped_date?, notes?, timestamps

## Endpoints
- GET `/api/customers` → lista de clientes
- POST `/api/customers` → cria cliente
- PUT `/api/customers/{id}` → atualiza cliente
- PATCH `/api/customers/{id}` → atualiza cliente (parcial)
- DELETE `/api/customers/{id}` → remove cliente
- GET `/api/products` → lista de produtos
- POST `/api/products` → cria produto
- PUT `/api/products/{id}` → atualiza produto
- PATCH `/api/products/{id}` → atualiza produto (parcial)
- DELETE `/api/products/{id}` → remove produto
- GET `/api/orders` → lista de pedidos

## Execução em Desenvolvimento
1. Backend
   - Requisitos: PHP 8.2+, Composer
   - Instalação (já executada na estruturação): `composer install`
   - DB SQLite criado em `database/database.sqlite` (migrações e seeds já configurados).
   - Rodar migrações e seeds: `php artisan migrate:fresh --seed`
   - Subir API: `php artisan serve` (http://localhost:8000)
2. Frontend
   - Requisitos: Node 18+
   - Variáveis: `NEXT_PUBLIC_API_BASE_URL` (opcional, default `http://localhost:8000/api`)
   - Subir dev server em porta alternativa: `npm run dev -- -p 4001` (http://localhost:4001)

## Execução com Docker
- Subir stack completa: `docker compose up --build`
- Frontend: http://localhost:4001
- Backend: http://localhost:8000/api
- MySQL:
  - Host: `localhost`
  - Porta: `3306`
  - Database: `queiroz_prime`
  - User: `queiroz`
  - Password: `secret`

## Próximos Passos
- Criar endpoints POST/PUT/PATCH para criação e atualização de pedidos.
- Autenticação e perfis de usuários (vendedores).
- Filtros avançados e exportação.
- Ajustar fila de envios para registrar postagens e status.

## Padrões
- Não versionar segredos.
- Tipos centralizados (frontend) em `types.ts`.
- Reutilizar `Primitives` para UI consistente.
