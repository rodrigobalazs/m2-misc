<?php
namespace Rbalzs\Misc\Command;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Magento\Framework\Registry;
use Magento\Framework\App\State;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\ObjectManagerInterface;

use Magento\Cms\Model\PageFactory;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Config\Model\ResourceModel\Config;
use Magento\Shipping\Model\Config\Source\Allmethods;

use Magento\Framework\Exception\LocalizedException;

/**
 * Command used to retrieve several information about magento 2 concepts (Customers, CMS Pages, Attributes, etc) and
 * in other hand, show usage of common objects like SearchCriteria, Repositories, etc..
 *
 * execution:
 * bin/magento cli:retrievedata;
 *
 * log info:
 * tail -f <m2root>/var/log/retrievedata.log;
 */
class RetrieveDataCommand extends BaseCommand
{
    /**
     * Customer Repository.
     *
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * CMS Page Factory.
     *
     * @var PageFactory
     */
    private $pageFactory;

    /**
     * Search Criteria Builder.
     *
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * EAV Model used to retrieve attributes information.
     *
     * @var EavConfig
     */
    private $eavConfig;

    /**
     * Config object, used mostly to read/write inside 'core_config_data' table.
     *
     * @var Config
     */
    private $config;

    /**
     * Sometimes the object injection via constructor() throws errors like 'area code not set',
     * so as a work-around, altought its not a good practice, we need to end up using the
     * object manager to get valid object instances.
     *
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * Represent Shipping Methods (like UPS).
     *
     * @var Allmethods
     */
    private $shippingMethodsOptions;

    const LOG_PATH = '/var/log/retrievedata.log';

    /**
     * Command Constructor.
     *
     * @param State $appState
     * @param Registry $registry
     * @param ResourceConnection $resourceConnection
     * @param CustomerRepositoryInterface $customerRepository
     * @param PageFactory $pageFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param EavConfig $eavConfig
     * @param Config $config
     * @param ObjectManagerInterface $objectManager
     * @param Allmethods $shippingMethodsOptions
     */
    public function __construct(
        State $appState,
        Registry $registry,
        ResourceConnection $resourceConnection,
        CustomerRepositoryInterface $customerRepository,
        PageFactory $pageFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        EavConfig $eavConfig,
        Config $config,
        ObjectManagerInterface $objectManager,
        Allmethods $shippingMethodsOptions
    ) {
        parent::__construct($appState, $registry, $resourceConnection, self::LOG_PATH);
        $this->customerRepository = $customerRepository;
        $this->pageFactory = $pageFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->eavConfig = $eavConfig;
        $this->config = $config;
        $this->objectManager = $objectManager;
        $this->shippingMethodsOptions = $shippingMethodsOptions;
    }

    protected function configure()
    {
        $this->setName('cli:retrievedata')->setDescription('Command line class used to retrieve several M2 info.');
    }

    /**
     * Command entry-point, in order to use it, it´s just a matter to comment/uncomment the
     * operations(methods) to perform.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setAreaCode();
        //$this->retrieveCustomerName('somecustomer@email.com');
        //$this->displayCmsPageIds('some_page_title');
        //$this->allowReorders();
        //$this->retrieveLastQuoteId();
        /*$this->logInfo(__METHOD__ . ' Attribute Value: ' .
            $this->retrieveAttributeValue('catalog_product', 'visibility', 'Not Visible Individually'));*/
        //$this->displayShippingMethods();
        //$this->test();
    }

    /**
     * Retrieves the customer name for the email given as parameter.
     *
     * @param string $email customer email.
     * @return string with the customer name.
     * @throws LocalizedException
     */
    private function retrieveCustomerName($email)
    {
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('email', $email, 'eq')->create();
        // should be a collection of 1 customer, cause email its unique in magento2 ..
        $customers = $this->customerRepository->getList($searchCriteria)->getItems();
        foreach ($customers as $customer) {
            /** @var CustomerInterface $customer */
            $name = $customer->getFirstname()  . ' ' . $customer->getLastname();
            $this->logInfo(__METHOD__ . ' Customer Name: ' . $name);
            return $name;
        }
    }

    /**
     * Shows the CMS Pages id´s which match the title passed as parameter.
     *
     * @param string $title page title.
     */
    private function displayCmsPageIds($title)
    {
        $pages = $this->pageFactory->create()->getCollection();
        $pages->addFieldToSelect(['title','identifier']);
        $pages->addFieldToFilter('is_active', 1);
        $pages->addFieldToFilter('title', ['like' => '%'. $title .'%']);

        foreach ($pages as $page) {
            $this->logInfo(__METHOD__ . ' CMS page identifier: ' . $page['page_id']);
        }
    }

    /**
     * Updates the 'core_config_data' table in order to allow Reordes.
     * Also, this could be done manually from "Stores => Configuration => SALES => Sales => Reorder => Allow Reorder"
     */
    private function allowReorders()
    {
        try {
            $this->config->saveConfig(
                'sales/reorder/allow',
                0,
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                1
            );
            $this->logInfo(__METHOD__ . ' the configuration was saved sucessfully');
        } catch (\Exception $e) {
            $this->logError(__METHOD__ . ' there was an error during the configuration update, exception message: '
                . $e->getMessage());
        }
    }

    /**
     * Retrieves the last Quote identifier, represents the newest Quote created in the system.
     * @return mixed
     */
    private function retrieveLastQuoteId()
    {
        $result = $this->connection->fetchRow("SELECT entity_id
            FROM quote ORDER BY entity_id DESC LIMIT 1");
        $quoteId = $result["entity_id"];
        $this->logInfo(__METHOD__ . ' Newest Quote ID: ' . $quoteId);
        return $quoteId;
    }

    /**
     * Retrieves the EAV Attribute Value for the Code given as parameter.
     *
     * @param $entity entity which the attribute belongs to e.g 'catalog_product'.
     * @param string $attributeCode attribute code e.g 'visibility'.
     * @param string $attributeLabel attribute label e.g 'Not Visible Individually'
     *
     * @throws LocalizedException
     * @return mixed|null string with the attribute value, example '1' (null if no results).
     */
    private function retrieveAttributeValue($entity, $attributeCode, $attributeLabel)
    {
        $attribute = $this->eavConfig->getAttribute($entity, $attributeCode);
        if (empty($attribute)) {
            return null;
        }
        $options = $attribute->getSource()->getAllOptions();
        if (empty($options)) {
            return null;
        }

        foreach ($options as $option) {
            if ($option['label'] == $attributeLabel) {
                return $option['value'];
            }
        }
        return null;
    }

    /**
     * Shows a list of carrier_code/title (e.g ups / United Parcel Service)
     * associated to each available 'Shipping Method' in Magento2.
     */
    private function displayShippingMethods()
    {
        $this->logInfo(__METHOD__ . ' Available shipping methods (carrier_code,title):');
        $shippingMethodsOptions = $this->shippingMethodsOptions->toOptionArray();
        foreach ($shippingMethodsOptions as $k => $v) {
            $this->logInfo(__METHOD__ . ' Carrier Code: ' . $k);
            $this->logInfo(__METHOD__ . ' Title: ' . $v["label"]);
        }
    }

    /**
     * Placeholder to test misc functionality.
     */
    private function test()
    {
        $this->logInfo(__METHOD__ . ' START execution of test() method ..');
    }
}
