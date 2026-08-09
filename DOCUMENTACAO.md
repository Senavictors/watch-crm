# Watch CRM — Documentação Funcional e Técnica

Atualizada em 8 de agosto de 2026.

## 1. Escopo Atual

O Watch CRM é uma aplicação fullstack para a operação de uma relojoaria. O sistema cobre:

- autenticação e autorização por papel;
- catálogo, produtos e estoque por origem;
- clientes, pedidos multi-item e pagamentos;
- dashboard financeiro-operacional;
- metas, comissões, despesas e avaliação de estoque;
- agenda e fila de postagem;
- garantias, trocas e devoluções;
- lista de espera e insights de clientes;
- alertas operacionais e resumo inteligente opcional.

O backend é a fonte da verdade para cálculos e regras de domínio. O frontend formata, apresenta e coleta entradas, sem duplicar cálculos financeiros relevantes.

## 2. Arquitetura

### 2.1 Frontend

- Next.js 16 com App Router, React 19 e TypeScript.
- Entrada pública em `src/app/login/page.tsx`.
- Grupo protegido em `src/app/(app)/`.
- `AuthContext` mantém sessão e permissões.
- `ToastContext` mantém notificações globais da interface.
- `ThemeContext` mantém tema claro, escuro ou do sistema.
- `api.ts` centraliza URL da API, cookies, CSRF e tratamento base das requisições.
- Cada página busca somente os dados necessários para o seu módulo.
- Listagens operacionais usam paginação server-side e filtros na API.
- Seletores de clientes, produtos, modelos e pedidos usam endpoints `/lookup` com busca sob demanda.

Rotas protegidas atuais:

```text
/dashboard
/pedidos
/envios
/garantias
/clientes
/lista-espera
/produtos
/modelos
/metas
/comissoes
/despesas
/configuracoes
/usuarios
```

Estrutura relevante:

```text
frontend/src/
├── app/
│   ├── login/
│   └── (app)/
└── features/crm/
    ├── api.ts
    ├── types.ts
    ├── helpers.ts
    ├── pagination.ts
    ├── contexts/
    ├── hooks/
    ├── data/
    ├── components/
    ├── views/
    └── ui/
```

### 2.2 Backend

- Laravel 12 e PHP 8.2+; a imagem Docker usa PHP 8.4.
- Rotas REST centralizadas em `backend/routes/api.php`.
- Sessão stateful por middleware `web` e `auth`.
- Middleware `permission:*` verifica capacidade por recurso e ação.
- Policies aplicam ownership e regras contextuais.
- Models Eloquent representam o domínio e suas relações.
- Classes em `app/Support/` concentram cálculos financeiros, transições, paginação, alertas, IA e metadados.
- Operações compostas, como criação de pedido e atualização de pós-venda, utilizam transação de banco.

Principais grupos de backend:

```text
backend/
├── app/Http/Controllers/Api/
├── app/Http/Middleware/
├── app/Models/
├── app/Policies/
├── app/Enums/
├── app/Support/
├── database/migrations/
├── database/seeders/
├── routes/api.php
└── tests/
```

### 2.3 Banco e portas

- O backend no Compose acessa o banco como `mysql:3306`.
- O host acessa o MySQL pela porta publicada `3307`.
- O frontend roda em `4001` e a API em `8000`.
- Testes de backend usam SQLite in-memory.

## 3. Autenticação e Sessão

Fluxo de login:

1. O frontend chama `GET /api/csrf-cookie`.
2. O backend devolve o cookie `XSRF-TOKEN`.
3. O frontend chama `POST /api/login` com credenciais, cookie e cabeçalho `X-XSRF-TOKEN`.
4. O backend cria uma sessão e devolve usuário e permissões.
5. `AuthContext` consulta `GET /api/me` na carga inicial e protege as rotas.

Características:

- cookie de sessão HTTP-only;
- proteção CSRF nativa;
- login e recuperação de senha com rate limit;
- usuário inativo não pode manter sessão;
- autenticação não utiliza JWT, bearer token ou `localStorage`.

