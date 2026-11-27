<?php

declare(strict_types=1);

namespace Opencart\System\Helper\Alliance\Fiscalization;

require_once __DIR__ . '/fiscalizationvalueresolver.php';

class FiscalizationProductsBuilder
{
    public static function build(array $lines, int $targetKopecks, array $defaults): array
    {
        $errors = [];

        $lines = array_values(array_filter($lines, static function ($line) {
            return (float)($line['weight'] ?? 0) > 0 && (float)($line['quantity'] ?? 0) > 0;
        }));

        if (!$lines) {
            return ['products' => [], 'errors' => [['code' => 'no_products', 'name' => '']]];
        }
        if ($targetKopecks <= 0) {
            return ['products' => [], 'errors' => [['code' => 'invalid_amount', 'name' => '']]];
        }

        $weightSum = 0.0;
        foreach ($lines as $line) {
            $weightSum += (float)$line['weight'];
        }

        $products = [];
        $cumulativeWeight = 0.0;
        $previousCumulative = 0;

        foreach ($lines as $line) {
            $cumulativeWeight += (float)$line['weight'];
            $cumulative = (int)round($targetKopecks * $cumulativeWeight / $weightSum);
            $lineTotal = $cumulative - $previousCumulative;
            $previousCumulative = $cumulative;

            $resolved = FiscalizationValueResolver::resolve($line['attributes'] ?? [], $defaults);
            foreach (FiscalizationValueResolver::validate($resolved) as $code) {
                $errors[] = ['code' => $code, 'name' => (string)$line['name']];
            }

            if ($lineTotal <= 0) {
                continue;
            }

            $base = [
                'id' => (string)$line['id'],
                'name' => (string)$line['name'],
            ];

            foreach (self::splitLine((float)$line['quantity'], $lineTotal) as [$count, $unitKopecks]) {
                $products[] = $base + ['count' => $count, 'unitPrice' => round($unitKopecks / 100, 2)] + $resolved;
            }
        }

        return ['products' => $products, 'errors' => $errors];
    }

    public static function sumKopecks(array $products): int
    {
        $sum = 0;
        foreach ($products as $product) {
            $sum += (int)round($product['count'] * $product['unitPrice'] * 100);
        }

        return $sum;
    }

    private static function splitLine(float $quantity, int $lineTotal): array
    {
        $quantity = round($quantity, 3);

        if (floor($quantity) === $quantity) {
            $count = (int)$quantity;
            $unit = intdiv($lineTotal, $count);
            $rest = $lineTotal - $unit * $count;

            if ($rest === 0) {
                return [[(float)$count, $unit]];
            }

            return [
                [(float)($count - $rest), $unit],
                [(float)$rest, $unit + 1],
            ];
        }

        $unit = (int)round($lineTotal / $quantity);
        if ((int)round($quantity * $unit) === $lineTotal) {
            return [[$quantity, $unit]];
        }

        return [[1.0, $lineTotal]];
    }
}
