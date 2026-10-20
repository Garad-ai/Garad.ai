# Garad AI Chat Pro — Account, Settings, and Sidebar Redesign Plan

## Objective

Upgrade the existing unified conversation prototype into a more production-ready Garad AI workspace, concentrating on the sidebar hierarchy, account entry point and popover, plan/upgrade flow, profile and personalization controls, settings depth, responsive behavior, and overall visual polish. Preserve the product rule that Chat, Agents, projects, artifacts, tools, and account management remain part of one conversation workspace rather than becoming separate dashboard routes.

## Grounded Current State

- The app is a single React 19/Vite experience implemented primarily in `src/App.tsx`, with all styling in `src/index.css`.
- Existing conversation, Chat/Agents, composer, tools, artifacts, previews, projects, notifications, voice, light/dark themes, and simulated generation behaviors are already implemented and must continue to work.
- The sidebar currently has brand, new chat, search, grouped conversations, projects, settings, theme, and a basic profile card.
- The current profile experience is a generic centered modal. Profile, Plan, and Usage are placeholder toasts; settings sections other than Appearance, Notifications, and Language are mostly placeholders.
- There is no authentication, backend, router, billing source, or persisted user source.
- Existing Garad mark and wordmark assets in `src/assets/` remain authoritative.
- Existing typography (DM Sans and Manrope), violet brand tokens (`#514cff` light and `#7773ff` dark), and light/dark token architecture should be refined rather than replaced.

## Product Defaults for the Frontend-Only Prototype

These defaults resolve the previously unanswered account/auth questions without introducing backend assumptions:

- Central prototype user: **Mohamed Ali**, initials **MA**, email **mohamed@example.com**.
- Initial subscription: **Pro**.
- Pricing catalog: **Free**, **Pro**, and **Business**. Pro is initially marked current; Business is the upgrade path.
- Plan changes are simulated in local React state and immediately update all plan labels, badges, usage copy, and pricing CTAs.
- Sign-out is simulated with an explicit confirmation followed by an in-app signed-out screen. No external redirect or nonexistent `/login` route is used. A “Continue as Mohamed Ali” action restores the prototype session.
- State remains session-only unless the existing implementation already persists it; no local storage or backend is added in this pass.

## Implementation Plan

### 1. Establish a Small, Centralized Account Model

Update `src/App.tsx` with typed fixtures and state instead of repeating account strings across the sidebar and dialogs.

- Add types for `UserProfile`, `PlanId`, plan metadata, and personalization preferences.
- Define one initial user fixture containing name, email, initials/avatar fallback, workspace label, and current plan.
- Define pricing metadata for Free, Pro, and Business: display name, short description, monthly price, key limits/features, and CTA state.
- Add state for:
  - editable profile fields;
  - current plan;
  - billing cadence used by the pricing modal;
  - personalization fields;
  - account popover visibility;
  - authenticated/signed-out prototype state.
- Derive initials and all displayed account/plan labels from this state so profile edits and plan changes remain consistent everywhere.
- Keep the fixtures local to the prototype; do not add a data layer or external dependency.

### 2. Expand the Shared Icon and Overlay Vocabulary

Extend the existing inline icon system only with symbols required by the new surfaces, such as user/profile, plan or card, personalization, shield/privacy, log out, and billing/usage. Continue using the current SVG icon pattern rather than emoji or a new icon package.

Refine overlay behavior:

- Keep large workflows in the existing modal shell.
- Implement the account menu as a true popover anchored to the sidebar account card, opening upward on desktop.
- Close the account popover on outside click, Escape, sidebar navigation, or opening another overlay.
- Ensure opening one popover/menu closes conflicting menus.
- Preserve the current modal backdrop, keyboard Escape behavior, and mobile bottom-sheet treatment.

### 3. Redesign the Sidebar Information Hierarchy

Refine the existing sidebar without changing its core purpose or conversation behavior.

- Polish the brand header spacing, collapse control, and use of the supplied wordmark/mark.
- Retain “New chat” as the strongest sidebar action and keep its shortcut.
- Improve search affordance, group labels, active conversation treatment, hover actions, and empty/search states.
- Keep conversation actions (rename, pin, archive, delete) and existing grouped navigation intact.
- Consolidate secondary workspace actions near the bottom with clearer hierarchy:
  - Projects;
  - Settings;
  - a compact plan/upgrade entry point (“View plans” for Pro, adapting if the selected plan changes);
  - the account card.
