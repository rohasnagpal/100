# Indian Law 100: Legal AI Micro-Case Benchmark

Indian Law 100 is a browser-based benchmark for measuring how accurately an AI model applies Indian law to concrete facts. It contains 100 self-contained micro cases across 13 legal domains, 3 difficulty tiers, and deliberate traps for outdated or repealed law.

<img width="2838" height="2022" alt="image" src="https://github.com/user-attachments/assets/02b604c3-2f74-411a-aefb-ea96f47fc735" />

Each answer is evaluated on the legal outcome, governing authority, and quality of application. Results can be reviewed and overridden by a human, exported as auditable JSON, and published to a comparative leaderboard.

The benchmark runner is intentionally self-contained: the complete case battery, UI, OpenRouter integration, scoring workflow, recovery logic, and export code are embedded in `index.html`.

> **Legal-content notice:** The questions and gold answers were supplied by Rohas Nagpal, the benchmark maintainer. Codex and GPT-5.6 assisted with software development and data-structure validation, but did not independently verify every legal proposition. 

## Built with Codex and GPT-5.6

This project was developed with **Codex**, using **GPT-5.6** as the coding and reasoning model during the implementation workflow.

Codex provided the working environment for inspecting the existing website, editing the project, running local checks, and testing the interface in a real browser. GPT-5.6 was used within that workflow to reason through the benchmark architecture, translate the product brief into working code, harden failure handling, and prepare documentation.

### Specific contributions

| Area | How Codex and GPT-5.6 were used |
|---|---|
| Product architecture | Converted the benchmark concept into a browser runner, scoring pipeline, JSON result format, PHP save endpoint, and leaderboard. |
| Interface development | Recreated the visual language of the existing legal-AI projects and built the responsive three-column answer, gold-answer, and verdict layout. |
| Dataset integration | Parsed three supplied JSON arrays, verified 100 unique IDs, checked domain and difficulty totals, and embedded the records without changing their substantive text. |
| OpenRouter integration | Implemented the live model catalogue, provider-grouped selector, pricing display, streaming answers, API-key controls, credit checking, and generation settings. |
| Judge reliability | Designed the 50/30/20 grading request, strict JSON Schema, response-healing request, tolerant parser, automatic retry, and human-override fallback. |
| Recovery engineering | Implemented IndexedDB checkpoints, legacy-draft migration, interrupted-run restoration, and continuation from the first ungraded case. |
| Result integrity | Added finalised-result validation, secret-field rejection, unique-case checks, scoring bounds, admin-only server saves, and auditable JSON output. |
| Quality assurance | Used PHP linting, source-fidelity checks, browser console inspection, responsive UI review, and simulated interruption/reload tests. |
| Documentation | Produced and refined this README and the in-product explanations for model settings and benchmark behavior. |

### What the AI tools did not do

- They did not create or independently certify the supplied legal gold answers.
- They did not replace expert legal review or human benchmark governance.
- They did not submit benchmark scores to the leaderboard.
- They did not store or receive a user’s OpenRouter API key outside the browser workflow.
- They are not benchmark participants unless their corresponding model is deliberately selected through OpenRouter.

## Benchmark composition

| Domain | Cases |
|---|---:|
| Criminal law — BNS | 15 |
| Criminal procedure — BNSS | 8 |
| Evidence — BSA | 7 |
| Contract law | 10 |
| Constitutional law | 10 |
| Consumer protection | 8 |
| IT Act and cybercrime | 8 |
| Property and tenancy | 7 |
| Family law | 7 |
| Labour and employment | 6 |
| Company law | 5 |
| Tax and GST | 5 |
| PMLA, crypto, and FEMA | 4 |
| **Total** | **100** |

Difficulty distribution:

- 40 easy cases
- 40 medium cases
- 20 hard cases
- 13 cases explicitly marked as traps

The traps include IPC-to-BNS and CrPC-to-BNSS mapping issues, repealed provisions, outdated precedents, limitation problems, amendment traps, and other tests of whether a model is applying current law.

The original design target described fact patterns of 50–120 words. The supplied battery is structurally complete, although 29 supplied fact patterns are shorter than 50 words. They are preserved exactly as provided.

## Scoring

Every case is worth 100 points:

| Axis | Weight | What is evaluated |
|---|---:|---|
| Correct outcome | 50 | Whether the model reaches the legally correct result. |
| Correct legal basis | 30 | Whether it cites the controlling section, statute, or binding authority. |
| Reasoning and trap avoidance | 20 | Whether it applies the rule soundly and avoids the case’s known traps. |

The aggregate maximum is **10,000 points**. The UI also reports an overall percentage, domain breakdown, difficulty breakdown, trap performance, estimated API cost, and runtime.

Verdict bands used by the interface:

- **Correct:** 70–100 points
- **Partial:** 40–69 points
- **Incorrect:** 0–39 points

These labels are convenience bands. The complete axis scores remain in the exported JSON.

## How a run works

