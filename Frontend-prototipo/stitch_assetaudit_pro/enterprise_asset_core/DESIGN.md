---
name: Enterprise Asset Core
colors:
  surface: '#f7f9fc'
  surface-dim: '#d8dadd'
  surface-bright: '#f7f9fc'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f2f4f7'
  surface-container: '#eceef1'
  surface-container-high: '#e6e8eb'
  surface-container-highest: '#e0e3e6'
  on-surface: '#191c1e'
  on-surface-variant: '#424752'
  inverse-surface: '#2d3133'
  inverse-on-surface: '#eff1f4'
  outline: '#727783'
  outline-variant: '#c2c6d4'
  surface-tint: '#005db5'
  primary: '#0052a1'
  on-primary: '#ffffff'
  primary-container: '#206bc4'
  on-primary-container: '#e7edff'
  inverse-primary: '#a8c8ff'
  secondary: '#585f6b'
  on-secondary: '#ffffff'
  secondary-container: '#d9e0ef'
  on-secondary-container: '#5c6370'
  tertiary: '#864000'
  on-tertiary: '#ffffff'
  tertiary-container: '#ab5300'
  on-tertiary-container: '#ffeade'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#d6e3ff'
  primary-fixed-dim: '#a8c8ff'
  on-primary-fixed: '#001b3d'
  on-primary-fixed-variant: '#00468b'
  secondary-fixed: '#dce3f2'
  secondary-fixed-dim: '#c0c7d5'
  on-secondary-fixed: '#151c27'
  on-secondary-fixed-variant: '#404753'
  tertiary-fixed: '#ffdbc7'
  tertiary-fixed-dim: '#ffb688'
  on-tertiary-fixed: '#311300'
  on-tertiary-fixed-variant: '#733600'
  background: '#f7f9fc'
  on-background: '#191c1e'
  surface-variant: '#e0e3e6'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '700'
    lineHeight: '1.2'
    letterSpacing: -0.02em
  display-lg-mobile:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '700'
    lineHeight: '1.2'
  headline-md:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: '1.4'
  headline-sm:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '600'
    lineHeight: '1.4'
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: '1.5'
  body-sm:
    fontFamily: Inter
    fontSize: 13px
    fontWeight: '400'
    lineHeight: '1.5'
  label-caps:
    fontFamily: Inter
    fontSize: 11px
    fontWeight: '600'
    lineHeight: '1.2'
    letterSpacing: 0.05em
  mono-label:
    fontFamily: jetbrainsMono
    fontSize: 12px
    fontWeight: '450'
    lineHeight: '1.4'
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 4px
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 32px
  2xl: 48px
  container-max: 1280px
  gutter: 24px
---

## Brand & Style

This design system is engineered for high-density IT asset management, prioritizing clarity, efficiency, and professional rigor. The aesthetic draws from the "Corporate Modern" movement, blending the systematic utility of technical dashboards with a premium, polished finish.

The visual narrative focuses on "Structured Intelligence." It utilizes a neutral foundation to allow data and status indicators to command attention. The interface is characterized by generous whitespace to reduce cognitive load in complex environments, balanced by crisp borders and a refined use of the primary corporate blue. The emotional response is one of reliability, precision, and institutional trust.

## Colors

The palette is anchored by "Corporate Blue," used strategically for primary actions, active states, and brand reinforcement. 

### Light Mode
The background uses a cool-toned off-white to reduce eye strain, while surfaces (cards, modals) are pure white to create a clear visual hierarchy. Borders are subtle, serving as structural guides rather than focal points.

### Dark Mode
The dark theme shifts to a deep graphite navy. Surface levels are differentiated by slight shifts in lightness rather than heavy shadows. Blue accents are tuned for higher luminosity to maintain accessibility against dark backgrounds.

