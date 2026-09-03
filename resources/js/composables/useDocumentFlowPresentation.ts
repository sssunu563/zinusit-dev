import { computed, unref } from 'vue';
import type { ComputedRef, Ref } from 'vue';

type MaybeRef<T> = T | Ref<T> | ComputedRef<T>;

interface UseDocumentFlowPresentationOptions {
    isReturnFlow: MaybeRef<boolean>;
    isLoanOut: MaybeRef<boolean>;
    isCompleted: MaybeRef<boolean>;
    isCancelled: MaybeRef<boolean>;
    baseDetailLabel: string;
    photoLabel: MaybeRef<string>;
}

export function useDocumentFlowPresentation(
    options: UseDocumentFlowPresentationOptions,
) {
    const approvalSectionKicker = computed(() =>
        unref(options.isReturnFlow) ? 'Return' : 'Approval',
    );

    const approvalSectionTitle = computed(() => {
        if (unref(options.isReturnFlow)) {
            return 'Ready to Sign Return';
        }

        if (unref(options.isLoanOut)) {
            return 'Ready to Sign Loan';
        }

        return 'Ready to Sign';
    });

    const approvalSectionCopy = computed(() => {
        if (unref(options.isReturnFlow)) {
            return 'This signature column is used to validate the asset return process.';
        }

        if (unref(options.isLoanOut)) {
            return 'This signature column is used to validate the asset loan process.';
        }

        return 'This signature column is used to validate the asset handover process.';
    });

    const approvalEmptyCopy = computed(() =>
        unref(options.isReturnFlow)
            ? 'Sign return via mobile or desktop'
            : 'Sign via mobile or desktop',
    );

    const photoPanelKicker = computed(() =>
        unref(options.isReturnFlow) ? 'Return' : 'Photo',
    );

    const photoPanelTitle = computed(() => {
        if (unref(options.isReturnFlow)) {
            return 'Visual proof of return';
        }

        if (unref(options.isLoanOut)) {
            return 'Visual proof of loan';
        }

        return 'Visual proof';
    });

    const photoPanelEmptyCopy = computed(
        () => `${unref(options.photoLabel)} not available`,
    );

    const remarkPanelKicker = computed(() =>
        unref(options.isReturnFlow) ? 'Return Remark' : 'Remark',
    );

    const remarkPanelTitle = computed(() => {
        if (unref(options.isReturnFlow)) {
            return 'Asset return notes';
        }

        if (unref(options.isLoanOut)) {
            return 'Loan notes';
        }

        return 'Additional notes';
    });

    const heroDetailKicker = computed(() => {
        if (unref(options.isReturnFlow)) {
            return 'Return Detail';
        }

        if (unref(options.isLoanOut)) {
            return 'Loan Detail';
        }

        return options.baseDetailLabel;
    });

    const heroProgressLabel = computed(() =>
        unref(options.isReturnFlow)
            ? 'Return Progress'
            : 'Document Progress',
    );

    const heroProgressMeta = computed(() => {
        if (unref(options.isCompleted)) {
            return 'Document finalized';
        }

        if (unref(options.isCancelled)) {
            return 'Document cancelled';
        }

        if (unref(options.isReturnFlow)) {
            return 'Waiting for return validation';
        }

        if (unref(options.isLoanOut)) {
            return 'Waiting for loan process';
        }

        return 'Waiting for approval process';
    });

    const heroUpdatedMetaPrefix = computed(() =>
        unref(options.isReturnFlow) ? 'Received:' : 'Location:',
    );

    const statementKicker = computed(() =>
        unref(options.isReturnFlow) ? 'Return Statement' : 'Statement',
    );

    const statementTitle = computed(() => {
        if (unref(options.isReturnFlow)) {
            return 'Asset Return Confirmation';
        }

        if (unref(options.isLoanOut)) {
            return 'Asset Loan Terms';
        }

        return 'Terms & Responsibility';
    });

    const statementIntro = computed(() => {
        if (unref(options.isReturnFlow)) {
            return 'The signing party declares that the asset has been returned and verified according to the condition when received back.';
        }

        if (unref(options.isLoanOut)) {
            return 'The signing party agrees to the company asset loan terms and responsibility of use during the loan period.';
        }

        return 'Have agreed to the applicable terms consciously and without coercion from any party:';
    });

    const statementBody = computed(() => {
        if (unref(options.isReturnFlow)) {
            return '(A) Ensure the returned asset matches the document data and visual inspection results.\n(B) Explain the last condition of the asset, including damage, lack of accessories, or functional changes if any.\n(C) Agree that follow-up repairs or replacements will be processed according to the verification results of the relevant team.';
        }

        if (unref(options.isLoanOut)) {
            return '(A) Keep the loaned asset safe, clean, and used only for work purposes.\n(B) Do not transfer, re-loan, or change the asset configuration without the approval of the relevant party.\n(C) Responsible for loss or damage due to personal negligence during the loan period.';
        }

        return '(A) Store and maintain all documents, information, or information contained in the item/ asset which are considered company secrets.\n(B) Maintain and try to prevent possible things that can harm company items/ assets.\n(C) Care for, maintain security/ cleanliness and maintain company-owned items/ assets entrusted to him or used in carrying out his work.\n(D) Responsible for making replacements if making personal mistakes/ negligence that result in damage/ loss of company items/assets.';
    });

    const violationTitle = computed(() =>
        unref(options.isReturnFlow)
            ? 'Discrepancy Findings:'
            : 'Violations:',
    );

    const violationBody = computed(() => {
        if (unref(options.isReturnFlow)) {
            return '(A) Asset returned in condition not in accordance with usage records or supporting items are incomplete.\n(B) There is damage, modification, or loss that needs to be followed up by the relevant party.';
        }

        if (unref(options.isLoanOut)) {
            return '(A) Take out or misuse company assets/equipment for personal gain without permission.\n(B) Using company assets for the benefit of third parties without company approval.';
        }

        return '(A) Take out or misuse company-owned items and/ or company-owned equipment for personal gain without company leadership permission.\n(B) Misuse company-owned items entrusted to him for personal gain and profit or other third parties';
    });

    const relationCompletedLabel = computed(() =>
        unref(options.isReturnFlow) ? 'Verified' : 'Final',
    );

    return {
        approvalSectionKicker,
        approvalSectionTitle,
        approvalSectionCopy,
        approvalEmptyCopy,
        photoPanelKicker,
        photoPanelTitle,
        photoPanelEmptyCopy,
        remarkPanelKicker,
        remarkPanelTitle,
        heroDetailKicker,
        heroProgressLabel,
        heroProgressMeta,
        heroUpdatedMetaPrefix,
        statementKicker,
        statementTitle,
        statementIntro,
        statementBody,
        violationTitle,
        violationBody,
        relationCompletedLabel,
    };
}
