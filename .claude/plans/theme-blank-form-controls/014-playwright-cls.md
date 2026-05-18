# Task 014: Playwright CLS Fixture + Spec for Form Controls

**Status**: completed
**Depends on**: 004, 005, 006, 007, 008, 009, 010, 011, 012, 013
**Retry count**: 0

## Description
Add a Playwright CLS smoke test covering all 10 form control components. Create a new static fixture HTML file (`forms-page.html`) that inlines all component CSS and renders one of each form component with realistic content. The spec asserts CLS === 0 both before and after custom element upgrade.

## Context
- Related files:
  - New: `packages/theme-blank/tests/Browser/fixtures/forms-page.html`
  - New: `packages/theme-blank/tests/Browser/forms-cls.spec.ts`
  - Reference: `packages/theme-blank/tests/Browser/primitives-cls.spec.ts` — mirror its structure exactly
  - Reference: `packages/theme-blank/tests/Browser/fixtures/primitives-page.html` — mirror its HTML structure
- The fixture inlines CSS literally (with any `@custom-media` resolved to `@media` literals). Do NOT load CSS from the dev server — the fixture must be self-contained.
- The fixture should NOT import the JS bundle — the pre-upgrade CLS test verifies CSS-only rendering. The upgrade test inlines a `<script type="module">` that imports the component bundle.

## Fixture HTML Structure (`forms-page.html`)

The fixture must include one of each component with realistic content:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    /* inline: tokens.css, base.css, mk-button.css, mk-input.css, mk-textarea.css,
       mk-select.css, mk-checkbox.css, mk-radio.css, mk-switch.css,
       mk-field.css, mk-fieldset.css, mk-form.css */
  </style>
</head>
<body>
  <mk-form>
    <form>
      <mk-fieldset>
        <fieldset>
          <legend>Account details</legend>
          <mk-field>
            <label for="email">Email</label>
            <mk-input><input id="email" name="email" type="email" required></mk-input>
            <small data-mk-error></small>
          </mk-field>
          <mk-field>
            <label for="bio">Bio</label>
            <mk-textarea><textarea id="bio" name="bio"></textarea></mk-textarea>
          </mk-field>
          <mk-field>
            <label for="country">Country</label>
            <mk-select>
              <select id="country" name="country">
                <option value="pl">Poland</option>
                <option value="de">Germany</option>
              </select>
            </mk-select>
          </mk-field>
          <mk-checkbox>
            <input type="checkbox" id="agree" name="agree">
            <label for="agree">I agree to the terms</label>
          </mk-checkbox>
          <mk-radio>
            <input type="radio" id="opt-a" name="option" value="a">
            <label for="opt-a">Option A</label>
          </mk-radio>
          <mk-switch>
            <input type="checkbox" id="notifications" name="notifications">
            <label for="notifications">Email notifications</label>
          </mk-switch>
        </fieldset>
      </mk-fieldset>
      <mk-button><button type="submit">Submit</button></mk-button>
    </form>
  </mk-form>
</body>
</html>
```

## Spec Structure (`forms-cls.spec.ts`)

Mirror `primitives-cls.spec.ts` exactly:
1. Load the fixture HTML via `readFileSync`
2. Test 1: "all 10 form controls produce zero CLS in unupgraded state" — set content, wait 500 ms, assert CLS === 0
3. Test 2: "all 10 form controls produce zero CLS through upgrade" — set content, simulate JS upgrade by setting each component's declarative attributes, wait 500 ms, assert CLS === 0

### Upgrade-simulation side effects (Test 2)

The upgrade stubs MUST replicate each component's real `connectedCallback` side-effects so the test reflects production behavior:

- `mk-field`: set `this.dataset.state = 'pristine'` (matches real `mk-field.connectedCallback`).
- `mk-switch`: find `input[type="checkbox"]` descendant; if it has no `role`, `setAttribute('role', 'switch')`.
- `mk-button`: NO loading side-effect required — the fixture does not set `loading="true"`. Document this in a comment.
- `mk-form`: find `form` descendant; `setAttribute('novalidate', '')` (matches real `mk-form.connectedCallback`).
- All others: a no-op `connectedCallback` is sufficient because they only register attributes already declared on the wrapper (which CSS reads directly).

All stub classes still insert a `document.createComment('')` marker as the first child (mirrors Lit's ChildPart marker — see `primitives-cls.spec.ts` line 73). This is critical: real Lit upgrade inserts a comment node, and if the test doesn't, the post-upgrade DOM differs from the actual post-upgrade DOM.

## Requirements (Test Descriptions)

- [ ] `it all 10 form controls produce zero CLS in the unupgraded (CSS-only) state`
- [ ] `it all 10 form controls produce zero CLS through the custom-element upgrade`

## Acceptance Criteria
- Both Playwright tests pass (CLS === 0)
- `npm run test:cls` exits 0 with both old specs and the new `forms-cls.spec.ts` passing
- Fixture HTML is self-contained (no network requests for CSS or JS)
- Fixture uses realistic content (not lorem ipsum — actual field labels)
- Fixture inlines the NEW form-token CSS added by task 001 (do not omit `--mk-input-*`, `--mk-button-*`, `--mk-field-*`, `--mk-color-focus-ring`, `--mk-radius-input`, `--mk-input-height-*`)
- Upgrade-simulation stubs apply mk-field `data-state=pristine`, mk-switch `role=switch` on inner input, and mk-form `novalidate` on inner `<form>`
