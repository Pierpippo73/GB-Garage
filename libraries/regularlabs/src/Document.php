<?php

/**
 * @package         Regular Labs Library
 * @version         26.11.17024
 * 
 * @author          Peter van Westen <info@regularlabs.com>
 * @link            https://regularlabs.com
 * @copyright       Copyright © 2026 Regular Labs All Rights Reserved
 * @license         GNU General Public License version 2 or later
 */
namespace RegularLabs\Library;

defined('_JEXEC') or die;
use Joomla\CMS\Document\Document as JDocument;
use Joomla\CMS\HTML\HTMLHelper as JHtml;
use Joomla\CMS\Language\Text as JText;
use Joomla\CMS\Toolbar\Toolbar as JToolbar;
use Joomla\CMS\WebAsset\WebAssetManager as JWebAssetManager;
class Document
{
    public static function adminError(string $message): void
    {
        self::adminMessage($message, 'error');
    }
    public static function adminMessage(string $message, string $type = 'message'): void
    {
        if (!self::isAdmin()) {
            return;
        }
        self::message($message, $type);
    }
    public static function error(string $message): void
    {
        self::message($message, 'error');
    }
    public static function get(): ?JDocument
    {
        $application = \RegularLabs\Library\Application::get();
        if (!method_exists($application, 'getDocument')) {
            return null;
        }
        $document = $application->getDocument();
        if (!is_null($document)) {
            return $document;
        }
        $application->loadDocument();
        return $application->getDocument();
    }
    public static function getAssetManager(): ?JWebAssetManager
    {
        $document = self::get();
        if (is_null($document)) {
            return null;
        }
        return $document->getWebAssetManager();
    }
    public static function getBody(): ?string
    {
        $application = \RegularLabs\Library\Application::get();
        if (!method_exists($application, 'getBody')) {
            return null;
        }
        $body = $application->getBody();
        if ($body == '' || !is_string($body)) {
            return null;
        }
        return $body;
    }
    public static function getComponentBuffer(): ?string
    {
        $document = self::get();
        if (is_null($document)) {
            return null;
        }
        $buffer = $document->getBuffer('component') ?? null;
        if (empty($buffer) || !is_string($buffer)) {
            return null;
        }
        $buffer = trim($buffer);
        if (empty($buffer)) {
            return null;
        }
        return $buffer;
    }
    public static function getToolbar(): JToolbar
    {
        $doc = self::get();
        return !is_null($doc) && method_exists($doc, 'getToolbar') ? $doc->getToolbar() : JToolbar::getInstance();
    }
    public static function isAdmin(bool $exclude_login = \false): bool
    {
        $cache = new \RegularLabs\Library\Cache(['document.is_admin', $exclude_login]);
        if ($cache->exists()) {
            return $cache->get();
        }
        $request = \RegularLabs\Library\RequestContext::fromInput();
        $is_admin = self::isAdministrator() && (!$exclude_login || !\RegularLabs\Library\User::isGuest()) && !($request->isOption('com_finder') && $request->isFormat('json'));
        return $cache->set($is_admin);
    }
    public static function isAdministrator(): bool
    {
        return \RegularLabs\Library\Application::isAdministrator();
    }
    public static function isCategoryList(string $context): bool
    {
        $cache = new \RegularLabs\Library\Cache(['document.is_category_list', $context]);
        if ($cache->exists()) {
            return $cache->get();
        }
        // Return false if it is not a category page
        $request = \RegularLabs\Library\RequestContext::fromInput();
        if ($context != 'com_content.category' || !$request->isView('category')) {
            return $cache->set(\false);
        }
        // Return false if layout is set and it is not a list layout
        if ($request->layout && !$request->isLayout('list')) {
            return $cache->set(\false);
        }
        if (!self::isSite()) {
            return $cache->set(\false);
        }
        // Return false if default layout is set to blog
        if (\RegularLabs\Library\Application::get()->getParams()->get('category_layout') == '_:blog') {
            return $cache->set(\false);
        }
        // Return true if it IS a list layout
        return $cache->set(\true);
    }
    public static function isCli(): bool
    {
        return \RegularLabs\Library\Application::isCli();
    }
    public static function isClient(string $identifier): bool
    {
        $identifier = $identifier == 'admin' ? 'administrator' : $identifier;
        $cache = new \RegularLabs\Library\Cache(['document.is_client', $identifier]);
        if ($cache->exists()) {
            return $cache->get();
        }
        return $cache->set(\RegularLabs\Library\Application::isClient($identifier));
    }
    public static function isCurl(): bool
    {
        $cache = new \RegularLabs\Library\Cache('document.is_curl');
        if ($cache->exists()) {
            return $cache->get();
        }
        $is_cli = (new \RegularLabs\Library\MobileDetect())->isCurl();
        return $cache->set($is_cli);
    }
    public static function isDebug(): bool
    {
        return \RegularLabs\Library\Config::get('debug') || \RegularLabs\Library\Input::get('debug');
    }
    public static function isEditPage(): bool
    {
        $cache = new \RegularLabs\Library\Cache('document.is_edit_page');
        if ($cache->exists()) {
            return $cache->get();
        }
        $request = \RegularLabs\Library\RequestContext::fromInput();
        $option = $request->option;
        // always return false for these components
        if (in_array($option, ['com_rsevents', 'com_rseventspro'], \true)) {
            return $cache->set(\false);
        }
        $task = $request->task;
        if (str_contains($task, '.')) {
            $task = explode('.', $task);
            $task = array_pop($task);
        }
        $view = $request->view;
        if (str_contains($view, '.')) {
            $view = explode('.', $view);
            $view = array_pop($view);
        }
        $is_edit_page = in_array($option, ['com_config', 'com_contentsubmit', 'com_cckjseblod'], \true) || $option == 'com_comprofiler' && in_array($task, ['', 'userdetails'], \true) || in_array($task, ['edit', 'form', 'submission'], \true) || in_array($view, ['edit', 'form'], \true) || in_array(\RegularLabs\Library\Input::get('do', ''), ['edit', 'form'], \true) || in_array($request->layout, ['edit', 'form', 'write'], \true) || self::isAdmin();
        return $cache->set($is_edit_page);
    }
    public static function isFeed(): bool
    {
        $cache = new \RegularLabs\Library\Cache('document.is_feed');
        if ($cache->exists()) {
            return $cache->get();
        }
        $is_feed = self::get() && ((self::get()->getType() ?? null) == 'feed' || in_array(\RegularLabs\Library\Input::getWord('format'), ['feed', 'xml'], \true) || in_array(\RegularLabs\Library\Input::getWord('type'), ['rss', 'atom'], \true));
        return $cache->set($is_feed);
    }
    public static function isHtml(): bool
    {
        $cache = new \RegularLabs\Library\Cache('document.is_html');
        if ($cache->exists()) {
            return $cache->get();
        }
        $is_html = self::get() ? self::get()->getType() == 'html' : \false;
        return $cache->set($is_html);
    }
    public static function isHttps(): bool
    {
        $cache = new \RegularLabs\Library\Cache('document.is_https');
        if ($cache->exists()) {
            return $cache->get();
        }
        $is_https = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off' || isset($_SERVER['SSL_PROTOCOL']) || isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443 || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https';
        return $cache->set($is_https);
    }
    public static function isJSON(): bool
    {
        $cache = new \RegularLabs\Library\Cache('document.is_json');
        if ($cache->exists()) {
            return $cache->get();
        }
        $is_json = \RegularLabs\Library\RequestContext::fromInput()->isFormat('json');
        return $cache->set($is_json);
    }
    /**
     * Check if the current setup matches the given main version number
     */
    public static function isJoomlaVersion(int $version, string $title = ''): bool
    {
        $jversion = \RegularLabs\Library\Version::getMajorJoomlaVersion();
        if ($jversion == $version) {
            return \true;
        }
        if ($title && self::isAdmin()) {
            \RegularLabs\Library\Language::load('plg_system_regularlabs');
            \RegularLabs\Library\Message::enqueue(JText::sprintf('RL_NOT_COMPATIBLE_WITH_JOOMLA_VERSION', JText::_($title), $jversion), 'error');
        }
        return \false;
    }
    public static function isPDF(): bool
    {
        $cache = new \RegularLabs\Library\Cache('document.is_pdf');
        if ($cache->exists()) {
            return $cache->get();
        }
        $is_pdf = self::get() && ((self::get()->getType() ?? null) == 'pdf' || \RegularLabs\Library\Input::getWord('format') == 'pdf' || \RegularLabs\Library\Input::getWord('cAction') == 'pdf');
        return $cache->set($is_pdf);
    }
    public static function isSite(): bool
    {
        return \RegularLabs\Library\Application::isSite();
    }
    public static function loadAdminDescriptionsAssets(): void
    {
        self::script('regularlabs.admin-form-descriptions');
    }
    public static function loadAdminFormAssets(): void
    {
        \RegularLabs\Library\Language::load('plg_system_regularlabs');
        JText::script('RL_AJAX_FIELD_LOADING_FAILED');
        JText::script('RL_RETRY');
        JText::script('RL_LIST_TYPE_TO_SEARCH');
        JText::script('RL_LIST_LOADING');
        JText::script('RL_LIST_LOAD_MORE');
        JText::script('RL_LIST_SEARCH_OR_SELECT');
        JText::script('RL_LIST_REFINE_SEARCH');
        JText::script('JGLOBAL_SELECT_NO_RESULTS_MATCH');
        JText::script('JSEARCH_FILTER');
        self::style('regularlabs.admin-form');
        self::script('regularlabs.admin-form');
        self::script('regularlabs.regular');
        self::script('regularlabs.script');
    }
    public static function loadEditorPopupAssets(): void
    {
        self::loadAdminFormAssets();
        self::style('regularlabs.popup');
    }
    public static function loadFancySelectAssets(): void
    {
        self::usePreset('choicesjs');
        self::useScript('webcomponent.field-fancy-select');
    }
    public static function loadTreeSelectAssets(): void
    {
        self::style('regularlabs.treeselect');
        self::script('regularlabs.treeselect');
        self::useScript('bootstrap.dropdown');
    }
    public static function message(string $message, string $type = 'message'): void
    {
        \RegularLabs\Library\Language::load('plg_system_regularlabs');
        \RegularLabs\Library\Message::enqueue($message, $type);
    }
    /**
     * @deprecated Use RegularLabs\Library\StringHelper::minify()
     */
    public static function minify(string $string): string
    {
        return \RegularLabs\Library\StringHelper::minify($string);
    }
    public static function removeScriptTag(string &$string, string $folder, string $name): void
    {
        \RegularLabs\Library\DocumentHeadCleaner::removeScriptTag($string, $folder, $name);
    }
    public static function removeScriptsOptions(string &$string, string $name, string $alias = ''): void
    {
        \RegularLabs\Library\DocumentHeadCleaner::removeScriptsOptions($string, $name, $alias);
    }
    public static function removeScriptsStyles(string &$string, string $name, string $alias = ''): void
    {
        \RegularLabs\Library\DocumentHeadCleaner::removeScriptsStyles($string, $name, $alias);
    }
    public static function removeStyleTag(string &$string, string $folder, string $name): void
    {
        \RegularLabs\Library\DocumentHeadCleaner::removeStyleTag($string, $folder, $name);
    }
    public static function script(string $name, array $attributes = ['defer' => \true], array $dependencies = [], bool $convert_dots = \true): void
    {
        $file = $name;
        if ($convert_dots) {
            $file = str_replace('.', '/', $file) . '.min.js';
        }
        if ($name == 'regularlabs.regular') {
            $attributes['defer'] = \false;
        }
        self::getAssetManager()->registerAndUseScript($name, $file, [], $attributes, $dependencies);
    }
    public static function scriptDeclaration(string $content = '', string $name = '', bool $minify = \true, string $position = 'before'): void
    {
        if ($minify) {
            $content = \RegularLabs\Library\StringHelper::minify($content);
        }
        if ($name == '') {
            $content = \RegularLabs\Library\Protect::wrapScriptDeclaration($content, $name, $minify);
        }
        self::getAssetManager()->addInlineScript($content, ['position' => $position]);
    }
    public static function scriptOptions(array $options = [], string $name = ''): void
    {
        $alias = \RegularLabs\Library\RegEx::replace('[^a-z0-9_-]', '', strtolower($name));
        $key = 'rl_' . $alias;
        $document = self::get();
        if (is_null($document)) {
            return;
        }
        JHtml::_('behavior.core');
        $document->addScriptOptions($key, $options);
    }
    public static function setBody(string $body = ''): void
    {
        $application = \RegularLabs\Library\Application::get();
        if (!method_exists($application, 'setBody')) {
            return;
        }
        $application->setBody($body);
    }
    public static function setComponentBuffer(string $buffer = ''): void
    {
        $document = self::get();
        if (is_null($document)) {
            return;
        }
        $document->setBuffer($buffer, 'component');
    }
    public static function stripScriptTag(string $string, string $folder, string $name): string
    {
        return \RegularLabs\Library\DocumentHeadCleaner::stripScriptTag($string, $folder, $name);
    }
    public static function stripScriptsOptions(string $string, string $name, string $alias = ''): string
    {
        return \RegularLabs\Library\DocumentHeadCleaner::stripScriptsOptions($string, $name, $alias);
    }
    public static function stripScriptsStyles(string $string, string $name, string $alias = ''): string
    {
        return \RegularLabs\Library\DocumentHeadCleaner::stripScriptsStyles($string, $name, $alias);
    }
    public static function stripStyleTag(string $string, string $folder, string $name): string
    {
        return \RegularLabs\Library\DocumentHeadCleaner::stripStyleTag($string, $folder, $name);
    }
    public static function style(string $name, array $attributes = [], bool $convert_dots = \true): void
    {
        $file = $name;
        if ($convert_dots) {
            $file = str_replace('.', '/', $file) . '.min.css';
        }
        self::getAssetManager()->registerAndUseStyle($name, $file, [], $attributes);
    }
    public static function styleDeclaration(string $content = '', string $name = '', bool $minify = \true): void
    {
        if ($minify) {
            $content = \RegularLabs\Library\StringHelper::minify($content);
        }
        if ($name == '') {
            $content = \RegularLabs\Library\Protect::wrapStyleDeclaration($content, $name, $minify);
        }
        self::getAssetManager()->addInlineStyle($content);
    }
    public static function usePreset(string $name): void
    {
        self::getAssetManager()->usePreset($name);
    }
    public static function useScript(string $name): void
    {
        self::getAssetManager()->useScript($name);
    }
    public static function useStyle(string $name): void
    {
        self::getAssetManager()->useStyle($name);
    }
}
