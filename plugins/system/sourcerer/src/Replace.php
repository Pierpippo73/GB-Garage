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

namespace RegularLabs\Plugin\System\Sourcerer;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text as JText;
use RegularLabs\Library\Application as RL_Application;
use RegularLabs\Library\ArrayHelper as RL_Array;
use RegularLabs\Library\Document as RL_Document;
use RegularLabs\Library\Html as RL_Html;
use RegularLabs\Library\Input as RL_Input;
use RegularLabs\Library\ObjectHelper as RL_Object;
use RegularLabs\Library\Php as RL_Php;
use RegularLabs\Library\PluginTag as RL_PluginTag;
use RegularLabs\Library\Protect as RL_Protect;
use RegularLabs\Library\RegEx as RL_RegEx;
use RegularLabs\Library\StringHelper as RL_String;
use Throwable;

class Replace
{
    public static function replace(
        string      &$string,
        string      $area = 'article',
        object|null $article = null,
        bool        $remove = false,
        object|null $security = null,
        string      $output_stage = 'Final',
        object|null $diagnostics = null
    ): void
    {
        if ($string == '')
        {
            return;
        }

        $security = Security::resolve($area, $security);

        if ( ! $remove
            && ! Params::get()->remove_unprocessed_tags
            && ! is_null(self::getGeneralRestrictionMessage($security)))
        {
            Protect::protectTags($string);

            return;
        }

        Protect::_($string);

        $regex = Params::getRegex();

        $array       = self::stringToSplitArray($string, $regex);
        $array_count = count($array);

        if ($array_count <= 1)
        {
            return;
        }

        $security = self::addRestrictionDiagnostics(
            $security,
            $area,
            $article,
            $output_stage,
            $diagnostics
        );

        for ($i = 1; $i < $array_count - 1; $i++)
        {
            if ($i % 2 === 0 || ! RL_RegEx::match($regex, $array[$i], $match))
            {
                continue;
            }

            $content = self::handleMatch(
                $match,
                $article,
                $remove,
                $security,
                $output_stage
            );

            $array[$i] = $match['start_pre'] . $match['start_post'] . $content . $match['end_pre'] . $match['end_post'];
        }

        $string = implode('', $array);
    }

    public static function replaceInTheRest(string &$string, object|null $diagnostics = null): void
    {
        if ($string == '')
        {
            return;
        }

        [$start_tags, $end_tags] = Params::getTags();

        [$pre_string, $string, $post_string] = RL_Html::getContentContainingSearches(
            $string,
            $start_tags,
            $end_tags
        );

        if ($string == '')
        {
            $string = $pre_string . $string . $post_string;

            return;
        }

        // COMPONENT
        if (RL_Document::isFeed())
        {
            $string = RL_RegEx::replace('(<item[^>]*>)', '\1<!-- START: SRC_COMPONENT -->', $string);
            $string = str_replace('</item>', '<!-- END: SRC_COMPONENT --></item>', $string);
        }

        if ( ! str_contains($string, '<!-- START: SRC_COMPONENT -->'))
        {
            Area::tag($string, 'component');
        }

        $components = Area::get($string, 'component');

        foreach ($components as $component)
        {
            self::replace(
                $component[1],
                'components',
                output_stage: 'Final',
                diagnostics: $diagnostics
            );
            $string = str_replace($component[0], $component[1], $string);
        }

        // EVERYWHERE
        self::replace(
            $string,
            'other',
            output_stage: 'Final',
            diagnostics: $diagnostics
        );

        $string = $pre_string . $string . $post_string;
    }

    private static function addInlineVariables(object $data, string &$content): void
    {
    }

