<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Tests\Unit\Bitrix;

use PHPUnit\Framework\TestCase;

final class InstallerLifecycleSourceTest extends TestCase
{
    public function testRepeatedInstallDoesNotRegisterOrRollbackAnExistingModule(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 3) . '/install/index.php');

        self::assertIsString($contents);
        self::assertStringContainsString(
            '$wasInstalled = ModuleManager::isModuleInstalled($this->MODULE_ID);',
            $contents,
        );
        self::assertMatchesRegularExpression(
            '/if \(!\$wasInstalled\) \{\s+RegisterModule\(\$this->MODULE_ID\);\s+\}/',
            $contents,
        );
        self::assertMatchesRegularExpression(
            '/catch \(Throwable \$exception\) \{\s+if \(!\$wasInstalled\) \{\s+'
            . '\$this->rollbackInstallation\(\);\s+\}/',
            $contents,
        );
    }
}
