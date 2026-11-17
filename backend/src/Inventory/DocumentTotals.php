<?php

declare(strict_types=1);

namespace App\Inventory;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class DocumentTotals
{
    public function money(mixed $value): string
    {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', (string) $value)) {
            throw new UnprocessableEntityHttpException('Money must be a nonnegative decimal with at most two fractional digits.');
        }

        return (string) BigDecimal::of($value)->toScale(2);
    }

    public function discount(BigDecimal $base, array $data): BigDecimal
    {
        $type = $data['discount_type'] ?? 'fixed';
        if (!in_array($type, ['fixed', 'percent'], true)) {
            throw new UnprocessableEntityHttpException('Invalid discount type.');
        }
        $value = BigDecimal::of($this->money($data['discount_value'] ?? '0'));
        if ('percent' === $type && $value->isGreaterThan(100)) {
            throw new UnprocessableEntityHttpException('Discount percentage cannot exceed 100.');
        }
        $discount = 'percent' === $type ? $base->multipliedBy($value)->dividedBy(100, 2, RoundingMode::HalfUp) : $value;
        if ($discount->isGreaterThan($base)) {
            throw new UnprocessableEntityHttpException('Discount exceeds the amount.');
        }

        return $discount;
    }

    public function calculate(array $items, array $details): array
    {
        $subtotal = BigDecimal::of('0.00');
        $lineDiscount = BigDecimal::of('0.00');
        $tax = BigDecimal::of($this->money($details['tax'] ?? '0'));
        foreach ($items as $line) {
            $base = BigDecimal::of($line['unit_price'])->multipliedBy($line['quantity']);
            $subtotal = $subtotal->plus($base);
            $lineDiscount = $lineDiscount->plus($this->discount($base, $line));
            $tax = $tax->plus($this->money($line['tax'] ?? '0'));
        }
        $discount = $lineDiscount->plus($this->discount($subtotal->minus($lineDiscount), $details));
        $shipping = BigDecimal::of($this->money($details['shipping'] ?? '0'));

        return array_map(static fn (BigDecimal $amount) => (string) $amount->toScale(2), [
            'subtotal' => $subtotal, 'discount' => $discount, 'tax' => $tax,
            'shipping' => $shipping, 'total' => $subtotal->minus($discount)->plus($tax)->plus($shipping),
        ]);
    }
}
