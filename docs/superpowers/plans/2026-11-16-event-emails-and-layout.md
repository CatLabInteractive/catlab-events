# Event Emails, Template Library and Mail Layout Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** An admin gives one event its own mail text or a shared library template. Every mail goes out in a new layout that the admin can adjust or replace.

**Architecture:** `EmailTemplates::resolve()` picks which version of a mail goes out (event text, library template, legacy view, organisation version, default). Every mail body becomes "content HTML", and a new `EmailLayouts` service wraps that content in one layout: the default HTML file with placeholders, or the organisation's own HTML. The Blade layout `emails/layouts/layout.blade.php` stops being a real layout. The Blade default views keep `@section('content')`, and the code reads that section.

**Tech Stack:** Laravel 5-era PHP 8.5, Blade, Charon admin, PHPUnit integration tests on MySQL.

**Spec:** `docs/superpowers/specs/2026-11-16-event-emails-and-layout-design.md`

## Global Constraints

- Copy shown to admins is Dutch. The labels in the spec are exact: "Standaardmails", "Sjablonen", "Layout", "Standaard van de organisatie", "Sjabloon", "Eigen tekst voor dit evenement", "Oude vaste mail", "Oude vaste mail verwijderen", "Geavanceerd".
- A mail that an admin did not touch keeps its text. Only the layout around it changes.
- Default accent color: `#4cb050`.
- Layout look, from the approved mockup at https://claude.ai/artifact/9VzZiTAwzJM6NAfKn7aymz:
  - page: background `#eef0f3`, logo centered above the card
  - card: 600px, white, radius 10px, `border-top: 4px solid <accent>`, padding 40px 44px
  - text: Arial 15px / 1.6 `#3d4451`, h2 24px bold `#1d2330`
  - footer: 12px `#5d6676`, centered
  - under 640px: card 100% wide, radius 0, padding 28px 20px
- No remote image in the layout other than the organisation logo. The `img.stat-pulse.com` background goes away.
- Every placeholder value is escaped, except values that the application renders as `HtmlString`.
- Admin-written HTML is previewed only in the sandboxed iframe, with the `Content-Security-Policy: sandbox` header that `EmailTemplateController::preview()` sets today.
- Only admins of the owning organisation (`Organisation::isAdmin()`) can open the new pages. Others get 404.
- Integration tests: `docker compose up -d mysql-db`, then `docker compose run --rm webserver vendor/bin/phpunit -c phpunit.integration.xml --filter <Test>`. If the local image does not build, push the branch and use the GitHub Actions "Tests" run. Unit tests run locally with `vendor/bin/phpunit --testsuite Unit`.
- Before every commit, `git branch --show-current` must print `feature/event-emails-and-layout`.

## Review Focus

- An admin posts the id of a library template from another organisation, or of another mail type, on the event page. Expected: a validation error, nothing saved. Test in Task 5.
- An event is deleted (soft delete) or force-deleted while it has `event_emails` rows. Expected: no FK error. Library delete counts only events that are not soft-deleted. Test in Task 4.
- An organisation has no logo, no website and no social links. Expected: the name in the header, and no empty footer lines or loose separators. Test in Task 2.
- A custom layout writes `{{content}}`, `{{  content  }}` or two `{{ content }}`. Expected: spacing does not matter, and two or zero placeholders are refused. Test in Task 6.
- A team invitation for an organisation with a custom layout. Expected: the custom layout, because the layout does not depend on an event. Test in Task 2.

---

### Task 0: Count legacy `confirmation_email` events on production

This decides Task 7. No code.

- [ ] **Step 1: Ask Thijs to run the count** (the auto-mode classifier blocks `dokku enter`, so Thijs runs it with `!`):

```bash
ssh dokku@fra001.apps.catlab.eu enter catlab-events web php artisan tinker --execute="echo App\Models\Event::withTrashed()->whereNotNull('confirmation_email')->where('confirmation_email','<>','')->pluck('confirmation_email','id')->toJson();"
```

- [ ] **Step 2: Write the result into Task 7.** `{}` means Task 7A. Anything else means Task 7B, and the list goes into the PR description.

