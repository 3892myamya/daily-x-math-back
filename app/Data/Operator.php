<?php

namespace App\Data;

enum Operator: int
{
    case ADD = 1;
    case SUB = 2;
    case MUL = 3;
    case DIV = 4;
    public function symbol(): string
    {
        return match($this) {
            self::ADD => '＋',
            self::SUB => '－',
            self::MUL => '×',
            self::DIV => '÷',
        };
    }
    public function apply(float $a, float $b): float
    {
        return match ($this) {
            Operator::ADD => $a + $b,
            Operator::SUB => $a - $b,
            Operator::MUL => $a * $b,
            Operator::DIV => $a / $b
        };
    }
}
