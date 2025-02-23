<?php
namespace Neos\Flow\Log\ThrowableStorage;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Error\Debugger;
use Neos\Flow\Http\Helper\RequestInformationHelper;
use Neos\Flow\Http\HttpRequestHandlerInterface;
use Neos\Flow\Log\PlainTextFormatter;
use Neos\Flow\Log\ThrowableStorageInterface;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Utility\Files;
use Psr\Http\Message\RequestInterface;

/**
 * Stores detailed information about throwables into files.
 *
 * @phpstan-consistent-constructor
 * @Flow\Proxy(false)
 * @Flow\Autowiring(false)
 */
class FileStorage implements ThrowableStorageInterface
{
    /**
     * @var \Closure
     */
    protected $requestInformationRenderer;

    /**
     * @var \Closure
     */
    protected $backtraceRenderer;

    /**
     * @var string
     */
    protected $storagePath;

    /**
     * The maximum age of throwable dump in seconds, 0 to disable cleaning based on age
     *
     * @var int
     */
    protected $maximumThrowableDumpAge = 0;

    /**
     * The maximum number of throwable dumps to store, 0 to disable cleaning based on count
     *
     * @var int
     */
    protected $maximumThrowableDumpCount = 0;

    /**
     * Factory method to get an instance.
     *
     * @param array $options
     * @return ThrowableStorageInterface
     */
    public static function createWithOptions(array $options): ThrowableStorageInterface
    {
        $storagePath = $options['storagePath'] ?? (FLOW_PATH_DATA . 'Logs/Exceptions');
        $maximumThrowableDumpAge = $options['maximumThrowableDumpAge'] ?? 0;
        $maximumThrowableDumpCount = $options['maximumThrowableDumpCount'] ?? 0;
        return new static($storagePath, $maximumThrowableDumpAge, $maximumThrowableDumpCount);
    }

    /**
     * Create new instance.
     *
     * @param string $storagePath
     * @param int $maximumThrowableDumpAge
     * @param int $maximumThrowableDumpCount
     * @see createWithOptions
     */
    public function __construct(string $storagePath, int $maximumThrowableDumpAge, int $maximumThrowableDumpCount)
    {
        $this->storagePath = $storagePath;
        $this->maximumThrowableDumpAge = $maximumThrowableDumpAge;
        $this->maximumThrowableDumpCount = $maximumThrowableDumpCount;

        $this->requestInformationRenderer = static function () {
            // The following lines duplicate Scripts::initializeExceptionStorage(), which is a fallback to handle
            // exceptions that may occur before Scripts::initializeExceptionStorage() has finished.

            $output = '';
            if (!(Bootstrap::$staticObjectManager instanceof ObjectManagerInterface)) {
                return $output;
            }

            $bootstrap = Bootstrap::$staticObjectManager->get(Bootstrap::class);
            /* @var Bootstrap $bootstrap */
            $requestHandler = $bootstrap->getActiveRequestHandler();
            if (!$requestHandler instanceof HttpRequestHandlerInterface) {
                return $output;
            }

            $request = $requestHandler->getHttpRequest();
            $output .= PHP_EOL . 'HTTP REQUEST:' . PHP_EOL . ($request instanceof RequestInterface ? RequestInformationHelper::renderRequestInformation($request) : '[request was empty]') . PHP_EOL;
            $output .= PHP_EOL . 'PHP PROCESS:' . PHP_EOL . 'Inode: ' . getmyinode() . PHP_EOL . 'PID: ' . getmypid() . PHP_EOL . 'UID: ' . getmyuid() . PHP_EOL . 'GID: ' . getmygid() . PHP_EOL . 'User: ' . get_current_user() . PHP_EOL;

            return $output;
        };

        $this->backtraceRenderer = static function ($backtrace) {
            return Debugger::getBacktraceCode($backtrace, false, true);
        };
    }

    /**
     * @param \Closure $requestInformationRenderer
     * @return $this|ThrowableStorageInterface
     */
    public function setRequestInformationRenderer(\Closure $requestInformationRenderer)
    {
        $this->requestInformationRenderer = $requestInformationRenderer;

        return $this;
    }

    /**
     * @param \Closure $backtraceRenderer
     * @return $this|ThrowableStorageInterface
     */
    public function setBacktraceRenderer(\Closure $backtraceRenderer)
    {
        $this->backtraceRenderer = $backtraceRenderer;

        return $this;
    }

