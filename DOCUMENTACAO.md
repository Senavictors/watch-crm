# Watch CRM — Documentação

## Visão Geral
- Frontend em `frontend/`: Next.js (App Router) + React + TypeScript.
- Backend em `backend/`: Laravel 12.
- Objetivo: CRM para relojoaria com autenticação stateful, catálogo, pedidos, fila de envios e dashboards.
- Documentação complementar de login e autorização: `docs/login-e-autorizacao.md`.

## Resumo da Última Evolução
- Pedidos passaram a suportar múltiplos itens com linhas próprias (`order_items`).
- O catálogo agora diferencia produtos e modelos por tipo: `WATCH` e `BOX`.
- Modelos `BOX` não precisam de qualidade; modelos `WATCH` continuam exigindo.
- O frontend deixou de simular a criação de pedidos localmente e passou a criar/atualizar dados reais via API.
- Clientes ganharam edição e saneamento de campos opcionais no backend.
- O Docker do frontend foi ajustado para usar `next dev --webpack`, evitando o encerramento do processo no container.

## Arquitetura Geral
### Frontend
- Entrada: `frontend/src/app/page.tsx` monta o `CrmApp`.
- Núcleo da aplicação: `frontend/src/features/crm/*`.
- `CrmApp.tsx` controla:
  - estado de sessão;
  - bootstrap do usuário com `GET /api/me`;
  - carregamento autenticado de `customers`, `products`, `orders`, `orders/metadata`, `brands`, `models` e `qualities`;
  - criação de pedidos, clientes, produtos, marcas, qualidades e modelos via API;
  - edição de clientes e atualização de status de pedidos.

### Backend
- Rotas centralizadas em `backend/routes/api.php`.
- Controllers REST para `customers`, `products`, `brands`, `qualities`, `models` e `orders`.
- Sessão stateful via middleware `web` + `auth`.
- Regras de autorização por `permission:*` e policies.
- Migrations e seeders atualizados para suportar catálogo tipado e pedidos multi-itens.

## Frontend — Estrutura Relevante
```text
frontend/
├─ src/app/
│  ├─ layout.tsx
│  ├─ globals.css
│  └─ page.tsx
└─ src/features/crm/
   ├─ CrmApp.tsx
   ├─ api.ts
   ├─ helpers.ts
   ├─ types.ts
   ├─ components/
   ├─ ui/
   ├─ data/mock.ts
   └─ views/
      ├─ NewOrderForm.tsx
      ├─ OrderList.tsx
      ├─ OrderDetail.tsx
      ├─ ShippingQueue.tsx
      ├─ Customers.tsx
      ├─ NewCustomerForm.tsx
      ├─ Products.tsx
      ├─ Models.tsx
      └─ NewModelForm.tsx
```

## Frontend — Fluxos Importantes
### Sessão e carregamento inicial
- `CrmApp` consulta `GET /api/me`.
- Se autenticado, carrega em paralelo:
  - `GET /api/customers`
  - `GET /api/products`
  - `GET /api/orders`
  - `GET /api/orders/metadata`
  - `GET /api/brands`
  - `GET /api/models`
  - `GET /api/qualities`

### Pedidos
- `NewOrderForm.tsx` agora trabalha com uma coleção de itens.
- Cada item possui:
  - `productId`
  - `quantity`
  - `unitPrice`
  - `unitDiscount`
- O formulário calcula resumo de venda bruta, desconto total, custo total, lucro estimado e origem do estoque.
- `CrmApp.tsx` persiste o pedido com `POST /api/orders`.
- `OrderList.tsx`, `OrderDetail.tsx` e `ShippingQueue.tsx` passaram a exibir `itemsCount` e dados mais fiéis do pedido.

### Catálogo
- `NewModelForm.tsx` permite escolher `productType` (`WATCH` ou `BOX`).
- A qualidade só aparece quando o tipo é `WATCH`.
- `Models.tsx` e `Products.tsx` exibem o tipo do item e ajustam o rótulo visual quando o produto é uma caixa.
- `helpers.ts` centraliza:
  - `productTypeLabel`
  - `modelLabel`
  - `productLabel`

### Clientes
- O frontend já cria e edita clientes pela API.
- Busca de clientes considera nome, telefone, email e Instagram.

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
  - `customers`, `products`, `brands`, `qualities`, `models`, `orders`, `orders/metadata`

### Controllers impactados
- `CustomerController`
  - centralizou validação em `validatedData()`;
  - mantém store/update alinhados.
- `ModelController`
  - passou a validar `productType`;
  - exige `qualityId` apenas para `WATCH`;
  - permite `BOX` sem qualidade;
  - garante unicidade por `brand + name + product_type + quality_key`.
- `ProductController`
  - expõe `productType` no payload.
- `OrderController`
  - cria e atualiza pedidos com múltiplos itens;
  - calcula totais a partir das linhas;
  - carrega `sellerUser` e `items`;
  - continua usando campos agregados no pedido para compatibilidade e resumo.

## Banco de Dados e Modelos
### Novos conceitos
- `WatchModel` ganhou:
  - `product_type`
  - `quality_key`
- `Order` ganhou relação `hasMany(OrderItem)`.
- `Product` ganhou relação `hasMany(OrderItem)`.
- Novo model `OrderItem`.

### Migrations novas
- `2026_04_09_000006_add_product_type_to_models_table.php`
  - adiciona `product_type`;
  - adiciona `quality_key`;
  - torna `quality_id` opcional;
  - substitui a unique antiga por `models_catalog_unique`.
- `2026_04_09_000007_create_order_items_table.php`
  - cria `order_items`;
  - torna `orders.product_id` anulável;
  - migra pedidos legados para uma linha inicial em `order_items`.

### Estrutura resumida das entidades
- `customers`
  - `name`, `phone`, `email?`, `instagram?`, `owner_user_id?`
- `models`
  - `brand_id`, `name`, `product_type`, `quality_id?`, `quality_key`, `image_path?`
- `products`
  - `brand_id`, `model_id`, `cost`, `price`, `stock`, `qty`
- `orders`
  - `customer_id`, `created_by_user_id?`, `seller_user_id?`, `product_id?`, `product_name`, `channel`, `seller`, `status`, `sale_price`, `cost`, `discount`, `freight`, `channel_fee`, `payment_method?`, `shipping_method`, `tracking_code?`, `sale_date`, `shipped_date?`, `notes?`
- `order_items`
  - `order_id`, `product_id?`, `product_name`, `product_type`, `brand_name?`, `model_name?`, `quality_name?`, `quantity`, `unit_price`, `unit_cost`, `unit_discount`

## Regras de Negócio Relevantes
### Catálogo
- `WATCH` precisa de qualidade.
- `BOX` não aceita qualidade.
- O mesmo nome de modelo pode coexistir:
  - em qualidades diferentes, quando `WATCH`;
  - por tipo diferente, quando `WATCH` vs `BOX`.

### Pedidos
- Um pedido precisa ter ao menos um item.
- Cada item precisa de:
  - produto válido;
  - quantidade mínima de `1`;
  - preço unitário;
  - desconto unitário maior ou igual a `0`.
- Os totais do pedido são derivados da soma das linhas:
  - `sale_price`
  - `cost`
  - `discount`
- O resumo textual do pedido usa o primeiro item como base e adiciona a quantidade restante quando houver mais linhas.

### Clientes
- Email e Instagram são opcionalmente persistidos.
- Strings vazias são convertidas para `null` no model `Customer`.

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
