<?php

namespace Tests\Unit;

use App\Support\ReturnMetadata;
use App\Support\ReturnStatusTransition;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * TASK-017 (RN-03/CA-01) — cobertura do grafo de `ReturnStatusTransition`,
 * isolado da camada HTTP/DB (ver `ReturnControllerTest` para o efeito via
 * API + `return_status_history`).
 */
class ReturnStatusTransitionTest extends TestCase
{
    public static function validTransitionProvider(): array
    {
        return [
            'aguardando -> recebido' => ['Aguardando Recebimento', 'Recebido'],
            'aguardando -> cancelado' => ['Aguardando Recebimento', 'Cancelado'],
            'recebido -> em analise' => ['Recebido', 'Em Análise'],
            'em analise -> enviado ao relojoeiro' => ['Em Análise', 'Enviado ao Relojoeiro'],
            'em analise -> em troca' => ['Em Análise', 'Em Troca'],
            'em analise -> reembolso pendente' => ['Em Análise', 'Reembolso Pendente'],
            'em analise -> recusado' => ['Em Análise', 'Recusado'],
            'enviado ao relojoeiro -> em manutencao' => ['Enviado ao Relojoeiro', 'Em Manutenção'],
            'em manutencao -> retornado' => ['Em Manutenção', 'Retornado'],
            'em manutencao -> recusado' => ['Em Manutenção', 'Recusado'],
            'retornado -> pronto para reenvio' => ['Retornado', 'Pronto para Reenvio'],
            'em troca -> troca aprovada' => ['Em Troca', 'Troca Aprovada'],
            'troca aprovada -> pronto para reenvio' => ['Troca Aprovada', 'Pronto para Reenvio'],
            'reembolso pendente -> reembolso efetuado' => ['Reembolso Pendente', 'Reembolso Efetuado'],
            'reembolso efetuado -> concluido' => ['Reembolso Efetuado', 'Concluído'],
            'pronto para reenvio -> reenviado' => ['Pronto para Reenvio', 'Reenviado'],
            'reenviado -> concluido' => ['Reenviado', 'Concluído'],
        ];
    }

    #[DataProvider('validTransitionProvider')]
    public function test_valid_transitions_are_accepted(string $from, string $to): void
    {
        $this->assertTrue(ReturnStatusTransition::isValid($from, $to));
        $this->assertContains($to, ReturnStatusTransition::validNextStatuses($from));
    }

    public static function invalidTransitionProvider(): array
    {
        return [
            'aguardando -> reembolso efetuado (pula etapas)' => ['Aguardando Recebimento', 'Reembolso Efetuado'],
            'recebido -> reenviado' => ['Recebido', 'Reenviado'],
            'reembolso efetuado -> reembolso pendente (nao recua)' => ['Reembolso Efetuado', 'Reembolso Pendente'],
            'reembolso efetuado -> cancelado' => ['Reembolso Efetuado', 'Cancelado'],
            'troca aprovada -> reembolso pendente' => ['Troca Aprovada', 'Reembolso Pendente'],
            'em manutencao -> em analise (nao recua)' => ['Em Manutenção', 'Em Análise'],
        ];
    }

    #[DataProvider('invalidTransitionProvider')]
    public function test_invalid_transitions_are_rejected(string $from, string $to): void
    {
        $this->assertFalse(ReturnStatusTransition::isValid($from, $to));
        $this->assertNotContains($to, ReturnStatusTransition::validNextStatuses($from));
    }

    public static function terminalStatusProvider(): array
    {
        return [
            ['Concluído'],
            ['Recusado'],
            ['Cancelado'],
        ];
    }

    #[DataProvider('terminalStatusProvider')]
    public function test_terminal_statuses_have_no_valid_next_status(string $status): void
    {
        $this->assertSame([], ReturnStatusTransition::validNextStatuses($status));
    }

    public function test_every_status_is_a_valid_no_op_transition_to_itself(): void
    {
        foreach (ReturnMetadata::STATUSES as $status) {
            $this->assertTrue(ReturnStatusTransition::isValid($status, $status));
        }
    }

    public function test_a_full_happy_path_from_receiving_to_conclusion(): void
    {
        $path = [
            'Aguardando Recebimento',
            'Recebido',
            'Em Análise',
            'Enviado ao Relojoeiro',
            'Em Manutenção',
            'Retornado',
            'Pronto para Reenvio',
            'Reenviado',
            'Concluído',
        ];

        for ($i = 0; $i < count($path) - 1; $i++) {
            $this->assertTrue(
                ReturnStatusTransition::isValid($path[$i], $path[$i + 1]),
                "Esperava transição válida de '{$path[$i]}' para '{$path[$i + 1]}'."
            );
        }
    }

    public function test_a_full_happy_path_for_a_refund(): void
    {
        $path = [
            'Aguardando Recebimento',
            'Recebido',
            'Em Análise',
            'Reembolso Pendente',
            'Reembolso Efetuado',
            'Concluído',
        ];

        for ($i = 0; $i < count($path) - 1; $i++) {
            $this->assertTrue(
                ReturnStatusTransition::isValid($path[$i], $path[$i + 1]),
                "Esperava transição válida de '{$path[$i]}' para '{$path[$i + 1]}'."
            );
        }
    }

    public function test_a_full_happy_path_for_an_exchange(): void
    {
        $path = [
            'Aguardando Recebimento',
            'Recebido',
            'Em Análise',
            'Em Troca',
            'Troca Aprovada',
            'Pronto para Reenvio',
            'Reenviado',
            'Concluído',
        ];

        for ($i = 0; $i < count($path) - 1; $i++) {
            $this->assertTrue(
                ReturnStatusTransition::isValid($path[$i], $path[$i + 1]),
                "Esperava transição válida de '{$path[$i]}' para '{$path[$i + 1]}'."
            );
        }
    }
}
