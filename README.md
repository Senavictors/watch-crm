# Watch CRM
CRM fullstack para relojoaria com autenticação stateful, catálogo, pedidos, fila de envios e dashboards.

## Destaques da versão atual
- Pedidos agora aceitam múltiplos itens, com quantidade, preço unitário e desconto unitário por linha.
- O catálogo passou a suportar dois tipos de modelo e produto: `WATCH` e `BOX`.
- Modelos do tipo `BOX` não exigem qualidade; modelos `WATCH` continuam exigindo.
- Detalhes, lista de pedidos e fila de envios exibem contagem de itens e resumo mais fiel do pedido.
- Clientes podem ser editados e campos opcionais como email e Instagram são normalizados no backend.
- O frontend em Docker sobe com `next dev --webpack`, evitando o encerramento prematuro observado com Turbopack no container.

## Stack
- Frontend: Next.js 16 + React 19 + TypeScript
- Backend: Laravel 12 + PHP 8.2+ localmente / PHP 8.4 no Docker
- Banco: MySQL 8.4 via Docker ou SQLite em desenvolvimento local

## Autenticação e Acesso
- Sessão stateful com Laravel Session e cookies HTTP-only
- Proteção CSRF com `GET /api/csrf-cookie` antes de ações autenticadas
- Autorização por papéis: `admin`, `gerente`, `vendedor`
- Permissões por rota e regras de ownership em módulos sensíveis

## Funcionalidades
- Login, logout e recuperação de sessão autenticada
- Dashboard com métricas de pedidos e performance
- Clientes com cadastro, edição e busca
- Produtos com suporte a relógios e caixas
- Modelos com upload de imagem e diferenciação por tipo de produto
- Pedidos com criação real via API, múltiplos itens e detalhamento por linha
- Fila de envios com visibilidade de quantidade de itens por pedido
- Configurações para cadastro de marcas e qualidades

## Telas do Sistema
### Dashboard
![Dashboard](imagens-do-sistema/dashboard.png)

### Pedidos
![Pedidos](imagens-do-sistema/pedidos.png)

### Fila de Envios
![Fila de Envios](imagens-do-sistema/fila-de-envios.png)

### Clientes
![Clientes](imagens-do-sistema/clientes.png)

### Produtos e Estoque
![Produtos e Estoque](imagens-do-sistema/produtos-e-estoque.png)

### Marcas
![Marcas](imagens-do-sistema/marcas.png)

### Modelos
![Modelos](imagens-do-sistema/modelos.png)

## Estrutura do Projeto
```text
watch-crm/
├─ frontend/                   # Next.js (UI/CRM)
│  └─ src/features/crm/        # Core do CRM
│     ├─ CrmApp.tsx            # Container principal + sessão autenticada
│     ├─ api.ts                # CSRF, cookies e chamadas autenticadas
│     ├─ views/                # Telas e formulários do CRM
│     ├─ ui/                   # Componentes base
│     ├─ types.ts              # Tipos do domínio, auth e permissões
│     └─ helpers.ts            # Cálculos, labels e formatação
├─ backend/                    # Laravel API
│  ├─ app/Http/Controllers/Api # Controllers da API
│  ├─ app/Models               # Models Eloquent
│  ├─ app/Policies             # Policies por ownership
│  ├─ app/Support              # Permissões e auditoria
│  ├─ database/migrations      # Migrations
│  └─ database/seeders         # Seeders
├─ docs/                       # Documentações específicas
├─ docker-compose.yml          # Orquestração Docker
├─ DOCUMENTACAO.md             # Documentação funcional e técnica
└─ crm-relogios.jsx            # MVP base de referência
```

## Endpoints Principais
- GET `/api/csrf-cookie`
- POST `/api/login`
- POST `/api/logout`
- GET `/api/me`
- POST `/api/forgot-password`
- POST `/api/reset-password`
- GET `/api/customers`
- POST `/api/customers`
- PATCH `/api/customers/{id}`
- DELETE `/api/customers/{id}`
- GET `/api/products`
- POST `/api/products`
- PATCH `/api/products/{id}`
- DELETE `/api/products/{id}`
- GET `/api/brands`
- POST `/api/brands`
- GET `/api/qualities`
- POST `/api/qualities`
- GET `/api/models`
- POST `/api/models`
- GET `/api/orders/metadata`
- GET `/api/orders`
- POST `/api/orders`
- PATCH `/api/orders/{id}`
- DELETE `/api/orders/{id}`

## Execução com Docker
```bash
docker compose up --build
```

Serviços:
- Frontend: `http://localhost:4001`
- Backend: `http://localhost:8000/api`
- MySQL: `localhost:3307`

Observações:
- O serviço `frontend` usa `next dev --webpack` no container para manter o servidor estável no Docker.
- Se houver containers órfãos de testes anteriores, prefira limpar com `docker compose down --remove-orphans` antes de subir novamente.

## Desenvolvimento Local
### Backend
- Instalar dependências: `composer install`
- Rodar API: `php artisan serve`
- Rodar migrations e seeders:
  - no Docker: `php artisan migrate --seed`
  - no host usando o MySQL publicado: `DB_HOST=127.0.0.1 DB_PORT=3307 php artisan migrate --seed`

### Frontend
- Instalar dependências: `npm install`
- Rodar app: `npm run dev -- -p 4001`
- Base da API: `NEXT_PUBLIC_API_BASE_URL=http://localhost:8000/api`

## Documentação
- Visão geral técnica e funcional: [DOCUMENTACAO.md](DOCUMENTACAO.md)
- Login, autorização, CSRF e troubleshooting: [docs/login-e-autorizacao.md](docs/login-e-autorizacao.md)
