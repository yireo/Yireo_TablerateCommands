<?php declare(strict_types=1);

namespace Yireo\TablerateCommands\Console\Command;

use Magento\OfflineShipping\Model\ResourceModel\Carrier\Tablerate\CollectionFactory as TablerateCollectionFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TablerateListCommand extends Command
{
    public function __construct(
        private TablerateCollectionFactory $tablerateCollectionFactory,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('yireo_tablerates:list');
        $this->setDescription('List all existing table rates');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = new Table($output);
        $table->setHeaders([
            'ID',
            'Website',
            'Country',
            'Region',
            'Postcode',
            'Condition Name',
            'Condition Value',
            'Price',
            'Cost',
        ]);

        $collection =$this->tablerateCollectionFactory->create();

        foreach ($collection as $tablerate) {
            $table->addRow([
                $tablerate->getPk(),
                $tablerate->getWebsiteId(),
                $tablerate->getDestCountryId(),
                $tablerate->getDestRegionId(),
                $tablerate->getDestZip(),
                $tablerate->getConditionName(),
                $tablerate->getConditionValue(),
                $tablerate->getPrice(),
                $tablerate->getCost(),
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
