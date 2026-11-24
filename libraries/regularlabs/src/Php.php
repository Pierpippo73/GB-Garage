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
use Joomla\CMS\Application\CMSApplicationInterface as JCMSApplicationInterface;
use Joomla\CMS\Document\Document as JDocument;
use Joomla\Filesystem\Folder as JFolder;
use Joomla\Filesystem\Path as JPath;
class Php
{
    static $rl_variables;
    static $source_line_offsets = [];
    public static function execute(string $rl_string, object|false|null $rl_article = null, object|false|null $rl_module = null, bool $has_own_return = \false): mixed
    {
        self::prepareString($rl_string);
        $function_name = self::getFunctionName($rl_string);
        if (!$function_name) {
            // Something went wrong!
            return \true;
        }
        if (!$rl_article && str_contains($rl_string, '$article')) {
            $request = \RegularLabs\Library\RequestContext::fromInput();
            if ($request->isOptionView('com_content', 'article')) {
                $rl_article = \RegularLabs\Library\Article::get($request->id);
            }
        }
        $rl_pre_variables = array_keys(get_defined_vars());
        $output_buffer_level = ob_get_level();
        ob_start();
        try {
            $rl_post_variables = $function_name(self::$rl_variables, $rl_article, $rl_module);
            $rl_output = ob_get_contents();
        } finally {
            while (ob_get_level() > $output_buffer_level) {
                ob_end_clean();
            }
        }
        if ($has_own_return) {
            return $rl_post_variables;
        }
        if (!is_array($rl_post_variables)) {
            return $rl_output;
        }
        $rl_diff_variables = array_diff(array_keys($rl_post_variables), $rl_pre_variables);
        foreach ($rl_diff_variables as $rl_diff_key) {
            if (in_array($rl_diff_key, ['Itemid', 'mainframe', 'app', 'document', 'doc', 'database', 'db', 'user', 'article', 'module'], \true) || substr($rl_diff_key, 0, 4) == 'rl_') {
                continue;
            }
            self::$rl_variables[$rl_diff_key] = $rl_post_variables[$rl_diff_key];
        }
        return $rl_output;
    }
    public static function getApplication(): JCMSApplicationInterface
    {
        return \RegularLabs\Library\Application::get();
    }
    public static function getDocument(): JDocument
    {
        return \RegularLabs\Library\Indexer::getDocument();
    }
    public static function getSourceLine(\Throwable $throwable): ?int
    {
        $file = JPath::clean($throwable->getFile());
        if (!isset(self::$source_line_offsets[$file])) {
            return null;
        }
        $line = $throwable->getLine() - self::$source_line_offsets[$file];
        return $line > 0 ? $line : null;
    }
    private static function createFunctionInMemory(string $string, int $source_line_offset): void
    {
        $temp_file = self::writeTemporaryFunctionFile($string);
        if (!$temp_file) {
            return;
        }
        self::$source_line_offsets[$temp_file] = $source_line_offset;
        try {
            include_once $temp_file;
        } finally {
            if (!\RegularLabs\Library\Config::get('debug')) {
                @unlink($temp_file);
            }
        }
    }
    private static function extractUseStatements(string &$string): string
    {
        $use_statements = [];
        $string = trim($string);
        \RegularLabs\Library\RegEx::matchAll('^use\s+[^\s;]+\s*;', $string, $matches, 'm');
        foreach ($matches as $match) {
            $use_statements[] = $match[0];
            $string = str_replace($match[0], '', $string);
        }
        $string = trim($string);
        return implode("\n", $use_statements);
    }
    private static function generateFileContents(string $function_name = 'rl_function', string $string = ''): array
    {
        $use_statements = self::extractUseStatements($string);
        $init_variables = self::getVarInits();
        $init_variables[] = 'if (is_array($rl_variables)) {' . 'foreach ($rl_variables as $rl_key => $rl_value) {' . '${$rl_key} = $rl_value;' . '}' . '}';
        $contents = ['<?php', 'defined(\'_JEXEC\') or die;', $use_statements, 'function ' . $function_name . '($rl_variables, $article, $module){', implode("\n", $init_variables), $string . ';', 'return get_defined_vars();', ';}'];
        $source_line_offset = substr_count(implode("\n", array_slice($contents, 0, 5)), "\n") + 1;
        $contents = implode("\n", $contents);
        return [self::removeZeroWidthCharactersFromExecutableCode($contents), $source_line_offset];
    }
    private static function getFunctionName(string $string): string|false
    {
        $function_name = 'regularlabs_php_' . md5($string);
        if (function_exists($function_name)) {
            return $function_name;
        }
        [$contents, $source_line_offset] = self::generateFileContents($function_name, $string);
        self::createFunctionInMemory($contents, $source_line_offset);
        if (!function_exists($function_name)) {
            // Something went wrong!
            return \false;
        }
        return $function_name;
    }
    private static function writeTemporaryFunctionFile(string $string): ?string
    {
        $tmp_path = \RegularLabs\Library\Config::get('tmp_path', JPATH_ROOT . '/tmp');
        $directory = JPath::clean($tmp_path . '/regularlabs/custom_php');
        if (!is_dir($directory) && !JFolder::create($directory)) {
            return null;
        }
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $file = JPath::clean($directory . '/' . getmypid() . '_' . bin2hex(random_bytes(16)));
            $handle = @fopen($file, 'x+b');
            if (!$handle) {
                continue;
            }
            $length = strlen($string);
            $written = 0;
            while ($written < $length) {
                $bytes = fwrite($handle, substr($string, $written));
                if ($bytes === \false || $bytes === 0) {
                    break;
                }
                $written += $bytes;
            }
            fclose($handle);
            if ($written === $length) {
                @chmod($file, 0600);
                return $file;
            }
            @unlink($file);
        }
        return null;
    }
    private static function getVarInits(): array
    {
        return ['$app = $mainframe = \RegularLabs\Library\Php::getApplication();', '$document = $doc = \RegularLabs\Library\Php::getDocument();', '$database = $db = \RegularLabs\Library\DB::get();', '$user = \RegularLabs\Library\User::get();', '$Itemid = \RegularLabs\Library\Input::getInt(\'Itemid\');'];
    }
    private static function prepareString(string &$string): void
    {
        $string = trim($string);
        $string = str_replace('?><?php', '', $string . '<?php ;');
        if (!str_starts_with($string, '<?php')) {
            $string = '?>' . $string;
            return;
        }
        $string = substr($string, 5);
        $string = trim($string);
    }
    private static function removeZeroWidthCharactersFromExecutableCode(string $contents): string
    {
        $zero_width_characters = ["​", "‌", "‍"];
        $output = '';
        foreach (token_get_all($contents) as $token) {
            if (!is_array($token)) {
                $output .= str_replace($zero_width_characters, '', $token);
                continue;
            }
            [$id, $text] = $token;
            if (in_array($id, [\T_CONSTANT_ENCAPSED_STRING, \T_ENCAPSED_AND_WHITESPACE, \T_INLINE_HTML], \true)) {
                $output .= $text;
                continue;
            }
            $output .= str_replace($zero_width_characters, '', $text);
        }
        return $output;
    }
}
