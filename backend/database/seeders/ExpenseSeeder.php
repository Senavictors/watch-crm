<?php

namespace Database\Seeders;

use App\Models\Expense;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * TASK-006 — despesa real informada pelo usuário (memória de perfil do
 * negócio): ~R$5.000/mês em anúncios. Só essa categoria é seedada com dado
 * real; as demais categorias de `ExpenseMetadata::CATEGORIES` ficam sem
 * seed — inventar valores não informados corromperia a demonstração
 * (mesma disciplina de `JosueOrdersSeeder`, que só usa números confirmados).
 *
 * Idempotente: IDs fixos (9000+), upsert — seguro re-executar a cada
 * `migrate --force --seed` automático do container.
 */
class ExpenseSeeder extends Seeder
{
    private const MONTHLY_AD_SPEND = 5000;

    /**
     * Nomes de mês em pt-BR, hardcoded — `APP_LOCALE` do projeto é `en`
     * (`config/app.php`), então `Carbon::translatedFormat()` geraria "August"
     * em vez de "Agosto". Trocar o locale da aplicação é fora do escopo desta
     * task (mudança de config, não de domínio de despesas).
     */
    private const MONTHS_PT_BR = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
    ];

    public function run(): void
    {
        $today = Carbon::today();
        $rows = [];

        // Um lançamento por mês, no primeiro dia do mês, cobrindo os últimos
        // 3 meses (mesma janela de ~90 dias de JosueOrdersSeeder).
        for ($i = 0; $i < 3; $i++) {
            $month = $today->copy()->subMonthsNoOverflow($i)->startOfMonth();
            $monthLabel = self::MONTHS_PT_BR[$month->month].'/'.$month->year;

            $rows[] = [
                'id' => 9000 + $i,
                'category' => 'Marketing e Anúncios',
                'description' => 'Anúncios Instagram/Facebook — '.$monthLabel,
                'amount' => self::MONTHLY_AD_SPEND,
                'expense_date' => $month->toDateString(),
                'created_by_user_id' => 1, // admin
                'created_at' => $month,
                'updated_at' => $month,
            ];
        }

        Expense::upsert($rows, ['id']);
    }
}
