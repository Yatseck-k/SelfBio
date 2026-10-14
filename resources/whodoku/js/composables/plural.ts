/** Russian number agreement: «1 очко, 2 очка, 5 очков». */
export function plural(count: number, one: string, few: string, many: string): string {
    const n = Math.abs(count);
    if (n % 100 >= 11 && n % 100 <= 14) return many;
    if (n % 10 === 1) return one;

    return n % 10 >= 2 && n % 10 <= 4 ? few : many;
}
