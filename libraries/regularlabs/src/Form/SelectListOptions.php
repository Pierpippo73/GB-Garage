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
namespace RegularLabs\Library\Form;

defined('_JEXEC') or die;
use Joomla\Database\QueryInterface;
use WeakMap;
class SelectListOptions
{
    private static ?WeakMap $counts = null;
    public static function count(\RegularLabs\Library\Form\FormField $field, array $attributes, int $limit): int
    {
        if ($limit < 1) {
            return 0;
        }
        self::$counts ??= new WeakMap();
        self::$counts[$field] = 0;
        try {
            $options = $field->getOptionsForLookup($attributes, '', null, $limit);
            return min($limit, self::$counts[$field] + count($options));
        } finally {
            unset(self::$counts[$field]);
        }
    }
    public static function load(\RegularLabs\Library\Form\FormField $field, QueryInterface $query, int $limit = 0): array
    {
        $db = $field->db;
        if (self::$counts !== null && isset(self::$counts[$field])) {
            $bounded_query = clone $query;
            $bounded_query->clear('order')->setLimit($limit);
            $count_query = $db->getQuery(\true)->select('COUNT(*)')->from('(' . $bounded_query . ') AS rl_lookup_count');
            $db->setQuery($count_query);
            self::$counts[$field] += (int) $db->loadResult();
            return [];
        }
        $db->setQuery($query, 0, $limit);
        return $db->loadObjectList();
    }
}
