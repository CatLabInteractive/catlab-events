# De Quizfabriek: round 2, three abstract directions

The owner turned down round 1 as "definitely too themed. We need something abstract, something clean." Earlier he turned down the first redesign as "very Claude-AI-built". So these three directions use no metaphors, no props, no textures and no faux-print effects. Each one has the same three parts:

- a strict layout grid
- one precise type choice
- one abstract motif taken from quizzing itself, used sparingly

The tone of voice from round 1 still applies (informal "je", rhetorical questions, emoji paragraphs, warmth). Here it lives in the copy and in a few small touches, not in illustration.

Each direction lives in `round2/<slug>/` and has:

- **Mockups:** `event-wide.html`, `event-portrait.html` and `rest-of-site.html`.
- **Screenshots:** for each mockup, `-desktop.jpg` (1440 wide, full page), `-phone-fold.jpg` (iPhone 13, 390x664) and `-phone-full.jpg`.

Checks on all 9 pages:

- **No horizontal scroll** on the phone (I also checked that the layout viewport stays at 390).
- **Price and Bestellen above the fold** on the phone. Bottom of the button:

  | Direction | CTA bottom (px of 664) |
  |---|---|
  | Raster | 297 |
  | Kleurveld | 481 |
  | Punt | 259 |

- **Posters are never cropped.** They are shown at their own ratio. Agenda thumbnails use `object-fit: contain` inside a fixed box.

Things all three avoid:

- pill chips
- rounded cards with shadows
- letter-spaced uppercase labels
- gradients
- dark heroes
- a centred layout

The green shield logo sits unchanged on a light background in each one.

---

## 1. Raster: Swiss, white, strict 12-column grid (`round2/raster/`)

**Concept.** International Typographic Style. The page is a visible 12-column grid with hairline rules. The title is set as large as the grid allows, flush left. There is one working colour, blue, for action. Red appears only for scarcity ("Laatste 6 tickets", "Uitverkocht").

**Motif.** A 2x2 "quadrant" mark: four answer squares, one of them chosen in blue. It is used in three places only:

- as the series marker in the breadcrumb
- as the menu icon
- large in the footer

**System**
- **Type:** Schibsted Grotesk.
  - Display: weight 800, tracking −0.045em. On desktop it scales with the width up to about 150px; on the phone it is sized to fit the screen.
  - Body: weight 400–500, 18px.
  - Section numbers (01–04): tabular figures.
- **Colours:**

  | Role | Hex |
  |---|---|
  | Background | `#ffffff` |
  | Ink | `#111214` |
  | Muted | `#6b6c70` |
  | Hairline | `#dcdcdc` |
  | Light fill | `#f3f3f1` |
  | Blue (action) | `#1f5fbf` |
  | Red (status only) | `#d7262e` |
  | Logo green | `#78be5a` |

- **Layout:** each section has a label in columns 1–3 and its content in columns 4–12, with a 1px ink rule above.
  - In the hero, the info block sits in columns 1–4 and the poster in columns 6–12.
  - For a portrait poster, the info takes columns 1–5 and the poster columns 8–12.
- **Button:** a square blue block, 60px high, label on the left and arrow on the right. It turns ink on hover.
- **Ticket options:** columns topped by a 4px ink rule, with the price in display type.
- **Inputs:** 58px fields with a 1px ink border, square corners and a 2px blue inner border on focus.
- **Choices:** square checkboxes that fill blue when selected.
- **Posters:** the image at its natural ratio inside its grid columns, on white, with no frame. Portrait posters get narrower columns. Agenda thumbnails sit in a 16:10 light-grey box.

**Rest of the site**
- The agenda is a row-based grid: big tabular date (28.11), title, venue, a status square, thumbnail.
- The phone menu is a full-screen list at 30px with "3 gepland" counts.
- The register form has a 3-segment progress bar and a sticky order summary.
- The thanks page has a large headline, "Veel plezier met de *Woonkamerquiz.*", with "Woonkamerquiz." in blue.

**Effort:** low. It needs about 350 lines of SCSS and one font. It maps naturally onto the Bootstrap 3 grid: `col-md-3` label, `col-md-9` content.

**Risks**
- It is the most "expected" of the three.
- It relies on consistent rhythm; sloppy spacing in other views shows at once.
- The blue must stay the only action colour.

## 2. Kleurveld: calm colour fields (`round2/kleurveld/`)

**Concept.** The poster is the main image. It sits on a flat field of soft colour with one or two large circles, like a Bauhaus or Matisse cut-out reduced to geometry. The four answer colours are softened into a calm palette. Each series owns one, which colours the hero field, the dot in the navigation and the agenda tiles. Everything else is off-white with a dark navy ink. It is the warmest and most "friendly" of the three without getting playful.

**Motif.** Circles: one filled circle per series, plus large circle segments that cut into the fields. A row of four small dots is the brand signature (menu, footer).

**System**
- **Type:** Jost, a geometric sans close to Futura.
  - Display: weight 500, −0.025em, up to 84px.
  - Body: weight 400, 18px.
  - No bold display weights and no all-caps.
- **Colours:**

  | Role | Hex |
  |---|---|
  | Background | `#efeeea` |
  | Ink | `#1e2430` |
  | Muted | `#646a76` |
  | Hairline | `#d7d4cd` |
  | Card | `#fbfaf8` |
  | Soft red | `#e27d74` |
  | Soft green | `#8cbf98` |
  | Soft orange | `#efb661` |
  | Soft blue | `#7ea6d8` |

