<?php declare(strict_types=1);

namespace Yireo\TablerateCommands\Console\Command;

use InvalidArgumentException;
use Magento\OfflineShipping\Model\ResourceModel\Carrier\Tablerate\CollectionFactory as TablerateCollectionFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yireo\TablerateCommands\Console\CellFormatter;

class TablerateListCommand extends Command
{
    private const PAGE_SIZE = 500;
    private const TRIM_CHARACTERS = " \f\n\r\t\v\x00";

    public function __construct(
        private readonly TablerateCollectionFactory $tablerateCollectionFactory,
        private readonly CellFormatter $cellFormatter,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('yireo_tablerates:list');
        $this->setDescription('List all existing table rates');
        $this->addOption('website_id', null, InputOption::VALUE_REQUIRED, 'Only list table rates of this website ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $websiteId = $this->getWebsiteId($input);
        } catch (InvalidArgumentException $exception) {
            $output->writeln('<error>'.OutputFormatter::escape($exception->getMessage()).'</error>');

            return Command::INVALID;
        }

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

        $page = 1;
        do {
            $collection = $this->tablerateCollectionFactory->create();
            if ($websiteId !== null) {
                $collection->addFieldToFilter('website_id', ['eq' => $websiteId]);
            }

            $collection->setOrder('pk', 'ASC');
            $collection->setPageSize(self::PAGE_SIZE);
            $collection->setCurPage($page);

            foreach ($collection as $tablerate) {
                $table->addRow(array_map([$this->cellFormatter, 'format'], [
                    $tablerate->getPk(),
                    $tablerate->getWebsiteId(),
                    $tablerate->getDestCountryId(),
                    $tablerate->getDestRegionId(),
                    $tablerate->getDestZip(),
                    $tablerate->getConditionName(),
                    $tablerate->getConditionValue(),
                    $tablerate->getPrice(),
                    $tablerate->getCost(),
                ]));
            }

            $lastPage = $collection->getLastPageNumber();
            $page++;
        } while ($page <= $lastPage);

        $table->render();

        return Command::SUCCESS;
    }

    private function getWebsiteId(InputInterface $input): ?int
    {
        $websiteId = $input->getOption('website_id');
        if ($websiteId === null) {
            return null;
        }

        $websiteId = trim((string)$websiteId, self::TRIM_CHARACTERS);
        if (false === ctype_digit($websiteId)) {
            throw new InvalidArgumentException((string)__('Website ID must be a positive number'));
        }

        return (int)$websiteId;
    }
}
