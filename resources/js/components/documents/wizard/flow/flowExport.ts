/** Resolves var(--token) in a serialized SVG so it renders outside the page. */
function resolveTokens(markup: string): string {
    const style = getComputedStyle(document.documentElement);

    return markup.replace(/var\((--[\w-]+)\)/g, (_, name: string) =>
        style.getPropertyValue(name).trim(),
    );
}

/** Draws the SVG on a white-backed canvas and returns it as a JPG blob. */
export async function svgToJpg(svg: SVGSVGElement): Promise<Blob> {
    const scale = 2;
    const width = svg.viewBox.baseVal.width;
    const height = svg.viewBox.baseVal.height;
    const clone = svg.cloneNode(true) as SVGSVGElement;
    clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
    clone.setAttribute('width', String(width));
    clone.setAttribute('height', String(height));
    const markup = resolveTokens(new XMLSerializer().serializeToString(clone));
    const url = URL.createObjectURL(
        new Blob([markup], { type: 'image/svg+xml;charset=utf-8' }),
    );

    try {
        const image = new Image();
        await new Promise<void>((resolve, reject) => {
            image.onload = () => resolve();
            image.onerror = () => reject(new Error('Gagal membaca diagram.'));
            image.src = url;
        });
        const canvas = document.createElement('canvas');
        canvas.width = width * scale;
        canvas.height = height * scale;
        const context = canvas.getContext('2d')!;
        context.fillStyle = getComputedStyle(document.documentElement)
            .getPropertyValue('--card')
            .trim();
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(image, 0, 0, canvas.width, canvas.height);

        return await new Promise<Blob>((resolve, reject) =>
            canvas.toBlob(
                (blob) =>
                    blob
                        ? resolve(blob)
                        : reject(new Error('Gagal membuat JPG.')),
                'image/jpeg',
                0.92,
            ),
        );
    } finally {
        URL.revokeObjectURL(url);
    }
}

export function download(blob: Blob, name: string): void {
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = name;
    link.click();
    URL.revokeObjectURL(link.href);
}
