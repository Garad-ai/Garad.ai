export const isWorkflowCreationPrompt = (prompt: string): boolean =>
  /\b(create|build|set up|make|automate)\b[\s\S]{0,120}\b(workflow|automation)\b/i.test(
    prompt,
  )