---

### Task 1: Data model

**Files:**
- Create: `database/migrations/2026_11_16_100000_add_name_to_email_templates.php`
- Create: `database/migrations/2026_11_16_100100_create_event_emails.php`
- Create: `database/migrations/2026_11_16_100200_create_email_layouts.php`
- Create: `app/Models/EventEmail.php`, `app/Models/EmailLayout.php`
- Modify: `app/Models/EmailTemplate.php`, `app/Models/Organisation.php`, `app/Models/Event.php`
- Modify: `app/Services/EmailTemplates.php` (`getOverride`), `app/Http/Controllers/Admin/EmailTemplateController.php` (`index`, `update`, `reset`)
- Test: `tests/Integration/EmailTemplateTest.php`

**Interfaces:**
- Produces:
  - `email_templates.name` string(100), not null, default `''`. Unique key `(organisation_id, type, name)` replaces `(organisation_id, type)`. Down migration restores the old key.
  - `event_emails` columns:
    - `id`, timestamps, unique `(event_id, type)`
    - `event_id`: FK `events`, cascade on delete
    - `type`: string(64)
    - `email_template_id`: nullable, FK `email_templates`, restrict on delete
    - `subject`: string, nullable. `content`: longText, nullable
  - `email_layouts`: `id`, `organisation_id` (FK, unique), `accent_color` string(7) nullable, `footer_text` text nullable, `custom_html` longText nullable, timestamps.
  - `EmailTemplate` scopes `scopeOrganisationVersion($q)` (`name = ''`) and `scopeLibrary($q)` (`name <> ''`), relation `eventEmails(): HasMany`. Add `name` to `$fillable`.
  - `EventEmail` (fillable `type`, `email_template_id`, `subject`, `content`), relations `event(): BelongsTo`, `emailTemplate(): BelongsTo`, method `hasOwnText(): bool` (`subject !== null`).
  - `EmailLayout` (fillable `accent_color`, `footer_text`, `custom_html`), relation `organisation()`.
  - `Organisation::emailLayout(): HasOne`. `Event::emails(): HasMany` to `EventEmail`.

- [ ] **Step 1: Write the failing test** `testALibraryTemplateIsNotTheOrganisationVersion` in `EmailTemplateTest`: create `$this->organisation->emailTemplates()->create(['type' => CONFIRMATION, 'name' => 'Thuisquiz', 'subject' => 'X', 'content' => '<p>X</p>'])`.
  - Assert that `getOverride($this->organisation, CONFIRMATION)` is null.
  - Assert that `/admin/emails` shows "Standaard" in the row of that mail.
  - Reset that mail. Assert `EmailTemplate::library()->count() === 1`.
- [ ] **Step 2: Run it.** Expected: FAIL, unknown column `name`.
- [ ] **Step 3: Add the migrations and models.** In `getOverride()`, `index()`, `update()` (the `updateOrCreate` key gets `'name' => ''`) and `reset()`, add the `organisationVersion()` scope.
- [ ] **Step 4: Run `EmailTemplateTest`.** Expected: all PASS.
- [ ] **Step 5: Commit** "Store library templates, event mails and the mail layout".

---

### Task 2: One layout for every mail

**Files:**
- Create: `app/Services/Placeholders.php`, `app/Services/EmailLayouts.php`
- Create: `resources/views/emails/editable/layout.html` (the default layout, written with placeholders)
- Create: `resources/views/emails/blocks/layoutHeader.blade.php`, `resources/views/emails/blocks/layoutFooter.blade.php`
- Modify: `app/Services/EmailTemplates.php`, `resources/views/emails/blocks/button.blade.php`, `app/Listeners/SendEmail.php` (`sendWaitingListInvitationEmail`), `resources/views/emails/layouts/layout.blade.php`
- Delete: `resources/views/emails/tickets/customContent.blade.php`
- Test: `tests/Integration/EmailLayoutTest.php` (new)

