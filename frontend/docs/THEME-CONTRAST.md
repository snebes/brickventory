# Light palette and contrast

The palette uses light blue `#C8D9E1`, turquoise `#00C5BC`, and yellow `#FEE89F`
for fills and decorative accents. App text stays near-black or neutral dark gray;
the sidebar and outer canvas retain their very light gray surfaces. The supplied
logo remains unchanged, with pink retained as a limited decorative accent.

Contrast targets follow WCAG 2.2 AA: normal text at least 4.5:1 and essential
control/graphic boundaries and visible focus indicators at least 3:1. Sources:
[Text contrast](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html) and
[non-text contrast](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html).

Light theme examples, calculated from the actual CSS tokens:

| Foreground / background | Contrast |
| --- | --- |
| Primary text / white | 18.36:1 |
| Secondary small text / white | 10.41:1 |
| Primary button text / light turquoise | 8.49:1 |
| Hovered button text / turquoise shade | 6.57:1 |
| Warning text / light yellow | 5.00:1 |
| Information text / light blue | 8.22:1 |
| Input border / white | 5.15:1 |
| Focus ring / white | 5.05:1 |

Light fills alone are insufficient to identify chart boundaries or checked controls
against white. Charts/progress indicators therefore have darker outlines, stock
rulers use darker strokes, checkbox accents retain dark turquoise, and inputs use
a dedicated contrasting border. Decorative card edges can use the exact light
palette; they do not carry unique information. State badges include readable text,
not just color. Placeholders use the same high-contrast neutral text scale.

`test/unit/themeContrast.test.ts` reads the CSS token file and checks normal text,
status pairs, button states, focus rings, input borders and chart outlines in both
light and dark token sets. `test/e2e/theme-contrast.spec.ts` checks actual computed
colors on the rendered dashboard and product form, including hover/focus states.
These checks cover theme contrast; they do not constitute a complete accessibility
or legal-conformance audit of every application workflow.
