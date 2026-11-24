<?php declare(strict_types=1);

namespace Yireo\TablerateCommands\Test\Integration\Console\Command;

use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Console\CommandListInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Yireo\TablerateCommands\Console\Command\TablerateImportCommand;
use Yireo\TablerateCommands\Test\Integration\TablerateTestTrait;

/**
 * @magentoDbIsolation disabled
 */
class TablerateImportCommandTest extends TestCase
{
    use TablerateTestTrait;

    private const HEADER = "Country,Region/State,ZIP/Postal Code,Order Subtotal (and above),Shipping Price,Shipping Cost\n";

    public function testCommandIsRegistered(): void
    {
        $commands = Bootstrap::getObjectManager()->get(CommandListInterface::class)->getCommands();
        $command = $commands['Yireo_TablerateCommands::tableRatesImport'] ?? null;

        $this->assertInstanceOf(TablerateImportCommand::class, $command);
        $this->assertSame('yireo_tablerates:import', $command->getName());
    }

    public function testImportsValidCsv(): void
    {
        $regionId = $this->getRegionId('US', 'CA');
        $csvFile = $this->createCsv("NL,*,,0,5,1\nUSA,CA,90210,10.5,7.25,\nUS,".$regionId.",*,20,3,\n");

        $commandTester = $this->getCommandTester(TablerateImportCommand::class);
        $commandTester->execute(['website_id' => '1', 'csv_file' => $csvFile]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode(), $commandTester->getDisplay());
        $this->assertStringContainsString('Imported 3 tablerates', $commandTester->getDisplay());

        $tablerates = $this->getTablerates(1);
        $this->assertCount(3, $tablerates);
        $this->assertSame(['NL', 0, '*', 0.0, 5.0, 1.0], $this->normalize($tablerates[0]));
        $this->assertSame(['US', $regionId, '90210', 10.5, 7.25, 0.0], $this->normalize($tablerates[1]));
        $this->assertSame(['US', $regionId, '*', 20.0, 3.0, 0.0], $this->normalize($tablerates[2]));
    }

    public function testRefusesToOverwriteWithoutOption(): void
    {
        $this->addTablerate(1, 'NL', '*', 9);
        $csvFile = $this->createCsv("NL,*,*,0,5,0\n");

        $commandTester = $this->getCommandTester(TablerateImportCommand::class);
        $commandTester->execute(['website_id' => '1', 'csv_file' => $csvFile, '--overwrite' => '0']);

        $this->assertSame(Command::INVALID, $commandTester->getStatusCode());
        $this->assertStringContainsString('without allowing overwrites', $commandTester->getDisplay());
        $this->assertSame(9.0, (float)$this->getTablerates(1)[0]['price']);
    }

    #[DataProvider('getOverwriteValues')]
    public function testOverwritesOnlyTheGivenWebsite(?string $overwrite): void
    {
        $this->addTablerate(0, 'DE', '*', 8);
        $this->addTablerate(1, 'NL', '*', 9);
        $csvFile = $this->createCsv("BE,*,*,0,4,0\n");

        $commandTester = $this->getCommandTester(TablerateImportCommand::class);
        $commandTester->execute(['website_id' => '1', 'csv_file' => $csvFile, '--overwrite' => $overwrite]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode(), $commandTester->getDisplay());
        $this->assertSame([['BE', 0, '*', 0.0, 4.0, 0.0]], array_map([$this, 'normalize'], $this->getTablerates(1)));
        $this->assertSame([['DE', 0, '*', 0.0, 8.0, 0.0]], array_map([$this, 'normalize'], $this->getTablerates(0)));
    }

    public static function getOverwriteValues(): array
    {
        return [
            'bare flag' => [null],
            'one' => ['1'],
            'yes' => ['yes'],
        ];
    }

    #[DataProvider('getInvalidWebsiteIds')]
    public function testRejectsInvalidWebsiteId(string $websiteId): void
    {
        $this->addTablerate(0, 'DE', '*', 8);
        $this->addTablerate(1, 'NL', '*', 9);
        $csvFile = $this->createCsv("BE,*,*,0,4,0\n");

        $commandTester = $this->getCommandTester(TablerateImportCommand::class);
        $commandTester->execute(['website_id' => $websiteId, 'csv_file' => $csvFile, '--overwrite' => '1']);

        $this->assertSame(Command::INVALID, $commandTester->getStatusCode());
        $this->assertCount(1, $this->getTablerates(0));
        $this->assertCount(1, $this->getTablerates(1));
    }