**Interfaces:**
- Produces:
  - `Placeholders::fillHtml(string $template, array $values): string` and `Placeholders::fillText(string $template, array $values): string`. Values are `string|HtmlString`. Pattern is today's `PLACEHOLDER_PATTERN`. Unknown keys stay as they are. `fillHtml` escapes strings and keeps `HtmlString` raw. `fillText` strips tags from `HtmlString` and keeps strings as they are. `EmailTemplates::fill()` is replaced by these.
  - `EmailLayouts::wrap(?Organisation $organisation, string $content, ?EmailLayout $draft = null): string` returns the full HTML document. It uses `$draft`, else the stored layout, else the default. If `custom_html` is not empty, it uses `custom_html`. If not, it uses `getDefaultHtml()`.
  - `EmailLayouts::getAccentColor(?Organisation $organisation, ?EmailLayout $draft = null): string` returns the draft or stored `accent_color`, else `#4cb050`.
  - `EmailLayouts::getDefaultHtml(): string` reads `layout.html`.
  - `EmailLayouts::getPlaceholders(): array` gives placeholder => Dutch description, for the keys in the spec: `content`, `layout.header`, `layout.footer`, `layout.accent_color`, `layout.footer_text`, `organisation.name`, `organisation.logo_url`, `organisation.website_url`, `organisation.support_email`.
  - `EmailTemplates::render(?Organisation $organisation, $type, array $attributes, ?EmailTemplate $template = null, ?EmailLayout $layout = null)`. The return shape stays `['subject' => string, 'body' => string]`. The body is always `EmailLayouts::wrap()`. For the Blade default it uses `View::make($view, $attributes)->renderSections()['content']`. `$attributes['accentColor']` is set from `getAccentColor()` before any view renders.
  - `button.blade.php` uses `$accentColor ?? '#4cb050'` for both backgrounds. `EmailTemplates::renderButton()` passes it.
  - `layout.blade.php` keeps only `@yield('content')`. The Blade views still extend it, and nothing renders it as a whole any more.

- [ ] **Step 1: Write the failing tests** in `EmailLayoutTest` (use `CreatesEventFixtures`, the admin setup from `EmailTemplateTest`, and the GET preview route `/admin/emails/{type}/preview`):
  - `testTheDefaultLayoutShowsTheLogoAboveTheCard`: give the organisation a logo asset. Assert the body contains the logo URL, `border-top:4px solid #4cb050`, and `width="600"`. Assert it does not contain `stat-pulse.com`.
  - `testWithoutALogoTheHeaderShowsTheName`: the body contains the organisation name inside the header block, and no `<img`.
  - `testTheFooterShowsOnlyWhatTheOrganisationFilledIn`: set `support_email` and `facebook_url` only. Assert both appear, "Instagram" does not, and the footer has no `&middot;` next to an empty value (assert the substring `&middot; </p>` is absent).
  - `testTheFooterTextAndAccentColorApply`: create `EmailLayout` with `accent_color` `#7b3fbf` and `footer_text` `Vragen?\n<b>Mail ons</b>`. The waiting list preview contains `#7b3fbf` in the button and the top line, `Vragen?<br`, and `&lt;b&gt;Mail ons`.
  - `testEveryMailTypeGoesOutInsideTheLayout`: each of the five previews contains `data-mail-header`. The `confirmation` preview still contains "We zijn er bij!".
  - `testTheTeamInvitationUsesTheCustomLayout`: store `custom_html` `<div id="eigen">{{ content }}</div>`. The `groupInvite` preview contains `<div id="eigen">` and "Accepteren".
- [ ] **Step 2: Run them.** Expected: FAIL.
- [ ] **Step 3: Implement `Placeholders`, `EmailLayouts`, `layout.html` and the two blocks**, following the layout values in Global Constraints. The header block renders `<img>` with the logo URL (`$organisation->logo->getUrl()`, width 220, `max-width:100%`), or the name in the accent color. Put `data-mail-header` on its `<td>`. The footer block renders three parts in this order:
  1. the footer text, escaped, with `nl2br`
  2. one line of `getLegalName()`, `support_email` (mailto link) and `website_url`
  3. one line of `getSocialLinks()`

  Lines 2 and 3 join their values with ` &middot; ` and skip empty values. A line with no values is left out. `layout.html` gets a `<style>` with `.content a { color: {{ layout.accent_color }} }` and the 640px media query.
