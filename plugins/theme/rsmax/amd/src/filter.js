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

/**
 * Filter buttons for lists of the Pluginia blocks, such as courses by category.
 *
 * Inside an element with data-region="pluginia-filter", buttons with data-filter="<value>" show
 * the items whose data-filter-value matches; the button with an empty value shows them all.
 *
 * @module     theme_rsmax/filter
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    ROOT: '[data-region="pluginia-filter"]',
    BUTTON: '[data-filter]',
    ITEM: '[data-filter-value]',
};

let listening = false;

/**
 * Starts listening for the filter buttons. Safe to call once per block on the page.
 */
export const init = () => {
    if (listening) {
        return;
    }
    listening = true;
    document.addEventListener('click', (event) => {
        const button = event.target.closest(SELECTORS.BUTTON);
        const root = button?.closest(SELECTORS.ROOT);
        if (!root) {
            return;
        }
        const value = button.dataset.filter;
        root.querySelectorAll(SELECTORS.BUTTON).forEach((other) => {
            other.setAttribute('aria-pressed', other === button ? 'true' : 'false');
            other.classList.toggle('active', other === button);
        });
        root.querySelectorAll(SELECTORS.ITEM).forEach((item) => {
            item.hidden = value !== '' && item.dataset.filterValue !== value;
        });
    });
};