- Move theme switching into Settings/account controls instead of treating it as a peer workspace navigation item; optionally retain a compact quick-theme action inside the account popover.
- Upgrade the account card to show derived avatar initials, user name, current plan/workspace label, and a clear disclosure affordance.
- Preserve collapsed sidebar behavior with centered icons and accessible labels/tooltips; the account avatar remains usable in collapsed mode.
- Preserve the current mobile drawer and ensure the refined hierarchy remains usable without horizontal overflow.

### 4. Build a Production-Quality Account Popover

Replace the profile-card-to-centered-modal interaction with a compact, layered account popover inspired by mature conversational products while retaining Garad branding.

Popover structure:

1. Account identity block with avatar, name, email, and current plan badge.
2. A visually distinct plan/usage row showing current plan and a concise usage signal.
3. Primary actions:
   - Personalization;
   - Settings;
   - View plans / Upgrade;
   - Profile.
4. Support actions:
   - Help;
   - theme toggle or appearance shortcut.
5. Separated destructive session action:
   - Sign out.

Interaction requirements:

- Every item opens a real prototype surface rather than a placeholder toast.
- The plan row opens the pricing modal.
- Personalization opens the settings experience directly on the Personalization section.
- Settings opens the last/default settings section.
- Profile opens an editable profile dialog.
- Sign out opens confirmation.
- On mobile, present the same content as a bottom sheet or safely positioned full-width popover.

### 5. Add the Upgrade and Pricing Experience

Create a wide pricing modal integrated into the conversation workspace.

- Header: concise value proposition, current plan context, and monthly/yearly cadence control.
- Plan cards: Free, Pro, Business with clear hierarchy and restrained comparison detail.
- Mark the active plan with a “Current plan” badge and disabled current-plan CTA.
- Provide sensible CTAs for other plans (“Switch to Free,” “Choose Pro,” “Upgrade to Business”).
- Show 4–6 high-value feature/limit bullets per plan; emphasize Agents, model access, artifact creation, uploads, and workspace collaboration rather than generic filler.
- Include a compact “Manage billing” affordance that explains billing is simulated in this prototype.
- Choosing a non-current plan opens a concise confirmation state or performs an explicit confirmed action, then updates `currentPlan` and shows success feedback.
- Avoid collecting payment details because there is no billing backend.
- Ensure dark mode, narrow viewport stacking, and keyboard focus styles are complete.

### 6. Replace Placeholder Profile and Personalization Flows

#### Profile dialog

- Show editable display name and email fields.
- Display a generated initials avatar and short account metadata.
- Save validates non-empty name and a plausible email shape, updates centralized account state, closes, and shows confirmation.
- Cancel leaves the account unchanged.
- Expose current plan with a link/button to the pricing modal.

#### Personalization section

Add a dedicated Settings section with working controls for:

- “What should Garad call you?” / preferred name;
- role or occupation;
- response traits (selectable chips such as concise, analytical, creative, direct);
- custom instructions textarea;
- memory/personalization toggle with prototype-safe explanatory copy.

Save or control changes should update local state and provide visible feedback. These preferences do not need to alter generated content in this pass unless a small label/copy change naturally demonstrates the state.

### 7. Rebuild Settings into Complete, Coherent Sections

Keep Settings as a wide modal with a left navigation on desktop and a horizontally scrollable or compact navigation treatment on mobile. Replace generic placeholder sections with purposeful controls.

Recommended sections and contents:

- **General**: default conversation mode, model preference, startup behavior, compact sidebar preference if useful.
- **Personalization**: fields and traits described above.
- **Appearance**: existing Light/Dark/System cards, reduced motion, and optional density control; retain immediate theme application.
- **Notifications**: existing task/artifact/tips switches with clearer grouping and descriptions.
- **Data controls**: chat history/training-style prototype toggles, export data action, archive-management affordance, and a clearly separated destructive “Delete all chats” confirmation if included.
- **Voice & language**: combine language choice, voice response preference, and speech controls rather than maintaining thin separate placeholder pages.
- **Account**: identity summary, current plan and usage, edit profile, view plans, and sign out.

Settings behavior:

- Opening Settings can accept an initial section so account-popover shortcuts deep-link correctly.
- Maintain one settings navigation model rather than separate disconnected account dashboards.
- Use switches, segmented controls, cards, and rows consistently.
- Provide accurate prototype copy for simulated actions; do not imply server persistence, real billing, or irreversible backend deletion.

### 8. Implement the Signed-Out Prototype State

After confirmation:

- Set the in-app authenticated state to false and close all overlays.
- Render a polished, full-height Garad sign-in state using the existing brand assets and visual tokens.
- Explain that this is a prototype session.
- Provide “Continue as Mohamed Ali” to restore the session and return to the conversation workspace.
- Keep this state intentionally small; do not build a full authentication flow, route, password form, or provider integration.

