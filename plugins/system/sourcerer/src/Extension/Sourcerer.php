<?php
/**
 * @package         Sourcerer
 * @version         17.0.1
 * 
 * @author          Peter van Westen <info@regularlabs.com>
 * @link            https://regularlabs.com
 * @copyright       Copyright © 2026 Regular Labs All Rights Reserved
 * @license         GNU General Public License version 2 or later
 */

namespace RegularLabs\Plugin\System\Sourcerer\Extension;

use Joomla\Registry\Registry as JRegistry;
use RegularLabs\Library\Document as RL_Document;
use RegularLabs\Library\Html as RL_Html;
use RegularLabs\Library\Indexer as RL_Indexer;
use RegularLabs\Library\Input as RL_Input;
use RegularLabs\Library\Plugin\System as RL_SystemPlugin;
use RegularLabs\Library\Protect as RL_Protect;
use RegularLabs\Plugin\System\Sourcerer\Area;
use RegularLabs\Plugin\System\Sourcerer\Clean;
use RegularLabs\Plugin\System\Sourcerer\Modules;
use RegularLabs\Plugin\System\Sourcerer\Params;
use RegularLabs\Plugin\System\Sourcerer\Protect;
use RegularLabs\Plugin\System\Sourcerer\Replace;
use RegularLabs\Plugin\System\Sourcerer\RequestInput;
use RegularLabs\Plugin\System\Sourcerer\Security;

defined('_JEXEC') or die;

final class Sourcerer extends RL_SystemPlugin
{
    private const TRUSTED_SOURCE_EVENT_PRIORITY = PHP_INT_MAX;
    static    $_extra_events        = [
        'onAfterCleanModuleList'                   => 'onAfterCleanModuleList',
        'onRegularLabsArticlesAnywherePrepareText' => 'onRegularLabsArticlesAnywherePrepareText',
    ];
    public    $_can_disable_by_url  = false;
    public    $_lang_prefix         = 'SRC';
    protected $_finalize_final_html = true;
    protected $_index_as_guest      = true;
    private bool $component_dispatch_complete = false;

    public function init(): void
    {
        if (method_exists(RL_Protect::class, 'enableSourcererTagProtection'))
        {
            RL_Protect::enableSourcererTagProtection();
        }

        $this->getDispatcher()->addListener(
            'onContentPrepare',
            [$this, 'protectTrustedCustomModuleContent'],
            self::TRUSTED_SOURCE_EVENT_PRIORITY
        );
    }

    public function onAfterCleanModuleList(mixed $event): void
    {
        if ( ! $this->passChecks() || ( ! is_array($event) && ! is_object($event)))
        {
            return;
        }

        Modules::protectFromListEvent($event);
    }

    public function onRegularLabsArticlesAnywherePrepareText(mixed $event): void
    {
        if ( ! $this->passChecks() || ! is_object($event) || ! method_exists($event, 'getArguments'))
        {
            return;
        }

        $source = $event->getArguments()[0] ?? null;

        if ( ! is_object($source)
            || ! isset($source->id, $source->created_by, $source->text)
            || (int) $source->id < 1
            || ! is_string($source->text))
        {
            return;
        }

        $this->prepareArticle('com_content.article', $source);
    }

    public function protectTrustedCustomModuleContent(mixed $event): void
    {
        if ( ! $this->passChecks() || ! is_object($event))
        {
            return;
        }

        Modules::protectCustomContentFromEvent($event);
    }

    protected function changeDocumentBuffer(string &$buffer): bool
    {
        if ( ! RL_Document::isHtml())
        {
            return false;
        }

        return Area::tag($buffer, 'component');
    }

    protected function changeFinalHtmlOutput(string &$html): bool
    {
        $params = Params::get();

        [$pre, $body, $post] = RL_Html::getBody($html);

        $component   = RL_Document::getComponentBuffer();
        $diagnostics = self::getFinalOutputDiagnostics($body, $component);

        if ( ! is_null($component))
        {
            $body = Area::tagContent($body, $component, 'component');
        }

        Protect::_($body);
        Replace::replaceInTheRest($body, $diagnostics);

        Clean::cleanFinalHtmlOutput($body);
        RL_Protect::unprotect($body);

        self::processHead($pre, $params->enable_in_head);

        $html = $pre . $body . $post;

        return true;
    }

    protected function finalizeHtmlOutput(string &$html): void
    {
        if (method_exists(RL_Protect::class, 'unprotectSourcererTags'))
        {
            RL_Protect::unprotectSourcererTags($html);
        }

        foreach (['Content', 'Module', 'Final', ''] as $stage)
        {
            $name = 'Sourcerer Output' . ($stage === '' ? '' : ': ' . $stage);

            RL_Protect::removeCommentTags($html, $name);
        }

        Modules::clean($html);
    }

    protected function handleOnAfterDispatch(): void
    {
        $this->component_dispatch_complete = true;

        parent::handleOnAfterDispatch();
    }

