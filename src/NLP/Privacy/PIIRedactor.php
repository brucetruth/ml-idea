<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Privacy;

final class PIIRedactor
{
    /**
     * @param array<string, string> $replacements
     */
    public function __construct(private readonly array $replacements = [
        'email' => '[EMAIL]',
        'phone' => '[PHONE]',
        'url' => '[URL]',
        'card' => '[CARD]',
    ]) {
    }

    public function redact(string $text): string
    {
        $email = $this->replacements['email'] ?? '[EMAIL]';
        $phone = $this->replacements['phone'] ?? '[PHONE]';
        $url = $this->replacements['url'] ?? '[URL]';
        $card = $this->replacements['card'] ?? '[CARD]';

        $out = $text;
        $out = (string) preg_replace('/\b[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[A-Za-z]{2,}\b/u', $email, $out);
        $out = (string) preg_replace('/\b(?:\+?[0-9][0-9\-\s().]{7,}[0-9])\b/u', $phone, $out);
        $out = (string) preg_replace('/\bhttps?:\/\/[^\s<>"]+/u', $url, $out);

        $out = (string) preg_replace_callback('/\b(?:\d[ -]*?){13,19}\b/u', static function (array $m) use ($card): string {
            $digits = preg_replace('/\D+/', '', $m[0]) ?? '';
            if ($digits !== '' && self::luhnCheck($digits)) {
                return $card;
            }
            return $m[0];
        }, $out);

        return $out;
    }

    private static function luhnCheck(string $digits): bool
    {
        $sum = 0;
        $alt = false;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $n = (int) $digits[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alt = !$alt;
        }

        return ($sum % 10) === 0;
    }
}