## 4. Papéis e Permissões

Papéis aceitos:

- `owner`;
- `admin`;
- `gerente`;
- `vendedor`;
- `garantia`.

Resumo da matriz atual:

| Capacidade | Owner | Admin | Gerente | Vendedor | Garantia |
|---|---:|---:|---:|---:|---:|
| Dashboard operacional | ✓ | ✓ | ✓ | ✓ | — |
| Dashboard financeiro | ✓ | ✓ | — | — | — |
| Ver fila de envios | ✓ | ✓ | ✓ | ✓ | ✓ |
| Configurar dias de postagem | ✓ | ✓ | ✓ | — | — |
| Ver clientes/produtos/modelos | ✓ | ✓ | ✓ | ✓ | ✓ |
| Manter catálogo | ✓ | ✓ | ✓ | — | — |
| Ver pedidos | ✓ | ✓ | ✓ | ✓, próprios | — |
| Criar/editar/excluir pedidos | ✓ | ✓ | ✓ | — | — |
| Ver pós-venda | ✓ | ✓ | ✓ | ✓ | ✓ |
| Criar/editar pós-venda | ✓ | ✓ | ✓ | — | ✓ |
| Excluir pós-venda | ✓ | ✓ | ✓ | — | — |
| Ver metas | ✓ | ✓ | ✓ | ✓, empresa/próprias | — |
| Manter metas | ✓ | ✓ | ✓ | — | — |
| Ver comissões | ✓ | ✓ | — | ✓, própria | — |
| Conciliar comissões | ✓ | ✓ | — | — | — |
| Manter despesas | ✓ | ✓ | — | — | — |
| Lista de espera | Completa | Completa | Completa | Ver/criar/editar próprias | — |
| Configurações gerais e usuários | ✓ | ✓ | ✓ | — | — |
| Resumo e configuração de IA | ✓ | ✓ | — | — | — |

Observações:

- `owner`, `admin` e `gerente` acessam todos os registros operacionais.
- Os demais papéis ficam sujeitos a ownership quando a policy ou controller do recurso aplica escopo.
- `owner` pode ser selecionado como vendedor de pedidos e metas.
- Uma nova permissão só está completa quando existe no backend, na atribuição por papel, na rota, no tipo `Permission` do frontend e, quando aplicável, no `NAV`.

## 5. Módulos Funcionais

### 5.1 Dashboard

`GET /api/dashboard/summary` recebe período e filtros e devolve uma resposta agregada, incluindo:

- período atual e período de comparação;
- faturamento e ticket médio;
- lucro das vendas e resultado líquido, quando autorizado;
- relógios vendidos e quantidade de pedidos;
- pedidos ativos;
- conversão de pagamento geral e por canal;
- valor, quantidade e tempo médio de pagamentos pendentes;
- despesas, comissões e avaliação financeira do estoque, quando autorizadas;
- evolução temporal;
- distribuição por categoria e canal;
- progresso de metas;
- próximos envios;
- alertas operacionais determinísticos.

Os agregados consideram todo o filtro e não apenas a página atual de uma listagem. Métricas financeiras usam `paid_at` como data de competência.

### 5.2 Resumo inteligente

- `GET /api/ai/summary` devolve o resumo em cache, quando disponível.
- `POST /api/ai/summary` gera uma nova seleção de fatos.
- A geração é restrita a `owner` e `admin`.
- O contexto enviado não contém PII de clientes ou usuários.
- A chamada usa a OpenAI Responses API com `store: false`.
- O serviço valida a seleção devolvida pelo modelo e mantém fallback isolado.
- Falha, timeout ou ausência de chave não derrubam o dashboard.
- Alertas críticos são calculados localmente e não dependem de IA.
- A geração possui limite de 10 tentativas por minuto por usuário.
- O card permanece aberto por padrão e pode ser recolhido ou expandido pelo usuário sem gerar uma nova chamada à API.

A configuração pode vir do ambiente ou da tabela `ai_settings`. A chave armazenada pelo painel é criptografada e tem precedência sobre a variável de ambiente.

