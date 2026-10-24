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

namespace theme_rsmax\block;

/**
 * Base class of the landing blocks that are a list of items written by hand.
 *
 * A block only has to declare its item fields and, when it has them, its layouts and its range
 * of columns. The template gets the items, the layout (also as an "is<layout>" flag), the columns
 * and the carousel settings.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class items_block extends landing_block {
    /** Layouts the block offers; empty when it only has one. A layout called "carousel" turns the carousel on. */
    public const LAYOUTS = [];

    /** True when the block is always a carousel, whatever the layout. */
    public const ALWAYSCAROUSEL = false;

    /** True when the block can overlap the block above it, like a row of figures over a hero. */
    public const CANOVERLAP = false;

    /** Default, fewest and most items per row; null when the block has no columns. */
    public const COLUMNS = null;

    /**
     * Tells whether the instance is shown as a carousel.
     *
     * @return bool
     */
    protected function is_carousel(): bool {
        return static::ALWAYSCAROUSEL || (static::LAYOUTS && $this->option('layout', static::LAYOUTS) == 'carousel');
    }

    /**
     * Returns the data for the template.
     *
     * @return array|null
     */
    protected function export_data(): ?array {
        $items = $this->items();
        if (!$items && !$this->page->user_is_editing()) {
            return null;
        }
        $layout = static::LAYOUTS ? $this->option('layout', static::LAYOUTS) : '';
        $carousel = $this->is_carousel();
        $data = [
            'items' => $items,
            'empty' => empty($items),
            'layout' => $layout,
            'columns' => static::COLUMNS ? $this->columns(...static::COLUMNS) : 1,
            'iscarousel' => $carousel,
            'autoplay' => $carousel ? $this->autoplay() : 0,
            'overlap' => static::CANOVERLAP && $this->setting('overlap', 0),
        ];
        if ($layout !== '') {
            $data['is' . $layout] = true;
        }
        return $data;
    }
}
