# AI Matching V2 audit and implementation

**Scope:** Matching eligibility, V1 scoring, lexical text comparison, ranking, and result persistence in CodeIgniter 3 / PHP 7.4. No database schema changes or data mutations were made.

## Implemented behavior

### Eligibility

- `Ai_match_model::get_candidates()` in [Ai_match_model.php](./application/models/Ai_match_model.php) selects active users with a profile and excludes the requesting user in SQL.
- `Ai::passes_hard_filters()` in [Ai.php](./application/controllers/Ai.php) validates candidate gender against the live `users.gender` enum (`male`, `female`, `other`). Male requesters retain the existing female-only rule; female requesters retain the existing male-only rule. For requester gender `other`, the existing behavior is retained: no opposite-gender restriction is applied. No product rule for `other` was found.
- DOB is required for eligibility. `Ai::calculate_age()` accepts only a real `YYYY-MM-DD` calendar date that is not in the future. Missing, malformed, impossible, and future dates are ineligible; age is calculated from the `users.date_of_birth` column.
- Configured age bounds are independently applied, including one-sided ranges. Invalid bounds (non-integer, outside 18–100, or inverted) return a JSON validation error instead of being silently ignored.
- A specifically selected, non-`any` marital status is required; missing or different candidate status excludes the candidate.
- The pre-existing behavior for known location, religion, and community mismatches remains: a populated preference and a populated, different profile value exclude a candidate. Missing candidate values still pass these checks. Whether these fields are mandatory is not documented, and `preferred_country` defaults to India in the live schema, so missing values were not converted into hard exclusions without a product decision.

### Scoring

- `Ai::calculate_score()` awards points only for actual comparisons. Education uses a normalized exact match; the live values (`BCA`, `Bachelor`) do not establish a reliable education ordering, so different or missing values are unknown rather than guessed matches or mismatches.
- Profession compares normalized whole words/phrases. Structured lifestyle preferences (`eating_habit`, `smoking_habit`, `drinking_habit`) are compared to the corresponding candidate profile values selected by `get_candidates()`.
- A populated bio alone no longer earns lifestyle, family-values, or general-preference points. Family values and free-text lifestyle/general preferences without comparable structured candidate data remain unknown.
- Each V1 component has a maximum weight totaling 100. Score coverage is comparable component weight divided by the full 100-point scale. Unknown weight contributes a neutral value of 50 and shrinks observed V1 compatibility toward neutral:

  `adjusted V1 = 50 + (observed comparable score - 50) * coverage`

  This prevents a small number of matches from producing an unsupported high score without treating missing values as mismatches. The API exposes `score_coverage`, `comparable_components`, and `unknown_components`.

### Text comparison and final score

- `Ai_match_model::build_semantic_profile_text()` now returns values keyed by comparable fields (for example, preferred city against candidate city). Field labels are not tokenized; values are normalized for case and punctuation in `tokenize_text()`.
- `calculate_text_similarity()` computes lexical Jaccard overlap only for fields present on both sides. This is word overlap, not embedding-based semantic AI.
- **Phase 3 correction:** the builder maps only corresponding preference/profile fields, and the comparator accepts only that explicit field allowlist. Free-text preference fields (`lifestyle`, `family_values`, `other_preferences`) and candidate `bio` are not paired because the schema has no corresponding candidate-value fields; they are excluded from lexical similarity instead of being concatenated into an unrelated text blob. Missing/empty or tokenless corresponding values are unavailable; one missing field does not invalidate other comparable fields.
- Both V1 and lexical scores are bounded from 0 to 100. When comparable text exists, the final score retains the 80% V1 / 20% lexical blend. When text is unavailable, a neutral 50 is used for the text component’s 20% weight. The combined score is clamped and rounded to two decimals only before response/storage.
- Eligible candidates are sorted by final score descending, then candidate ID ascending. Hard-filter failures are never added to the response.

### Persistence and API

- `Ai_match_model::save_match_result()` appends match history; it does not update or delete prior results. This is the repeated-run policy because the live schema has no unique `(user_id, matched_user_id)` constraint. `get_matches()` orders history newest first, then by score and deterministic identifiers.
- The insert stores the session requester ID, candidate ID, and the same two-decimal score returned by the API. The existing nullable component columns are used; no schema was changed.
- Database debug output is temporarily disabled around the insert and restored afterward. Database error detail is written to server logs, while the public response contains only a safe message.
- Existing JSON fields (`success`, `algorithm_version`, `user_id`, `matches_generated`, `matches_saved`, and `matches`) remain. Per-match `saved` flags and score evidence fields are additive. Partial/total failures report actual successful inserts and a safe error; an empty result set is identified as `no_matches`.

## Regression and integration checks

- `tests/ai_hard_filters_test.php` is a standalone mocked PHP test harness. It covers DOB boundaries and invalid/future values, one-sided age ranges, gender behavior including `other`, required marital status, normalized location comparison, score evidence/coverage, unknown education/lifestyle values, bio-only scoring, text-label exclusion, identical and different values under matching fields, case/whitespace/punctuation normalization, empty/missing/unrelated fields, text score bounds and unavailable state, the 80/20 blend, deterministic ties, empty candidates, repeated runs, successful/failed writes, saved identifiers/scores, and API contract fields.
- PHP 7.4.33 syntax checks passed for `Ai.php`, `Ai_match_model.php`, and the test harness.
- The combined mocked harness passed **100 assertions**, including Phase 3 text cases and both available/unavailable blend behavior. It uses mocked records and inserts; it does not mutate the local database.
- Read-only MySQL checks confirmed the lifestyle columns, match-result ordering columns, gender enum, DOB validity counts, and revised SQL projection against the actual local schema.
- No authenticated browser request or real database insert was replayed during these checks.

## Remaining product-rule limitation

The project provides no separate policy specifying whether missing location, religion, or community data should exclude a candidate, and no rule defining eligibility for requester gender `other`. Existing behavior is preserved and documented rather than inventing those rules. The local database had no `other` gender rows, so that path was verified with mocks only.