- **Hero:** split into a 7/5 grid. The left part is a full-bleed colour field with a large orange circle segment and a white disc behind the poster. The right part holds the title and the buy block.
- **Button:** an ink rectangle, 62px, with the label on the left and a series-colour disc holding the arrow on the right.
- **Ticket options:** two flat colour fields (blue and orange), each with a circle that grows on hover and a large price.
- **"Goed om te weten":** a full-width soft-green band with white dots as bullets.
- **Inputs:** a light fill with a 2px ink bottom line that turns blue on focus.
- **Choices:** circular radios. The selected option's row fills soft blue.
- **Posters:** contained inside the colour field at their own ratio, with a light drop shadow.
  - Desktop: the field is up to 620px high.
  - Phone: the field is 220px, with the poster limited to 180px high, so the title and the CTA still fit above the fold.
  - The leftover space is simply field colour. That works for any poster ratio, because the field is decoration, not a frame.

**Rest of the site**
- The agenda is a two-column list: a series-colour square holding the contained poster, then date, title and a status dot.
- The phone menu is a navy panel with a circle segment and coloured dots per series.
- The register form is a white panel, with an order summary on a red field beside it.
- The thanks page is a soft-green field: "Veel plezier, De Kwinkslagers." Below it are three "Wat nu?" tiles.

**Effort:** low to medium. It needs about 450 lines of SCSS and one font. It also needs a colour per series: a new `series.color` column (upgrade SQL plus `schema.sql`), or `id % 4`. The circles are plain CSS pseudo-elements.

**Risks**
- With dark posters on the soft fields, the shapes need restraint: at most two per field.
- Off-white plus soft colours could slide towards "pastel startup" if the shapes multiply.
- White text does not work on these soft colours, so text on them is always ink.

## 3. Punt: monochrome plus one green, typographic (`round2/punt/`)

**Concept.** Almost only type and white space. There is one colour, green, the brand green from the logo in a deeper tone. The event page is a two-column composition:

- A sticky left rail works as an order panel: series, title, the ticket choices as radio dots, price and Bestellen.
- The right column holds the poster and the content, each section with a small monospaced lowercase label.

Everything quiz-like is said with dots.

**Motif.** Three uses of dots:

- **Question-mark matrix:** a 5x7 dot matrix that draws a question mark in green on grey dots. It appears on the event rail, the agenda header, the phone menu and the footer.
- **Progress rows:** rows of dots (●●○) used as progress in the register steps, as the "Goed om te weten" bullets (one more dot filled per item) and in the thanks steps.
- **Check matrix:** a dot-matrix check mark on the thanks page.

**System**
- **Type:** Geist and Geist Mono.
  - Display: Geist weight 600, −0.035em, 64px.
  - Body: Geist weight 400, 17px.
  - Labels and figures: Geist Mono 13px, lowercase, no tracking.
- **Colours:**

  | Role | Hex |
  |---|---|
  | Background | `#ffffff` |
  | Ink | `#0f1110` |
  | Muted | `#6e716f` |
  | Inactive dot | `#dadcda` |
  | Hairline | `#e8e9e7` |
  | Green (only colour) | `#1f8a4c` |

  The logo green `#78be5a` stays in the logo.
- **Layout:** 4/8 columns with an 80px gap. The rail is sticky on desktop.
- **Button:** a green rectangle, 56px, with four small dots on the right where the last one is lit, as if the fourth answer is chosen. It turns ink on hover.
- **Ticket options:** rows in the rail with a 12px radio dot and the price in mono.
- **Inputs:** a single 1px ink underline with a mono label, becoming a 2px green underline on focus.
- **Status:**
  - filled green dot: tickets available
  - ink dot: almost sold out
  - ring: sold out
- **Posters:** the image at its natural ratio in the 8-column content area, with no box. Portrait posters are limited to 820px high. A mono caption gives the pixel size. On the phone the rail comes first (CTA at 259px) and the poster follows.

**Rest of the site**
- The agenda is a hairline list: mono date and time, title, venue, a status dot, and a small contained thumbnail on the right.
- The phone menu is an outlined panel with large type and the question-mark matrix.
- The register form uses the same rail-and-content rhythm with ●●○ progress.
- The thanks page shows the check-mark matrix and "Bedankt."

**Effort:** low. It needs about 350 lines of SCSS and two small fonts. The dot matrices are static HTML spans, which can live in a Blade partial (`blocks/dotmatrix`).

**Risks**
- It is the most reserved of the three. The warmth has to come from the copy and the posters.
- Mono labels must stay short and lowercase, or the page gets technical.
- The question-mark matrix only reads at 8px dots or larger.
- The sticky rail needs a sensible fallback for very long titles; they wrap, and the title is sized to fit.

---

## Comparison

| | Raster | Kleurveld | Punt |
|---|---|---|---|
| Character | confident, editorial, Swiss | calm, warm, poster-led | quiet, precise, typographic |
| Colour | white, blue, red only for status | soft four-colour fields, one per series | white, one green |
| Motif | 2x2 quadrant (answers) | circles and four dots | dot matrix "?", progress dots |
| Poster role | an image in the grid | the hero image on a colour field | an image in the content column |
| Phone CTA bottom (px of 664) | 297 | 481 | 259 |
| Effort | low | low to medium (series colour) | low |

## Recommendation

**Punt**, with Kleurveld as the alternative if the owner wants more colour. Punt is the cleanest answer to "abstract, clean", and it is the hardest to mistake for a template. It also does the job best:

- On desktop, the sticky order rail keeps price and Bestellen visible all the way down the page.
- On the phone it gives the highest CTA of the three.
- Its single green ties directly to the logo, so the brand mark finally belongs to the page.
- The dot motif says "quiz" (answer options, progress, a question mark) without illustrating anything.

If he finds Punt too austere, Kleurveld keeps the same cleanliness but adds the answer colours back as calm fields.
