<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Math;

use ML\IDEA\Exceptions\InvalidArgumentException;

final class ExpressionEvaluator
{
    /**
     * @return array{result: float, normalized: string}
     */
    public function evaluate(string $expression): array
    {
        $tokens = $this->tokenize($expression);
        $rpn = $this->toRpn($tokens);
        $result = $this->evalRpn($rpn);

        return ['result' => $result, 'normalized' => trim($expression)];
    }

    /** @return array<int, string> */
    private function tokenize(string $expr): array
    {
        $pattern = '/\s*([A-Za-z_][A-Za-z0-9_]*|\d+\.\d+|\d+|\.|\+|\-|\*|\/|\^|\(|\)|,)\s*/';
        preg_match_all($pattern, $expr, $m);
        $tokens = $m[1];
        if ($tokens === []) {
            throw new InvalidArgumentException('Invalid math expression.');
        }

        return $tokens;
    }

    /** @param array<int, string> $tokens @return array<int, string> */
    private function toRpn(array $tokens): array
    {
        $out = [];
        $ops = [];
        $prev = null;

        foreach ($tokens as $token) {
            if (is_numeric($token)) {
                $out[] = $token;
                $prev = 'num';
                continue;
            }

            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $token) === 1) {
                if (in_array(strtolower($token), ['pi', 'e'], true)) {
                    $out[] = strtolower($token);
                    $prev = 'num';
                } else {
                    $ops[] = strtolower($token);
                    $prev = 'func';
                }
                continue;
            }

            if ($token === ',') {
                while ($ops !== [] && end($ops) !== '(') {
                    $out[] = array_pop($ops);
                }
                continue;
            }

            if ($token === '(') {
                $ops[] = $token;
                $prev = '(';
                continue;
            }

            if ($token === ')') {
                while ($ops !== [] && end($ops) !== '(') {
                    $out[] = array_pop($ops);
                }
                if ($ops === [] || end($ops) !== '(') {
                    throw new InvalidArgumentException('Mismatched parentheses in expression.');
                }
                array_pop($ops);

                if ($ops !== [] && preg_match('/^[a-z_]/', (string) end($ops)) === 1) {
                    $out[] = array_pop($ops);
                }
                $prev = ')';
                continue;
            }

            if (in_array($token, ['+', '-', '*', '/', '^'], true)) {
                $op = $token;
                if ($op === '-' && ($prev === null || in_array($prev, ['op', '(', 'func'], true))) {
                    $op = 'u-';
                }

                while ($ops !== [] && $this->isOperator((string) end($ops))) {
                    $top = (string) end($ops);
                    if (($this->isRightAssoc($op) && $this->precedence($op) < $this->precedence($top))
                        || (!$this->isRightAssoc($op) && $this->precedence($op) <= $this->precedence($top))) {
                        $out[] = array_pop($ops);
                        continue;
                    }
                    break;
                }

                $ops[] = $op;
                $prev = 'op';
                continue;
            }
        }

        while ($ops !== []) {
            $op = array_pop($ops);
            if ($op === '(' || $op === ')') {
                throw new InvalidArgumentException('Mismatched parentheses in expression.');
            }
            $out[] = $op;
        }

        return $out;
    }

    /** @param array<int, string> $rpn */
    private function evalRpn(array $rpn): float
    {
        $stack = [];

        foreach ($rpn as $token) {
            if (is_numeric($token)) {
                $stack[] = (float) $token;
                continue;
            }

            if ($token === 'pi') {
                $stack[] = M_PI;
                continue;
            }
            if ($token === 'e') {
                $stack[] = M_E;
                continue;
            }

            if ($this->isOperator($token)) {
                if ($token === 'u-') {
                    $a = array_pop($stack);
                    if ($a === null) {
                        throw new InvalidArgumentException('Invalid unary operation.');
                    }
                    $stack[] = -$a;
                    continue;
                }

                $b = array_pop($stack);
                $a = array_pop($stack);
                if ($a === null || $b === null) {
                    throw new InvalidArgumentException('Invalid binary operation.');
                }

                $stack[] = match ($token) {
                    '+' => $a + $b,
                    '-' => $a - $b,
                    '*' => $a * $b,
                    '/' => $b == 0.0 ? throw new InvalidArgumentException('Division by zero.') : $a / $b,
                    '^' => $a ** $b,
                    default => throw new InvalidArgumentException('Unknown operator.'),
                };
                continue;
            }

            $stack = $this->applyFunction($token, $stack);
        }

        if (count($stack) !== 1) {
            throw new InvalidArgumentException('Failed to evaluate expression.');
        }

        return (float) $stack[0];
    }

    /** @param array<int, float> $stack @return array<int, float> */
    private function applyFunction(string $fn, array $stack): array
    {
        $pop = static function () use (&$stack): float {
            $v = array_pop($stack);
            if ($v === null) {
                throw new InvalidArgumentException('Invalid function arguments.');
            }
            return (float) $v;
        };

        switch ($fn) {
            case 'sin': $stack[] = sin($pop()); return $stack;
            case 'cos': $stack[] = cos($pop()); return $stack;
            case 'tan': $stack[] = tan($pop()); return $stack;
            case 'asin': $stack[] = asin($pop()); return $stack;
            case 'acos': $stack[] = acos($pop()); return $stack;
            case 'atan': $stack[] = atan($pop()); return $stack;
            case 'sqrt': $stack[] = sqrt($pop()); return $stack;
            case 'abs': $stack[] = abs($pop()); return $stack;
            case 'ln': $stack[] = log($pop()); return $stack;
            case 'log': $stack[] = log10($pop()); return $stack;
            case 'exp': $stack[] = exp($pop()); return $stack;
            case 'floor': $stack[] = floor($pop()); return $stack;
            case 'ceil': $stack[] = ceil($pop()); return $stack;
            case 'round': $stack[] = round($pop()); return $stack;
            case 'pow':
                $b = $pop();
                $a = $pop();
                $stack[] = $a ** $b;
                return $stack;
            case 'min':
                $b = $pop();
                $a = $pop();
                $stack[] = min($a, $b);
                return $stack;
            case 'max':
                $b = $pop();
                $a = $pop();
                $stack[] = max($a, $b);
                return $stack;
            default:
                throw new InvalidArgumentException(sprintf('Unsupported function: %s', $fn));
        }
    }

    private function isOperator(string $token): bool
    {
        return in_array($token, ['+', '-', '*', '/', '^', 'u-'], true);
    }

    private function precedence(string $op): int
    {
        return match ($op) {
            'u-' => 5,
            '^' => 4,
            '*', '/' => 3,
            '+', '-' => 2,
            default => 1,
        };
    }

    private function isRightAssoc(string $op): bool
    {
        return in_array($op, ['^', 'u-'], true);
    }
}