### Semantic Colors
- **Success:** #2fb344 (Asset active/verified)
- **Warning:** #f76707 (Maintenance due/Warranty expiring)
- **Danger:** #d63939 (Missing/Critical failure)
- **Info:** #4299e1 (Deployment in progress)

## Typography

The typography system relies on **Inter**, a typeface designed for user interfaces. It provides exceptional legibility at small sizes, crucial for data-heavy tables and property lists.

- **Weight Usage:** Use Semibold (600) for section headers and Bold (700) for primary dashboard metrics. Regular (400) is the standard for all body copy.
- **Micro-copy:** Use `label-caps` for table headers and secondary category labels to create a clear distinction from dynamic data.
- **Technical Data:** For serial numbers, IP addresses, and asset tags, use a monospaced font (JetBrains Mono) at the `mono-label` level to ensure character alignment and readability.

## Layout & Spacing

The design system employs a **Fluid-to-Fixed Grid** model. Content scales fluidly until reaching a 1280px maximum container width on desktop to maintain optimal line lengths for data tables.

### Grid & Breakpoints
- **Desktop (1024px+):** 12-column grid, 24px gutters, 32px page margins.
- **Tablet (768px - 1023px):** 6-column grid, 16px gutters, 24px page margins.
- **Mobile (<768px):** 2-column grid, 12px gutters, 16px page margins.

### Spacing Rhythm
A 4px baseline grid governs all spacing. Horizontal spacing in tables should be more generous than vertical spacing to lead the eye across asset rows effectively. Use `lg` (24px) for spacing between unrelated card components and `md` (16px) for internal card padding.

## Elevation & Depth

Visual hierarchy is established through **Tonal Layering** and **Ambient Shadows**.

1.  **Level 0 (Background):** The lowest layer, using the base background color.
2.  **Level 1 (Cards/Surface):** Pure white (Light) or Navy (Dark). These use a subtle 1px border (`border-color`) to define edges.
3.  **Level 2 (Dropdowns/Popovers):** Elevated with a "Soft Ambient" shadow: `0 10px 15px -3px rgba(0, 0, 0, 0.07), 0 4px 6px -2px rgba(0, 0, 0, 0.05)`.
4.  **Level 3 (Modals):** High elevation with a more pronounced shadow and a backdrop blur of 8px on the layer beneath.

In Dark Mode, shadows are replaced by "Inner Glow" borders (0.5px stroke with 10% white opacity) on the top edge of cards to simulate a light source.

## Shapes

The shape language is "Rounded-Professional," utilizing an 8px (0.5rem) base radius. This creates an interface that feels modern and approachable without losing its corporate authority.

- **Base Radius (8px):** Applied to buttons, input fields, and small cards.
- **Large Radius (16px):** Applied to main dashboard containers and modal windows.
- **Interactive States:** Hovering over a list item or table row should trigger a 4px rounded highlight background rather than a sharp-edged box.

## Components

### Buttons
- **Primary:** Corporate Blue background, white text. 8px radius. Subtle 10% darken on hover.
- **Secondary:** White background with a 1px `#e6e8e9` border.
- **Ghost:** No background/border; primary color text. Used for less frequent actions.

### Tables (Core Component)
- **Header:** `label-caps` typography, light gray background (#f1f5f9), 1px bottom border.
- **Rows:** 1px subtle bottom border. Hover state: Background changes to `#f8fafc`.
- **Density:** Provide a "Compact" toggle that reduces vertical padding from 12px to 8px for power users.

### Status Badges
- Small, 4px rounded containers with a low-opacity background of the semantic color (e.g., 10% Success Green) and high-contrast text of the same hue.

### Input Fields
- **Default:** 1px border, 8px radius, white background.
- **Focus:** 1px Corporate Blue border with a 3px soft blue outer glow (box-shadow).
- **Labels:** Always positioned above the field using `body-sm` semibold.

### Cards
- Use for dashboard metrics. Include a 24px icon in the top right (Primary Blue) and the metric value in `display-lg`.