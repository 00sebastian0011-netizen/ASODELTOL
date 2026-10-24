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
 * A floating button that opens the AI assistant of the page.
 *
 * The assistant is a block (block_openaiagent) that lives in the side panel, which is closed
 * most of the time. The button is fixed to the bottom of the window and presses the block's own
 * "open chat" button, so the chat is still the block's: nothing of it is copied here.
 *
 * A page has one button. When it holds the assistant of a course and the one of the site, the
 * button opens the one of the course and the block of the other is put away.
 *
 * @module     theme_rsmax/assistant
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    ASSISTANT: '.block_openaiagent .openaiagent-container[data-blockid]',
    BLOCK: '.block',
};

const BUTTON = 'rsmax-assistant-button';

const CLASSES = {
    READY: 'rsmax-assistant',
    OPEN: 'rsmax-assistant-open',
};

const ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" ' +
    'stroke-linejoin="round" aria-hidden="true"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/>' +
    '<path d="M8 9h8"/><path d="M8 13h5"/></svg>';

/**
 * Picks the assistant the button opens.
 *
 * @param {Number} site Id of the block of the assistant of the site.
 * @returns {HTMLElement|undefined}
 */
const choose = site => {
    const all = Array.from(document.querySelectorAll(SELECTORS.ASSISTANT));
    const own = all.filter(one => one.dataset.blockid !== String(site));
    if (own.length) {
        all.filter(one => !own.includes(one)).forEach(one => {
            const block = one.closest(SELECTORS.BLOCK);
            if (block) {
                block.hidden = true;
            }
        });
    }
    return own[0] || all[0];
};

/**
 * Starts the module.
 *
 * @param {Object} config
 * @param {Number} config.site Id of the block of the assistant of the site, 0 when there is none.
 * @param {String} config.label Text of the button.
 * @param {String} config.open What the button does, for those who cannot see its text.
 */
export const init = config => {
    if (document.getElementById(BUTTON)) {
        return;
    }
    const assistant = choose(config.site);
    if (!assistant) {
        return;
    }
    const id = assistant.dataset.blockid;
    const trigger = document.getElementById('openaiagent-trigger-' + id);
    const chat = document.getElementById('openaiagent-modal-' + id);
    if (!trigger || !chat) {
        return;
    }

    const button = document.createElement('button');
    button.type = 'button';
    button.id = BUTTON;
    button.className = BUTTON;
    button.title = config.open;
    button.setAttribute('aria-label', config.open);
    button.innerHTML = ICON;
    const label = document.createElement('span');
    label.className = 'rsmax-assistant-label';
    label.textContent = config.label;
    button.append(label);
    button.addEventListener('click', () => trigger.click());
    document.body.append(button);
    document.body.classList.add(CLASSES.READY);

    // The block opens and closes its chat; the button steps aside while it is open and takes
    // the focus back when it closes.
    new MutationObserver(() => {
        const open = chat.classList.contains('is-open');
        const was = document.body.classList.contains(CLASSES.OPEN);
        document.body.classList.toggle(CLASSES.OPEN, open);
        if (was && !open) {
            button.focus();
        }
    }).observe(chat, {attributes: true, attributeFilter: ['class']});
};
