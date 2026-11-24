<?php declare(strict_types=1);

namespace Yireo\TablerateCommands\Test\Integration;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

trait TablerateTestTrait
{
    private array $backupRows = [];
    private string $tmpFolder = '';

    protected function setUp(): void
    {
        $this->backupRows = $this->getConnection()->fetchAll(
            $this->getConnection()->select()->from($this->getTable())
        );
        $this->getConnection()->delete($this->getTable());

        $this->tmpFolder = sys_get_temp_dir().'/yireo-tablerate-commands-'.uniqid();
        mkdir($this->tmpFolder, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->getConnection()->delete($this->getTable());
        if (!empty($this->backupRows)) {
            $this->getConnection()->insertMultiple($this->getTable(), $this->backupRows);
        }

        foreach (glob($this->tmpFolder.'/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->tmpFolder);
    }

    private function getCommandTester(string $commandClass): CommandTester
    {
        /** @var Command $command */
        $command = Bootstrap::getObjectManager()->create($commandClass);

        return new CommandTester($command);
    }

    private function addTablerate(int $websiteId, string $countryId, string $zip, float $price): void
    {
        $this->getConnection()->insert($this->getTable(), [
            'website_id' => $websiteId,
            'dest_country_id' => $countryId,
            'dest_region_id' => 0,
            'dest_zip' => $zip,
            'condition_name' => 'package_weight',
            'condition_value' => 0,
            'price' => $price,
            'cost' => 0,
        ]);
    }

    private function getTablerates(int $websiteId): array
    {
        $select = $this->getConnection()->select()
            ->from($this->getTable(), ['dest_country_id', 'dest_region_id', 'dest_zip', 'condition_value', 'price', 'cost'])
            ->where('website_id = ?', $websiteId)
            ->order('pk ASC');

        return $this->getConnection()->fetchAll($select);
    }

    private function getConnection(): AdapterInterface
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
    }

    private function getTable(): string
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class)->getTableName('shipping_tablerate');
    }
}