    private static function addRestrictionDiagnostics(
        object      $security,
        string      $area,
        object|null $article,
        string      $output_stage,
        object|null $render_diagnostics
    ): object
    {
        if ( ! RL_Input::getBool('sourcerer_diagnostics'))
        {
            return $security;
        }

        $policy            = clone $security;
        $area_params       = $policy->area_params;
        $content_object    = is_null($article) ? 'none' : get_debug_type($article);
        $component_buffer  = $render_diagnostics->component_buffer ?? 'unknown';
        $context           = RL_RegEx::replace(
            '[^a-zA-Z0-9_.:-]',
            '',
            (string) ($render_diagnostics->context ?? 'none')
        );
        $component_matches = (int) ($render_diagnostics->component_exact_matches ?? 0);
        $component_starts  = (int) ($render_diagnostics->component_start_markers ?? 0);
        $component_ends    = (int) ($render_diagnostics->component_end_markers ?? 0);

        if ( ! in_array($component_buffer, ['missing', 'empty', 'present'], true))
        {
            $component_buffer = 'unknown';
        }

        if ($context === '')
        {
            $context = 'none';
        }

        $policy->diagnostic = [
            'area'                    => Params::getAreaType($area),
            'stage'                   => $output_stage,
            'route'                   => RL_Input::getCmd('option', 'none'),
            'context'                 => $context,
            'component_dispatched'    => isset($render_diagnostics->component_dispatch_complete)
                ? ($render_diagnostics->component_dispatch_complete ? 'yes' : 'no')
                : 'unknown',
            'content_object'          => $content_object,
            'created_by'              => isset($article->created_by) ? 'present' : 'missing',
            'modified_by'             => isset($article->modified_by) ? 'present' : 'missing',
            'area_enabled'            => (bool) ($area_params->enable ?? true) ? 'yes' : 'no',
            'untrusted_enabled'       => (bool) ($area_params->enable_untrusted ?? false) ? 'yes' : 'no',
            'request_source_tag'      => (bool) ($security->request_source_tag ?? false) ? 'present' : 'absent',
            'request_user_trusted'    => (bool) ($security->request_user_trusted ?? false) ? 'yes' : 'no',
            'component_buffer'        => $component_buffer,
            'component_exact_matches' => $component_matches,
            'component_start_markers' => $component_starts,
            'component_end_markers'   => $component_ends,
        ];

        return $policy;
    }

    private static function appendRestrictionDiagnostics(string $message, object $security): string
    {
        if (empty($security->diagnostic) || ! is_array($security->diagnostic))
        {
            return $message;
        }

        $details = [];

        foreach ($security->diagnostic as $name => $value)
        {
            $details[] = $name . '=' . $value;
        }

        return $message . ' Sourcerer diagnostic: ' . implode('; ', $details) . '.';
    }

    private static function containsCss(string $string): bool
    {
        return RL_RegEx::match(
            '<\s*(?:style\b|link\b[^>]*(?:rel\s*=\s*["\']?stylesheet|type\s*=\s*["\']?text/css))',
            $string
        );
    }

    private static function containsJavascript(string $string): bool
    {
        $string = RL_String::html_entity_decoder($string);

        if (RL_RegEx::match('<\s*\/?\s*script\b', $string))
        {
            return true;
        }

        if ( ! RL_RegEx::matchAll('<[^>]+>', $string, $tags))
        {
            return false;
        }

        foreach ($tags as $tag)
        {
            $tag = $tag[0] ?? '';

            if (RL_RegEx::match('\s(?:on[a-z0-9:_-]+|srcdoc)\s*=', $tag))
            {
                return true;
            }

            $normalized_tag = RL_RegEx::replace('[\x00-\x20]+', '', $tag);
            $normalized_tag = RL_String::strtolower($normalized_tag);

            if (str_contains($normalized_tag, 'javascript:')
                || str_contains($normalized_tag, 'data:text/html')
                || str_contains($normalized_tag, 'data:image/svg+xml')
                || RL_RegEx::match('attributename=["\']?on[a-z0-9:_-]+', $normalized_tag)
            )
            {
                return true;
            }
        }

        return false;
    }

    private static function convertWysiwygToPlainText(string $content): string
    {
        RL_Protect::protectByRegex(
            $content,
            '<!-- START: (?<name>[^>]+) -->.*?<!-- END: \k<name> -->'
        );

        $content = RL_Html::convertWysiwygToPlainText($content);
        RL_Protect::unprotect($content);

        // Remove trailing spaces from EOT lines
        $content = RL_RegEx::replace('(=\s*<<<([^\s]+)) ?(\n.*?\2;) ?', '\1\3', $content);

        return $content;
    }

