<?php
/**
 * @package         Sourcerer
 * @version         18.0.0
 * 
 * @author          Peter van Westen <info@regularlabs.com>
 * @link            https://regularlabs.com
 * @copyright       Copyright © 2026 Regular Labs All Rights Reserved
 * @license         GNU General Public License version 2 or later
 */

namespace RegularLabs\Plugin\EditorButton\Sourcerer;

defined('_JEXEC') or die;

use RegularLabs\Library\Document as RL_Document;
use RegularLabs\Library\Input as RL_Input;
use RegularLabs\Library\Plugin\EditorButtonPopup as RL_EditorButtonPopup;
use RegularLabs\Library\RegEx as RL_RegEx;

class Popup extends RL_EditorButtonPopup
{
    protected $extension = 'sourcerer';

    protected function loadScripts(): void
    {
        $editor_name = RL_Input::getString('editor', 'text');
        // Remove any dangerous character to prevent cross site scripting
        $editor_name = RL_RegEx::replace('[\'\";\s]', '', $editor_name);

        RL_Document::script('sourcerer.popup');

        $script = "
            const initializePopup = function() {
                RegularLabs.SourcererPopup.init('" . $editor_name . "');
            };
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initializePopup, {once: true});
            } else {
                initializePopup();
            }
        ";
        RL_Document::scriptDeclaration($script, 'Sourcerer Button', true, 'after');
    }
}
