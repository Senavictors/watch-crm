<?php

namespace App\Support;

use UnexpectedValueException;

class AiSummarySelectionValidator
{
    public static function validate(mixed $factIds, array $allowedIds): array
    {
        if (! is_array($factIds) || count($factIds) < 3 || count($factIds) > 5) {
            throw new UnexpectedValueException('A seleção deve conter entre 3 e 5 fatos.');
        }

        foreach ($factIds as $id) {
            if (! is_string($id) || ! in_array($id, $allowedIds, true)) {
                throw new UnexpectedValueException('A seleção contém um fato desconhecido.');
            }
        }

        if (count(array_unique($factIds, SORT_STRING)) !== count($factIds)) {
            throw new UnexpectedValueException('A seleção contém fatos duplicados.');
        }

        return array_values($factIds);
    }
}
