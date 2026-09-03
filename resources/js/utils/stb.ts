import {
    formatDocumentFlowDocId,
    isDocumentFlowLoanOut,
    isDocumentFlowLoanReturn,
    isDocumentFlowReturn,
    isDocumentFlowService,
    resolveDocumentFlowAssetLabel,
    resolveDocumentFlowDateLabel,
    resolveDocumentFlowLabel,
    resolveDocumentFlowMovement,
    resolveDocumentFlowPhotoLabel,
    resolveDocumentFlowPrintToolbarCopy,
    resolveDocumentFlowPrintToolbarKicker,
    resolveDocumentFlowRemarkLabel,
    resolveDocumentFlowRequesterRoleLabels,
    resolveDocumentFlowTitle,
    resolveDocumentFlowType,
} from '@/utils/documentFlow';

export const formatStbDocId = ({ id, locationName, date }: { id?: any; locationName?: any; date?: any }) => 
    formatDocumentFlowDocId({ id, locationName, date, prefix: 'STB' });

export {
    isDocumentFlowLoanOut as isStbLoanOut,
    isDocumentFlowLoanReturn as isStbLoanReturn,
    isDocumentFlowReturn as isStbReturnDocument,
    isDocumentFlowService as isStbServiceDocument,
    resolveDocumentFlowAssetLabel as resolveStbAssetLabel,
    resolveDocumentFlowDateLabel as resolveStbDateLabel,
    resolveDocumentFlowLabel as resolveStbDocumentLabel,
    resolveDocumentFlowMovement as resolveStbMovementType,
    resolveDocumentFlowPhotoLabel as resolveStbPhotoLabel,
    resolveDocumentFlowPrintToolbarCopy as resolveStbPrintToolbarCopy,
    resolveDocumentFlowPrintToolbarKicker as resolveStbPrintToolbarKicker,
    resolveDocumentFlowRemarkLabel as resolveStbRemarkLabel,
    resolveDocumentFlowRequesterRoleLabels as resolveStbRequesterRoleLabels,
    resolveDocumentFlowTitle as resolveStbFlowTitle,
    resolveDocumentFlowType as resolveStbDocumentType,
};
