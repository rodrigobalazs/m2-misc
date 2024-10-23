## Magento 2: Misc Examples

This repository contains a Magento 2 Command RetrieveDataCommand.php which could be used to quickly retrieve a bunch of magento 2 concepts ( Customers, CMS Pages, Attributes )
for testing purposes. In other hand, the idea it´s show usage of common objects like SearchCriteria, Repositories, etc.

### 🔧 Technology Stack

###### Magento 2
###### PHP
###### Warden
###### Composer

### ⚒️ Getting Started

```bash
# clone and install the module
git clone https://github.com/rodrigobalazs/m2-misc.git;
composer require rbalazs/misc;
bin/magento module:enable RBalazs_Misc;
bin/magento setup:upgrade;

# executes the Command via CLI
bin/magento cli:retrievedata;
```