# De Quizfabriek: four visual directions

**Status: none of these were chosen.** Kept for reference while a different direction is explored.

These directions replace the current event-page redesign. That redesign used a dark gradient hero, pill chips, rounded white cards, a pink accent and letter-spaced micro labels. The owner said it "looks very Claude-AI-built", so none of the four directions build on it.

Each direction has three mockups in `docs/design-directions/2026-11-17/<slug>/`:

- `event-wide.html` shows the event page with a 16:9 poster.
- `event-portrait.html` shows the event page with an A-series portrait poster.
- `rest-of-site.html` shows the agenda, the phone menu, the one-page register form, the thanks/ticket page and the footer.

Each HTML file has three screenshots: `*-desktop.jpg` (1440 wide, full page), `*-phone-fold.jpg` (iPhone 13, 390x664) and `*-phone-full.jpg`.

Checks on all 12 pages:

- No page scrolls horizontally on the phone.
- On every event page the price and "Bestellen" fit on the first phone screen:
  - Productielijn: 282 px
  - Antwoordknoppen: 309 px
  - Huiskamer: 506 px
  - Affiche: 389 px
- Posters always keep their full image, never cropped. As in the current `blocks/poster.blade.php`, the frame takes the poster's own ratio, limited to between 0.7 and 1.9 (`--r`). Each direction fills any leftover space with its own material instead of a blurred copy of the poster.

The HTML mockups open directly in a browser (they load Google Fonts and the demo posters in `assets/`). Screenshots are next to each mockup as JPEG.

---

## 1. Tone of voice (from quizfabriek.be and tickets.quizfabriek.be)

| # | Trait | Real copy | What it means visually |
|---|---|---|---|
| 1 | **Informal and direct (je/jij)** | "Daag je vrienden en familie uit", "Speel wanneer het jou past", "Wil je zelf presenteren, maar heb je goede quizvragen nodig?" | Labels should read like speech ("Kies je ticket", "Hoe heet je team?") rather than system words. Avoid shouty all-caps micro labels. |
| 2 | **Rhetorical questions** | "Klinkt te mooi om waar te zijn?", "Hoezo, quizzen kan alleen in een zaaltje?", "Help! Tante Margriet moet naar het toilet. Kan ik de quiz pauzeren?" | Headings can be questions, and the form steps can be quiz questions (direction 2 does this). |
| 3 | **Self-mocking, absurd humour** | "DOMME MOPJES VOORZIEN 🥁", "Hoelang kan Marcel aan een fazant denken?", "De CEO die absurde opdrachtjes moet uitvoeren", "Nee dat is niet verplicht!" | Leave room for winks: a stamp, a handwritten note, an aside. Use humour in the small places, never in the main buttons. |
| 4 | **Emoji as signposts** | 📺 🛋️ 🙋 📲 at the start of paragraphs, 🍺 "De quiz duurt ongeveer drie uur", ❤️ | Emoji are part of the content. Each direction gives them a deliberate slot: a box, an inline marker, or hidden behind a drop cap. |
| 5 | **Warm and low-threshold** | "van jong tot oud of van ervaren quizzer tot nieuweling", "er in elk van ons een goede quizzer verborgen zit", "quizliefhebbers en -twijfelaars" | Use warm paper-like backgrounds rather than cold white or dark tech. Use generous type sizes and nothing intimidating. |
| 6 | **Rhythm and repetition** | "Quizzen waar je maar wil, wanneer je maar wil, met wie je maar wil.", "terug van nooit echt weggeweest" | The copy is written to be read aloud. Give the lead paragraph room: a larger italic, or a drop cap. |
| 7 | **Made to measure, handmade** | "Een quiz die volledig op jullie maat geschreven is", "helemaal uniek", "Plezier gegarandeerd!" | Use print-shop textures (stamps, tape, paper, riso) and visible craft over glossy UI. |
| 8 | **Real people, a real living room** | "onze gezellige woonkamer waar Kim en Wietse je entertainen … met de nodige kwinkslag" | Name the people in the UI (credits as a colophon or a TV guide, "door Kim & Wietse"). |
| 9 | **Fun above facts** | "geen saaie aframmeling van feiten", "een interactieve, dynamische avond waar plezier centraal staat" | Every direction needs one bold, joyful move. Polite neutrality is exactly what reads as an AI template. |

The copy barely uses the "fabriek" metaphor itself. It is in the name, but not in the sentences. Direction 1 builds on that metaphor, which makes it a brand extension rather than a reflection of the current tone.

