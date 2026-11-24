<?php declare(strict_types=1);

namespace Yireo\TablerateCommands\Test\Integration\Console\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Yireo\TablerateCommands\Console\Command\TablerateListCommand;
use Yireo\TablerateCommands\Test\Integration\TablerateTestTrait;

/**
 * @magentoDbIsolation disabled
 */
class TablerateListCommandTest extends TestCase
{
    use TablerateTestTrait;

    public function testListsTablerates(): void
    {
        $this->addTablerate(0, 'DE', '*', 8);
        $this->addTablerate(1, 'NL', '1234', 9);

        $commandTester = $this->getCommandTester(TablerateListCommand::class);
        $commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString('DE', $commandTester->getDisplay());
        $this->assertStringContainsString('1234', $commandTester->getDisplay());
    }

    public function testFiltersByWebsite(): void
    {
        $this->addTablerate(0, 'DE', '*', 8);
        $this->addTablerate(1, 'NL', '1234', 9);

        $commandTester = $this->getCommandTester(TablerateListCommand::class);
        $commandTester->execute(['--website_id' => '1']);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringNotContainsString('DE', $commandTester->getDisplay());
        $this->assertStringContainsString('1234', $commandTester->getDisplay());
    }

    public function testRejectsInvalidWebsite(): void
    {
        $commandTester = $this->getCommandTester(TablerateListCommand::class);
        $commandTester->execute(['--website_id' => '1 OR 1=1']);

        $this->assertSame(Command::INVALID, $commandTester->getStatusCode());
    }

    public function testEscapesConsoleMarkupAndControlCharacters(): void
    {
        $this->addTablerate(1, 'NL', "<error>\x1b[", 9);

        $commandTester = $this->getCommandTester(TablerateListCommand::class);
        $commandTester->execute([]);

        $this->assertStringContainsString('<error>[', $commandTester->getDisplay());
        $this->assertStringNotContainsString("\x1b", $commandTester->getDisplay());
    }
}
