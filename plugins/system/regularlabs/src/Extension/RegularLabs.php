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

namespace RegularLabs\Plugin\System\RegularLabs\Extension;

use Joomla\CMS\Event\Model\AfterSaveEvent;
use Joomla\CMS\Form\Form as JForm;
use Joomla\CMS\Language\Text as JText;
use Joomla\CMS\Session\Session as JSession;
use Joomla\Event\Event as JEvent;
use Joomla\Registry\Registry as JRegistry;
use RegularLabs\Library\Application as RL_Application;
use RegularLabs\Library\Color as RL_Color;
use RegularLabs\Library\Document as RL_Document;
use RegularLabs\Library\DownloadKey as RL_DownloadKey;
use RegularLabs\Library\FieldHelper as RL_FieldHelper;
use RegularLabs\Library\Form\Form as RL_Form;
use RegularLabs\Library\Form\FormField as RL_FormField;
use RegularLabs\Library\Input as RL_Input;
use RegularLabs\Library\Language as RL_Language;
use RegularLabs\Library\Message as RL_Message;
use RegularLabs\Library\Plugin\System as RL_SystemPlugin;
use RegularLabs\Library\RegEx as RL_RegEx;
use RegularLabs\Library\Uri as RL_Uri;
use RegularLabs\Library\User as RL_User;
use RegularLabs\Plugin\System\RegularLabs\AdminMenu;
use RegularLabs\Plugin\System\RegularLabs\QuickPage;

defined('_JEXEC') or die;

final class RegularLabs extends RL_SystemPlugin
{
    private const ACTION_LOG_EVENT_PRIORITY = PHP_INT_MAX;
    private const COLOR_TABLE_ACCESS = [
        'advancedmodules'  => [
            'component' => 'com_modules', 'id_column' => 'module_id', 'item_asset' => 'com_modules.module',
        ],
        'conditions'       => ['component' => 'com_conditions', 'id_column' => 'id'],
        'contenttemplater' => ['component' => 'com_contenttemplater', 'id_column' => 'id'],
        'rereplacer'       => ['component' => 'com_rereplacer', 'id_column' => 'id'],
        'snippets'         => ['component' => 'com_snippets', 'id_column' => 'id'],
    ];
    static $_extra_events         = [
        'onAjaxRegularlabs'    => 'onAjaxRegularlabs',
        'onExtensionAfterSave' => ['onExtensionAfterSave', self::ACTION_LOG_EVENT_PRIORITY],
    ];
    public $_enable_in_admin      = true;
    public $_enable_on_edit_pages = true;

    public function getAjaxData()
    {
        $format = RL_Input::getString('format', 'json');

        if (RL_Input::getBool('getDownloadKey'))
        {
            if ( ! $this->canUseDownloadKeyAjax())
            {
                return false;
            }

            RL_Application::getCurrent()->getSession()->close();

            return RL_DownloadKey::get();
        }

        if (RL_Input::getBool('checkDownloadKey'))
        {
            if ( ! $this->canUseDownloadKeyAjax())
            {
                return false;
            }

            RL_Application::getCurrent()->getSession()->close();

            return $this->checkDownloadKey();
        }

        if (RL_Input::getBool('saveDownloadKey'))
        {
            if ( ! $this->canUseDownloadKeyAjax())
            {
                return false;
            }

            RL_Application::getCurrent()->getSession()->close();

            return $this->saveDownloadKey();
        }

        if (RL_Input::getBool('saveColor'))
        {
            return $this->saveColor();
        }

        $attributes_data = RL_Uri::getCompressedAttributes();

        $attributes = new JRegistry($attributes_data);

        if ( ! $this->canUseFieldAjax($attributes))
        {
            return false;
        }

        if (
            ! RL_Form::applyAjaxFieldRuntimeIdentity(
                $attributes,
                RL_Input::getString('fieldname', (string) $attributes->get('name')),
                RL_Input::getString('fieldid', (string) $attributes->get('id'))
            )
        )
        {
            return false;
        }

        RL_Application::getCurrent()->getSession()->close();

        $field_class = $attributes->get('field_class');

        if (empty($field_class))
        {
            return false;
        }

        if ( ! class_exists($field_class))
        {
            return false;
        }

        if ( ! is_subclass_of($field_class, RL_FormField::class))
        {
            return false;
        }

        $type = $attributes->get('type', '');

        $method = 'getAjax' . ucfirst($format) . ucfirst($type);

        $field_class = new $field_class;

        if ( ! method_exists($field_class, $method))
        {
            return false;
        }

        return $field_class->$method($attributes);
    }

