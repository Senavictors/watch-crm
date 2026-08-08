<?php

namespace Tests\Unit;

use App\Support\AiSummarySelectionValidator;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class AiSummarySelectionValidatorTest extends TestCase
{
    public function test_evaluation_dataset_rejects_hallucinated_and_malformed_selections(): void
    {
        $cases = json_decode(
            file_get_contents(__DIR__.'/../Fixtures/ai-summary-evaluation.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $allowed = [
            'financial.revenue',
            'financial.net_result',
            'sales.volume',
            'shipping.queue',
            'returns.open',
            'waitlist.active',
        ];

        foreach ($cases as $case) {
            try {
                $result = AiSummarySelectionValidator::validate($case['factIds'], $allowed);
                $this->assertTrue($case['valid'], $case['name'].' deveria ser rejeitado.');
                $this->assertSame($case['factIds'], $result, $case['name']);
            } catch (UnexpectedValueException) {
                $this->assertFalse($case['valid'], $case['name'].' deveria ser aceito.');
            }
        }
    }
}
