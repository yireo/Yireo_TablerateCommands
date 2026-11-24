<?php declare(strict_types=1);

namespace Yireo\TablerateCommands\Console;

use Symfony\Component\Console\Formatter\OutputFormatter;

class CellFormatter
{
    public function format(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $value = (string)preg_replace('/[\x00-\x1F\x7F]/', '', (string)$value);

        return OutputFormatter::escape($value);
    }
}
