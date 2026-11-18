import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'

// Check the actual CSS tokens with the WCAG relative-luminance formula.
const css = readFileSync(new URL('../../assets/css/design-tokens.css', import.meta.url), 'utf8')
function tokens(selector: string) {
    const block = css.match(new RegExp(`${selector}\\s*\\{([^}]+)\\}`))![1]!
    return Object.fromEntries([...block.matchAll(/(--[\w-]+):\s*([^;]+);/g)].map((m) => [m[1]!, m[2]!.trim()]))
}
const light = tokens(':root')
const dark = { ...light, ...tokens('\\.dark') }
function rgb(value: string): number[] {
    if (value.startsWith('#')) return [1, 3, 5].map((i) => parseInt(value.slice(i, i + 2), 16) / 255)
    const [h, s, l] = value.match(/[\d.]+/g)!.map(Number) as [number, number, number]
    const saturation = s / 100,
        level = l / 100
    const chroma = (1 - Math.abs(2 * level - 1)) * saturation
    const secondary = chroma * (1 - Math.abs(((h / 60) % 2) - 1))
    const offset = level - chroma / 2
    const channels = [
        [chroma, secondary, 0],
        [secondary, chroma, 0],
        [0, chroma, secondary],
        [0, secondary, chroma],
        [secondary, 0, chroma],
        [chroma, 0, secondary],
    ][Math.floor(h / 60) % 6]!
    return channels.map((v) => v + offset)
}
function luminance(value: string) {
    return rgb(value).reduce(
        (sum, channel, i) =>
            sum +
            (channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4) * [0.2126, 0.7152, 0.0722][i]!,
        0,
    )
}
function contrast(a: string, b: string) {
    const values = [luminance(a), luminance(b)].sort((x, y) => y - x)
    return (values[0]! + 0.05) / (values[1]! + 0.05)
}
function check(theme: Record<string, string>, foreground: string, background: string, minimum: number) {
    const ratio = contrast(theme[foreground]!, theme[background]!)
    expect(ratio, `${foreground} on ${background}: ${ratio.toFixed(2)}:1`).toBeGreaterThanOrEqual(minimum)
}
describe('palette contrast', () => {
    it('uses the requested light palette exactly', () => {
        expect(light['--brand-blue']).toBe('#c8d9e1')
        expect(light['--brand-turquoise']).toBe('#00c5bc')
        expect(light['--brand-yellow']).toBe('#fee89f')
        expect(rgb(light['--accent-fill']!).map((v) => Math.round(v * 255))).toEqual([0, 197, 188])
    })
    for (const [name, theme] of [
        ['light', light],
        ['dark', dark],
    ] as const) {
        it(`${name}: normal text meets 4.5:1`, () => {
            for (const fg of ['--text-primary', '--text-secondary', '--text-tertiary']) {
                for (const bg of ['--surface-canvas', '--surface-base', '--surface-raised', '--surface-overlay'])
                    check(theme, fg, bg, 4.5)
            }
            check(theme, '--accent-foreground', '--accent-fill', 4.5)
            check(theme, '--accent-hover-foreground', '--accent-hover-fill', 4.5)
            for (const state of ['success', 'warning', 'danger', 'info'])
                check(theme, `--status-${state}`, `--status-${state}-soft`, 4.5)
            check(theme, '--text-primary', '--status-info-soft', 4.5)
            check(theme, '--ring', '--status-info-soft', 3)
        })
        it(`${name}: focus, control edges and meaningful chart outlines meet 3:1`, () => {
            for (const fg of ['--ring', '--control-border', '--accent', '--status-warning']) {
                for (const bg of ['--surface-canvas', '--surface-base', '--surface-raised', '--surface-overlay'])
                    check(theme, fg, bg, 3)
            }
        })
    }
})
