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
use Joomla\CMS\Uri\Uri as JUri;

$image = JUri::root(true) . '/media/regularlabs/images/regular-expressions-cheat-sheet-v2.png';

?>
<img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>"
     width="763"
     height="1037"
     class="img-fluid"
     alt="<?php echo htmlspecialchars(JText::_('RL_REGULAR_EXPRESSIONS'), ENT_QUOTES, 'UTF-8'); ?>">