    private static function executePhp(string $script, object|null $article): string
    {
        $tmp_path = trim((string) (Params::get()->tmp_path ?? ''));

        if ($tmp_path === '')
        {
            return (string) RL_Php::execute('<?php ' . $script . ' ?>', $article);
        }

        $application     = RL_Application::get();
        $global_tmp_path = $application->get('tmp_path', JPATH_ROOT . '/tmp');

        $application->set('tmp_path', $tmp_path);

        try
        {
            return (string) RL_Php::execute('<?php ' . $script . ' ?>', $article);
        }
        finally
        {
            $application->set('tmp_path', $global_tmp_path);
        }
    }

    private static function filterUntrustedCode(string $content, object $data, object $security): string
    {
        if ( ! ($security->untrusted ?? false))
        {
            return $content;
        }

        $area_params = $security->area_params;

        if ( ! (bool) ($area_params->enable_php ?? true)
            || ! (bool) ($security->pass_php ?? false))
        {
            $message = Protect::getMessageCommentTag(
                JText::sprintf('SRC_CODE_REMOVED_NOT_ALLOWED', JText::_('RL_PHP_CODE'))
            );
            $content = RL_RegEx::replace('<\?php\b.*?\?>', $message, $content);
            self::removeAttributes($data, ['file', 'php', 'include', 'include_once', 'require', 'require_once']);
        }

        if ( ! (bool) ($area_params->enable_js ?? true)
            || ! (bool) ($security->pass_js ?? false))
        {
            $content = self::removeJavascript($content);
            self::removeAttributes($data, ['js', 'javascript', 'script', 'defer', 'async']);
        }

        if ( ! (bool) ($area_params->enable_css ?? true)
            || ! (bool) ($security->pass_css ?? false))
        {
            $content = self::removeCss($content);
            self::removeAttributes($data, ['css', 'style', 'stylesheet']);
        }

        return $content;
    }

    private static function getCodeRestrictionMessage(object $code_usage, object $security): ?string
    {
        $area_params = $security->area_params;

        foreach (['php', 'js', 'css'] as $code_type)
        {
            if ( ! $code_usage->{$code_type})
            {
                continue;
            }

            $language_key = $code_type === 'js'
                ? 'RL_JAVASCRIPT'
                : ($code_type === 'php' ? 'RL_PHP_CODE' : 'RL_CSS');
            $label        = JText::_($language_key);

            if ( ! (bool) ($area_params->{'enable_' . $code_type} ?? true))
            {
                return JText::sprintf('SRC_CODE_REMOVED_NOT_ALLOWED', $label);
            }

            if ( ! (bool) ($security->{'pass_' . $code_type} ?? false))
            {
                return JText::sprintf('SRC_CODE_REMOVED_SECURITY', $label);
            }
        }

        return null;
    }

    private static function getCodeUsage(string $content, object $data): object
    {
        $php_files_code = '';

        return (object) [
            'css'            => self::containsCss($content)
                || ! empty(RL_Object::getValue($data, ['css', 'style', 'stylesheet'])),
            'js'             => self::containsJavascript($content)
                || ! empty(RL_Object::getValue($data, ['js', 'javascript', 'script'])),
            'php'            => RL_RegEx::match('<\?php\b', $content)
                || $php_files_code !== '',
            'php_files_code' => $php_files_code,
        ];
    }

    private static function getGeneralRestrictionMessage(object $security): ?string
    {
        $area_params = $security->area_params;

        if ( ! (bool) ($area_params->enable ?? true))
        {
            return JText::_('SRC_CODE_REMOVED_NOT_ENABLED');
        }

        if (($security->untrusted ?? false)
            && ! (bool) ($area_params->enable_untrusted ?? false))
        {
            return self::appendRestrictionDiagnostics(
                JText::_('SRC_CODE_REMOVED_UNTRUSTED'),
                $security
            );
        }

        if ( ! (bool) ($security->pass ?? false))
        {
            return JText::sprintf('SRC_CODE_REMOVED_SECURITY', '');
        }

        return null;
    }

