<?php
namespace Rbalzs\Misc\Command;

use Symfony\Component\Console\Command\Command;
use Magento\Framework\Exception\LocalizedException;

use Magento\Framework\Registry;
use Magento\Framework\App\State;
use Magento\Framework\App\ResourceConnection;

use Zend\Log\Writer\Stream;

/**
 * Abstract Command with some infrastructure(logging,set magento area code,etc) methods.
 */
abstract class BaseCommand extends Command
{
    /**
     * Logger implementation with Zend logger which allows log to specific files.
     *
     * @var Stream
     */
    private $zendLogger;

    /**
     * @var State
     */
    private $appState;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * Connection, used to perform direct SQL queries.
     */
    protected $connection;

    /**
     * Command Constructor.
     *
     * @param State $appState
     * @param Registry $registry
     * @param ResourceConnection $resourceConnection
     * @param String $zendLoggerFileLocation
     */
    public function __construct(
        State $appState,
        Registry $registry,
        ResourceConnection $resourceConnection,
        $zendLoggerFileLocation
    ) {
        $this->appState = $appState;
        $this->registry = $registry;
        $this->connection = $resourceConnection->getConnection(ResourceConnection::DEFAULT_CONNECTION);
        $writer = new \Zend\Log\Writer\Stream(BP . $zendLoggerFileLocation);
        $this->zendLogger = new \Zend\Log\Logger();
        $this->zendLogger->addWriter($writer);
        parent::__construct();
    }

    /**
     * Logs with INFO severity.
     *
     * @param string $message message to log.
     */
    protected function logInfo($message)
    {
        $this->zendLogger->info($message);
    }

    /**
     * Logs with ERROR severity.
     *
     * @param string $message message to log.
     */
    protected function logError($message)
    {
        $this->zendLogger->err($message);
    }

    /**
     * Avoid magento error 'area code not set', the catch statement is intentionally
     * empty to bypass the 'area code' error.
     *
     * Implementation note => this method should be executed on execute() method rather
     * than constructor(), if not, seems that magento breaks its normal flow.
     */
    protected function setAreaCode()
    {
        try {
            $this->appState->setAreaCode('global');
        } catch (LocalizedException $e) {
        }
    }
}
