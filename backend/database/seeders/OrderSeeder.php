<?php

namespace Database\Seeders;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'id' => 1001,
                'customer_id' => 1,
                'created_by_user_id' => 2,
                'seller_user_id' => 3,
                'product_id' => 1,
                'product_name' => 'G-Shock GA-100',
                'channel' => 'Instagram',
                'seller' => 'Josue Vendedor',
                'status' => 'Entregue',
                'sale_price' => 320,
                'cost' => 180,
                'discount' => 0,
                'freight' => 25,
                'channel_fee' => 0,
                'payment_method' => 'PIX',
                'shipping_method' => 'Sedex',
                'tracking_code' => 'BR123456789BR',
                'sale_date' => '2025-01-10',
                'shipped_date' => '2025-01-13',
                'notes' => '',
            ],
            [
                'id' => 1002,
                'customer_id' => 2,
                'created_by_user_id' => 3,
                'seller_user_id' => 3,
                'product_id' => 4,
                'product_name' => 'Seiko 5 Sports SRPD55',
                'channel' => 'Mercado Livre',
                'seller' => 'Josue Vendedor',
                'status' => 'Enviado',
                'sale_price' => 890,
                'cost' => 480,
                'discount' => 50,
                'freight' => 0,
                'channel_fee' => 89,
                'payment_method' => 'Cartão Crédito',
                'shipping_method' => 'Jadlog',
                'tracking_code' => 'JD987654321BR',
                'sale_date' => '2025-01-15',
                'shipped_date' => '2025-01-17',
                'notes' => 'Cliente VIP',
            ],
            [
                'id' => 1003,
                'customer_id' => 3,
                'created_by_user_id' => 1,
                'seller_user_id' => 3,
                'product_id' => 2,
                'product_name' => 'Tissot PRX',
                'channel' => 'WhatsApp',
                'seller' => 'Josue Vendedor',
                'status' => 'Pronto para Envio',
                'sale_price' => 420,
                'cost' => 220,
                'discount' => 20,
                'freight' => 30,
                'channel_fee' => 0,
                'payment_method' => 'PIX',
                'shipping_method' => 'Sedex',
                'tracking_code' => '',
                'sale_date' => '2025-01-18',
                'shipped_date' => null,
                'notes' => '',
            ],
            [
                'id' => 1004,
                'customer_id' => 1,
                'created_by_user_id' => 3,
                'seller_user_id' => 3,
                'product_id' => 3,
                'product_name' => 'Orient Bambino RA-AC0E',
                'channel' => 'Site',
                'seller' => 'Josue Vendedor',
                'status' => 'Pago',
                'sale_price' => 680,
                'cost' => 350,
                'discount' => 0,
                'freight' => 35,
                'channel_fee' => 0,
                'payment_method' => 'Cartão Crédito',
                'shipping_method' => 'Correios PAC',
                'tracking_code' => '',
                'sale_date' => '2025-01-19',
                'shipped_date' => null,
                'notes' => 'Buscar com fornecedor',
            ],
            [
                'id' => 1005,
                'customer_id' => 2,
                'created_by_user_id' => 2,
                'seller_user_id' => 3,
                'product_id' => 5,
                'product_name' => 'Casio Vintage A168WA',
                'channel' => 'Instagram',
                'seller' => 'Josue Vendedor',
                'status' => 'Aguardando Pagamento',
                'sale_price' => 170,
                'cost' => 90,
                'discount' => 0,
                'freight' => 20,
                'channel_fee' => 0,
                'payment_method' => null,
                'shipping_method' => 'Sedex',
                'tracking_code' => '',
                'sale_date' => '2025-01-20',
                'shipped_date' => null,
                'notes' => '',
            ],
            [
                'id' => 1006,
                'customer_id' => 1,
                'created_by_user_id' => 1,
                'seller_user_id' => 3,
                'product_id' => 2,
                'product_name' => 'Omega SpeedMaster · Base ETA + 1 item(ns)',
                'channel' => 'WhatsApp',
                'seller' => 'Josue Vendedor',
                'status' => 'Pago',
                'sale_price' => 570,
                'cost' => 300,
                'discount' => 10,
                'freight' => 15,
                'channel_fee' => 0,
                'payment_method' => 'PIX',
                'shipping_method' => 'Sedex',
                'tracking_code' => '',
                'sale_date' => '2025-01-22',
                'shipped_date' => null,
                'notes' => 'Relógio com caixa vendida separadamente.',
            ],
        ];
        Order::upsert($data, ['id']);

        $now = now();

        $items = [
            ['order_id' => 1001, 'product_id' => 1, 'product_name' => 'TAG HEUER F1 SENNA · Base ETA', 'product_type' => 'WATCH', 'brand_name' => 'TAG HEUER', 'model_name' => 'F1 SENNA', 'quality_name' => 'Base ETA', 'quantity' => 1, 'unit_price' => 320, 'unit_cost' => 180, 'unit_discount' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['order_id' => 1002, 'product_id' => 4, 'product_name' => 'Seiko 5 Sports SRPD55 · Base ETA', 'product_type' => 'WATCH', 'brand_name' => 'Seiko', 'model_name' => '5 Sports SRPD55', 'quality_name' => 'Base ETA', 'quantity' => 1, 'unit_price' => 890, 'unit_cost' => 480, 'unit_discount' => 50, 'created_at' => $now, 'updated_at' => $now],
            ['order_id' => 1003, 'product_id' => 2, 'product_name' => 'Omega SpeedMaster · Base ETA', 'product_type' => 'WATCH', 'brand_name' => 'Omega', 'model_name' => 'SpeedMaster', 'quality_name' => 'Base ETA', 'quantity' => 1, 'unit_price' => 420, 'unit_cost' => 220, 'unit_discount' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['order_id' => 1004, 'product_id' => 3, 'product_name' => 'TISSOT PRX · Base ETA', 'product_type' => 'WATCH', 'brand_name' => 'TISSOT', 'model_name' => 'PRX', 'quality_name' => 'Base ETA', 'quantity' => 1, 'unit_price' => 680, 'unit_cost' => 350, 'unit_discount' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['order_id' => 1005, 'product_id' => 5, 'product_name' => 'Casio Vintage A168WA · Base ETA', 'product_type' => 'WATCH', 'brand_name' => 'Casio', 'model_name' => 'Vintage A168WA', 'quality_name' => 'Base ETA', 'quantity' => 1, 'unit_price' => 170, 'unit_cost' => 90, 'unit_discount' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['order_id' => 1006, 'product_id' => 2, 'product_name' => 'Omega SpeedMaster · Base ETA', 'product_type' => 'WATCH', 'brand_name' => 'Omega', 'model_name' => 'SpeedMaster', 'quality_name' => 'Base ETA', 'quantity' => 1, 'unit_price' => 420, 'unit_cost' => 220, 'unit_discount' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['order_id' => 1006, 'product_id' => 6, 'product_name' => 'Rolex Caixa Rolex', 'product_type' => 'BOX', 'brand_name' => 'Rolex', 'model_name' => 'Caixa Rolex', 'quality_name' => null, 'quantity' => 1, 'unit_price' => 150, 'unit_cost' => 80, 'unit_discount' => 0, 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('order_items')->whereIn('order_id', collect($data)->pluck('id'))->delete();
        DB::table('order_items')->insert($items);
    }
}
