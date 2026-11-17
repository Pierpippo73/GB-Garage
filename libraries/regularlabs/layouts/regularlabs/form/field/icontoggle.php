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
use RegularLabs\Library\Document;

extract($displayData);

/**
 * Layout variables
 * -----------------
 *
 * @var   string $id     DOM id of the field.
 * @var   string $icon1  Icon to show when the field is off.
 * @var   string $icon2  Icon to show when the field is on.
 * @var   string $text1  Text to show when the field is off.
 * @var   string $text2  Text to show when the field is on.
 * @var   string $class1 Class to add to the field when it is off.
 * @var   string $class2 Class to add to the field when it is on.
 * @var   string $name   Name of the input field.
 */

$text1 = JText::_($text1);
$text2 = JText::_($text2);

Document::useScript('showon');
?>

<fieldset>
    <input type="hidden" name="<?php echo $name; ?>" id="<?php echo $id; ?>" value="0">
    <div data-showon='[{"field":"<?php echo $name; ?>","values":["0"],"sign":"=","op":""}]' class="hidden">
        <span class="<?php echo $class1; ?>"
              role="button"
              onclick="el=this.closest('fieldset').querySelector('input[type=hidden]');el.value = 1;el.dispatchEvent(new Event('change'));">
            <span class="icon-<?php echo $icon1; ?>" aria-label="<?php echo $text1 ?: JText::_('JSHOW'); ?>"></span>
            <?php echo $text1; ?>
        </span>
    </div>

    <div data-showon='[{"field":"<?php echo $name; ?>","values":["1"],"sign":"=","op":""}]' class="hidden">
        <span class="<?php echo $class2; ?>"
              role="button"
              onclick="el=this.closest('fieldset').querySelector('input[type=hidden]');el.value = 0;el.dispatchEvent(new Event('change'));">
            <span class="icon-<?php echo $icon2; ?>" aria-label="<?php echo $text2 ?: JText::_('JHIDE'); ?>"></span>
            <?php echo $text2; ?>
        </span>
    </div>
</fieldset>
