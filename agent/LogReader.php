<?php

namespace BringAgent;

use App\Support\Redactor;

class LogReader
{
    public const FILES = ['system.log', 'exception.log', 'debug.log', 'cron.log'];

    public function __construct(private readonly string $root) {}

    public function read(string $file, ?int $cursor = null, int $bytes = 65536): array
    {
        if (! in_array($file, self::FILES, true)) {
            throw new \InvalidArgumentException('File not allowed.');
        }
        $directory = realpath($this->root.'/var/log');
        $path = $directory ? realpath($directory.'/'.$file) : false;
        if (! $path || ! str_starts_with($path, $directory.DIRECTORY_SEPARATOR) || is_link($directory.'/'.$file)) {
            throw new \RuntimeException('Log unavailable.');
        }
        $stream = fopen($path, 'rb');
        if (! $stream) {
            throw new \RuntimeException('Log unavailable.');
        }
        try {
            $size = fstat($stream)['size'];
            $end = $cursor === null ? $size : max(0, min($cursor, $size));
            $start = max(0, $end - max(1024, min($bytes, 262144)));
            fseek($stream, $start);
            $text = $end > $start ? fread($stream, $end - $start) : '';
            // Avoid returning half a line at the chunk boundary.
            if ($start > 0 && ($newline = strpos($text, "\n")) !== false) {
                $text = substr($text, $newline + 1);
                $start += $newline + 1;
            }

            return ['file' => $file, 'text' => Redactor::clean($text), 'cursor' => $start, 'has_more' => $start > 0, 'size' => $size];
        } finally {
            fclose($stream);
        }
    }
}
