import type { ScheduledTask } from "./types"

export type ScheduleTaskType = "reminder" | "agent_task" | "monitor"
export type ScheduleRecurrence = "once" | "daily" | "weekdays" | "weekly" | "monthly"

export type ScheduledTaskIntent = {
  intent: "scheduled_task"
  taskType: ScheduleTaskType
  title: string
  instruction: string
  scheduleType: NonNullable<ScheduledTask["scheduleType"]>
  schedule: string
  timezone: string
  date: string
  time: string
  recurrence: ScheduleRecurrence
  notificationEnabled: boolean
  cron: string
  runAt: string
  opportunity: boolean
  requiresConfirmation: boolean
}

const dayNames = [
  "sunday",
  "monday",
  "tuesday",
  "wednesday",
  "thursday",
  "friday",
  "saturday",
] as const

const titleCase = (value: string): string =>
  value
    .replace(/\s+/g, " ")
    .trim()
    .split(" ")
    .filter(Boolean)
    .map((part, index) => {
      const normalized = part.replace(/[.,!?;:()\[\]"']/g, "")
      if (!normalized) return ""
      if (/^[A-Z0-9]{2,5}$/.test(normalized)) return normalized
      const lower = normalized.toLowerCase()
      if (index > 0 && /^(?:a|an|and|at|for|from|in|of|on|or|the|to|with|my|me)$/.test(lower)) {
        return lower
      }
      return lower.charAt(0).toUpperCase() + lower.slice(1)
    })
    .join(" ")

const normalizeWhitespace = (value: string): string =>
  value.replace(/\s+/g, " ").trim()

const getTimezone = (): string => {
  try {
    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone
    if (timezone) {
      new Intl.DateTimeFormat("en", { timeZone: timezone })
      return timezone
    }
  } catch {
    // Fall through to Garad's configured application default.
  }
  return "Africa/Mogadishu"
}

const toDateInput = (date: Date): string => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, "0")
  const day = String(date.getDate()).padStart(2, "0")
  return `${year}-${month}-${day}`
}

const addDays = (date: Date, days: number): Date => {
  const result = new Date(date)
  result.setDate(result.getDate() + days)
  return result
}

const findTime = (value: string): string | null => {
  const match = value.match(
    /\b(?:at|@)\s*(\d{1,2})(?::(\d{2}))?\s*(am|pm)?\b|\b(\d{1,2})(?::(\d{2}))?\s*(am|pm)\b/i,
  )
  if (!match) return null

  let hour = Number(match[1] || match[4])
  const minute = Number(match[2] || match[5] || 0)
  const meridiem = (match[3] || match[6] || "").toLowerCase()
  if (minute > 59 || hour > 23 || (meridiem && (hour < 1 || hour > 12))) return null
  if (meridiem === "pm" && hour < 12) hour += 12
  if (meridiem === "am" && hour === 12) hour = 0
  return `${String(hour).padStart(2, "0")}:${String(minute).padStart(2, "0")}`
}

const findDate = (value: string, now = new Date()): string => {
  const lower = value.toLowerCase()
  const exactDate = value.match(/\b(20\d{2}-\d{2}-\d{2})\b/)
  if (exactDate) return exactDate[1]
  if (/\btomorrow\b/.test(lower)) return toDateInput(addDays(now, 1))
  if (/\btoday\b|\blater today\b|\btonight\b/.test(lower)) return toDateInput(now)
  if (/\bnext week\b/.test(lower)) return toDateInput(addDays(now, 7))
  const inDays = lower.match(/\bin\s+(\d+)\s+days?\b/)
  if (inDays) return toDateInput(addDays(now, Number(inDays[1])))
  const inWeeks = lower.match(/\bin\s+(\d+)\s+weeks?\b/)
  if (inWeeks) return toDateInput(addDays(now, Number(inWeeks[1]) * 7))

  const dayMatch = lower.match(
    /\b(next\s+)?(sunday|monday|tuesday|wednesday|thursday|friday|saturday)\b/,
  )
  if (!dayMatch) return ""
  const target = dayNames.indexOf(dayMatch[2] as (typeof dayNames)[number])
  let offset = (target - now.getDay() + 7) % 7
  if (dayMatch[1] || offset === 0) offset += 7
  return toDateInput(addDays(now, offset))
}

