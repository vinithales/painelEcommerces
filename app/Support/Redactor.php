<?php

namespace App\Support;

final class Redactor
{
    public static function clean(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = preg_match('/password|passwd|secret|token|authorization|cookie|api.?key|consumer.?key|private.?key/i', (string) $k) ? '[REDACTED]' : self::clean($v);
            }

            return $out;
        }
        if (! is_string($value)) {
            return $value;
        }
        $value = preg_replace('/-----BEGIN [^-]*PRIVATE KEY-----.*?-----END [^-]*PRIVATE KEY-----/s', '[REDACTED]', $value);
        $value = preg_replace('/(Bearer|Basic)\s+[A-Za-z0-9+\/=._-]+/i', '$1 [REDACTED]', $value);

        return preg_replace('/((?:password|passwd|secret|token|authorization|cookie|api[_-]?key|consumer[_-]?(?:key|secret))["\x27]?\s*[:=]\s*)("[^"]*"|\x27[^\x27]*\x27|[^\s&,;]+)/i', '$1[REDACTED]', $value);
    }
}
