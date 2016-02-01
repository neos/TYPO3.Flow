<?php
namespace TYPO3\Flow\Cache;

/*
 * This file is part of the TYPO3.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Cache\Backend\AbstractBackend;
use TYPO3\Flow\Cache\Backend\SimpleFileBackend;
use TYPO3\Flow\Core\ApplicationContext;
use TYPO3\Flow\Object\ObjectManagerInterface;
use TYPO3\Flow\Utility\Environment;

/**
 * This cache factory takes care of instantiating a cache frontend and injecting
 * a certain cache backend. In a Flow context you should use the CacheManager to
 * get a Cache.
 *
 * @Flow\Scope("singleton")
 * @api
 */
class CacheFactory extends GenericCacheFactory implements CacheFactoryInterface
{
    /**
     * The current Flow context ("Production", "Development" etc.)
     *
     * @var ApplicationContext
     */
    protected $context;

    /**
     * A reference to the cache manager
     *
     * @var \TYPO3\Flow\Cache\CacheManager
     */
    protected $cacheManager;

    /**
     * @var Environment
     */
    protected $environment;

    /**
     * @var FlowCacheEnvironmentConfiguration
     */
    protected $environmentConfiguration;

    /**
     * @param CacheManager $cacheManager
     * @Flow\Autowiring(enabled=false)
     */
    public function injectCacheManager(\TYPO3\Flow\Cache\CacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }

    /**
     * @param FlowCacheEnvironmentConfiguration $environmentConfiguration
     * @Flow\Autowiring(enabled=false)
     */
    public function injectEnvironmentConfiguration(FlowCacheEnvironmentConfiguration $environmentConfiguration)
    {
        $this->environmentConfiguration = $environmentConfiguration;
    }

    /**
     * Constructs this cache factory
     *
     * @param ApplicationContext $context The current Flow context
     * @param Environment $environment
     * @Flow\Autowiring(enabled=false)
     */
    public function __construct(ApplicationContext $context, Environment $environment)
    {
        $this->context = $context;
        $this->environment = $environment;
    }

    /**
     * @param string $cacheIdentifier
     * @param string $cacheObjectName
     * @param string $backendObjectName
     * @param array $backendOptions
     * @param boolean $persistent
     * @return Frontend\FrontendInterface
     */
    public function create($cacheIdentifier, $cacheObjectName, $backendObjectName, array $backendOptions = [], $persistent = false)
    {
        $backend = $this->instanciateBackend($backendObjectName, $backendOptions, $persistent);
        $cache = $this->instanciateCache($cacheIdentifier, $cacheObjectName, $backend);
        $backend->setCache($cache);

        return $cache;
    }

    /**
     * {@inheritdoc}
     */
    protected function instanciateCache($cacheIdentifier, $cacheObjectName, $backend)
    {
        $cache = parent::instanciateCache($cacheIdentifier, $cacheObjectName, $backend);

        if (is_callable([$cache, 'initializeObject'])) {
            $cache->initializeObject(ObjectManagerInterface::INITIALIZATIONCAUSE_CREATED);
        }

        return $cache;
    }

    /**
     * @param string $backendObjectName
     * @param array $backendOptions
     * @param boolean $persistent
     * @return AbstractBackend|Backend\BackendInterface
     * @throws Exception\InvalidBackendException
     */
    protected function instanciateBackend($backendObjectName, $backendOptions, $persistent = false)
    {
        if (
            $persistent &&
            is_a($backendObjectName, SimpleFileBackend::class, true) &&
            (!isset($backendOptions['cacheDirectory']) || $backendOptions['cacheDirectory'] === '') &&
            (!isset($backendOptions['baseDirectory']) || $backendOptions['baseDirectory'] === '')
        ) {
            $backendOptions['baseDirectory'] = $this->environmentConfiguration->getPersistentCacheBasePath();
        }

        if (is_a($backendObjectName, AbstractBackend::class, true)) {
            return $this->instanciateFlowSpecificBackend($backendObjectName, $backendOptions);
        }

        return parent::instanciateBackend($backendObjectName, $backendOptions);
    }

    /**
     * @param string $backendObjectName
     * @param array $backendOptions
     * @return AbstractBackend
     * @throws Exception\InvalidBackendException
     */
    protected function instanciateFlowSpecificBackend($backendObjectName, $backendOptions)
    {
        $backend = new $backendObjectName($this->context, $backendOptions, $this->environmentConfiguration);

        if (!$backend instanceof Backend\BackendInterface) {
            throw new Exception\InvalidBackendException('"' . $backendObjectName . '" is not a valid cache backend object.', 1216304301);
        }

        /** @var AbstractBackend $backend */
        $backend->injectEnvironment($this->environment);

        if (is_callable([$backend, 'injectCacheManager'])) {
            $backend->injectCacheManager($this->cacheManager);
        }
        if (is_callable([$backend, 'initializeObject'])) {
            $backend->initializeObject(ObjectManagerInterface::INITIALIZATIONCAUSE_CREATED);
        }

        return $backend;
    }
}
