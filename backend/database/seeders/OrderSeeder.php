<?php

namespace Database\Seeders;

use App\Models\Order;
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
                'product_id' => 1,
                'product_name' => 'G-Shock GA-100',
                'channel' => 'Instagram',
                'seller' => 'Amanda',
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
                'product_id' => 4,
                'product_name' => 'Seiko 5 Sports SRPD55',
                'channel' => 'Mercado Livre',
                'seller' => 'Josue',
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
                'product_id' => 2,
                'product_name' => 'Tissot PRX',
                'channel' => 'WhatsApp',
                'seller' => 'Victor',
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
                'product_id' => 3,
                'product_name' => 'Orient Bambino RA-AC0E',
                'channel' => 'Site',
                'seller' => 'Josue',
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
                'product_id' => 5,
                'product_name' => 'Casio Vintage A168WA',
                'channel' => 'Instagram',
                'seller' => 'Ana Karolina',
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
        ];
        Order::upsert($data, ['id']);
    }
}
