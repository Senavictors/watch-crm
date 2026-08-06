<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Models\WatchModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Dados de demonstração do negócio real (pedido do usuário em 2026-08-06):
 * marcas/modelos/qualidades/preços tabelados, vendedores e uma base de
 * clientes maior para os pedidos gerados por JosueOrdersSeeder terem sentido.
 *
 * Preço tabelado (RN informada pelo usuário):
 * - Qualidade "Base ETA": R$1.050 à vista (PIX) / R$1.200 no cartão. Comissão
 *   do vendedor: R$40/unidade (TASK-005).
 * - Qualidade "Clone" (ele chamou de "prime/clone"): R$4.000 à vista / R$6.000
 *   no cartão. Comissão do vendedor: R$150/unidade (TASK-005).
 * `cost` (custo) não foi informado — assumido como ~45% do preço à vista,
 * decisão documentada aqui, sem base real do negócio.
 *
 * Marcas de luxo/alta complicação (Patek Philippe, Audemars Piguet, Richard
 * Mille) só entram em Clone, por decisão do usuário (réplica Base ETA não é
 * realista pra esse nível de complicação mecânica). As demais (Rolex, Omega,
 * TAG Heuer, Breitling, Tissot) entram nas duas qualidades.
 *
 * Idempotente: todos os IDs são fixos e os inserts usam upsert — seguro
 * re-executar (inclusive no `migrate --force --seed` automático do container
 * a cada restart, ver `.claude/agents/docker-infra.md`).
 */
class JosueCatalogSeeder extends Seeder
{
    private const WATCHES_CATEGORY_ID = 1;

    private const BOXES_CATEGORY_ID = 2;

    private const BASE_ETA_QUALITY_ID = 1;

    private const CLONE_QUALITY_ID = 2;

    // TASK-005: comissão real do vendedor por unidade, informada pelo usuário
    // junto com o preço tabelado (mesma fonte) — R$40 (Base ETA) / R$150
    // (Prime/Clone). Caixas não têm comissão informada (ver seedBoxes).
    private const BASE_ETA_PRICING = ['cost' => 480, 'price' => 1050, 'price_pix' => 1050, 'price_card' => 1200, 'commission' => 40];

    private const CLONE_PRICING = ['cost' => 1800, 'price' => 4000, 'price_pix' => 4000, 'price_card' => 6000, 'commission' => 150];

    public function run(): void
    {
        $this->seedSellers();
        $this->seedCustomers();
        $newBrandIds = $this->seedNewBrands();
        $this->seedWatchModelsAndProducts($newBrandIds);
        $this->seedBoxes($newBrandIds);
    }

