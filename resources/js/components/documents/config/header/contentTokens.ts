export type Segment = { kind: 'text' | 'field'; value: string };

/** "Nomor : {documentNumber}" as text and field segments (empty texts are kept between fields). */
export function parseContent(text: string): Segment[] {
    return text
        .split(/(\{[A-Za-z]+\})/)
        .map((part, index) =>
            index % 2 === 1
                ? { kind: 'field' as const, value: part.slice(1, -1) }
                : { kind: 'text' as const, value: part },
        );
}

export function serializeContent(segments: Segment[]): string {
    return segments
        .map((s) =>
            s.kind === 'field' ? `{${s.value}}` : s.value.replace(/[{}]/g, ''),
        )
        .join('');
}
