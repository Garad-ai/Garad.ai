import { useMemo, useState } from "react"
import type { Dispatch, SetStateAction } from "react"
import AssistantMarkdown from "../core/AssistantMarkdown"
import { detectScheduledTaskIntent } from "../core/scheduledTaskParser"
import type { ScheduledTask } from "../core/types"
import ScheduledTaskProposalCard, {
  type ScheduleProposalState,
} from "./ScheduledTaskProposalCard"
import { updateScheduleFields } from "../core/scheduledTaskParser"

type Filter = "all" | "upcoming" | "active" | "paused" | "completed" | "failed"

type Props = {
  tasks: ScheduledTask[]
  canUse: boolean
  busy: boolean
  loading: boolean
  conversationId: string
  proposal: ScheduleProposalState | null
  setProposal: Dispatch<SetStateAction<ScheduleProposalState | null>>
  onSchedule: () => void
  onClose: () => void
  onUpgrade: () => void
  onToggle: (task: ScheduledTask) => Promise<void>
  onRun: (task: ScheduledTask) => Promise<void>
  onSave: (id: string, patch: Partial<ScheduledTask>) => Promise<void>
  onDelete: (task: ScheduledTask) => Promise<void>
  onCreated: () => void
}

type Question = {
  id: "frequency" | "time" | "language"
  title: string
  options: Array<{ label: string; value: string }>
  placeholder: string
}

const questions: Question[] = [
  {
    id: "frequency",
    title: "How often should this run?",
    options: [
      { label: "Every day", value: "every day" },
      { label: "Every weekday", value: "every weekday" },
      { label: "Every Monday", value: "every Monday" },
      { label: "Tomorrow only", value: "tomorrow" },
    ],
    placeholder: "Or type your own, e.g. every Friday",
  },
  {
    id: "time",
    title: "What time should it run?",
    options: [
      { label: "7:00 AM", value: "7:00 AM" },
      { label: "8:00 AM", value: "8:00 AM" },
      { label: "9:00 AM", value: "9:00 AM" },
      { label: "6:00 PM", value: "6:00 PM" },
    ],
    placeholder: "Or type a time, e.g. 8:30 AM",
  },
  {
    id: "language",
    title: "Which language should the result use?",
    options: [
      { label: "Somali", value: "Somali" },
      { label: "English", value: "English" },
      { label: "Both", value: "Somali and English" },
    ],
    placeholder: "Or type another language",
  },
]

const templates = [
  {
    icon: "☀️",
    title: "News updates",
    text: "Send me a daily roundup of news on topics I care about.",
  },
  {
    icon: "🎓",
    title: "Scholarship alerts",
    text: "Find new scholarships and opportunities I can apply for and send me the best five.",
  },
  {
    icon: "💼",
    title: "Job digest",
    text: "Find new software engineering jobs and send me the best five with source links.",
  },
  {
    icon: "🇸🇴",
    title: "Somalia & East Africa briefing",
    text: "Send me a briefing of the most important Somalia and East Africa news with sources.",
  },
  {
    icon: "📖",
    title: "Find weekend reads",
    text: "Send me a great article to read every Saturday.",
  },
  {
    icon: "🎉",
    title: "Plan my weekend",
    text: "Send me local weekend ideas every Thursday.",
  },
]

const cleanTitle = (value: string) => {
  const line = value.trim().replace(/\s+/g, " ").replace(/[.!?]+$/, "")
  return line.length > 60 ? `${line.slice(0, 57)}…` : line
}

const formatWhen = (value?: string, timezone?: string) => {
  if (!value) return "—"
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  try {
    return date.toLocaleString(undefined, {
      weekday: "short",
      month: "short",
      day: "numeric",
      year: "numeric",
      hour: "numeric",
      minute: "2-digit",
      ...(timezone ? { timeZone: timezone } : {}),
    })
  } catch {
    return value
  }
}

const describeCron = (task: ScheduledTask) => {
  const match = /^(\d+) (\d+) (\*|[\d,]+) (\*) (\*|[\d,\-]+)$/.exec(
    task.cron || "",
  )
  if (!match) return task.schedule || "—"
  const hour = Number(match[2])
  const minute = Number(match[1])
  const time = `${((hour + 11) % 12) + 1}:${String(minute).padStart(2, "0")} ${hour < 12 ? "AM" : "PM"}`
  if (match[3] === "*" && match[5] === "*") return `Every day at ${time}`
  if (match[3] === "*" && match[5] === "1-5") return `Weekdays at ${time}`
  if (match[3] === "*") return `Weekly at ${time}`
  return `Monthly at ${time}`
}

