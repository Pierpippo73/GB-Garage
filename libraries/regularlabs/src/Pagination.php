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
use Joomla\CMS\Pagination\Pagination as JPagination;
class Pagination
{
    public static function canRender(object $pagination): bool
    {
        return $pagination instanceof JPagination;
    }
    public static function create(int $total, int $limitstart, int $limit, string $prefix = ''): object
    {
        if (!\RegularLabs\Library\Indexer::isActive()) {
            return new JPagination($total, $limitstart, $limit, $prefix);
        }
        $pages_total = $limit > 0 ? (int) ceil($total / $limit) : 0;
        $pages_current = $limit > 0 ? (int) floor($limitstart / $limit) + 1 : 1;
        return (object) ['limit' => $limit, 'limitstart' => $limitstart, 'total' => $total, 'prefix' => $prefix, 'pagesStart' => $pages_total > 0 ? 1 : 0, 'pagesStop' => $pages_total, 'pagesCurrent' => min($pages_current, max(1, $pages_total)), 'pagesTotal' => $pages_total];
    }
}
