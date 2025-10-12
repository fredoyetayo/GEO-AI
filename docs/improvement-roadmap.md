# GEO AI Plugin Improvement Roadmap

The Gemini-powered audit delivers scoring, issue tracking, and AI suggestions inside the editor UI, but there are several enhancements that can increase adoption and effectiveness for answer-engine optimization. The ideas below are grouped by theme so we can prioritize iteratively.

## 1. Elevate Trust and Transparency Signals
- **Expose source evidence inside the audit sidebar.** Persist citation metadata from the Gemini response (currently surfaced only as plain URLs) so editors can attach them to content blocks with one click, reinforcing E-E-A-T for ChatGPT/Perplexity snapshots.
- **Track historical scores.** Store each audit run in a custom post type so strategists can demonstrate improvement over time and correlate changes with LLM ranking gains.

## 2. Improve Author Workflow Efficiency
- **Inline quick fixes.** Instead of reloading the page after applying a quick fix via `applyQuickFix`, update the Gutenberg document state in place to avoid breaking user focus.
- **Last-run reminders.** Surface the `_geoai_audit_timestamp` meta in the sidebar and show a warning when the audit is older than 7 days, prompting reruns before publishing updates.

## 3. Deepen Gemini Analysis Coverage
- **Schema enhancement planner.** Extend the Gemini prompt so `schema.errors` contains actionable remediation steps (e.g., FAQ needs more than two questions), and add buttons to inject missing FAQ/HowTo blocks into Gutenberg automatically.
- **Competitive landscape snapshot.** Enrich the prompt with SERP context by passing in the target keyword and top-ranked competitor summaries, allowing Gemini to recommend differentiated talking points and backlinks to chase.

## 4. Strengthen Reliability and Governance
- **Usage metering and rate limiting.** Log each Gemini call with latency and token usage, and stop background audits when the API key exceeds quota. This prevents editors from seeing `WP_Error` responses during peak publishing windows.
- **Granular role capabilities.** Create custom capabilities (e.g., `geoai_run_audit`, `geoai_apply_fix`) to restrict high-cost operations to specific roles, ensuring agencies can control billing exposure.

## 5. Onboarding and Insight Sharing
- **Audit export & sharing.** Provide a `geoai/v1/audit-export` REST endpoint that returns the latest results as a PDF/CSV so SEO teams can share insights with clients not in WordPress.
- **Guided tour & tooltips.** Add contextual help tabs and introduction modals explaining how answer engines score content, turning first-time users into power users faster.

These improvements build on the existing Gemini integration implemented in `GeoAI_Analyzer` and the Gutenberg sidebar (`src/editor.js`) by adding transparency, advanced analysis, and operational safeguards.
