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
    - `components/*`: shell do app, sidebar, background, toasts, modal e tema.
    - `views/*`: telas (Dashboard, Pedidos, Envios, Clientes, Produtos).
    - `ui/Primitives.tsx`: componentes base (Card, Badge, Input, Select, Btn).
    - `types.ts`: tipagens do domínio (Customer, Product, Order).
    - `helpers.ts`: formatação e cálculos (BRL, margem, lucro).
    - `data/mock.ts`: NAV e listas de referência (mantido apenas para metadados; dados vêm da API).
  - Carregamento de dados: `CrmApp` consome a API via `fetch` em `NEXT_PUBLIC_API_BASE_URL` (padrão `http://localhost:8000/api`) para clientes, produtos, pedidos, marcas, modelos e qualidades.

- Back
  - Rotas API: `backend/routes/api.php`
    - GET `/api/customers`, `/api/products`, `/api/orders`
  - Controladores: `backend/app/Http/Controllers/Api/*Controller.php`
  - Modelos: `backend/app/Models/{Customer,Product,Order}.php`
  - Migrations: `backend/database/migrations/*create_{customers,products,orders}_table.php`
  - Seeders: `backend/database/seeders/{CustomerSeeder,ProductSeeder,OrderSeeder,DatabaseSeeder}.php`
  - CORS: `backend/config/cors.php` autoriza `http://localhost:4001`.

## Frontend — Arquitetura Detalhada
### Estrutura de Pastas (frontend/)
```
frontend/
├─ public/                     # Assets estáticos (svg, imagens, favicon)
├─ src/
│  ├─ app/                      # Next.js App Router
│  │  ├─ layout.tsx             # Layout raiz + fontes + script de tema
│  │  ├─ globals.css            # Tokens de design e reset global
│  │  ├─ page.tsx               # Entry point que monta o CRM
│  │  ├─ page.module.css        # CSS módulo default do Next (não usado no CRM)
│  │  └─ favicon.ico
│  └─ features/
│     └─ crm/
│        ├─ CrmApp.tsx          # Container principal, navegação e modais
│        ├─ components/         # Shell, background, sidebar, toasts, modal, tema
│        ├─ data/
│        │  └─ mock.ts          # Constantes de domínio e NAV
│        ├─ ui/
│        │  └─ Primitives.tsx   # Componentes base (Card, Badge, Input, Btn)
│        ├─ views/              # Telas e formulários
│        │  ├─ Dashboard.tsx
│        │  ├─ OrderList.tsx
│        │  ├─ OrderDetail.tsx
│        │  ├─ ShippingQueue.tsx
│        │  ├─ Customers.tsx
│        │  ├─ Products.tsx
│        │  ├─ Models.tsx
│        │  ├─ Brands.tsx
│        │  ├─ Settings.tsx
│        │  ├─ NewOrderForm.tsx
│        │  ├─ NewProductForm.tsx
│        │  ├─ NewCustomerForm.tsx
│        │  ├─ NewModelForm.tsx
│        │  └─ NewBrandForm.tsx
│        ├─ helpers.ts          # Cálculos e formatação (BRL, margem, datas)
│        └─ types.ts            # Tipos do domínio (Order, Product, Customer)
├─ next.config.ts
├─ package.json
└─ tsconfig.json
```