### 5.3 Catálogo, modelos e produtos

- Marcas, categorias e qualidades são cadastráveis.
- Todo modelo exige marca e categoria.
- `categories.has_quality` determina se `quality_id` é obrigatório ou proibido.
- A unicidade de modelo considera marca, nome, categoria e chave de qualidade.
- Imagens de modelo aceitam JPG, JPEG ou PNG até 2 MB.
- Produtos representam entradas de estoque por modelo e origem `IN_STOCK` ou `SUPPLIER`.
- Existe no máximo uma entrada por `modelo + origem`.
- Produto possui custo, preço padrão, preço PIX, preço cartão e comissão unitária opcional.
- Usuários somente-leitura não recebem o custo do catálogo.
- `PATCH /api/products/{id}/add-qty` adiciona unidades sem substituir a quantidade atual.

### 5.4 Pedidos e pagamentos

O contrato de criação e atualização utiliza `items`, não `order_items`:

```json
{
  "customerId": 1,
  "sellerUserId": 3,
  "channel": "WhatsApp",
  "status": "Novo",
  "paymentMethod": "PIX",
  "paymentExpiresAt": "2026-08-08T18:00:00-03:00",
  "shippingMethod": "Correios",
  "saleDate": "2026-08-08",
  "items": [
    {
      "productId": 10,
      "quantity": 1,
      "unitPrice": 1500,
      "unitDiscount": 50
    }
  ]
}
```

Regras principais:

- ao menos um item;
- quantidade mínima de 1;
- produto existente;
- preço e desconto unitários não negativos;
- `sellerUserId` precisa apontar para papel vendável (`owner` ou `vendedor`);
- totais, custos e snapshots de comissão são calculados no backend;
- a primeira transição para `Pago` grava `paid_at` e `paid_by_user_id`;
- `payment_expires_at` é opcional;
- comissão paga impede recriação silenciosa dos itens;
- vendedor não cria nem edita pedidos e só lista pedidos atribuídos a si.

Filtros atuais incluem busca, categoria, período, status, canal, vendedor, cliente e pagamento pendente.

### 5.5 Clientes

- Cadastro com nome, telefone, email, Instagram e endereço.
- Busca por nome, telefone, email ou Instagram.
- Vínculo opcional a vendedor responsável.
- O detalhe apresenta pedidos e pós-vendas paginados.
- Insights consideram apenas compras pagas e incluem total gasto, quantidade, ticket médio, última compra e possível recompra.
- Possível recompra é um sinal heurístico de três estados: sim, não ou dados insuficientes.
- Notas de atrito são imutáveis; não há edição ou exclusão.

### 5.6 Metas

- Escopo `company` ou `user`.
- O escopo `user` exige um usuário vendável ativo.
- Cálculo por `total_value` ou `quantity`.
- Filtros opcionais por tipo de produto (`WATCH`/`BOX`), marca e modelo, aplicados aos snapshots persistidos em `order_items`.
- Ciclos mensal, trimestral, semestral ou anual.
- Cada intervalo exige alvo maior que zero.
- Atualização recria os intervalos da meta.
- Pedidos cancelados não entram no progresso.
- Reembolso efetivado reduz o progresso correspondente.

### 5.7 Comissões

- A comissão unitária é copiada do produto para a linha no momento da venda.
- O relatório separa valores acumulados, pagos e pendentes.
- Vendedor vê somente a própria projeção.
- Owner/admin podem conciliar itens pendentes.
- A conciliação registra data e usuário responsável.

### 5.8 Despesas e estoque financeiro

- Despesas possuem categoria, descrição, valor, data e criador.
- Listagem aceita filtros e devolve um resumo agregado do filtro completo.
- Avaliação de estoque consolida custo atual e potencial de faturamento.
- Esses dados são restritos a `owner` e `admin`.

Limitação atual: pedido pago/vendido não decrementa automaticamente `products.qty`, e devolução não repõe unidades.

### 5.9 Envios

