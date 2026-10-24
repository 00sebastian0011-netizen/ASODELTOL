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
 * Keeps the side panels above the site footer.
 *
 * The panels are fixed to the window and the footer is as wide as the page, so at the end of a
 * page the footer used to slide over them. Here a panel ends where the footer begins: it gets
 * shorter as the footer comes into view, and its content scrolls inside it.
 *
 * @module     theme_rsmax/drawers
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    DRAWERS: '.drawer.drawer-left, .drawer.drawer-right',
    FOOTER: '.rsmax-footer',
};

/** Space left between a panel and the footer, in pixels. */
const GAP = 12;

/** Below this width Moodle shows the panels over the page, full height. */
const DOCKED = 992;

/**
 * Sets the height each panel has room for.
 */
const fit = () => {
    const footer = document.querySelector(SELECTORS.FOOTER);
    const docked = window.innerWidth >= DOCKED;
    document.querySelectorAll(SELECTORS.DRAWERS).forEach(drawer => {
        if (!footer || !docked) {
            drawer.style.maxHeight = '';
            return;
        }
        const room = footer.getBoundingClientRect().top - drawer.getBoundingClientRect().top - GAP;
        drawer.style.maxHeight = room < window.innerHeight ? Math.max(0, Math.round(room)) + 'px' : '';
    });
};

/**
 * Starts the module.
 */
export const init = () => {
    let pending = false;
    const schedule = () => {
        if (pending) {
            return;
        }
        pending = true;
        window.requestAnimationFrame(() => {
            pending = false;
            fit();
        });
    };
    window.addEventListener('scroll', schedule, {passive: true});
    window.addEventListener('resize', schedule);
    // The page grows and shrinks as sections fold and content loads.
    if (window.ResizeObserver) {
        new ResizeObserver(schedule).observe(document.body);
    }
    fit();
};