const taskInstruction = (value: string): string => {
  let instruction = normalizeWhitespace(value)
    .replace(/^(?:please|can you|could you|would you|kindly)\s+/i, "")
    .replace(/^(?:remind me|reminder)\s+(?:to\s+)?/i, "")
    .replace(/^(?:schedule|create|set up)\s+(?:a\s+)?(?:reminder|task)\s+(?:to\s+)?/i, "")
    .replace(/\b(?:tomorrow|today|tonight|next week|every day|daily|weekdays|every weekday|every (?:monday|tuesday|wednesday|thursday|friday|saturday|sunday)|monthly|every month|next (?:monday|tuesday|wednesday|thursday|friday|saturday|sunday))\b/gi, "")
    .replace(/\b(?:at|@)\s*\d{1,2}(?::\d{2})?\s*(?:am|pm)?\b/gi, "")
    .replace(/^\s*(?:to|that)\s+/i, "")
    .replace(/\s+/g, " ")
    .replace(/^[\s,;:!?.-]+|[\s,;:!?.-]+$/g, "")
  return instruction || normalizeWhitespace(value)
}

const inferTitle = (instruction: string): string => {
  const cleaned = instruction
    .replace(/^(?:i need to|i have to|i should|i must|don't forget to|remember to)\s+/i, "")
    .replace(/^(?:please\s+)?(?:submit|call|review|prepare|finish|complete|check|renew|create|generate)\s+/i, (verb) => verb.trim() + " ")
  return titleCase(cleaned.slice(0, 52)) || "Scheduled reminder"
}

const getScheduleFields = (
  value: string,
): Pick<ScheduledTaskIntent, "date" | "time" | "recurrence" | "scheduleType" | "cron"> => {
  const lower = value.toLowerCase()
  const time = findTime(value)
  const date = findDate(value)
  let recurrence: ScheduleRecurrence = "once"
  if (/every\s+2\s+hours|every\s+two\s+hours/.test(lower)) recurrence = "daily"
  else if (/weekdays|every weekday/.test(lower)) recurrence = "weekdays"
  else if (/every day|daily|every morning|every evening|every night/.test(lower)) recurrence = "daily"
  else if (/every month|monthly|first day of every month|first day of the month/.test(lower)) recurrence = "monthly"
  else if (/every (?:monday|tuesday|wednesday|thursday|friday|saturday|sunday)/.test(lower)) recurrence = "weekly"

  const normalizedTime = time || (recurrence === "once" ? "" : "08:00")
  const [hour = "8", minute = "0"] = normalizedTime.split(":")
  let cron = ""
  if (recurrence === "daily") {
    cron = /every\s+2\s+hours|every\s+two\s+hours/.test(lower)
      ? "0 */2 * * *"
      : `${Number(minute)} ${Number(hour)} * * *`
  } else if (recurrence === "weekdays") {
    cron = `${Number(minute)} ${Number(hour)} * * 1-5`
  } else if (recurrence === "weekly") {
    const day = dayNames.findIndex((name) => lower.includes(name))
    cron = `${Number(minute)} ${Number(hour)} * * ${day < 0 ? 1 : day}`
  } else if (recurrence === "monthly") {
    cron = `${Number(minute)} ${Number(hour)} 1 * *`
  }

  const scheduleType: NonNullable<ScheduledTask["scheduleType"]> =
    recurrence === "once" ? "one_time" : recurrence === "daily" || recurrence === "weekdays" ? "daily" : recurrence
  return { date, time: normalizedTime, recurrence, scheduleType, cron }
}

const hasActionSignal = (value: string): boolean =>
  /\b(remind(?:er)?|don't forget|remember to|need to|have to|should|must|call|meeting|appointment|deadline|submit|renew|prepare|review|finish|complete|due|generate|create|summarize|draft|report|news)\b/i.test(value)

const getScheduleText = (
  date: string,
  time: string,
  recurrence: ScheduleRecurrence,
): string => {
  const timeLabel = time
    ? new Date(`2000-01-01T${time}:00`).toLocaleTimeString([], {
        hour: "numeric",
        minute: "2-digit",
      })
    : ""
  if (recurrence === "daily") return `Every day${timeLabel ? ` at ${timeLabel}` : ""}`
  if (recurrence === "weekdays") return `Every weekday${timeLabel ? ` at ${timeLabel}` : ""}`
  if (recurrence === "weekly") {
    const weekday = date ? new Date(`${date}T12:00:00`).toLocaleDateString([], { weekday: "long" }) : "Monday"
    return `Every ${weekday}${timeLabel ? ` at ${timeLabel}` : ""}`
  }
  if (recurrence === "monthly") return `First day of every month${timeLabel ? ` at ${timeLabel}` : ""}`
  if (!date) return "Choose a date and time"
  const dateLabel =
    date === toDateInput(addDays(new Date(), 1))
      ? "Tomorrow"
      : new Date(`${date}T12:00:00`).toLocaleDateString([], {
          weekday: "long",
          month: "short",
          day: "numeric",
        })
  return `${dateLabel}${timeLabel ? ` · ${timeLabel}` : ""}`
}

const recurrenceCron = (
  recurrence: ScheduleRecurrence,
  date: string,
  time: string,
): string => {
  if (!time || recurrence === "once") return ""
  const [hours, minutes] = time.split(":").map(Number)
  if (recurrence === "daily") return `${minutes} ${hours} * * *`
  if (recurrence === "weekdays") return `${minutes} ${hours} * * 1-5`
  if (recurrence === "monthly") return `${minutes} ${hours} 1 * *`
  const day = date ? new Date(`${date}T12:00:00`).getDay() : 1
  return `${minutes} ${hours} * * ${day}`
}

const zonedDateTimeToIso = (date: string, time: string, timezone: string): string => {
  const [year, month, day] = date.split("-").map(Number)
  const [hour, minute] = time.split(":").map(Number)
  const target = Date.UTC(year, month - 1, day, hour, minute)
  let candidate = target
  const formatter = new Intl.DateTimeFormat("en-CA", {
    timeZone: timezone,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    hourCycle: "h23",
  })
  for (let attempt = 0; attempt < 3; attempt += 1) {
    const parts = Object.fromEntries(
      formatter
        .formatToParts(new Date(candidate))
        .filter((part) => part.type !== "literal")
        .map((part) => [part.type, Number(part.value)]),
    )
    const represented = Date.UTC(
      parts.year,
      parts.month - 1,
      parts.day,
      parts.hour,
      parts.minute,
    )
    candidate += target - represented
  }
  return new Date(candidate).toISOString()
}

export const updateScheduleFields = (
  current: ScheduledTaskIntent,
  patch: Partial<Pick<ScheduledTaskIntent, "date" | "time" | "recurrence" | "timezone" | "notificationEnabled">>,
): ScheduledTaskIntent => {
  const date = patch.date ?? current.date
  const time = patch.time ?? current.time
  const recurrence = patch.recurrence ?? current.recurrence
  let timezone = patch.timezone ?? current.timezone
  try {
    new Intl.DateTimeFormat("en", { timeZone: timezone })
  } catch {
    timezone = current.timezone
  }
  const scheduleType: NonNullable<ScheduledTask["scheduleType"]> =
    recurrence === "once" ? "one_time" : recurrence === "daily" || recurrence === "weekdays" ? "daily" : recurrence
  const ready = Boolean(date && time)
  return {
    ...current,
    date,
    time,
    recurrence,
    notificationEnabled:
      patch.notificationEnabled ?? current.notificationEnabled,
    timezone,
    scheduleType,
    cron: recurrenceCron(recurrence, date, time),
    runAt: recurrence === "once" && ready
      ? zonedDateTimeToIso(date, time, timezone)
      : "",
    schedule: getScheduleText(date, time, recurrence),
  }
}

export const detectScheduledTaskIntent = (
  rawPrompt: string,
): ScheduledTaskIntent | null => {
  const prompt = normalizeWhitespace(rawPrompt)
  if (!prompt) return null

  const lower = prompt.toLowerCase()
  const explicitSchedule = /\b(?:remind me|reminder|schedule|scheduled|every\s+(?:day|morning|evening|night|weekday|monday|tuesday|wednesday|thursday|friday|saturday|sunday|month)|daily|weekdays|monthly|tomorrow|next week|tonight|next\s+(?:monday|tuesday|wednesday|thursday|friday|saturday|sunday))\b/i.test(prompt)
  const opportunity =
    !explicitSchedule &&
    hasActionSignal(prompt) &&
    /\b(?:tomorrow|today|tonight|next week|next\s+(?:monday|tuesday|wednesday|thursday|friday|saturday|sunday)|\b(?:monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b)\b/i.test(prompt)
  if (!explicitSchedule && !opportunity) return null
  if (!hasActionSignal(prompt)) return null

  const taskType: ScheduleTaskType =
    /\b(?:watch for|notify me when|monitor|check for|scan for)\b/i.test(prompt)
      ? "monitor"
      : /\b(?:generate|create|summarize|prepare|draft|report|news)\b/i.test(prompt) &&
          !/\bremind(?:er)?\b/i.test(prompt)
        ? "agent_task"
        : "reminder"
  const instruction = taskInstruction(prompt)
  const fields = getScheduleFields(prompt)
  const time = fields.time || (fields.date ? "09:00" : "")
  const timezone = getTimezone()
  const schedule = getScheduleText(fields.date, time, fields.recurrence)
  const date = fields.date
  const runAt =
    fields.recurrence === "once" && date && time
      ? `${date}T${time}:00`
      : ""
  return {
    intent: "scheduled_task",
    taskType,
    title: inferTitle(instruction),
    instruction,
    scheduleType: fields.scheduleType,
    schedule,
    timezone,
    date,
    time,
    recurrence: fields.recurrence,
    notificationEnabled: true,
    cron: fields.cron,
    runAt: runAt ? zonedDateTimeToIso(date, time, timezone) : "",
    opportunity,
    requiresConfirmation: true,
  }
}

export const updateScheduledTaskIntent = (
  current: ScheduledTaskIntent,
  text: string,
): ScheduledTaskIntent | null => {
  const fields = getScheduleFields(text)
  if (!fields.date && !fields.time && fields.recurrence === "once") return null
  const date = fields.date || current.date
  const time = fields.time || current.time
  const recurrence =
    fields.recurrence !== "once" ? fields.recurrence : current.recurrence
  const normalizedTime = time || (date ? "09:00" : "")
  const scheduleType: NonNullable<ScheduledTask["scheduleType"]> =
    recurrence === "once" ? "one_time" : recurrence === "daily" || recurrence === "weekdays" ? "daily" : recurrence
  return updateScheduleFields(
    { ...current, recurrence, scheduleType },
    { date, time: normalizedTime, recurrence },
  )
}

export const scheduleTaskIsReady = (intent: ScheduledTaskIntent): boolean => {
  if (intent.date) {
    const [year, month, day] = intent.date.split("-").map(Number)
    const date = new Date(Date.UTC(year, month - 1, day))
    if (
      !Number.isFinite(date.getTime()) ||
      date.getUTCFullYear() !== year ||
      date.getUTCMonth() + 1 !== month ||
      date.getUTCDate() !== day
    )
      return false
  }

  const timeMatch = /^([01]\d|2[0-3]):([0-5]\d)$/.exec(intent.time)
  if (!timeMatch) return false

  try {
    new Intl.DateTimeFormat("en", { timeZone: intent.timezone })
  } catch {
    return false
  }

  if (intent.recurrence === "once") {
    const runAt = Date.parse(intent.runAt)
    return Boolean(
      intent.date &&
        Number.isFinite(runAt) &&
        runAt > Date.now(),
    )
  }

  return Boolean(intent.cron.trim())
}
