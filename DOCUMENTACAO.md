# Queiroz Prime CRM — Documentação

## Visão Geral
- Frontend: Next.js (App Router) + React + TypeScript em `frontend/`.
- Backend: Laravel 12 (PHP 8.2+) em `backend/`.
- Objetivo: CRM para relojoaria com cadastros, catálogo, pedidos, envios e dashboards.
- Documentação específica de login e autorização: `docs/login-e-autorizacao.md`.

## Arquitetura
- Front
  - Entrada: `frontend/src/app/page.tsx` monta o app `CrmApp`.
  - Features: `frontend/src/features/crm/*`
    - `CrmApp.tsx`: container principal, sessão autenticada, navegação e modais.
    - `components/*`: shell do app, sidebar, background, toasts, modal e tema.
    - `views/*`: telas (Dashboard, Pedidos, Envios, Clientes, Produtos, Modelos, Configurações e Login).
    - `ui/Primitives.tsx`: componentes base (Card, Badge, Input, Select, Btn).
    - `types.ts`: tipagens do domínio, usuário autenticado e permissões.
    - `api.ts`: utilitários de sessão, CSRF e chamadas autenticadas.
    - `helpers.ts`: formatação e cálculos (BRL, margem, lucro, próxima postagem).
    - `data/mock.ts`: NAV e listas de referência (canais, vendedores, métodos de envio/pagamento).
  - Carregamento de dados: `CrmApp` primeiro consulta `GET /api/me`; se autenticado, consome a API em `NEXT_PUBLIC_API_BASE_URL` (padrão `http://localhost:8000/api`) para clientes, produtos, pedidos, marcas, modelos e qualidades. A criação de pedidos ainda é local no frontend.

