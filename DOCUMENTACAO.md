# Watch CRM — Documentação

## Visão Geral
- Frontend em `frontend/`: Next.js (App Router) + React + TypeScript.
- Backend em `backend/`: Laravel 12.
- Objetivo: CRM para relojoaria com autenticação stateful, catálogo, pedidos, fila de envios e dashboards.
- Documentação complementar de login e autorização: `docs/login-e-autorizacao.md`.

## Resumo da Última Evolução
- Implementado módulo de gerenciamento de usuários: listagem, criação, edição, bloqueio/desbloqueio e redefinição de senha.
- Gerente (`gerente`) passou a ter acesso ao módulo de usuários (`users.manage`), com restrição de não poder criar ou editar administradores.
- Frontend migrado de SPA monolítico (`CrmApp.tsx`) para roteamento por arquivo (Next.js App Router). Cada página agora carrega seus dados de forma independente, somente quando o usuário navega até ela.
- Estado global (auth, tema, toasts) extraído para contexts React dedicados.
- `CrmApp.tsx` removido; responsabilidades distribuídas entre contexts e páginas de rota.

## Arquitetura Geral
### Frontend
- Entrada: `frontend/src/app/page.tsx` redireciona para `/dashboard`.
- Contextos globais em `frontend/src/features/crm/contexts/`:
  - `AuthContext` — sessão, usuário logado, login, logout, hasPermission.
  - `ToastContext` — notificações globais.
  - `ThemeContext` — tema claro/escuro/sistema.
- Layout protegido em `frontend/src/app/(app)/layout.tsx`:
  - Verifica autenticação e redireciona para `/login` se necessário.
  - Verifica permissão da rota atual e redireciona se o usuário não tiver acesso.
  - Renderiza AppShell + Sidebar.
- Cada página (`/dashboard`, `/pedidos`, etc.) é independente e busca seus próprios dados via `useEffect`.

### Backend
- Rotas centralizadas em `backend/routes/api.php`.
- Controllers REST para `customers`, `products`, `brands`, `qualities`, `models`, `orders` e `users`.
- Sessão stateful via middleware `web` + `auth`.
- Regras de autorização por `permission:*` e policies.
- Migrations e seeders atualizados para suportar catálogo tipado e pedidos multi-itens.

## Frontend — Estrutura Relevante
```text
frontend/
├─ src/app/
│  ├─ layout.tsx                  # Root layout — monta <Providers>
│  ├─ page.tsx                    # Redirect para /dashboard
│  ├─ Providers.tsx               # Composição dos 3 contexts (client component)
│  ├─ login/
│  │  └─ page.tsx                 # Página de login standalone
│  └─ (app)/
│     ├─ layout.tsx               # Auth guard + permission guard + AppShell + Sidebar
│     ├─ dashboard/page.tsx
│     ├─ pedidos/page.tsx
│     ├─ envios/page.tsx
│     ├─ clientes/page.tsx
│     ├─ produtos/page.tsx
│     ├─ modelos/page.tsx
│     ├─ configuracoes/page.tsx
│     └─ usuarios/page.tsx
└─ src/features/crm/
   ├─ contexts/
   │  ├─ AuthContext.tsx
   │  ├─ ToastContext.tsx
   │  └─ ThemeContext.tsx
   ├─ api.ts                      # apiFetch, apiCreate, apiUpdate, getErrorMessage, CSRF
   ├─ helpers.ts
   ├─ types.ts
   ├─ components/
   ├─ ui/
   ├─ data/mock.ts                # NAV com id, label, path e permission
   └─ views/
      ├─ Dashboard.tsx
      ├─ OrderList.tsx / OrderDetail.tsx / NewOrderForm.tsx
      ├─ ShippingQueue.tsx
      ├─ Customers.tsx / NewCustomerForm.tsx
      ├─ Products.tsx / NewProductForm.tsx
      ├─ Models.tsx / NewModelForm.tsx
      ├─ Settings.tsx
      ├─ Users.tsx / NewUserForm.tsx
      └─ LoginView.tsx
```

## Frontend — Fluxos Importantes
### Sessão e carregamento inicial
- `AuthContext` consulta `GET /api/me` na montagem.
- Se autenticado, `(app)/layout.tsx` renderiza a aplicação.
- Cada página carrega seus dados de forma independente no `useEffect` de montagem.
- Não há mais carregamento global de todos os dados ao fazer login.

