<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockReservation;
use App\Support\ApiPagination;
use App\Support\StockLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $canViewCost = $request->user()->canViewCatalogCost();

        $query = $this->listQuery();

        $this->applySearch($query, $request->string('search')->toString());

        $products = $query->orderByDesc('id')->paginate(ApiPagination::perPage($request));

        return ApiPagination::response($products, fn (Product $product) => $this->toPayload($product, $canViewCost));
    }

    public function lookup(Request $request)
    {
        $query = $this->listQuery();
        $this->applySearch($query, $request->string('search')->toString());

        $products = $query->orderBy('brand_id')->orderBy('model_id')->limit(20)->get();

        return response()->json([
            'data' => $products->map(fn (Product $product) => $this->toPayload($product, $request->user()->canViewCatalogCost()))->values(),
        ]);
    }

    private function listQuery()
    {
        return Product::query()->with(['brand', 'watchModel.quality', 'watchModel.category']);
    }

    private function applySearch($query, string $search): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        $query->where(function ($builder) use ($search) {
            $builder->whereHas('brand', fn ($brand) => $brand->where('name', 'like', "%{$search}%"))
                ->orWhereHas('watchModel', fn ($model) => $model->where('name', 'like', "%{$search}%"))
                ->orWhereHas('watchModel.quality', fn ($quality) => $quality->where('name', 'like', "%{$search}%"))
                ->orWhereHas('watchModel.category', fn ($category) => $category->where('name', 'like', "%{$search}%"));
        });
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'brandId' => ['required', 'integer', 'exists:brands,id'],
            'modelId' => [
                'required',
                'integer',
                Rule::exists('models', 'id')->where('brand_id', $request->input('brandId')),
            ],
            'cost' => ['required', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'pricePix' => ['nullable', 'numeric', 'min:0'],
            'priceCard' => ['nullable', 'numeric', 'min:0'],
            'commissionAmount' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'in:IN_STOCK,SUPPLIER'],
            'qty' => ['required', 'integer', 'min:0'],
        ]);

        $alreadyExists = Product::where('model_id', $data['modelId'])
            ->where('stock', $data['stock'])
            ->exists();

        if ($alreadyExists) {
            $label = $data['stock'] === 'IN_STOCK' ? 'Estoque' : 'Fornecedor';

            return response()->json([
                'message' => "Já existe uma entrada \"{$label}\" para este modelo. Edite a entrada existente ou adicione unidades a ela.",
            ], 422);
        }

        $product = Product::create([
            'brand_id' => $data['brandId'],
            'model_id' => $data['modelId'],
            'cost' => $data['cost'],
            'price' => $data['price'],
            'price_pix' => $data['pricePix'] ?? null,
            'price_card' => $data['priceCard'] ?? null,
            'commission_amount' => $data['commissionAmount'] ?? null,
            'stock' => $data['stock'],
            'qty' => $data['qty'],
        ]);
        $product->load(['brand', 'watchModel.quality', 'watchModel.category']);
        $this->audit('products.created', 'Produto criado.', $product);

        return response()->json($this->toPayload($product, $request->user()->canViewCatalogCost()), 201);
    }

    public function addQty(Request $request, int $id)
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json(['message' => 'Produto não encontrado.'], 404);
        }

        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:1'],
        ]);

        // TASK-024 (ADR-006, item 4): é por este caminho que a reposição de
        // uma peça devolvida entra — devolução não repõe estoque sozinha,
        // então esta entrada manual precisa ficar no ledger para o histórico
        // de estoque ser auditável de ponta a ponta (CA-05).
        DB::transaction(function () use ($product, $data, $request) {
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->first();
            $locked->qty = (int) $locked->qty + (int) $data['qty'];
            $locked->save();

            StockLedger::recordManualChange(
                $locked,
                (int) $data['qty'],
                StockLedger::TYPE_MANUAL_ENTRY,
                $request->user(),
                'Entrada manual de unidades no catálogo.',
            );

            $product->qty = $locked->qty;
        }, 3);

        $product->load(['brand', 'watchModel.quality', 'watchModel.category']);
        $this->audit('products.qty_added', "Adicionadas {$data['qty']} unidades ao produto.", $product);

        return response()->json($this->toPayload($product, $request->user()->canViewCatalogCost()));
    }

    public function update(Request $request, int $id)
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json(['message' => 'Produto não encontrado.'], 404);
        }

        if ($request->has('brandId') && ! $request->has('modelId')) {
            return response()->json(['message' => 'modelId é obrigatório quando brandId é informado.'], 422);
        }

        $brandId = $request->input('brandId', $product->brand_id);

        $data = $request->validate([
            'brandId' => ['sometimes', 'integer', 'exists:brands,id'],
            'modelId' => [
                'sometimes',
                'integer',
                Rule::exists('models', 'id')->where('brand_id', $brandId),
            ],
            'cost' => ['sometimes', 'numeric', 'min:0'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'pricePix' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'priceCard' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'commissionAmount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'in:IN_STOCK,SUPPLIER'],
            'qty' => ['sometimes', 'integer', 'min:0'],
        ]);

        if (array_key_exists('brandId', $data)) {
            $data['brand_id'] = $data['brandId'];
            unset($data['brandId']);
        }

        if (array_key_exists('modelId', $data)) {
            $data['model_id'] = $data['modelId'];
            unset($data['modelId']);
        }

        if (array_key_exists('pricePix', $data)) {
            $data['price_pix'] = $data['pricePix'];
            unset($data['pricePix']);
        }

        if (array_key_exists('priceCard', $data)) {
            $data['price_card'] = $data['priceCard'];
            unset($data['priceCard']);
        }

        if (array_key_exists('commissionAmount', $data)) {
            $data['commission_amount'] = $data['commissionAmount'];
            unset($data['commissionAmount']);
        }

        // TASK-024 (RN-02): edição manual não pode derrubar `qty` abaixo do
        // que já está prometido a pedidos vivos, senão o saldo disponível
        // ficaria negativo.
        if (array_key_exists('qty', $data) && (int) $data['qty'] < (int) $product->reserved_qty) {
            return response()->json([
                'message' => "Não é possível reduzir para {$data['qty']} unidade(s): {$product->reserved_qty} já estão reservadas por pedidos em aberto.",
                'code' => 'reserved_stock_conflict',
            ], 422);
        }

        $previousQty = (int) $product->qty;

        DB::transaction(function () use ($product, $data, $request, $previousQty) {
            $product->fill($data);
            $product->save();

            if (array_key_exists('qty', $data)) {
                StockLedger::recordManualChange(
                    $product,
                    (int) $data['qty'] - $previousQty,
                    StockLedger::TYPE_MANUAL_ADJUST,
                    $request->user(),
                    'Ajuste manual de quantidade na edição do produto.',
                );
            }
        }, 3);

        $product->load(['brand', 'watchModel.quality', 'watchModel.category']);
        $this->audit('products.updated', 'Produto atualizado.', $product);

        return response()->json($this->toPayload($product, $request->user()->canViewCatalogCost()));
    }

    public function destroy(int $id)
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json(['message' => 'Produto não encontrado.'], 404);
        }

        // TASK-025: produto vendido não é apagado — as FKs de `order_items`/
        // `return_items` são SET NULL, então o pedido sobreviveria, mas
        // perderia o vínculo com o item de catálogo. Reserva ativa bloqueia
        // porque `stock_reservations` cascateia (ADR-006/ADR-007).
        $conflict = $this->conflictIfInUse([
            'itens de pedido' => OrderItem::query()->where('product_id', $product->id)->count(),
            'reservas de estoque ativas' => StockReservation::query()
                ->where('product_id', $product->id)
                ->where('status', '!=', StockReservation::STATUS_RELEASED)
                ->count(),
        ], 'Este produto', 'product_in_use');

        if ($conflict) {
            return $conflict;
        }

        DB::transaction(function () use ($product, $id) {
            $product->delete();
            $this->audit('products.deleted', 'Produto removido.', null, ['product_id' => $id]);
        });

        return response()->json(['ok' => true]);
    }

    /**
     * TASK-013 (RN-02): custo é dado operacional de catálogo, não relatório
     * financeiro — visível pra quem cadastra/edita produto OU tem
     * `dashboard.financial.view` (ver `User::canViewCatalogCost()`).
     * Vendedor/garantia, que só têm `products.view`, não recebem `cost`.
     */
    private function toPayload(Product $product, bool $canViewCost): array
    {
        $payload = [
            'id' => $product->id,
            'brandId' => $product->brand_id,
            'modelId' => $product->model_id,
            'brand' => $product->brand?->name,
            'model' => $product->watchModel?->name,
            'categoryName' => $product->watchModel?->category?->name,
            'categoryHasQuality' => (bool) $product->watchModel?->category?->has_quality,
            'modelQualityName' => $product->watchModel?->quality?->name,
            'price' => (float) $product->price,
            'pricePix' => $product->price_pix !== null ? (float) $product->price_pix : null,
            'priceCard' => $product->price_card !== null ? (float) $product->price_card : null,
            // TASK-005: comissão é sempre visível a quem tem `products.view`
            // (inclui vendedor) — diferente de `cost`, não revela custo/
            // margem, é o valor que o próprio vendedor recebe por unidade.
            'commissionAmount' => $product->commission_amount !== null ? (float) $product->commission_amount : null,
            'stock' => $product->stock,
            'qty' => $product->qty,
            // TASK-024 (ADR-006): `qty` continua sendo o saldo físico;
            // `availableQty` é o que ainda pode ser vendido, descontadas as
            // unidades prometidas a pedidos em aberto.
            'reservedQty' => (int) $product->reserved_qty,
            'availableQty' => $product->availableQty(),
        ];

        if ($canViewCost) {
            $payload['cost'] = (float) $product->cost;
        }

        return $payload;
    }
}
