/**
 * Revisiones antes de subir: tamaño y duración. Evitan que alguien espere una
 * subida de 40 MB para enterarse al final de que el video era muy largo.
 */
export const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
export const VIDEO_TYPES = ['video/mp4', 'video/quicktime', 'video/webm'];
export const ACCEPT = [...IMAGE_TYPES, ...VIDEO_TYPES].join(',');

export type Picked = {
    id: string;
    file: File;
    url: string;
    type: 'image' | 'video';
    seconds: number | null;
};

function videoDuration(url: string): Promise<number | null> {
    return new Promise((resolve) => {
        const video = document.createElement('video');
        video.preload = 'metadata';
        video.onloadedmetadata = () => resolve(Number.isFinite(video.duration) ? video.duration : null);
        video.onerror = () => resolve(null);
        video.src = url;
    });
}

export async function inspect(
    file: File,
    limits: { maxImageMb: number; maxVideoMb: number; maxVideoSeconds: number },
): Promise<{ item?: Picked; error?: string }> {
    const isImage = IMAGE_TYPES.includes(file.type);
    const isVideo = VIDEO_TYPES.includes(file.type);

    if (!isImage && !isVideo) {
        return { error: `«${file.name}» no es una foto ni un video compatible (JPG, PNG, WEBP, MP4, MOV).` };
    }

    const mb = file.size / 1024 / 1024;
    if (isImage && mb > limits.maxImageMb) {
        return { error: `«${file.name}» pesa ${mb.toFixed(1)} MB. Las fotos pueden pesar hasta ${limits.maxImageMb} MB.` };
    }
    if (isVideo && mb > limits.maxVideoMb) {
        return { error: `«${file.name}» pesa ${mb.toFixed(0)} MB. Los videos pueden pesar hasta ${limits.maxVideoMb} MB.` };
    }

    const url = URL.createObjectURL(file);
    let seconds: number | null = null;

    if (isVideo) {
        seconds = await videoDuration(url);
        if (seconds !== null && seconds > limits.maxVideoSeconds + 0.5) {
            URL.revokeObjectURL(url);

            return { error: `«${file.name}» dura ${Math.round(seconds)} s. El máximo es ${limits.maxVideoSeconds} s.` };
        }
    }

    return {
        item: {
            id: `${file.name}-${file.size}-${file.lastModified}-${Math.random().toString(36).slice(2, 7)}`,
            file,
            url,
            type: isVideo ? 'video' : 'image',
            seconds,
        },
    };
}

export function formatSeconds(seconds: number | null): string {
    if (seconds === null) return '';
    const s = Math.round(seconds);

    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}
