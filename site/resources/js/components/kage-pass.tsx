/** "19 Jan 2027" in the visitor's language. */
export function passEnds(ends: string, tag: string): string {
    return new Date(`${ends}T12:00:00`).toLocaleDateString(tag, { day: 'numeric', month: 'short', year: 'numeric' });
}
