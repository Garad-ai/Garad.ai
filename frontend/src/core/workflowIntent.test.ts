import assert from "node:assert/strict"

import test from "node:test"

import { isWorkflowCreationPrompt } from "./workflowIntent.ts"

test("detects an explicit natural-language workflow creation request", () => {
  assert.equal(
    isWorkflowCreationPrompt(
      "Create a workflow that qualifies every new lead.",
    ),

    true,
  )

  assert.equal(
    isWorkflowCreationPrompt("Please automate our lead follow-up workflow."),

    true,
  )
})

test("does not classify unrelated chat or workflow status questions as creation", () => {
  assert.equal(
    isWorkflowCreationPrompt("Explain how workflow approvals work."),
    false,
  )

  assert.equal(isWorkflowCreationPrompt("Run the workflow now."), false)
})
