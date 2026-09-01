## Language: English only

All code and all product output is in **English** — always, with no exceptions
unless the user explicitly asks for another language in a specific place.

This covers: UI copy, labels, headings, tooltips, empty states, aria-labels,
chart legends and category labels (e.g. "Other", "No source"), error and
validation messages, page titles and breadcrumbs, code comments, commit
messages, variable/function/class names, and test descriptions.

No Spanglish and no mixed-language sentences. If you find Spanish text while
working in a file, translate it as part of the change. Chat replies to the user
follow the user's language; the repo does not.

## Domain traps (read before writing code — these are not visible in the files)

These are hard-won gotchas specific to this repo. Each one is a bug that already
cost real debugging time. Treat them as rules, not suggestions. Run the relevant
verification before considering any task done.

### Power BI / DAX
- `SELECTCOLUMNS` results come back from Power BI with the column aliases WRAPPED
  (the engine returns them enveloped, not as the bare alias you wrote). Unwrap the
  aliases before consuming the result — never index the response by the raw alias
  and assume it maps directly. When you touch any code that reads a DAX query
  result, verify the actual shape of what Power BI returns, not what the DAX text
  implies.

### Salesforce
- Salesforce record IDs exist in two forms: 15-char (case-sensitive) and 18-char
  (case-insensitive). NEVER compare IDs across forms directly — a 15-char and an
  18-char ID for the same record will not be `===` equal. Normalize to 18 chars
  before any comparison, lookup key, dedupe, or map. If you introduce an ID
  comparison, confirm both sides are normalized first.

### React
- `react-hooks` lint rules are treated as ERRORS in this project, not warnings.
  In particular `react-hooks/set-state-in-effect`: do not call a state setter
  synchronously inside a `useEffect` in a way that triggers the rule. Follow the
  Rules of Hooks strictly (no conditional hooks, correct dependency arrays,
  no setState-in-effect patterns). A component that reads fine can still fail the
  linter — passing the file read is not passing.

### ESLint / style
- This project uses `@stylistic` for formatting/style rules in ESLint. Match the
  existing style; do not fight the stylistic ruleset or introduce a different
  formatting convention.

## Verification is mandatory (do not report success without it)
Before marking any task complete, actually RUN the checks — do not claim green
from reading the code:
- Backend tests via the project's test runner (e.g. `php artisan test` / Pest).
- Frontend: run the linter (ESLint with `@stylistic` + `react-hooks`), the type
  checker, and the build. A UI task is not done until the linter is green.
Report the actual command output (pass/fail), not an assumption.
