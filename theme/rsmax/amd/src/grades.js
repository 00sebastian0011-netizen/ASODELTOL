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
 * Puts a summary a person can read at a glance above the grades of a learner.
 *
 * Moodle's report is a table of numbers. The summary is drawn from that same table, in the
 * browser: the grade of the course as a ring and how much has been graded. In the table, the
 * percentage of each activity becomes a bar. Nothing is fetched, so it can only ever show what
 * the report already shows:
 * hidden grades, the columns the site turned off and the way it rounds stay as they are.
 *
 * @module     theme_rsmax/grades
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    TABLE: 'table.user-grade',
    NAME: 'th.column-itemname',
    GRADE: 'td.column-grade',
    RANGE: 'td.column-range',
    PERCENTAGE: 'td.column-percentage',
    FEEDBACK: 'td.column-feedback',
};

/**
 * Reads a number written the way the site writes them: "82,00 %", "1.234,5" or "82.00".
 *
 * @param {String} text
 * @return {Number|null} Null when there is no number.
 */
const number = text => {
    const clean = (text || '').replace(/[^\d.,-]/g, '');
    if (!/\d/.test(clean)) {
        return null;
    }
    // The last comma or point is the decimal mark; any before it only group thousands.
    const mark = Math.max(clean.lastIndexOf(','), clean.lastIndexOf('.'));
    const whole = mark < 0 ? clean : clean.slice(0, mark).replace(/[.,]/g, '') + '.' + clean.slice(mark + 1);
    const value = parseFloat(whole);
    return Number.isFinite(value) ? value : null;
};

/**
 * Reads one row of the report.
 *
 * @param {HTMLElement} row
 * @return {Object|null} Null for rows that are not a grade.
 */
const readRow = row => {
    const name = row.querySelector(SELECTORS.NAME);
    const gradecell = row.querySelector(SELECTORS.GRADE);
    if (!name || !gradecell) {
        return null;
    }
    const link = name.querySelector('.gradeitemheader');
    if (!link) {
        return null;
    }
    // The grade is the first text of its cell; a menu of actions may follow it.
    const gradetext = (gradecell.querySelector('.d-flex > div:first-child') || gradecell).textContent.trim();
    const grade = number(gradetext);
    let percentage = number(row.querySelector(SELECTORS.PERCENTAGE)?.textContent);
    const range = (row.querySelector(SELECTORS.RANGE)?.textContent || '').split(/[–—]|\s-\s/).map(number);
    if (percentage === null && grade !== null && range.length == 2 && range[0] !== null && range[1] > range[0]) {
        percentage = (grade - range[0]) * 100 / (range[1] - range[0]);
    }
    return {
        row,
        name: link.textContent.trim(),
        url: link.getAttribute('href'),
        kind: name.querySelector('.small')?.textContent.trim() || '',
        istotal: !!name.querySelector('.courseitem'),
        iscategory: !!name.querySelector('.categoryitem'),
        gradetext: grade === null ? '' : gradetext,
        max: range.length == 2 && range[1] !== null ? range[1] : null,
        percentage: percentage === null ? null : Math.max(0, Math.min(100, percentage)),
        passed: gradecell.classList.contains('gradepass'),
        failed: gradecell.classList.contains('gradefail'),
        feedback: (row.querySelector(SELECTORS.FEEDBACK)?.textContent || '').trim(),
    };
};

/**
 * Creates an element.
 *
 * @param {String} tag
 * @param {String} classes
 * @param {String} text
 * @return {HTMLElement}
 */
const el = (tag, classes, text = '') => {
    const node = document.createElement(tag);
    node.className = classes;
    if (text !== '') {
        node.textContent = text;
    }
    return node;
};

/**
 * Formats a percentage without decimals nobody reads.
 *
 * @param {Number} value
 * @return {String}
 */
const percent = value => Math.round(value) + '%';

/**
 * Builds the summary of one report.
 *
 * @param {HTMLTableElement} table
 * @param {Object} strings
 */
const summarise = (table, strings) => {
    const rows = [...table.querySelectorAll('tbody tr')].map(readRow).filter(Boolean);
    const items = rows.filter(row => !row.istotal && !row.iscategory);
    const total = rows.filter(row => row.istotal).pop();
    if (!items.length) {
        return;
    }
    const graded = items.filter(item => item.percentage !== null);

    const panel = el('section', 'rsmax-grades');
    panel.setAttribute('aria-label', strings.summary);
    const top = el('div', 'rsmax-grades-top');

    const course = el('div', 'rsmax-grades-course');
    const ring = el('div', 'rsmax-ring rsmax-grades-ring');
    const known = total && total.percentage !== null;
    ring.style.setProperty('--rsmax-ring', known ? total.percentage : 0);
    const figure = el('span', 'rsmax-ring-figure', known ? String(Math.round(total.percentage)) : '–');
    if (known) {
        figure.append(el('small', '', '%'));
    }
    ring.append(figure);
    const coursetext = el('div', 'rsmax-grades-coursetext');
    coursetext.append(el('span', 'rsmax-grades-label', strings.course));
    if (known && total.gradetext && total.max !== null) {
        coursetext.append(el('span', 'rsmax-grades-points', total.gradetext + ' / ' + total.max));
    } else if (!known) {
        coursetext.append(el('span', 'rsmax-grades-points', strings.nogrades));
    }
    course.append(ring, coursetext);

    const figures = el('dl', 'rsmax-grades-figures');
    const add = (value, label) => {
        const box = el('div', '');
        box.append(el('dt', '', label), el('dd', '', value));
        figures.append(box);
    };
    add(graded.length + '/' + items.length, strings.graded);
    add(String(items.length - graded.length), strings.topending);
    if (graded.length) {
        add(percent(graded.reduce((sum, item) => sum + item.percentage, 0) / graded.length), strings.average);
    }
    top.append(course, figures);
    panel.append(top);
    (table.closest('.table-responsive') || table).before(panel);

    // The table tells the rest: the percentage of each activity becomes a bar, in the colour of
    // its result when the activity has a pass mark, and what is not graded yet says so.
    rows.forEach(item => {
        const cell = item.row.querySelector(SELECTORS.PERCENTAGE);
        if (!cell) {
            return;
        }
        if (item.percentage === null) {
            if (!item.istotal && !item.iscategory) {
                cell.classList.add('rsmax-pct-pending');
                cell.textContent = strings.pending;
            }
            return;
        }
        cell.classList.add('rsmax-pct');
        cell.classList.toggle('is-passed', item.passed);
        cell.classList.toggle('is-failed', item.failed);
        cell.style.setProperty('--rsmax-pct', item.percentage + '%');
    });
};

/**
 * Starts the module on a page with the grades of a learner.
 *
 * @param {Object} strings Texts of the summary.
 */
export const init = strings => {
    document.querySelectorAll(SELECTORS.TABLE).forEach(table => {
        if (!table.dataset.rsmaxGrades) {
            table.dataset.rsmaxGrades = '1';
            summarise(table, strings);
        }
    });
};
