import { router } from '@inertiajs/vue3';
import axios from 'axios';
import type { ComponentPublicInstance, Ref } from 'vue';
import { nextTick, onBeforeUnmount, onMounted, ref, unref } from 'vue';

type MaybeRef<T> = T | Ref<T>;

interface UseSignaturePadWorkflowOptions<TRole extends string> {
    isCancelled: MaybeRef<boolean>;
    sharedMode: MaybeRef<boolean>;
    shareSignUrls: MaybeRef<Partial<Record<TRole, string>> | undefined>;
    docId: MaybeRef<string>;
    reloadOnly: string[];
    resolveSignUrl: (role: TRole) => string;
    resolveClearUrl: (role: TRole) => string;
}

export function useSignaturePadWorkflow<TRole extends string>(
    options: UseSignaturePadWorkflowOptions<TRole>,
) {
    const canvasRef = ref<HTMLCanvasElement | null>(null);
    const signatureModalOpen = ref(false);
    const signatureRole = ref<TRole | null>(null);
    const clearConfirmRole = ref<TRole | null>(null);
    const signatureProcessing = ref(false);
    const signatureError = ref('');
    const hasSignatureStroke = ref(false);
    let canvasContext: CanvasRenderingContext2D | null = null;
    let isDrawing = false;

    const strokes = ref<{ x: number; y: number; time: number; type: string }[]>([]);

    const syncCanvasSize = () => {
        const canvas = canvasRef.value;
        if (!canvas) {
            return;
        }

        const ratio = window.devicePixelRatio || 1;
        const width = canvas.clientWidth;
        const height = canvas.clientHeight;

        canvas.width = width * ratio;
        canvas.height = height * ratio;

        canvasContext = canvas.getContext('2d');
        if (!canvasContext) {
            return;
        }

        canvasContext.scale(ratio, ratio);
        canvasContext.lineCap = 'round';
        canvasContext.lineJoin = 'round';
        canvasContext.lineWidth = 2.5;
        canvasContext.strokeStyle = '#003628';
        
        if (strokes.value.length > 0) {
            redraw();
        }
    };

    const redraw = () => {
        if (!canvasContext || !canvasRef.value) return;
        const width = canvasRef.value.clientWidth;
        const height = canvasRef.value.clientHeight;
        canvasContext.clearRect(0, 0, width, height);

        canvasContext.beginPath();
        strokes.value.forEach((p) => {
            if (p.type === 'start') {
                canvasContext!.moveTo(p.x, p.y);
            } else {
                canvasContext!.lineTo(p.x, p.y);
                canvasContext!.stroke();
                canvasContext!.beginPath();
                canvasContext!.moveTo(p.x, p.y);
            }
        });
    };

    const setCanvasRef = (
        element: Element | ComponentPublicInstance | null,
    ) => {
        canvasRef.value = element instanceof HTMLCanvasElement ? element : null;
    };

    const getPoint = (event: MouseEvent | TouchEvent) => {
        const canvas = canvasRef.value;
        if (!canvas) {
            return null;
        }

        const rect = canvas.getBoundingClientRect();
        const source =
            'touches' in event
                ? (event.touches[0] ?? event.changedTouches[0])
                : event;

        if (!source) {
            return null;
        }

        return {
            x: source.clientX - rect.left,
            y: source.clientY - rect.top,
        };
    };

    let lastX = 0;
    let lastY = 0;

    const startDrawing = (event: MouseEvent | TouchEvent) => {
        if (!canvasContext) {
            return;
        }

        const point = getPoint(event);
        if (!point) {
            return;
        }

        isDrawing = true;
        hasSignatureStroke.value = true;
        signatureError.value = '';
        canvasContext.beginPath();
        canvasContext.moveTo(point.x, point.y);
        
        lastX = point.x;
        lastY = point.y;
        
        strokes.value.push({ ...point, time: Date.now(), type: 'start' });
        event.preventDefault();
    };

    const drawSignature = (event: MouseEvent | TouchEvent) => {
        if (!isDrawing || !canvasContext) {
            return;
        }

        const point = getPoint(event);
        if (!point) {
            return;
        }

        const dist = Math.sqrt(Math.pow(point.x - lastX, 2) + Math.pow(point.y - lastY, 2));
        if (dist < 1.2) return;

        canvasContext.lineTo(point.x, point.y);
        canvasContext.stroke();
        
        lastX = point.x;
        lastY = point.y;
        
        strokes.value.push({ ...point, time: Date.now(), type: 'move' });
        event.preventDefault();
    };

    const stopDrawing = () => {
        isDrawing = false;
    };

    const clearSignature = () => {
        hasSignatureStroke.value = false;
        signatureError.value = '';
        strokes.value = [];
        if (canvasRef.value && canvasContext) {
            const width = canvasRef.value.clientWidth;
            const height = canvasRef.value.clientHeight;
            canvasContext.clearRect(0, 0, width, height);
        }
    };

    const closeSignatureModal = () => {
        signatureModalOpen.value = false;
        signatureRole.value = null;
        signatureError.value = '';
        hasSignatureStroke.value = false;
        strokes.value = [];
    };

    const openSignatureModal = async (role: TRole) => {
        if (unref(options.isCancelled)) {
            return;
        }

        signatureRole.value = role;
        signatureModalOpen.value = true;
        hasSignatureStroke.value = false;
        signatureError.value = '';
        strokes.value = [];

        await nextTick();
        syncCanvasSize();
    };

    const openClearConfirm = (role: TRole) => {
        if (unref(options.isCancelled)) {
            return;
        }

        clearConfirmRole.value = role;
    };

    const closeClearConfirm = () => {
        clearConfirmRole.value = null;
    };

    const refreshPageState = () => {
        router.visit(window.location.href, {
            only: options.reloadOnly,
            preserveScroll: true,
            preserveState: true,
        });
    };

    const submitSignature = () => {
        if (!signatureRole.value || !canvasRef.value) {
            return;
        }

        if (!hasSignatureStroke.value || strokes.value.length === 0) {
            signatureError.value = 'Tanda tangan belum diisi.';
            return;
        }

        signatureProcessing.value = true;

        const signUrl = unref(options.sharedMode)
            ? ((unref(options.shareSignUrls) as any)?.[signatureRole.value as string] ?? '')
            : options.resolveSignUrl(signatureRole.value);

        if (!signUrl) {
            signatureProcessing.value = false;
            signatureError.value = 'Link tanda tangan tidak tersedia.';
            return;
        }

        axios
            .post(signUrl, {
                docId: unref(options.docId),
                signature: JSON.stringify(strokes.value),
            })
            .then(() => {
                closeSignatureModal();
                refreshPageState();
            })
            .catch((error) => {
                signatureError.value =
                    error?.response?.data?.message ||
                    'Gagal menyimpan tanda tangan.';
            })
            .finally(() => {
                signatureProcessing.value = false;
            });
    };

    const clearSignatureRole = (role: TRole) => {
        signatureProcessing.value = true;

        axios
            .delete(options.resolveClearUrl(role))
            .then(() => {
                closeClearConfirm();
                refreshPageState();
            })
            .catch((error) => {
                signatureError.value =
                    error?.response?.data?.message ||
                    'Gagal menghapus tanda tangan.';
            })
            .finally(() => {
                signatureProcessing.value = false;
            });
    };

    onMounted(() => {
        window.addEventListener('resize', syncCanvasSize);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('resize', syncCanvasSize);
    });

    return {
        canvasRef,
        signatureModalOpen,
        signatureRole,
        clearConfirmRole,
        signatureProcessing,
        signatureError,
        setCanvasRef,
        startDrawing,
        drawSignature,
        stopDrawing,
        clearSignature,
        openSignatureModal,
        closeSignatureModal,
        openClearConfirm,
        closeClearConfirm,
        submitSignature,
        clearSignatureRole,
        syncCanvasSize,
    };
}