### 9. Apply a Focused Visual Polish Pass

Update `src/index.css` to support the new components and improve consistency across existing ones.

- Refine spacing rhythm, sidebar row heights, typography hierarchy, radii, borders, selected states, and shadows.
- Make the violet brand color intentional for active/upgrade states while keeping routine navigation neutral.
- Add semantic styling for plan badges, usage meter, pricing cards, grouped menu sections, form validation, account popover, and signed-out screen.
- Improve hover, active, disabled, focus-visible, and destructive states.
- Ensure all new surfaces use existing light/dark CSS variables and remain legible in both themes.
- Respect reduced motion through the existing `data-motion` and `prefers-reduced-motion` rules.
- Keep imported fonts and existing brand assets; do not add a new font or photography for account/settings surfaces.
- Avoid broad restyling of conversation content, artifacts, or previews except where shared tokens need a small consistency adjustment.

### 10. Responsive and Accessibility Requirements

- Desktop: anchored account popover stays within viewport; wide settings/pricing modals fit common laptop heights.
- Tablet: sidebar remains a drawer; modal layouts compress without clipped actions.
- Mobile: account popover becomes a bottom sheet, pricing cards stack, settings navigation remains reachable, dialog footers remain visible, and forms do not overflow.
- Preserve semantic buttons, labels, `role="dialog"`, `aria-modal`, switch semantics, and meaningful `aria-label`s.
- Add `aria-expanded`/`aria-haspopup` where appropriate for account and menu triggers.
- Ensure keyboard Escape closes the top-level surface and focus-visible styling remains clear.
- Do not use color alone to communicate current plan, active settings section, or validation errors.

## File Scope

- `src/App.tsx`
  - typed account/plan/personalization fixtures and state;
  - account popover;
  - pricing modal;
  - editable profile and complete settings content;
  - sign-out confirmation and signed-out state;
  - required icon additions and interaction wiring.
- `src/index.css`
  - sidebar/account hierarchy refinements;
  - account popover, pricing, settings, profile, validation, and signed-out styles;
  - dark, responsive, collapsed-sidebar, mobile-sheet, and reduced-motion coverage.
- `src/assets/garad-ai-mark.png` and `src/assets/garad-ai-wordmark.png`
  - reuse only; no asset modification.

No router, backend, authentication SDK, payment provider, new package, or separate dashboard page is planned.

## Verification Strategy

1. Run the repository formatter command after implementation: `pnpm format`.
2. Run the broad compile/build check because this is a substantial JSX/state/CSS change: `pnpm build`.
3. Use the already-running preview; do not start another development server.
4. Exercise the core regression path:
   - new chat, search, select conversation, collapsed sidebar, and mobile drawer;
   - Chat/Agents switching and sending a simulated prompt;
   - tools/model popovers and existing modal interactions;
   - artifact preview and context panel.
5. Exercise all new account paths:
   - open/close account popover by click, outside click, and Escape;
   - open Personalization, Settings, Profile, Help, and Pricing from the popover;
   - edit and save profile, confirming sidebar/popover/settings synchronize;
   - change personalization and settings controls;
   - switch plans and confirm all current-plan labels synchronize;
   - cancel and confirm sign out, then restore with “Continue as Mohamed Ali.”
6. Visually review light and dark themes at desktop, tablet, and mobile widths, paying particular attention to popover placement, modal height/scrolling, stacked pricing cards, settings navigation, collapsed-sidebar access, focus indicators, and long text truncation.
7. Confirm there are no placeholder Profile/Plan/Usage actions left in the redesigned account flow and no navigation to nonexistent auth or billing routes.

## Acceptance Criteria

- The sidebar has a clear, polished hierarchy and remains fully functional in expanded, collapsed, and mobile states.
- Account identity and plan data come from one state source and stay synchronized across every surface.
- The account card opens a real account popover rather than a generic profile modal.
- Profile, Personalization, Settings, Plan/Pricing, and Sign-out are complete interactive prototype flows, not toast-only placeholders.
- Pricing clearly identifies the current plan and supports simulated plan changes without pretending to process payment.
- Sign-out produces a recoverable in-app signed-out state, not a broken route.
- Existing Chat, Agents, composer, conversation, artifact, preview, project, notification, voice, and theme behavior remains intact.
- New UI is coherent in light and dark mode, responsive down to mobile, keyboard-operable, and consistent with Garad’s violet brand and existing typography.
- Formatting and production build complete successfully, or any unrelated/environmental failure is reported precisely.