- Os dias habilitados são persistidos em `posting_days`.
- É obrigatório manter ao menos um dia de postagem habilitado.
- A fila considera pedidos pagos ainda não enviados.
- A próxima postagem é calculada a partir de `paid_at` e da agenda semanal.
- Usuários com `orders.update` podem concluir um item da fila pela modal **Atualizar envio**; a operação define o status `Enviado`, grava a data atual em `shipped_date` e remove o pedido da fila.
- O código de rastreio é obrigatório ao concluir envios por Sedex ou Correios PAC e opcional para os demais métodos.
- A interface também mostra pós-vendas prontos para reenvio.
- A integração automática com rastreamento dos Correios ainda não está implementada.

### 5.10 Garantias, trocas e devoluções

- Registro vinculado a cliente e opcionalmente a pedido.
- Um ou mais itens por ocorrência.
- Tipos: garantia, troca ou devolução.
- Custos de entrada, relojoeiro, saída e outros.
- Reembolso e códigos de rastreio opcionais.
- Transições de status são validadas por `ReturnStatusTransition`.
- Toda transição gera registro em `return_status_history`.
- O detalhe expõe histórico e indicador da janela de garantia.

### 5.11 Lista de espera

- Entrada vinculada a cliente, produto e vendedor.
- Pedido convertido é opcional.
- Status: `Pendente`, `Avisado`, `Convertido` ou `Encerrado`.
- Vendedor vê, cria e atualiza apenas as próprias entradas e não as exclui.
- O módulo não envia notificações automaticamente.

## 6. Banco de Dados

Entidades de domínio:

| Tabela | Campos e relações relevantes |
|---|---|
| `users` | Papel, atividade, último login e dados de autenticação. |
| `customers` | Dados pessoais, endereço e `owner_user_id`. |
| `customer_friction_notes` | Cliente, nota, autor e data. |
| `brands` | Nome único. |
| `categories` | Nome único e `has_quality`. |
| `qualities` | Nome único. |
| `models` | Marca, categoria, qualidade, chave de qualidade e imagem. |
| `products` | Marca, modelo, custo, preços, comissão, origem e quantidade. |
| `orders` | Cliente, criador, vendedor, pagamento, totais, envio e datas. |
| `order_items` | Produto, snapshots de catálogo, quantidade, valores e comissão. |
| `goals` | Criador, alvo, escopo, cálculo, filtros, ciclo e período. |
| `goal_intervals` | Período, alvo e vínculo à meta. |
| `expenses` | Categoria, descrição, valor, data e autor. |
| `posting_days` | Dia da semana e estado habilitado. |
| `product_returns` | Tipo, status, cliente, pedido, custos, reembolso e rastreio. |
| `return_items` | Itens do pós-venda. |
| `return_status_history` | Status anterior/novo, ator, nota e data. |
| `waitlist_entries` | Cliente, produto, vendedor, pedido convertido e status. |
| `ai_settings` | Provedor, modelo, chave criptografada, projeto e feature flag. |
| `audit_logs` | Usuário, ação, descrição, IP e metadados. |

O diretório de migrations é incremental. A definição atual de uma tabela é a soma de todas as migrations que a criam ou alteram.

## 7. Convenções da API

- Base local: `http://localhost:8000/api`.
- Corpos e respostas usam predominantemente `camelCase`; alguns filtros de query preservam nomes legados, como `customer_id`.
- Erros de validação retornam HTTP 422 com `message` e `errors`.
- Falta de autenticação retorna 401; falta de permissão retorna 403.
- Recursos ausentes retornam 404.
- Escritas protegidas usam CSRF e cookie de sessão.
- `PUT` e `PATCH` apontam para a mesma atualização nos CRUDs que expõem ambos.

### 7.1 Paginação

Listagens operacionais aceitam:

- `page` — padrão 1;
- `perPage` — padrão 20, máximo 100.

Resposta uniforme:

```json
{
  "data": [],
  "meta": {
    "currentPage": 1,
    "lastPage": 1,
    "perPage": 20,
    "total": 0,
    "from": null,
    "to": null
  }
}
```

