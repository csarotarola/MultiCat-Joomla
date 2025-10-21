<?php

declare(strict_types=1);

namespace Joomla\Plugin\System\OtarolaMulticat\Helper;

use RuntimeException;

use function class_exists;
use function class_alias;
use function defined;
use function file_get_contents;
use function file_exists;
use function str_replace;
use function spl_autoload_register;
use function spl_autoload_unregister;

/**
 * Registers an override for the frontend ArticlesModel at runtime.
 */
final class ArticlesModelOverrideShim
{
    private const TARGET_CLASS = 'Joomla\\Component\\Content\\Site\\Model\\ArticlesModel';
    private const BASE_CLASS = 'Joomla\\Component\\Content\\Site\\Model\\BaseArticlesModel';
    private const LEGACY_CLASS = 'ContentModelArticles';

    private static bool $registered = false;

    /**
     * Registers the autoloader shim.
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        if (class_exists(self::TARGET_CLASS, false)) {
            // The model already loaded. Bail out to avoid fatal errors.
            return;
        }

        spl_autoload_register([self::class, 'autoload'], true, true);

        self::$registered = true;
    }

    /**
     * Autoload handler that rewrites the core class on the fly.
     *
     * @param  string  $class  Requested class name.
     */
    private static function autoload(string $class): void
    {
        if ($class !== self::TARGET_CLASS) {
            return;
        }

        spl_autoload_unregister([self::class, 'autoload']);

        if (!defined('JPATH_SITE')) {
            return;
        }

        $originalPath = JPATH_SITE . '/components/com_content/src/Model/ArticlesModel.php';

        if (!file_exists($originalPath)) {
            spl_autoload_register([self::class, 'autoload'], true, true);

            return;
        }

        $contents = file_get_contents($originalPath);

        if ($contents === false) {
            throw new RuntimeException('Unable to read core ArticlesModel definition.');
        }

        $rewritten = str_replace(
            ['final class ArticlesModel', 'class ArticlesModel', 'ArticlesModel::class'],
            ['final class BaseArticlesModel', 'class BaseArticlesModel', 'BaseArticlesModel::class'],
            $contents
        );

        $rewritten = str_replace(
            ["class_alias(BaseArticlesModel::class, 'ContentModelArticles');", 'class_alias(BaseArticlesModel::class, "ContentModelArticles");'],
            '',
            $rewritten
        );

        eval('?>' . $rewritten);

        if (!class_exists(self::BASE_CLASS, false)) {
            throw new RuntimeException('Failed to bootstrap base ArticlesModel class.');
        }

        require_once __DIR__ . '/ArticlesModelOverride.php';

        if (!class_exists(self::LEGACY_CLASS, false)) {
            class_alias(self::TARGET_CLASS, self::LEGACY_CLASS);
        }

        spl_autoload_register([self::class, 'autoload'], true, true);
    }
}
