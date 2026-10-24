<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace theme_rsmax\local;

use core\url;

/**
 * Cleans the values administrators type in the theme and block settings before they reach a page.
 *
 * It lives outside the block classes on purpose: the page layout uses it on every page, and block
 * classes can only be loaded where Moodle has loaded the block library.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class clean {
    /**
     * Cleans a link. Paths starting with / are local to the site.
     *
     * @param string $value Link.
     * @return string Empty when there is no valid link.
     */
    public static function link(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (str_starts_with($value, '/') && !str_starts_with($value, '//')) {
            return (new url($value))->out(false);
        }
        return clean_param($value, PARAM_URL);
    }

    /**
     * Cleans a Font Awesome icon name such as "fa-rocket".
     *
     * @param string $value Icon name, with or without the "fa-" prefix.
     * @return string Empty when it is not a valid icon name.
     */
    public static function icon(string $value): string {
        $value = strtolower(trim($value));
        if ($value !== '' && !str_starts_with($value, 'fa-')) {
            $value = 'fa-' . $value;
        }
        return preg_match('/^fa-[a-z0-9]+(-[a-z0-9]+)*$/', $value) ? $value : '';
    }

    /**
     * Cleans a colour.
     *
     * @param string $value Colour.
     * @return string A hexadecimal colour such as #00a8d6, or empty when it is not one.
     */
    public static function colour(string $value): string {
        $value = strtolower(trim($value));
        return preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/', $value) ? $value : '';
    }
}
