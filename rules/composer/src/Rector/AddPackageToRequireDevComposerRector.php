<?php

declare(strict_types=1);

namespace Rector\Composer\Rector;

use Rector\Composer\Contract\Rector\ComposerRectorInterface;
use Rector\Composer\Contract\Rector\CoreComposerRectorInterface;
use Rector\Composer\Guard\VersionGuard;
use Rector\Composer\ValueObject\PackageAndVersion;
use Symplify\ComposerJsonManipulator\ValueObject\ComposerJson;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Rector\Composer\Tests\Rector\AddPackageToRequireDevComposerRector\AddPackageToRequireDevComposerRectorTest
 */
final class AddPackageToRequireDevComposerRector implements CoreComposerRectorInterface
{
    /**
     * @var string
     */
    public const PACKAGES_AND_VERSIONS = 'packages_and_version';

    /**
     * @var PackageAndVersion[]
     */
    private $packageAndVersions = [];

    /**
     * @var VersionGuard
     */
    private $versionGuard;

    public function __construct(VersionGuard $versionGuard)
    {
        $this->versionGuard = $versionGuard;
    }

    public function refactor(ComposerJson $composerJson): void
    {
        foreach ($this->packageAndVersions as $packageAndVersion) {
            $composerJson->addRequiredDevPackage($packageAndVersion->getPackageName(), $packageAndVersion->getVersion());
        }
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Add package to "require-dev" in `composer.json`', [new ConfiguredCodeSample(<<<'CODE_SAMPLE'
{
}
CODE_SAMPLE
, <<<'CODE_SAMPLE'
{
    "require-dev": {
        "symfony/console": "^3.4"
    }
}
CODE_SAMPLE
, [
            self::PACKAGES_AND_VERSIONS => [new PackageAndVersion('symfony/console', '^3.4')],
        ]),
        ]);
    }

    /**
     * @param array<string, PackageAndVersion[]> $configuration
     */
    public function configure(array $configuration): void
    {
        $packagesAndVersions = $configuration[self::PACKAGES_AND_VERSIONS] ?? [];
        $this->versionGuard->validate($packagesAndVersions);
        $this->packageAndVersions = $packagesAndVersions;
    }
}
