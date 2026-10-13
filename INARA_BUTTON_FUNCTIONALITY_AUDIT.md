# INARA Matrimony — Button and Feature Functionality Audit

**Audit date:** 2026-10-12  
**Stack:** CodeIgniter 3, PHP 7.4, MySQL  
**Scope:** Clickable controls and actions exposed by the current user-facing page templates: navigation links, buttons, icon links, native menus/selects, forms, result cards, and dashboard shortcuts. This is a functionality audit, not a visual or security audit.

## Summary

Source inspection covered the public landing page, authentication and verification pages, dashboard/shared navigation, discovery/profile pages, profile/preferences/photo forms, interests/connections/notifications, messaging, profile-interaction lists, portal settings/stories, and legacy pages/news templates. Controller actions and declared routes were checked for visible actions.

The local browser was used to open the landing and registration/login pages, follow the public registration link, toggle password visibility, and request a filtered matches URL while signed out. The registration link navigated to the register page; the password field toggled from masked to visible. The password-toggle control does not update its accessible label. A filtered matches request redirected to login and discarded the query string.

Several authenticated actions (profile saves, photo upload/delete, interest responses, messaging, shortlist/block, password changes) could not be submitted safely without a dedicated test account and disposable test records. Their form-to-route wiring was inspected, but they are **not reported as end-to-end tested**.

### Findings by severity

| Severity | Count | Summary |
|---|---:|---|
| High | 1 | Registration creates accounts that cannot receive the email-verification link |
| Medium | 9 | Mismatched login/search/navigation actions and incomplete advertised features |
| Low | 1 | Password visibility toggle leaves a stale accessible name |

## Findings

### 1. Registration does not deliver the verification link

- **Severity:** High
- **Page:** Register; login verification/resend flow
- **Files:** `application/controllers/Auth.php:527-565`, `application/controllers/Auth.php:826-853, 1021-1025`
- **Expected:** After account creation or a resend request, the user receives a usable verification link and can activate the account.
- **Actual:** Registration creates and stores a hashed verification token, constructs a verification URL, then leaves the email send as a TODO. The resend action likewise records a new token and explicitly reports that email delivery is not configured. The token is not exposed to the user elsewhere. Login blocks unverified accounts, so a new registrant cannot complete activation through the intended UI.
- **Evidence:** The registration controller comment says SMTP/email sending will be connected later; the resend success text says “Email delivery is not configured yet.”

### 2. Login promises mobile-number support but only accepts email

- **Severity:** Medium
- **Page:** Sign in
- **Files:** `application/views/auth/login.php:183-196`, `application/controllers/Auth.php:863-913, 976-978`
- **Expected:** A value entered as “Email or Mobile Number” should be accepted as either identifier.
- **Actual:** The server validates the submitted identifier with `FILTER_VALIDATE_EMAIL` and retrieves the user by email. A valid mobile number is rejected as an invalid email, despite the field label and its text input accepting it.

### 3. “Forgot Password?” is inert

- **Severity:** Medium
- **Page:** Sign in
- **Files:** `application/views/auth/login.php:208-215`; routes are declared in `application/config/routes.php`
- **Expected:** Clicking the password-recovery prompt should open a recovery flow or clearly identify that recovery is unavailable.
- **Actual:** It is a `<span>` with `aria-disabled="true"`, no link, no button semantics, and no password-reset route. It cannot be activated by mouse or keyboard.

### 4. “Remember me” does not change session persistence

- **Severity:** Medium
- **Page:** Sign in
- **Files:** `application/views/auth/login.php:244-252`, `application/controllers/Auth.php:863-1124`
- **Expected:** Selecting “Remember me” should extend authentication persistence according to the product policy.
- **Actual:** The checkbox submits `remember=1`, but the login handler does not read or act on that field. Successful authentication always creates the normal session; selecting or clearing the checkbox has no effect.

### 5. Shared search control is not wired consistently

- **Severity:** Medium
- **Page:** Dashboard and authenticated portal pages
- **Files:** `application/views/layouts/app_navigation.php:37-40`, `application/views/dashboard/index.php:91-99`
- **Expected:** The “Search profiles” field should search profiles from any page where it is shown.
- **Actual:** The input has no `name`, so submitting its form sends no search term. On the dashboard, JavaScript only filters the currently rendered `.member-card` elements in the browser. On portal pages the input has a different ID (`portal-search`) and no matching input handler exists; pressing Enter therefore navigates to the unfiltered matches page. The portal search is effectively nonfunctional, and the dashboard search is limited to the already-loaded recommendations rather than the discovery search.

### 6. Landing-page search loses selected filters at the sign-in gate

- **Severity:** Medium
- **Page:** Landing page → Matches → Sign in
- **Files:** `application/views/home.php:260-342`, `application/controllers/Discovery.php:40-45`, `application/controllers/Auth.php:1118-1121`
- **Expected:** If authentication is required to search, the selected filters should be retained and applied after sign-in.
- **Actual:** A browser request to `/matches?gender=female&age_min=22&age_max=27` while signed out redirected to the login page without those parameters. The successful login handler then redirects to the dashboard, so the user’s search criteria are not resumed.

### 7. Dashboard shortcuts do not perform their labeled actions

- **Severity:** Medium
- **Page:** Dashboard
- **File:** `application/views/dashboard/index.php:41-61`
- **Expected:** “AI Match” should open AI-generated matches; “View All” should open the full discovery list; “Premium Benefits” should open benefit or membership information.
- **Actual:**
  - “AI Match” points to `matches`, not the `ai` route. It opens ordinary discovery rather than invoking the AI controller.
  - “View All” points to `#recommended`, the same section it is already in, so it does not open a full results page.
  - “Premium Benefits” links to `#premium-benefits`, and its own card has that ID. It only targets itself; there is no benefits page or purchase action behind it.