    protected function handleOnAfterInitialise(): void
    {
        RequestInput::capture();
    }

    protected function handleOnAfterRenderModule(object &$module, array &$params): void
    {
        Modules::replaceModule($module);
    }

    protected function handleOnContentPrepare(
        string $area,
        string $context,
        mixed  &$article,
        mixed  &$params,
        int    $page = 0
    ): bool
    {
        if ( ! is_object($article))
        {
            return false;
        }

        $this->prepareArticle($context, $article);

        return false;
    }

    private static function getFinalOutputDiagnostics(string $body, string|null $component): object|null
    {
        if ( ! RL_Input::getBool('sourcerer_diagnostics'))
        {
            return null;
        }

        $component_buffer = match (true)
        {
            is_null($component) => 'missing',
            $component === ''   => 'empty',
            default             => 'present',
        };

        return (object) [
            'component_buffer'        => $component_buffer,
            'component_exact_matches' => is_null($component) || $component === ''
                ? 0
                : substr_count($body, $component),
            'component_start_markers' => substr_count($body, '<!-- START: SRC_COMPONENT -->'),
            'component_end_markers'   => substr_count($body, '<!-- END: SRC_COMPONENT -->'),
        ];
    }

    private static function processHead(string &$head, bool $enabled): void
    {
        if ( ! $enabled)
        {
            Clean::cleanTagsFromHead($head);

            return;
        }

        Replace::replace($head, 'head');
    }

    private static function replaceArticleField(
        object      $article,
        string      $field,
        string      $area,
        bool        $remove,
        object      $security,
        object|null $diagnostics
    ): bool
    {
        if ( ! isset($article->{$field}) || ! is_string($article->{$field}))
        {
            return false;
        }

        Replace::replace(
            $article->{$field},
            $area,
            $article,
            $remove,
            $security,
            'Content',
            $diagnostics
        );

        return true;
    }

    private static function replaceArticlePageTitle(
        object      $article,
        string      $area,
        bool        $remove,
        object      $security,
        object|null $diagnostics
    ): void
    {
        if ( ! isset($article->params) || ! ($article->params instanceof JRegistry))
        {
            return;
        }

        $page_title = $article->params->get('article_page_title');

        if ( ! is_string($page_title) || $page_title === '')
        {
            return;
        }

        Replace::replace(
            $page_title,
            $area,
            $article,
            $remove,
            $security,
            'Content',
            $diagnostics
        );

        $article->params->set('article_page_title', $page_title);
    }

    private function getContentArea(string $context, object $article): string
    {
        if (isset($article->created_by))
        {
            return 'articles';
        }

        if (str_starts_with($context, 'com_'))
        {
            return 'components';
        }

        if ( ! $this->component_dispatch_complete
            && str_starts_with(RL_Input::getCmd('option'), 'com_'))
        {
            return 'components';
        }

        return 'other';
    }

    private function getContentDiagnostics(string $context): object|null
    {
        if ( ! RL_Input::getBool('sourcerer_diagnostics'))
        {
            return null;
        }

        return (object) [
            'context'                     => $context,
            'component_dispatch_complete' => $this->component_dispatch_complete,
        ];
    }

    private function prepareArticle(string $context, object &$article): void
    {
        $src_params            = Params::get();
        $is_custom_module_text = $context === 'mod_custom.content'
            && isset($article->text)
            && is_string($article->text);

        $area = $this->getContentArea($context, $article);

        $remove = $src_params->remove_from_search
            && (RL_Indexer::isContext($context)
                || in_array($context, ['com_search.search', 'com_search.search.article'], true));

        $security    = $area === 'articles'
            ? Security::getForArticle($article)
            : Security::getForUntrustedArea($area);
        $diagnostics = $this->getContentDiagnostics($context);

        self::replaceArticleField($article, 'description', $area, $remove, $security, $diagnostics);
        self::replaceArticleField($article, 'title', $area, $remove, $security, $diagnostics);

        if ($src_params->enable_in_head)
        {
            self::replaceArticlePageTitle($article, $area, $remove, $security, $diagnostics);
        }

        // Don't handle article texts in category list view
        if (RL_Document::isCategoryList($context))
        {
            return;
        }

        $trusted = $is_custom_module_text
            ? Modules::maskForContentPreparation($article->text)
            : [];

        $text_replaced = self::replaceArticleField(
            $article,
            'text',
            $area,
            $remove,
            $security,
            $diagnostics
        );

        if ( ! empty($trusted))
        {
            Modules::restoreAfterContentPreparation($article->text, $trusted);
        }

        if ($text_replaced)
        {
            // Don't also do stuff on introtext/fulltext if text is set
            return;
        }

        self::replaceArticleField($article, 'introtext', $area, $remove, $security, $diagnostics);
        self::replaceArticleField($article, 'fulltext', $area, $remove, $security, $diagnostics);
    }
}
