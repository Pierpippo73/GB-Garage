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
final class RequestContext
{
    public function __construct(public readonly string $option, public readonly string $view, public readonly string $task, public readonly string $layout, public readonly string $format, public readonly int $id, public readonly int $itemId)
    {
    }
    public static function fromInput(): self
    {
        return new self(\RegularLabs\Library\Input::getCmd('option'), \RegularLabs\Library\Input::getCmd('view'), \RegularLabs\Library\Input::getCmd('task'), \RegularLabs\Library\Input::getString('layout'), \RegularLabs\Library\Input::getWord('format'), \RegularLabs\Library\Input::getInt('id'), \RegularLabs\Library\Input::getInt('Itemid'));
    }
    public function isFormat(string $format): bool
    {
        return $this->format === $format;
    }
    public function isLayout(string $layout): bool
    {
        return $this->layout === $layout;
    }
    public function isOption(string $option): bool
    {
        return $this->option === $option;
    }
    public function isOptionView(string $option, string $view): bool
    {
        return $this->isOption($option) && $this->isView($view);
    }
    public function isTask(string $task): bool
    {
        return $this->task === $task;
    }
    public function isView(string $view): bool
    {
        return $this->view === $view;
    }
}
