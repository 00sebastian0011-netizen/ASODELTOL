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
 * Draws what the learner has done in each section next to its name, wherever the section shows:
 * in the course index and in the content of the course, whatever the course format.
 *
 * Course formats render their sections with their own templates and the course index is drawn
 * in the browser, so the theme cannot put this in a template of its own. The page carries the
 * figures and this module adds them to every section it finds.
 *
 * @module     theme_rsmax/course
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import CourseEvents from 'core_course/events';

const SELECTORS = {
    DATA: '#rsmax-coursedata',
    INDEX: '#theme_boost-drawers-courseindex',
    INDEXSECTION: '.courseindex-section[data-id]',
    INDEXTITLE: '.courseindex-section-title',
    CONTENTSECTION: '#region-main [data-for="section"][data-id]',
    CONTENTHEADER: '.course-section-header',
    METER: '.rsmax-meter',
};

let data = null;

/**
 * Builds the mark of a section: a ring that fills and the count beside it.
 *
 * @param {Number} completed Activities done.
 * @param {Number} total Activities with completion.
 * @return {HTMLElement}
 */
const buildMeter = (completed, total) => {
    const meter = document.createElement('span');
    meter.className = 'rsmax-meter';
    meter.setAttribute('role', 'img');
    const ring = document.createElement('span');
    ring.className = 'rsmax-meter-ring';
    const count = document.createElement('span');
    count.className = 'rsmax-meter-count';
    count.setAttribute('aria-hidden', 'true');
    meter.append(ring, count);
    updateMeter(meter, completed, total);
    return meter;
};

/**
 * Sets the figures of a mark.
 *
 * @param {HTMLElement} meter
 * @param {Number} completed
 * @param {Number} total
 */
const updateMeter = (meter, completed, total) => {
    meter.style.setProperty('--rsmax-meter', Math.round(completed * 100 / total));
    meter.classList.toggle('is-complete', completed >= total);
    meter.classList.toggle('is-started', completed > 0);
    meter.setAttribute('aria-label', data.label.replace('{completed}', completed).replace('{total}', total));
    // Only touched when it changes: the page is being watched for changes.
    const count = meter.querySelector('.rsmax-meter-count');
    if (count.textContent !== completed + '/' + total) {
        count.textContent = completed + '/' + total;
    }
};

/**
 * Adds or refreshes the mark of one section.
 *
 * @param {HTMLElement} section The element of the section.
 * @param {HTMLElement|null} holder Where the mark goes.
 */
const mark = (section, holder) => {
    const figures = data.sections[section.dataset.id];
    if (!holder || !figures) {
        return;
    }
    const existing = holder.querySelector(':scope > ' + SELECTORS.METER);
    if (existing) {
        updateMeter(existing, figures[0], figures[1]);
    } else {
        holder.append(buildMeter(figures[0], figures[1]));
    }
};

/**
 * Goes through the sections on the page.
 */
const decorate = () => {
    document.querySelectorAll(SELECTORS.INDEXSECTION).forEach(section => {
        mark(section, section.querySelector(':scope > ' + SELECTORS.INDEXTITLE));
    });
    document.querySelectorAll(SELECTORS.CONTENTSECTION).forEach(section => {
        const header = section.querySelector(SELECTORS.CONTENTHEADER);
        mark(section, header);
        const number = parseInt(section.dataset.number, 10);
        // A subsection is numbered by Moodle after every section of the course: that is not its place.
        const issubsection = section.classList.contains('delegated-section');
        if (header && number > 0 && !issubsection && !header.dataset.rsmaxNumber) {
            header.dataset.rsmaxNumber = String(number).padStart(2, '0');
        }
        const banner = data.banners[section.dataset.id];
        if (banner && header && !section.classList.contains('rsmax-hasbanner')) {
            section.classList.add('rsmax-hasbanner');
            header.style.setProperty('--rsmax-banner', 'url("' + banner.replace(/"/g, '%22') + '")');
        }
    });
    if (data.next) {
        const next = document.querySelector('#region-main #module-' + data.next);
        const card = next?.querySelector('.activity-item');
        if (card && !card.dataset.rsmaxNext) {
            next.classList.add('rsmax-next');
            card.dataset.rsmaxNext = data.nextlabel;
        }
    }
};

/**
 * Keeps the figures right when the learner ticks an activity off, or unticks it.
 *
 * @param {CustomEvent} event
 */
const completionToggled = event => {
    const {cmid, completed} = event.detail;
    const item = document.querySelector('#region-main #module-' + cmid)
        ?? document.querySelector('#course-index-cm-' + cmid);
    const section = item?.closest('[data-for="section"][data-id]');
    const figures = section ? data.sections[section.dataset.id] : null;
    if (!figures) {
        return;
    }
    figures[0] = Math.max(0, Math.min(figures[1], figures[0] + (completed ? 1 : -1)));
    decorate();
};

/**
 * Starts the module on a page of a course.
 */
export const init = () => {
    const node = document.querySelector(SELECTORS.DATA);
    if (!node) {
        return;
    }
    try {
        data = JSON.parse(node.textContent);
    } catch (error) {
        return;
    }
    data.sections = data.sections || {};
    data.banners = data.banners || {};
    decorate();

    // The index is drawn after the page loads and again whenever the course changes.
    let pending = false;
    const observer = new MutationObserver(() => {
        if (pending) {
            return;
        }
        pending = true;
        window.requestAnimationFrame(() => {
            pending = false;
            decorate();
        });
    });
    [document.querySelector(SELECTORS.INDEX), document.querySelector('#region-main')].forEach(target => {
        if (target) {
            observer.observe(target, {childList: true, subtree: true});
        }
    });
    document.addEventListener(CourseEvents.manualCompletionToggled, completionToggled);
};