    private static function getPhpErrorMessage(Throwable $throwable, int $source_start_line): string
    {
        $type        = get_debug_type($throwable);
        $message     = RL_RegEx::replace('\s+', ' ', trim($throwable->getMessage()));
        $message     = RL_String::truncate($message, 500);
        $file        = str_replace('\\', '/', $throwable->getFile());
        $site_path   = rtrim(str_replace('\\', '/', JPATH_SITE), '/');
        $is_source   = str_contains($file, '/regularlabs/custom_php/');
        $source_line = $is_source && method_exists(RL_Php::class, 'getSourceLine')
            ? RL_Php::getSourceLine($throwable)
            : null;

        if ( ! $is_source)
        {
            $display_file = str_starts_with($file, $site_path . '/')
                ? substr($file, strlen($site_path) + 1)
                : $file;
            $details      = JText::sprintf(
                'SRC_PHP_ERROR_IN_FILE',
                $type,
                $display_file,
                $throwable->getLine(),
                $message
            );

            return self::getPhpExecutionFailedComment($details);
        }

        $message = str_replace([$throwable->getFile(), $file], 'Sourcerer block', $message);
        $details = $source_line
            ? JText::sprintf(
                'SRC_PHP_ERROR_IN_BLOCK',
                $type,
                $source_start_line + $source_line - 1,
                $message
            )
            : JText::sprintf('SRC_PHP_ERROR', $type, $message);

        return self::getPhpExecutionFailedComment($details);
    }

    private static function getPhpExecutionFailedComment(string $details): string
    {
        $comment = JText::sprintf('SRC_PHP_EXECUTION_FAILED', $details);

        return str_replace('--', '- -', $comment);
    }

    private static function getPhpFileCodeByType(?string $file, string $type): string
    {


        return '';
    }

    private static function getPhpFilePath(string $file, string $include_path): string
    {
        $file = trim(str_replace('\\', '/', $file));
        $file = ltrim($file, '/');

        if ($file == '' || str_contains($file, "\0") || str_contains($file, '://'))
        {
            return '';
        }

        $parts = explode('/', $file);

        foreach ($parts as $part)
        {
            if ($part == '' || $part == '.' || $part == '..')
            {
                return '';
            }
        }

        $include_path = '/' . trim(str_replace('\\', '/', $include_path), ' /') . '/';
        $path         = $include_path . implode('/', $parts);
        $base_path    = realpath(JPATH_SITE . $include_path);
        $file_path    = realpath(JPATH_SITE . $path);

        if ($base_path === false || $file_path === false || ! is_file($file_path))
        {
            return '';
        }

        if ($file_path != $base_path && ! str_starts_with($file_path, $base_path . DIRECTORY_SEPARATOR))
        {
            return '';
        }

        return $path;
    }

    private static function getPhpFilesCode(object $data): string
    {


        return '';
    }

    private static function getPhpOutput(
        string      $script,
        int         $source_start_line,
        object|null $article
    ): string
    {
        if ( ! (bool) (Params::get()->handle_php_errors ?? true))
        {
            return self::executePhp($script, $article);
        }

        $output_buffer_level = ob_get_level();

        try
        {
            return self::executePhp($script, $article);
        }
        catch (Throwable $throwable)
        {
            while (ob_get_level() > $output_buffer_level)
            {
                ob_end_clean();
            }

            return RL_Document::isHtml()
                ? Protect::getMessageCommentTag(
                    self::getPhpErrorMessage($throwable, $source_start_line)
                )
                : '';
        }
    }

