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

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text as JText;
use Joomla\CMS\Layout\LayoutHelper as JLayoutHelper;
use RegularLabs\Library\StringHelper;

extract($displayData);

/**
 * Layout variables
 * -----------------
 *
 * @var array  $displayData
 * @var string $format
 * @var int    $bytes
 * @var int    $word_count
 * @var int    $word_length
 * @var string $separator
 * @var string $preview
 * @var bool   $generate_on_empty
 * @var string $button_text
 * @var bool   $can_generate
 */
?>
<div class="input-group flex-nowrap"
     data-rl-generated-secret
     data-format="<?php echo StringHelper::escape($format); ?>"
     data-bytes="<?php echo $bytes; ?>"
     data-word-count="<?php echo $word_count; ?>"
     data-word-length="<?php echo $word_length; ?>"
     data-separator="<?php echo StringHelper::escape($separator); ?>"
     data-preview="<?php echo StringHelper::escape($preview); ?>"
     data-generate-on-empty="<?php echo $generate_on_empty ? 'true' : 'false'; ?>">
    <?php echo JLayoutHelper::render('joomla.form.field.text', $displayData); ?>
    <button type="button"
            class="btn btn-secondary flex-shrink-0 text-nowrap"
            data-rl-generate-secret
        <?php echo $can_generate ? '' : 'disabled'; ?>>
        <?php echo StringHelper::escape(JText::_($button_text)); ?>
    </button>
</div>
