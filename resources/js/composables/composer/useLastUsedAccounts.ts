import { computed, onMounted, ref } from 'vue';

import type { ComposerAccount } from '@/composables/usePostComposition';

const STORAGE_KEY = 'trypost:composer:last-used-accounts';

/**
 * The channels the last post was sent to, offered as a shortcut in the
 * composer. Kept in this browser only; without storage there is no shortcut.
 */
export const useLastUsedAccounts = (accounts: () => ComposerAccount[]) => {
    const ids = ref<string[]>([]);

    onMounted(() => {
        try {
            const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '[]');

            if (Array.isArray(saved)) {
                ids.value = saved.filter(
                    (id): id is string => typeof id === 'string',
                );
            }
        } catch {
            ids.value = [];
        }
    });

    const recentlyUsed = computed(() =>
        accounts().filter((account) => ids.value.includes(account.id)),
    );

    const remember = (accountIds: string[]): void => {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(accountIds));
        } catch {
            return;
        }
    };

    return { recentlyUsed, remember };
};
