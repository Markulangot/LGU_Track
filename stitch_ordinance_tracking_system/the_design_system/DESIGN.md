---
name: The Design System
colors:
  surface: '#f7f9fb'
  surface-dim: '#d8dadc'
  surface-bright: '#f7f9fb'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f2f4f6'
  surface-container: '#eceef0'
  surface-container-high: '#e6e8ea'
  surface-container-highest: '#e0e3e5'
  on-surface: '#191c1e'
  on-surface-variant: '#45464d'
  inverse-surface: '#2d3133'
  inverse-on-surface: '#eff1f3'
  outline: '#76777d'
  outline-variant: '#c6c6cd'
  surface-tint: '#565e74'
  primary: '#000000'
  on-primary: '#ffffff'
  primary-container: '#131b2e'
  on-primary-container: '#7c839b'
  inverse-primary: '#bec6e0'
  secondary: '#515f74'
  on-secondary: '#ffffff'
  secondary-container: '#d5e3fd'
  on-secondary-container: '#57657b'
  tertiary: '#000000'
  on-tertiary: '#ffffff'
  tertiary-container: '#00174b'
  on-tertiary-container: '#497cff'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dae2fd'
  primary-fixed-dim: '#bec6e0'
  on-primary-fixed: '#131b2e'
  on-primary-fixed-variant: '#3f465c'
  secondary-fixed: '#d5e3fd'
  secondary-fixed-dim: '#b9c7e0'
  on-secondary-fixed: '#0d1c2f'
  on-secondary-fixed-variant: '#3a485c'
  tertiary-fixed: '#dbe1ff'
  tertiary-fixed-dim: '#b4c5ff'
  on-tertiary-fixed: '#00174b'
  on-tertiary-fixed-variant: '#003ea8'
  background: '#f7f9fb'
  on-background: '#191c1e'
  surface-variant: '#e0e3e5'
typography:
  h1:
    fontFamily: publicSans
    fontSize: 32px
    fontWeight: '700'
    lineHeight: '1.2'
  h2:
    fontFamily: publicSans
    fontSize: 24px
    fontWeight: '600'
    lineHeight: '1.3'
  h3:
    fontFamily: publicSans
    fontSize: 20px
    fontWeight: '600'
    lineHeight: '1.4'
  body-ui:
    fontFamily: publicSans
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.5'
  body-doc:
    fontFamily: newsreader
    fontSize: 18px
    fontWeight: '400'
    lineHeight: '1.6'
  body-doc-sm:
    fontFamily: newsreader
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.5'
  label-bold:
    fontFamily: publicSans
    fontSize: 14px
    fontWeight: '600'
    lineHeight: '1.2'
    letterSpacing: 0.02em
  label-caps:
    fontFamily: publicSans
    fontSize: 12px
    fontWeight: '700'
    lineHeight: '1.1'
    letterSpacing: 0.05em
  caption:
    fontFamily: publicSans
    fontSize: 13px
    fontWeight: '400'
    lineHeight: '1.4'
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  base: 8px
  xs: 4px
  sm: 12px
  md: 24px
  lg: 40px
  xl: 64px
  max-width: 1440px
  gutter: 24px
---

## Brand & Style
The brand personality of this design system is rooted in **institutional trust, legislative precision, and civic authority**. The objective is to facilitate the complex task of ordinance tracking by providing a workspace that feels stable, organized, and reliable. 

The aesthetic style follows a **Modern Corporate** approach with leanings toward **Institutional Minimalism**. It prioritizes function over decoration, utilizing generous white space to reduce cognitive load while maintaining the high data density required for legal review. The emotional response should be one of "calm efficiency"—the user should feel that the system is an impartial vessel for important public records.

## Colors
The palette is dominated by **Deep Navy (#0F172A)** to project authority and **Slate Grays** to provide structure. A crisp white background ensures maximum legibility and a modern, clean feel.

**Primary & Secondary:** Used for navigation, headers, and primary actions to anchor the user's attention.
**Tertiary:** A brighter blue reserved for interactive elements like links and active states to provide clear affordance.
**Status Palette:** Highly legible, desaturated tones for "Passed," "Pending," "Repealed," or "Under Review" indicators. These colors must meet WCAG AA contrast standards against both white and light gray backgrounds.

## Typography
This design system employs a dual-typeface strategy to distinguish between the "Interface" and the "Content."

1.  **Public Sans (Interface):** Used for all navigational elements, buttons, data labels, and headers. Its geometric but neutral character ensures the UI feels modern and accessible.
2.  **Newsreader (Document):** Used exclusively for the text of ordinances, legal briefs, and long-form records. This refined serif mimics the authoritative feel of printed legal documents, improving reading stamina and establishing a formal tone for the actual legislation.

Scale is used aggressively to create a clear hierarchy in data-heavy screens, with uppercase labels used for metadata to distinguish it from interactive UI text.

## Layout & Spacing
The layout utilizes a **Fixed Grid** approach for desktop views to maintain a structured, newspaper-like rigor. Content is centered within a 1440px container using a 12-column grid.

**Rhythm:** An 8px base unit governs all dimensions.
**Density:** For ordinance lists and tracking tables, the system uses "Compact" vertical spacing (12px gutters) to allow users to scan dozens of records without excessive scrolling. 
**Margins:** Large external margins (40px+) are used to frame document views, creating a "reading mode" that mimics a physical piece of paper.

## Elevation & Depth
To maintain an authoritative and "flat" institutional feel, this design system avoids heavy drop shadows. Depth is communicated through **Tonal Layering** and **Low-Contrast Outlines**.

*   **Background:** The lowest layer is #F8FAFC (Neutral Gray).
*   **Surface:** Primary content cards and containers use #FFFFFF (White) with a subtle 1px border in #E2E8F0.
*   **Interaction:** Soft, ambient shadows (0px 4px 12px rgba(15, 23, 42, 0.05)) are reserved exclusively for temporary overlays like dropdown menus or modal dialogs.
*   **Active State:** Elements that are selected or "in focus" use a 2px solid border in the Primary or Tertiary blue rather than a shadow.

## Shapes
Shapes are disciplined and conservative. A **Soft (0.25rem)** corner radius is applied to buttons, input fields, and cards. This slight rounding prevents the UI from feeling "sharp" or aggressive while remaining significantly more professional than the "pill" shapes common in consumer apps. 

Status badges use a slightly higher roundedness (1rem) to distinguish them as "tags" or "pills" distinct from interactive buttons.

## Components
Consistent component behavior ensures the system is predictable for government employees and legal researchers.

*   **Buttons:** Primary buttons are solid Navy (#0F172A). Secondary buttons use a Slate Blue outline. Action text is always Public Sans Bold.
*   **Status Indicators:** Small, pill-shaped badges using the status palette. Text inside badges is always "Label-Caps" for high-contrast scannability.
*   **Data Tables:** The core of the system. Use "Zebra striping" with #F8FAFC and high-contrast Slate text. Headers remain sticky and use a slightly darker gray background.
*   **Ordinance Card:** A specialized container for document previews. It features a Newsreader serif snippet of the text and a sidebar of metadata (Ordinance ID, Date, Sponsor).
*   **Search & Filter Bar:** A persistent, prominent component at the top of tracking views. It uses a refined 1px border and a subtle internal shadow to indicate it is the primary entry point for data retrieval.
*   **Progress Stepper:** A vertical indicator used for tracking an ordinance's journey from "Draft" to "Enacted." It uses solid circles for completed steps and hollow circles for upcoming ones.