import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const FALLBACK = 'SelfBio';

type SharedProps = { brand?: string };

/** The name of the site comes from the server (APP_NAME, `php artisan site:rename`): pages never spell it out. */
export function useBrand() {
    // Outside an Inertia app (component tests) the page has no props: the fallback name is shown.
    const page = usePage<SharedProps>() as { props?: SharedProps } | undefined;

    return computed(() => page?.props?.brand ?? FALLBACK);
}
