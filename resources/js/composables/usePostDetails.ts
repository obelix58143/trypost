import { router, useHttp } from '@inertiajs/vue3';
import { ref } from 'vue';

import { publication as showPublication } from '@/actions/App/Http/Controllers/App/InsightsController';
import { edit as editPostRoute } from '@/actions/App/Http/Controllers/App/PostController';
import { show as showPostGroup } from '@/actions/App/Http/Controllers/App/PostGroupController';
import { duplicatePostCard } from '@/composables/usePostCardActions';
import type { PublicationAnalyticsDetail } from '@/types/analytics';
import type { PostCard, PostCardMenuAction } from '@/types/publish';

/**
 * Opens the details of a post, or of a network publication without one, on a
 * page that only knows their ids (the Insights tables), so the details never
 * leave the page they were opened from.
 */
export const usePostDetails = () => {
    const http = useHttp<Record<string, never>, PostCard[]>({});
    const detailsPost = ref<PostCard | null>(null);
    const detailsOpen = ref(false);
    const publicationHttp = useHttp<Record<string, never>, PublicationAnalyticsDetail>({});
    const detailsPublication = ref<PublicationAnalyticsDetail | null>(null);
    const publicationOpen = ref(false);

    const openPost = async (postId: string): Promise<void> => {
        const posts = await http.get(showPostGroup.url(postId));
        const post = posts?.find((candidate) => candidate.id === postId);

        if (post) {
            detailsPost.value = post;
            detailsOpen.value = true;
        }
    };

    const openPublication = async (publicationId: string): Promise<void> => {
        const detail = await publicationHttp.get(showPublication.url(publicationId));

        if (detail) {
            detailsPublication.value = detail;
            publicationOpen.value = true;
        }
    };

    const editPost = (post: PostCard): void => {
        router.visit(editPostRoute.url(post.id));
    };

    const runPostAction = (action: PostCardMenuAction, post: PostCard): void => {
        if (action === 'duplicate') {
            duplicatePostCard(post);
        }
    };

    return {
        detailsPost,
        detailsOpen,
        openPost,
        detailsPublication,
        publicationOpen,
        openPublication,
        editPost,
        runPostAction,
    };
};
