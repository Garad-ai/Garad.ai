import { strict as assert } from "node:assert"

import { buildSourceGroundedDocument, runDocumentGroundingChecks } from "./documentGrounding"

export const documentGroundingRegressionCases = [
  {
    name: "Arkaan weekly report",
    check: () => {
      const draft = buildSourceGroundedDocument(
        `I want you to make a weekly work report for my work at Arkaan.

This week:
- I worked on the Arkaan website.
- I added visual/design improvements.
- I attended about 3 meetings.

Meeting 1:
- Arkaan's main weekly meeting.
- Reporting was discussed.

Meeting 2:
- A meeting with Baxnaano.
- The purpose was to discuss/design a reporting system for monitoring where work/jobs are at and tracking progress.

Meeting 3:
- A meeting about the opening/launch event of Garad AI.

User identity:
- Name: Jabir Osman Gedi
- Title: ICT Coordinator`,
        { documentBrand: "Arkaan", platformBrand: "GaradAI" },
      )

      assert.equal(draft.brand.documentBrand, "Arkaan")
      assert.match(draft.content, /Jabir Osman Gedi/i)
      assert.match(draft.content, /Baxnaano/i)
      assert.match(draft.content, /launch|Garad AI/i)
      assert.doesNotMatch(draft.content, /Plan for the Coming Week|No blocking issues|five specific actions/i)
      assert.doesNotMatch(draft.content, /Garad AI logo|Garad AI watermark|Garad AI footer|Garad AI purple|Garad AI \| Intelligence for practical work/i)
      return true
    },
  },
  {
    name: "GaradAI report",
    check: () => {
      const draft = buildSourceGroundedDocument("Create a GaradAI report.", {
        documentBrand: "GaradAI",
        platformBrand: "GaradAI",
      })
      assert.equal(draft.brand.documentBrand, "GaradAI")
      return true
    },
  },
  {
    name: "third-party brand stays separate",
    check: () => {
      const draft = buildSourceGroundedDocument("Create a report for Acme Ltd. with their brand.", {
        documentBrand: "Acme Ltd.",
        platformBrand: "GaradAI",
      })
      assert.equal(draft.brand.documentBrand, "Acme Ltd.")
      assert.doesNotMatch(draft.content, /Garad AI|GaradAI/i)
      return true
    },
  },
  {
    name: "no brand report stays neutral",
    check: () => {
      const draft = buildSourceGroundedDocument("Create a neutral professional summary.", {
        documentBrand: "Neutral",
        platformBrand: "GaradAI",
      })
      assert.equal(draft.brand.documentBrand, "Neutral")
      assert.doesNotMatch(draft.content, /Garad AI|GaradAI/i)
      return true
    },
  },
  {
    name: "incomplete request does not invent data",
    check: () => {
      const draft = buildSourceGroundedDocument("Create a report.")
      assert.doesNotMatch(draft.content, /publication-ready|all objectives|completed|no blocking/i)
      return true
    },
  },
]

export function runDocumentGroundingRegressionSuite(): Record<string, boolean> {
  const results: Record<string, boolean> = {}

  for (const scenario of documentGroundingRegressionCases) {
    try {
      scenario.check()
      results[scenario.name] = true
    } catch (error) {
      results[scenario.name] = false
    }
  }

  return Object.assign(results, runDocumentGroundingChecks())
}
