import { describe, expect, it } from 'vitest';
import { avatarRecipe, BACKGROUNDS, HAIR_COLORS, SHIRT_COLORS, SKIN_TONES } from '../composables/avatar';

describe('avatarRecipe', () => {
    it('reads the recipe stored with the case', () => {
        expect(avatarRecipe('v1;g=m;skin=2;hair=3;style=5;shirt=1;extra=beard', 'Иван')).toEqual({
            gender: 'm', skin: 2, hair: 3, style: 5, shirt: 1, extra: 'beard',
        });
    });

    it('keeps values inside the palettes whatever the data says', () => {
        const recipe = avatarRecipe('v1;g=f;skin=99;hair=-4;style=40;shirt=77;extra=none', 'Анна');

        expect(recipe.skin).toBeLessThan(SKIN_TONES.length);
        expect(recipe.hair).toBeLessThan(HAIR_COLORS.length);
        expect(recipe.shirt).toBeLessThan(SHIRT_COLORS.length);
        expect(recipe.style).toBeLessThan(7);
        expect(BACKGROUNDS.length).toBeGreaterThanOrEqual(SHIRT_COLORS.length);
    });

    it('draws older cases from the name, always the same way and in the gender of the surname', () => {
        const first = avatarRecipe(null, 'Мария Петрова');

        expect(avatarRecipe(undefined, 'Мария Петрова')).toEqual(first);
        expect(first.gender).toBe('f');
        expect(avatarRecipe(null, 'Пётр Волков').gender).toBe('m');
        expect([1, 2, 3, 4]).toContain(first.style);
    });
});
