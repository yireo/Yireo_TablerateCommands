# Yireo_TablerateCommands

**A Magento 2 module offering CLI commands to manipulate table rates**

### Usage
List all existing tablerates:
```bash
bin/magento yireo_tablerates:list
```

Export all existing tablerates with website ID `1`:
```bash
bin/magento yireo_tablerates:export 1 test.csv
bin/magento yireo_tablerates:export 1 test.csv --overwrite=1
```

Import tablerates with website ID `1`:
```bash
bin/magento yireo_tablerates:import 1 test.csv
bin/magento yireo_tablerates:import 1 test.csv --overwrite=1
```
