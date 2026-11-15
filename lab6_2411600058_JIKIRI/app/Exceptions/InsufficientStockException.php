<?php

namespace App\Exceptions;

use App\Models\Product;
use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(public readonly Product $product, public readonly int $requested)
    {
        parent::__construct(
            "Only {$product->quantity} unit(s) of {$product->name} in stock, so you cannot remove {$requested}."
        );
    }
}