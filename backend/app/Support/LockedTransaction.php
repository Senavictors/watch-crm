<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * TASK-029 — leitura sob lock, autorização, validação, mutação e auditoria
 * no MESMO commit.
 *
 * O padrão que existia antes desta task era: carregar o registro, autorizar,
 * validar a transição e só então abrir `DB::transaction()`, gravando a
 * auditoria depois do commit. Isso abre três buracos (achado 6):
 *
 * 1. **Lost update** — duas requisições partem do mesmo estado e a segunda
 *    sobrescreve a primeira sem perceber.
 * 2. **Histórico impossível** — as duas validam a transição contra o mesmo
 *    status anterior e gravam dois "de A para B" a partir de um A que já não
 *    existia.
 * 3. **Auditoria órfã** — a mutação commita e o registro de auditoria falha
 *    depois, sem forma de desfazer.
 *
 * Aqui o registro é relido com `lockForUpdate()` DENTRO da transação: quem
 * chega depois espera, e ao entrar enxerga o estado já atualizado. O
 * callback recebe a instância bloqueada e roda inteiro dentro do mesmo
 * commit — inclusive `$this->audit(...)`, que passa a poder derrubar a
 * operação em vez de deixar rastro faltando.
 *
 * `attempts = 3` porque o banco pode abortar por deadlock legítimo quando
 * duas transações tocam os mesmos registros; o retry só ocorre em erro de
 * concorrência (regra do próprio `DB::transaction`) e, como a auditoria está
 * dentro, uma tentativa descartada não deixa linha duplicada (CA-04).
 */
class LockedTransaction
{
    /**
     * Devolve o retorno do callback, ou `null` quando o registro não existe
     * — quem chama transforma isso no 404 do próprio recurso.
     *
     * @param  Builder  $query  consulta base (pode trazer `with()` de eager loads)
     * @param  callable(\Illuminate\Database\Eloquent\Model): mixed  $work
     */
    public static function run(Builder $query, int $id, callable $work, int $attempts = 3): mixed
    {
        return DB::transaction(function () use ($query, $id, $work) {
            $record = (clone $query)->whereKey($id)->lockForUpdate()->first();

            if ($record === null) {
                return null;
            }

            return $work($record);
        }, $attempts);
    }
}