- [ ] **Step 4: Switch `EmailTemplates::render()` and `SendEmail::sendWaitingListInvitationEmail()` to `wrap()`.** The admin-edited `$content` is wrapped with `wrap($event->organisation, $content)`. Delete `customContent.blade.php`.
- [ ] **Step 5: Run `EmailLayoutTest`, `EmailTemplateTest`, `WaitingListInvitationTest`, `GroupInvitationMailTest`.** Expected: all PASS. If the new document does not start with `<html>`, update the existing assertions on `'<html>'` and keep their intent.
- [ ] **Step 6: Commit** "Send every mail in one new layout".

---

### Task 3: Which version of a mail goes out

**Files:**
- Create: `app/Services/MailSource.php`
- Modify: `app/Services/EmailTemplates.php`, `app/Listeners/SendEmail.php` (`sendConfirmationEmail`), `app/Events/InvitedFromWaitingList.php` (`renderDefaultContent`)
- Test: `tests/Integration/EventEmailResolutionTest.php` (new)

**Interfaces:**
- Consumes: Task 1 models, Task 2 `render()`.
- Produces:
  - `MailSource` (readonly properties): `string $kind` (one of the constants `EVENT`, `LIBRARY`, `LEGACY`, `ORGANISATION`, `DEFAULT`), `?string $subject`, `?string $content`, `?string $view`, `?EmailTemplate $libraryTemplate`.
  - `EmailTemplates::resolve(?Organisation $organisation, ?Event $event, string $type): MailSource`. The first match wins:
    1. the event's `EventEmail` with own text
    2. the `emailTemplate` of that row
    3. for `CONFIRMATION` and `CONFIRMATION_PLAY_LINK` only: the event's `confirmation_email` view (skip it unless `view()->exists()`)
    4. `getOverride()`
    5. the view of the definition

    An event without `id` (sample data) skips 1 to 3. `GROUP_INVITE` never looks at an event.
  - `render()` without `$template` uses `resolve($organisation, $attributes['event'] ?? null, $type)`. `LEGACY` and `DEFAULT` render their view's content section. The other kinds fill `subject` and `content`. For `LEGACY` the subject is the default subject.
  - `EmailTemplates::getStartingContent(?Organisation $organisation, ?Event $event, string $type): string` returns the placeholder text of the version in use (kinds `EVENT`, `LIBRARY`, `ORGANISATION`), else `getDefaultContent($type)`. Task 5 uses this.
  - `SendEmail::sendConfirmationEmail()` loses its own `confirmation_email` branch and calls `render()` only.
  - `InvitedFromWaitingList::renderDefaultContent()` uses `resolve()`. It fills the text for kinds `EVENT`, `LIBRARY`, `ORGANISATION`, else it renders the view's content section as today.

- [ ] **Step 1: Write the failing tests**, one per step. Send each registration mail through a guest registration, as `testACustomisedConfirmationIsSentInsteadOfTheDefault` does:
  - `testTheEventsOwnTextWinsOverEverything`: event own text, plus a library pick on another type, plus an organisation version. The mail uses the own subject and text.
  - `testALibraryTemplateWinsOverTheOrganisationVersion`
  - `testALegacyViewWinsOverTheOrganisationVersionForBothConfirmationTypes`: `confirmation_email = 'emails.tickets.play-at-home'`. The mail contains a sentence from that view, for an order with and without `play_link`.
  - `testTheOrganisationVersionGoesOutWhenTheEventHasNothing`
  - `testTheDefaultGoesOutWhenNobodyChangedAnything`: contains "We zijn er bij!".
  - `testAnotherEventsTextDoesNotLeak`: own text on event A. A mail for event B of the same organisation is the default.
  - `testTheWaitingListEditorStartsFromTheEventsText`: own text for `waitingListInvitation` on the event. `GET /admin/events/{id}/waitinglist/invite/{user}/edit` shows that text with its placeholders filled in.
