<?php declare(strict_types=1);

namespace Yireo\TablerateCommands\Test\Integration\Console\Command;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Yireo\TablerateCommands\Console\Command\TablerateExportCommand;
use Yireo\TablerateCommands\Console\Command\TablerateImportCommand;
use Yireo\TablerateCommands\Test\Integration\TablerateTestTrait;

/**
 * @magentoDbIsolation disabled
 */
class TablerateExportCommandTest extends TestCase
{
    use TablerateTestTrait;

    public function testExportsTableratesOfWebsite(): void
    {
        $this->addTablerate(0, 'DE', '*', 8);
        $this->addTablerate(1, 'NL', '1234', 9);
        $csvFile = $this->tmpFolder.'/export.csv';

        $commandTester = $this->getCommandTester(TablerateExportCommand::class);
        $commandTester->execute(['website_id' => '1', 'csv_file' => $csvFile]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode(), $commandTester->getDisplay());
        $lines = file($csvFile, FILE_IGNORE_NEW_LINES);
        $this->assertCount(2, $lines);
        $this->assertStringStartsWith('Country,', $lines[0]);
        $this->assertSame('NL,*,1234,0.0000,9.0000,0.0000', $lines[1]);
    }

    public function testEscapesFormulasInTextColumns(): void
    {
        $this->addTablerate(1, 'NL', '=1+1', 9);
        $csvFile = $this->tmpFolder.'/export.csv';

        $commandTester = $this->getCommandTester(TablerateExportCommand::class);
        $commandTester->execute(['website_id' => '1', 'csv_file' => $csvFile]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode(), $commandTester->getDisplay());
        $this->assertStringContainsString("NL,*,'=1+1,0.0000,9.0000,0.0000", (string)file_get_contents($csvFile));
    }

    public function testExportCanBeImportedAgain(): void
    {
        $this->addTablerate(1, 'NL', '1234', 9);
        $this->addTablerate(1, 'BE', '*', 4);
        $csvFile = $this->tmpFolder.'/export.csv';

        $exportTester = $this->getCommandTester(TablerateExportCommand::class);
        $exportTester->execute(['website_id' => '1', 'csv_file' => $csvFile]);
        $before = $this->getTablerates(1);

        $importTester = $this->getCommandTester(TablerateImportCommand::class);
        $importTester->execute(['website_id' => '1', 'csv_file' => $csvFile, '--overwrite' => null]);

        $this->assertSame(Command::SUCCESS, $importTester->getStatusCode(), $importTester->getDisplay());
        $this->assertEqualsCanonicalizing($before, $this->getTablerates(1));
    }

    public function testRefusesExistingFileWithoutOverwrite(): void
    {
        $this->addTablerate(1, 'NL', '*', 9);
        $csvFile = $this->tmpFolder.'/export.csv';
        file_put_contents($csvFile, 'original');

        $commandTester = $this->getCommandTester(TablerateExportCommand::class);
        $commandTester->execute(['website_id' => '1', 'csv_file' => $csvFile, '--overwrite' => 'no']);

        $this->assertSame(Command::INVALID, $commandTester->getStatusCode());
        $this->assertStringContainsString('File already exists', $commandTester->getDisplay());
        $this->assertSame('original', file_get_contents($csvFile));

        $commandTester->execute(['website_id' => '1', 'csv_file' => $csvFile, '--overwrite' => null]);
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode(), $commandTester->getDisplay());
        $this->assertStringStartsWith('Country,', (string)file_get_contents($csvFile));
    }

    #[DataProvider('getForbiddenPaths')]
    public function testRefusesForbiddenPaths(string $path, string $expectedError): void
    {
        $this->addTablerate(1, 'NL', '*', 9);
        $file = Bootstrap::getObjectManager()->get(DirectoryList::class)->getRoot().'/'.$path;

        $commandTester = $this->getCommandTester(TablerateExportCommand::class);
        $commandTester->execute(['website_id' => '1', 'csv_file' => $file, '--overwrite' => '1']);

        $this->assertSame(Command::INVALID, $commandTester->getStatusCode());
        $this->assertStringContainsString($expectedError, $commandTester->getDisplay());
        $this->assertFileDoesNotExist($file);
    }

    public static function getForbiddenPaths(): array
    {
        return [
            'php file' => ['var/yireo-tablerate-commands.php', 'extension ".csv"'],
            'pub folder' => ['pub/yireo-tablerate-commands.csv', 'Refusing to write'],
            'app etc folder' => ['app/etc/yireo-tablerate-commands.csv', 'Refusing to write'],
        ];
    }

    public function testRejectsInvalidWebsiteId(): void
    {
        $commandTester = $this->getCommandTester(TablerateExportCommand::class);
        $commandTester->execute(['website_id' => '1 OR 1=1', 'csv_file' => $this->tmpFolder.'/export.csv']);

        $this->assertSame(Command::INVALID, $commandTester->getStatusCode());
        $this->assertFileDoesNotExist($this->tmpFolder.'/export.csv');
    }
}
