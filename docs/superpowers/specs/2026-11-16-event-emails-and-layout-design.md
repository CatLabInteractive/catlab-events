# Event-specific emails, a template library and an editable mail layout

Date: 2026-11-16

## Intent

Admins can rewrite the transactional mails for their organisation (Settings > E-mails).
That is not enough for two reasons.

1. A specific event needs its own text. Today the only event-level choice is the
   `confirmation_email` field, which picks a Blade file that a developer wrote.
2. The wrapper around every mail (the layout) is a bare white card on a grey
   background. It has no logo, no header and no footer. It does not look like a
   proper email, and admins cannot change it.

## Goals

- An admin gives one event its own version of a mail, without a developer.
- An admin keeps named templates in a library and lets several events use the same one.
- Every mail goes out in a layout that looks like a proper email with no work from the admin.
- An admin adjusts the layout with a few settings, or replaces the layout HTML completely.

## Non-goals

- New mail types or new moments at which a mail is sent. A library template is
  always a variant of one of the existing types.
- A layout per event. The layout belongs to the organisation.
- A better rich-text editor. The existing `contenteditable` editor with the HTML toggle stays.
- Translations of one template in several languages.

## Anti-goals

- A mail that an admin did not touch must not change its text. Only its layout changes.
- An admin must not be able to save a layout that loses the mail content.
- No event silently loses a mail that it sends today through `confirmation_email`.

## How mails work today

- `App\Services\EmailTemplates` defines five types: `confirmation`,
  `confirmationPlayLink`, `cancellation`, `waitingListInvitation`, `groupInvite`.
- `email_templates` holds one row per organisation and type (unique on both).
  If that row exists, `render()` uses it. If not, `render()` uses the default Blade view.
- Custom content is HTML with `{{ placeholders }}`. `customContent.blade.php` wraps it
  in `resources/views/emails/layouts/layout.blade.php`. The default Blade views extend the same layout.
- `App\Listeners\SendEmail` sends the confirmation, cancellation and waiting list mails.
  `GroupMemberController` sends the team invitation.
- Organisations already have a logo, legal name, support address, website URL and social URLs.

## Approaches

### Event-specific content

1. Per-event override. The event holds its own subject and content per mail type.
   It is the direct answer to "this event needs this text". A second event that needs
   the same text gets a copy.
2. Template library. The organisation keeps named templates and an event picks one per
   mail type. Several events share one text. It needs two steps for a single special event.
3. Both. Per mail type an event uses the organisation version, a library template, or its own text.

Picked: 3. Thijs asked for both.

### Layout

1. A redesigned default layout that fills itself from organisation data, plus a few settings.
   Nobody can break it.
2. The same, plus an advanced editor that replaces the whole layout HTML. Full control, but
   hand-edited email HTML breaks easily in mail clients.

Picked: 2, which includes 1. Thijs asked for both.

## Design

Everything below this line is Claude's call unless it says otherwise. Thijs decided the
two "Picked" lines above.

### Data model

`email_templates` (existing) gains a `name` column, not null, default `''`.

- `name = ''` is the organisation version of that type (the rows that exist today).
- Any other `name` is a library template of that type.
- The unique key becomes `(organisation_id, type, name)`.

New table `event_emails`, one row per event and type, unique on `(event_id, type)`:

| column | meaning |
|---|---|
| `event_id` | the event |
| `type` | one of the four event mail types (not `groupInvite`) |
| `email_template_id` | nullable. A library template that this event uses |
| `subject`, `content` | nullable. The event's own text |

A row has either `email_template_id` or `subject` and `content`, never both. No row
means that the event follows the organisation.

New table `email_layouts`, one row per organisation (unique on `organisation_id`):
`accent_color` (hex, nullable), `footer_text` (text, nullable), `custom_html` (longText, nullable).

### Which version goes out

`EmailTemplates::render()` gets the event as an extra argument. The first match wins:

1. the event's own text (`event_emails.subject` and `content`)
2. the library template that the event picked
3. the organisation version
4. the default Blade view

The team invitation has no event, so it only uses steps 3 and 4.

The waiting list invitation that an admin edits before a single send keeps its behavior.
It starts from the version that the list above gives.

### Admin screens

