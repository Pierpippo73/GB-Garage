<?php

/**
 * @package         Regular Labs Library
 * @version         26.10.25669
 * 
 * @author          Peter van Westen <info@regularlabs.com>
 * @link            https://regularlabs.com
 * @copyright       Copyright © 2026 Regular Labs All Rights Reserved
 * @license         GNU General Public License version 2 or later
 */
namespace RegularLabs\Library;

defined('_JEXEC') or die;
use Joomla\CMS\Application\CMSApplicationInterface as JCMSApplicationInterface;
use Joomla\CMS\Document\Document as JDocument;
use Joomla\CMS\Version as JVersion;
class Indexer
{
    private static int $context_depth = 0;
    public static function getApplication(): JCMSApplicationInterface
    {
        return \RegularLabs\Library\Application::get();
    }
    public static function getDocument(): ?JDocument
    {
        if (!self::isActive()) {
            return \RegularLabs\Library\Document::get();
        }
        $language = self::getApplication()->getLanguage();
        $version = new JVersion();
        return JDocument::getInstance('html', ['charset' => 'utf-8', 'lineend' => 'unix', 'tab' => "\t", 'language' => $language?->getTag() ?? 'en-GB', 'direction' => $language?->isRtl() ?? \false ? 'rtl' : 'ltr', 'mediaversion' => $version->getMediaVersion() ?? null]);
    }
    public static function isActive(): bool
    {
        if (self::$context_depth > 0) {
            return \true;
        }
        $request = \RegularLabs\Library\RequestContext::fromInput();
        if ($request->isOption('com_finder') && in_array($request->task, ['indexer.batch', 'batch'], \true)) {
            return \true;
        }
        if (!\RegularLabs\Library\Application::isCli()) {
            return \false;
        }
        return in_array('finder:index', $_SERVER['argv'] ?? [], \true);
    }
    public static function isContext(string $context): bool
    {
        return in_array($context, ['com_finder.index', 'com_finder.indexer'], \true);
    }
    public static function isFinderContext(): bool
    {
        return self::isActive() || \RegularLabs\Library\RequestContext::fromInput()->isOption('com_finder');
    }
    public static function runInContext(string $context, callable $callback): mixed
    {
        if (!self::isContext($context)) {
            return $callback();
        }
        self::$context_depth++;
        try {
            return $callback();
        } finally {
            self::$context_depth--;
        }
    }
}
