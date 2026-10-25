import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import test from 'node:test';

const bytes = await readFile(new URL('../../plugins/local/asodeltolstaff/lang/es/local_asodeltolstaff.php', import.meta.url));
const source = new TextDecoder('utf-8', {fatal:true}).decode(bytes);
const strings = new Map([...source.matchAll(/\$string\['([^']+)'\]\s*=\s*'((?:\\.|[^'\\])*)';/g)]
    .map(([,key,value])=>[key,value]));

test('el catálogo español es UTF-8 válido sin BOM ni caracteres dañados', () => {
    assert.notDeepEqual([...bytes.subarray(0,3)], [0xef,0xbb,0xbf]);
    assert.ok(strings.size > 100, 'Se debe comprobar el catálogo completo');
    for (const [key,value] of strings) {
        assert.doesNotMatch(value, /\uFFFD|\u00C3[\u0080-\u00BF]|\u00C2[\u0080-\u00BF]/u, key);
        assert.doesNotMatch(value, /\p{L}\?\p{L}/u, key);
        // Interrogatives are allowed when written with Spanish opening punctuation.
        if (!value.includes('¿')) assert.ok(!value.includes('?'), `Sustitución por ? en ${key}`);
    }
});

test('la ayuda conserva las tildes y la ñ que se mostraban como interrogaciones', () => {
    for (const [key,words] of Object.entries({
        guide_access:['está','comunícate'],
        guide_submission:['botón','envío'],
        guide_grades:['finalización','qué','configuración'],
        guide_communication:['administración'],
        guide_account:['contraseña','sesión','recuperación','página'],
    })) {
        for (const word of words) assert.ok(strings.get(key)?.includes(word), `${key}: falta ${word}`);
    }
});
