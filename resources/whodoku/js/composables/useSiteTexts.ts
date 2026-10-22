import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

type SharedProps = { pageTexts?: { puzzles?: { about?: string } } };

const ABOUT = 'Whodoku — детективные логические головоломки. В каждом деле есть карта, гости и их показания: расставьте гостей по комнатам так, чтобы все показания сошлись, и назовите виновного.';

/** The explanation of the catalog page: the editors change it in the admin panel («Настройки сайта»). */
export function useCatalogAbout() {
    // Outside an Inertia app (component tests) the page has no props: the default text is shown.
    const page = usePage<SharedProps>() as { props?: SharedProps } | undefined;

    return computed(() => page?.props?.pageTexts?.puzzles?.about ?? ABOUT);
}
