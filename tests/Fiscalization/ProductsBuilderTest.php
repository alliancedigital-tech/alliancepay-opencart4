<?php

declare(strict_types=1);

use Opencart\System\Helper\Alliance\Fiscalization\FiscalizationProductsBuilder as Builder;
use PHPUnit\Framework\TestCase;

class ProductsBuilderTest extends TestCase
{
    private const DEFAULTS = ['measurement_code' => '0001'];

    private function line(string $id, float $quantity, float $weight, array $attributes = []): array
    {
        return ['id' => $id, 'name' => 'Item ' . $id, 'quantity' => $quantity, 'weight' => $weight, 'attributes' => $attributes];
    }

    public function testSumEqualsTargetWithRoundingRemainder(): void
    {
        $result = Builder::build(
            [$this->line('1', 3, 100.0), $this->line('2', 1, 33.33), $this->line('shipping', 1, 10.0), $this->line('3', 2.5, 7.77)],
            12345,
            self::DEFAULTS
        );

        $this->assertSame([], $result['errors']);
        $this->assertSame(12345, Builder::sumKopecks($result['products']));
    }

    public function testDiscountIsSpreadProportionally(): void
    {
        $result = Builder::build([$this->line('1', 1, 100.0), $this->line('2', 1, 100.0)], 15000, self::DEFAULTS);

        $this->assertSame(75.0, $result['products'][0]['unitPrice']);
        $this->assertSame(75.0, $result['products'][1]['unitPrice']);
    }

    public function testIndivisibleLineIsSplitByOneKopeck(): void
    {
        $result = Builder::build([$this->line('1', 3, 1.0)], 1000, self::DEFAULTS);

        $this->assertCount(2, $result['products']);
        $this->assertSame(1000, Builder::sumKopecks($result['products']));
    }

    public function testServiceLineAndAttributesAreResolved(): void
    {
        $result = Builder::build(
            [
                $this->line('1', 1, 90.0, ['tax_code' => '2', 'classification_code' => '2203000000', 'excise_labels' => 'AA1']),
                $this->line('shipping', 1, 10.0),
            ],
            10000,
            self::DEFAULTS
        );

        $this->assertSame(['AA1'], $result['products'][0]['exciseLabels']);
        $this->assertArrayNotHasKey('taxCode', $result['products'][1]);
        $this->assertSame('shipping', $result['products'][1]['id']);
        $this->assertSame(10000, Builder::sumKopecks($result['products']));
    }

    public function testMissingClassificationIsReported(): void
    {
        $result = Builder::build([$this->line('1', 1, 1.0, ['tax_code' => '7'])], 100, self::DEFAULTS);

        $this->assertSame('classification_required', $result['errors'][0]['code']);
    }

    public function testEmptyInput(): void
    {
        $this->assertSame('no_products', Builder::build([], 100, self::DEFAULTS)['errors'][0]['code']);
        $this->assertSame('invalid_amount', Builder::build([$this->line('1', 1, 1.0)], 0, self::DEFAULTS)['errors'][0]['code']);
    }
}