    private function seedSellers(): void
    {
        $now = now();

        // Josué é o proprietário real do negócio e também vende — reaproveita
        // o usuário vendedor de exemplo (id 3) em vez de duplicar. TASK-013:
        // migrado pra role owner (permissões financeiras exclusivas); ele
        // continua "vendável" como vendedor de pedido via
        // UserRole::sellableRoles().
        User::where('id', 3)->update(['name' => 'Josué', 'role' => 'owner']);

        User::upsert([
            [
                'id' => 4,
                'name' => 'Karolina',
                'email' => 'karolina@watchcrm.local',
                'password' => Hash::make('Karolina123456!'),
                'role' => 'vendedor',
                'is_active' => true,
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 5,
                'name' => 'Igor',
                'email' => 'igor@watchcrm.local',
                'password' => Hash::make('Igor123456!'),
                'role' => 'vendedor',
                'is_active' => true,
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['id'], ['name', 'email', 'password', 'role', 'is_active', 'email_verified_at', 'updated_at']);
    }

    /**
     * ~80 clientes adicionais (além dos 3 de exemplo já existentes) — sem
     * isso, 270 pedidos em 3 meses ficariam concentrados em 3 pessoas, o que
     * não é realista pra uma revenda de relógios.
     */
    private function seedCustomers(): void
    {
        if (Customer::where('id', '>=', 100)->exists()) {
            return;
        }

        $faker = \Faker\Factory::create('pt_BR');
        $faker->seed(20260806);

        $sellerIds = [3, 4, 5];
        $rows = [];
        $now = now();

        for ($i = 0; $i < 80; $i++) {
            $rows[] = [
                'id' => 100 + $i,
                'name' => $faker->unique()->name(),
                'phone' => $faker->numerify('119########'),
                'email' => $faker->unique()->safeEmail(),
                'instagram' => '@'.$faker->userName(),
                'owner_user_id' => $sellerIds[$i % count($sellerIds)],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Customer::upsert($rows, ['id']);
    }

    /**
     * @return array<string,int> nome da marca => id
     */
    private function seedNewBrands(): array
    {
        $brands = [
            'Patek Philippe' => 7,
            'Audemars Piguet' => 8,
            'Richard Mille' => 9,
            'Breitling' => 10,
        ];

        Brand::upsert(
            array_map(fn ($name, $id) => ['id' => $id, 'name' => $name], array_keys($brands), $brands),
            ['id'],
            ['name']
        );

        return $brands;
    }

    private function seedWatchModelsAndProducts(array $newBrandIds): void
    {
        // brand_id => lista de nomes de modelo (sem o prefixo da marca).
        $dualTierBrands = [
            1 => ['Carrera', 'Monaco', 'Aquaracer', 'Formula 1', 'Autavia'], // TAG HEUER
            2 => ['Speedmaster Professional Moonwatch', 'Seamaster Diver 300M', 'Seamaster Aqua Terra', 'Constellation Globemaster', 'De Ville Prestige'], // Omega
            3 => ['PRX', 'Seastar 1000/2000', 'Le Locle', 'T-Touch', 'Chemin des Tourelles'], // TISSOT — "PRX" já existe (model id 3, Base ETA)
            6 => ['Submariner', 'Daytona', 'Datejust', 'GMT-Master II', 'Oyster Perpetual'], // Rolex
            $newBrandIds['Breitling'] => ['Navitimer', 'Chronomat', 'Superocean', 'Avenger', 'Premier'],
        ];

        $cloneOnlyBrands = [
            $newBrandIds['Patek Philippe'] => ['Nautilus', 'Aquanaut', 'Calatrava', 'Complications', 'Grand Complications'],
            $newBrandIds['Audemars Piguet'] => ['Royal Oak', 'Royal Oak Offshore', 'Royal Oak Concept', 'Code 11.59', 'Millenary'],
            $newBrandIds['Richard Mille'] => ['RM 11-03', 'RM 27', 'RM 65-01', 'RM 055', 'RM 67-02'],
        ];

        $now = now();
        $modelRows = [];
        $productRows = [];
        $nextId = 101;

        // TASK-004: preço já é decidido só pela qualidade (RN "preço
        // tabelado"), então todo produto da mesma qualidade tem os mesmos
        // valores — variação fica em estoque/origem pra não ficar tudo igual.
        $stockPattern = ['IN_STOCK', 'IN_STOCK', 'IN_STOCK', 'SUPPLIER'];
        $qtyPattern = [3, 5, 2, 0, 4, 6, 0, 3];
        $patternIndex = 0;

        $addModelAndProduct = function (int $brandId, string $modelName, int $qualityId, array $pricing) use (&$modelRows, &$productRows, &$nextId, $now, &$patternIndex, $stockPattern, $qtyPattern) {
            $id = $nextId++;
            $modelRows[] = [
                'id' => $id,
                'brand_id' => $brandId,
                'name' => $modelName,
                'category_id' => self::WATCHES_CATEGORY_ID,
                'quality_id' => $qualityId,
                'quality_key' => $qualityId,
                'image_path' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $stock = $stockPattern[$patternIndex % count($stockPattern)];
            $qty = $stock === 'SUPPLIER' ? 0 : $qtyPattern[$patternIndex % count($qtyPattern)];
            $patternIndex++;
            $productRows[] = [
                'id' => $id,
                'brand_id' => $brandId,
                'model_id' => $id,
                'cost' => $pricing['cost'],
                'price' => $pricing['price'],
                'price_pix' => $pricing['price_pix'],
                'price_card' => $pricing['price_card'],
                'commission_amount' => $pricing['commission'],
                'stock' => $stock,
                'qty' => $qty,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        };

        foreach ($dualTierBrands as $brandId => $models) {
            foreach ($models as $modelName) {
                if ($brandId === 3 && $modelName === 'PRX') {
                    // Já existe (model id 3, Base ETA) — só atualiza o preço
                    // pro novo tabelamento e cria a variante Clone.
                    Product::where('id', 3)->update([
                        'cost' => self::BASE_ETA_PRICING['cost'],
                        'price' => self::BASE_ETA_PRICING['price'],
                        'price_pix' => self::BASE_ETA_PRICING['price_pix'],
                        'price_card' => self::BASE_ETA_PRICING['price_card'],
                        'commission_amount' => self::BASE_ETA_PRICING['commission'],
                    ]);
                    $addModelAndProduct($brandId, $modelName, self::CLONE_QUALITY_ID, self::CLONE_PRICING);

                    continue;
                }

                $addModelAndProduct($brandId, $modelName, self::BASE_ETA_QUALITY_ID, self::BASE_ETA_PRICING);
                $addModelAndProduct($brandId, $modelName, self::CLONE_QUALITY_ID, self::CLONE_PRICING);
            }
        }

        foreach ($cloneOnlyBrands as $brandId => $models) {
            foreach ($models as $modelName) {
                $addModelAndProduct($brandId, $modelName, self::CLONE_QUALITY_ID, self::CLONE_PRICING);
            }
        }

        WatchModel::upsert($modelRows, ['id'], ['brand_id', 'name', 'category_id', 'quality_id', 'quality_key', 'updated_at']);
        Product::upsert($productRows, ['id']);
    }

    /**
     * Caixas por marca (RN informada pelo usuário). Só um preço por caixa —
     * sem PIX/Cartão diferenciado (`price_pix`/`price_card` ficam nulos,
     * caem no preço padrão pra qualquer forma de pagamento — TASK-004).
     * Audemars Piguet, Richard Mille e Breitling ficam sem caixa cadastrada
     * (decisão do usuário — preço não informado pra essas 3).
     *
     * Comissão (TASK-005): não informada pra caixas — `commission_amount`
     * fica nulo, mesmo tratamento de `price_pix`/`price_card` (RN-01 não
     * obriga configurar todo produto).
     */
    private function seedBoxes(array $newBrandIds): void
    {
        $now = now();

        // Rolex já tem model (id 6) e product (id 6) de caixa — só atualiza
        // o preço pro valor real informado.
        Product::where('id', 6)->update(['cost' => 160, 'price' => 400]);

        $boxes = [
            ['id' => 201, 'brand_id' => 1, 'name' => 'Caixa TAG Heuer', 'cost' => 160, 'price' => 400],
            ['id' => 202, 'brand_id' => 2, 'name' => 'Caixa Omega', 'cost' => 200, 'price' => 500],
            ['id' => 203, 'brand_id' => 3, 'name' => 'Caixa Tissot', 'cost' => 160, 'price' => 400],
            ['id' => 204, 'brand_id' => $newBrandIds['Patek Philippe'], 'name' => 'Caixa Patek Philippe', 'cost' => 240, 'price' => 600],
        ];

        $modelRows = [];
        $productRows = [];

        foreach ($boxes as $box) {
            $modelRows[] = [
                'id' => $box['id'],
                'brand_id' => $box['brand_id'],
                'name' => $box['name'],
                'category_id' => self::BOXES_CATEGORY_ID,
                'quality_id' => null,
                'quality_key' => 0,
                'image_path' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $productRows[] = [
                'id' => $box['id'],
                'brand_id' => $box['brand_id'],
                'model_id' => $box['id'],
                'cost' => $box['cost'],
                'price' => $box['price'],
                'price_pix' => null,
                'price_card' => null,
                'stock' => 'IN_STOCK',
                'qty' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        WatchModel::upsert($modelRows, ['id'], ['brand_id', 'name', 'category_id', 'quality_id', 'quality_key', 'updated_at']);
        Product::upsert($productRows, ['id']);
    }
}
