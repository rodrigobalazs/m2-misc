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
     * Zend logger.
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
    )
    {
        $this->appState = $appState;
        $this->registry = $registry;
        $this->connection = $resourceConnection->getConnection(ResourceConnection::DEFAULT_CONNECTION);
        $writer = new \Zend\Log\Writer\Stream(BP . $zendLoggerFileLocation);
        $this->zendLogger = new \Zend\Log\Logger();
        $this->zendLogger->addWriter($writer);
        parent::__construct();
    }

    /**
     * Logs the input given as param both on the command line and in an
     * specific log file with an INFO severity.
     *
     * @param $message message to log.
     * @param $outputInterface output interface.
     */
    protected function logInfo($message, $outputInterface){
        $outputInterface->writeln($message);
        $this->zendLogger->info($message);
    }

    /**
     * Logs the input given as param both on the command line and in an
     * specific log file with an ERROR severity.
     *
     * @param $exceptionMessage exception message to log.
     * @param $outputInterface output interface.
     */
    protected function logError($exceptionMessage, $outputInterface){
        $outputInterface->writeln('exception message: ' . $exceptionMessage);
        $this->zendLogger->err($exceptionMessage);
    }

    /**
     * Avoid magento error 'operation is forbidden for current area'.
     */
    protected function setSecureArea(){
        $this->registry->register('isSecureArea', true);
    }

    /**
     * Avoid magento error 'area code not set', the catch statement is intentionally
     * empty to bypass the 'area code' error.
     *
     * implementation note => this method should be executed on execute() method rather
     * than constructor(), if not, seems that magento breaks its normal flow.
     */
    protected function setAreaCode(){
        try {
            $this->appState->setAreaCode('global');
        } catch (LocalizedException $e) {}
    }
}