- Back
  - Rotas API: `backend/routes/api.php`
    - Endpoints de autenticação: `csrf-cookie`, `login`, `logout`, `me`, `forgot-password`, `reset-password`
    - Endpoints do CRM protegidos por `auth` + `permission:*`
  - Controladores: `backend/app/Http/Controllers/Api/*Controller.php`
  - Modelos: `backend/app/Models/{User,Customer,Product,Order,Brand,WatchModel,Quality,AuditLog}.php`
  - Migrations: `backend/database/migrations/*`
  - Seeders: `backend/database/seeders/*Seeder.php`
  - CORS: `backend/config/cors.php` autoriza `http://localhost:4001` com credenciais.

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
│        ├─ CrmApp.tsx          # Container principal, sessão, navegação e modais
│        ├─ api.ts              # Helpers para CSRF, cookies e fetch autenticado
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
│        │  ├─ LoginView.tsx
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
  - Estado de sessão (`unknown`, `authenticated`, `unauthenticated`).
  - Bootstrap do usuário por `GET /api/me`.
  - Navegação por estado (`page`) usando `NAV` de `data/mock.ts`.
  - Carregamento autenticado dos dados com `fetch` na API (`NEXT_PUBLIC_API_BASE_URL`).
  - Modais de criação e detalhe (Pedidos, Produtos, Clientes, Modelos).
  - Sistema de toasts para feedback visual.
  Referência: [CrmApp.tsx](file:///Users/victorhugo/Documents/watch-crm/frontend/src/features/crm/CrmApp.tsx)
- Cada tela está isolada em `views/*`, consumindo props simples e usando os componentes base de `ui/Primitives.tsx`.
  Referência: [views](file:///Users/victorhugo/Documents/watch-crm/frontend/src/features/crm/views)
  - A tela de Configurações centraliza cadastros de Marcas e Qualidades.
  - A tela `LoginView` concentra a autenticação inicial da aplicação.

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
- A `Sidebar` agora também exibe o usuário autenticado e a ação de logout.
- Status de pedidos usam cores centralizadas em `data/mock.ts` (`STATUS_COLORS`) para garantir consistência.
  Referências: [Primitives.tsx](file:///Users/victorhugo/Documents/watch-crm/frontend/src/features/crm/ui/Primitives.tsx), [mock.ts](file:///Users/victorhugo/Documents/watch-crm/frontend/src/features/crm/data/mock.ts), [Sidebar.tsx](file:///Users/victorhugo/Documents/watch-crm/frontend/src/features/crm/components/Sidebar/Sidebar.tsx)

### Regras de Envios
- A sugestão de próxima postagem segue o calendário fixo de envios: segunda, quarta e sexta.
  Referência: [helpers.ts](file:///Users/victorhugo/Documents/watch-crm/frontend/src/features/crm/helpers.ts)

## Backend — Arquitetura Detalhada
### Estrutura Principal (backend/)
- Rotas: `backend/routes/api.php` centraliza os endpoints do CRM.
- Controladores: `backend/app/Http/Controllers/Api/*Controller.php`.
- Modelos Eloquent: `backend/app/Models/*`.
- Policies: `backend/app/Policies/*`.
- Middleware customizado: `backend/app/Http/Middleware/EnsureUserHasPermission.php`.
- Suporte a permissões/auditoria: `backend/app/Support/*`.
- Migrations: `backend/database/migrations/*`.
- Seeders: `backend/database/seeders/*`.

### Rotas e Controladores
- Auth: `AuthController` (`csrfCookie`, `login`, `logout`, `me`, `forgotPassword`, `resetPassword`).
- Customers: `CustomerController` (index, store, update, destroy).
- Products: `ProductController` (index, store, update, destroy).
- Brands: `BrandController` (index, store, update, destroy).
- Models: `ModelController` (index, store, update, destroy).
- Qualities: `QualityController` (index, store, update, destroy).
- Orders: `OrderController` (index).
Referência: [api.php](file:///Users/victorhugo/Documents/watch-crm/backend/routes/api.php)

### Modelos e Relações
- User → hasMany Orders criados; hasMany Customers sob ownership.
- Customer → hasMany Orders; belongsTo owner (`owner_user_id`).
- Order → belongsTo Customer, Product e creator (`created_by_user_id`).
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
- Migrations de autenticação adicionam campos de usuário, ownership, creator e `audit_logs`.
- Seeders populam usuários, clientes, marcas, qualidades, modelos, produtos e pedidos.
Referências: [migrations](file:///Users/victorhugo/Documents/watch-crm/backend/database/migrations), [seeders](file:///Users/victorhugo/Documents/watch-crm/backend/database/seeders)

### CORS
- Origem liberada para `http://localhost:4001` e `http://127.0.0.1:4001`.
- `supports_credentials` está habilitado para permitir sessão por cookie entre frontend e backend.
Referência: [cors.php](file:///Users/victorhugo/Documents/watch-crm/backend/config/cors.php)

### Autenticação e Autorização
- O backend usa autenticação stateful com Laravel Session.
- As rotas de autenticação vivem dentro do middleware `web`.
- As rotas de negócio do CRM vivem dentro de `auth`.
- Cada rota protegida declara também a habilidade exigida com `permission:<ability>`.
- Policies aplicam ownership por registro para clientes e pedidos.
- O payload de `GET /api/me` retorna dados mínimos do usuário, papel e permissões efetivas.

### Auditoria
- Ações sensíveis são registradas em `audit_logs`.
- Eventos auditados incluem login, falha de login, logout, reset de senha e CRUD crítico.

## Modelos e Campos
- Users
  - id, name, email, password, role, is_active, last_login_at?, two_factor_secret?, two_factor_confirmed_at?, timestamps
- Customers
  - id, name, phone, email?, instagram?, owner_user_id?, timestamps
- Brands
  - id, name, timestamps
- Qualities
  - id, name, timestamps
- Models
  - id, brand_id (FK), quality_id (FK), name, image_path?, timestamps
- Products
  - id, brand_id (FK), model_id (FK), cost (decimal), price (decimal), stock ['IN_STOCK','SUPPLIER'], qty (int), timestamps
- Orders
  - id, customer_id (FK), created_by_user_id? (FK), product_id (FK), product_name, channel, seller, status, sale_price, cost, discount, freight, channel_fee, payment_method?, shipping_method, tracking_code?, sale_date, shipped_date?, notes?, timestamps
- AuditLogs
  - id, user_id?, action, description?, auditable_type?, auditable_id?, ip_address?, user_agent?, metadata?, timestamps

## Endpoints
- GET `/api/csrf-cookie` → prepara a sessão CSRF
- POST `/api/login` → autentica usuário
- POST `/api/logout` → encerra sessão
- GET `/api/me` → retorna usuário autenticado e permissões
- POST `/api/forgot-password` → solicita recuperação de senha
- POST `/api/reset-password` → redefine senha
- GET `/api/customers` → lista de clientes autenticado + `customers.view`
- POST `/api/customers` → cria cliente autenticado + `customers.create`
- PUT `/api/customers/{id}` → atualiza cliente autenticado + `customers.update`
- PATCH `/api/customers/{id}` → atualiza cliente (parcial) autenticado + `customers.update`
- DELETE `/api/customers/{id}` → remove cliente autenticado + `customers.delete`
- GET `/api/products` → lista de produtos autenticado + `products.view`
- POST `/api/products` → cria produto autenticado + `products.create`
- PUT `/api/products/{id}` → atualiza produto autenticado + `products.update`
- PATCH `/api/products/{id}` → atualiza produto (parcial) autenticado + `products.update`
- DELETE `/api/products/{id}` → remove produto autenticado + `products.delete`
- GET `/api/orders` → lista de pedidos autenticado + `orders.view`
- GET `/api/brands` → lista de marcas autenticado + `brands.view`
- POST `/api/brands` → cria marca autenticado + `brands.create`
- PUT `/api/brands/{id}` → atualiza marca autenticado + `brands.update`
- PATCH `/api/brands/{id}` → atualiza marca (parcial) autenticado + `brands.update`
- DELETE `/api/brands/{id}` → remove marca autenticado + `brands.delete`
- GET `/api/models` → lista de modelos autenticado + `models.view`
- POST `/api/models` → cria modelo autenticado + `models.create`
- PUT `/api/models/{id}` → atualiza modelo autenticado + `models.update`
- PATCH `/api/models/{id}` → atualiza modelo (parcial) autenticado + `models.update`
- DELETE `/api/models/{id}` → remove modelo autenticado + `models.delete`
- GET `/api/qualities` → lista de qualidades autenticado + `qualities.view`
- POST `/api/qualities` → cria qualidade autenticado + `qualities.create`
- PUT `/api/qualities/{id}` → atualiza qualidade autenticado + `qualities.update`
- PATCH `/api/qualities/{id}` → atualiza qualidade (parcial) autenticado + `qualities.update`
- DELETE `/api/qualities/{id}` → remove qualidade autenticado + `qualities.delete`

## Execução em Desenvolvimento
1. Backend
   - Requisitos: PHP 8.2+, Composer
   - Instalação: `composer install`
   - SQLite local é suportado (criar `database/database.sqlite` e ajustar `.env`).
   - Rodar migrações e seeds: `php artisan migrate:fresh --seed`
   - Se estiver rodando no host com MySQL do Docker publicado: `DB_HOST=127.0.0.1 DB_PORT=3307 php artisan migrate --seed`
   - Subir API: `php artisan serve` (http://localhost:8000)
2. Frontend
   - Requisitos: Node 18+
   - Variáveis: `NEXT_PUBLIC_API_BASE_URL` (opcional, default `http://localhost:8000/api`)
   - Subir dev server em porta alternativa: `npm run dev -- -p 4001` (http://localhost:4001)
   - O login depende de `GET /api/csrf-cookie` antes do `POST /api/login`

## Execução com Docker
- Subir stack completa: `docker compose up --build`
- Frontend: http://localhost:4001
- Backend: http://localhost:8000/api
- MySQL:
  - Host: `localhost`
  - Porta: `3307`
  - Database: `queiroz_prime`
  - User: `queiroz`
  - Password: `secret`

## Próximos Passos
- Criar endpoints POST/PUT/PATCH para criação e atualização de pedidos.
- Gestão administrativa de usuários e perfis pelo próprio CRM.
- 2FA opcional por usuário ou papel crítico.
- Filtros avançados e exportação.
- Ajustar fila de envios para registrar postagens e status.

## Padrões
- Não versionar segredos.
- Tipos centralizados (frontend) em `types.ts`.
- Reutilizar `Primitives` para UI consistente.
