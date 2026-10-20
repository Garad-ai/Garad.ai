import {
  scheduleTaskIsReady,
  type ScheduledTaskIntent,
} from "../core/scheduledTaskParser"

export type ScheduleProposalState = {
  conversationId: string
  sourcePrompt?: string
  intent: ScheduledTaskIntent
  expanded: boolean
  scheduledTaskId?: string
  error?: string
}

type Props = {
  proposal: ScheduleProposalState
  busy: boolean
  onExpand: () => void
  onUpdate: (patch: Partial<Pick<ScheduledTaskIntent, "date" | "time" | "recurrence" | "timezone" | "notificationEnabled">>) => void
  onSchedule: () => void
  onCancel: () => void
  onViewTask: () => void
}

const repeatOptions: Array<{ value: ScheduledTaskIntent["recurrence"]; label: string }> = [
  { value: "once", label: "Once" },
  { value: "daily", label: "Daily" },
  { value: "weekdays", label: "Weekdays" },
  { value: "weekly", label: "Weekly" },
  { value: "monthly", label: "Monthly" },
]

const timezones = [
  "Africa/Mogadishu",
  "Africa/Nairobi",
  "UTC",
  "Europe/London",
  "America/New_York",
  "America/Los_Angeles",
]

export default function ScheduledTaskProposalCard({
  proposal,
  busy,
  onExpand,
  onUpdate,
  onSchedule,
  onCancel,
  onViewTask,
}: Props) {
  const { intent } = proposal
  const scheduled = Boolean(proposal.scheduledTaskId)
  const ready = scheduleTaskIsReady(intent)

  return (
    <section
      aria-label="Scheduled task proposal"
      className={`schedule-proposal-card${scheduled ? " is-scheduled" : ""}`}
      aria-live="polite"
    >
      <div className="schedule-proposal-heading">
        <span className="schedule-proposal-icon" aria-hidden="true">⏰</span>
        <div>
          <strong>{scheduled ? "Scheduled" : intent.opportunity ? "Set a reminder?" : "Schedule this task?"}</strong>
          <span>{scheduled ? intent.schedule : `${intent.schedule} · ${intent.timezone}`}</span>
        </div>
        {scheduled && <span className="schedule-proposal-check" aria-hidden="true">✓</span>}
      </div>
      <div className="schedule-proposal-task">
        <strong>{intent.title}</strong>
        <p>{intent.instruction}</p>
      </div>

      {!scheduled && intent.opportunity && !proposal.expanded && (
        <div className="schedule-proposal-actions">
          <button className="schedule-proposal-primary" onClick={onExpand} disabled={busy}>
            ⏰ Remind me
          </button>
          <button className="schedule-proposal-quiet" onClick={onCancel} disabled={busy}>
            Not now
          </button>
        </div>
      )}

      {!scheduled && proposal.expanded && (
        <div className="schedule-proposal-fields">
          <label>
            <span>Date</span>
            <input
              type="date"
              value={intent.date}
              onChange={(event) => onUpdate({ date: event.target.value })}
              aria-label="Reminder date"
            />
          </label>
          <label>
            <span>Time</span>
            <input
              type="time"
              value={intent.time}
              onChange={(event) => onUpdate({ time: event.target.value })}
              aria-label="Reminder time"
            />
          </label>
          <label>
            <span>Repeat</span>
            <select
              value={intent.recurrence}
              onChange={(event) => onUpdate({ recurrence: event.target.value as ScheduledTaskIntent["recurrence"] })}
              aria-label="Repeat schedule"
            >
              {repeatOptions.map((option) => (
                <option key={option.value} value={option.value}>{option.label}</option>
              ))}
            </select>
          </label>
          <label className="schedule-proposal-timezone">
            <span>Timezone</span>
            <select
              value={intent.timezone}
              onChange={(event) => onUpdate({ timezone: event.target.value })}
              aria-label="Timezone"
            >
              {!timezones.includes(intent.timezone) && (
                <option value={intent.timezone}>{intent.timezone}</option>
              )}
              {timezones.map((timezone) => (
                <option key={timezone} value={timezone}>{timezone}</option>
              ))}
            </select>
          </label>
          <label className="schedule-proposal-notify">
            <input
              type="checkbox"
              checked={intent.notificationEnabled}
              onChange={(event) =>
                onUpdate({ notificationEnabled: event.target.checked })
              }
            />
            <span>Notify me in Garad when it runs</span>
          </label>
          {proposal.error && (
            <p className="schedule-proposal-error" role="alert">
              {proposal.error}
            </p>
          )}
          <div className="schedule-proposal-actions">
            <button className="schedule-proposal-quiet" onClick={onCancel} disabled={busy}>
              Cancel
            </button>
            <button
              className="schedule-proposal-primary"
              onClick={onSchedule}
              disabled={busy || !ready}
            >
              {busy ? "Scheduling…" : proposal.error ? "Try again" : "Schedule"}
            </button>
          </div>
        </div>
      )}

      {scheduled && (
        <div className="schedule-proposal-actions">
          <button className="schedule-proposal-quiet" onClick={onViewTask}>
            View scheduled task
          </button>
        </div>
      )}
    </section>
  )
}