    /**
     * Stores information about the given exception and returns information about
     * the exception and where the details have been stored. The returned message
     * can be logged or displayed as needed.
     *
     * The returned message follows this pattern:
     * Exception #<code> in <line> of <file>: <message> - See also: <dumpFilename>
     *
     * @param \Throwable $throwable
     * @param array $additionalData
     * @return string Informational message about the stored throwable
     */
    public function logThrowable(\Throwable $throwable, array $additionalData = [])
    {
        $message = $this->getErrorLogMessage($throwable);

        if ($throwable->getPrevious() !== null) {
            $additionalData['previousException'] = $this->getErrorLogMessage($throwable->getPrevious());
        }

        if (!file_exists($this->storagePath)) {
            Files::createDirectoryRecursively($this->storagePath);
        }
        if (!file_exists($this->storagePath) || !is_dir($this->storagePath) || !is_writable($this->storagePath)) {
            return sprintf('Could not write exception backtrace into %s because the directory could not be created or is not writable.', $this->storagePath);
        }

        $this->cleanupThrowableDumps();

        // FIXME: getReferenceCode should probably become an interface.
        $referenceCode = (is_callable([
            $throwable,
            'getReferenceCode'
        ]) ? $throwable->getReferenceCode() : $this->generateUniqueReferenceCode());
        $throwableDumpPathAndFilename = Files::concatenatePaths([$this->storagePath, $referenceCode . '.txt']);
        file_put_contents($throwableDumpPathAndFilename, $this->renderErrorInfo($throwable, $additionalData));
        $message .= ' - See also: ' . basename($throwableDumpPathAndFilename);

        return $message;
    }

    /**
     * Generates a reference code for this specific error event to make it findable.
     *
     * @return string
     */
    protected function generateUniqueReferenceCode()
    {
        $timestamp = $_SERVER['REQUEST_TIME'] ?? time();
        return date('YmdHis', $timestamp) . substr(md5(rand()), 0, 6);
    }

    /**
     * Get current error post mortem informations with support for error chaining
     *
     * @param \Throwable $error
     * @param array $additionalData
     * @return string
     */
    protected function renderErrorInfo(\Throwable $error, array $additionalData = [])
    {
        $maximumDepth = 100;
        $backTrace = $error->getTrace();
        $message = $this->getErrorLogMessage($error);
        $postMortemInfo = $message . PHP_EOL . PHP_EOL . $this->renderBacktrace($backTrace);
        $depth = 0;
        while (($error->getPrevious() instanceof \Throwable) && $depth < $maximumDepth) {
            $error = $error->getPrevious();
            $message = 'Previous exception: ' . $this->getErrorLogMessage($error);
            $backTrace = $error->getTrace();
            $postMortemInfo .= PHP_EOL . $message . PHP_EOL . PHP_EOL . $this->renderBacktrace($backTrace);
            ++$depth;
        }

        if ($depth === $maximumDepth) {
            $postMortemInfo .= PHP_EOL . 'Maximum chainging depth reached ...';
        }

        $postMortemInfo .= PHP_EOL . $this->renderRequestInfo();
        $postMortemInfo .= PHP_EOL;
        $postMortemInfo .= empty($additionalData) ? '' : (new PlainTextFormatter($additionalData))->format();

        return $postMortemInfo;
    }

    /**
     * @param \Throwable $error
     * @return string
     */
    protected function getErrorLogMessage(\Throwable $error)
    {
        // getCode() does not always return an integer, e.g. in PDOException it can be a string
        if (is_int($error->getCode()) && $error->getCode() > 0) {
            $errorCodeString = ' #' . $error->getCode();
        } else {
            $errorCodeString = ' [' . $error->getCode() . ']';
        }
        $backTrace = $error->getTrace();
        $line = isset($backTrace[0]['line']) ? ' in line ' . $backTrace[0]['line'] . ' of ' . $backTrace[0]['file'] : '';

        return 'Exception' . $errorCodeString . $line . ': ' . $error->getMessage();
    }

    /**
     * Renders background information about the circumstances of the exception.
     *
     * @param array $backtrace
     * @return string
     */
    protected function renderBacktrace($backtrace)
    {
        $output = '';
        if ($this->backtraceRenderer !== null) {
            $output = $this->backtraceRenderer->__invoke($backtrace);
        }

        return $output;
    }

    /**
     * Render information about the current request, if possible
     *
     * @return string
     */
    protected function renderRequestInfo()
    {
        $output = '';
        if ($this->requestInformationRenderer !== null) {
            $output = $this->requestInformationRenderer->__invoke();
        }

        return $output;
    }

    /**
     * Cleans up existing throwable dumps when they are older than the configured
     * maximum age or the oldest ones exceeding the maximum number of dumps allowed.
     */
    protected function cleanupThrowableDumps(): void
    {
        if ($this->maximumThrowableDumpAge > 0) {
            $cutoffTime = time() - $this->maximumThrowableDumpAge;

            $iterator = new \DirectoryIterator($this->storagePath);
            foreach ($iterator as $directoryEntry) {
                if ($directoryEntry->isFile() && $directoryEntry->getCTime() < $cutoffTime) {
                    unlink($directoryEntry->getRealPath());
                }
            }
        }

        if ($this->maximumThrowableDumpCount > 0) {
            // this returns alphabetically ordered, so oldest first, as we have a date/time in the name
            $existingDumps = glob(Files::concatenatePaths([$this->storagePath, '*']));
            $existingDumpsCount = count($existingDumps);
            if ($existingDumpsCount > $this->maximumThrowableDumpCount) {
                $dumpsToRemove = array_slice($existingDumps, 0, $existingDumpsCount - $this->maximumThrowableDumpCount);

                foreach ($dumpsToRemove as $dumpToRemove) {
                    unlink($dumpToRemove);
                }
            }
        }
    }
}
