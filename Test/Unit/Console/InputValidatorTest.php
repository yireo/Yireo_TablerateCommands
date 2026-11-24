<?php declare(strict_types=1);

namespace Yireo\TablerateCommands\Test\Unit\Console;

use InvalidArgumentException;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Yireo\TablerateCommands\Console\InputValidator;

class InputValidatorTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/yireo-tablerate-commands-'.uniqid();
        mkdir($this->root.'/pub/media', 0777, true);
        mkdir($this->root.'/app/etc', 0777, true);
        mkdir($this->root.'/var', 0777, true);
    }

    protected function tearDown(): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($this->root);
    }

    #[DataProvider('getOverwriteValues')]
    public function testIsOverwrite(array $parameters, bool $expected): void
    {
        $this->assertSame($expected, $this->getInputValidator()->isOverwrite($this->getInput($parameters)));
    }

    public static function getOverwriteValues(): array
    {
        return [
            'not passed' => [[], false],
            'bare flag' => [['--overwrite' => null], true],
            'one' => [['--overwrite' => '1'], true],
            'true' => [['--overwrite' => 'true'], true],
            'yes' => [['--overwrite' => 'yes'], true],
            'zero' => [['--overwrite' => '0'], false],
            'false' => [['--overwrite' => 'false'], false],
            'no' => [['--overwrite' => 'no'], false],
        ];
    }

    public function testIsOverwriteRejectsInvalidValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->getInputValidator()->isOverwrite($this->getInput(['--overwrite' => 'maybe']));
    }

    public function testGetWebsiteId(): void
    {
        $this->assertSame(0, $this->getInputValidator()->getWebsiteId($this->getInput(['website_id' => '0'])));
        $this->assertSame(1, $this->getInputValidator()->getWebsiteId($this->getInput(['website_id' => ' 1 '])));
    }

    #[DataProvider('getInvalidWebsiteIds')]
    public function testGetWebsiteIdRejectsInvalidValues(string $websiteId): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->getInputValidator()->getWebsiteId($this->getInput(['website_id' => $websiteId]));
    }

    public static function getInvalidWebsiteIds(): array
    {
        return [
            'empty' => [''],
            'negative' => ['-1'],
            'sql injection' => ['1" OR "1"="1'],
            'decimal' => ['1.5'],
            'code' => ['base'],
            'unknown' => ['999'],
        ];
    }

    public function testGetWritableCsvFile(): void
    {
        $csvFile = $this->root.'/var/export.csv';
        $this->assertSame(
            realpath($this->root.'/var').'/export.csv',
            $this->getInputValidator()->getWritableCsvFile($this->getInput(['csv_file' => $csvFile]), false)
        );
    }

    #[DataProvider('getInvalidWritablePaths')]
    public function testGetWritableCsvFileRejectsInvalidPaths(string $path, bool $overwrite): void
    {
        touch($this->root.'/var/existing.csv');
        symlink($this->root.'/app/etc/env.php', $this->root.'/var/link.csv');

        $this->expectException(InvalidArgumentException::class);
        $this->getInputValidator()->getWritableCsvFile(
            $this->getInput(['csv_file' => $this->root.'/'.$path]),
            $overwrite
        );
    }

    public static function getInvalidWritablePaths(): array
    {
        return [
            'php file' => ['var/export.php', true],
            'no extension' => ['var/export', true],
            'pub folder' => ['pub/export.csv', true],
            'pub subfolder' => ['pub/media/export.csv', true],
            'app etc folder' => ['app/etc/export.csv', true],
            'traversal into pub' => ['var/../pub/export.csv', true],
            'missing folder' => ['missing/export.csv', true],
            'existing without overwrite' => ['var/existing.csv', false],
            'symbolic link' => ['var/link.csv', true],
        ];
    }

    public function testGetReadableCsvFile(): void
    {
        touch($this->root.'/var/import.csv');
        $input = $this->getInput(['csv_file' => $this->root.'/var/import.csv']);

        $this->assertSame($this->root.'/var/import.csv', $this->getInputValidator()->getReadableCsvFile($input));
    }

    public function testGetReadableCsvFileRejectsNonCsvFile(): void
    {
        touch($this->root.'/app/etc/env.php');

        $this->expectException(InvalidArgumentException::class);
        $this->getInputValidator()->getReadableCsvFile($this->getInput(['csv_file' => $this->root.'/app/etc/env.php']));
    }

    public function testGetReadableCsvFileRejectsMissingFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->getInputValidator()->getReadableCsvFile($this->getInput(['csv_file' => $this->root.'/var/missing.csv']));
    }

    private function getInput(array $parameters): ArrayInput
    {
        return new ArrayInput(array_merge(['website_id' => '1', 'csv_file' => 'test.csv'], $parameters), new InputDefinition([
            new InputArgument('website_id', InputArgument::REQUIRED),
            new InputArgument('csv_file', InputArgument::REQUIRED),
            new InputOption('overwrite', null, InputOption::VALUE_OPTIONAL, '', false),
        ]));
    }

    private function getInputValidator(): InputValidator
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getWebsite')->willReturnCallback(function (int $websiteId) {
            if (!in_array($websiteId, [0, 1], true)) {
                throw new NoSuchEntityException();
            }

            $website = $this->createMock(WebsiteInterface::class);
            $website->method('getId')->willReturn($websiteId);

            return $website;
        });

        $directoryList = $this->createMock(DirectoryList::class);
        $directoryList->method('getRoot')->willReturn($this->root);

        return new InputValidator($storeManager, $directoryList);
    }
}
