import { computed, reactive } from 'vue';
import { playerState } from './usePlayer';

/**
 * What the player may use beyond the plain game, from the skills of the detective (the server decides, see
 * `PlayerSkills`). A guest and a new player have only the starting game: no notebook, no strong hints.
 */
export const abilities = reactive({
    /** A statement lights up the object, the room or the window it names on the map («На карте»): attention. */
    locateOnMap: computed(() => playerState.player?.development.abilities.locate ?? false),
    /** Notes on cells: expertise. */
    notes: computed(() => playerState.player?.development.abilities.notes ?? false),
    /** The row and the column of a placed guest are crossed out on the map by themselves: expertise. */
    autoCross: computed(() => playerState.player?.development.abilities.cover ?? false),
    /** The helper that outlines the cells where a guest can still stand: expertise. */
    helper: computed(() => playerState.player?.development.abilities.helper ?? false),
    /** «Отметить кандидатов»: expertise. */
    markCandidates: computed(() => playerState.player?.development.abilities.mark ?? false),
    /** «Поставить»: puts the guest on the only cell the helper leaves: expertise. */
    autoplace: computed(() => playerState.player?.development.abilities.autoplace ?? false),
});