type Recurrence = "daily" | "weekdays" | "weekly" | "monthly" | "custom"

const cronParts = (cron?: string) => {
  const fields = (cron || "").trim().split(/\s+/)
  return fields.length === 5 ? fields : null
}

const recurrenceFromTask = (task: ScheduledTask): Recurrence => {
  const fields = cronParts(task.cron)
  if (!fields) {
    const schedule = task.schedule.toLowerCase()
    if (schedule.includes("weekday")) return "weekdays"
    if (schedule.includes("weekly") || /\b(monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/.test(schedule)) return "weekly"
    if (schedule.includes("monthly")) return "monthly"
    return "daily"
  }
  if (fields[2] !== "*") return "monthly"
  if (fields[4] === "1-5") return "weekdays"
  if (fields[4] !== "*") return "weekly"
  return "daily"
}

const scheduleTimeFromTask = (task: ScheduledTask) => {
  const time = /(?:at\s+)?(\d{1,2}):(\d{2})\s*(AM|PM)?/i.exec(task.schedule)
  if (!time) return null
  let hour = Number(time[1])
  if (time[3]?.toUpperCase() === "PM" && hour < 12) hour += 12
  if (time[3]?.toUpperCase() === "AM" && hour === 12) hour = 0
  return `${String(hour).padStart(2, "0")}:${time[2]}`
}

const weekdayFromTask = (task: ScheduledTask) => {
  const fields = cronParts(task.cron)
  if (fields && /^\d$/.test(fields[4])) return fields[4] === "7" ? "0" : fields[4]
  const weekday = /\b(monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/i.exec(task.schedule)?.[1]?.toLowerCase()
  return ({
    monday: "1",
    tuesday: "2",
    wednesday: "3",
    thursday: "4",
    friday: "5",
    saturday: "6",
    sunday: "0",
  } as Record<string, string>)[weekday || ""] || "1"
}

const localScheduleParts = (value?: string, timezone = "UTC") => {
  if (!value || value === "Pending") return { date: "", time: "08:00" }
  const instant = new Date(value)
  if (Number.isNaN(instant.getTime())) return { date: "", time: "08:00" }
  try {
    const parts = Object.fromEntries(
      new Intl.DateTimeFormat("en-CA", {
        timeZone: timezone,
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
        hourCycle: "h23",
      })
        .formatToParts(instant)
        .map(({ type, value }) => [type, value]),
    )
    return {
      date: `${parts.year}-${parts.month}-${parts.day}`,
      time: `${parts.hour}:${parts.minute}`,
    }
  } catch {
    return { date: "", time: "" }
  }
}

const cronFromEditor = (
  recurrence: Recurrence,
  time: string,
  weekday: string,
  monthDay: string,
  customCron: string,
) => {
  if (recurrence === "custom") return customCron.trim()
  const [hour, minute] = time.split(":").map(Number)
  const timeFields = `${minute} ${hour}`
  if (recurrence === "weekdays") return `${timeFields} * * 1-5`
  if (recurrence === "weekly") return `${timeFields} * * ${weekday}`
  if (recurrence === "monthly") return `${timeFields} ${monthDay} * *`
  return `${timeFields} * * *`
}

const taskIcon = (task: ScheduledTask) =>
  task.taskType === "monitor" ? "📡" : task.taskType === "agent_task" ? "✨" : "🔔"

const statusLabel = (task: ScheduledTask) =>
  task.status === "running"
    ? "Running"
    : task.status === "failed" || task.lastRunStatus === "failed"
      ? "Failed"
      : task.status === "completed"
        ? "Completed"
        : !task.enabled || task.status === "paused"
          ? "Paused"
          : "Scheduled"

const timeAgo = (value?: string) => {
  if (!value) return ""
  const time = Date.parse(value)
  if (!Number.isFinite(time)) return value
  const minutes = Math.round((Date.now() - time) / 60000)
  if (minutes < 1) return "just now"
  if (minutes < 60) return `${minutes} min ago`
  const hours = Math.round(minutes / 60)
  if (hours < 24) return `${hours} hour${hours === 1 ? "" : "s"} ago`
  const days = Math.round(hours / 24)
  return `${days} day${days === 1 ? "" : "s"} ago`
}

const filterTask = (task: ScheduledTask, filter: Filter) => {
  if (filter === "all") return true
  if (filter === "upcoming") {
    const nextRun = Date.parse(task.nextRun)
    return task.enabled && Number.isFinite(nextRun) && nextRun > Date.now()
  }
  if (filter === "paused") return !task.enabled || task.status === "paused"
  if (filter === "completed") return task.status === "completed"
  if (filter === "failed")
    return task.status === "failed" || task.lastRunStatus === "failed"
  return (
    task.enabled &&
    task.status !== "paused" &&
    task.status !== "completed" &&
    task.status !== "failed"
  )
}

function TaskDetail({
  task,
  busy,
  onClose,
  onToggle,
  onRun,
  onSave,
  onDelete,
}: {
  task: ScheduledTask
  busy: boolean
  onClose: () => void
  onToggle: (task: ScheduledTask) => Promise<void>
  onRun: (task: ScheduledTask) => Promise<void>
  onSave: (id: string, patch: Partial<ScheduledTask>) => Promise<void>
  onDelete: (task: ScheduledTask) => Promise<void>
}) {
  const [prompt, setPrompt] = useState(task.prompt || "")
  const [title, setTitle] = useState(task.title)
  const initialScheduleParts = localScheduleParts(
    task.runAt || task.nextRun,
    task.timezone,
  )
  const initialCronParts = cronParts(task.cron)
  const [scheduleType, setScheduleType] = useState<"one_time" | "recurring">(
    task.scheduleType === "one_time" || Boolean(task.runAt)
      ? "one_time"
      : "recurring",
  )
  const [recurrence, setRecurrence] = useState<Recurrence>(
    recurrenceFromTask(task),
  )
  const [scheduleDate, setScheduleDate] = useState(initialScheduleParts.date)
  const [scheduleTime, setScheduleTime] = useState(
    initialCronParts
      ? `${initialCronParts[1].padStart(2, "0")}:${initialCronParts[0].padStart(2, "0")}`
      : scheduleTimeFromTask(task) || initialScheduleParts.time,
  )
  const [weekday, setWeekday] = useState(weekdayFromTask(task))
  const [monthDay, setMonthDay] = useState(
    initialCronParts && /^\d+$/.test(initialCronParts[2])
      ? initialCronParts[2]
      : "1",
  )
  const [timezone, setTimezone] = useState(task.timezone || "UTC")
  const [notificationEnabled, setNotificationEnabled] = useState(
    task.notificationEnabled !== false,
  )
  const [customCron, setCustomCron] = useState(task.cron || "")
  const [scheduleDirty, setScheduleDirty] = useState(false)
  const [error, setError] = useState("")
  const [working, setWorking] = useState(false)
  const [menuOpen, setMenuOpen] = useState(false)
  const [confirmDelete, setConfirmDelete] = useState(false)
  const history = task.history || []

  const dirty =
    prompt !== (task.prompt || "") ||
    title !== task.title ||
    notificationEnabled !== (task.notificationEnabled !== false) ||
    scheduleDirty

  const save = async () => {
    setError("")
    const patch: Partial<ScheduledTask> = {}
    if (title.trim() && title !== task.title) patch.title = title.trim()
    if (prompt !== (task.prompt || "")) patch.prompt = prompt
    if (notificationEnabled !== (task.notificationEnabled !== false)) {
      patch.notificationEnabled = notificationEnabled
    }
    if (scheduleDirty) {
      if (!/^\d{2}:\d{2}$/.test(scheduleTime)) {
        setError("Choose a valid execution time.")
        return
      }
      patch.timezone = timezone
      if (scheduleType === "one_time") {
        if (!scheduleDate) {
          setError("Choose the date for this one-time task.")
          return
        }
        patch.scheduleType = "one_time"
        patch.runAt = `${scheduleDate}T${scheduleTime}:00`
        patch.cron = undefined
      } else {
        const expression = cronFromEditor(
          recurrence,
          scheduleTime,
          weekday,
          monthDay,
          customCron,
        )
        if (expression.split(/\s+/).length !== 5) {
          setError("Enter a five-field cron schedule, for example 0 8 * * 1-5.")
          return
        }
        patch.scheduleType = "custom"
        patch.runAt = undefined
        patch.cron = expression
      }
    }
    setWorking(true)
    try {
      await onSave(task.id, patch)
      setScheduleDirty(false)
    } catch (saveError) {
      setError(
        saveError instanceof Error
          ? saveError.message
          : "Could not save changes. Please try again.",
      )
    } finally {
      setWorking(false)
    }
  }

  const run = async () => {
    setError("")
    setWorking(true)
    try {
      await onRun(task)
    } catch (runError) {
      setError(
        runError instanceof Error
          ? runError.message
          : "Could not start this scheduled task.",
      )
    } finally {
      setWorking(false)
    }
  }

  return (
    <aside className="sched-detail" aria-label={`${task.title} details`}>
      <header className="sched-detail-head">
        <span className="sched-icon" aria-hidden="true">
          {taskIcon(task)}
        </span>
        <div>
          <strong>{task.title}</strong>
          <small>
            {statusLabel(task)}
            {task.lastRun ? ` · Last run ${timeAgo(task.lastRun)}` : " · Never run"}
          </small>
        </div>
        <div className="sched-detail-actions">
          <button
            type="button"
            className="icon-btn"
            aria-label="More actions"
            aria-expanded={menuOpen}
            onClick={() => setMenuOpen((open) => !open)}
          >
            ⋯
          </button>
          <button
            type="button"
            className="icon-btn"
            aria-label="Close details"
            onClick={onClose}
          >
            ✕
          </button>
          {menuOpen && (
            <div className="sched-menu" role="menu">
              <button
                type="button"
                role="menuitem"
                disabled={working}
                onClick={() => {
                  setMenuOpen(false)
                  void run()
                }}
              >
                {task.lastRunStatus === "failed" ? "Retry now" : "Run now"}
              </button>
              <button
                type="button"
                role="menuitem"
                onClick={() => {
                  setMenuOpen(false)
                  void onToggle(task)
                }}
              >
                {task.enabled ? "Pause" : "Resume"}
              </button>
              <button
                type="button"
                role="menuitem"
                className="danger"
                onClick={() => {
                  setMenuOpen(false)
                  setConfirmDelete(true)
                }}
              >
                Delete
              </button>
            </div>
          )}
        </div>
      </header>

      <div className="sched-detail-body">
        <label className="sched-label" htmlFor="sched-title">
          Title
        </label>
        <input
          id="sched-title"
          className="sched-input"
          maxLength={255}
          value={title}
          onChange={(event) => setTitle(event.target.value)}
        />
        <label className="sched-label" htmlFor="sched-prompt">
          Prompt
        </label>
        <textarea
          id="sched-prompt"
          className="sched-textarea"
          maxLength={5000}
          value={prompt}
          onChange={(event) => setPrompt(event.target.value)}
        />

        <span className="sched-label">Schedule</span>
        <div className="sched-card">
          <div>
            <span>Schedule</span>
            <strong>{describeCron(task)}</strong>
          </div>
          <div>
            <span>Time zone</span>
            <strong>{task.timezone || "UTC"}</strong>
          </div>
          <div>
            <span>Next run</span>
            <strong>{formatWhen(task.nextRun, task.timezone)}</strong>
          </div>
        </div>
        <div className="sched-schedule-editor">
          <label className="sched-label" htmlFor="sched-type">Repeat</label>
          <select
            id="sched-type"
            className="sched-input"
            value={scheduleType}
            onChange={(event) => {
              setScheduleType(event.target.value as "one_time" | "recurring")
              setScheduleDirty(true)
            }}
          >
            <option value="one_time">One time</option>
            <option value="recurring">Recurring</option>
          </select>
          {scheduleType === "one_time" ? (
            <div className="sched-editor-row">
              <label>
                <span>Date</span>
                <input
                  className="sched-input"
                  type="date"
                  value={scheduleDate}
                  onChange={(event) => {
                    setScheduleDate(event.target.value)
                    setScheduleDirty(true)
                  }}
                />
              </label>
              <label>
                <span>Time</span>
                <input
                  className="sched-input"
                  type="time"
                  value={scheduleTime}
                  onChange={(event) => {
                    setScheduleTime(event.target.value)
                    setScheduleDirty(true)
                  }}
                />
              </label>
            </div>
          ) : (
            <>
              <div className="sched-editor-row">
                <label>
                  <span>Frequency</span>
                  <select
                    className="sched-input"
                    value={recurrence}
                    onChange={(event) => {
                      setRecurrence(event.target.value as Recurrence)
                      setScheduleDirty(true)
                    }}
                  >
                    <option value="daily">Daily</option>
                    <option value="weekdays">Weekdays</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                    <option value="custom">Custom cron</option>
                  </select>
                </label>
                <label>
                  <span>Time</span>
                  <input
                    className="sched-input"
                    type="time"
                    value={scheduleTime}
                    onChange={(event) => {
                      setScheduleTime(event.target.value)
                      setScheduleDirty(true)
                    }}
                  />
                </label>
              </div>
              {recurrence === "weekly" && (
                <label className="sched-label" htmlFor="sched-weekday">Day of week</label>
              )}
              {recurrence === "weekly" && (
                <select
                  id="sched-weekday"
                  className="sched-input"
                  value={weekday}
                  onChange={(event) => {
                    setWeekday(event.target.value)
                    setScheduleDirty(true)
                  }}
                >
                  <option value="1">Monday</option>
                  <option value="2">Tuesday</option>
                  <option value="3">Wednesday</option>
                  <option value="4">Thursday</option>
                  <option value="5">Friday</option>
                  <option value="6">Saturday</option>
                  <option value="0">Sunday</option>
                </select>
              )}
              {recurrence === "monthly" && (
                <>
                  <label className="sched-label" htmlFor="sched-month-day">Day of month</label>
                  <input
                    id="sched-month-day"
                    className="sched-input"
                    type="number"
                    min={1}
                    max={31}
                    value={monthDay}
                    onChange={(event) => {
                      setMonthDay(event.target.value)
                      setScheduleDirty(true)
                    }}
                  />
                </>
              )}
              {recurrence === "custom" && (
                <>
                  <label className="sched-label" htmlFor="sched-cron">Cron expression</label>
                  <input
                    id="sched-cron"
                    className="sched-input"
                    value={customCron}
                    placeholder="0 8 * * 1-5"
                    onChange={(event) => {
                      setCustomCron(event.target.value)
                      setScheduleDirty(true)
                    }}
                  />
                </>
              )}
            </>
          )}
          <label className="sched-label" htmlFor="sched-timezone">Time zone</label>
          <select
            id="sched-timezone"
            className="sched-input"
            value={timezone}
            onChange={(event) => {
              setTimezone(event.target.value)
              setScheduleDirty(true)
            }}
          >
            {!["Africa/Mogadishu", "Africa/Nairobi", "Africa/Kampala", "Africa/Addis_Ababa", "UTC", "Europe/London", "Asia/Dubai", "America/New_York", "America/Los_Angeles"].includes(timezone) && (
              <option value={timezone}>{timezone}</option>
            )}
            <option value="Africa/Mogadishu">Africa/Mogadishu</option>
            <option value="Africa/Nairobi">Africa/Nairobi</option>
            <option value="Africa/Kampala">Africa/Kampala</option>
            <option value="Africa/Addis_Ababa">Africa/Addis_Ababa</option>
            <option value="UTC">UTC</option>
            <option value="Europe/London">Europe/London</option>
            <option value="Asia/Dubai">Asia/Dubai</option>
            <option value="America/New_York">America/New_York</option>
            <option value="America/Los_Angeles">America/Los_Angeles</option>
          </select>
        </div>
        <span className="sched-label">Result delivery</span>
        <div className="sched-card">
          <div>
            <span>Destination</span>
            <strong>{notificationEnabled ? "Garad chat" : "Scheduled task history"}</strong>
          </div>
          <div>
            <span>Delivery</span>
            <strong>{notificationEnabled ? "A result message is created after each run" : "Chat notification is off; run results remain in this history"}</strong>
          </div>
        </div>
        <label className="sched-delivery-toggle">
          <input
            type="checkbox"
            checked={notificationEnabled}
            onChange={(event) => setNotificationEnabled(event.target.checked)}
          />
          <span>Send completed results to Garad chat</span>
        </label>
        {error && (
          <p className="schedule-proposal-error" role="alert">
            {error}
          </p>
        )}
        <div className="sched-save-row">
          <button
            type="button"
            className="sched-save"
            disabled={!dirty || working || busy}
            onClick={() => void save()}
          >
            {working ? "Saving…" : "Save"}
          </button>
          <button
            type="button"
            className="secondary-btn"
            disabled={working}
            onClick={() => void run()}
          >
            {task.lastRunStatus === "failed" ? "Retry now" : "Run now"}
          </button>
        </div>

        <span className="sched-label">Results</span>
        {history.length === 0 && (
          <p className="sched-muted">
            No runs yet. Results will appear here after the first run.
          </p>
        )}
        {history.map((item) => (
          <article key={item.id} className="sched-run">
            <header>
              <span className={`sched-badge ${item.status}`}>
                {item.status === "completed"
                  ? "Completed"
                  : item.status === "failed"
                    ? "Failed"
                    : "Running"}
              </span>
              <small>{timeAgo(item.startedAt)}</small>
            </header>
            <p className={`sched-delivery ${item.deliveryStatus || "pending"}`}>
              {item.deliveryStatus === "delivered"
                ? "Result delivered to Garad chat."
                : item.deliveryStatus === "skipped"
                  ? item.deliverySkipReason === "no_result"
                    ? "No notification was sent because the task returned no new result."
                    : "Chat notification is off; the result is saved in task history."
                  : item.deliveryStatus === "failed"
                    ? `Chat delivery failed and is pending retry.${item.deliveryError ? ` ${item.deliveryError}` : ""}`
                    : "Result saved in task history; chat delivery is pending."}
            </p>
            {item.result ? (
              <AssistantMarkdown content={item.result} streaming={false} />
            ) : (
              <p className="sched-muted">
                {item.error || "No result was returned yet."}
              </p>
            )}
          </article>
        ))}
      </div>
      {confirmDelete && (
        <div className="sched-confirm-backdrop">
          <section
            className="sched-confirm"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="sched-delete-title"
            aria-describedby="sched-delete-description"
          >
            <h3 id="sched-delete-title">Delete this scheduled task?</h3>
            <p id="sched-delete-description">
              “{task.title}” and its task entry will be removed. Past chat messages are not deleted.
            </p>
            <div>
              <button
                type="button"
                className="secondary-btn"
                disabled={working}
                onClick={() => setConfirmDelete(false)}
              >
                Keep task
              </button>
              <button
                type="button"
                className="sched-delete-confirm"
                disabled={working}
                onClick={async () => {
                  setWorking(true)
                  setError("")
                  try {
                    await onDelete(task)
                  } catch (deleteError) {
                    setError(
                      deleteError instanceof Error
                        ? deleteError.message
                        : "Could not delete this scheduled task.",
                    )
                    setConfirmDelete(false)
                  } finally {
                    setWorking(false)
                  }
                }}
              >
                {working ? "Deleting…" : "Delete task"}
              </button>
            </div>
          </section>
        </div>
      )}
    </aside>
  )
}

export default function ScheduledPage({
  tasks,
  canUse,
  busy,
  loading,
  conversationId,
  proposal,
  setProposal,
  onSchedule,
  onClose,
  onUpgrade,
  onToggle,
  onRun,
  onSave,
  onDelete,
  onCreated,
}: Props) {
  const [filter, setFilter] = useState<Filter>("active")
  const [text, setText] = useState("")
  const [taskSearch, setTaskSearch] = useState("")
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [step, setStep] = useState<number | null>(null)
  const [answers, setAnswers] = useState<Record<string, string>>({})
  const [custom, setCustom] = useState("")
  const [hint, setHint] = useState("")

  const visible = useMemo(
    () =>
      tasks
        .filter((task) => filterTask(task, filter))
        .filter((task) => {
          const query = taskSearch.trim().toLocaleLowerCase()
          if (!query) return true
          return [
            task.title,
            task.prompt,
            task.schedule,
            task.timezone,
            task.status,
            task.lastRunStatus,
          ].some((value) => value?.toLocaleLowerCase().includes(query))
        })
        .sort((left, right) => {
          if (filter !== "upcoming") return 0
          return Date.parse(left.nextRun) - Date.parse(right.nextRun)
        }),
    [tasks, filter, taskSearch],
  )
  const filters: Array<{ value: Filter; label: string }> = [
    { value: "all", label: "All" },
    { value: "upcoming", label: "Upcoming" },
    { value: "active", label: "Active" },
    { value: "paused", label: "Paused" },
    { value: "completed", label: "Completed" },
    { value: "failed", label: "Failed" },
  ]
  const selected = tasks.find((task) => task.id === selectedId) || null
  const question = step === null ? null : questions[step]

  const propose = (value: string, title?: string) => {
    const detected = detectScheduledTaskIntent(value)
    if (!detected) return false
    const intent = title ? { ...detected, title } : detected
    setProposal({ conversationId, intent, expanded: true })
    setStep(null)
    setHint("")
    return true
  }

  const submit = () => {
    const value = text.trim()
    if (!value || !canUse) return
    setProposal(null)
    setAnswers({})
    if (propose(value)) return
    setStep(0)
    setCustom("")
    setHint("")
  }

  const answer = (value: string | null) => {
    if (!question || step === null) return
    const next = value ? { ...answers, [question.id]: value } : answers
    setAnswers(next)
    setCustom("")
    if (step < questions.length - 1) {
      setStep(step + 1)
      return
    }
    const when = next.frequency
      ? `${next.frequency} at ${next.time || "9:00 AM"}`
      : "tomorrow at 9:00 AM"
    const parts = [
      `${text.trim().replace(/[.!?]+$/, "")} ${when}.`,
      next.language ? `Respond in ${next.language}.` : "",
    ]
    if (!propose(parts.filter(Boolean).join(" "), cleanTitle(text))) {
      setHint(
        "I could not turn that into a valid schedule. Add a time such as “every day at 8 AM”.",
      )
      setStep(0)
    }
  }

  return (
    <div className="sched-backdrop">
      <section
        className={`sched-page${selected ? " has-detail" : ""}`}
        role="dialog"
        aria-modal="true"
        aria-label="Scheduled tasks"
      >
        <div className="sched-main">
          <header className="sched-head">
            <div>
              <h2>Scheduled</h2>
              <p>Ask Garad to schedule tasks, set reminders, or monitor for updates</p>
            </div>
            <div className="sched-head-actions">
              <button
                type="button"
                className="icon-btn"
                aria-label="Close scheduled tasks"
                onClick={onClose}
              >
                ✕
              </button>
            </div>
          </header>

          <nav className="sched-tabs" aria-label="Task status filters">
            {filters.map((item) => {
              const count = tasks.filter((task) =>
                filterTask(task, item.value),
              ).length
              return (
                <button
                  key={item.value}
                  type="button"
                  aria-pressed={filter === item.value}
                  className={filter === item.value ? "on" : ""}
                  onClick={() => setFilter(item.value)}
                >
                  {item.label}
                  <span>{count}</span>
                </button>
              )
            })}
          </nav>

          {!canUse && (
            <div className="oauth-note">
              <span>Scheduled tasks are not available on your current plan.</span>
              <button onClick={onUpgrade}>Upgrade</button>
            </div>
          )}

          <form
            className="sched-composer"
            onSubmit={(event) => {
              event.preventDefault()
              submit()
            }}
          >
            <input
              value={text}
              disabled={!canUse || busy}
              placeholder="Schedule a task"
              aria-label="Schedule a task"
              onChange={(event) => setText(event.target.value)}
            />
            <button
              type="submit"
              aria-label="Create scheduled task"
              disabled={!canUse || busy || !text.trim()}
            >
              ↑
            </button>
          </form>

          {hint && (
            <p className="schedule-proposal-error" role="alert">
              {hint}
            </p>
          )}

          {question && step !== null && (
            <section className="sched-question" aria-live="polite">
              <header>
                <strong>{question.title}</strong>
                <span className="sched-question-nav">
                  <button
                    type="button"
                    aria-label="Previous question"
                    disabled={step === 0}
                    onClick={() => setStep(step - 1)}
                  >
                    ‹
                  </button>
                  {step + 1} of {questions.length}
                  <button
                    type="button"
                    aria-label="Skip question"
                    onClick={() => answer(null)}
                  >
                    ›
                  </button>
                  <button
                    type="button"
                    aria-label="Cancel questions"
                    onClick={() => setStep(null)}
                  >
                    ✕
                  </button>
                </span>
              </header>
              {question.options.map((option, index) => (
                <button
                  key={option.value}
                  type="button"
                  className={`sched-option${answers[question.id] === option.value ? " on" : ""}`}
                  onClick={() => answer(option.value)}
                >
                  <kbd>{index + 1}</kbd>
                  {option.label}
                </button>
              ))}
              <form
                className="sched-question-custom"
                onSubmit={(event) => {
                  event.preventDefault()
                  if (custom.trim()) answer(custom.trim())
                }}
              >
                <input
                  value={custom}
                  placeholder={question.placeholder}
                  aria-label={question.placeholder}
                  onChange={(event) => setCustom(event.target.value)}
                />
                <button type="button" onClick={() => answer(null)}>
                  Skip
                </button>
              </form>
            </section>
          )}

          {proposal && (
            <ScheduledTaskProposalCard
              proposal={proposal}
              busy={busy}
              onExpand={() =>
                setProposal((current) =>
                  current ? { ...current, expanded: true } : current,
                )
              }
              onUpdate={(patch) =>
                setProposal((current) =>
                  current
                    ? {
                        ...current,
                        intent: updateScheduleFields(current.intent, patch),
                      }
                    : current,
                )
              }
              onSchedule={onSchedule}
              onCancel={() => {
                setProposal(null)
                setText("")
              }}
              onViewTask={() => {
                setProposal(null)
                setText("")
                onCreated()
              }}
            />
          )}

          <div className="sched-task-search">
            <label className="sr-only" htmlFor="sched-task-search">
              Search scheduled tasks
            </label>
            <span aria-hidden="true">⌕</span>
            <input
              id="sched-task-search"
              type="search"
              value={taskSearch}
              placeholder="Search your tasks"
              onChange={(event) => setTaskSearch(event.target.value)}
            />
            {taskSearch && (
              <button
                type="button"
                aria-label="Clear task search"
                onClick={() => setTaskSearch("")}
              >
                ×
              </button>
            )}
          </div>

          <div className="sched-list" role="list">
            {visible.length === 0 && loading && (
              <p className="sched-muted sched-empty" role="status" aria-live="polite">
                Loading your scheduled tasks…
              </p>
            )}
            {visible.length === 0 && !loading && (
              <div className="sched-empty-state">
                <p className="sched-muted sched-empty" role="status">
                  {taskSearch.trim()
                    ? "No tasks match your search."
                    : `No ${filter === "all" ? "" : `${filter} `}tasks to show.`}
                </p>
                {(taskSearch.trim() || filter !== "all") && (
                  <button
                    type="button"
                    onClick={() => {
                      setTaskSearch("")
                      setFilter("all")
                    }}
                  >
                    Clear search and filters
                  </button>
                )}
              </div>
            )}
            {visible.map((task) => (
              <button
                key={task.id}
                type="button"
                role="listitem"
                className={`sched-item${selectedId === task.id ? " on" : ""}`}
                onClick={() => setSelectedId(task.id)}
              >
                <span className="sched-icon" aria-hidden="true">
                  {taskIcon(task)}
                </span>
                <span>
                  <strong>{task.title}</strong>
                  <small>
                    {statusLabel(task)}
                    {task.lastRun
                      ? ` · Last run ${formatWhen(task.lastRun, task.timezone)}`
                      : task.nextRun && task.nextRun !== "Pending"
                        ? ` · Next ${formatWhen(task.nextRun, task.timezone)}`
                        : " · No upcoming run"}
                  </small>
                  <span className="sched-item-prompt">
                    {task.prompt || "No task instructions provided."}
                  </span>
                  <span className="sched-item-meta">
                    {describeCron(task)} · {task.timezone || "UTC"}
                  </span>
                </span>
              </button>
            ))}
          </div>

          <h3 className="sched-recommended">Recommended</h3>
          <div className="sched-templates">
            {templates.map((template) => (
              <button
                key={template.title}
                type="button"
                disabled={!canUse}
                onClick={() => {
                  setText(template.text)
                  setProposal(null)
                  setAnswers({})
                  setStep(0)
                  setHint("")
                }}
              >
                <span className="sched-icon" aria-hidden="true">
                  {template.icon}
                </span>
                <span>
                  <strong>{template.title}</strong>
                  <small>{template.text}</small>
                </span>
                <b aria-hidden="true">+</b>
              </button>
            ))}
          </div>
        </div>

        {selected && (
          <TaskDetail
            key={selected.id}
            task={selected}
            busy={busy}
            onClose={() => setSelectedId(null)}
            onToggle={onToggle}
            onRun={onRun}
            onSave={onSave}
            onDelete={async (task) => {
              await onDelete(task)
              setSelectedId(null)
            }}
          />
        )}
      </section>
    </div>
  )
}