    private static function handleMatch(
        array       &$match,
        object|null $article,
        bool        $remove,
        object      $security,
        string      $output_stage
    ): string
    {
        if ($remove)
        {
            return '';
        }

        $restriction_message = self::getGeneralRestrictionMessage($security);

        if ( ! is_null($restriction_message))
        {
            return Protect::getMessageCommentTag($restriction_message);
        }

        $params = Params::get();

        $data = RL_PluginTag::getAttributesFromString($match['data']);

        $content = trim($match['content']);

        $data->raw ??= false;

        // Remove html tags if code is placed via the WYSIWYG editor
        if ( ! $data->raw)
        {
            $content = self::convertWysiwygToPlainText($content);
        }

        if (method_exists(RL_Protect::class, 'unprotectSourcererTags'))
        {
            RL_Protect::unprotectSourcererTags($content);
        }

        self::replacePhpShortCodes($content);

        $content = self::filterUntrustedCode($content, $data, $security);

        $code_usage          = self::getCodeUsage($content, $data);
        $restriction_message = self::getCodeRestrictionMessage($code_usage, $security);

        if ( ! is_null($restriction_message))
        {
            return Protect::getMessageCommentTag($restriction_message);
        }


        self::replaceTags($content, $security, $article);

        if (method_exists(RL_Protect::class, 'reprotectSourcererTags'))
        {
            RL_Protect::reprotectSourcererTags($content);
        }

        if ($content !== '')
        {
            $content = RL_Protect::wrapInCommentTags(
                'Sourcerer Output: ' . $output_stage,
                $content
            );
        }

        if ($data->raw)
        {
            return $content;
        }

        $trim = $data->trim ?? $params->trim;

        if ($trim)
        {
            $tags = RL_Html::cleanSurroundingTags([
                'start_pre'  => $match['start_pre'],
                'start_post' => $match['start_post'],
            ], ['div', 'p', 'span']);

            $match = [...$match, ...$tags];

            $tags = RL_Html::cleanSurroundingTags([
                'end_pre'  => $match['end_pre'],
                'end_post' => $match['end_post'],
            ], ['div', 'p', 'span']);

            $match = [...$match, ...$tags];

            $tags = RL_Html::cleanSurroundingTags([
                'start_pre' => $match['start_pre'],
                'end_post'  => $match['end_post'],
            ], ['div', 'p', 'span']);

            $match = [...$match, ...$tags];
        }

        return $content;
    }

    private static function loadFiles(
        object $data,
        string &$content,
        object $code_usage
    ): void
    {
    }

    private static function loadMediaFile(?string $file, string $type, array $options = []): void
    {
    }

    private static function loadScripts(object $data): void
    {
    }

    private static function loadStylesheets(object $data): void
    {
    }

    private static function removeAttributes(object $data, array $attributes): void
    {
        foreach ($attributes as $attribute)
        {
            unset($data->{$attribute});
        }
    }

    private static function removeCss(string $content): string
    {
        $message = Protect::getMessageCommentTag(
            JText::sprintf('SRC_CODE_REMOVED_NOT_ALLOWED', JText::_('RL_CSS'))
        );
        $content = RL_RegEx::replace('<\s*style\b[^>]*>.*?<\s*\/\s*style\s*>', $message, $content);
        $content = RL_RegEx::replace('<\s*\/?\s*style\b[^>]*>', $message, $content);

        if ( ! RL_RegEx::matchAll('<\s*link\b[^>]*>', $content, $tags))
        {
            return $content;
        }

        foreach ($tags as $tag)
        {
            $tag = $tag[0] ?? '';

            if ($tag === '' || ! self::containsCss($tag))
            {
                continue;
            }

            $content = str_replace($tag, $message, $content);
        }

        return $content;
    }

    private static function removeJavascript(string $content): string
    {
        $message = Protect::getMessageCommentTag(
            JText::sprintf('SRC_CODE_REMOVED_NOT_ALLOWED', JText::_('RL_JAVASCRIPT'))
        );
        $content = RL_RegEx::replace('<\s*script\b[^>]*>.*?<\s*\/\s*script\s*>', $message, $content);
        $content = RL_RegEx::replace('<\s*\/?\s*script\b[^>]*>', $message, $content);

        if ( ! RL_RegEx::matchAll('<[^>]+>', $content, $tags))
        {
            return $content;
        }

        foreach ($tags as $tag)
        {
            $tag = $tag[0] ?? '';

            if ($tag === '' || ! self::containsJavascript($tag))
            {
                continue;
            }

            $safe_tag = self::removeJavascriptAttributes($tag);
            $safe_tag = self::containsJavascript($safe_tag) ? $message : $safe_tag;
            $content  = str_replace($tag, $safe_tag, $content);
        }

        return $content;
    }

