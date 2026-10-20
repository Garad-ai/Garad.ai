import { useEffect, useMemo, useRef, useState } from "react"

import type { ApiClient, VideoJob } from "./core/api"
import { BrandIcon } from "./SocialBrand"
import PostPreview from "./SocialPreview"
import type { Account, Overview, PlatformInfo, Post } from "./SocialMedia"

type Variation = { content?: string; images?: string[]; video?: string | null }

type Props = {
  api: ApiClient
  data: Overview
  editing: Post | null
  initialDate?: string
  initialText?: string
  onClose: () => void
  onChanged: (message: string, tone: "ok" | "err") => void
  onConnect: () => void
}

const DRAFT_KEY = "garad.social.composer.v2"
const PERSONAL_KEY = "garad.social.templates.personal"
const IMAGE_HISTORY_KEY = "garad.imageStudio.history.v1"

const EMOJIS = ["😀", "😍", "🔥", "✨", "🚀", "🎉", "💡", "👏", "❤️", "💪", "🙏", "✅", "📣", "🎯", "🌍", "⭐"]

type ComposerIconName = "tag" | "template" | "sparkles" | "eye" | "expand" | "collapse" | "close" | "plus" | "search" | "image" | "smile" | "clock" | "chevron" | "calendar" | "check" | "help" | "external"

function ComposerIcon({ name, size = 16 }: { name: ComposerIconName; size?: number }) {
  const common = { fill: "none", stroke: "currentColor", strokeWidth: 1.8, strokeLinecap: "round" as const, strokeLinejoin: "round" as const }
  const paths: Record<ComposerIconName, string> = {
    tag: "M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8Z M7.5 7.5h.01",
    template: "M4 4h16v16H4z M8 8h8 M8 12h8 M8 16h5",
    sparkles: "m12 3 1.5 5.5L19 10l-5.5 1.5L12 17l-1.5-5.5L5 10l5.5-1.5L12 3Z M19 14l.8 2.2L22 17l-2.2.8L19 20l-.8-2.2L16 17l2.2-.8L19 14Z",
    eye: "M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6Z M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z",
    expand: "M8 3H3v5 M16 3h5v5 M3 16v5h5 M21 16v5h-5 M3 8l6-5 M21 8l-6-5 M3 16l6 5 M21 16l-6 5",
    collapse: "M8 3v5H3 M16 3v5h5 M3 16h5v5 M21 16h-5v5 M3 8l6-5 M21 8l-6-5 M3 16l6 5 M21 16l-6 5",
    close: "m18 6-12 12 M6 6l12 12",
    plus: "M12 5v14 M5 12h14",
    search: "m20 20-4.5-4.5 M18 10.5a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z",
    image: "M3 4h18v16H3z M8.5 10a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z M21 15l-5-5L5 20",
    smile: "M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z M8 14s1.5 2 4 2 4-2 4-2 M9 9h.01 M15 9h.01",
    clock: "M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z M12 6v6l4 2",
    chevron: "m7 10 5 5 5-5",
    calendar: "M8 2v4 M16 2v4 M3 9h18 M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z M8 13h.01 M12 13h.01 M16 13h.01",
    check: "m5 12 4 4L19 6",
    help: "M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3 M12 17h.01 M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z",
    external: "M14 4h6v6 M20 4l-9 9 M18 13v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h6",
  }
  return <svg width={size} height={size} viewBox="0 0 24 24" aria-hidden="true" {...common}><path d={paths[name]} /></svg>
}

export const TEMPLATES: { category: string; title: string; text: string }[] = [
  { category: "Marketing", title: "Benefit-led hook", text: "Want [result] without [pain point]?\n\nHere's how [product] helps: [benefit 1], [benefit 2], [benefit 3].\n\nTry it today 👉 [link]" },
  { category: "Product Launch", title: "Launch day", text: "🚀 It's here! Meet [product name].\n\n[One-line value]. Built for [audience].\n\nAvailable now → [link]" },
  { category: "Announcement", title: "Big news", text: "📣 Big news: [announcement].\n\nWhat it means for you: [impact].\n\nMore details soon." },
  { category: "Educational", title: "Quick tip", text: "💡 Quick tip: [tip headline]\n\n1. [step one]\n2. [step two]\n3. [step three]\n\nSave this for later." },
  { category: "Promotion", title: "Limited offer", text: "🔥 [Offer] for [audience] — until [date].\n\nUse code [CODE] at checkout.\n\n[link]" },
  { category: "Event", title: "Event invite", text: "📅 Join us on [date] at [time] for [event name].\n\nYou'll learn: [topic 1], [topic 2].\n\nReserve your spot: [link]" },
  { category: "Hiring", title: "We're hiring", text: "We're hiring a [role]! 🙌\n\nYou'll: [responsibility].\nWe offer: [benefit].\n\nApply: [link]" },
  { category: "Quote", title: "Inspirational quote", text: "“[Quote]”\n— [Author]\n\nWhat does this mean to you?" },
  { category: "Engagement", title: "Ask a question", text: "Quick question for you 👇\n\n[Question]?\n\nTell us in the comments." },
  { category: "Story", title: "Behind the scenes", text: "Behind the scenes at [brand]: [moment].\n\nWhy it matters: [reason]." },
  { category: "Video", title: "Video teaser", text: "🎬 Watch: [video topic] in [duration].\n\nYou'll see [highlight].\n\nFull video 👉 [link]" },
  { category: "Business", title: "Company update", text: "Company update: [milestone].\n\nThank you to our customers and team for making this possible." },
]

const AI_ACTIONS: { id: string; label: string }[] = [
  { id: "improve", label: "Improve" },
  { id: "grammar", label: "Fix grammar" },
  { id: "rewrite", label: "Rewrite" },
  { id: "shorten", label: "Shorten" },
  { id: "expand", label: "Expand" },
  { id: "professional", label: "Professional" },
  { id: "friendly", label: "Friendly" },
  { id: "marketing", label: "Marketing" },
  { id: "educational", label: "Educational" },
  { id: "translate", label: "Translate" },
  { id: "hashtags", label: "Hashtags" },
  { id: "cta", label: "Call to action" },
]

