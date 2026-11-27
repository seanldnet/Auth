# Frontend UI Standard

## Mandatory component system

The entire Auth frontend must use **shadcn/ui** as its single UI component system.

This is a project requirement, not a preference:

- Do not hand-write replacement buttons, inputs, selects, textareas, checkboxes, switches, dialogs, dropdowns, tabs, tables, tooltips, alerts, badges, cards, or other reusable UI controls.
- Use the corresponding component from `frontend/src/components/ui` (shadcn/ui conventions and Radix UI primitives).
- If a required shadcn/ui component is missing, add the standard shadcn/ui component first, then use it. Do not improvise a one-off control inside a page.
- Page files may contain business-specific composition and layout, but interactive controls and common visual primitives must come from the shared shadcn/ui component library.
- Do not introduce a second UI framework or mix component libraries.
- Keep spacing, typography, colors, focus states, disabled states, loading states, validation, empty states, and responsive behavior consistent across every route.

## Refactor requirements

- Migrate every public and authenticated route, including every button and form control.
- Preserve API contracts, business behavior, role-based visibility, authorization checks, and existing data flows.
- Do not replace live data with fabricated examples or placeholder success states.
- Use accessible labels, keyboard navigation, focus management, and semantic elements.
- Verify TypeScript, frontend production build, Docker image build, and Docker smoke tests before declaring a version ready for user testing.

## Review checklist

- [ ] No raw HTML `button`, `select`, or `textarea` controls in page components.
- [ ] No page-local hand-built reusable modal/dropdown/table/control implementations.
- [ ] All shared controls are sourced from `frontend/src/components/ui`.
- [ ] Every route has consistent page heading, actions, content spacing, loading/error/empty states, and mobile layout.
- [ ] Existing API and permission behavior remains unchanged.
- [ ] CI and Docker smoke tests pass.