    private static function removeJavascriptAttributes(string $tag): string
    {
        $pattern = '\s+(?<name>[^\s=\/>]+)(?:\s*=\s*(?<value>"[^"]*"|\'[^\']*\'|[^\s>]+))?';

        if ( ! RL_RegEx::matchAll($pattern, $tag, $attributes))
        {
            return $tag;
        }

        foreach ($attributes as $attribute)
        {
            $name             = RL_String::strtolower($attribute['name'] ?? '');
            $value            = trim($attribute['value'] ?? '', " \n\r\t\v\f\"'");
            $normalized_value = RL_String::html_entity_decoder($value);
            $normalized_value = RL_RegEx::replace('[\x00-\x20]+', '', $normalized_value);
            $normalized_value = RL_String::strtolower($normalized_value);
            $is_event         = RL_RegEx::matchesEntireString('on[a-z0-9:_-]+', $name);
            $is_event_target  = $name === 'attributename'
                && RL_RegEx::matchesEntireString('on[a-z0-9:_-]+', $normalized_value);
            $is_unsafe_value  = str_contains($normalized_value, 'javascript:')
                || str_contains($normalized_value, 'data:text/html')
                || str_contains($normalized_value, 'data:image/svg+xml');

            if ( ! $is_event && $name !== 'srcdoc' && ! $is_event_target && ! $is_unsafe_value)
            {
                continue;
            }

            $tag = str_replace($attribute[0], '', $tag);
        }

        return $tag;
    }

    private static function removePhpComments(string $script): string
    {
        $script_without_comments = '';

        foreach (token_get_all('<?php ' . $script) as $token)
        {
            if ( ! is_array($token))
            {
                $script_without_comments .= $token;

                continue;
            }

            if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true))
            {
                $script_without_comments .= ' ';

                continue;
            }

