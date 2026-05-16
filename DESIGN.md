---
name: Vibrant Retailer
colors:
  surface: '#f8f9ff'
  surface-dim: '#cbdbf5'
  surface-bright: '#f8f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eff4ff'
  surface-container: '#e5eeff'
  surface-container-high: '#dce9ff'
  surface-container-highest: '#d3e4fe'
  on-surface: '#0b1c30'
  on-surface-variant: '#434655'
  inverse-surface: '#213145'
  inverse-on-surface: '#eaf1ff'
  outline: '#737686'
  outline-variant: '#c3c6d7'
  surface-tint: '#0053db'
  primary: '#004ac6'
  on-primary: '#ffffff'
  primary-container: '#2563eb'
  on-primary-container: '#eeefff'
  inverse-primary: '#b4c5ff'
  secondary: '#006c49'
  on-secondary: '#ffffff'
  secondary-container: '#6cf8bb'
  on-secondary-container: '#00714d'
  tertiary: '#632ecd'
  on-tertiary: '#ffffff'
  tertiary-container: '#7d4ce7'
  on-tertiary-container: '#f6edff'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dbe1ff'
  primary-fixed-dim: '#b4c5ff'
  on-primary-fixed: '#00174b'
  on-primary-fixed-variant: '#003ea8'
  secondary-fixed: '#6ffbbe'
  secondary-fixed-dim: '#4edea3'
  on-secondary-fixed: '#002113'
  on-secondary-fixed-variant: '#005236'
  tertiary-fixed: '#e9ddff'
  tertiary-fixed-dim: '#d0bcff'
  on-tertiary-fixed: '#23005c'
  on-tertiary-fixed-variant: '#5516be'
  background: '#f8f9ff'
  on-background: '#0b1c30'
  surface-variant: '#d3e4fe'
typography:
  display-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 48px
    fontWeight: '800'
    lineHeight: '1.2'
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 32px
    fontWeight: '700'
    lineHeight: '1.25'
  headline-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 24px
    fontWeight: '700'
    lineHeight: '1.3'
  headline-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 20px
    fontWeight: '600'
    lineHeight: '1.4'
  body-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 18px
    fontWeight: '400'
    lineHeight: '1.6'
  body-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.6'
  label-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 14px
    fontWeight: '600'
    lineHeight: '1.2'
    letterSpacing: 0.01em
  label-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 12px
    fontWeight: '500'
    lineHeight: '1.2'
  headline-lg-mobile:
    fontFamily: Plus Jakarta Sans
    fontSize: 28px
    fontWeight: '700'
    lineHeight: '1.2'
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 8px
  xs: 4px
  sm: 12px
  md: 24px
  lg: 40px
  xl: 64px
  gutter: 24px
  margin-mobile: 16px
  margin-desktop: 32px
---

## Brand & Style

This design system is built for high-energy retail and hospitality environments where speed and clarity are paramount. The brand personality is **Dynamic, Optimistic, and Reliable**. It moves away from the sterile, grey-heavy interfaces of traditional POS software in favor of a "Sophisticated Vibrant" aesthetic—using high-saturation accents to guide the eye while maintaining a professional foundation through structured layouts.

The design style combines **Corporate Modern** with subtle **Glassmorphic** accents. It utilizes large touch targets, generous whitespace to prevent cognitive overload during rush hours, and a clear visual hierarchy that distinguishes between administrative tasks and transactional actions. The emotional goal is to make the staff feel empowered and the customers feel that they are interacting with a cutting-edge, trustworthy brand.

## Colors

The palette is designed to be functional and energetic.
- **Primary (Electric Blue):** Used for main actions, active navigation states, and primary buttons. It represents stability and technology.
- **Secondary (Emerald Green):** Dedicated to "Success" states, completions, and "Add to Cart" functions.
- **Tertiary (Vivid Purple):** Used for secondary categories, loyalty program features, or special promotions.
- **Accent (Bright Orange):** Reserved for "Pay," "Alerts," or "Pending" items that require immediate but non-critical attention.
- **Neutrals:** A range of cool slates and greys provide the structural scaffolding, ensuring the vibrant colors don't become overwhelming.

