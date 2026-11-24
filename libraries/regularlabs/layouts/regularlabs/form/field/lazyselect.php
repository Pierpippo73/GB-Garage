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

defined('_JEXEC') or die;

use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Language\Text;
use RegularLabs\Library\StringHelper;

extract($displayData);

$renderer = new FileLayout('joomla.form.field.list-fancy-select');
$clear_label = Text::_('JCLEAR');
$search_prompt = Text::_($list_mode === 'search' ? 'JSEARCH_FILTER' : 'RL_LIST_SEARCH_OR_SELECT');
$select = $renderer->render([
    'multiple'      => false,
    'autofocus'     => false,
    'class'         => '',
    'onchange'      => '',
    'dataAttribute' => '',
    'readonly'      => false,
    'disabled'      => false,
    'required'      => false,
    ...$attributes,
    'hint'          => $search_prompt,
    'id'            => $data['id'],
    'name'          => $data['name'],
    'value'         => $values,
    'options'       => $options,
]);
?>
<div class="rl-lazy-select<?= $values ? ' rl-list-has-selection' : '' ?>" data-rl-ajax="<?= StringHelper::escape(json_encode($data)) ?>" data-rl-search-mode="<?= StringHelper::escape($mode) ?>" data-rl-list-mode="<?= StringHelper::escape($list_mode) ?>">
    <div class="rl-list-control">
        <?= $select ?>
        <?php if ( ! empty($attributes['multiple'])) : ?>
            <span class="rl-list-placeholder" aria-hidden="true"<?= $values ? ' hidden' : '' ?>><?= StringHelper::escape($search_prompt) ?></span>
        <?php endif; ?>
        <?php if (empty($attributes['multiple'])) : ?>
            <button type="button" class="rl-clear-selection" aria-label="<?= StringHelper::escape($clear_label) ?>" hidden>
                <span aria-hidden="true">&times;</span>
            </button>
        <?php endif; ?>
    </div>
    <div class="rl-list-status small" role="status" aria-live="polite"></div>
    <button type="button" class="rl-list-load-more btn btn-link" hidden><?= Text::_('RL_LIST_LOAD_MORE') ?></button>
</div>