1. The user enters an OpenRouter API key.
2. The live OpenRouter catalogue is loaded and grouped by model provider.
3. The user selects one model and, optionally, a separate judge model.
4. Cases are sent sequentially and in isolation.
5. The tested model’s answer streams into the results table in real time.
6. The answer is graded against the supplied gold answer and runtime-generated rubric.
7. The score and explanation appear beside the answer.
8. The user can override any of the three scoring axes or use a correct, partial, or incorrect preset.
9. After all 100 cases have grades, the user finalises the run and downloads its JSON.
10. If the user has an authorised ROHAS admin session, the same JSON is also saved to `results/` and becomes available to the leaderboard.

The tested model is instructed to answer in no more than 180 words using three labelled parts: **Outcome**, **Legal basis**, and **Reasoning**.

## Model settings

| Setting | Purpose |
|---|---|
| Temperature | Controls randomness; lower values produce more repeatable answers. |
| Top P | Restricts sampling to the most likely probability mass. |
| Maximum output tokens | Caps the length of each tested-model answer. The recommended default is 400. |
| Reasoning effort | Requests a reasoning level from compatible models and providers. |
| Seed | Improves repeatability where the selected model supports seeded sampling. |
| Independent judge model | Uses a separate model to grade answers; if blank, the tested model grades itself. |

Recommended public-comparison settings are temperature `0`, Top P `1`, 400 output tokens, model-default reasoning, and no seed. Every setting is included in the exported run JSON.

For fair comparisons, use the same fixed judge model and settings for every model appearing on a public leaderboard.

## Judge reliability and fallback behavior

The judge request uses a strict four-field schema:

```json
{
  "outcome_score": 0,
  "legal_basis_score": 0,
  "reasoning_score": 0,
  "reason": "Brief grading explanation"
}
```

The runner requests OpenRouter structured output and response healing. It also handles common model deviations:

- JSON wrapped in Markdown fences
- JSON surrounded by explanatory prose
- score fields written without a valid enclosing object
- alternative `outcome`, `legal_basis`, and `reasoning` field names
- structured-output incompatibility at a selected provider

If the first judge response is unreadable, the runner makes one repair attempt. If both attempts fail, the tested model’s answer remains visible and is marked **Judge failed**. The user can then grade it with Human override instead of losing the answer or the run.

## Refresh-safe recovery with IndexedDB

Active runs are stored in the browser’s IndexedDB database:

- Database: `indian-law-100-benchmark`
- Object store: `active-runs`
- Active record key: `active`

The runner checkpoints:

- approximately every 750 ms while an answer is streaming;
- immediately before and after answer generation;
- before and after judging;
- after manual overrides;
- when the page becomes hidden or is being unloaded; and
- at the end of a complete, partial, failed, or cancelled run.

After a refresh, the UI restores the model, judge, generation settings, answers, grades, errors, usage, cost, and progress. The button changes to **Resume benchmark**.

When resuming:

- already graded cases are skipped;
- an interrupted partial answer is regenerated from the beginning;
- a completed answer that was waiting for its judge is reused;
- a failed judge is retried without regenerating the tested-model answer; and
- the recorded model and settings are reapplied to prevent an accidental mixed-model run.

Older local-storage drafts are migrated to IndexedDB automatically. IndexedDB failures fall back to the legacy local draft so that browser storage restrictions do not silently destroy a run.

The API key is deliberately separate from the benchmark record. It is stored in browser local storage only when the user presses **Save key**, and it can be removed with **Clear key**.

## Project structure

```text
micro-case-benchmark-india/
├── README.md              Project documentation
├── index.html             Self-contained benchmark runner and 100-case dataset
├── leaderboard.php        Result scanner, ranking, charts, and run archive
├── save-result.php        Authenticated validation and JSON save endpoint
└── results/
    └── .gitkeep           Destination for finalised result JSON files
```

There is no build step and no JavaScript package dependency.

## Local setup

Requirements:

- A modern browser with Fetch, streaming responses, IndexedDB, and `AbortController`
- PHP 8 or later for the leaderboard and server-side result saving
- An OpenRouter API key for real benchmark runs

From the website root:

```bash
php -S 127.0.0.1:8000
```

Open:

```text
http://127.0.0.1:8000/micro-case-benchmark-india/index.html
```

The runner can display and export results without an authenticated site session. Adding results to the server leaderboard requires the existing ROHAS admin session described below.

## Production deployment

1. Serve the folder from a PHP-capable HTTPS website.
2. Ensure the PHP process can create and write files inside `results/`.
3. Integrate the existing administrator login so an authorised session contains:

   ```php
   $_SESSION['rohas_admin'] = true;
   ```

4. Confirm that the parent site’s Content Security Policy permits requests to OpenRouter and any required Google Fonts assets.
5. Run a controlled test with a low-cost model before starting a full 100-case benchmark.
6. Back up finalised JSON files and review them before making public comparative claims.

The browser calls OpenRouter directly. This design keeps the API key away from the website server, but it means the site must be delivered in an environment where browser-to-OpenRouter requests are permitted.

## API-key and credit controls

- **Save key** stores the key only in the current browser’s local storage.
- **Clear key** removes it from browser storage and clears the field.
- **Check credits** validates the key through OpenRouter.
- Management or provisioning keys can retrieve account credit totals.
- Standard keys display the remaining key limit when available, or the key’s recorded usage.
- API keys are never included in result JSON.

