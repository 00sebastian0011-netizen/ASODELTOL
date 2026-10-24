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
 * Arrows and automatic advance for the carousels of the Pluginia blocks.
 *
 * The carousel itself is a scrolling row with CSS snap points, so it already works by touch,
 * mouse and keyboard without this module. This adds previous and next buttons, and an optional
 * automatic advance that stops while the pointer or the focus is on the carousel, while the tab
 * is hidden, when the user presses pause and for users who ask for reduced motion.
 *
 * @module     theme_rsmax/carousel
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    TRACK: '[data-region="track"]',
    PREVIOUS: '[data-action="previous"]',
    NEXT: '[data-action="next"]',
    TOGGLE: '[data-action="toggle-autoplay"]',
};

/** Pixels of tolerance when comparing scroll positions. */
const TOLERANCE = 2;

const initialised = new WeakSet();

/**
 * Sets up one carousel.
 *
 * @param {HTMLElement} root The carousel element.
 */
const setup = (root) => {
    const track = root.querySelector(SELECTORS.TRACK);
    if (!track) {
        return;
    }
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const behavior = reducedMotion ? 'auto' : 'smooth';
    // In right-to-left pages the scroll position runs the other way.
    const direction = getComputedStyle(track).direction === 'rtl' ? -1 : 1;

    const position = () => Math.abs(track.scrollLeft);
    const end = () => track.scrollWidth - track.clientWidth;

    /**
     * Moves one page forwards (1) or backwards (-1), wrapping around at both ends.
     *
     * @param {Number} step 1 or -1.
     */
    const move = (step) => {
        if (step > 0 && position() >= end() - TOLERANCE) {
            track.scrollTo({left: 0, behavior});
        } else if (step < 0 && position() <= TOLERANCE) {
            track.scrollTo({left: end() * direction, behavior});
        } else {
            track.scrollBy({left: step * direction * track.clientWidth, behavior});
        }
    };

    root.querySelector(SELECTORS.PREVIOUS)?.addEventListener('click', () => move(-1));
    root.querySelector(SELECTORS.NEXT)?.addEventListener('click', () => move(1));

    // A thin line under the row shows how far along it is, in place of the browser's scrollbar.
    const progress = document.createElement('div');
    progress.className = 'pluginia-carousel-progress';
    progress.setAttribute('aria-hidden', 'true');
    progress.append(document.createElement('span'));
    track.after(progress);
    const updateProgress = () => {
        const visible = track.scrollWidth ? track.clientWidth / track.scrollWidth : 1;
        root.style.setProperty('--pluginia-thumb', Math.min(visible, 1).toFixed(4));
        root.style.setProperty('--pluginia-progress', (end() > 0 ? position() / end() : 0).toFixed(4));
    };
    track.addEventListener('scroll', updateProgress, {passive: true});

    // Without overflow there is nothing to scroll: the controls are hidden through this class.
    const update = () => {
        root.classList.toggle('pluginia-carousel-static', end() <= TOLERANCE);
        updateProgress();
    };
    new ResizeObserver(update).observe(track);
    update();

    const delay = parseInt(root.dataset.autoplay, 10) || 0;
    const toggle = root.querySelector(SELECTORS.TOGGLE);
    if (!delay || reducedMotion) {
        toggle?.remove();
        return;
    }

    let stopped = false;
    let hovered = false;
    let focused = false;
    setInterval(() => {
        if (!stopped && !hovered && !focused && !document.hidden && end() > TOLERANCE) {
            move(1);
        }
    }, delay);
    root.addEventListener('mouseenter', () => {
        hovered = true;
    });
    root.addEventListener('mouseleave', () => {
        hovered = false;
    });
    root.addEventListener('focusin', () => {
        focused = true;
    });
    root.addEventListener('focusout', () => {
        focused = false;
    });
    toggle?.addEventListener('click', () => {
        stopped = !stopped;
        toggle.setAttribute('aria-pressed', stopped ? 'true' : 'false');
        root.classList.toggle('pluginia-carousel-stopped', stopped);
    });
};

/**
 * Sets up every carousel that matches the selector and has not been set up yet.
 *
 * @param {String} selector CSS selector of the carousel elements.
 */
export const init = (selector) => {
    document.querySelectorAll(selector).forEach((root) => {
        if (!initialised.has(root)) {
            initialised.add(root);
            setup(root);
        }
    });
};