### Carregamento por página
| Rota | Dados carregados |
|---|---|
| `/dashboard` | `GET /orders`, `GET /orders/metadata` |
| `/pedidos` | `GET /orders`, `GET /orders/metadata`, `GET /customers`, `GET /products` |
| `/envios` | `GET /orders`, `GET /customers` |
| `/clientes` | `GET /customers` |
| `/produtos` | `GET /products`, `GET /brands`, `GET /models` |
| `/modelos` | `GET /models`, `GET /brands`, `GET /qualities` |
| `/configuracoes` | `GET /brands`, `GET /qualities` |
| `/usuarios` | `GET /users` |

### Pedidos
- `NewOrderForm.tsx` trabalha com uma coleção de itens.
- Cada item possui `productId`, `quantity`, `unitPrice`, `unitDiscount`.
- O formulário calcula resumo de venda bruta, desconto total, custo total, lucro estimado e origem do estoque.
- Persistido com `POST /api/orders`.

### Catálogo
- `NewModelForm.tsx` permite escolher `productType` (`WATCH` ou `BOX`).
- A qualidade só aparece quando o tipo é `WATCH`.
- `helpers.ts` centraliza: `productTypeLabel`, `modelLabel`, `productLabel`.

### Usuários
- Acessível em `/usuarios` apenas por `admin` e `gerente`.
- Tabela com filtros por nome/e-mail, função e status.
- Ações por linha: editar, bloquear/ativar, redefinir senha.
- Gerente não pode criar, editar ou agir sobre administradores.
- Modal `NewUserForm` unificado para criar, editar e redefinir senha.

### Clientes
- Busca considera nome, telefone, email e Instagram.
- Criação e edição via `NewCustomerForm`.

## Backend — Estrutura e Regras
### Rotas principais
- Auth:
  - `GET /api/csrf-cookie`
  - `POST /api/login`
  - `POST /api/logout`
  - `GET /api/me`
  - `POST /api/forgot-password`
  - `POST /api/reset-password`
- CRM:
  - `customers`, `products`, `brands`, `qualities`, `models`, `orders`, `orders/metadata`, `users`

### Controllers impactados
- `CustomerController` — validação centralizada, store/update alinhados.
- `ModelController` — valida `productType`; exige `qualityId` apenas para `WATCH`; unicidade por `brand + name + product_type + quality_key`.
- `ProductController` — expõe `productType` no payload.
- `OrderController` — cria e atualiza pedidos com múltiplos itens; calcula totais a partir das linhas.
- `UserController` — listagem, criação, edição, toggle de status e redefinição de senha; auditoria em todas as operações de escrita.

### Permissões por papel
| Permissão | Admin | Gerente | Vendedor |
|---|---|---|---|
| Todas as permissões de catálogo e pedidos | ✓ | ✓ | — |
| `orders.view` | ✓ | ✓ | ✓ |
| `customers.view`, `products.view`, `models.view` | ✓ | ✓ | ✓ |
| `dashboard.view`, `shipping.view` | ✓ | ✓ | ✓ |
| `users.manage` | ✓ | ✓ | — |

### Policies
- `CustomerPolicy` — vendedor só visualiza/edita clientes próprios.
- `OrderPolicy` — vendedor só visualiza/edita pedidos próprios.
- `UserPolicy` — gerente não pode criar, editar, bloquear ou redefinir senha de administradores.

## Banco de Dados e Modelos
### Novos conceitos
- `WatchModel` ganhou: `product_type`, `quality_key`.
- `Order` ganhou relação `hasMany(OrderItem)`.
- `Product` ganhou relação `hasMany(OrderItem)`.
- Novo model `OrderItem`.

### Migrations relevantes
- `add_product_type_to_models_table` — adiciona `product_type`, `quality_key`; torna `quality_id` opcional.
- `create_order_items_table` — cria `order_items`; torna `orders.product_id` anulável; migra pedidos legados.

### Estrutura resumida das entidades
- `users` — `name`, `email`, `password`, `role`, `is_active`, `last_login_at`
- `customers` — `name`, `phone`, `email?`, `instagram?`, `owner_user_id?`
- `models` — `brand_id`, `name`, `product_type`, `quality_id?`, `quality_key`, `image_path?`
- `products` — `brand_id`, `model_id`, `cost`, `price`, `stock`, `qty`
- `orders` — `customer_id`, `created_by_user_id?`, `seller_user_id?`, `product_id?`, `product_name`, `channel`, `seller`, `status`, `sale_price`, `cost`, `discount`, `freight`, `channel_fee`, `payment_method?`, `shipping_method`, `tracking_code?`, `sale_date`, `shipped_date?`, `notes?`
- `order_items` — `order_id`, `product_id?`, `product_name`, `product_type`, `brand_name?`, `model_name?`, `quality_name?`, `quantity`, `unit_price`, `unit_cost`, `unit_discount`

