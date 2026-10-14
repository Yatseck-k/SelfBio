import { describe, expect, it } from 'vitest';
import { plural } from '../composables/plural';

describe('plural', () => {
    it('picks the Russian form by the count', () => {
        const form = (n: number) => plural(n, 'очко', 'очка', 'очков');
        const cases: [number, string][] = [[1, 'очко'], [2, 'очка'], [4, 'очка'], [5, 'очков'], [0, 'очков'], [11, 'очков'], [14, 'очков'], [21, 'очко'], [22, 'очка'], [111, 'очков']];
        for (const [count, expected] of cases) expect(form(count)).toBe(expected);
    });
});
