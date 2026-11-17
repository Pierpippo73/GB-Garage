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
namespace RegularLabs\Library\Plugin;

defined('_JEXEC') or die;
use Joomla\CMS\Application\CMSApplication as JCMSApplication;
use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Event\Finder\ResultEvent;
use Joomla\CMS\Event\Model\PrepareFormEvent;
use Joomla\CMS\Event\Module\AfterRenderModuleEvent;
use Joomla\CMS\Event\Module\BeforeRenderModuleEvent;
use Joomla\CMS\Event\Module\PrepareModuleListEvent;
use Joomla\CMS\Event\Plugin\AjaxEvent;
use Joomla\CMS\Form\Form as JForm;
use Joomla\CMS\Language\Text as JText;
use Joomla\CMS\Plugin\CMSPlugin as JCMSPlugin;
use Joomla\Component\Finder\Administrator\Indexer\Helper as JIndexerHelper;
use Joomla\Component\Finder\Administrator\Indexer\Query as JIndexerQuery;
use Joomla\Component\Finder\Administrator\Indexer\Result as JIndexerResult;
use Joomla\Database\DatabaseDriver as JDatabaseDriver;
use Joomla\Event\DispatcherInterface as JDispatcherInterface;
use Joomla\Event\Event as JEvent;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry as JRegistry;
use RegularLabs\Library\Application;
use RegularLabs\Library\Article;
use RegularLabs\Library\DB;
use RegularLabs\Library\Document;
use RegularLabs\Library\Indexer;
use RegularLabs\Library\Input;
use RegularLabs\Library\Language;
use RegularLabs\Library\Message;
use RegularLabs\Library\Protect;
use RegularLabs\Library\RegEx;
use RegularLabs\Library\RequestContext;
use RegularLabs\Library\Security;
use RegularLabs\Library\User;
use function func_num_args;
class System extends JCMSPlugin implements SubscriberInterface
{
    use \RegularLabs\Library\Plugin\SystemPassState;
    private const FINALIZE_EVENT_PRIORITY = \PHP_INT_MIN;
    private const REPROCESS_SOURCERER_EVENT_PRIORITY = \PHP_INT_MIN + 1;
    static $_extra_events = [];
    public $_alias = '';
    public $_lang_prefix = '';
    public $_title = '';
    protected $_can_disable_by_url = \true;
    protected $_doc_ready = \false;
    protected $_enable_in_admin = \false;
    protected $_enable_in_frontend = \true;
    protected $_enable_in_indexer = \true;
    protected $_enable_on_edit_pages = \false;
    protected $_finalize_final_html = \false;
    protected $_id = 0;
    protected $_index_as_guest = \false;
    protected $_page_types = ['html', 'feed', 'pdf', 'xml', 'ajax', 'json', 'raw'];
    protected $_pass;
    protected $_reprocess_sourcerer_output = \false;
    /**
     * @var    JCMSApplication
     */
    protected $app;
    protected $autoloadLanguage = \true;
    /**
     * @var    JDatabaseDriver
     */
    protected $db;
    public function __construct($config = [])
    {
        if ($config instanceof JDispatcherInterface) {
            $dispatcher = $config;
            $config = func_num_args() > 1 ? func_get_arg(1) : [];
            parent::__construct($dispatcher, $config);
        } else {
            parent::__construct($config);
        }
        if (isset($config['id'])) {
            $this->_id = $config['id'];
        }
        $this->app = Application::get();
        $this->db = DB::get();
        if (empty($this->_alias)) {
            $this->_alias = $this->_name;
        }
        if (empty($this->_title)) {
            $this->_title = strtoupper($this->_alias);
        }
        $this->init();
    }
    public static function getSubscribedEvents(): array
    {
        return ['onAfterDispatch' => 'onAfterDispatch', 'onAfterInitialise' => 'onAfterInitialise', 'onAfterRender' => 'onAfterRender', 'onAfterRenderModule' => 'onAfterRenderModule', 'onAfterRoute' => 'onAfterRoute', 'onBeforeCompileHead' => 'onBeforeCompileHead', 'onContentPrepare' => 'onContentPrepare', 'onContentPrepareForm' => 'onContentPrepareForm', 'onFinderResult' => 'onFinderResult', ...static::$_extra_events];
    }
    public function handleAjaxResult(mixed $data, ?JEvent $event = null): mixed
    {
        if ($event instanceof AjaxEvent) {
            $event->addResult($data);
            return null;
        }
        if ($event instanceof JEvent) {
            $event->setArgument('result', [$data]);
            return null;
        }
        return $data;
    }
    public function handleOnFinderResult(JIndexerResult $item, JIndexerQuery|array $query): void
    {
        $description = $item->description ?? '';
        $summary = $item->getElement('summary') ?? '';
        if (empty($description) && empty($summary)) {
            return;
        }
        $article = (object) ['id' => $item->getElement('id')];
        if (!empty($description)) {
            $article->fulltext = $description;
            Article::processText('fulltext', $article, $this, 'processArticle', ['article', 'com_finder.index', $article]);
            $item->description = JIndexerHelper::parse($article->fulltext);
        }
        if ($description == $summary) {
            $item->setElement('summary', $item->description);
            return;
        }
        if (!empty($summary)) {
            $article->fulltext = $summary;
            Article::processText('fulltext', $article, $this, 'processArticle', ['article', 'com_finder.index', $article]);
            $item->setElement('summary', $article->fulltext);
        }
    }
    public function handleOnPrepareModuleList(?array &$modules): void
    {
    }
    public function handleOnRenderModule(?object &$module): void
    {
    }
    public function init(): void
    {
    }
    /**
     * @param string $extension
     * @param string $basePath
     *
     * @return  bool
     */
    public function loadLanguage($extension = '', $basePath = JPATH_ADMINISTRATOR)
    {
        parent::loadLanguage('plg_system_regularlabs', JPATH_LIBRARIES . '/regularlabs');
        return parent::loadLanguage();
    }
    public function onAfterDispatch(): void
    {
        if (!$this->passChecks()) {
            return;
        }
        $this->handleOnAfterDispatch();
        $buffer = Document::getComponentBuffer();
        $has_component_buffer = !is_null($buffer);
        $buffer ??= '';
        if (!Indexer::isActive()) {
            $this->loadStylesAndScripts($buffer);
        }
        if (!$has_component_buffer) {
            return;
        }
        if (empty($buffer)) {
            return;
        }
        $this->changeDocumentBuffer($buffer);
        Document::setComponentBuffer($buffer);
    }
    public function onAfterInitialise(): void
    {
        Language::load('plg_system_' . $this->_name);
        if (!$this->passChecks()) {
            return;
        }
        if ($this->_finalize_final_html) {
            $this->getDispatcher()->addListener('onAfterRender', [$this, 'finalizeFinalHtmlOutput'], self::FINALIZE_EVENT_PRIORITY);
        }
        if ($this->_reprocess_sourcerer_output) {
            $this->getDispatcher()->addListener('onContentPrepare', [$this, 'reprocessSourcererOutputOnContentPrepare'], self::REPROCESS_SOURCERER_EVENT_PRIORITY);
            $this->getDispatcher()->addListener('onAfterRenderModule', [$this, 'reprocessSourcererOutputOnAfterRenderModule'], self::REPROCESS_SOURCERER_EVENT_PRIORITY);
            $this->getDispatcher()->addListener('onAfterRender', [$this, 'reprocessSourcererOutputOnAfterRender'], self::REPROCESS_SOURCERER_EVENT_PRIORITY);
        }
        $this->handleOnAfterInitialise();
    }
    public function onAfterRender(): void
    {
        if (!$this->passChecks()) {
            return;
        }
        $this->handleOnAfterRender();
        $html = Document::getBody();
        if (is_null($html)) {
            return;
        }
        if (!$this->changeFinalHtmlOutput($html)) {
            return;
        }
        $this->cleanFinalHtmlOutput($html);
        Document::setBody($html);
    }
    public function finalizeFinalHtmlOutput(): void
    {
        if (!$this->passChecks()) {
            return;
        }
        $html = Document::getBody();
        if (is_null($html)) {
            return;
        }
        $this->finalizeHtmlOutput($html);
        Document::setBody($html);
    }
    public function onAfterRenderModule($event): void
    {
        if (!$this->passChecks()) {
            return;
        }
        [$module, $params] = $this->getAfterRenderModuleArguments(func_get_args());
        if (!isset($module->content) || !is_string($module->content)) {
            return;
        }
        $this->handleOnAfterRenderModule($module, $params);
    }
    public function processSourcererArticleOutput(string &$string, string $area = 'article', string $context = '', mixed $article = null, int $page = 0): void
    {
        if (!$this->protectNonSourcererOutput($string, 'Content')) {
            return;
        }
        try {
            $this->processArticle($string, $area, $context, $article, $page);
        } finally {
            Protect::unprotectHtmlSafe($string);
        }
    }
    public function reprocessSourcererOutputOnAfterRender(): void
    {
        if (!$this->passChecks()) {
            return;
        }
        $html = Document::getBody();
        if (is_null($html) || !$this->protectNonSourcererOutput($html, 'Final')) {
            return;
        }
        try {
            if (!$this->changeFinalHtmlOutput($html)) {
                return;
            }
            $this->cleanFinalHtmlOutput($html);
        } finally {
            Protect::unprotectHtmlSafe($html);
        }
        Document::setBody($html);
    }
    public function reprocessSourcererOutputOnAfterRenderModule($event): void
    {
        if (!$this->passChecks()) {
            return;
        }
        [$module, $params] = $this->getAfterRenderModuleArguments(func_get_args());
        if (!isset($module->content) || !is_string($module->content) || !$this->protectNonSourcererOutput($module->content, 'Module')) {
            return;
        }
        try {
            $this->handleOnAfterRenderModule($module, $params);
        } finally {
            Protect::unprotectHtmlSafe($module->content);
        }
    }
    public function reprocessSourcererOutputOnContentPrepare($event): void
    {
        [$area, $context, $article, $params, $page] = $this->getContentPrepareData(func_get_args());
        if (!$this->articleContainsSourcererOutput($article, 'Content')) {
            return;
        }
        $this->runContentPrepare($context, function () use ($area, $context, &$article, $page): void {
            Article::process($article, $context, $this, 'processSourcererArticleOutput', [$area, $context, $article, $page]);
        });
    }
    public function onAfterRoute(): void
    {
        $this->_doc_ready = \true;
        if (!$this->passChecks()) {
            return;
        }
        $this->handleOnAfterRoute();
    }
    public function onBeforeCompileHead(): void
    {
        if (!$this->passChecks()) {
            return;
        }
        $this->handleOnBeforeCompileHead();
    }
    /**
     * Example:
     *  new ContentPrepareEvent('onEventName', ['context' => 'com_example.example', 'subject' => $contentObject, 'params' => $params, 'page' => $pageNum]);
     */
    public function onContentPrepare($event): void
    {
        [$area, $context, $article, $params, $page] = $this->getContentPrepareData(func_get_args());
        $this->runContentPrepare($context, function () use ($area, $context, &$article, &$params, $page): void {
            if (!$this->handleOnContentPrepare($area, $context, $article, $params, $page)) {
                return;
            }
            Article::process($article, $context, $this, 'processArticle', [$area, $context, $article, $page]);
        });
    }
    public function onContentPrepareForm($event): void
    {
        if (!$this->passChecks()) {
            return;
        }
        [$form, $data] = $this->getContentPrepareFormArguments(func_get_args());
        $this->handleOnContentPrepareForm($form, $data);
    }
    public function onFinderResult($event): void
    {
        if (!$this->passChecks()) {
            return;
        }
        [$item, $query] = $this->getFinderResultArguments(func_get_args());
        $this->handleOnFinderResult($item, $query);
    }
    public function onPrepareModuleList($event): void
    {
        if (!$this->passChecks()) {
            return;
        }
        $this->handlePrepareModuleListEvent($event);
    }
    public function onRenderModule($event): void
    {
        if (!$this->passChecks()) {
            return;
        }
        $module = $this->getModuleFromRenderEvent($event);
        $this->handleOnRenderModule($module);
    }
    public function processArticle(string &$string, string $area = 'article', string $context = '', mixed $article = null, int $page = 0): void
    {
    }
    protected function changeDocumentBuffer(string &$buffer): bool
    {
        return \false;
    }
    protected function changeFinalHtmlOutput(string &$html): bool
    {
        return \false;
    }
    protected function changeModulePositionOutput(string &$buffer, object &$params): void
    {
    }
    protected function cleanFinalHtmlOutput(string &$html): void
    {
    }
    protected function extraChecks(): bool
    {
        return \true;
    }
    protected function handleFeedArticles(): void
    {
        if (!empty($this->_page_types) && !in_array('feed', $this->_page_types, \true)) {
            return;
        }
        if (!Document::isFeed() && Input::get('option', '') != 'com_acymailing') {
            return;
        }
        $document = Document::get();
        if (!isset($document->items)) {
            return;
        }
        $context = 'feed';
        $items = $document->items;
        $params = null;
        foreach ($items as $item) {
            $this->handleOnContentPrepare('article', $context, $item, $params);
        }
    }
    protected function handleOnAfterDispatch(): void
    {
        $this->handleFeedArticles();
    }
    protected function handleOnAfterInitialise(): void
    {
    }
    protected function handleOnAfterRender(): void
    {
    }
    protected function finalizeHtmlOutput(string &$html): void
    {
    }
    protected function handleOnAfterRenderModule(object &$module, array &$params): void
    {
    }
    protected function handleOnAfterRoute(): void
    {
    }
    protected function handleOnBeforeCompileHead(): void
    {
    }
    protected function handleOnContentPrepare(string $area, string $context, mixed &$article, mixed &$params, int $page = 0): bool
    {
        return \true;
    }
    protected function handleOnContentPrepareForm(JForm $form, object $data): void
    {
    }
    protected function is3rdPartyEditPage(): bool
    {
        $request = RequestContext::fromInput();
        // Disable on SP PageBuilder edit form: option=com_sppagebuilder&view=form
        if ($request->isOptionView('com_sppagebuilder', 'form')) {
            return \true;
        }
        return \false;
    }
    protected function loadStylesAndScripts(string &$buffer): void
    {
    }
    protected function passChecks(): bool
    {
        $is_indexer = Indexer::isActive();
        if (!$is_indexer && !is_null($this->_pass)) {
            return $this->_pass;
        }
        if (!$is_indexer) {
            $this->setPass(\false);
        }
        if (!$this->isFrameworkEnabled()) {
            return \false;
        }
        if (!$this->_enable_on_edit_pages && $this->is3rdPartyEditPage()) {
            return \false;
        }
        if (!$is_indexer && $this->_doc_ready && !$this->passPageTypes()) {
            return \false;
        }
        if (!$this->_enable_in_frontend && Document::isSite()) {
            return \false;
        }
        $is_joomlaupdate = $this->app->input->get('option') == 'com_joomlaupdate' && $this->app->input->get('task') == 'install';
        if ($this->app->input->get('option')) {
            $this->resetPass();
        }
        if ($is_joomlaupdate) {
            return \false;
        }
        if (!$this->_enable_in_indexer && $is_indexer) {
            return \false;
        }
        $is_admin = !Document::isSite() && !$is_indexer;
        if (!$this->_enable_in_admin && $is_admin) {
            return \false;
        }
        // disabled by url?
        if ($this->_can_disable_by_url && Protect::isDisabledByUrl($this->_alias)) {
            return \false;
        }
        if (!$this->extraChecks()) {
            return \false;
        }
        if (!$is_indexer) {
            $this->setPass(\true);
        }
        return \true;
    }
    protected function passPageTypes(): bool
    {
        if (empty($this->_page_types)) {
            return \true;
        }
        if (in_array('*', $this->_page_types, \true)) {
            return \true;
        }
        if (Document::isFeed()) {
            return in_array('feed', $this->_page_types, \true);
        }
        if (Document::isPDF()) {
            return in_array('pdf', $this->_page_types, \true);
        }
        $document = Document::get();
        if (!$document) {
            return \false;
        }
        $page_type = $document->getType();
        return in_array($page_type, $this->_page_types, \true);
    }
    protected function throwError(string $error): void
    {
        // Return if page is not an admin page or the admin login page
        if (!Document::isAdministrator() || User::isGuest()) {
            return;
        }
        // load the admin language file
        Language::get()->load('plg_' . $this->_type . '_' . $this->_name, JPATH_PLUGINS . '/' . $this->_type . '/' . $this->_name);
        $text = JText::sprintf($this->_lang_prefix . '_' . $error, JText::_($this->_title));
        $text = JText::_($text) . ' ' . JText::sprintf($this->_lang_prefix . '_EXTENSION_CAN_NOT_FUNCTION', JText::_($this->_title));
        Message::enqueueOnce($text, 'error');
    }
    private function getAfterRenderModuleArguments(array $arguments): array
    {
        $event = $arguments[0] ?? null;
        if ($event instanceof AfterRenderModuleEvent) {
            return [$event->getModule(), $event->getArguments()];
        }
        if (!$event instanceof JEvent) {
            return [$event, $arguments[1] ?? []];
        }
        $arguments = $event->getArguments();
        return [$arguments[0] ?? null, $arguments[1] ?? []];
    }
    private function getContentPrepareArguments(array $arguments): array
    {
        $event = $arguments[0] ?? '';
        if ($event instanceof ContentPrepareEvent) {
            return [$event->getContext(), $event->getItem(), $event->getParams(), $event->getPage() ?? 0];
        }
        if (!$event instanceof JEvent) {
            return [$event, $arguments[1] ?? (object) [], $arguments[2] ?? new JRegistry(), $arguments[3] ?? 0];
        }
        $arguments = $event->getArguments();
        return [$arguments[0] ?? '', $arguments[1] ?? (object) [], $arguments[2] ?? new JRegistry(), $arguments[3] ?? 0];
    }
    private function getContentPrepareData(array $arguments): array
    {
        [$context, $article, $params, $page] = $this->getContentPrepareArguments($arguments);
        $area = Security::getContentSourceArea($article, (string) $context);
        if ($params instanceof JRegistry && $params->get('rl_search')) {
            $context = 'com_search.' . $params->get('readmore_limit');
        }
        return [$area, is_string($context) ? $context : '', $article, $params, $page];
    }
    private function getContentPrepareFormArguments(array $arguments): array
    {
        $event = $arguments[0] ?? null;
        if ($event instanceof PrepareFormEvent) {
            return [$event->getForm(), (object) $event->getData()];
        }
        if (!$event instanceof JEvent) {
            return [$event, (object) ($arguments[1] ?? [])];
        }
        $arguments = $event->getArguments();
        return [$arguments[0] ?? null, (object) ($arguments[1] ?? [])];
    }
    private function getFinderResultArguments(array $arguments): array
    {
        $event = $arguments[0] ?? null;
        if ($event instanceof ResultEvent) {
            return [$event->getItem(), $event->getQuery()];
        }
        if (!$event instanceof JEvent) {
            return [$event, $arguments[1] ?? []];
        }
        $arguments = $event->getArguments();
        return [$arguments[0] ?? null, $arguments[1] ?? []];
    }
    private function getModuleFromRenderEvent(object $event): ?object
    {
        if ($event instanceof BeforeRenderModuleEvent) {
            return $event->getModule();
        }
        if (!$event instanceof JEvent) {
            return $event;
        }
        return $event->getArguments()[0] ?? null;
    }
    private function handlePrepareModuleListEvent(PrepareModuleListEvent|JEvent|array $event): void
    {
        if ($event instanceof PrepareModuleListEvent) {
            $modules = $event->getModules();
            $this->handleOnPrepareModuleList($modules);
            $event->updateModules($modules);
            return;
        }
        if (!$event instanceof JEvent) {
            $this->handleOnPrepareModuleList($event);
            return;
        }
        $arguments = $event->getArguments();
        $modules = $arguments[0] ?? null;
        $this->handleOnPrepareModuleList($modules);
        $event->setArgument(0, $modules);
    }
    private function runContentPrepare(string $context, callable $callback): void
    {
        Indexer::runInContext($context, function () use ($callback): void {
            if (!$this->passChecks()) {
                return;
            }
            $this->runWithIndexerIdentity($callback);
        });
    }
    private function runWithIndexerIdentity(callable $callback): void
    {
        if (!$this->_index_as_guest || !Indexer::isActive()) {
            $callback();
            return;
        }
        $application = Application::get();
        $current_identity = User::get();
        $application->loadIdentity(User::getGuest());
        try {
            $callback();
        } finally {
            $application->loadIdentity($current_identity);
        }
    }
    private function articleContainsSourcererOutput(mixed $article, string $stage): bool
    {
        if (!is_object($article)) {
            return \false;
        }
        foreach (['title', 'created_by_alias', 'description', 'text', 'introtext', 'fulltext'] as $property) {
            if (!isset($article->{$property}) || !is_string($article->{$property}) || !$this->containsSourcererOutput($article->{$property}, $stage)) {
                continue;
            }
            return \true;
        }
        return \false;
    }
    private function containsSourcererOutput(string $html, string $stage): bool
    {
        [$stage_start, $stage_end] = Protect::getCommentTags('Sourcerer Output: ' . $stage);
        [$legacy_start, $legacy_end] = Protect::getCommentTags('Sourcerer Output');
        return str_contains($html, $stage_start) && str_contains($html, $stage_end) || str_contains($html, $legacy_start) && str_contains($html, $legacy_end);
    }
    private function protectNonSourcererOutput(string &$html, string $stage = ''): bool
    {
        [$start_markers, $end_markers] = $this->getSourcererOutputMarkers($html, $stage);
        if (empty($start_markers)) {
            return \false;
        }
        return $this->protectContentOutsideMarkers($html, $start_markers, $end_markers);
    }
    private function getSourcererOutputMarkers(string $html, string $stage): array
    {
        $comment_names = ['Sourcerer Output'];
        if ($stage !== '') {
            array_unshift($comment_names, 'Sourcerer Output: ' . $stage);
        }
        $start_markers = [];
        $end_markers = [];
        foreach ($comment_names as $comment_name) {
            [$start, $end] = Protect::getCommentTags($comment_name);
            $start_count = substr_count($html, $start);
            $end_count = substr_count($html, $end);
            if ($start_count !== $end_count) {
                return [[], []];
            }
            if ($start_count === 0) {
                continue;
            }
            $start_markers[$start] = $end;
            $end_markers[$end] = $start;
        }
        return [$start_markers, $end_markers];
    }
    private function protectContentOutsideMarkers(string &$html, array $start_markers, array $end_markers): bool
    {
        $markers = [...array_keys($start_markers), ...array_keys($end_markers)];
        $quoted_markers = [];
        foreach ($markers as $marker) {
            $quoted_markers[] = RegEx::quote($marker);
        }
        $parts = RegEx::split('(' . implode('|', $quoted_markers) . ')', $html, 's');
        $stack = [];
        foreach ($parts as &$part) {
            if (isset($start_markers[$part])) {
                $stack[] = $part;
                continue;
            }
            if (isset($end_markers[$part])) {
                if (empty($stack) || end($stack) !== $end_markers[$part]) {
                    return \false;
                }
                array_pop($stack);
                continue;
            }
            if (empty($stack) && $part !== '') {
                $part = Protect::protectString($part);
            }
        }
        if (!empty($stack)) {
            return \false;
        }
        $html = implode('', $parts);
        Protect::convertProtectionToHtmlSafe($html);
        return \true;
    }
}