Busca e filtros são aplicados antes da paginação. Agregados financeiros adicionais, como o resumo de despesas, continuam considerando todo o filtro.

### 7.2 Lookups

Clientes, produtos, modelos e pedidos expõem `/lookup`, retornando `{ data: [...] }` com no máximo 20 resultados. Esses endpoints alimentam seletores assíncronos e evitam carregar catálogos inteiros em formulários.

## 8. Endpoints

### 8.1 Autenticação

- `GET /api/csrf-cookie`
- `POST /api/login`
- `POST /api/forgot-password`
- `POST /api/reset-password`
- `GET /api/me`
- `POST /api/logout`

### 8.2 Dashboard e IA

- `GET /api/dashboard/summary`
- `GET /api/ai/summary`
- `POST /api/ai/summary`
- `GET /api/ai/settings`
- `PUT /api/ai/settings`
- `DELETE /api/ai/settings/key`

### 8.3 Envios

- `GET /api/shipping/schedule`
- `PUT /api/shipping/schedule`
- `GET /api/shipping/queue`

### 8.4 Clientes

- `GET /api/customers`
- `GET /api/customers/lookup`
- `GET /api/customers/{id}`
- `POST /api/customers`
- `PUT /api/customers/{id}`
- `PATCH /api/customers/{id}`
- `DELETE /api/customers/{id}`
- `POST /api/customers/{id}/friction-notes`

### 8.5 Produtos

- `GET /api/products`
- `GET /api/products/lookup`
- `POST /api/products`
- `PUT /api/products/{id}`
- `PATCH /api/products/{id}`
- `PATCH /api/products/{id}/add-qty`
- `DELETE /api/products/{id}`

### 8.6 Marcas

- `GET /api/brands`
- `POST /api/brands`
- `PUT /api/brands/{id}`
- `PATCH /api/brands/{id}`
- `DELETE /api/brands/{id}`

### 8.7 Qualidades

- `GET /api/qualities`
- `POST /api/qualities`
- `PUT /api/qualities/{id}`
- `PATCH /api/qualities/{id}`
- `DELETE /api/qualities/{id}`

### 8.8 Categorias

- `GET /api/categories`
- `POST /api/categories`
- `PUT /api/categories/{id}`
- `PATCH /api/categories/{id}`
- `DELETE /api/categories/{id}`

### 8.9 Modelos

- `GET /api/models`
- `GET /api/models/lookup`
- `POST /api/models`
- `PUT /api/models/{id}`
- `PATCH /api/models/{id}`
- `DELETE /api/models/{id}`

### 8.10 Pedidos

- `GET /api/orders/metadata`
- `GET /api/orders/lookup`
- `GET /api/orders`
- `GET /api/orders/{id}`
- `POST /api/orders`
- `PUT /api/orders/{id}`
- `PATCH /api/orders/{id}`
- `DELETE /api/orders/{id}`

### 8.11 Pós-venda

- `GET /api/returns/metadata`
- `GET /api/returns`
- `GET /api/returns/{id}`
- `POST /api/returns`
- `PUT /api/returns/{id}`
- `PATCH /api/returns/{id}`
- `DELETE /api/returns/{id}`

### 8.12 Comissões

- `GET /api/commissions`
- `POST /api/commissions/pay`

### 8.13 Despesas

- `GET /api/expenses/metadata`
- `GET /api/expenses`
- `POST /api/expenses`
- `PUT /api/expenses/{id}`
- `PATCH /api/expenses/{id}`
- `DELETE /api/expenses/{id}`

### 8.14 Metas

- `GET /api/goals/metadata`
- `GET /api/goals`
- `POST /api/goals`
- `PUT /api/goals/{id}`
- `PATCH /api/goals/{id}`
- `DELETE /api/goals/{id}`

### 8.15 Lista de espera

- `GET /api/waitlist/metadata`
- `GET /api/waitlist`
- `POST /api/waitlist`
- `PUT /api/waitlist/{id}`
- `PATCH /api/waitlist/{id}`
- `DELETE /api/waitlist/{id}`

