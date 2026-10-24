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
 * What the theme adds to activity pages on top of what Moodle prints.
 *
 * Quiz, while answering: a bar with one mark per question of the page, filled as they are answered.
 * Quiz, when reviewing: the result as one coloured block per question, each a link to it.
 * Forum: the messages of the reader are marked, and discussions get a level by their replies.
 *
 * Everything is read from the page Moodle already sent; nothing is requested from the server.
 *
 * @module     theme_rsmax/activity
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const RESULTS = ['correct', 'partiallycorrect', 'incorrect'];

/**
 * Creates an element.
 *
 * @param {string} tag Tag name.
 * @param {string} className Classes.
 * @param {string} text Text content.
 * @returns {HTMLElement}
 */
const make = (tag, className, text = '') => {
    const element = document.createElement(tag);
    element.className = className;
    element.textContent = text;
    return element;
};

/**
 * Returns the number Moodle shows for a question, or its position when it has none.
 *
 * @param {HTMLElement} question The question.
 * @param {number} index Position on the page, from zero.
 * @returns {string}
 */
const numberOf = (question, index) => question.querySelector('.info .qno')?.textContent.trim() || String(index + 1);

/**
 * Tells whether the reader has answered a question.
 *
 * @param {HTMLElement} question The question.
 * @returns {boolean}
 */
const isAnswered = (question) => {
    const area = question.querySelector('.formulation');
    if (!area) {
        return false;
    }
    const chosen = area.querySelector('input[type="radio"]:checked:not([value="-1"]), input[type="checkbox"]:checked');
    if (chosen) {
        return true;
    }
    const written = [...area.querySelectorAll('input[type="text"], input[type="number"], textarea')]
        .some((field) => field.value.replace(/<[^>]*>/g, '').trim() !== '');
    if (written) {
        return true;
    }
    const selects = [...area.querySelectorAll('select')];
    return selects.length > 0 && selects.every((select) => select.value !== '' && select.value !== '0');
};

/**
 * Quiz attempt: a bar that fills as the questions of the page are answered.
 *
 * @param {HTMLElement[]} questions Questions of the page.
 */
const followAnswers = (questions) => {
    const bar = make('div', 'rsmax-answered');
    bar.setAttribute('aria-hidden', 'true');
    const count = make('span', 'rsmax-answered-count');
    const marks = make('span', 'rsmax-answered-marks');
    const items = questions.map((question, index) => {
        const mark = make('a', 'rsmax-answered-mark', numberOf(question, index));
        mark.href = '#' + question.id;
        mark.tabIndex = -1;
        marks.append(mark);
        return mark;
    });
    bar.append(count, marks);
    questions[0].before(bar);

    const update = () => {
        let answered = 0;
        questions.forEach((question, index) => {
            const done = isAnswered(question);
            items[index].classList.toggle('is-done', done);
            question.classList.toggle('rsmax-answered-que', done);
            answered += done ? 1 : 0;
        });
        count.textContent = answered + ' / ' + questions.length;
        bar.classList.toggle('is-complete', answered === questions.length);
    };
    ['change', 'input'].forEach((type) => document.addEventListener(type, (event) => {
        if (event.target.closest('.que')) {
            update();
        }
    }));
    update();
};

/**
 * Quiz review: the result as a row of blocks, one per question, coloured by how it went.
 *
 * @param {HTMLElement[]} questions Questions of the page.
 */
const showResult = (questions) => {
    const graded = questions.filter((question) => RESULTS.some((result) => question.classList.contains(result)));
    if (!graded.length) {
        return;
    }
    const panel = make('nav', 'rsmax-result');
    const figure = make('div', 'rsmax-result-figure');
    const right = questions.filter((question) => question.classList.contains('correct')).length;
    figure.append(make('strong', '', String(right)), make('span', '', '/ ' + questions.length));
    const blocks = make('ol', 'rsmax-result-blocks list-unstyled');
    const legend = make('ul', 'rsmax-result-legend list-unstyled');
    const seen = {};

    questions.forEach((question, index) => {
        const result = RESULTS.find((name) => question.classList.contains(name)) || 'other';
        const state = question.querySelector('.info .state')?.textContent.trim() || '';
        const item = make('li', 'rsmax-result-block is-' + result);
        const link = make('a', '', numberOf(question, index));
        link.href = '#' + question.id;
        link.title = state;
        link.append(make('span', 'visually-hidden', ' ' + state));
        item.append(link);
        blocks.append(item);
        if (!seen[result]) {
            seen[result] = {state, count: 0};
        }
        seen[result].count++;
    });
    [...RESULTS, 'other'].filter((result) => seen[result]).forEach((result) => {
        const entry = make('li', 'is-' + result);
        entry.append(make('strong', '', String(seen[result].count)), make('span', '', seen[result].state));
        legend.append(entry);
    });

    const body = make('div', 'rsmax-result-body');
    body.append(blocks, legend);
    panel.append(figure, body);
    questions[0].before(panel);
};

/**
 * Forum: marks the messages written by the reader.
 */
const markOwnPosts = () => {
    const userid = String(window.M?.cfg?.userId ?? '');
    if (!userid) {
        return;
    }
    const mark = (root) => root.querySelectorAll('.forum-post-container:not([data-rsmax-seen])').forEach((post) => {
        post.dataset.rsmaxSeen = '1';
        const author = post.querySelector(':scope > .forumpost header a[href*="/user/"]');
        if (author && new URL(author.href).searchParams.get('id') === userid) {
            post.classList.add('rsmax-own');
        }
    });
    mark(document);
    // Replies written in place arrive later.
    const thread = document.querySelector('[data-content="forum-discussion"]');
    if (thread) {
        new MutationObserver(() => mark(thread)).observe(thread, {childList: true, subtree: true});
    }
};

/**
 * Forum: gives every discussion of the list a level by its number of replies.
 */
const rateDiscussions = () => {
    document.querySelectorAll('table.discussion-list tr.discussion').forEach((row) => {
        const cell = row.querySelector('td.p-0.text-center.fit-content');
        const replies = parseInt(cell?.textContent ?? '', 10);
        if (!Number.isNaN(replies)) {
            const few = replies < 3 ? 'some' : 'many';
            row.dataset.rsmaxReplies = replies === 0 ? 'none' : few;
        }
    });
};

let started = false;

/**
 * Starts the additions that apply to the page being shown.
 */
export const init = () => {
    if (started) {
        return;
    }
    started = true;
    const body = document.body;
    const questions = [...document.querySelectorAll('#region-main .que')];
    if (questions.length && body.id === 'page-mod-quiz-attempt') {
        followAnswers(questions);
    } else if (questions.length && body.id === 'page-mod-quiz-review') {
        showResult(questions);
    }
    if (body.classList.contains('path-mod-forum')) {
        markOwnPosts();
        rateDiscussions();
    }
};
