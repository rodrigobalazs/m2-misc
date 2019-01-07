<?php
namespace Rbalzs\Misc\Command;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\SharedCatalog\Api\SharedCatalogRepositoryInterface;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Magento\Framework\Registry;
use Magento\Framework\App\State;
use Magento\Framework\App\ResourceConnection;

use Magento\Cms\Model\PageFactory;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Config\Model\ResourceModel\Config;

/**
 * Command used to retrieve several information about magento 2 concepts (Customers, CMS Pages, Attributes, etc) and
 * in other hand, show usage of common objects like SearchCriteria, Repositories, etc..
 *
 * execution:
 * bin/magento cli:retrievedata;
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
     * Order Repository.
     *
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * Address Repository.
     *
     * @var AddressRepositoryInterface
     */
    private $addressRepository;

    /**
     * Company Repository.
     *
     * @var CompanyRepositoryInterface
     */
    private $companyRepository;

    /**
     * Shared Catalog Repository.
     *
     * @var SharedCatalogRepositoryInterface
     */
    private $sharedCatalogRepository;

    /**
    * Used to log in the command line.
    *
    * @var OutputInterface
    */
    private $outputInterface;

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

    const LOG_PATH = '/var/log/retrievedata.log';

    /**
     * Command Constructor.
     *
     * @param State $appState
     * @param Registry $registry
     * @param ResourceConnection $resourceConnection
     * @param CustomerRepositoryInterface $customerRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param AddressRepositoryInterface $addressRepository
     * @param CompanyRepositoryInterface $companyRepository
     * @param SharedCatalogRepositoryInterface $sharedCatalogRepository
     * @param PageFactory $pageFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param EavConfig $eavConfig
     * @param Config $config
     */
    public function __construct(
        State $appState,
        Registry $registry,
        ResourceConnection $resourceConnection,
        CustomerRepositoryInterface $customerRepository,
        OrderRepositoryInterface $orderRepository,
        AddressRepositoryInterface $addressRepository,
        CompanyRepositoryInterface $companyRepository,
        SharedCatalogRepositoryInterface $sharedCatalogRepository,
        PageFactory $pageFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        EavConfig $eavConfig,
        Config $config
    )
    {
        parent::__construct($appState, $registry, $resourceConnection, self::LOG_PATH);
        $this->customerRepository = $customerRepository;
        $this->orderRepository = $orderRepository;
        $this->addressRepository = $addressRepository;
        $this->companyRepository = $companyRepository;
        $this->sharedCatalogRepository = $sharedCatalogRepository;
        $this->pageFactory = $pageFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->eavConfig = $eavConfig;
        $this->config = $config;
    }

    protected function configure() {
        $this->setName('cli:retrievedata')->setDescription('Command line class used to retrieve several M2 info.');
    }

    /**
     * Command entry-point, it just a matter to comment/uncomment the operations to perform.
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->outputInterface = $output;

        $this->setAreaCode();
        $this->setSecureArea();

        //$this->retrieveCustomerName('some_customer_email');
        //$this->retrieveCustomerEmail(1234);
        //$this->filterCmsPagesByTitle('some_page_title');
        //$this->retrieveAttributeLabel('catalog_product', 'visibility', 1);
        //$this->retrieveCityShippingAddress(52);
        //$this->allowReorders();
        $this->retrieveLastQuoteId();
    }

    /**
     * Retrieves the customer name for the customer email given as parameter.
     *
     * @param $email customer email.
     * @return customer name.
     */
    private function retrieveCustomerName($email){
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('email',$email,'eq')->create();
        // should be a collection of 1 customer, cause email its unique ..
        $customers = $this->customerRepository->getList($searchCriteria)->getItems();
        foreach($customers as $customer) {
            /** @var CustomerInterface $customer */
            $name = $customer->getFirstname()  . ' ' . $customer->getLastname();
            $this->logInfo('customer name: ' . $name, $this->outputInterface);
            return $name;
        }
    }

    /**
     * Retrieves the customer email for the customer identifier given as parameter.
     *
     * @param $customerId customer identifier.
     * @return customer email.
     */
    private function retrieveCustomerEmail($customerId){
        /** @var CustomerInterface $customer */
        $customer = $this->customerRepository->getById($customerId);
        $email = $customer->getEmail();
        $this->logInfo('customer email: ' . $email, $this->outputInterface);
        return $email;
    }

    /**
     * Filter CMS Pages by title.
     *
     * @param $pageTitle page title.
     */
    private function filterCmsPagesByTitle($pageTitle){
        $pages = $this->pageFactory->create()->getCollection();
        $pages->addFieldToSelect(['title','identifier']);
        $pages->addFieldToFilter('is_active', 1);
        $pages->addFieldToFilter('title', array('like' => '%'. $pageTitle .'%'));

        foreach($pages as $page){
            $this->logInfo('CMS page title: ' . $page['title'], $this->outputInterface);
            $this->logInfo('CMS page identifier: ' . $page['page_id'], $this->outputInterface);
        }
    }

    /**
     * Retrieves the attribute label associated to a given attribute value.
     *
     * @param $entity entity which the attribute belongs to, example 'catalog_product'.
     * @param $attributeCode attribute code, example 'visibility'.
     * @param $attributeValue attribute value, example '1'
     *
     * @return attribute label, example 'Not Visible Individually'
     */
    private function retrieveAttributeLabel($entity, $attributeCode, $attributeValue){
        $attribute = $this->eavConfig->getAttribute($entity, $attributeCode);
        if(empty($attribute)){
            return null;
        }
        $options = $attribute->getSource()->getAllOptions();
        if(empty($options)){
            return null;
        }

        foreach ($options as $option) {
            if ($option['value'] == $attributeValue) {
                $this->logInfo('attribute code: ' . $attributeCode, $this->outputInterface);
                $this->logInfo('attribute value: ' . $attributeValue, $this->outputInterface);
                $this->logInfo('attribute label: ' . $option['label'], $this->outputInterface);
                return $option['label'];
            }
        }
        return null;
    }

    /**
     * Retrieves the City associated to a given Shipping Address, which is the address of the Order
     * identifier given as parameter.
     *
     * @param $orderId order identifier
     *
     * @return city
     */
    private function retrieveCityShippingAddress($orderId){
        $order = $this->orderRepository->get($orderId);
        if(empty($order)){
            return null;
        }

        $addressId = $order->getShippingAddress()->getCustomerAddressId();
        $shippingAddress = $this->addressRepository->getById($addressId);
        $city = $shippingAddress->getCity();

        $this->logInfo('city associated to the order shipping address: ' . $city, $this->outputInterface);
        return $city;
    }


    /**
     * Allow reorders; manually it could be applied also from the Magento Admin
     * Stores => Configuration => SALES => Sales => Reorder => Allow Reorder
     */
    private function allowReorders(){
        try {
            $this->config->saveConfig('sales/reorder/allow', 0,
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 1);
            $this->logInfo('the configuration was saved sucessfully', $this->outputInterface);
        } catch(\Exception $e){
            $this->logInfo('there was an error during the configuration update, exception message: '
                . $e->getMessage(), $this->outputInterface);
        }
    }

    /**
     * Retrieves the last Quote identifier, represents the newest Quote created in the system.
     */
    private function retrieveLastQuoteId(){
        $result = $this->connection->fetchRow("SELECT entity_id
            FROM quote ORDER BY entity_id DESC LIMIT 1");
        $quoteId = $result["entity_id"];
        $this->logInfo('newest quote Id: ' . $quoteId, $this->outputInterface);
        return $quoteId;
    }
}
