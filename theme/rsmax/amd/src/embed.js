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
 * Loads maps and videos from other sites only when the visitor presses their button.
 *
 * Until then the page holds a placeholder, so no request is made to the other site and the
 * visitor's address is not shared with it.
 *
 * @module     theme_rsmax/embed
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    EMBED: '[data-region="pluginia-embed"]',
    BUTTON: '[data-action="load-embed"]',
};

let listening = false;

/**
 * Replaces the placeholder of an embed with its frame.
 *
 * @param {HTMLElement} embed The embed element.
 */
const load = (embed) => {
    const frame = document.createElement('iframe');
    frame.src = embed.dataset.src;
    frame.title = embed.dataset.title;
    frame.loading = 'lazy';
    frame.referrerPolicy = 'strict-origin-when-cross-origin';
    frame.allow = 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; fullscreen';
    frame.allowFullscreen = true;
    embed.replaceChildren(frame);
    embed.classList.add('pluginia-embed-loaded');
    frame.focus();
};

/**
 * Starts listening for the buttons of the embeds. Safe to call once per embed on the page.
 */
export const init = () => {
    if (listening) {
        return;
    }
    listening = true;
    document.addEventListener('click', (event) => {
        const button = event.target.closest(SELECTORS.BUTTON);
        const embed = button?.closest(SELECTORS.EMBED);
        if (embed) {
            load(embed);
        }
    });
};
