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
use DOMDocument;
use Joomla\CMS\Plugin\PluginHelper as JPluginHelper;
use Joomla\Registry\Registry as JRegistry;
class Text
{
    protected static string $comment_page_splitter = 'PAGE_SPLITTER';
    protected static string $comment_pagination_placeholder = 'PAGENAVIGATION_%nr%';
    protected static string $comment_prefix = '';
    protected static string $comment_tag_splitter = 'TAG_SPLITTER';
    protected static array $navigations = [];
    public static function escape(string $string, string $type = '')
    {
        return match ($type) {
            'double' => str_replace('"', '\"', $string),
            'single' => str_replace("'", "\\'", $string),
            default => str_replace(['"', "'"], ['\"', "\\'"], $string),
        };
    }
    public static function nl2br(string $string)
    {
        $string = str_replace(["\r\n", "\r"], "\n", $string);
        $string = str_replace("\n", '<br>', $string);
        return $string;
    }
    public static function process($string, $key, $attributes)
    {
        if (!is_string($string)) {
            return $string;
        }
        $string = self::protectNavigations($string);
        if (isset($attributes->page)) {
            $string = self::getPage($string, $attributes);
        }
        if (isset($attributes->id) || isset($attributes->element)) {
            $id = $attributes->id ?? $attributes->element;
            $string = self::getElementById($string, $id);
        }
        if (!empty($attributes->paragraphs)) {
            $string = self::limitByParagraphs($string, $attributes->paragraphs, $attributes->add_ellipsis ?? \true);
            $attributes = clone $attributes;
            unset($attributes->paragraphs);
        }
        if (isset($attributes->html) && !$attributes->html) {
            $string = self::removeHtml($string);
        }
        if (isset($attributes->images) && !$attributes->images) {
            $string = self::removeImages($string);
        }
        if (isset($attributes->offset_headings)) {
            $string = self::offsetHeadings($string, $attributes->offset_headings);
        }
        $string = self::limit($string, $attributes);
        $string = self::unprotectNavigations($string);
        if (isset($attributes->replace)) {
            $string = self::replace($string, $attributes->replace, $attributes->replace_case_sensitive ?? \true, $attributes->replace_html ?? \false);
        }
        if (isset($attributes->convert_case)) {
            $string = \RegularLabs\Library\StringHelper::toCase($string, $attributes->convert_case);
        }
        if (isset($attributes->htmlentities) && $attributes->htmlentities) {
            $string = htmlentities($string);
        }
        if (isset($attributes->escape)) {
            $string = self::escape($string, $attributes->escape);
        }
        if (isset($attributes->nl2br) && $attributes->nl2br) {
            $string = self::nl2br($string);
        }
        return $string;
    }
    public static function triggerContentPlugins($string, $id = 0)
    {
        $item = (object) [];
        $item->id = $id;
        $item->text = $string;
        $item->slug = '';
        $item->catslug = '';
        $item->introtext = null;
        $item->fulltext = null;
        $article_params = new JRegistry();
        $article_params->loadArray(['inline' => \false]);
        JPluginHelper::importPlugin('content');
        \RegularLabs\Library\Event::trigger('onContentPrepare', ['com_content.article', &$item, &$article_params, 0]);
        return $item->text;
    }
    protected static function addEllipsis(&$string)
    {
        $string = \RegularLabs\Library\StringHelper::rtrim($string);
        $string = \RegularLabs\Library\RegEx::replaceAtEnd('(.)\.*((?:\s*</[a-z][^>]*>)*)', '\1...\2', $string);
    }
    protected static function containsHtml($string)
    {
        return str_contains($string, '<') && str_contains($string, '>');
    }
    protected static function extractPages($string)
    {
        // Flip order of title and class around to match latest syntax
        $string = \RegularLabs\Library\RegEx::replace('<hr title="([^"]*)" class="system-pagebreak" /?>', '<hr class="system-pagebreak" title="\1" />', $string);
        $regex = '<hr class="system-pagebreak" title="([^"]*)" /?>';
        \RegularLabs\Library\RegEx::matchAll($regex, $string, $page_titles, null, \PREG_PATTERN_ORDER);
        if (empty($page_titles)) {
            return [];
        }
        $splitter = self::getComment('page_splitter');
        $string = \RegularLabs\Library\RegEx::replace($regex, $splitter, $string);
        $contents = explode($splitter, $string);
        $pages = [];
        foreach ($contents as $i => $content) {
            $pages[] = (object) ['title' => $i === 0 ? '' : $page_titles[1][$i - 1] ?? '', 'contents' => $content];
        }
        return $pages;
    }
    protected static function getByParagraphsByRange(string $string, object $range): string
    {
        $paragraphs = self::getParagraphsFromString($string);
        if (empty($paragraphs)) {
            return '';
        }
        $selected = array_slice($paragraphs, $range->start - 1, $range->length);
        return implode('', $selected);
    }
    protected static function getCharacters($string)
    {
        $string = \RegularLabs\Library\StringHelper::decodeHtmlEntities($string);
        return preg_split('//u', $string, -1, \PREG_SPLIT_NO_EMPTY);
    }
    protected static function getComment(string $name): string
    {
        $comment = match ($name) {
            'page_splitter' => self::$comment_page_splitter,
            'pagination_placeholder' => self::$comment_pagination_placeholder,
            'tag_splitter' => self::$comment_tag_splitter,
        };
        return '<!-- ' . (self::$comment_prefix ? self::$comment_prefix . ': ' : '') . $comment . ' -->';
    }
    protected static function getElementById($string, $id)
    {
        if (!class_exists('DOMDocument')) {
            return '';
        }
        if (!str_contains($string, 'id="' . $id . '"')) {
            return '';
        }
        $doc = new DOMDocument();
        $doc->validateOnParse = \true;
        $string = '<html>' . '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>' . '<body><div>' . $string . '</div></body>' . '</html>';
        $doc->loadHTML($string);
        $node = $doc->getElementById($id);
        if (empty($node)) {
            return '';
        }
        return $doc->saveHTML($node);
    }
    protected static function getLengthCharacters($string)
    {
        $string = \RegularLabs\Library\StringHelper::decodeHtmlEntities($string);
        return \RegularLabs\Library\StringHelper::strlen($string);
    }
    protected static function getLengthLetters($string)
    {
        $letters = self::getLetters($string);
        return count($letters);
    }
    protected static function getLengthWords($string)
    {
        $string = \RegularLabs\Library\StringHelper::decodeHtmlEntities($string);
        return \RegularLabs\Library\StringHelper::countWords($string);
    }
    protected static function getLetters($string)
    {
        $characters = self::getCharacters($string);
        $letters = [];
        foreach ($characters as $character) {
            if (!is_numeric($character) && !self::isLetter($character)) {
                continue;
            }
            $letters[] = $character;
        }
        return $letters;
    }
    protected static function getNumberOfParagraphs($string): int
    {
        $paragraphs = self::getParagraphsFromString($string);
        return count($paragraphs);
    }
    protected static function getPage($string, $attributes)
    {
        if (empty($attributes->page)) {
            return $string;
        }
        $pages = self::extractPages($string);
        if (empty($pages)) {
            return $string;
        }
        if (is_numeric($attributes->page)) {
            return $pages[$attributes->page - 1]->contents ?? '';
        }
        foreach ($pages as $page) {
            if ($page->title === $attributes->page) {
                return $page->contents;
            }
        }
        return '';
    }
    protected static function getParagraphsFromString($string): array
    {
        if (!self::containsHtml($string)) {
            return [];
        }
        \RegularLabs\Library\RegEx::matchAll('<p\b[^>]*>.*?</p>', $string, $matches, 'is', \PREG_PATTERN_ORDER);
        return $matches[0] ?? [];
    }
    protected static function getPartsToKeep($parts, $last_text_part)
    {
        $parts_to_keep = [];
        $opening_tags = [];
        foreach ($parts as $i => $part) {
            // Include all parts up to the last text part we need to include
            if ($i <= $last_text_part) {
                $parts_to_keep[] = $part;
                continue;
            }
            // this is a text part. So ignore it.
            if (!($i % 2)) {
                continue;
            }
            \RegularLabs\Library\RegEx::startsWith('<(?<closing>\/?)(?<type>[a-z][a-z0-9]*)', $part, $tag);
            if (empty($tag['type'])) {
                continue;
            }
            // This is a self closing tag. So ignore it.
            if (\RegularLabs\Library\HtmlTag::isSelfClosingTag($tag['type'])) {
                continue;
            }
            // This is a closing tag of the previous opening tag. So ignore both
            if ($tag['closing'] && $tag['type'] === end($opening_tags)) {
                array_pop($opening_tags);
                array_pop($parts_to_keep);
                continue;
            }
            $parts_to_keep[] = $part;
            // This is a opening tag. So add it to the list to remember
            if (!$tag['closing']) {
                $opening_tags[] = $tag['type'];
            }
        }
        return $parts_to_keep;
    }
    protected static function getRangeFromString(string $string, int $max = 999999): object
    {
        $string = \RegularLabs\Library\StringHelper::decodeHtmlEntities($string);
        if (!str_contains($string, '-')) {
            $string = '1-' . $string;
        }
        [$start, $end] = explode('-', $string);
        $end = $end ?: $max;
        $end = max((int) $start, min((int) $end, $max));
        $length = $end - $start + 1;
        return (object) ['start' => (int) $start, 'end' => (int) $end, 'length' => max(0, $length)];
    }
    protected static function getWords(string $string, string $limit): array
    {
        $words = \RegularLabs\Library\StringHelper::countWords($string, 'numbered');
        $count = count($words);
        $range = self::getRangeFromString($limit, $count);
        $selected = array_slice($words, $range->start - 1, $range->length);
        if (empty($selected)) {
            return [];
        }
        $lastWord = array_pop($selected);
        $lastWord = \RegularLabs\Library\RegEx::replaceAtEnd('(\p{L}[\p{L}\p{N}\']*\.*).*', '$1', $lastWord);
        $selected[] = $lastWord;
        return $selected;
    }
    protected static function isLetter($character)
    {
        return \RegularLabs\Library\RegEx::matchesEntireString('[\p{Latin}]', $character);
    }
    protected static function limit($string, $attributes)
    {
        if (empty($attributes->characters) && empty($attributes->words) && empty($attributes->letters)) {
            return $string;
        }
        if (self::containsHtml($string)) {
            return self::limitHtml($string, $attributes);
        }
        $add_ellipsis = $attributes->add_ellipsis ?? \false;
        if (!empty($attributes->words)) {
            return self::limitByWords($string, (string) $attributes->words, $add_ellipsis);
        }
        if (!empty($attributes->letters)) {
            return self::limitByLetters($string, $attributes->letters, $add_ellipsis);
        }
        return self::limitByCharacters($string, $attributes->characters, $add_ellipsis);
    }
    protected static function limitByCharacters($string, $limit, $add_ellipsis)
    {
        $total = self::getLengthCharacters($string);
        $range = self::getRangeFromString($limit, $total);
        if ($range->length === 0 || $range->start > $total) {
            return '';
        }
        $characters = self::getCharacters($string);
        $selected = array_slice($characters, $range->start - 1, $range->length);
        $string = implode('', $selected);
        if ($add_ellipsis && $range->end < $total) {
            self::addEllipsis($string);
        }
        return $string;
    }
    protected static function limitByLetters($string, $limit, $add_ellipsis)
    {
        $total = self::getLengthLetters($string);
        $range = self::getRangeFromString($limit, $total);
        if ($range->length === 0 || $range->start > $total) {
            return '';
        }
        $characters = self::getCharacters($string);
        $letter_count = 0;
        $characters_to_keep = [];
        foreach ($characters as $character) {
            $is_letter = is_numeric($character) || self::isLetter($character);
            if ($is_letter) {
                $letter_count++;
            }
            if ($letter_count < $range->start) {
                continue;
            }
            $characters_to_keep[] = $character;
            if ($letter_count >= $range->end) {
                break;
            }
        }
        $string = implode('', $characters_to_keep);
        if ($add_ellipsis && $range->end < $total) {
            self::addEllipsis($string);
        }
        return $string;
    }
    protected static function limitByParagraphs($string, $limit, $add_ellipsis = \true)
    {
        if (!self::containsHtml($string)) {
            return $string;
        }
        $count = self::getNumberOfParagraphs($string);
        $range = self::getRangeFromString($limit, $count);
        $string = self::getByParagraphsByRange($string, $range);
        if ($add_ellipsis && $range->end < $count) {
            \RegularLabs\Library\RegEx::endsWith('(.*?)(</p>)', $string, $match);
            self::addEllipsis($match[1]);
            $string = $match[1] . $match[2];
        }
        return \RegularLabs\Library\Html::fix($string);
    }
    protected static function limitByWords(string $string, string $limit, bool $add_ellipsis = \true): string
    {
        $total = self::getLengthWords($string);
        $range = self::getRangeFromString($limit, $total);
        if ($range->start === 1 && $range->end >= $total) {
            return $string;
        }
        $string = \RegularLabs\Library\StringHelper::decodeHtmlEntities($string);
        $words = self::getWords($string, $limit);
        if (empty($words)) {
            return '';
        }
        $newString = implode(' ', $words);
        if ($newString === $string) {
            return $string;
        }
        if ($add_ellipsis && $range->end < $total) {
            self::addEllipsis($newString);
        }
        return $newString;
    }
    protected static function limitHtml($string, $attributes)
    {
        if (empty($attributes->characters) && empty($attributes->letters) && empty($attributes->words) && empty($attributes->paragraphs)) {
            return $string;
        }
        $add_ellipsis = $attributes->add_ellipsis ?? \false;
        if (!empty($attributes->paragraphs)) {
            return self::limitByParagraphs($string, $attributes->paragraphs, $add_ellipsis);
        }
        if (!empty($attributes->words)) {
            return self::limitHtmlByType('words', $string, $attributes->words, $add_ellipsis);
        }
        if (!empty($attributes->letters)) {
            return self::limitHtmlByType('letters', $string, $attributes->letters, $add_ellipsis);
        }
        return self::limitHtmlByType('characters', $string, $attributes->characters, $add_ellipsis);
    }
    protected static function limitHtmlByType($type, $string, $limit, $add_ellipsis = \true)
    {
        if (!in_array($type, ['words', 'letters', 'characters'], \true)) {
            return $string;
        }
        $limit_class = 'limitBy' . ucfirst($type);
        if (!self::containsHtml($string)) {
            return self::$limit_class($string, $limit, $add_ellipsis);
        }
        $parts = self::splitByHtmlTags($string);
        $original = $parts;
        $text = '';
        $text_parts = [];
        foreach ($parts as $i => $part) {
            if ($i % 2) {
                // Block boundaries separate words, but inline formatting must not split a word.
                if ($type === 'words' && \RegularLabs\Library\RegEx::startsWith('<\/?(?:address|article|aside|blockquote|br|dd|div|dl|dt|figcaption|figure|footer|h[1-6]|header|hr|li|main|nav|ol|p|pre|section|table|tbody|td|th|thead|tr|ul)\b', $part, $match, 'i')) {
                    $text .= ' ';
                }
                continue;
            }
            $decoded = \RegularLabs\Library\StringHelper::decodeHtmlEntities($part);
            $text_parts[$i] = [strlen($text), $decoded];
            $text .= $decoded;
        }
        if ($type === 'words') {
            $units = \RegularLabs\Library\StringHelper::countWords($text, 'numbered');
        } else {
            $pattern = $type === 'letters' ? '[0-9\p{Latin}]' : '.';
            \RegularLabs\Library\RegEx::matchAll($pattern, $text, $matches, 'us', \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);
            $units = [];
            foreach ($matches as $match) {
                $units[$match[0][1]] = $match[0][0];
            }
        }
        $total = count($units);
        $range = self::getRangeFromString($limit, $total);
        if ($range->start === 1 && $range->end >= $total) {
            return $string;
        }
        if ($range->length === 0 || $range->start > $total) {
            return '';
        }
        $offsets = array_keys($units);
        $start = $offsets[$range->start - 1];
        $last = $offsets[min($range->end, $total) - 1];
        $end = $last + strlen($units[$last]);
        $last_text_part = 0;
        foreach ($text_parts as $i => [$offset, $decoded]) {
            $from = max(0, $start - $offset);
            $to = min(strlen($decoded), $end - $offset);
            if ($to <= $from) {
                $parts[$i] = '';
                continue;
            }
            $parts[$i] = $from === 0 && $to === strlen($decoded) ? $original[$i] : \RegularLabs\Library\StringHelper::escape(substr($decoded, $from, $to - $from), \ENT_NOQUOTES);
            $last_text_part = $i;
        }
        if ($add_ellipsis && $range->end < $total) {
            self::addEllipsis($parts[$last_text_part]);
        }
        $parts = self::removeTrimmedHtmlElements($parts, $original, $last_text_part);
        return implode('', self::getPartsToKeep($parts, $last_text_part));
    }
    protected static function removeTrimmedHtmlElements(array $parts, array $original, int $last_text_part): array
    {
        $open = [];
        foreach ($original as $i => $part) {
            if ($i > $last_text_part) {
                break;
            }
            if (!($i % 2) || !\RegularLabs\Library\RegEx::startsWith('<(?<closing>\/?)(?<name>[a-z][a-z0-9:-]*)\b', $part, $tag, 'i')) {
                continue;
            }
            $name = strtolower($tag['name']);
            if (\RegularLabs\Library\HtmlTag::isSelfClosingTag($name)) {
                continue;
            }
            if (!$tag['closing']) {
                $open[] = [$name, $i];
                continue;
            }
            if (empty($open) || end($open)[0] !== $name) {
                continue;
            }
            [, $opening] = array_pop($open);
            $length = $i - $opening - 1;
            $before = implode('', array_slice($original, $opening + 1, $length));
            $after = implode('', array_slice($parts, $opening + 1, $length));
            // Remove wrappers emptied by this selection, not originally empty anchors or controls.
            if (trim(strip_tags($before)) !== '' && trim($after) === '') {
                $parts[$opening] = '';
                $parts[$i] = '';
            }
        }
        return $parts;
    }
    protected static function offsetHeadings($string, $offset = 0)
    {
        $offset = (int) $offset;
        if ($offset === 0) {
            return $string;
        }
        if (!str_contains($string, '<h') && !str_contains($string, '<H')) {
            return $string;
        }
        if (!\RegularLabs\Library\RegEx::matchAll('<h(?<nr>[1-6])(?<content>[\s>].*?)</h\1>', $string, $headings)) {
            return $string;
        }
        foreach ($headings as $heading) {
            $new_nr = min(max($heading['nr'] + $offset, 1), 6);
            $string = str_replace($heading[0], '<h' . $new_nr . $heading['content'] . '</h' . $new_nr . '>', $string);
        }
        return $string;
    }
    protected static function protectNavigations($string)
    {
        self::$navigations = [];
        $regex = '<div [^>]*>\s*<p class="counter.*?</p><nav role="navigation".*?</nav>\s*</div>';
        if (!\RegularLabs\Library\RegEx::matchAll($regex, $string, $matches)) {
            return $string;
        }
        foreach ($matches as $i => $match) {
            $string = str_replace($match[0], str_replace('%nr%', $i, self::getComment('pagination_placeholder')), $string);
        }
        return $string;
    }
    protected static function removeHtml($string)
    {
        return \RegularLabs\Library\StringHelper::removeHtml($string, \true);
    }
    protected static function removeImages($string)
    {
        return \RegularLabs\Library\RegEx::replace('(<p><img\s[^>]*></p>|<img\s.*?>)', '', $string);
    }
    protected static function replace($string, $replacement_string, $casesensitive = \true, $as_html = \false, $separator = '=>')
    {
        $replacements = \RegularLabs\Library\ArrayHelper::toArray($replacement_string, ',', \false, \false);
        foreach ($replacements as $replacement) {
            $replacement = str_replace(htmlentities($separator), $separator, $replacement);
            if (!str_contains($replacement, $separator)) {
                $string = str_replace($replacement, '', $string);
                continue;
            }
            [$search, $replace] = \RegularLabs\Library\ArrayHelper::toArray($replacement, '=>', \false, \false);
            if ($as_html) {
                $search = \RegularLabs\Library\StringHelper::decodeHtmlEntities($search);
                $replace = \RegularLabs\Library\StringHelper::decodeHtmlEntities($replace);
            }
            $string = $casesensitive ? str_replace($search, $replace, $string) : str_ireplace($search, $replace, $string);
        }
        return $string;
    }
    protected static function rtrim($string, $limit)
    {
        return \RegularLabs\Library\StringHelper::rtrim(\RegularLabs\Library\StringHelper::substr($string, 0, $limit));
    }
    protected static function splitByHtmlTags($string)
    {
        $splitter = self::getComment('tag_splitter');
        // add splitter strings around tags
        $string = \RegularLabs\Library\RegEx::replace('(<\/?[a-z][a-z0-9:-]*\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>|<!--.*?-->)', $splitter . '\1' . $splitter, $string, 'is');
        return explode($splitter, $string);
    }
    protected static function unprotectNavigations($string)
    {
        $comment = self::getComment('pagination_placeholder');
        foreach (self::$navigations as $i => $navigation) {
            $string = str_replace(str_replace('%nr%', $i, $comment), $navigation, $string);
        }
        return $string;
    }
}
