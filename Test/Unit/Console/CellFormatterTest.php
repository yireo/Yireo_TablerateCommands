<?php declare(strict_types=1);

namespace Yireo\TablerateCommands\Test\Unit\Console;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Yireo\TablerateCommands\Console\CellFormatter;

class CellFormatterTest extends TestCase
{
    #[DataProvider('getValues')]
    public function testFormat(mixed $value, string $expected): void
    {
        $this->assertSame($expected, (new CellFormatter())->format($value));
    }

    public static function getValues(): array
    {
        return [
            'plain text' => ['NL', 'NL'],
            'integer' => [42, '42'],
            'decimal string' => ['12.5000', '12.5000'],
            'null' => [null, ''],
            'array' => [['foo'], ''],
            'ansi escape' => ["\x1b[31mred\x1b[0m", '[31mred[0m'],
            'osc hyperlink' => ["\x1b]8;;https://evil.test\x07click\x1b]8;;\x07", ']8;;https://evil.testclick]8;;'],
            'newlines and tabs' => ["foo\nbar\tbaz\r", 'foobarbaz'],
            'delete character' => ["foo\x7fbar", 'foobar'],
            'multibyte' => ['Zürich', 'Zürich'],
        ];
    }

    #[DataProvider('getFormatterTags')]
    public function testFormatterTagsAreRenderedLiterally(string $value): void
    {
        $formatted = (new CellFormatter())->format($value);

        $this->assertSame($value, (new OutputFormatter(true))->format($formatted));
    }

    public static function getFormatterTags(): array
    {
        return [
            'error tag' => ['<error>Foo</error>'],
            'hyperlink tag' => ['<href=https://evil.test>Foo</>'],
            'color tag' => ['<fg=red;options=blink>Foo</>'],
        ];
    }
}