- [ ] **Step 2: Run them.** Expected: FAIL.
- [ ] **Step 3: Implement `MailSource`, `resolve()`, `getStartingContent()`**, and the three call-site changes.
- [ ] **Step 4: Run `EventEmailResolutionTest`, `EmailTemplateTest`, `WaitingListInvitationTest`, `GuestRegistrationTest`, `FreeTicketPurchaseTest`.** Expected: all PASS.
- [ ] **Step 5: Commit** "Let an event's own mail or library template win over the organisation's".

---

### Task 4: Template library admin (Settings > E-mails > Sjablonen)

**Files:**
- Create: `app/Http/Controllers/Admin/EmailLibraryController.php`
- Create: `resources/views/admin/emails/library/index.blade.php`, `create.blade.php`, `edit.blade.php`
- Create: `resources/views/admin/emails/partials/editor.blade.php`, `resources/views/admin/emails/partials/tabs.blade.php`
- Modify: `resources/views/admin/emails/edit.blade.php` (use the editor partial), `resources/views/admin/emails/index.blade.php` (tabs), `routes/web.php`
- Test: `tests/Integration/EmailLibraryTest.php` (new)

**Interfaces:**
- Produces:
  - `editor.blade.php` takes `$formAction`, `$previewAction`, `$cancelUrl`, `$subject`, `$content`, `$placeholders`, and optional `$extraFields` (a section). It holds the editor parts of `edit.blade.php`, moved without changes: subject field, `contenteditable` editor with HTML toggle, placeholder table, buttons and preview iframe.
  - `tabs.blade.php` takes `$active` in `standard|library|layout` and links "Standaardmails", "Sjablonen", "Layout".
  - Routes (inside the existing admin group, before the `emails/{type}` routes). Add `->where('type', ...)` with the five type names to every `emails/{type}` route.
    - `GET emails/templates` `index`, `GET emails/templates/create` `create`, `POST emails/templates` `store`
    - `GET emails/templates/{id}/edit` `edit`, `POST emails/templates/{id}` `update`, `POST emails/templates/{id}/delete` `destroy`
    - `GET|POST emails/templates/{id}/preview` `preview`, `GET|POST emails/templates/create/preview` `previewNew`
  - Validation: `type` in the four event types (not `groupInvite`), `name` required, max 100, unique per organisation and type, `subject` required max 255, `content` required. A new template starts from `getDefaultContent($type)`.
  - If `eventEmails()` has rows whose event is not soft-deleted, `destroy` refuses. It redirects to `index` with the error `Dit sjabloon wordt nog gebruikt door: <event names>.` Rows of soft-deleted events are deleted together with the template.
  - `index` shows per template: name, mail type label, `updated_at`, and the names of the events that use it, each linked to the Task 5 event page.

- [ ] **Step 1: Write the failing tests:**
  - `testAnAdminCreatesEditsAndDeletesATemplate`
  - `testATemplateNameIsUniquePerType`
  - `testAGroupInviteTemplateCannotBeCreated`
  - `testATemplateInUseCannotBeDeleted`: the error names the event, and the row still exists.
  - `testATemplateUsedOnlyByADeletedEventCanBeDeleted`
  - `testAnotherOrganisationsTemplateIsNotFound`: 404 on edit, update and delete.
  - `testTheLibraryPreviewIsSandboxed`
- [ ] **Step 2: Run them.** Expected: FAIL.
- [ ] **Step 3: Implement the controller, views and routes.** Reuse `getAdministeredOrganisation()`: move it from `EmailTemplateController` into a trait `app/Http/Controllers/Admin/Concerns/AdministersEmails.php`, together with a helper that returns the sandboxed preview response.
- [ ] **Step 4: Run `EmailLibraryTest`, `EmailTemplateTest`.** Expected: all PASS.
- [ ] **Step 5: Commit** "Let admins keep a library of mail templates".

---

### Task 5: Event mails admin (`admin/events/{event}/emails`)