### 8. Help & Support opens Settings

- **Severity:** Medium
- **Page:** Dashboard sidebar
- **File:** `application/views/layouts/app_navigation.php:91-98`
- **Expected:** “Help & Support” should open help/support information or a contact channel.
- **Actual:** Its `href` is the same `settings` route used by the Settings link. Clicking it opens account settings, not support.

### 9. Membership plan buttons do not select or purchase a plan

- **Severity:** Medium
- **Page:** Landing page membership section
- **Files:** `application/views/home.php:480-517`; `PROJECT_FEATURE_STATUS.md` (membership/payment status)
- **Expected:** “Choose Signature” and the other plan calls to action should select the named plan or continue to a plan-aware checkout/enrollment step.
- **Actual:** All three plan buttons navigate to the same registration URL. No plan identifier is submitted or retained, and the project feature-status notes that payment integration and membership data are not implemented. The page therefore does not complete plan selection or purchase.

### 10. Stories and profile-visibility navigation lead to explicit placeholders

- **Severity:** Medium
- **Page:** Authenticated Stories and Profile Visibility
- **Files:** `application/controllers/Portal.php:10-18, 35-53`; `application/views/portal/page.php:46-53, 63-66`
- **Expected:** Stories should show member stories, and Profile Visibility should let the member change their visibility.
- **Actual:** Stories shows a message that submissions are not available; Profile Visibility says controls are not configured and states that active profiles remain discoverable. The links navigate, but the advertised features cannot be used.

### 11. Password toggle accessible name does not reflect its state

- **Severity:** Low
- **Page:** Sign in and Register
- **Files:** `application/views/auth/login.php:231-235, 344-360`; `application/views/auth/register.php:216-220, 356-379`
- **Expected:** After revealing a password, the control should expose an accessible name such as “Hide password”; after masking it again, it should say “Show password.”
- **Actual:** Browser checks confirmed clicking toggles the input from `type=password` to `type=text`, but the button continues to have `aria-label="Show password"` in both states. Keyboard/screen-reader users are not told the current action correctly.

## Page and interaction inventory

| Page/template group | Clickable controls and forms reviewed | Verification status |
|---|---|---|
| Landing — `application/views/home.php` | Header/footer links, search-profile links, shortlist links, registration CTAs, membership CTAs, GET search selectors/button, in-page privacy/membership anchors | Landing loaded in browser; registration link followed; search-to-login redirect tested. Filtered search was not performed with a signed-in account. |
| Sign in/Register — `application/views/auth/login.php`, `register.php` | Submit forms, registration/sign-in links, password visibility controls, remember checkbox, resend-verification form, native gender/date controls | Sign-in/register pages opened; registration link and password toggle tested. No account was created or submitted. |
| Verification result — `application/views/auth/verification_result.php` | Brand/sign-in/dashboard/profile/matches/preferences navigation links | Source-reviewed only; no real token flow can be completed because email delivery is not configured. |
| Dashboard/shared navigation — `application/views/dashboard/index.php`, `application/views/layouts/app_navigation.php` | Navigation links, top-search form, account `<details>` menu, logout forms/buttons, sidebar links, recommendation profile links, dashboard shortcuts | Source and route wiring reviewed. Authenticated interaction was not browser-tested. Findings 5, 7, and 8 apply. |
| Discovery/profile — `application/views/discovery/index.php`, `discovery/profile.php` | GET filter controls, sort/photo filter, clear/pagination links, profile links, save/block/interest forms | Source-to-controller/route wiring reviewed. Requires signed-in member data; end-to-end actions not tested. |
| Profile/preferences/photos — `application/views/profile/index.php`, `preferences/index.php`, `partner_preferences/index.php`, `photos/index.php` | Profile and preference submit controls; photo upload, primary-photo, and delete controls; cross-links | Source-to-controller/route wiring reviewed. Upload and database-changing actions not submitted. |
| Interests/connections/notifications/messages — corresponding views under `application/views/interests/` and `application/views/messages/` | Profile links, accept/decline/cancel forms, connection/message links, notification rows, compose/send form | Source-to-controller/route wiring reviewed. No authenticated test records were available. |
| Profile interactions — `application/views/profile_interactions/index.php` | Profile links, save/remove/unblock forms | Source-to-controller/route wiring reviewed. No state-changing action submitted. |
| Portal — `application/views/portal/page.php` | Profile/settings/visibility/story links and actions | Source-reviewed; placeholder behavior confirmed directly in the rendered template. |
| Legacy pages/news — `application/views/pages/`, `application/views/news/`, `application/views/templates/` | Header navigation and article links | Source-reviewed; not reachable through the current landing-page interaction checks. |

Native selects, account menus, and linked profile-card areas do not have separate JavaScript click handlers; their behavior comes from browser form submission, `<details>/<summary>`, and anchor navigation. No additional custom clickable-card handler was found in the inspected active views.

## Actions with valid wiring but not end-to-end verified

The active view templates submit profile edits, preference changes, photo uploads/primary-photo changes/deletions, interests, messages, and shortlist/block changes to corresponding CodeIgniter controller routes. The relevant route declarations and controller method guards were present. These operations can change user/database state, so they were not submitted without a dedicated authenticated test account and disposable records. Their successful database effects, redirects, and error handling remain unverified by this audit.

## Audit limitations

- No authenticated test account was provided; protected pages were checked by tracing view actions to routes/controllers, not by completing member workflows.
- No new account, email, photo, message, interest, or profile-interaction record was created during testing.
- The report lists verified defects and explicit unimplemented placeholders; it does not claim that every backend operation succeeds merely because a route exists.