### 8.16 Usuários

- `GET /api/users`
- `POST /api/users`
- `PATCH /api/users/{id}`
- `PATCH /api/users/{id}/active`
- `PATCH /api/users/{id}/password`

## 9. Configuração e Execução

### 9.1 Docker Compose

```bash
docker compose up --build
```

Serviços:

- frontend: `http://localhost:4001`;
- backend: `http://localhost:8000/api`;
- MySQL no host: `localhost:3307`;
- database: `watch_crm`;
- user: `watchcrm`;
- password local: `secret`.

O Compose executa `composer install`, gera `APP_KEY` quando necessário, limpa o cache de configuração, executa `migrate --force --seed` e inicia a API.

O frontend usa `next dev --webpack` e `WATCHPACK_POLLING=true`. O polling é necessário para Fast Refresh com bind mount no Docker Desktop/Windows.

### 9.2 Backend local com SQLite

```bash
cd backend
composer install
cp .env.example .env
touch database/database.sqlite
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

No PowerShell, use `Copy-Item` no lugar de `cp` e `New-Item database/database.sqlite -ItemType File` no lugar de `touch`.

### 9.3 Frontend local

O Next.js instalado exige Node.js 20.9 ou superior.

```bash
cd frontend
npm install
npm run dev -- -p 4001
```

`NEXT_PUBLIC_API_BASE_URL` é opcional; o padrão é `http://localhost:8000/api`.

### 9.4 Integração OpenAI

Variáveis disponíveis:

```dotenv
AI_SUMMARY_ENABLED=true
AI_SUMMARY_CACHE_TTL=900
AI_SUMMARY_TIMEOUT=20
AI_SUMMARY_TIMEZONE=America/Sao_Paulo
OPENAI_API_KEY=
OPENAI_PROJECT=
OPENAI_MODEL=gpt-5.6-luna
OPENAI_PROXY=
```

No Compose, `OPENAI_PROXY` assume `http://http.docker.internal:3128` quando a variável não é informada. Fora do Docker o proxy é opcional.

## 10. Testes e Qualidade

### Backend

```bash
cd backend
php artisan test
./vendor/bin/pint
```

`phpunit.xml` referencia `tests/bootstrap.php`, que força SQLite in-memory antes do bootstrap do Laravel. Isso impede que variáveis MySQL do container direcionem os testes ao banco de desenvolvimento.

As suítes cobrem, entre outros pontos:

- autenticação e CSRF;
- autorização e ownership;
- catálogo e categorias;
- pedidos, pagamentos, filtros e paginação;
- dashboard e regras financeiras;
- comissões, despesas e estoque;
- garantias e transições;
- agenda de postagem;
- lista de espera e insights de clientes;
- IA, fallback e ausência de PII.

### Frontend

```bash
cd frontend
npm run lint
npm run build
```

Não há suíte Jest, Vitest ou Playwright configurada. Mudanças visuais exigem validação manual no navegador.

## 11. Padrões e Restrições

- Não duplicar regras financeiras no frontend.
- Não alterar contrato de endpoint sem verificar consumidores em `frontend/src/features/crm/`.
- Não introduzir JWT ou persistir token de autenticação no navegador.
- Não versionar segredos.
- Centralizar tipos públicos do frontend em `types.ts`.
- Reutilizar `api.ts`, `helpers.ts`, paginação e primitives existentes.
- Manter permissões sincronizadas entre backend e frontend.
- Não calcular agregados apenas sobre a página visível.
- Não presumir que uma migration de criação representa o schema final.

## 12. Limitações e Pendências Conhecidas

- Estoque ainda não é decrementado automaticamente na venda nem reposto na devolução.
- Rastreamento automático dos Correios aguarda definição do contrato/cartão de postagem.
- O piloto do resumo inteligente depende de credencial OpenAI válida e smoke test real.
- Não há testes automatizados de interface no frontend.
- Credenciais do MySQL no Compose são apenas para desenvolvimento local e não devem ser reutilizadas em produção.