    /**
     * @return  void
     */
    public function onAfterRender(): void
    {
        if ( ! RL_Document::isAdmin(true) || ! RL_Document::isHtml())
        {
            return;
        }

        $html = RL_Document::getBody();

        if (is_null($html))
        {
            return;
        }

        $has_changes = false;

        if ($this->removeEmptyFormControlGroups($html))
        {
            $has_changes = true;
        }

        if ($this->removeFormColumnLayout($html))
        {
            $has_changes = true;
        }

        if (AdminMenu::combineHtml($html))
        {
            $has_changes = true;
        }

        if ( ! $has_changes)
        {
            return;
        }

        RL_Document::setBody($html);
    }

    /**
     * @return  void
     */
    public function onAfterRoute(): void
    {
        if (RL_Input::getInt('rl_qp', 0))
        {
            QuickPage::render();
        }
    }

    public function onAjaxRegularlabs(?JEvent $event = null)
    {
        $data = $this->getAjaxData();

        return $this->handleAjaxResult($data, $event);
    }

    /**
     * Normalizes the request data.
     *
     * @param string $context The context
     * @param object $data    The object
     * @param JForm  $form    The form
     *
     * @return  void
     */
    public function onContentNormaliseRequestData($context, $data, JForm $form)
    {
        if ( ! is_object($data) || empty($data->com_fields))
        {
            return;
        }

        foreach ($data->com_fields as $field_name => &$field_value)
        {
            RL_FieldHelper::correctFieldValue($field_name, $field_value);
        }
    }

    public function onExtensionAfterSave($event): void
    {
        if ($event instanceof AfterSaveEvent)
        {
            $context = $event->getContext();
            $table   = $event->getItem();
        }
        elseif ($event instanceof JEvent)
        {
            $context = $event->getArguments()[0] ?? '';
            $table   = $event->getArguments()[1] ?? null;
        }
        else
        {
            return;
        }

        if ($context !== 'com_plugins.plugin' || ! is_object($table))
        {
            return;
        }

        // Joomla stores the plugin name as an untranslated action-log title after the table has already been saved.
        $manifest = json_decode((string) ($table->manifest_cache ?? ''));

        if ( ! is_object($manifest) || ! str_starts_with((string) ($manifest->author ?? ''), 'Regular Labs'))
        {
            return;
        }

        $extension = 'plg_' . ($table->folder ?? '') . '_' . ($table->element ?? '');

        RL_Language::load($extension);

        $table->name = JText::_((string) ($table->name ?? ''));
    }

    /**
     * @param string $buffer
     */
    protected function loadStylesAndScripts(string &$buffer): void
    {
        self::addStylesheetToInstaller();
    }

    /**
     * @throws Exception
     */
    private function addStylesheetToInstaller(): void
    {
        if (RL_Input::getCmd('option') !== 'com_installer')
        {
            return;
        }

        if ( ! self::hasRegularLabsMessages())
        {
            return;
        }

        RL_Document::style('regularlabs.admin-form');
    }