export const friendlyError = (e: unknown, fallback = "Something went wrong. Please try again.") => {
  const m = e instanceof Error ? e.message : ""
  if (!m || m.length > 220 || /SQLSTATE|\.php|Exception|Stack trace|vendor[\\/]|Illuminate|\bat line\b|<html|^\s*[{[]/i.test(m)) return fallback
  return m
}

const wallToIso = (date: string, time: string, tz: string) => {
  const [y, m, d] = date.split("-").map(Number)
  const [hh, mm] = time.split(":").map(Number)
  const guess = Date.UTC(y, m - 1, d, hh, mm)
  const fmt = new Intl.DateTimeFormat("en-US", { timeZone: tz, hourCycle: "h23", year: "numeric", month: "numeric", day: "numeric", hour: "numeric", minute: "numeric", second: "numeric" })
  const offset = (t: number) => {
    const p = Object.fromEntries(fmt.formatToParts(new Date(t)).map((x) => [x.type, x.value]))
    return Date.UTC(+p.year, +p.month - 1, +p.day, +p.hour, +p.minute, +p.second) - t
  }
  const first = guess - offset(guess)
  return new Date(guess - offset(first)).toISOString()
}

const isoToWall = (iso: string, tz: string) => {
  const p = Object.fromEntries(
    new Intl.DateTimeFormat("en-CA", { timeZone: tz, hourCycle: "h23", year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit" })
      .formatToParts(new Date(iso))
      .map((x) => [x.type, x.value]),
  )
  return { date: `${p.year}-${p.month}-${p.day}`, time: `${p.hour}:${p.minute}` }
}

const zones = (extra: string[]) => {
  const all = (Intl as unknown as { supportedValuesOf?: (k: string) => string[] }).supportedValuesOf?.("timeZone") ?? []
  return [...new Set([...extra, "UTC", ...all])]
}

type Result = { account: Account | undefined; post: Post }

export default function SocialComposer({ api, data, editing, initialDate, initialText, onClose, onChanged, onConnect }: Props) {
  const browserTz = Intl.DateTimeFormat().resolvedOptions().timeZone || "UTC"
  const defaultTz = data.timezone || browserTz
  const platformOf = (k: string): PlatformInfo | undefined => data.platforms.find((p) => p.key === k)
  const accountOf = (id: number) => data.accounts.find((a) => a.id === id)

  const saved = useMemo(() => {
    if (editing) return null
    try {
      return JSON.parse(window.localStorage.getItem(DRAFT_KEY) || "null") as { content?: string; images?: string[]; video?: string; accountIds?: number[] } | null
    } catch {
      return null
    }
  }, [editing])

  const [accountIds, setAccountIds] = useState<number[]>(
    editing
      ? [editing.accountId]
      : saved
        ? (saved.accountIds ?? []).filter((id) => data.accounts.some((a) => a.id === id && a.status === "connected"))
        : data.accounts.filter((a) => a.status === "connected").map((a) => a.id),
  )
  const [content, setContent] = useState(editing?.content ?? initialText ?? saved?.content ?? "")
  const [images, setImages] = useState<string[]>(editing?.images ?? saved?.images ?? [])
  const [video, setVideo] = useState(editing?.video ?? saved?.video ?? "")
  const [campaigns, setCampaigns] = useState(data.campaigns)
  const [campaignId, setCampaignId] = useState(editing?.campaignId ? String(editing.campaignId) : "")
  const [custom, setCustom] = useState<Record<string, Variation>>({})
  const [customOn, setCustomOn] = useState(false)
  const [activeNet, setActiveNet] = useState<string>("")
  const [expanded, setExpanded] = useState(false)
  const [panel, setPanel] = useState<"" | "channels" | "plus" | "schedule" | "emoji" | "picker-image" | "picker-video" | "picker-library">("")
  const [side, setSide] = useState<"preview" | "templates" | "ai">("preview")
  const [when, setWhen] = useState<"now" | "later">(editing?.scheduledAt || initialDate ? "later" : "now")
  const [whenMenu, setWhenMenu] = useState(false)
  const [createAnother, setCreateAnother] = useState(false)
  const [chanSearch, setChanSearch] = useState("")
  const [tagOpen, setTagOpen] = useState(false)
  const [tagSearch, setTagSearch] = useState("")
  const [newTagOpen, setNewTagOpen] = useState(false)
  const [newTagName, setNewTagName] = useState("")
  const [newTagAudience, setNewTagAudience] = useState("")
  const [tagBusy, setTagBusy] = useState(false)
  const [tplTab, setTplTab] = useState<"discover" | "personal">("discover")
  const [tplSearch, setTplSearch] = useState("")
  const [tplCategory, setTplCategory] = useState("")
  const [tplSaving, setTplSaving] = useState(false)
  const [tplTitle, setTplTitle] = useState("")
  const [dragOver, setDragOver] = useState(false)
  const [personal, setPersonal] = useState<{ id: string; title: string; text: string }[]>(() => {
    try {
      const raw = JSON.parse(window.localStorage.getItem(PERSONAL_KEY) || "[]")
      return Array.isArray(raw) ? raw.filter((x) => x && typeof x.id === "string" && typeof x.text === "string") : []
    } catch {
      return []
    }
  })
  const [tz, setTz] = useState(defaultTz)
  const initWall = editing?.scheduledAt ? isoToWall(editing.scheduledAt, defaultTz) : null
  const [date, setDate] = useState(initialDate ?? initWall?.date ?? "")
  const [time, setTime] = useState(initWall?.time ?? "09:00")
  const [scheduleSet, setScheduleSet] = useState(Boolean(editing?.scheduledAt || initialDate))
  const [busy, setBusy] = useState<"" | "draft" | "schedule" | "now" | "ai" | "upload" | "variations">("")
  const [err, setErr] = useState("")
  const [results, setResults] = useState<Result[] | null>(null)
  const [aiAction, setAiAction] = useState("")
  const [aiIdea, setAiIdea] = useState("")
  const [aiLang, setAiLang] = useState("")
  const [aiOut, setAiOut] = useState("")
  const [aiBusy, setAiBusy] = useState(false)
  const [aiHelpOpen, setAiHelpOpen] = useState(false)
  const [videos, setVideos] = useState<VideoJob[] | null>(null)
  const [library, setLibrary] = useState<{ id: string; name: string; url: string }[] | null>(null)
  const clientId = useRef("")
  const area = useRef<HTMLTextAreaElement>(null)
  const dialog = useRef<HTMLDivElement>(null)

  useEffect(() => setCampaigns(data.campaigns), [data.campaigns])

  const selected = accountIds.map(accountOf).filter((a): a is Account => Boolean(a))
  const netKeys = [...new Set(selected.map((a) => a.platform))]
  const shownNet = netKeys.includes(activeNet) ? activeNet : netKeys[0] ?? ""
  const connected = data.accounts.filter((a) =>
    a.status === "connected" &&
    !(a.platform === "linkedin" && a.canPublish === false)
  )

  useEffect(() => {
    if (editing) return
    const t = window.setTimeout(() => {
      try {
        window.localStorage.setItem(DRAFT_KEY, JSON.stringify({ content, images, video, accountIds }))
      } catch {
        // Storage may be unavailable.
      }
    }, 400)
    return () => window.clearTimeout(t)
  }, [content, images, video, accountIds, editing])

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape" && !busy) onClose()
    }
    window.addEventListener("keydown", onKey)
    dialog.current?.focus()
    return () => window.removeEventListener("keydown", onKey)
  }, [onClose, busy])

  const variationFor = (a: Account) => {
    const v = customOn ? custom[a.platform] : undefined
    return {
      content: v?.content?.trim() ? v.content : content,
      images: v?.images ?? images,
      video: v && "video" in v ? v.video ?? "" : video,
    }
  }

  const toggle = (id: number) => setAccountIds((c) => (c.includes(id) ? c.filter((x) => x !== id) : [...c, id]))
  const allSelected = connected.length > 0 && connected.every((a) => accountIds.includes(a.id))

  const insert = (text: string) => {
    const el = area.current
    if (!el) return setContent((c) => c + text)
    const s = el.selectionStart ?? content.length
    const e = el.selectionEnd ?? content.length
    setContent(content.slice(0, s) + text + content.slice(e))
    window.setTimeout(() => {
      el.focus()
      el.setSelectionRange(s + text.length, s + text.length)
    }, 0)
  }

  const strictLimit = selected.reduce<number | null>((m, a) => {
    const l = platformOf(a.platform)?.textLimit ?? 0
    return l > 0 && (m === null || l < m) ? l : m
  }, null)

  const problems = (mode: "draft" | "schedule" | "now"): string[] => {
    const out: string[] = []
    if (!selected.length) out.push("Select at least one account.")
    if (!content.trim() && !selected.some((a) => custom[a.platform]?.content?.trim())) out.push("Write the post text.")
    if (mode === "draft") return out
    for (const a of selected) {
      const p = platformOf(a.platform)
      const v = variationFor(a)
      if (!p) continue
      if (p.textLimit > 0 && v.content.length > p.textLimit) out.push(`${p.label} allows ${p.textLimit} characters (${v.content.length} used).`)
      if (p.imageRequired && !v.images.length) out.push(`${p.label} needs an image.`)
      if (p.videoRequired && !v.video) out.push(`${p.label} needs a video.`)
      if (a.status !== "connected") out.push(`${p.label} needs to be reconnected.`)
    }
    if (mode === "schedule") {
      if (!date || !time) out.push("Pick a date and time.")
      else if (new Date(wallToIso(date, time, tz)).getTime() <= Date.now()) out.push("The scheduled time must be in the future.")
    }
    return [...new Set(out)]
  }

  const submit = async (mode: "draft" | "schedule" | "now") => {
    if (busy) return
    const issues = problems(mode)
    if (issues.length) return setErr(issues[0])
    if (!clientId.current) clientId.current = `${Date.now()}-${Math.random().toString(36).slice(2)}`
    setBusy(mode)
    setErr("")
    try {
      const scheduledAt = mode === "schedule" ? wallToIso(date, time, tz) : null
      const base = { content, mode, scheduledAt, images, video: video || null, campaignId: campaignId ? Number(campaignId) : null }
      let posts: Post[]
      if (editing) {
        const v = variationFor(selected[0])
        posts = [await api.socialRequest<Post>("PUT", `/posts/${editing.id}`, { ...base, content: v.content, images: v.images, video: v.video || null })]
      } else {
        const variations: Record<string, Variation> = {}
        if (customOn) {
          for (const a of selected) {
            const v = custom[a.platform]
            if (v) variations[String(a.id)] = { content: v.content, ...(v.images ? { images: v.images } : {}), ...("video" in v ? { video: v.video ?? null } : {}) }
          }
        }
        const res = await api.socialRequest<{ posts: Post[] }>("POST", "/posts", { ...base, accountIds, clientId: clientId.current, variations })
        posts = res.posts
      }
      clientId.current = ""
      if (!editing) window.localStorage.removeItem(DRAFT_KEY)
      const failed = posts.filter((p) => p.status === "failed")
      if (mode === "now" && failed.length) {
        setResults(posts.map((post) => ({ account: accountOf(post.accountId), post })))
        onChanged(`${posts.length - failed.length} of ${posts.length} platforms completed.`, "err")
        return
      }
      onChanged(
        mode === "now"
          ? `Published to ${posts.length} account${posts.length > 1 ? "s" : ""}.`
          : mode === "schedule"
            ? `Scheduled for ${new Date(scheduledAt as string).toLocaleString(undefined, { dateStyle: "medium", timeStyle: "short", timeZone: tz })} (${tz}).`
            : "Draft saved.",
        "ok",
      )
      if (createAnother && !editing) {
        setContent("")
        setImages([])
        setVideo("")
        setCustom({})
        setCustomOn(false)
        setAiOut("")
        setPanel("")
        setErr("")
      } else onClose()
    } catch (e) {
      setErr(friendlyError(e, "We couldn't save your post. Please try again."))
    } finally {
      setBusy("")
    }
  }

  const upload = async (kind: "image" | "video", file: File | undefined) => {
    if (!file) return
    setBusy("upload")
    setErr("")
    try {
      const url = await api.socialUpload(kind, file)
      if (kind === "image") setImages((c) => [...c, url])
      else setVideo(url)
    } catch (e) {
      setErr(friendlyError(e, "Upload failed. Check the file type and size, then try again."))
    } finally {
      setBusy("")
    }
  }

  const replaceImage = async (i: number, file: File | undefined) => {
    if (!file) return
    try {
      const url = await api.socialUpload("image", file)
      setImages((c) => c.map((x, j) => (j === i ? url : x)))
    } catch (e) {
      setErr(friendlyError(e, "Upload failed. Check the file type and size, then try again."))
    }
  }

  const runAssist = async (action: string, text: string, extra: Record<string, unknown> = {}) => {
    const res = await api.socialRequest<{ text: string }>("POST", "/assist", {
      action,
      text,
      platform: shownNet || selected[0]?.platform,
      language: aiLang || undefined,
      ...extra,
    })
    if (!res.text) throw new Error("The AI returned no text.")
    return res.text
  }

  const doAi = async (action: string, source?: string) => {
    const text = source ?? (action === "generate" ? aiIdea : content)
    if (!text.trim()) return setErr(action === "generate" ? "Describe what the post is about." : "Write some text first.")
    if (action === "translate" && !aiLang.trim()) return setErr("Enter the language to translate into.")
    setAiAction(action)
    setAiBusy(true)
    setErr("")
    try {
      setAiOut(await runAssist(action, text))
    } catch (e) {
      setErr(friendlyError(e, "The AI assistant is unavailable right now."))
    } finally {
      setAiBusy(false)
    }
  }

  const doVariations = async () => {
    if (!content.trim()) return setErr("Write the main post first.")
    if (!netKeys.length) return setErr("Select at least one account first.")
    setBusy("variations")
    setErr("")
    try {
      const res = await api.socialRequest<{ variations: Record<string, string> }>("POST", "/assist", { action: "variations", text: content, platforms: netKeys, language: aiLang || undefined })
      setCustom((c) => {
        const next = { ...c }
        for (const [k, v] of Object.entries(res.variations)) next[k] = { ...next[k], content: v }
        return next
      })
      setCustomOn(true)
    } catch (e) {
      setErr(friendlyError(e, "Platform variations are unavailable right now."))
    } finally {
      setBusy("")
    }
  }

  const improveNetwork = async (action: "hashtags" | "cta") => {
    const cur = custom[shownNet]?.content?.trim() ? (custom[shownNet]?.content as string) : content
    if (!cur.trim()) return setErr("Write some text first.")
    setBusy("ai")
    setErr("")
    try {
      const out = await runAssist(action, cur, { platform: shownNet })
      setCustom((c) => ({ ...c, [shownNet]: { ...c[shownNet], content: action === "hashtags" ? `${cur}\n\n${out}` : out } }))
    } catch (e) {
      setErr(friendlyError(e, "The AI assistant is unavailable right now."))
    } finally {
      setBusy("")
    }
  }

  const openPicker = async (kind: "picker-image" | "picker-video" | "picker-library") => {
    setPanel(kind)
    if (kind === "picker-video" && videos === null) {
      try {
        const list = await api.getVideoHistory()
        setVideos(list.filter((v) => v.state === "completed" && v.videoUrl))
      } catch (e) {
        setVideos([])
        setErr(friendlyError(e, "Couldn't load your videos."))
      }
    }
    if (kind === "picker-library" && library === null) {
      try {
        const list = await api.getLibrary({ kind: "image" })
        setLibrary(
          list
            .filter((i) => i.kind === "image")
            .map((i) => ({ id: String(i.id), name: i.name, url: ("url" in i && i.url) || i.previewUrl || "" }))
            .filter((i) => /^https?:\/\//i.test(i.url)),
        )
      } catch (e) {
        setLibrary([])
        setErr(friendlyError(e, "Couldn't load your library."))
      }
    }
  }

  const imageHistory = useMemo<{ id: string; url: string }[]>(() => {
    if (panel !== "picker-image") return []
    try {
      const raw = JSON.parse(window.localStorage.getItem(IMAGE_HISTORY_KEY) || "[]")
      return Array.isArray(raw) ? raw.filter((i) => i && typeof i.url === "string" && typeof i.id === "string") : []
    } catch {
      return []
    }
  }, [panel])

  const scheduleLabel = scheduleSet && date ? `Scheduled for ${date} ${time} (${tz})` : ""
  const tzList = useMemo(() => zones([defaultTz, browserTz]), [defaultTz, browserTz])

  const clearNetworkMedia = () => setCustom((c) => ({ ...c, [shownNet]: { ...c[shownNet], images: [], video: null } }))
  const useSharedMedia = () =>
    setCustom((c) => {
      const rest = { ...(c[shownNet] ?? {}) }
      delete rest.images
      delete rest.video
      return { ...c, [shownNet]: rest }
    })

  const firstAcct = (k: string) => selected.find((a) => a.platform === k)
  const netWarnings = (k: string): string[] => {
    const a = firstAcct(k)
    const p = platformOf(k)
    if (!a || !p) return []
    const v = variationFor(a)
    const w: string[] = []
    if (p.textLimit > 0 && v.content.length > p.textLimit) w.push(`${p.label} allows ${p.textLimit} characters (${v.content.length} used).`)
    if (p.imageRequired && !v.images.length) w.push(`${p.label} needs an image.`)
    if (p.videoRequired && !v.video) w.push(`${p.label} needs a video.`)
    return w
  }

  const dropFiles = async (files: FileList | File[] | null) => {
    if (!files) return
    for (const f of Array.from(files)) await upload(f.type.startsWith("video/") ? "video" : "image", f)
  }

  const savePersonal = () => {
    const title = tplTitle.trim()
    if (!title || !content.trim()) return setErr("Write your post and give the template a name.")
    const next = [{ id: `${Date.now()}`, title, text: content }, ...personal]
    setPersonal(next)
    window.localStorage.setItem(PERSONAL_KEY, JSON.stringify(next))
    setTplSaving(false)
    setTplTitle("")
    setTplTab("personal")
  }
  const createCampaignTag = async () => {
    const name = newTagName.trim()
    const audience = newTagAudience.trim()
    if (!name || !audience || tagBusy) return
    setTagBusy(true)
    setErr("")
    try {
      const created = await api.socialRequest<{ id: number; name: string; audience: string; posts: number }>("POST", "/campaigns", { name, audience })
      setCampaigns((current) => [created, ...current])
      setCampaignId(String(created.id))
      setTagSearch("")
      setNewTagName("")
      setNewTagAudience("")
      setNewTagOpen(false)
      setTagOpen(false)
      onChanged("Tag created and added to this post.", "ok")
    } catch (e) {
      setErr(friendlyError(e, "Couldn't create this tag. Please try again."))
    } finally {
      setTagBusy(false)
    }
  }
  const removePersonal = (id: string) => {
    const next = personal.filter((x) => x.id !== id)
    setPersonal(next)
    window.localStorage.setItem(PERSONAL_KEY, JSON.stringify(next))
  }
  const useTemplate = (text: string) => {
    if (content.trim() && !window.confirm("Replace your current text with this template?")) return
    setContent(text)
    setSide("preview")
  }

  const hasMedia = images.length > 0 || Boolean(video)
  const multi = selected.length > 1 && !customOn && !editing
  const noInput = !selected.length || (!content.trim() && !selected.some((a) => custom[a.platform]?.content?.trim()))
  const whenLabel = when === "later" && date ? `${date} ${time} (${tz})` : when === "later" ? "Set Date and Time" : "Publish Now"
  const q = chanSearch.trim().toLowerCase()
  const chanList = data.accounts.filter((a) => !q || `${a.name ?? ""} ${platformOf(a.platform)?.label ?? a.platform}`.toLowerCase().includes(q))
  const tplQ = tplSearch.trim().toLowerCase()
  const tagQ = tagSearch.trim().toLowerCase()
  const filteredCampaigns = campaigns.filter((c) => c.name.toLowerCase().includes(tagQ))
  const discover = TEMPLATES.filter((t) => (!tplCategory || t.category === tplCategory) && (!tplQ || `${t.title} ${t.category} ${t.text}`.toLowerCase().includes(tplQ)))
  const mine = personal.filter((t) => !tplQ || `${t.title} ${t.text}`.toLowerCase().includes(tplQ))

  const avatar = (a: Account) => (
    <span className="smc-av">
      {a.picture ? <img src={a.picture} alt="" /> : <i>{(a.name ?? platformOf(a.platform)?.label ?? "?")[0]}</i>}
      <span className="smc-av-badge"><BrandIcon k={a.platform} size={16} /></span>
    </span>
  )

  const toggleAll = () => setAccountIds(allSelected ? [] : connected.map((a) => a.id))

  const thumbs = (list: string[], vid: string, onRemoveImg: (i: number) => void, onRemoveVid: () => void, withReplace: boolean) => (
    <>
      {list.map((u, i) => (
        <div key={u + i} className="smx-thumb smc-thumb">
          <img src={u} alt="" />
          {withReplace && (
            <label className="rep" title="Replace">
              ⟲
              <input type="file" hidden accept="image/jpeg,image/png,image/gif" onChange={(e) => { void replaceImage(i, e.target.files?.[0]); e.target.value = "" }} />
            </label>
          )}
          <button type="button" aria-label="Remove image" onClick={() => onRemoveImg(i)}>×</button>
        </div>
      ))}
      {vid && (
        <div className="smx-thumb smc-thumb">
          <video src={vid} muted preload="metadata" />
          <span className="smc-play" aria-hidden>▶</span>
          <button type="button" aria-label="Remove video" onClick={onRemoveVid}>×</button>
        </div>
      )}
    </>
  )

  const dropzone = (
    <label
      className={`smc-drop${dragOver ? " over" : ""}`}
      onDragOver={(e) => { e.preventDefault(); setDragOver(true) }}
      onDragLeave={() => setDragOver(false)}
      onDrop={(e) => { e.preventDefault(); setDragOver(false); void dropFiles(e.dataTransfer.files) }}
    >
      <ComposerIcon name="image" size={20} />
      <span>Drag &amp; drop or <u>select a file</u></span>
      <input type="file" hidden multiple accept="image/jpeg,image/png,image/gif,video/mp4,video/quicktime" onChange={(e) => { void dropFiles(e.target.files); e.target.value = "" }} />
    </label>
  )

  const toolbar = (
    <div className="smc-tools">
      <div className="smc-rel">
        <button type="button" className="smc-tool" onClick={() => setPanel(panel === "plus" ? "" : "plus")} aria-label="Add media" aria-expanded={panel === "plus"}><ComposerIcon name="plus" size={15} /><ComposerIcon name="chevron" size={12} /></button>
        {panel === "plus" && (
          <>
            <div className="smc-scrim" onClick={() => setPanel("")} />
            <div className="smc-menu smc-menu-up" role="menu">
              <button type="button" role="menuitem" onClick={() => void openPicker("picker-image")}><ComposerIcon name="image" /> Image Generator</button>
              <button type="button" role="menuitem" onClick={() => void openPicker("picker-video")}><ComposerIcon name="image" /> Video Generator</button>
              <button type="button" role="menuitem" onClick={() => void openPicker("picker-library")}><ComposerIcon name="template" /> Library</button>
            </div>
          </>
        )}
      </div>
      <span className="smc-sep" />
      <button type="button" className="smc-tool" onClick={() => setPanel(panel === "emoji" ? "" : "emoji")} aria-label="Emoji"><ComposerIcon name="smile" /></button>
      <button type="button" className="smc-tool" onClick={() => insert("#")} aria-label="Hashtag">#</button>
      <span className="smx-grow" />
      <small className={`smc-count${strictLimit !== null && content.length > strictLimit ? " over" : ""}`}>{content.length}{strictLimit !== null ? ` / ${strictLimit}` : ""}</small>
    </div>
  )

  return (
    <div className="smx-overlay" role="presentation" onMouseDown={(e) => {
      if (tagOpen && e.target instanceof Element && !e.target.closest(".smc-tag-control")) setTagOpen(false)
      if (e.target === e.currentTarget && !busy) onClose()
    }}>
      <div ref={dialog} tabIndex={-1} className={`smx-modal smc${expanded ? " expanded" : ""}`} role="dialog" aria-modal="true" aria-label={editing ? "Edit post" : "Create post"}>
        <header className="smc-head">
          <h2>{editing ? "Edit Post" : "Create Post"}</h2>
          <div className="smc-tag-control">
            <button type="button" className={`smc-camp${campaignId ? " selected" : ""}`} onClick={() => { setTagOpen((open) => !open); setNewTagOpen(false) }} aria-expanded={tagOpen} aria-haspopup="dialog">
              <ComposerIcon name="tag" /><span>{campaigns.find((c) => String(c.id) === campaignId)?.name ?? "Tags"}</span><ComposerIcon name="chevron" size={13} />
            </button>
            {tagOpen && (
              <>
                <div className="smc-tag-menu" role="dialog" aria-label="Select a tag">
                  <div className="smc-search-wrap">
                    <ComposerIcon name="search" size={16} />
                    <input autoFocus className="smc-search" value={tagSearch} onChange={(e) => setTagSearch(e.target.value)} placeholder="Search tags" aria-label="Search tags" />
                  </div>
                  {newTagOpen ? (
                    <form className="smc-tag-create" onSubmit={(e) => { e.preventDefault(); void createCampaignTag() }}>
                      <div className="smc-tag-create-title"><b>Create a tag</b><button type="button" className="smc-ico" onClick={() => setNewTagOpen(false)} aria-label="Back to tags"><ComposerIcon name="close" size={15} /></button></div>
                      <label>Tag name<input autoFocus maxLength={120} value={newTagName} onChange={(e) => setNewTagName(e.target.value)} placeholder="e.g. Product launch" /></label>
                      <label>Target audience<input maxLength={500} value={newTagAudience} onChange={(e) => setNewTagAudience(e.target.value)} placeholder="Who is this campaign for?" /></label>
                      <p>Tags are saved as campaigns and can be reused on other posts.</p>
                      <button type="submit" className="smc-tag-new" disabled={tagBusy || !newTagName.trim() || !newTagAudience.trim()}>{tagBusy ? "Creating…" : "Create tag"}</button>
                    </form>
                  ) : (
                    <>
                      <div className="smc-tag-list">
                        {filteredCampaigns.map((campaign) => (
                          <button key={campaign.id} type="button" className={`smc-tag-option${String(campaign.id) === campaignId ? " selected" : ""}`} onClick={() => { setCampaignId(String(campaign.id)); setTagOpen(false); setTagSearch("") }}>
                            <span className="smc-tag-option-icon"><ComposerIcon name="tag" size={15} /></span>
                            <span><b>{campaign.name}</b><small>{campaign.audience}</small></span>
                            {String(campaign.id) === campaignId && <ComposerIcon name="check" size={16} />}
                          </button>
                        ))}
                        {!filteredCampaigns.length && (
                          <div className="smc-tag-empty">
                            <span className="smc-tag-empty-icon"><ComposerIcon name="tag" size={25} /></span>
                            <p>{campaigns.length ? "No tags match your search." : "There are no tags yet."}</p>
                          </div>
                        )}
                      </div>
                      <button type="button" className="smc-tag-new" onClick={() => setNewTagOpen(true)}><ComposerIcon name="plus" size={15} /> New tag</button>
                    </>
                  )}
                </div>
              </>
            )}
          </div>
          <span className="smx-grow" />
          <nav className="smc-tabs" aria-label="Side panel">
            <button type="button" className={side === "templates" ? "on" : ""} onClick={() => setSide(side === "templates" ? "preview" : "templates")}><ComposerIcon name="template" /> <span>Templates</span></button>
            <button type="button" className={side === "ai" ? "on" : ""} onClick={() => setSide(side === "ai" ? "preview" : "ai")}><ComposerIcon name="sparkles" /> <span>AI Assistant</span></button>
            <button type="button" className={side === "preview" ? "on" : ""} onClick={() => setSide("preview")}><ComposerIcon name="eye" /> <span>Preview</span></button>
          </nav>
          <button type="button" className="smc-ico" onClick={() => setExpanded((v) => !v)} aria-label={expanded ? "Collapse" : "Expand"}><ComposerIcon name={expanded ? "collapse" : "expand"} /></button>
          <button type="button" className="smc-ico" onClick={onClose} aria-label="Close" disabled={Boolean(busy)}><ComposerIcon name="close" /></button>
        </header>

        {err && (
          <div className="smx-notice err" role="alert">
            <span>{err}</span>
            <button type="button" onClick={() => setErr("")} aria-label="Dismiss">×</button>
          </div>
        )}

        <div className="smx-mb smc-body">
          <div className="smx-mcol smc-main">
            {results ? (
              <div className="smx-results">
                <h3>{results.filter((r) => r.post.status !== "failed").length} of {results.length} platforms completed</h3>
                <ul>
                  {results.map((r) => (
                    <li key={r.post.id}>
                      <span className={`smx-badge ${r.post.status}`}>{r.post.status}</span>
                      <b>{platformOf(r.post.platform)?.label ?? r.post.platform}</b>
                      <small>{r.account?.name ?? ""}</small>
                      {r.post.status === "failed" && <p className="smx-error">{r.post.error || "We couldn't publish to this platform."}</p>}
                    </li>
                  ))}
                </ul>
                <p className="smx-hint">Failed platforms stay in Posts, where you can edit and retry them.</p>
                <button type="button" className="smx-primary" onClick={onClose}>Done</button>
              </div>
            ) : (
              <>
                <div className="smc-accts">
                  {!editing && (
                    <div className="smc-channel-controls">
                      <div className="smc-rel">
                        <button type="button" className="smc-channels-btn" onClick={() => setPanel(panel === "channels" ? "" : "channels")} aria-expanded={panel === "channels"}><ComposerIcon name="plus" size={15} /> Channels</button>
                        {panel === "channels" && (
                          <>
                            <div className="smc-scrim" onClick={() => setPanel("")} />
                            <div className="smc-menu smc-chan" role="dialog" aria-label="Channels">
                              <div className="smc-search-wrap"><ComposerIcon name="search" size={16} /><input autoFocus className="smc-search" value={chanSearch} onChange={(e) => setChanSearch(e.target.value)} placeholder="Search channels" aria-label="Search channels" /></div>
                              <div className="smc-chan-head">
                                <span>Channels</span>
                                {connected.length > 1 && (
                                  <button type="button" className="smx-link" onClick={toggleAll}>{allSelected ? "Deselect all" : "Select all"}</button>
                                )}
                              </div>
                              <div className="smc-chan-list">
                                {chanList.length === 0 && <p className="smx-empty">{data.accounts.length ? "No channels match." : "No channels connected yet."}</p>}
                                {chanList.map((a) => {
                                  const ok = a.status === "connected" && !(a.platform === "linkedin" && a.canPublish === false)
                                  return (
                                    <label key={a.id} className={`smc-chan-row${ok ? "" : " off"}`}>
                                      {avatar(a)}
                                      <span>
                                        <b>{a.name ?? platformOf(a.platform)?.label}</b>
                                        <small>
                                          {platformOf(a.platform)?.label}
                                          {a.accountType === "organization" ? " · Company Page" : a.accountType === "member" ? " · Personal profile" : ""}
                                          {a.role ? ` · ${a.role.replace(/_/g, " ")}` : ""}
                                          {a.status !== "connected" ? " · connection expired" : !ok ? " · publishing permission unavailable" : ""}
                                        </small>
                                      </span>
                                      <input type="checkbox" disabled={!ok} checked={accountIds.includes(a.id)} onChange={() => toggle(a.id)} />
                                    </label>
                                  )
                                })}
                              </div>
                              <button type="button" className="smc-chan-connect" onClick={onConnect}><span><ComposerIcon name="plus" /></span> Connect a Channel</button>
                            </div>
                          </>
                        )}
                      </div>
                  {connected.length > 0 && !editing && (
                    <div className={`smc-all-accounts${allSelected ? " on" : ""}`}>
                      <button type="button" className="smc-all-toggle" onClick={toggleAll}>{allSelected ? "All" : `${selected.length} selected`}</button>
                      <div className="smc-account-stack">
                        {selected.slice(0, 3).map((a) => (
                          <span key={a.id} className="smc-sel" title={`${a.name ?? ""} - ${platformOf(a.platform)?.label ?? a.platform}`}>
                            {avatar(a)}
                            <button type="button" className="smc-sel-x" aria-label={`Remove ${a.name ?? "account"}`} onClick={() => toggle(a.id)}><ComposerIcon name="close" size={12} /></button>
                          </span>
                        ))}
                        {selected.length > 3 && <span className="smc-account-more">+{selected.length - 3}</span>}
                      </div>
                    </div>
                  )}
                  {editing && selected.map((a) => <span key={a.id} className="smc-sel" title={`${a.name ?? ""} - ${platformOf(a.platform)?.label ?? a.platform}`}>{avatar(a)}</span>)}
                  {!selected.length && <span className="smc-hintline">{data.accounts.length ? "Choose where to post" : "Connect a channel to start publishing"}</span>}
                  </div>
                  )}
                </div>

                {!customOn && (
                  <div className="smc-editor">
                    <textarea ref={area} id="smx-mc-text" className="smc-text" value={content} onChange={(e) => setContent(e.target.value)} placeholder="Start writing or get inspired with Templates" aria-label="Post" />
                    {panel === "emoji" && (
                      <div className="smx-emoji">
                        {EMOJIS.map((e) => <button key={e} type="button" onClick={() => { insert(e); setPanel("") }}>{e}</button>)}
                      </div>
                    )}
                    <div className="smc-media">
                      {thumbs(images, video, (i) => setImages(images.filter((_, j) => j !== i)), () => setVideo(""), true)}
                      {dropzone}
                    </div>
                    {busy === "upload" && <p className="smx-hint">Uploading…</p>}
                    {toolbar}
                  </div>
                )}

                {customOn && (
                  <div className="smc-nets">
                    <div className="smc-nets-head">
                      <button type="button" className="smx-link" onClick={() => setCustomOn(false)}>← Back to shared post</button>
                      <button type="button" className="smc-tool ai" disabled={busy !== ""} onClick={() => void doVariations()}>{busy === "variations" ? "Generating…" : <><ComposerIcon name="sparkles" /> AI variations</>}</button>
                    </div>
                    {netKeys.map((k) => {
                      const p = platformOf(k)
                      const open = shownNet === k
                      const text = custom[k]?.content ?? content
                      const w = netWarnings(k)
                      const own = custom[k]?.images !== undefined || (custom[k] !== undefined && "video" in (custom[k] as object))
                      const imgs = custom[k]?.images ?? images
                      const vid = custom[k] && "video" in (custom[k] as object) ? custom[k]?.video ?? "" : video
                      return (
                        <section key={k} className={`smc-net${open ? " open" : ""}`}>
                          <button type="button" className="smc-net-h" onClick={() => setActiveNet(k)} aria-expanded={open}>
                            <BrandIcon k={k} size={22} />
                            <b>{p?.label ?? k}</b>
                            {!open && <span className="smc-net-prev">{text || "No text"}</span>}
                            {w.length > 0 && <em className="smc-net-warn" title={w[0]}>⚠</em>}
                          </button>
                          {open && (
                            <div className="smc-net-b">
                              {w.map((m) => <div key={m} className="smc-warn">⚠ {m}</div>)}
                              <textarea
                                className="smc-text"
                                value={text}
                                onChange={(e) => setCustom((c) => ({ ...c, [k]: { ...c[k], content: e.target.value } }))}
                                aria-label={`${p?.label ?? k} text`}
                              />
                              <div className="smc-media">
                                {thumbs(imgs, vid, (i) => setCustom((c) => ({ ...c, [k]: { ...c[k], images: imgs.filter((_, j) => j !== i) } })), () => setCustom((c) => ({ ...c, [k]: { ...c[k], video: null } })), false)}
                                {!hasMedia && !own && dropzone}
                              </div>
                              <div className="smc-tools">
                                <button type="button" className="smc-tool" disabled={busy !== ""} onClick={() => void improveNetwork("hashtags")}># Add hashtags</button>
                                <button type="button" className="smc-tool" disabled={busy !== ""} onClick={() => void improveNetwork("cta")}>Add CTA</button>
                                <button type="button" className="smc-tool" onClick={clearNetworkMedia}>No media</button>
                                {own && <button type="button" className="smc-tool" onClick={useSharedMedia}>Use shared media</button>}
                                <span className="smx-grow" />
                                <small className={`smc-count${p && p.textLimit > 0 && text.length > p.textLimit ? " over" : ""}`}>{text.length}{p && p.textLimit > 0 ? ` / ${p.textLimit}` : ""}</small>
                              </div>
                            </div>
                          )}
                        </section>
                      )
                    })}
                  </div>
                )}

                {(panel === "picker-image" || panel === "picker-video" || panel === "picker-library") && (
                  <div className="smx-pop">
                    <div className="smx-pop-head">
                      <strong>{panel === "picker-image" ? "Your generated images" : panel === "picker-video" ? "Your generated videos" : "Your library images"}</strong>
                      <button type="button" onClick={() => setPanel("")}>Close</button>
                    </div>
                    <div className="smx-picker-grid">
                      {panel === "picker-image" && (imageHistory.length ? imageHistory.map((i) => (
                        <button key={i.id} type="button" onClick={() => { setImages((c) => [...c, i.url]); setPanel("") }}><img src={i.url} alt="" loading="lazy" /></button>
                      )) : <p className="smx-empty">No generated images on this device yet.</p>)}
                      {panel === "picker-video" && (videos === null ? <p className="smx-empty">Loading…</p> : videos.length ? videos.map((v) => (
                        <button key={v.id} type="button" onClick={() => { setVideo(v.videoUrl as string); setPanel("") }}>
                          <video src={`${v.videoUrl as string}#t=0.1`} muted preload="metadata" />
                          <small>{v.durationSeconds ? `${v.durationSeconds}s` : ""}</small>
                        </button>
                      )) : <p className="smx-empty">No completed videos yet.</p>)}
                      {panel === "picker-library" && (library === null ? <p className="smx-empty">Loading…</p> : library.length ? library.map((i) => (
                        <button key={i.id} type="button" onClick={() => { setImages((c) => [...c, i.url]); setPanel("") }}><img src={i.url} alt={i.name} loading="lazy" /></button>
                      )) : <p className="smx-empty">No public images in your library.</p>)}
                    </div>
                  </div>
                )}
              </>
            )}
          </div>

          {!results && (
            <aside className="smc-side">
              {side === "preview" && (
                <>
                  <h3 className="smc-side-h">{customOn && shownNet ? `${platformOf(shownNet)?.label ?? shownNet} Preview` : "Post Previews"}</h3>
                  {selected.length && (content.trim() || hasMedia || Object.keys(custom).length) ? (
                    <div className="smc-pvs">
                      {netKeys.map((k) => {
                        const a = firstAcct(k) as Account
                        const v = variationFor(a)
                        return (
                          <div key={k} className="smc-pv">
                            <div className="smc-pv-label"><BrandIcon k={k} size={16} /> {platformOf(k)?.label ?? k}</div>
                            <PostPreview platform={k} label={platformOf(k)?.label ?? k} accountName={a.name} picture={a.picture} content={v.content} images={v.images} video={v.video} textLimit={platformOf(k)?.textLimit} />
                          </div>
                        )
                      })}
                      <p className="smx-hint">Approximate preview. Each network may display your post slightly differently.</p>
                    </div>
                  ) : (
                    <div className="smc-empty">
                      <div className="smc-ph" aria-hidden><i /><b /><b /><u /></div>
                      <p>See your post's preview here</p>
                    </div>
                  )}
                </>
              )}

              {side === "templates" && (
                <>
                  <div className="smc-side-top">
                    <h3 className="smc-side-h">Templates</h3>
                    <button type="button" className="smc-ico" onClick={() => setTplSaving((v) => !v)} aria-label="Save current text as a template" title="Save current text as a template"><ComposerIcon name="plus" /></button>
                  </div>
                  {tplSaving && (
                    <div className="smc-save">
                      <input value={tplTitle} onChange={(e) => setTplTitle(e.target.value)} placeholder="Template name" aria-label="Template name" />
                      <button type="button" className="smx-primary" onClick={savePersonal}>Save</button>
                    </div>
                  )}
                  <div className="smc-sub">
                    <button type="button" className={tplTab === "discover" ? "on" : ""} onClick={() => setTplTab("discover")}>Discover</button>
                    <button type="button" className={tplTab === "personal" ? "on" : ""} onClick={() => setTplTab("personal")}>Personal</button>
                  </div>
                  <div className="smc-tpl-search">
                    <input className="smc-search" value={tplSearch} onChange={(e) => setTplSearch(e.target.value)} placeholder="Search templates..." aria-label="Search templates" />
                    <label className="smc-tpl-filter" title="Filter templates">
                      <span aria-hidden>☷</span>
                      <select value={tplCategory} onChange={(e) => setTplCategory(e.target.value)} aria-label="Filter templates">
                        <option value="">All</option>
                        {[...new Set(TEMPLATES.map((t) => t.category))].map((category) => <option key={category} value={category}>{category}</option>)}
                      </select>
                    </label>
                  </div>
                  <div className="smc-cards">
                    {tplTab === "discover" && discover.map((t) => (
                      <button key={t.title} type="button" className="smc-card" onClick={() => useTemplate(t.text)}>
                        <span className="smc-card-ico"><ComposerIcon name="template" size={19} /></span>
                        <b>{t.title}</b>
                        <span>{t.text}</span>
                      </button>
                    ))}
                    {tplTab === "personal" && mine.map((t) => (
                      <div key={t.id} className="smc-card as-div">
                        <button type="button" className="smc-card-main" onClick={() => useTemplate(t.text)}>
                          <span className="smc-card-ico"><ComposerIcon name="template" size={19} /></span>
                          <b>{t.title}</b>
                          <span>{t.text}</span>
                        </button>
                        <button type="button" className="smc-card-del" aria-label={`Delete ${t.title}`} onClick={() => removePersonal(t.id)}>×</button>
                      </div>
                    ))}
                    {tplTab === "discover" && !discover.length && <p className="smx-empty">No templates match.</p>}
                    {tplTab === "personal" && !mine.length && <p className="smx-empty">{personal.length ? "No templates match." : "No personal templates yet. Write a post and tap + to save it here."}</p>}
                  </div>
                  {tplTab === "discover" && <p className="smx-hint">Built-in starting points. Replace the [brackets] with your details.</p>}
                </>
              )}

              {side === "ai" && (
                <>
                  <h3 className="smc-side-h smc-ai-h"><ComposerIcon name="sparkles" /> AI Assistant</h3>
                  <label className="smc-lbl" htmlFor="smc-ai-idea">What do you want to write about?</label>
                  <textarea id="smc-ai-idea" className="smc-ai-in" rows={6} value={aiIdea} onChange={(e) => setAiIdea(e.target.value)} placeholder="Eg. Promote my photography course to get new signups. Registration closes in 3 days." />
                  <p className="smx-hint"><b>Pro tip:</b> Include key points, your target audience and your desired outcome for this post.</p>
                  <div className="smc-right">
                    <button type="button" className="smx-primary" disabled={aiBusy || !aiIdea.trim()} onClick={() => void doAi("generate")}>{aiBusy && aiAction === "generate" ? "Generating…" : <><ComposerIcon name="sparkles" /> Generate</>}</button>
                  </div>
                  {aiBusy && aiAction !== "generate" && <p className="smx-hint">Working…</p>}
                  {aiOut && !aiBusy && (
                    <div className="smx-ai-out">
                      <p>{aiOut}</p>
                      <div className="smx-mc-tools">
                        <button type="button" className="smx-primary" onClick={() => { setContent(aiOut); setAiOut("") }}>Replace</button>
                        <button type="button" className="smx-secondary" onClick={() => { insert((content ? "\n\n" : "") + aiOut); setAiOut("") }}>Insert</button>
                        <button type="button" className="smx-secondary" onClick={() => void doAi(aiAction)}>Regenerate</button>
                        <button type="button" className="smx-link" onClick={() => setAiOut("")}>Discard</button>
                      </div>
                    </div>
                  )}
                  {content.trim() && (
                    <>
                      <p className="smc-lbl">Improve your post</p>
                      <div className="smx-mc-tools">
                        {AI_ACTIONS.map((a) => (
                          <button key={a.id} type="button" className="smx-chipbtn" disabled={aiBusy} onClick={() => void doAi(a.id)}>{a.label}</button>
                        ))}
                      </div>
                      <input className="smc-search" value={aiLang} onChange={(e) => setAiLang(e.target.value)} placeholder="Language (for Translate)" aria-label="Language" />
                    </>
                  )}
                  <div className="smc-ai-resource">
                    <button type="button" onClick={() => setAiHelpOpen((open) => !open)} aria-expanded={aiHelpOpen}>
                      <ComposerIcon name="help" size={17} />
                      <span>Learn more about <b>writing clear prompts and AI limitations</b>.</span>
                    </button>
                    {aiHelpOpen && <p>Be specific about your audience, key points and preferred tone. Review generated text for accuracy and edit it before publishing.</p>}
                  </div>
                </>
              )}
            </aside>
          )}
        </div>

        {!results && (
          <footer className="smc-foot">
            {!editing && (
              <label className="smc-another"><input type="checkbox" checked={createAnother} onChange={(e) => setCreateAnother(e.target.checked)} /> Create Another</label>
            )}
            <button type="button" className="smc-draft" disabled={busy !== ""} onClick={() => void submit("draft")}>{busy === "draft" ? "Saving…" : selected.length > 1 ? "Save Drafts" : "Save Draft"}</button>
            <span className="smx-grow" />
            <div className="smc-split">
              <div className="smc-rel">
                <button type="button" className="smc-when" onClick={() => setWhenMenu((v) => !v)} aria-expanded={whenMenu}><ComposerIcon name={when === "later" ? "calendar" : "clock"} /> {whenLabel} <ComposerIcon name="chevron" size={14} /></button>
                {whenMenu && (
                  <>
                    <div className="smc-scrim" onClick={() => setWhenMenu(false)} />
                    <div className="smc-menu smc-menu-up smc-menu-right smc-when-menu" role="menu">
                      <button type="button" role="menuitem" className={when === "now" ? "on" : ""} onClick={() => { setWhen("now"); setWhenMenu(false); setPanel("") }}>
                        <b>Now</b><span>Publish your post right away.</span>
                      </button>
                      <button type="button" role="menuitem" className={when === "later" ? "on" : ""} onClick={() => { setWhen("later"); setWhenMenu(false); setPanel("schedule") }}>
                        <b>Set Date and Time</b><span>Choose a specific time to post.</span>
                      </button>
                    </div>
                  </>
                )}
                {panel === "schedule" && (
                  <>
                    <div className="smc-scrim" onClick={() => setPanel("")} />
                    <div className="smc-menu smc-menu-up smc-menu-right smc-sched">
                      <div className="smx-sched">
                        <label>Date<input type="date" value={date} onChange={(e) => setDate(e.target.value)} /></label>
                        <label>Time<input type="time" value={time} onChange={(e) => setTime(e.target.value)} /></label>
                        <label>Timezone
                          <select value={tz} onChange={(e) => setTz(e.target.value)}>
                            {tzList.map((z) => <option key={z}>{z}</option>)}
                          </select>
                        </label>
                      </div>
                      <div className="smx-mc-tools">
                        <button type="button" className="smx-primary" onClick={() => { if (!date) return setErr("Pick a date."); setScheduleSet(true); setWhen("later"); setPanel("") }}>Set time</button>
                        <button type="button" className="smx-link" onClick={() => { setScheduleSet(false); setDate(""); setWhen("now"); setPanel("") }}>Clear</button>
                      </div>
                    </div>
                  </>
                )}
              </div>
              {multi ? (
                <button type="button" className="smc-go" disabled={noInput || busy !== ""} onClick={() => setCustomOn(true)}>Customize for each network →</button>
              ) : (
                <button
                  type="button"
                  className="smc-go"
                  disabled={noInput || busy !== ""}
                  onClick={() => {
                    if (when === "later" && !date) return setPanel("schedule")
                    void submit(when === "later" ? "schedule" : "now")
                  }}
                >
                  {busy === "now" || busy === "schedule" ? "Working…" : when === "later" ? (selected.length > 1 ? "Schedule Posts" : "Schedule Post") : selected.length > 1 ? "Publish Posts" : "Publish Now"}
                </button>
              )}
            </div>
          </footer>
        )}
      </div>
    </div>
  )
}