For shared computers, do not use Save key. Clear the key after finishing a run.

## Result JSON

A finalised export contains:

- benchmark ID, version, runner revision, and law-as-of date;
- run ID and timestamps;
- selected model metadata and listed pricing;
- judge model;
- generation settings;
- all 100 case definitions;
- every model answer;
- token usage, latency, and estimated cost;
- judge output and retry count;
- final axis scores and manual-override flags; and
- overall, domain, difficulty, and trap summaries.

The export does **not** contain the OpenRouter API key or an Authorization header.

## Server-side result validation

`save-result.php` accepts only authenticated `POST` requests and enforces:

- benchmark ID `indian-law-100` and version `1.0`;
- finalised status and timestamp;
- a maximum request size of 2.5 MB;
- exactly 100 result records;
- a unique non-empty case ID for every result;
- all three numeric scoring axes;
- axis bounds of 50, 30, and 20;
- an overall percentage between 0 and 100; and
- rejection of fields named `api_key`, `apikey`, or `authorization`.

Accepted files receive a timestamped, randomised filename and are written with a lock to `results/`.

## Leaderboard behavior

`leaderboard.php` scans finalised JSON files in `results/` and displays:

- the latest finalised run for each model;
- overall score and rank;
- correct, partial, and incorrect counts;
- trap accuracy;
- per-domain bars;
- cost and finalisation time;
- aggregate performance by legal domain; and
- links to the original auditable JSON.

Models are ranked by percentage. A tie is broken by lower estimated cost and then model name. Earlier runs remain available in the run archive.

## Case record format

Cases follow this structure:

```json
{
  "id": "CRIM-BNS-001",
  "domain": "criminal_bns",
  "difficulty": "easy",
  "is_trap": true,
  "trap_type": "ipc_bns_mapping",
  "fact_pattern": "Self-contained facts...",
  "question": "One precise legal question...",
  "gold": {
    "outcome": "Verifiable legal result...",
    "legal_basis": ["Controlling section or judgment"],
    "reasoning_notes": "Required application and trap avoidance...",
    "common_wrong_answers": ["Known incorrect answer"]
  }
}
```

The runner generates a default grading rubric from `gold.outcome`, `gold.legal_basis`, and `gold.reasoning_notes`. A case may also provide its own `grading_rubric` object.

## Updating the battery

When changing questions:

1. Preserve unique and stable case IDs.
2. Keep `legal_basis` and `common_wrong_answers` as arrays.
3. Recheck the 13 domain totals and the 40/40/20 difficulty split.
4. Review every trap marker and trap type.
5. Have a qualified legal reviewer approve the facts, outcome, authority, and reasoning notes.
6. Replace the embedded `CASES` array in `index.html`.
7. Increase `BENCHMARK.version` when the substantive battery changes.
8. Update the accepted version in `save-result.php` at the same time.
9. Increase `runner_revision` for an incompatible runner change that should invalidate older active drafts without changing the legal battery.
10. Repeat browser, PHP, source-fidelity, and full-run checks.

Do not silently alter gold answers after public scores exist. Publish a new benchmark version so runs remain comparable and auditable.

## Known limitations

- Legal accuracy ultimately depends on the supplied gold answers and review process.
- LLM judging can be inconsistent; a fixed judge and human audit are recommended.
- OpenRouter pricing and model availability can change after a run.
- Cost figures are estimates based on the pricing returned by the model catalogue.
- Browser storage is origin-specific and can be removed by private browsing or site-data cleanup.
- A 100-case run makes at least one tested-model request and one judge request per case; retries increase time and cost.
- Dynamic routers can use different underlying models, reducing reproducibility.
- A seed improves repeatability only where supported and does not guarantee determinism.
- The current runner evaluates one model at a time.

## Recommended benchmark-governance practice

- Freeze and publish the exact benchmark version.
- Use a fixed independent judge and fixed generation settings.
- Retain the original JSON for every public score.
- Audit all judge failures and manual overrides.
- Report the tested OpenRouter model ID, not only its display name.
- Separate experimental runs from the public leaderboard.
- Re-run models when major Indian-law amendments take effect.
- Publish corrections and version changes transparently.

## Verification performed during development

- Confirmed 100 embedded cases and 100 unique IDs.
- Confirmed 13 legal domains.
- Confirmed the 40 easy, 40 medium, and 20 hard distribution.
- Confirmed 13 explicit trap cases.
- Compared the embedded cases with the supplied source records for exact equality.
- Ran PHP syntax checks on both PHP files.
- Loaded the runner and leaderboard in a browser and checked the console.
- Tested grouped model loading and setup readiness.
- Simulated an interrupted run, refreshed the page, and verified IndexedDB restoration and resume state.
- Cleared the test run and verified that its IndexedDB record and saved test key were removed.

## Responsible use

This benchmark is a research and evaluation tool. It is not legal advice, a substitute for professional review, or evidence that a model is safe for unsupervised legal practice. Scores should be interpreted alongside the dataset version, judge model, generation settings, failure logs, manual overrides, and the scope of the 100 cases.

