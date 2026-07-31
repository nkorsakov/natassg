import imageCompression from 'browser-image-compression';

const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];

export function isImageFile(file) {
    return file && (IMAGE_TYPES.includes(file.type) || file.type.startsWith('image/'));
}

/**
 * Сжимает крупные фото (в т.ч. iPhone) перед загрузкой.
 * Документы и небольшие файлы возвращаются как есть.
 */
export async function prepareUploadFile(file, options = {}) {
    if (!file || !isImageFile(file)) {
        return { file, width: null, height: null };
    }

    const maxSizeMB = options.maxSizeMB ?? 1.2;
    const maxWidthOrHeight = options.maxWidthOrHeight ?? 1920;

    // Уже небольшое — не трогаем
    if (file.size < 400 * 1024) {
        const dims = await readImageSize(file);
        return { file, ...dims };
    }

    try {
        const compressed = await imageCompression(file, {
            maxSizeMB,
            maxWidthOrHeight,
            useWebWorker: true,
            fileType: 'image/jpeg',
            initialQuality: 0.82,
        });

        const out = new File(
            [compressed],
            renameToJpeg(file.name),
            { type: 'image/jpeg', lastModified: Date.now() },
        );
        const dims = await readImageSize(out);
        return { file: out, ...dims };
    } catch {
        const dims = await readImageSize(file);
        return { file, ...dims };
    }
}

function renameToJpeg(name) {
    const base = name.replace(/\.[^.]+$/, '') || 'photo';
    return `${base}.jpg`;
}

function readImageSize(file) {
    return new Promise((resolve) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => {
            URL.revokeObjectURL(url);
            resolve({ width: img.naturalWidth || null, height: img.naturalHeight || null });
        };
        img.onerror = () => {
            URL.revokeObjectURL(url);
            resolve({ width: null, height: null });
        };
        img.src = url;
    });
}
