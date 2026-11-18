import { test, expect } from '@playwright/test'

test('rendered light theme keeps readable text, controls, focus rings and chart boundaries', async ({
    page,
    request,
}) => {
    await request.get('http://127.0.0.1:4318/__reset')
    await page.goto('/login')
    await page.getByLabel('Workspace access token').fill('frontend-test-token')
    await page.getByRole('button', { name: 'Sign in', exact: true }).click()
    await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible()
    async function check(selector: string, property = 'color', minimum = 4.5) {
        const ratio = await page
            .locator(selector)
            .first()
            .evaluate((element, property) => {
                function rgb(value: string): number[] {
                    return value
                        .match(/[\d.]+/g)!
                        .map(Number)
                        .slice(0, 3)
                        .map((v) => v / 255)
                }
                function luminance(channels: number[]) {
                    return channels.reduce(
                        (sum, v, i) =>
                            sum +
                            (v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4) * [0.2126, 0.7152, 0.0722][i]!,
                        0,
                    )
                }
                function background(node: Element | null): number[] {
                    if (!node) return [1, 1, 1]
                    const value = getComputedStyle(node).backgroundColor
                    const components = value.match(/[\d.]+/g)!.map(Number),
                        alpha = components[3] ?? 1
                    const color = rgb(value)
                    return alpha === 1
                        ? color
                        : color.map((v, i) => v * alpha + background(node.parentElement)[i]! * (1 - alpha))
                }
                const style = getComputedStyle(element)
                const foreground = luminance(rgb(style.getPropertyValue(property)))
                const surface = luminance(
                    background(
                        property === 'outline-color' || property === 'border-top-color'
                            ? element.parentElement
                            : element,
                    ),
                )
                return (Math.max(foreground, surface) + 0.05) / (Math.min(foreground, surface) + 0.05)
            }, property)
        expect(ratio, `${selector} ${property}: ${ratio.toFixed(2)}:1`).toBeGreaterThanOrEqual(minimum)
    }
    await check('h1')
    await check('.stat-card strong')
    await check('.stat-card .muted')
    await check('.sidebar a[aria-current="page"]')
    await check('.badge.pending')
    await check('.primary')
    await page.locator('.primary').first().hover()
    await check('.primary')
    await page.keyboard.press('Tab')
    await page.locator('.primary').first().focus()
    await expect(page.locator('.primary').first()).toHaveCSS('outline-width', '3px')
    await check('.primary', 'outline-color', 3)
    await check('.dashboard-bar-out', 'border-top-color', 3)
    await page.goto('/products/new')
    await check('#field-name')
    await check('#field-name', 'border-top-color', 3)
})