**Files:**
- Create: `app/Http/Controllers/Admin/EventEmailController.php`
- Create: `resources/views/admin/events/emails/index.blade.php`, `edit.blade.php`
- Modify: `app/Http/Controllers/Admin/EventController.php` (add a `ResourceAction` "E-mails" after "Gasten"), `app/Services/EmailTemplates.php` (`getSampleAttributes`), `routes/web.php`
- Test: `tests/Integration/EventEmailTest.php` (new)

**Interfaces:**
- Consumes: `resolve()`, `getStartingContent()`, `MailSource` (Task 3), the editor partial (Task 4).
- Produces:
  - `EmailTemplates::getSampleAttributes(Organisation $organisation, $type, ?Event $event = null)`. With `$event`, it uses the real event and its first ticket category. If the event has no ticket category, it uses a made-up category with the event's dates. Player, team, order and URLs stay made up.
  - Routes: `GET events/{event}/emails` `index`, `GET events/{event}/emails/{type}` `edit`, `POST events/{event}/emails/{type}` `update`, `GET|POST events/{event}/emails/{type}/preview` `preview`, with `type` limited to the four event types.
  - Access: `Event::findOrFail()`, then 404 unless `$event->organisation->isAdmin(Auth::user())`.
  - `index` shows per type the label and the source: "Standaard van de organisatie", "Sjabloon: <name>", "Eigen tekst", or "Oude vaste mail (<view>)" (Task 7B only).
  - `edit` shows radio `source` with `organisation|template|own` (the sketch in the spec). `template` has a select of the organisation's library templates of this type. When there are none, the option is disabled with the hint "Nog geen sjablonen voor deze mail". `own` has the editor, starting from `getStartingContent()`.
  - `update` per `source`:
    - `organisation`: delete the row.
    - `template`: `email_template_id` must be a library template of this organisation and this type. Store it with `subject` and `content` null.
    - `own`: `subject` required max 255, `content` required. Store them with `email_template_id` null.
  - `preview` renders with the real event. POST previews the posted choice without saving.

- [ ] **Step 1: Write the failing tests:**
  - `testTheEventPageListsWhereEachMailComesFrom`
  - `testAnAdminGivesAnEventItsOwnText`: then send a registration mail and assert the own text.
  - `testAnAdminPicksALibraryTemplate`
  - `testChoosingTheOrganisationVersionRemovesTheEventsChoice`
  - `testATemplateOfAnotherOrganisationOrTypeIsRefused`: two posts, both with a validation error. `EventEmail::count() === 0`.
  - `testAnAdminOfAnotherOrganisationGetsNotFound`
  - `testTheEventPreviewUsesTheRealEvent`: contains the event name and venue name, not "Voorbeeldquiz".
  - `testTheEventsMenuLinksToItsMails`: the admin event list or show page contains the `index` URL.
  - `testDeletingAnEventKeepsWorking`: with an `event_emails` row, `$event->delete()` and `$event->forceDelete()` both succeed.
- [ ] **Step 2: Run them.** Expected: FAIL.
- [ ] **Step 3: Implement the controller, views, routes, `ResourceAction` and the `getSampleAttributes()` change.**
- [ ] **Step 4: Run `EventEmailTest`, `EmailTemplateTest`, `AdminSmokeTest`.** Expected: all PASS.
- [ ] **Step 5: Commit** "Let admins give one event its own mails".

---

### Task 6: Layout admin (Settings > E-mails > Layout)

**Files:**
- Create: `app/Http/Controllers/Admin/EmailLayoutController.php`, `resources/views/admin/emails/layout.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Integration/EmailLayoutTest.php`

