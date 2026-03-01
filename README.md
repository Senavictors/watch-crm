# Queiroz Prime CRM
CRM fullstack para relojoaria Queiroz Prime com cadastros, pedidos, envios e dashboards.

## Stack
- Frontend: Next.js (App Router) + React + TypeScript
- Backend: Laravel 12 (PHP 8.2+)
- Banco: MySQL (via Docker) ou SQLite local (desenvolvimento)

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

## Observações
- Documentação completa em `DOCUMENTACAO.md`.