            $script_without_comments .= $token[1];
        }

        return $script_without_comments;
    }

    private static function replacePhpShortCodes(string &$string): void
    {
        // Replace <? with <?php
        $string = RL_RegEx::replace('<\?(\s.*?)\?>', '<?php\1?>', $string);
        // Replace <?= with <?php echo
        $string = RL_RegEx::replace('<\?=\s*(.*?)\?>', '<?php echo \1?>', $string);
    }

    private static function replaceTags(
        string      &$string,
        object      $security,
        object|null $article
    ): void
    {
        if (empty($string))
        {
            return;
        }

        self::replaceTagsByType($string, 'php', $security, $article);
        self::replaceTagsByType($string, 'all', $security, $article);
        self::replaceTagsByType($string, 'js', $security, $article);
        self::replaceTagsByType($string, 'css', $security, $article);
    }

    private static function replaceTagsAll(
        string &$string,
        bool   $enabled,
        bool   $security_pass,
        object $area_params
    ): void
    {
        if (empty($string))
        {
            return;
        }

        if ( ! $enabled)
        {
            // replace source block content with HTML comment
            $string = Protect::getMessageCommentTag(JText::_('SRC_CODE_REMOVED_NOT_ENABLED'));

            return;
        }

        if ( ! $security_pass)
        {
            // replace source block content with HTML comment
            $string = Protect::getMessageCommentTag(JText::sprintf('SRC_CODE_REMOVED_SECURITY', ''));

            return;
        }

        $forbidden_tags_array = explode(',', $area_params->forbidden_tags);
        $forbidden_tags_array = RL_Array::clean($forbidden_tags_array);
        $forbidden_tags_array = RL_String::toLowerCase($forbidden_tags_array);
        // remove the comment tag syntax from the array - they cannot be disabled
        $forbidden_tags_array = array_diff($forbidden_tags_array, ['!--']);
        // reindex the array
        $forbidden_tags_array = [...$forbidden_tags_array];

        $has_forbidden_tags = false;

        foreach ($forbidden_tags_array as $forbidden_tag)
        {
            if (RL_String::contains($string, '<' . $forbidden_tag, false))
            {
                $has_forbidden_tags = true;
                break;
            }
        }

        if ( ! $has_forbidden_tags)
        {
            return;
        }

        // double tags
        $tag_regex = '<\s*([a-z\!][^>\s]*?)(?:\s+.*?)?>.*?</\1>';
        RL_RegEx::matchAll($tag_regex, $string, $matches);

        if ( ! empty($matches))
        {
            foreach ($matches as $match)
            {
                $tag_name = RL_String::toLowerCase($match[1]);

                if ( ! in_array($tag_name, $forbidden_tags_array, true))
                {
                    continue;
                }

                $tag    = Protect::getMessageCommentTag(JText::sprintf('SRC_TAG_REMOVED_FORBIDDEN', $tag_name));
                $string = str_replace($match[0], $tag, $string);
            }
        }

        // single tags
        $tag_regex = '<\s*([a-z\!][^>\s]*?)(?:\s+.*?)?>';
        RL_RegEx::matchAll($tag_regex, $string, $matches);

        if ( ! empty($matches))
        {
            foreach ($matches as $match)
            {
                $tag_name = RL_String::toLowerCase($match[1]);

                if ( ! in_array($tag_name, $forbidden_tags_array, true))
                {
                    continue;
                }

                $tag    = Protect::getMessageCommentTag(JText::sprintf('SRC_TAG_REMOVED_FORBIDDEN', $tag_name));
                $string = str_replace($match[0], $tag, $string);
            }
        }
    }

    private static function replaceTagsByType(
        ?string     &$string,
        string      $type,
        object      $security,
        object|null $article
    ): void
    {
        if (empty($string))
        {
            return;
        }

        $type_ext      = $type == 'all' ? '' : '_' . $type;
        $area_params   = $security->area_params;
        $enable        = (bool) ($area_params->{'enable' . $type_ext} ?? true);
        $security_pass = (bool) ($security->{'pass' . $type_ext} ?? false);

        switch ($type)
        {
            case 'php':
                self::replaceTagsPHP($string, $enable, $security_pass, $area_params, $article);
                break;
            case 'js':
                self::replaceTagsJS($string, $enable, $security_pass);
                break;
            case 'css':
                self::replaceTagsCSS($string, $enable, $security_pass);
                break;
            default:
                self::replaceTagsAll($string, $enable, $security_pass, $area_params);
                break;
        }
    }

    private static function replaceTagsCSS(
        string &$string,
        bool   $enabled = true,
        bool   $security_pass = true
    ): void
    {
        if (empty($string))
        {
            return;
        }

        if ( ! self::containsCss($string))
        {
            return;
        }

        if ( ! $enabled)
        {
            // replace source block content with HTML comment
            $string = Protect::getMessageCommentTag(
                JText::sprintf('SRC_CODE_REMOVED_NOT_ALLOWED', JText::_('RL_CSS'))
            );

            return;
        }

        if ( ! $security_pass)
        {
            // replace source block content with HTML comment
            $string = Protect::getMessageCommentTag(JText::sprintf('SRC_CODE_REMOVED_SECURITY', JText::_('RL_CSS')));

            return;
        }
    }

    private static function replaceTagsJS(
        string &$string,
        bool   $enabled = true,
        bool   $security_pass = true
    ): void
    {
        if (empty($string))
        {
            return;
        }

        if ( ! self::containsJavascript($string))
        {
            return;
        }

        if ( ! $enabled)
        {
            // replace source block content with HTML comment
            $string = Protect::getMessageCommentTag(
                JText::sprintf('SRC_CODE_REMOVED_NOT_ALLOWED', JText::_('RL_JAVASCRIPT'))
            );

            return;
        }

        if ( ! $security_pass)
        {
            // replace source block content with HTML comment
            $string = Protect::getMessageCommentTag(
                JText::sprintf('SRC_CODE_REMOVED_SECURITY', JText::_('RL_JAVASCRIPT'))
            );

            return;
        }
    }

    private static function replaceTagsPHP(
        string      &$string,
        bool        $enabled,
        bool        $security_pass,
        object      $area_params,
        object|null $article
    ): void
    {
        if (empty($string))
        {
            return;
        }

        if ( ! str_contains($string, '<?'))
        {
            return;
        }

        // Match ( read {} as <> ):
        // {?php ... ?}
        // {? ... ?}

        $string_array       = self::stringToSplitArray($string, '<\?(?:php)?([\s<].*?)\?>');
        $string_array_count = count($string_array);

        if ($string_array_count <= 1)
        {
            $string = implode('', $string_array);

            return;
        }

        if ( ! $enabled)
        {
            // replace source block content with HTML comment
            $string_array    = [];
            $string_array[0] = Protect::getMessageCommentTag(
                JText::sprintf('SRC_CODE_REMOVED_NOT_ALLOWED', JText::_('RL_PHP_CODE'))
            );

            $string = implode('', $string_array);

            return;
        }

        if ( ! $security_pass)
        {
            // replace source block content with HTML comment
            $string_array    = [];
            $string_array[0] = Protect::getMessageCommentTag(
                JText::sprintf('SRC_CODE_REMOVED_SECURITY', JText::_('RL_PHP_CODE'))
            );

            $string = implode('', $string_array);

            return;
        }

        // if source block content has more than 1 php block, combine them
        if ($string_array_count > 3)
        {
            for ($i = 2; $i < $string_array_count - 1; $i++)
            {
                if ($i % 2 === 0)
                {
                    $string_array[1] .= "<!-- SRC_SEMICOLON --> ?>" . $string_array[$i] . "<?php ";
                    unset($string_array[$i]);
                    continue;
                }

                $string_array[1] .= $string_array[$i];
                unset($string_array[$i]);
            }
        }

        $leading_whitespace_length = strspn($string_array[1], " \n\r\t\v\f");
        $leading_whitespace        = substr($string_array[1], 0, $leading_whitespace_length);
        $source_start_line         = substr_count($string_array[0] . $leading_whitespace, "\n") + 1;
        $semicolon                 = '<!-- SRC_SEMICOLON -->';
        $script                    = trim($string_array[1]) . $semicolon;
        $script                    = RL_RegEx::replace(
            '(;[^\S\r\n]*)?' . RL_RegEx::quote($semicolon),
            ';',
            $script
        );

        $forbidden_php_array = explode(',', $area_params->forbidden_php);
        $forbidden_php_array = RL_Array::clean($forbidden_php_array);

        $functions = [];

        if ( ! empty($forbidden_php_array))
        {
            $forbidden_php_functions = implode('|', $forbidden_php_array);
            $forbidden_php_regex     = '(?:->|::)\s*(?:' . $forbidden_php_functions . ')(?=\s*\()(*SKIP)(*F)'
                . '|[^a-z_](' . $forbidden_php_functions . ')(\s*\(|\s+[\'"])';

            $script_without_comments = self::removePhpComments($script);

            RL_RegEx::matchAll($forbidden_php_regex, ' ' . $script_without_comments, $functions);
        }

        if ( ! empty($functions))
        {
            $functions_array = [];

            foreach ($functions as $function)
            {
                $functions_array[] = $function[1] . '()';
            }

            $comment = JText::_('SRC_PHP_CODE_REMOVED_FORBIDDEN')
                . ': ( ' . implode(', ', $functions_array) . ' )';

            $string_array[1] = RL_Document::isHtml()
                ? Protect::getMessageCommentTag($comment)
                : '';

            $string = implode('', $string_array);

            return;
        }

        $output = self::getPhpOutput($script, $source_start_line, $article);

        $string_array[1] = $output;

        $string = implode('', $string_array);
    }

    private static function stringToSplitArray(
        string $string,
        string $search
    ): array
    {
        $params = Params::get();

        $string = RL_RegEx::replace($search, $params->splitter . '\1' . $params->splitter, $string);

        return explode($params->splitter, $string);
    }
}
