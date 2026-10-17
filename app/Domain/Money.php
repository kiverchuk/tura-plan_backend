<?php

namespace App\Domain;

use InvalidArgumentException;

/** Integer minor units; never calculate money with binary floats. */
final readonly class Money
{
    public function __construct(public int $minor, public string $currency)
    {
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Currency must be an ISO 4217 code.');
        }
    }

    public function plus(self $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Cannot add different currencies.');
        }

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function multiplyRatio(int $numerator, int $denominator): self
    {
        if ($denominator <= 0 || $numerator < 0) {
            throw new InvalidArgumentException('Invalid ratio.');
        }
        $product = $this->minor * $numerator;
        $rounded = intdiv($product, $denominator);
        $remainder = $product % $denominator;
        if ($remainder * 2 >= $denominator) {
            $rounded++;
        }

        return new self($rounded, $this->currency);
    }
}
