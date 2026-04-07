<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            ['id' => 1, 'name' => 'Lucas Ferreira', 'phone' => '11999887766', 'email' => 'lucas@email.com', 'instagram' => '@lucasf_watches', 'owner_user_id' => 3],
            ['id' => 2, 'name' => 'Mariana Costa', 'phone' => '21988776655', 'email' => 'mari@email.com', 'instagram' => '@maricosta', 'owner_user_id' => 3],
            ['id' => 3, 'name' => 'Pedro Alves', 'phone' => '31977665544', 'email' => 'pedro@email.com', 'instagram' => '', 'owner_user_id' => 2],
        ];
        Customer::upsert($data, ['id']);
    }
}