## Regras de Negócio Relevantes
### Catálogo
- `WATCH` precisa de qualidade.
- `BOX` não aceita qualidade.
- O mesmo nome de modelo pode coexistir em qualidades diferentes (quando `WATCH`) ou por tipo diferente (`WATCH` vs `BOX`).

### Pedidos
- Um pedido precisa ter ao menos um item.
- Cada item precisa de: produto válido, quantidade mínima de `1`, preço unitário, desconto unitário ≥ `0`.
- Totais derivados da soma das linhas: `sale_price`, `cost`, `discount`.

### Usuários
- Senha mínima de 12 caracteres (criação e redefinição).
- Um usuário não pode bloquear a própria conta.
- Gerente não pode criar usuário com role `admin` nem alterar a role de um admin existente.

### Clientes
- Email e Instagram são opcionalmente persistidos.
- Strings vazias convertidas para `null` no model `Customer`.

## Endpoints
- `GET /api/csrf-cookie`
- `POST /api/login`
- `POST /api/logout`
- `GET /api/me`
- `POST /api/forgot-password`
- `POST /api/reset-password`
- `GET /api/customers`
- `POST /api/customers`
- `PUT /api/customers/{id}`
- `PATCH /api/customers/{id}`
- `DELETE /api/customers/{id}`
- `GET /api/products`
- `POST /api/products`
- `PUT /api/products/{id}`
- `PATCH /api/products/{id}`
- `DELETE /api/products/{id}`
- `GET /api/brands`
- `POST /api/brands`
- `PUT /api/brands/{id}`
- `PATCH /api/brands/{id}`
- `DELETE /api/brands/{id}`
- `GET /api/qualities`
- `POST /api/qualities`
- `PUT /api/qualities/{id}`
- `PATCH /api/qualities/{id}`
- `DELETE /api/qualities/{id}`
- `GET /api/models`
- `POST /api/models`
- `PUT /api/models/{id}`
- `PATCH /api/models/{id}`
- `DELETE /api/models/{id}`
- `GET /api/orders/metadata`
- `GET /api/orders`
- `POST /api/orders`
- `PUT /api/orders/{id}`
- `PATCH /api/orders/{id}`
- `DELETE /api/orders/{id}`
- `GET /api/users`
- `POST /api/users`
- `PATCH /api/users/{id}`
- `PATCH /api/users/{id}/active`
- `PATCH /api/users/{id}/password`

## Testes Cobertos
- `CatalogProductTypeTest`
  - criação de modelo `BOX` sem qualidade;
  - obrigatoriedade de qualidade para `WATCH`;
  - exposição de `productType` em produtos;
  - unicidade correta no catálogo.
- `OrderAuthorizationTest`
  - criação de pedido multi-item por admin;
  - atualização por gerente;
  - proibição para vendedor criar/editar;
  - escopo de listagem por vendedor;
  - retorno de metadata de pedidos.

## Execução em Desenvolvimento
### Backend
- Requisitos: PHP 8.2+ e Composer
- Instalação: `composer install`
- Rodar migrations e seeders: `php artisan migrate:fresh --seed`
- Rodar API: `php artisan serve`

### Frontend
- Requisitos: Node 18+
- Instalação: `npm install`
- Variável opcional: `NEXT_PUBLIC_API_BASE_URL=http://localhost:8000/api`
- Rodar app: `npm run dev -- -p 4001`

## Execução com Docker
- Subir stack: `docker compose up --build`
- Limpar órfãos antigos, se necessário: `docker compose down --remove-orphans`
- Frontend: `http://localhost:4001`
- Backend: `http://localhost:8000/api`
- MySQL:
  - Host: `localhost`
  - Porta: `3307`
  - Database: `watch_crm`
  - User: `watchcrm`
  - Password: `secret`

### Observação importante sobre o frontend em Docker
- O serviço do frontend usa `next dev --webpack` no `docker-compose.yml`.
- Esse ajuste foi necessário porque o `next dev` com Turbopack estava encerrando o processo do container logo após o startup.

## Padrões do Projeto
- Não versionar segredos.
- Centralizar tipos do frontend em `types.ts`.
- Reutilizar `helpers.ts` para labels e cálculos de domínio.
- Reutilizar `Primitives.tsx` para manter consistência visual.
- Cada página de rota gerencia seu próprio estado e fetch de dados.
- Mutations usam `apiCreate` / `apiUpdate` de `api.ts` (CSRF já incluso).
- Contexts (`AuthContext`, `ToastContext`, `ThemeContext`) são a única fonte de estado global.
