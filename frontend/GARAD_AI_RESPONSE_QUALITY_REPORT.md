# Garad AI Response Quality Report
- Normalizer (`src/core/normalizeResponse.ts`) strips `<br/>`, `:::meta`/`...meta`, entity/suggestion JSON, tool HTML cards and markers; preserves code, tables, lists, links.
- Markdown rendered via react-markdown + remark-gfm with code Copy button and scrollable tables.
- Status line (Thinking/Working/Generating) disappears once content arrives; no artificial timers.
- Errors shown as "Response failed" with collapsible technical details.
- Verified in browser: "Tell me about Garad AI" and a fruit table render clean (PASS).
- Not verified: Deep Research / Web Search long runs. Known backend issue: stream sometimes truncates mid-table (not changed).
