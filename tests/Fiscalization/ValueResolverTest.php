<?php

declare(strict_types=1);

use Opencart\System\Helper\Alliance\Fiscalization\FiscalizationValidator;
use Opencart\System\Helper\Alliance\Fiscalization\FiscalizationValueResolver as Resolver;
use PHPUnit\Framework\TestCase;

class ValueResolverTest extends TestCase
{
    private const DEFAULTS = [
        'tax_code' => '2',
        'classification_code' => '2203000000',
        'excise_labels' => 'D1, D2',
        'measurement_code' => '0001',
    ];

    public function testProductValueWins(): void
    {
        $resolved = Resolver::resolve(['tax_code' => '7', 'classification_code' => '1111111111'], self::DEFAULTS);

        $this->assertSame(7, $resolved['taxCode']);
        $this->assertSame('1111111111', $resolved['classificationCode']);
    }

    public function testEmptyProductFallsBackToDefaults(): void
    {
        $resolved = Resolver::resolve([], self::DEFAULTS);

        $this->assertSame(2, $resolved['taxCode']);
        $this->assertSame('2203000000', $resolved['classificationCode']);
        $this->assertSame(['D1', 'D2'], $resolved['exciseLabels']);
        $this->assertSame('0001', $resolved['measurementCode']);
    }

    public function testMixedFieldsAreResolvedIndependently(): void
    {
        $resolved = Resolver::resolve(['tax_code' => '8', 'measurement_code' => '0002'], self::DEFAULTS);

        $this->assertSame(8, $resolved['taxCode']);
        $this->assertSame('2203000000', $resolved['classificationCode']);
        $this->assertSame('0002', $resolved['measurementCode']);
    }

    public function testNothingConfiguredOmitsOptionalFields(): void
    {
        $resolved = Resolver::resolve([], ['measurement_code' => '0001']);

        $this->assertSame(['measurementCode' => '0001'], $resolved);
        $this->assertSame([], Resolver::validate($resolved));
    }

    public function testExciseLabelsOnlyForClassifiedTaxCodes(): void
    {
        $resolved = Resolver::resolve(['tax_code' => '5'], self::DEFAULTS);

        $this->assertArrayNotHasKey('exciseLabels', $resolved);
    }

    public function testClassificationRequiredForTaxCodes(): void
    {
        foreach (['2', '7', '8'] as $taxCode) {
            $resolved = Resolver::resolve(['tax_code' => $taxCode], ['measurement_code' => '0001']);

            $this->assertSame(['classification_required'], Resolver::validate($resolved));
        }
    }

    public function testMeasurementRequired(): void
    {
        $this->assertSame(['measurement_required'], Resolver::validate(Resolver::resolve([], [])));
    }

    public function testValidatorSettings(): void
    {
        $errors = FiscalizationValidator::validate(
            ['tax_code' => '2', 'classification_code' => '', 'measurement_code' => ''],
            true,
            true
        );

        $this->assertSame('error_fiscalization_classification_required', $errors['classification_code']);
        $this->assertSame('error_fiscalization_measurement_code', $errors['measurement_code']);
        $this->assertSame(
            'error_fiscalization_tax_code',
            FiscalizationValidator::validate(['tax_code' => 'abc'])['tax_code']
        );
        $this->assertSame(['A', 'B'], FiscalizationValidator::parseExciseLabels(" A ,\n,B, A"));
    }
}