    public static function getInvalidWebsiteIds(): array
    {
        return [
            'sql injection' => ['1" OR "1"="1'],
            'negative' => ['-1'],
            'unknown' => ['999999'],
            'empty' => [' '],
        ];
    }

    #[DataProvider('getInvalidRows')]
    public function testAbortsWholeImportOnInvalidRow(string $invalidRow, string $expectedError): void
    {
        $this->addTablerate(1, 'NL', '*', 9);
        $csvFile = $this->createCsv("BE,*,*,0,4,0\n".$invalidRow."\n");

        $commandTester = $this->getCommandTester(TablerateImportCommand::class);
        $commandTester->execute(['website_id' => '1', 'csv_file' => $csvFile, '--overwrite' => '1']);

        $this->assertSame(Command::INVALID, $commandTester->getStatusCode());
        $this->assertStringContainsString('Line 3', $commandTester->getDisplay());
        $this->assertStringContainsString($expectedError, $commandTester->getDisplay());
        $this->assertStringContainsString('no table rates were changed', $commandTester->getDisplay());
        $this->assertSame([['NL', 0, '*', 0.0, 9.0, 0.0]], array_map([$this, 'normalize'], $this->getTablerates(1)));
    }

    public static function getInvalidRows(): array
    {
        return [
            'unknown country' => ['XX,*,*,0,4,0', 'Unknown country'],
            'unknown iso3 country' => ['XXX,*,*,0,4,0', 'Unknown country'],
            'unknown region' => ['US,XX,*,0,4,0', 'Unknown region'],
            'region of other country' => ['NL,CA,*,0,4,0', 'Unknown region'],
            'long zip' => ['NL,*,12345678901,0,4,0', 'longer than 10'],
            'non numeric price' => ['NL,*,*,0,abc,0', 'Shipping Price'],
            'negative cost' => ['NL,*,*,0,4,-1', 'Shipping Cost'],
            'formula' => ['NL,*,*,=1+1,4,0', 'Order Subtotal'],
        ];
    }

    public function testRollsBackWhenDatabaseInsertFails(): void
    {
        $this->addTablerate(1, 'NL', '*', 9);
        $csvFile = $this->createCsv("BE,*,*,0,4,0\nBE,*,*,0,5,0\n");

        $commandTester = $this->getCommandTester(TablerateImportCommand::class);
        $commandTester->execute(['website_id' => '1', 'csv_file' => $csvFile, '--overwrite' => '1']);

        $this->assertSame(Command::INVALID, $commandTester->getStatusCode());
        $this->assertStringContainsString('Import failed', $commandTester->getDisplay());
        $this->assertSame([['NL', 0, '*', 0.0, 9.0, 0.0]], array_map([$this, 'normalize'], $this->getTablerates(1)));
    }

    #[DataProvider('getInvalidFiles')]
    public function testRejectsInvalidFiles(string $file, string $expectedError): void
    {
        $file = str_replace('{root}', Bootstrap::getObjectManager()->get(DirectoryList::class)->getRoot(), $file);
        $commandTester = $this->getCommandTester(TablerateImportCommand::class);
        $commandTester->execute(['website_id' => '1', 'csv_file' => $file]);

        $this->assertSame(Command::INVALID, $commandTester->getStatusCode());
        $this->assertStringContainsString($expectedError, $commandTester->getDisplay());
    }

    public static function getInvalidFiles(): array
    {
        return [
            'non csv file' => ['{root}/app/etc/env.php', 'extension ".csv"'],
            'missing file' => ['{root}/var/does-not-exist.csv', 'No such file'],
        ];
    }

    private function createCsv(string $rows): string
    {
        $csvFile = $this->tmpFolder.'/import.csv';
        file_put_contents($csvFile, self::HEADER.$rows);

        return $csvFile;
    }

    private function getRegionId(string $countryId, string $code): int
    {
        $region = Bootstrap::getObjectManager()->get(RegionFactory::class)->create();
        $region->loadByCode($code, $countryId);

        return (int)$region->getId();
    }

    private function normalize(array $tablerate): array
    {
        return [
            (string)$tablerate['dest_country_id'],
            (int)$tablerate['dest_region_id'],
            (string)$tablerate['dest_zip'],
            (float)$tablerate['condition_value'],
            (float)$tablerate['price'],
            (float)$tablerate['cost'],
        ];
    }
}