### Fluxo de Renderização
- `page.tsx` apenas retorna `<CrmApp />` e concentra a UI no módulo de CRM.  
  Referência: [page.tsx](file:///Users/victorhugo/Documents/watch-crm/frontend/src/app/page.tsx)
- `CrmApp.tsx` controla:
  - Navegação por estado (`page`) usando `NAV` de `data/mock.ts`.
  - Carregamento de dados com `fetch` na API (`NEXT_PUBLIC_API_BASE_URL`).
  - Modais de criação e detalhe (Pedidos, Produtos, Clientes, Modelos).
  - Sistema de toasts para feedback visual.
  Referência: [CrmApp.tsx](file:///Users/victorhugo/Documents/watch-crm/frontend/src/features/crm/CrmApp.tsx)
- Cada tela está isolada em `views/*`, consumindo props simples e usando os componentes base de `ui/Primitives.tsx`.
  Referência: [views](file:///Users/victorhugo/Documents/watch-crm/frontend/src/features/crm/views)

### Base de Referência (MVP)
- O layout original e a lógica de MVP vieram de `crm-relogios.jsx`, que foi modularizado e tipado no diretório `features/crm`.
  Referência: [crm-relogios.jsx](file:///Users/victorhugo/Documents/watch-crm/crm-relogios.jsx)
- Arquivos de inspiração visual e animações:
  - [design-system.html](file:///Users/victorhugo/Documents/watch-crm/fluxora.aura.build/design-system.html)
  - [index.html](file:///Users/victorhugo/Documents/watch-crm/fluxora.aura.build/index.html)
  - [predictive-analytics-feature-grid-section.html](file:///Users/victorhugo/Documents/watch-crm/fluxora.aura.build/predictive-analytics-feature-grid-section.html)

## Frontend — Estilização e UI
### Tokens e Temas
- As cores e tokens de UI ficam centralizados em `globals.css` via CSS variables `--crm-*`.
  Referência: [globals.css](file:///Users/victorhugo/Documents/watch-crm/frontend/src/app/globals.css)
- Tema claro/escuro funciona por `data-theme` no `<html>`:
  - `:root` define o tema claro.
  - `:root[data-theme="dark"]` define o tema escuro.
  - `:root[data-theme="system"]` respeita `prefers-color-scheme`.
- O tema é aplicado cedo no `layout.tsx` com um script inline, evitando flash de tema.
  Referência: [layout.tsx](file:///Users/victorhugo/Documents/watch-crm/frontend/src/app/layout.tsx)

### Estratégia de Estilo
- A UI usa CSS Modules por view e por componente para manter consistência visual com tokens `--crm-*`.
- Estilos inline ficaram apenas para valores dinâmicos (ex.: cores por status, delays de animação, barras proporcionais).
- `ui/Primitives.tsx` concentra os padrões de UI:
  - `Card` com vidro fosco (blur), bordas suaves e sombra.
  - `Badge` e `StatCard` com cores derivadas do status e paleta.
  - `Input`, `Select` e `Btn` com tokens de borda, fundo e tipografia.  
  Referência: [Primitives.tsx](file:///Users/victorhugo/Documents/watch-crm/frontend/src/features/crm/ui/Primitives.tsx)
- Animações são declaradas em `globals.css`:
  - `.crm-animate-width` para barras (crescimento lateral).
  - `.crm-animate-fade` para entradas suaves em listas/cards.  
  Referência: [globals.css](file:///Users/victorhugo/Documents/watch-crm/frontend/src/app/globals.css)

### Fundo e Grid do CRM
- O fundo do app é construído com camadas:
  - Cor base (`--crm-bg`).
  - Gradientes suaves (`--crm-bg-gradient`).
  - Grid com baixa opacidade para textura sutil.  
  Implementado no componente `Background`.
  Referência: [Background.tsx](file:///Users/victorhugo/Documents/watch-crm/frontend/src/features/crm/components/Background/Background.tsx)

### Tipografia
- A fonte principal aplicada ao CRM é `Inter`, carregada via `next/font` e exposta como `--font-inter`.
- O layout global também define variáveis de fonte `Geist`, mantendo compatibilidade com outras páginas.
  Referência: [layout.tsx](file:///Users/victorhugo/Documents/watch-crm/frontend/src/app/layout.tsx)

### Componentização e Consistência
- Telas reutilizam `Card`, `StatCard`, `Badge`, `Input`, `Select` e `Btn` para manter ritmo visual e hierarquia.
- `AppShell`, `Sidebar` e `Background` consolidam o layout principal e o tema visual.
- `Modal` e `Toasts` padronizam interações e feedbacks de usuário.
- Status de pedidos usam cores centralizadas em `data/mock.ts` (`STATUS_COLORS`) para garantir consistência.
  Referências: [Primitives.tsx](file:///Users/victorhugo/Documents/watch-crm/frontend/src/features/crm/ui/Primitives.tsx), [mock.ts](file:///Users/victorhugo/Documents/watch-crm/frontend/src/features/crm/data/mock.ts), [Sidebar.tsx](file:///Users/victorhugo/Documents/watch-crm/frontend/src/features/crm/components/Sidebar/Sidebar.tsx)

## Backend — Arquitetura Detalhada
### Estrutura Principal (backend/)
- Rotas: `backend/routes/api.php` centraliza os endpoints do CRM.
- Controladores: `backend/app/Http/Controllers/Api/*Controller.php`.
- Modelos Eloquent: `backend/app/Models/*`.
- Migrations: `backend/database/migrations/*`.
- Seeders: `backend/database/seeders/*`.

### Rotas e Controladores
- Customers: `CustomerController` (index, store, update, destroy).
- Products: `ProductController` (index, store, update, destroy).
- Brands: `BrandController` (index, store, update, destroy).
- Models: `ModelController` (index, store, update, destroy).
- Qualities: `QualityController` (index, store, update, destroy).
- Orders: `OrderController` (index).
Referência: [api.php](file:///Users/victorhugo/Documents/watch-crm/backend/routes/api.php)

### Modelos e Relações
- Customer → hasMany Orders.
- Order → belongsTo Customer e Product.
- Product → belongsTo Brand e WatchModel; hasMany Orders.
- Brand → hasMany WatchModels.
- WatchModel → belongsTo Brand e Quality.
- Quality → hasMany WatchModels.
Referência: [Models](file:///Users/victorhugo/Documents/watch-crm/backend/app/Models)

### Validações de Entrada
- Customers: nome/telefone obrigatórios; email e instagram opcionais.
- Products: valida brandId/modelId com relação de marca, custos numéricos, estoque e quantidade.
- Brands/Qualities: nome único com validação de update.
- Models: nome único por marca+qualidade, qualidade obrigatória, upload de imagem opcional.
Referências: [CustomerController](file:///Users/victorhugo/Documents/watch-crm/backend/app/Http/Controllers/Api/CustomerController.php), [ProductController](file:///Users/victorhugo/Documents/watch-crm/backend/app/Http/Controllers/Api/ProductController.php), [ModelController](file:///Users/victorhugo/Documents/watch-crm/backend/app/Http/Controllers/Api/ModelController.php)

### Uploads e Storage
- Modelos aceitam `image` (jpg/jpeg/png) até 2MB.
- Upload salva em `storage/app/public/models` e retorna `imageUrl` usando `Storage::url`.
- É necessário `php artisan storage:link` para servir imagens via `/storage`.
Referência: [ModelController](file:///Users/victorhugo/Documents/watch-crm/backend/app/Http/Controllers/Api/ModelController.php), [filesystems.php](file:///Users/victorhugo/Documents/watch-crm/backend/config/filesystems.php)

### Banco e Seeds
- Migrations criam tabelas de customers, products, orders, brands, models e qualities.
- Migrações de evolução ajustam FKs de product e a relação de quality nos models.
- Seeders populam clientes, marcas, qualidades, modelos, produtos e pedidos.
Referências: [migrations](file:///Users/victorhugo/Documents/watch-crm/backend/database/migrations), [seeders](file:///Users/victorhugo/Documents/watch-crm/backend/database/seeders)

### CORS
- Origem liberada para `http://localhost:4001` e `http://127.0.0.1:4001`.
Referência: [cors.php](file:///Users/victorhugo/Documents/watch-crm/backend/config/cors.php)

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
- GET `/api/brands` → lista de marcas
- POST `/api/brands` → cria marca
- PUT `/api/brands/{id}` → atualiza marca
- PATCH `/api/brands/{id}` → atualiza marca (parcial)
- DELETE `/api/brands/{id}` → remove marca
- GET `/api/models` → lista de modelos
- POST `/api/models` → cria modelo
- PUT `/api/models/{id}` → atualiza modelo
- PATCH `/api/models/{id}` → atualiza modelo (parcial)
- DELETE `/api/models/{id}` → remove modelo
- GET `/api/qualities` → lista de qualidades
- POST `/api/qualities` → cria qualidade
- PUT `/api/qualities/{id}` → atualiza qualidade
- PATCH `/api/qualities/{id}` → atualiza qualidade (parcial)
- DELETE `/api/qualities/{id}` → remove qualidade

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
