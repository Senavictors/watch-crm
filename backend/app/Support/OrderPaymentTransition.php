<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;

/**
 * TASK-003 — confirmação de pagamento por transição de status
 * (docs/domain/financial-rules.md, docs/domain/glossary.md "Pedido pago").
 *
 * `orders.paid_at`/`orders.paid_by_user_id` guardam a confirmação CORRENTE de
 * pagamento, não um histórico — o histórico de transições fica em
 * `AuditLogger` (eventos `EVENT_CONFIRMED`/`EVENT_REVERTED`), preservado
 * mesmo quando estas colunas são apagadas.
 *
 * Regras de negócio:
 * - RN-01: pedido criado já em status "pago" (`OrderMetadata::PAID_STATUSES`)
 *   usa o momento da criação (`applyOnCreate`).
 * - RN-02: ao sair de um status "pago" para um status de pagamento pendente
 *   (`OrderMetadata::PENDING_PAYMENT_STATUSES`), a confirmação corrente é
 *   apagada; uma nova confirmação de pagamento recebe uma data nova (não
 *   restaura a anterior).
 *
 * Não decide autorização — quem pode alterar o status de um pedido já é
 * decidido por `permission:orders.update` + `OrderPolicy::update` (mesma regra
 * de qualquer edição de pedido). Ver TASK-003 para a decisão de manter assim
 * até a TASK-013/ADR-003 formalizarem o papel `owner`.
 */
class OrderPaymentTransition
{
    public const EVENT_CONFIRMED = 'orders.payment_confirmed';

    public const EVENT_REVERTED = 'orders.payment_reverted';

    /**
     * Chamado na criação do pedido (RN-01). Muta o model em memória; quem
     * chama é responsável por persistir dentro da mesma transação do create.
     */
    public static function applyOnCreate(Order $order, User $actor): ?string
    {
        if (! in_array($order->status, OrderMetadata::PAID_STATUSES, true)) {
            return null;
        }

        $order->paid_at = now();
        $order->paid_by_user_id = $actor->id;

        return self::EVENT_CONFIRMED;
    }

    /**
     * Chamado antes de salvar uma atualização de pedido, com o status anterior
     * (capturado antes de aplicar os campos vindos da requisição) e o status já
     * atribuído em `$order->status`. Muta o model em memória; quem chama é
     * responsável por persistir dentro da mesma transação da atualização.
     *
     * Retorna o evento de auditoria a registrar (ou null se a transição não
     * envolveu confirmação/reversão de pagamento).
     */
    public static function apply(Order $order, ?string $previousStatus, User $actor): ?string
    {
        $newStatus = $order->status;

        if ($previousStatus === $newStatus) {
            return null;
        }

        $wasPaid = $previousStatus !== null && in_array($previousStatus, OrderMetadata::PAID_STATUSES, true);
        $isPaid = in_array($newStatus, OrderMetadata::PAID_STATUSES, true);
        $isPending = in_array($newStatus, OrderMetadata::PENDING_PAYMENT_STATUSES, true);

        if ($isPaid && ! $wasPaid) {
            $order->paid_at = now();
            $order->paid_by_user_id = $actor->id;

            return self::EVENT_CONFIRMED;
        }

        if ($isPending && $order->paid_at !== null) {
            $order->paid_at = null;
            $order->paid_by_user_id = null;

            return self::EVENT_REVERTED;
        }

        return null;
    }
}
