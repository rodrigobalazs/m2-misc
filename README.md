# Magento 2: misc examples

## Installation
```
composer require rbalzs/misc;
bin/magento module:enable Rbalzs_Misc;
bin/magento setup:upgrade;
```
## RetrieveDataCommand

Allow retrieves usefull information about a bunch of magento2 concepts (Customers, CMS Pages, Attributes, etc).
In other hand, the idea it´s show usage of common objects like SearchCriteria, Repositories, etc.

```
bin/magento cli:retrievedata;
```
