<?php
namespace Neos\Flow\Command;

/*
 * This file is part of the Neos.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Composer\ComposerUtility;
use Neos\Flow\Package\PackageInterface;
use Neos\Flow\Package\PackageKeyAwareInterface;
use Neos\Flow\Package\PackageManager;

/**
 * Package command controller to handle packages from CLI
 *
 * @Flow\Scope("singleton")
 */
class PackageCommandController extends CommandController
{
    /**
     * @var PackageManager
     */
    protected $packageManager;

    /**
     * @param PackageManager $packageManager
     * @return void
     */
    public function injectPackageManager(PackageManager $packageManager)
    {
        $this->packageManager = $packageManager;
    }

    /**
     * Create a new package
     *
     * This command creates a new package which contains only the mandatory
     * directories and files.
     *
     * @Flow\FlushesCaches
     * @param string $packageKey The package key of the package to create
     * @param string $packageType The package type of the package to create
     * @return void
     * @see neos.kickstarter:kickstart:package
     */
    public function createCommand(string $packageKey, string $packageType = PackageInterface::DEFAULT_COMPOSER_TYPE)
    {
        if (!$this->packageManager->isPackageKeyValid($packageKey)) {
            $this->outputLine('The package key "%s" is not valid.', [$packageKey]);
            $this->quit(1);
        }
        if ($this->packageManager->isPackageAvailable($packageKey)) {
            $this->outputLine('The package "%s" already exists.', [$packageKey]);
            $this->quit(1);
        }

        if (!ComposerUtility::isFlowPackageType($packageType)) {
            $this->outputLine('The package must be a Flow package, but "%s" is not a valid Flow package type.', [$packageType]);
            $this->quit(1);
        }
        $package = $this->packageManager->createPackage($packageKey, ['type' => $packageType], null);
        $this->outputLine('Created new package "' . $packageKey . '" at "' . $package->getPackagePath() . '".');
    }

    /**
     * List available packages
     *
     * Lists all locally available packages. Displays the package key, version and
     * package title.
     *
     * @param boolean $loadingOrder The returned packages are ordered by their loading order.
     * @return void The list of packages
     */
    public function listCommand(bool $loadingOrder = false)
    {
        $availablePackages = [];
        $longestPackageKey = 0;

        foreach ($this->packageManager->getAvailablePackages() as $packageKey => $package) {
            if (strlen($packageKey) > $longestPackageKey) {
                $longestPackageKey = strlen($packageKey);
            }

            $availablePackages[$packageKey] = $package;
        }

        if ($loadingOrder === false) {
            ksort($availablePackages);
        }

        $this->outputLine('PACKAGES:');
        /** @var PackageInterface|PackageKeyAwareInterface $package */
        foreach ($availablePackages as $package) {
            $this->outputLine(' ' . str_pad($package->getPackageKey(), $longestPackageKey + 3) . str_pad($package->getInstalledVersion(), 15));
        }
    }

    /**
     * Freeze a package
     *
     * This function marks a package as <b>frozen</b> in order to improve performance
     * in a development context. While a package is frozen, any modification of files
     * within that package won't be tracked and can lead to unexpected behavior.
     *
     * File monitoring won't consider the given package. Further more, reflection
     * data for classes contained in the package is cached persistently and loaded
     * directly on the first request after caches have been flushed. The precompiled
     * reflection data is stored in the <b>Configuration</b> directory of the
     * respective package.
     *
     * By specifying <b>all</b> as a package key, all currently frozen packages are
     * frozen (the default).
     *
     * @param string $packageKey Key of the package to freeze
     * @return void
     * @see neos.flow:package:unfreeze
     * @see neos.flow:package:refreeze
     * @deprecated since 8.4
     */
    public function freezeCommand(string $packageKey = 'all')
    {
        $this->outputLine('Package freezing is no longer supported, this command is deprecated and will be removed with 9.0.');
    }

    /**
     * Unfreeze a package
     *
     * Unfreezes a previously frozen package. On the next request, this package will
     * be considered again by the file monitoring and related services – if they are
     * enabled in the current context.
     *
     * By specifying <b>all</b> as a package key, all currently frozen packages are
     * unfrozen (the default).
     *
     * @param string $packageKey Key of the package to unfreeze, or 'all'
     * @return void
     * @see neos.flow:package:freeze
     * @see neos.flow:cache:flush
     * @deprecated since 8.4
     */
    public function unfreezeCommand(string $packageKey = 'all')
    {
        $this->outputLine('Package freezing is no longer supported, this command is deprecated and will be removed with 9.0.');
    }

    /**
     * Refreeze a package
     *
     * Refreezes a currently frozen package: all precompiled information is removed
     * and file monitoring will consider the package exactly once, on the next
     * request. After that request, the package remains frozen again, just with the
     * updated data.
     *
     * By specifying <b>all</b> as a package key, all currently frozen packages are
     * refrozen (the default).
     *
     * @param string $packageKey Key of the package to refreeze, or 'all'
     * @return void
     * @see neos.flow:package:freeze
     * @see neos.flow:cache:flush
     * @deprecated since 8.4
     */
    public function refreezeCommand(string $packageKey = 'all')
    {
        $this->outputLine('Package freezing is no longer supported, this command is deprecated and will be removed with 9.0.');
    }

    /**
     * Rescan package availability and recreates the PackageStates configuration.
     */
    public function rescanCommand()
    {
        $packageStates = $this->packageManager->rescanPackages();

        $this->outputLine('The following packages are registered and will be loaded in this order:');
        $this->outputLine('');
        foreach ($packageStates['packages'] as $composerName => $packageState) {
            $this->outputLine($composerName);
        }
        $this->outputLine('');
        $this->outputLine('Package rescan successful.');
    }
}