---

## 2. Directions

### A. Productielijn (`productielijn/`)

**Concept.** Every quiz is a product coming off the line at the factory. The event page is a shipping crate in kraft cardboard. The poster is taped to it as a printed sheet. The price and "Bestellen" sit on a shipping label with a lot number. A red rubber stamp says "Goedgekeurd door Kim & Wietse". The tone is joking and literal: the quizpakket arrives in a crate, and "Goed om te weten" becomes *de bijsluiter* (the package insert that comes with medicine).

**System**
- **Type:** Big Shoulders Stencil Display 900 (titles, prices; crate stencil), Archivo 400–800 (body, buttons), IBM Plex Mono 400–600 (labels, lot numbers, meta).
- **Palette:**
  - paper `#f4efe4`, kraft `#c9a36a`, ink `#1d1a16`
  - label yellow `#f2c230` (actions), stamp red `#c8283a`, green `#2e8b4a` (links/OK)
  - logo green `#78be5a` stays untouched on the black top bar
- **Motifs:** kraft texture (SVG noise plus fine fibres), a thin yellow/black hazard stripe under the header and above the footer, masking tape, a rubber stamp with a grunge mask, a stencilled watermark word in the hero, ticket stubs with a perforated edge, a timecard for the credits, and a barcode.
- **Button:** solid yellow, 2 px ink border and a hard 4 px offset shadow. It "presses in" on hover. The secondary button is ink with a yellow shadow.
- **Cards:** ticket stubs (white body, dashed tear line, yellow price stub).
- **Inputs:** underlined fields on a "bestelbon" (order form), with mono labels and a yellow highlight on focus.
- **Posters:** a white printed sheet with a 12 px margin, taped onto kraft and tilted by 1°. The window takes the poster's ratio. Leftover space shows as paper. On phones the shipping label comes first (CTA at 282 px) and the poster follows.

**Rest of the site**
- The agenda is a shipping manifest: stencil date block, poster in a kraft thumbnail box, red or green stamp ("Laatste 6 tickets!", "Uitverkocht!").
- The register form is a *bestelbon* with numbered stencil steps.
- The thanks page is a ticket with a perforation, a barcode and a "Bevestigd" stamp.
- The phone menu is a black workshop board.

**Effort (Bootstrap 3 + SCSS):** medium. The markup maps one to one onto the existing `ev-*`, `rf-*` and `order-ticket` classes. The work is about 600 lines of SCSS and 3 Google Fonts, plus SVG textures as data URIs. No template logic changes. The stamp and tape are pure CSS.

**Risks**
- Stencil type is hard to read for long titles ("Quiz in het Wintercircus - editie 3"). The title is auto-fitted with container query units (`cqw`), which need Safari 16 or later.
- The heavy borders and offset shadows are close to the "neo-brutalist" trend.
- It leans on a metaphor the copy itself barely uses.
- Kraft brown next to dark-red posters can look muddy.

### B. Antwoordknoppen (`antwoordknoppen/`) — recommended

**Concept.** Everything on the site is an answer button. The four colours of the Woonkamerquiz app (red/green/orange/blue) are already on every poster. Here they become the whole system:
- Each series owns one colour.
- The order button is the "correct answer", in green.
- The ticket options are answers A and B.
- The register form asks three questions ("Vraag 1 van 3: Welk ticket wordt het?").
- The thanks page says "Juist antwoord!"

This is the copy's question-and-answer style turned into interface. There are no outlines, no shadows and no rounded cards: flat colour fields and big condensed type, like 80s TV-quiz graphics.

**System**
- **Type:** Bricolage Grotesque, a variable font. Width 75 and weight 800 for display (condensed, a bit quirky); width 100 and weight 400–700 for body. One family, many voices.
- **Palette:**
  - Background `#f3eee6`, ink `#1b1a1f`, muted `#55525c`, hairline `#d9d1c4`.
  - Answer colours: A `#c8283a`, B `#2e8b4a`, C `#c8822a`, D `#2a6eaa`.
  - Logo `#78be5a` on the light background.
- **Motifs:**
  - A four-colour ribbon at the top, below the hero and above the footer.
  - Square letter badges (A/B/C/D, ✓, ?).
  - A giant faded series initial on the stage behind the poster.
  - Short coloured dashes cycling through the four colours in "Goed om te weten".