Backgrounds use a very light tint of blue-grey (#F8FAFC) to reduce eye strain compared to pure white, while maintaining a clean, professional "canvas" feel.

## Typography

This design system uses **Plus Jakarta Sans** for its friendly yet modern geometric construction. The typeface's open counters and tall x-height ensure excellent legibility on tablet screens and fixed POS terminals.

- **Weight Strategy:** Use Bold (700) and ExtraBold (800) for prices and primary totals to ensure they are the first thing a user sees.
- **Body Text:** Maintained at 16px minimum for accessibility in fast-paced environments.
- **Caps:** Use `label-md` in all-caps with slight letter spacing for category headers or table column titles to provide visual distinction without increasing font size.

## Layout & Spacing

The layout follows a **Fluid Grid** system designed primarily for 10-inch tablets and widescreen POS terminals.

- **Desktop/Tablet:** A 12-column grid with a 24px gutter. The layout is typically split: a 3-column left sidebar for categories/navigation, a 6-column center area for the product grid, and a 3-column right sidebar for the persistent "Current Order" receipt view.
- **Mobile:** A 4-column grid with 16px margins. The "Current Order" becomes a collapsible bottom sheet or a separate tab.
- **Spacing Rhythm:** All spacing is derived from a 4px baseline, with 24px (md) being the standard padding for cards and containers to maintain the "generous whitespace" requirement.

## Elevation & Depth

To achieve a professional yet vibrant look, this design system uses **Tonal Layers** combined with **Ambient Shadows**.

- **Level 0 (Base):** The background (#F8FAFC).
- **Level 1 (Cards/Containers):** Pure white surfaces with a very soft, diffused shadow (0px 4px 20px rgba(0, 0, 0, 0.05)).
- **Level 2 (Modals/Active States):** Elevated white surfaces with a more pronounced shadow and a 1px soft-grey outline to define boundaries.
- **Glassmorphism:** Used sparingly for top navigation bars or floating action buttons—using a background blur (12px) and 80% opacity white fill to create a sense of lightness and technical sophistication.

## Shapes

The shape language is defined by **Soft Roundedness**, communicating approachability and modern software standards.

- **Standard Elements:** Buttons, input fields, and small cards use a 0.5rem (8px) corner radius.
- **Large Containers:** Main product grids and the order summary panel use `rounded-lg` (1rem / 16px).
- **Interactive Feedback:** When an item is selected, it should utilize a 2px inner-border in the primary color, maintaining the corner radius of the parent element.

## Components

### Buttons
- **Primary:** Solid Electric Blue with white text. High-contrast, 16px padding on sides.
- **Success (Pay):** Solid Emerald Green. This is the largest button in the system, often spanning the full width of the order sidebar.
- **Ghost:** Transparent background with a 1px border of the primary color; used for secondary actions like "Add Note" or "Discount."

### Product Chips & Cards
- **Product Cards:** White background, `rounded-lg` corners. Image takes the top 60%, with price in Bold 18px text at the bottom right.
- **Category Chips:** Pill-shaped, using light tints of the primary/tertiary colors (e.g., 10% opacity purple background with 100% opacity purple text).

### Input Fields
- Understated but clear. 1px light grey border that shifts to a 2px Primary Blue border on focus. Labels are always visible in `label-sm` above the field.

### List Items (The Receipt)
- High density but clear. Use zebra-striping with a very faint grey (#F1F5F9) to distinguish between items. Swipe-to-delete gestures should be hinted at with a subtle shadow on the right edge.

### Status Indicators
- Use small, vibrant dots (pulses) or "pill" badges for stock levels: Green for "In Stock," Orange for "Low Stock," and Red for "Out of Stock."