import { computed, unref   } from 'vue';
import type {ComputedRef, Ref} from 'vue';

type MaybeRef<T> = T | Ref<T> | ComputedRef<T>;

interface ApprovalSignatureState {
    path: string | null;
    signedAt: string | null;
}

interface ApprovalCardItem<TRole extends string> {
    role: TRole;
    title: string;
    name: string;
    signaturePath: string | null;
    signedAt: string | null;
    badge: string;
}

interface UseDocumentApprovalCardsOptions<TRole extends string> {
    activeRole: MaybeRef<TRole | null>;
    clearRole: MaybeRef<TRole | null>;
    cards: MaybeRef<ApprovalCardItem<TRole>[]>;
}

export function useDocumentApprovalCards<TRole extends string>(
    options: UseDocumentApprovalCardsOptions<TRole>,
) {
    const approvalCards = computed(() => unref(options.cards));

    const activeApprovalCard = computed(
        () =>
            approvalCards.value.find(
                (card) => card.role === unref(options.activeRole),
            ) ?? null,
    );

    const activeClearCard = computed(
        () =>
            approvalCards.value.find(
                (card) => card.role === unref(options.clearRole),
            ) ?? null,
    );

    const signedCount = computed(
        () => approvalCards.value.filter((card) => card.signaturePath).length,
    );

    return {
        approvalCards,
        activeApprovalCard,
        activeClearCard,
        signedCount,
    };
}

export function createApprovalSignatureState(
    path: string | null,
    signedAt: string | null,
): ApprovalSignatureState {
    return { path, signedAt };
}

export function createApprovalBadge(path: string | null) {
    return path ? 'Ditandatangani' : 'Menunggu';
}