    private function canSaveColor(): bool
    {
        if ( ! $this->hasAuthenticatedAdminAjaxRequest())
        {
            return false;
        }

        $table     = RL_Input::getCmd('table');
        $item_id   = RL_Input::getInt('item_id');
        $id_column = RL_Input::getCmd('id_column', 'id');
        $access    = self::COLOR_TABLE_ACCESS[$table] ?? [];

        if (empty($access) || $item_id < 1 || $id_column !== $access['id_column'])
        {
            return false;
        }

        if (RL_User::authorise('core.admin'))
        {
            return true;
        }

        if (isset($access['item_asset']))
        {
            return RL_User::authorise('core.edit', $access['item_asset'] . '.' . $item_id);
        }

        return RL_User::authorise('core.edit', $access['component']);
    }

    private function canUseDownloadKeyAjax(): bool
    {
        if ( ! $this->hasAuthenticatedAdminAjaxRequest())
        {
            return false;
        }

        return RL_DownloadKey::canManageExtension(
            RL_Input::getCmd('extension', 'all')
        );
    }

    private function canUseFieldAjax(JRegistry $attributes): bool
    {
        if ( ! $this->hasAuthenticatedFieldAjaxRequest())
        {
            return false;
        }

        if ( ! RL_Form::hasValidAjaxDataSignature($attributes))
        {
            return false;
        }

        $parent_request = (array) $attributes->get('parent_request', []);
        $option         = $parent_request['option'] ?? '';

        if ($option == '')
        {
            return false;
        }

        if (RL_Document::isSite())
        {
            return true;
        }

        return RL_User::authorise('core.admin')
            || RL_User::authorise('core.manage', $option);
    }

    private function checkDownloadKey(): string
    {

        $key       = RL_Input::getPostString('key');
        $extension = RL_Input::getString('extension', 'all');

        return RL_DownloadKey::isValid($key, $extension);
    }

    private function hasAuthenticatedAdminAjaxRequest(): bool
    {
        if ( ! RL_Document::isAdministrator())
        {
            return false;
        }

        if (RL_User::isGuest())
        {
            return false;
        }

        return JSession::checkToken('request');
    }

    private function hasAuthenticatedFieldAjaxRequest(): bool
    {
        if ( ! RL_Document::isAdministrator() && ! RL_Document::isSite())
        {
            return false;
        }

        if (RL_User::isGuest())
        {
            return false;
        }

        return JSession::checkToken('request');
    }

    /**
     * @throws Exception
     */
    private function hasRegularLabsMessages(): bool
    {
        return RL_Message::containsText('class="rl-');
    }

    private function removeEmptyFormControlGroups(string &$html): bool
    {
        if (
            ! str_contains($html, '<div class="control-label">')
            && ! str_contains($html, '<div class="controls">')
            && ! str_contains($html, '<div class="control-group">')
        )
        {
            return false;
        }

        $html = RL_RegEx::replace(
            '<div class="(control-label|controls)">\s*</div>',
            '',
            $html
        );

        $html = RL_RegEx::replace(
            '<div class="control-group">\s*</div>',
            '',
            $html
        );

        return true;
    }

    private function removeFormColumnLayout(string &$html): bool
    {
        if (RL_Document::isSite())
        {
            return false;
        }

        if (
            $this->app->input->get('option', '') != 'com_plugins'
            || $this->app->input->get('view', '') != 'plugin'
            || $this->app->input->get('layout', '') != 'edit'
        )
        {
            return false;
        }

        if ( ! str_contains($html, 'column-count-md-2 column-count-lg-3'))
        {
            return false;
        }

        $html = str_replace('column-count-md-2 column-count-lg-3', '', $html);

        return true;
    }

    private function saveColor(): bool
    {
        if ( ! $this->canSaveColor())
        {
            return false;
        }

        $table     = RL_Input::getCmd('table');
        $item_id   = RL_Input::getInt('item_id');
        $color     = RL_Input::getString('color');
        $id_column = self::COLOR_TABLE_ACCESS[$table]['id_column'];

        return RL_Color::save($table, $item_id, $color, $id_column);
    }

    /**
     * @throws Exception
     */
    private function saveDownloadKey(): bool
    {
        $key = RL_Input::getPostString('key');

        return RL_DownloadKey::store($key);
    }
}
