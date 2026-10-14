/** Portraits are drawn from a short recipe stored with the case: `v1;g=f;skin=2;hair=3;style=1;shirt=4;extra=glasses`. */
export interface AvatarRecipe {
    gender: 'm' | 'f';
    skin: number;
    hair: number;
    style: number;
    shirt: number;
    extra: string;
}

export const SKIN_TONES = ['#f6d5b8', '#efbf94', '#d99a6c', '#b87650', '#8d5a3b', '#6b4229'];
export const HAIR_COLORS = ['#2b211c', '#4a3022', '#7a4a2a', '#b5762f', '#d8b25a', '#a7332b', '#9aa1a6'];
export const SHIRT_COLORS = ['#3f6f8f', '#a14c4c', '#4f7d5c', '#8a6aa8', '#c58a2f', '#2f4858', '#b0577a', '#5c6f3f'];
export const BACKGROUNDS = ['#dcebee', '#f2e4ce', '#e5def0', '#deeadb', '#f0dfe2', '#e2e5e3', '#f6e9a8', '#cfe4f6'];

function hash(text: string): number {
    let value = 2166136261;
    for (const char of text) {
        value = Math.imul(value ^ char.codePointAt(0)!, 16777619) >>> 0;
    }

    return value;
}

function pick(seed: number, shift: number, size: number): number {
    return Math.floor(seed / 2 ** shift) % size;
}

/**
 * The recipe of the case, or a stable one made from the name for older cases that have none.
 * The gender of a made-up recipe follows the name ending, which fits Russian full names.
 */
export function avatarRecipe(avatar: string | null | undefined, name: string): AvatarRecipe {
    if (avatar?.startsWith('v1;')) {
        const parts = Object.fromEntries(avatar.split(';').slice(1).map((part) => part.split('=') as [string, string]));
        const number = (key: string, size: number): number => Math.abs(Number.parseInt(parts[key] ?? '0', 10) || 0) % size;

        return {
            gender: parts.g === 'm' ? 'm' : 'f',
            skin: number('skin', SKIN_TONES.length),
            hair: number('hair', HAIR_COLORS.length),
            style: number('style', 7),
            shirt: number('shirt', SHIRT_COLORS.length),
            extra: parts.extra ?? 'none',
        };
    }

    const seed = hash(name);
    const female = /[ая]$/iu.test(name.trim().split(/\s+/).at(-1) ?? '');
    const styles = female ? [1, 2, 3, 4] : [0, 0, 4, 5, 6];
    const extras = female ? ['none', 'glasses', 'earrings', 'freckles'] : ['none', 'glasses', 'beard', 'mustache'];

    return {
        gender: female ? 'f' : 'm',
        skin: pick(seed, 0, SKIN_TONES.length),
        hair: pick(seed, 3, HAIR_COLORS.length),
        style: styles[pick(seed, 6, styles.length)],
        shirt: pick(seed, 9, SHIRT_COLORS.length),
        extra: extras[pick(seed, 12, extras.length)],
    };
}
