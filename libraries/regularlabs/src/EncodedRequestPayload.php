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
class EncodedRequestPayload
{
    public static function decode(string $post_name, string $legacy_prefix, int $legacy_count = 10): string
    {
        $encoded = \RegularLabs\Library\Input::getPostString($post_name);
        if ($encoded != '') {
            return \RegularLabs\Library\Base64::decode($encoded, '') ?? '';
        }
        return \RegularLabs\Library\EncodedPayload::decodeRequestChunks($legacy_prefix, $legacy_count);
    }
}
