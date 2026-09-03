import {
    ensureSnipeAssetsLoaded,
    ensureSnipeDirectoryLoaded,
    getSnipeAssetById,
    getSnipeAssetLabel,
    getSnipeAssetReferenceLabel,
    getSnipeAssetReferenceValue,
    getSnipeDeptHeadLabel,
    getSnipeGroupParts,
    getSnipeUserById,
    getSnipeUserEmail,
    getSnipeUserLabel,
    getSnipeUserPhone,
    getSnipeUserPosition,
    normalizeSnipeAssetCategory,
    useSnipeDirectory,
} from '@/composables/useSnipeDirectory';

export const ensureStbDirectoryLoaded = ensureSnipeDirectoryLoaded;
export const ensureStbAssetsLoaded = ensureSnipeAssetsLoaded;
export const normalizeStbAssetCategory = normalizeSnipeAssetCategory;
export const getStbUserLabel = getSnipeUserLabel;
export const getStbUserPhone = getSnipeUserPhone;
export const getStbUserEmail = getSnipeUserEmail;
export const getStbUserPosition = getSnipeUserPosition;
export const getStbDeptHeadLabel = getSnipeDeptHeadLabel;
export const getStbUserById = getSnipeUserById;
export const getStbAssetById = getSnipeAssetById;
export const getStbAssetLabel = getSnipeAssetLabel;
export const getStbAssetReferenceLabel = getSnipeAssetReferenceLabel;
export const getStbAssetReferenceValue = getSnipeAssetReferenceValue;
export const getStbGroupParts = getSnipeGroupParts;
export const useStbDirectory = useSnipeDirectory;