- **Buttons:** full-width flat answer tiles (letter badge · label · price), with a 6 px slide on hover. The secondary button is a 3 px ink outline with a "?" badge.
- **Cards:** none. The "Goed om te weten" facts form a hairline grid, and the credits are a scoreboard list.
- **Inputs:** white fields with a 4 px ink bottom bar that turns blue on focus, large text (22 px).
- **Posters:** they sit on a full-bleed "stage" in the series colour. On desktop the stage runs to the right edge of the viewport. The frame takes the poster's ratio (portrait posters get height-limited to 70vh, with the column order flipped). Leftover space is flat series colour, which works with any poster. On phones the title and green answer tile come first (CTA at 309 px).

**Rest of the site**
- The agenda is a grid of colour stages, each poster contained, with a date in condensed display type.
- The phone menu is a stack of A/B/C/D answer tiles.
- The register form asks 3 questions with answer tiles. Unselected tiles are dimmed, and the selected one gets a thick outline.
- The thanks page is a green "Juist antwoord!" panel next to the poster on a red stage.

**Effort:** low to medium.
- About 450 lines of SCSS and 1 Google Font.
- One template addition: a colour per series, either a new `series.color` column (upgrade SQL plus schema.sql) or derived as `series.id % 4`.
- The existing hero, CTA, poster partial and register form keep their structure, so this is mostly a reskin plus the question-style headings in `registerOnePage.blade.php`.

**Risks**
- Four saturated colours need discipline: only one colour field per view plus the ribbon, or it turns into a children's site.
- Red and green carry meaning (wrong/right). The green CTA works for that reason, but red must never be used for an error-free action.
- White text on orange `#c8822a` is about 3:1. That is fine for 19 px bold or larger, but body text must not sit on it.
- It is closely tied to the Woonkamerquiz app colours. That is a strength if the app stays.

### C. Huiskamer (`huiskamer-riso/`)

**Concept.** Welcome to the living room. The hero is a 70s wallpaper wall with a skirting board. The poster hangs on a nail in a wooden frame with a passe-partout. A handwritten note says "klik, dan hangt hij groter". Everything is printed as a two-colour risograph: orange and teal on warm paper, with grain and slight misregistration (the teal "shadow" behind the orange button). Other details:
- The agenda reads like a TV guide ("Vanavond op De Quizfabriek").
- The credits are "Op de aftiteling".
- The thanks page is a postcard: "Groeten uit de woonkamer!", signed by Kim & Wietse.

**System**
- **Type:** Fraunces (SOFT 100, WONK 1, weights 400–900, italics for warmth), Work Sans 400–700 (body/UI), Caveat 600–700 (only for handwritten asides).
- **Palette:**
  - Paper `#f5ecd9` and `#efe2c6`, ink (riso navy) `#2b2a4c`, muted `#5d5873`.
  - Riso orange `#ff6c2f`, riso teal `#00838a`, mustard `#e8b04b`, wood `#7a4b2a`.
- **Motifs:** wallpaper SVG pattern, grain overlay, squiggle underlines, hand-drawn checkmarks, gift-tag ticket options with a punched hole, postcard stamp and postmark.
- **Button:** an orange pill with a 4 px teal offset (overprint effect). The alternate button is teal with an orange offset.
- **Cards:** gift tags and a postcard. No generic cards.
- **Inputs:** cream fields with a 2 px ink border, irregular radius (14/4 px) and a teal offset on focus.
- **Posters:** a wooden frame with a mat. The mat takes the poster's ratio and leftover space is mat.
  - Desktop: frame on the left, title on the right.
  - Phone: the frame comes first but is height-limited to 172 px, so a portrait poster becomes a small framed picture and the CTA still lands at 506 px.

**Rest of the site**
- The agenda is a TV-guide listing with tiny framed thumbnails and handwritten badges.
- The phone menu is wallpaper with paper strips.
- The register form uses teal blob numbers, radio options as rounded cards and an orange pay pill.
- The thanks page is a postcard.

**Effort:** medium to high.
- About 650 lines of SCSS and 3 fonts (Fraunces variable is heavy, around 150 KB), plus SVG assets.
- The frame and wallpaper are pure CSS, but there are many decorative details to keep consistent across 20 or more Blade views.

**Risks**
- It can slide into twee or kitsch. The handwritten font has to stay rare.
- It is strongly tied to the Woonkamerquiz. For a pub quiz in Café Sekuurne the living-room metaphor is weaker, although "quizavond in je stamcafé" would be a natural variant.
- The grain overlay costs some paint performance on low-end phones (you can drop it on mobile).
- The poster gets smaller on phones than in the other directions.