**Interfaces:**
- Consumes: `EmailLayouts` (Task 2), the `AdministersEmails` trait (Task 4).
- Produces:
  - Routes, registered before `emails/{type}`: `GET emails/layout` `edit`, `POST emails/layout` `update`, `GET|POST emails/layout/preview` `preview`, `POST emails/layout/reset` `reset`.
  - The page shows:
    - an accent color field: `<input type="color">` plus a text field
    - the footer text and a preview iframe
    - a collapsed "Geavanceerd" section: a textarea that starts from `custom_html` (or `getDefaultHtml()`), and the table from `getPlaceholders()`
  - Validation: `accent_color` nullable, `regex:/^#[0-9a-fA-F]{6}$/`. `footer_text` nullable, max 2000. `custom_html` nullable.
  - If `custom_html` is filled in, `preg_match_all('/\{\{\s*content\s*\}\}/', ...)` must be exactly 1. If not, show the error `De layout moet precies één keer {{ content }} bevatten.`
  - Store a `custom_html` that is the same as `getDefaultHtml()` as null. Later changes to the default then reach the organisation.
  - `reset` sets `custom_html` to null and keeps the two settings.
  - `preview` renders the sample `CONFIRMATION` mail through `render(..., null, $draft)`, where `$draft` is an unsaved `EmailLayout` built from the POST.

- [ ] **Step 1: Write the failing tests:**
  - `testAnAdminSetsTheAccentColorAndFooterText`
  - `testAnInvalidAccentColorIsRefused`
  - `testACustomLayoutNeedsExactlyOneContentPlaceholder`: refused for none and for two. Accepted for `{{content}}` and `{{  content  }}`.
  - `testAResetReturnsToTheDefaultLayout`
  - `testSavingTheUnchangedDefaultStoresNothing`
  - `testUnknownPlaceholdersInTheLayoutStayVisible`
  - `testTheLayoutPreviewShowsTheDraftWithoutSaving`
  - `testOnlyAdminsCanChangeTheLayout`
- [ ] **Step 2: Run them.** Expected: FAIL.
- [ ] **Step 3: Implement the controller, view and routes.**
- [ ] **Step 4: Run `EmailLayoutTest`.** Expected: all PASS.
- [ ] **Step 5: Commit** "Let admins adjust or replace the mail layout".

---

### Task 7A: Remove the legacy field (if Task 0 found no events)

**Files:** Modify `app/Http/Api/V1/ResourceDefinitions/Events/EventResourceDefinition.php` (remove the `confirmation_email` field and the view scan), `app/Services/EmailTemplates.php` (remove step 3 from `resolve()` and `MailSource::LEGACY`). Run `grep -rn play-at-home app resources`. If nothing else refers to `resources/views/emails/tickets/play-at-home.blade.php`, delete it. Leave the column. Change the Task 3 legacy test to assert that `confirmation_email` no longer changes the mail.

- [ ] Run the full integration suite. Expected: PASS. Commit "Retire the per-event Blade confirmation mail".

### Task 7B: Show and clear the legacy field (if Task 0 found events)

**Files:** Modify `EventEmailController` (route `POST events/{event}/emails/legacy/clear` `clearLegacy`, sets `confirmation_email` to null), both event email views.

- On `index`, both confirmation rows show "Oude vaste mail (<view>)". On `edit` of those two types, a notice explains the old mail and shows the button "Oude vaste mail verwijderen". Saving "Standaard van de organisatie" does not clear the field.
- For such an event, "Eigen tekst" starts from `getDefaultContent($type)`, with the notice "Je vertrekt van de standaardtekst, niet van de oude vaste mail."
- Tests: `testALegacyEventShowsItsOldMail`, `testClearingTheLegacyMailFallsBackToTheOrganisation`, `testSavingTheOrganisationChoiceKeepsTheLegacyMail`.
- [ ] Run `EventEmailTest`. Expected: PASS. Commit "Show and clear the old per-event confirmation mail".

---

### Task 8: Finish

- [ ] **Step 1: Run the full integration suite and the Unit suite.** Expected: all PASS.
- [ ] **Step 2: Render one real registration mail and one waiting list mail** through the admin preview with the docker webserver (`http://localhost:8095/admin/emails/confirmation/preview`). Take screenshots at desktop and phone width and compare them with the mockup.
- [ ] **Step 3: Push the branch and open a PR to `master`.** The description lists the Task 0 result, the new admin pages, and the screenshots.