Settings > E-mails gets three parts.

- Standaardmails: the list that exists today.
- Sjablonen: the library. Create a template (pick a type, give it a name), edit it with
  the existing editor and preview, delete it. The page shows which events use each template.
  A template that an event uses cannot be deleted. The page names those events.
- Layout: see below.

Each event gets a page `admin/events/{event}/emails`, linked from the same place as the
event's waiting list and guests pages. It lists the four event mail types and shows where
each one comes from. The edit page for one type has three choices:

```
Welke mail krijgt dit evenement?
( ) Standaard van de organisatie
( ) Sjabloon:  [ Thuisquiz bevestiging  v ]
(o) Eigen tekst voor dit evenement
    Onderwerp [ ...................................... ]
    Inhoud    [ editor, starts from the version in use  ]

[ Opslaan ]  [ Voorbeeld ]
```

The preview on an event page uses the real event (name, dates, venue) with a made-up
player, team and order. Only admins of the event's organisation can open these pages.
The pages use the same access rule as the other event admin pages.

### Layout

The new default layout, top to bottom:

```
+------------------------------------------+
|            [ organisation logo ]         |   name as text when there is no logo
+------------------------------------------+
|                                          |
|   mail content                           |   600px wide card, white, rounded
|   [  Button in accent color  ]           |
|                                          |
+------------------------------------------+
   footer text (optional, from settings)
   Legal name · support address · website
   Facebook · Instagram · ...               only the links that the organisation has
```

- Thijs approved the look of the mockup at https://claude.ai/artifact/9VzZiTAwzJM6NAfKn7aymz.
  Follow it. The logo is centered above the card, and the card has a 4px accent line on top.
  Body text is 15px. On a phone, the card fills the screen width.
- Table-based HTML with inline styles, so that it holds in Outlook and Gmail. It works on a phone.
- No remote images other than the organisation logo. The background image that the current
  layout loads over http from a third-party mail service goes away.
- Links and the button block (`emails.blocks.button`) use the accent color.
  The default accent color is the button color in use today.
- Every mail uses it: default Blade mails, custom content, and the waiting list mail that an admin edits.

Settings > E-mails > Layout has:

- accent color and footer text, with a live preview
- "Geavanceerd": an HTML editor for the whole layout. It starts from the default layout
  written with placeholders. Saving requires exactly one `{{ content }}`. A reset button
  returns to the default layout.

Layout placeholders: `{{ content }}`, `{{ layout.header }}` (logo or name block),
`{{ layout.footer }}` (footer block), `{{ layout.accent_color }}`, `{{ layout.footer_text }}`,
`{{ organisation.name }}`, `{{ organisation.logo_url }}`, `{{ organisation.website_url }}`,
`{{ organisation.support_email }}`. Text values are escaped. The blocks are trusted HTML that the application renders.

Layout HTML is admin-written, at the same trust level as custom mail content today.
Previews stay in the sandboxed iframe.

### Legacy `confirmation_email`

The field goes away. Remove it from `EventResourceDefinition` and from `SendEmail`, and
delete `play-at-home.blade.php`. Leave the column.

The production count on 2026-11-17 found 24 events with a value:

- Events 52 and 53 point to views that no longer exist. They already get the normal mail.
- 21 events point to `emails/tickets/confirmation`, the default view itself. Today the field
  wins over the organisation version, so these events never send an edited registration mail.
  Removing the field fixes that.
- Event 74 points to `play-at-home`. Its last date was 2024-12-02, so nothing is lost.

### Tests

Extend `tests/Integration/EmailTemplateTest.php` and add tests for:

- the resolution order, one case per step
- the library: create, edit, delete, delete refused while in use
- the event page: each of the three choices, and access refused for another organisation's admin
- the layout: default with and without a logo, settings applied, custom HTML applied,
  save refused without `{{ content }}`, reset
- every mail type still goes out with its content inside the layout

## Left to the builder

- How the Blade default views and the placeholder layout share one layout code path.
- The exact HTML and CSS of the default layout, within the sketch above.
  Show Thijs a rendered preview before the work is called done.
- Route names, controller split and view file names, in the style of the existing admin code.
- Where exactly the event's "E-mails" link sits in the event admin screens.