### D. Affiche (`affiche/`)

**Concept.** A Flemish café or kermis poster meets a local newspaper. Wood-type headlines, hairline rules and newspaper columns, in black plus one spot colour (vermilion) on newsprint. Details:
- The title is set like a poster ("WOONKAMER" in black, "QUIZ" in red).
- Prices are a rate card with dotted leaders ("Tarieven").
- The credits are a *colofon*.
- The register form is a newspaper coupon with "✂ knip hier".
- The confirmation is a front-page headline ("Je speelt de Woonkamerquiz").

The site turns quiet and typographic so the posters, which are already loud, can carry the colour.

**System**
- **Type:** Anton (wood-type display), Alfa Slab One (numbers: prices, dates, list numerals), Newsreader 400–700 plus italics (body, captions; an editorial serif).
- **Palette:** newsprint `#efebe0`, ink `#141414`, muted `#4b4740`, spot vermilion `#e0371f`, success green `#2e8b4a` (confirmation only), plate white `#f7f4ec`, logo green `#78be5a`.
- **Motifs:** newspaper masthead with a strapline ("Gent, sinds 2015"), 1 px and 4 px rules, column rules, a drop cap, numbered boxes, dotted price leaders, a caption under the poster ("AFFICHE · Woonkamerquiz, liggend"), and a giant wordmark in the footer.
- **Button:** a solid ink bar with an uppercase Anton label (no letter-spacing) and a serif arrow. It turns red on hover. The price sits in a ruled cell glued to the button.
- **Cards:** none. Everything is ruled columns and boxes.
- **Inputs:** a newspaper form line (2 px underline), italic serif labels, and a red underline with a faint red tint on focus.
- **Posters:** a "plate": 1 px ink border, 10 px off-white margin, ratio-sized, with a caption below that names the format (liggend/staand). Portrait posters are limited to 620 px high and the columns swap. On phones the title, strapline and price/Bestellen bar come first (CTA at 389 px).

**Rest of the site**
- The agenda is a poster wall: a huge slab date, Anton title, italic venue, red "UITVERKOCHT!" flag, and the poster at the far right behind a rule.
- The phone menu is a ruled list in big Anton.
- The register form is the coupon.
- The thanks page is the front-page headline plus a three-column "wat nu?".

**Effort:** low. It is the simplest CSS of the four (about 400 lines, no textures, no images) and 3 small fonts. It maps cleanly onto the Bootstrap grid with column rules.

**Risks**
- Splitting the title into two colours ("WOONKAMER / QUIZ") is a manual choice. For arbitrary titles, use one colour, or colour only the last word with a heuristic.
- Uppercase Anton titles get very long for names like "Ludieke live gestreamde quiz: De juwelen van de barones" and need `clamp`/`cqw` sizing.
- It is the least "playful" direction. The humour has to come from the copy (coupon, colofon) rather than the visuals.
- Done badly it could look like a news site.

---

## 3. Comparison

| | Productielijn | Antwoordknoppen | Huiskamer | Affiche |
|---|---|---|---|---|
| Roots in the copy and product | name (fabriek) | product (app colours) and question tone | Woonkamerquiz copy | Flemish print culture |
| Works for every series | yes | yes (one colour per series) | weaker for café quizzes | yes |
| Posters of any ratio | sheet on kraft | colour stage | frame and mat | plate and caption |
| Phone CTA top (px of 664) | 282 | 309 | 506 | 389 |
| Implementation effort | medium | low to medium | medium to high | low |
| Main risk | brutalist cliché, stencil legibility | too colourful without discipline | kitsch | too serious |

## 4. Recommendation

**Go with B, Antwoordknoppen.** It is the only direction whose visual system comes from the product itself. The four answer colours are already on every poster and in the Woonkamerquiz app. It also turns the house tone of rhetorical questions into interaction ("Vraag 1 van 3: Welk ticket wordt het?", "Juist antwoord!"), so it reads as De Quizfabriek rather than as a theme.

It handles any poster, because the stage is a flat field in the series colour. Colour-coding the series also gives the agenda and the navigation real structure. It is cheap to build: one font, one optional `series.color` field, and a reskin of the existing `ev-*` and `rf-*` markup.

Borrow two things from **D, Affiche**:
- the restraint in the body (rules instead of boxes, a serif-like calm for long descriptions)
- the coupon and colofon wording

Keep **C, Huiskamer** in reserve for Woonkamerquiz campaign pages or the quizpakket landing page, where the living-room story is literal.
