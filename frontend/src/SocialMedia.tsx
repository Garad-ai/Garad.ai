import { useCallback, useEffect, useMemo, useRef, useState } from "react"

import type { ApiClient } from "./core/api"
import SocialComposer, { friendlyError, TEMPLATES } from "./SocialComposer"
import { BrandIcon } from "./SocialBrand"
import PostPreview from "./SocialPreview"

type Props = {
  api: ApiClient
  isAuthenticated: boolean
  userName?: string
  onRequireLogin: () => void
}

export type PlatformInfo = {
  key: string
  label: string
  textLimit: number
  maxImages: number | null
  imageRequired: boolean
  videoRequired: boolean
  supportsImages: boolean
  supportsStory: boolean
  connectUrl: string
  callbackUrl?: string | null
  configured: boolean
}

export type Account = {
  id: number
  platform: string
  name: string | null
  picture: string | null
  type: string | null
  accountType?: "member" | "organization" | null
  role?: string | null
  canPublish?: boolean | null
  profileUrl?: string | null
  followers: number | null
  connectedAt: string | null
  expiresAt: string | null
  status: "connected" | "expired"
}

export type Post = {
  id: number
  platform: string
  accountId: number
  content: string
  images: string[]
  video: string | null
  status: "draft" | "scheduled" | "published" | "failed" | "pending"
  error: string | null
  scheduledAt: string | null
  postedAt: string | null
  campaignId: number | null
  tone: string | null
  link: string | null
  metrics: Record<string, unknown> | null
  engagement: number | null
  createdAt: string | null
}

type Campaign = { id: number; name: string; audience: string; posts: number }

type Company = {
  id: number
  name: string
  industry: string | null
  description: string | null
  tagline: string | null
  website: string | null
  instructions: string | null
}

export type Overview = {
  platforms: PlatformInfo[]
  accounts: Account[]
  posts: Post[]
  campaigns: Campaign[]
  companies: Company[]
  stats: {
    published: number
    scheduled: number
    drafts: number
    failed: number
    withMetrics: number
    engagement: number | null
    followers: number | null
  }
  workerNote: string
  serverTime: string
  timezone: string
}

type Section = "overview" | "calendar" | "posts" | "community" | "analytics" | "accounts" | "settings"

const communityTabFromPath = (): "comments" | "mentions" =>
  new URLSearchParams(window.location.search).get("community") === "mentions" ? "mentions" : "comments"

const socialSectionFromPath = (): Section => {
  const query = new URLSearchParams(window.location.search)
  if (query.get("community") === "comments" || query.get("community") === "mentions") return "community"
  if (query.get("insights") === "1") return "analytics"
  return "overview"
}

const socialMediaBasePath = () =>
  window.location.pathname.replace(/\/$/, "")

const SECTIONS: { id: Section; label: string }[] = [
  { id: "overview", label: "Overview" },
  { id: "calendar", label: "Calendar" },
  { id: "posts", label: "Posts" },
  { id: "community", label: "Community" },
  { id: "analytics", label: "Analytics" },
  { id: "accounts", label: "Accounts" },
  { id: "settings", label: "Settings" },
]

const REPLY_ACTIONS: { id: string; label: string }[] = [
  { id: "reply", label: "Suggest reply" },
  { id: "reply_professional", label: "Professional" },
  { id: "reply_friendly", label: "Friendly" },
  { id: "reply_short", label: "Short" },
  { id: "reply_thanks", label: "Thank you" },
  { id: "reply_answer", label: "Answer question" },
]

const fmt = (iso: string | null) =>
  iso ? new Date(iso).toLocaleString(undefined, { dateStyle: "medium", timeStyle: "short" }) : "—"

const dateKey = (date: Date) => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, "0")
  const day = String(date.getDate()).padStart(2, "0")
  return `${year}-${month}-${day}`
}

const metricNumber = (post: Post, key: string) => {
  const value = post.metrics?.[key]
  if (typeof value === "number" && Number.isFinite(value)) return value
  if (typeof value === "string" && value.trim() && Number.isFinite(Number(value))) return Number(value)
  return 0
}

const hasMetric = (post: Post, key: string) => {
  const value = post.metrics?.[key]
  return (typeof value === "number" && Number.isFinite(value)) ||
    (typeof value === "string" && value.trim() !== "" && Number.isFinite(Number(value)))
}

const metricLabel = (post: Post, key: string, compact: (value: number) => string) =>
  hasMetric(post, key) ? compact(metricNumber(post, key)) : "—"

const postInteractions = (post: Post) =>
  metricNumber(post, "like_count") +
  metricNumber(post, "comment_count") +
  metricNumber(post, "share_count")

const hasCompleteInteractionMetrics = (post: Post) =>
  hasMetric(post, "like_count") && hasMetric(post, "comment_count") && hasMetric(post, "share_count")

const postWhen = (p: Post) => p.scheduledAt || p.postedAt || p.createdAt

const groupPosts = (list: Post[]): Post[][] => {
  const sorted = [...list].sort((a, b) => new Date(b.createdAt ?? 0).getTime() - new Date(a.createdAt ?? 0).getTime())
  const groups: Post[][] = []
  for (const p of sorted) {
    const g = groups[groups.length - 1]
    const last = g?.[g.length - 1]
    const close = last && Math.abs(new Date(last.createdAt ?? 0).getTime() - new Date(p.createdAt ?? 0).getTime()) <= 5000
    if (g && close && last.scheduledAt === p.scheduledAt && (last.status === "draft") === (p.status === "draft")) g.push(p)
    else groups.push([p])
  }
  return groups
}

const CONNECT_SUB: Record<string, string> = {
  facebook: "Page",
  instagram: "Business or Creator",
  x: "Profile",
  linkedin: "Page or Profile",
  tiktok: "Business or Personal",
  youtube: "Channel",
  "youtube-shorts": "Channel",
}
const CONNECT_TITLE: Record<string, string> = {
  facebook: "Select Facebook Account Type",
  instagram: "How would you like to connect your Instagram account?",
}
const CONNECT_OPT: Record<string, { main: string; mainText: string; other?: string; otherText?: string }> = {
  facebook: {
    main: "Page",
    mainText: "A page is for businesses, brands and public figures. You'll choose which Page to connect after signing in.",
    other: "Group",
    otherText: "Publishing to Facebook Groups is not supported by Meta.",
  },
  instagram: {
    main: "Professional (Business & Creator)",
    mainText: "Automatic posting to your Instagram Business or Creator account.",
    other: "Personal",
    otherText: "Personal Instagram accounts can't be published to automatically.",
  },
}

export default function SocialMedia({ api, isAuthenticated, userName, onRequireLogin }: Props) {
  const [section, setSection] = useState<Section>(socialSectionFromPath)
  const [data, setData] = useState<Overview | null>(null)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState("")
  const [notice, setNotice] = useState<{ tone: "ok" | "err"; text: string } | null>(null)
  const [composer, setComposer] = useState<{ editing: Post | null; date?: string; text?: string } | null>(null)
  const [connectOpen, setConnectOpen] = useState(false)
  const [connectStep, setConnectStep] = useState<PlatformInfo | null>(null)
  const closeConnect = useCallback(() => {
    setConnectOpen(false)
    setConnectStep(null)
  }, [])
  const [connecting, setConnecting] = useState("")
  const [filter, setFilter] = useState<"all" | Post["status"]>("all")
  const [search, setSearch] = useState("")
  const [calMode, setCalMode] = useState<"month" | "week" | "list">("month")
  const [calCursor, setCalCursor] = useState(() => new Date())
  const [campName, setCampName] = useState("")
  const [campAudience, setCampAudience] = useState("")
  const [previewKey, setPreviewKey] = useState<number | null>(null)
  const [commTab, setCommTab] = useState<"comments" | "mentions">(communityTabFromPath)
  const [comment, setComment] = useState("")
  const [postCtx, setPostCtx] = useState("")
  const [reply, setReply] = useState("")
  const [replyAction, setReplyAction] = useState("reply")
  const [replyLang, setReplyLang] = useState("")
  const [replyBusy, setReplyBusy] = useState(false)
  const [communityPostId, setCommunityPostId] = useState<number | null>(null)
  const [communitySearch, setCommunitySearch] = useState("")
  const [communityChannel, setCommunityChannel] = useState("all")
  const [communityLayout, setCommunityLayout] = useState<"post" | "list">("post")
  const [analyticsRange, setAnalyticsRange] = useState<"7" | "30" | "mtd" | "last-month" | "custom">("30")
  const [analyticsFrom, setAnalyticsFrom] = useState(() => {
    const date = new Date()
    date.setDate(date.getDate() - 29)
    return dateKey(date)
  })
  const [analyticsTo, setAnalyticsTo] = useState(() => dateKey(new Date()))
  const [analyticsChannel, setAnalyticsChannel] = useState("all")
  const [analyticsCampaign, setAnalyticsCampaign] = useState("all")
  const [analyticsExportOpen, setAnalyticsExportOpen] = useState(false)
  const [analyticsPostMetric, setAnalyticsPostMetric] = useState<"reactions" | "comments">("reactions")
  const [analyticsUpdateOpen, setAnalyticsUpdateOpen] = useState(false)
  const abortRef = useRef<AbortController | null>(null)
  const pollRef = useRef<number | null>(null)
  const oauthPopupRef = useRef<Window | null>(null)
  const oauthPopupIdRef = useRef<string | null>(null)
  const oauthChannelRef = useRef<BroadcastChannel | null>(null)
  const oauthClosedAtRef = useRef<number | null>(null)
  const oauthStartedAtRef = useRef<number | null>(null)
  const oauthCompleteRef = useRef<(message: unknown) => void>(() => {})

  const flash = useCallback((tone: "ok" | "err", text: string) => setNotice({ tone, text }), [])

  const navigateCommunity = useCallback((tab: "comments" | "mentions") => {
    const nextUrl = new URL(window.location.href)
    nextUrl.pathname = socialMediaBasePath()
    nextUrl.searchParams.set("community", tab)
    if (window.location.href !== nextUrl.href) window.history.pushState({}, "", nextUrl)
    setSection("community")
    setCommTab(tab)
  }, [])

  const navigateSection = (next: Section) => {
    setSection(next)
    if (next === "community") {
      navigateCommunity("comments")
      return
    }
    if (next === "analytics") {
      const nextUrl = new URL(window.location.href)
      nextUrl.searchParams.delete("community")
      nextUrl.searchParams.set("insights", "1")
      window.history.pushState({}, "", nextUrl)
      return
    }
    if (new URLSearchParams(window.location.search).has("community") || new URLSearchParams(window.location.search).has("insights")) {
      const nextUrl = new URL(window.location.href)
      nextUrl.searchParams.delete("community")
      nextUrl.searchParams.delete("insights")
      window.history.pushState({}, "", nextUrl)
    }
  }

  useEffect(() => {
    const syncCommunityRoute = () => {
      if (/\/app\/social-media\/?$/.test(window.location.pathname)) {
        setSection(socialSectionFromPath())
        setCommTab(communityTabFromPath())
      }
    }
    window.addEventListener("popstate", syncCommunityRoute)
    return () => window.removeEventListener("popstate", syncCommunityRoute)
  }, [])

  const load = useCallback(
    async (silent = false) => {
      abortRef.current?.abort()
      const ctl = new AbortController()
      abortRef.current = ctl
      if (!silent) setLoading(true)
      setLoadError("")
      try {
        const res = await api.socialRequest<Overview>("GET", "/overview", undefined, ctl.signal)
        if (!ctl.signal.aborted) setData(res)
        return res
      } catch (e) {
        if (!ctl.signal.aborted) setLoadError(friendlyError(e, "We couldn't load your social data."))
      } finally {
        if (!ctl.signal.aborted && !silent) setLoading(false)
      }
      return null
    },
    [api],
  )

  useEffect(() => {
    if (!isAuthenticated) {
      setLoading(false)
      return
    }
    void load()
    return () => {
      abortRef.current?.abort()
      if (pollRef.current) window.clearInterval(pollRef.current)
      oauthChannelRef.current?.close()
    }
  }, [isAuthenticated, load])

  useEffect(() => {
    oauthCompleteRef.current = (payload) => {
      if (!payload || typeof payload !== "object") return
      const message = payload as { type?: unknown; status?: unknown; message?: unknown }
      if (message.type !== "garad-social-oauth-complete") return
      if ((message as { popupId?: unknown }).popupId !== oauthPopupIdRef.current) return

      oauthPopupRef.current = null
      oauthPopupIdRef.current = null
      oauthClosedAtRef.current = null
      oauthStartedAtRef.current = null
      oauthChannelRef.current?.close()
      oauthChannelRef.current = null
      if (pollRef.current) window.clearInterval(pollRef.current)
      pollRef.current = null
      setConnecting("")
      closeConnect()
      void load(true)
      flash(message.status === "success" ? "ok" : "err", String(message.message ?? "Social account connection finished."))
    }

    const onOAuthComplete = (event: MessageEvent) => {
      if (event.source !== oauthPopupRef.current) return
      oauthCompleteRef.current(event.data)
    }

    window.addEventListener("message", onOAuthComplete)
    return () => {
      window.removeEventListener("message", onOAuthComplete)
      oauthCompleteRef.current = () => {}
    }
  }, [closeConnect, flash, load])

  const platformOf = useCallback((key: string) => data?.platforms.find((p) => p.key === key), [data])
  const accountOf = useCallback((id: number) => data?.accounts.find((a) => a.id === id), [data])
  const connected = useMemo(() => (data?.accounts ?? []).filter((a) => a.status === "connected"), [data])

  const act = async (fn: () => Promise<unknown>, ok: string) => {
    try {
      await fn()
      flash("ok", ok)
      await load(true)
    } catch (e) {
      flash("err", friendlyError(e))
    }
  }

  const removePost = (p: Post) => {
    if (!window.confirm("Delete this post? This cannot be undone.")) return
    void act(() => api.socialRequest("DELETE", `/posts/${p.id}`), "Post deleted.")
  }
  const duplicatePost = (p: Post) => void act(() => api.socialRequest("POST", `/posts/${p.id}/duplicate`), "Duplicated as a draft.")
  const retryPost = (p: Post) => void act(() => api.socialRequest("PUT", `/posts/${p.id}`, { mode: "now" }), "Retry sent.")

  const disconnect = (a: Account) => {
    if (!window.confirm(`Disconnect ${platformOf(a.platform)?.label ?? a.platform}${a.name ? ` (${a.name})` : ""}?`)) return
    void act(() => api.socialRequest("DELETE", `/accounts/${a.id}`), "Account disconnected.")
  }

  // Real OAuth runs in a popup; completion signals refresh the connected-account overview.
  const connect = (p: PlatformInfo) => {
    if (!p.configured) return
    const connectUrl = new URL(p.connectUrl, window.location.origin)
    connectUrl.searchParams.set("garad_popup", "1")
    const popupId = typeof crypto.randomUUID === "function"
      ? crypto.randomUUID()
      : Array.from(crypto.getRandomValues(new Uint8Array(16)), (value) => value.toString(16).padStart(2, "0")).join("")
    connectUrl.searchParams.set("garad_popup_id", popupId)
    const pop = window.open(connectUrl.toString(), "garad-social-connect", "width=620,height=760")
    if (!pop) return flash("err", "Your browser blocked the popup. Allow popups for this site and try again.")
    oauthPopupRef.current = pop
    oauthPopupIdRef.current = popupId
    oauthClosedAtRef.current = null
    oauthStartedAtRef.current = Date.now()
    if ("BroadcastChannel" in window) {
      oauthChannelRef.current?.close()
      const channel = new BroadcastChannel(`garad-social-oauth-${popupId}`)
      channel.addEventListener("message", (event) => oauthCompleteRef.current(event.data))
      oauthChannelRef.current = channel
    }
    setConnecting(p.key)
    if (pollRef.current) window.clearInterval(pollRef.current)
    pollRef.current = window.setInterval(() => {
      if (pop.closed || Date.now() - (oauthStartedAtRef.current ?? Date.now()) > 5 * 60_000) {
        if (pop.closed && oauthClosedAtRef.current === null) oauthClosedAtRef.current = Date.now()
        if (pop.closed && Date.now() - (oauthClosedAtRef.current ?? Date.now()) < 5000) return
        if (pollRef.current) window.clearInterval(pollRef.current)
        pollRef.current = null
        oauthPopupRef.current = null
        oauthPopupIdRef.current = null
        oauthStartedAtRef.current = null
        oauthChannelRef.current?.close()
        oauthChannelRef.current = null
        setConnecting("")
        const callbackUrl = p.callbackUrl
          ? new URL(p.callbackUrl, window.location.origin)
          : new URL(connectUrl)
        if (!p.callbackUrl) callbackUrl.pathname = callbackUrl.pathname.replace(/\/redirect\/[^/]+$/, `/callback/${p.key}`)
        flash(
          "err",
          pop.closed
            ? `${p.label} did not return to Garad. If the provider showed a redirect URL error, register this exact URL in its developer settings: ${callbackUrl.toString()}`
            : "Connection timed out. Please try again.",
        )
      }
    }, 500)
  }

  const addCampaign = async () => {
    if (!campName.trim() || !campAudience.trim()) return flash("err", "Name and audience are required.")
    await act(() => api.socialRequest("POST", "/campaigns", { name: campName, audience: campAudience }), "Campaign created.")
    setCampName("")
    setCampAudience("")
  }

  const runReply = async (action: string) => {
    if (!comment.trim()) return flash("err", "Paste the comment you want to reply to.")
    setReplyAction(action)
    setReplyBusy(true)
    try {
      const isImprove = action === "reply_improve" || action === "translate"
      if (isImprove && !reply.trim()) {
        flash("err", "Write or generate a reply first.")
        return
      }
      if (action === "translate" && !replyLang.trim()) {
        flash("err", "Enter the language to translate into.")
        return
      }
      const res = await api.socialRequest<{ text: string }>("POST", "/assist", {
        action,
        text: isImprove ? reply : comment,
        context: isImprove ? comment : postCtx || undefined,
        language: replyLang || undefined,
      })
      setReply(res.text)
    } catch (e) {
      flash("err", friendlyError(e, "The AI assistant is unavailable right now."))
    } finally {
      setReplyBusy(false)
    }
  }

  const posts = data?.posts ?? []
  const [goal, setGoal] = useState<number>(() => Number(localStorage.getItem("garad.social.weeklyGoal")) || 0)
  const [goalEdit, setGoalEdit] = useState(false)
  const saveGoal = (n: number) => {
    const v = Number.isFinite(n) ? Math.max(0, Math.min(99, Math.floor(n))) : 0
    setGoal(v)
    if (v) localStorage.setItem("garad.social.weeklyGoal", String(v))
    else localStorage.removeItem("garad.social.weeklyGoal")
    setGoalEdit(false)
  }
  const dash = useMemo(() => {
    const DAY = 86400000
    const now = Date.now()
    const times = (data?.posts ?? []).filter((p) => p.status === "published" && p.postedAt).map((p) => new Date(p.postedAt as string).getTime())
    const inRange = (from: number, to: number) => times.filter((x) => x > from && x <= to).length
    const thisWeek = inRange(now - 7 * DAY, now)
    const lastWeek = inRange(now - 14 * DAY, now - 7 * DAY)
    let streak = 0
    for (let w = thisWeek > 0 ? 0 : 1; w < 520; w++) {
      if (inRange(now - (w + 1) * 7 * DAY, now - w * 7 * DAY) > 0) streak++
      else break
    }
    const h = new Date().getHours()
    const greeting = h < 12 ? "Good Morning" : h < 18 ? "Good Afternoon" : "Good Evening"
    const icon = h < 12 ? "🌅" : h < 18 ? "☀️" : "🌙"
    const delta = lastWeek > 0 ? Math.round(((thisWeek - lastWeek) / lastWeek) * 100) : null
    return { thisWeek, lastWeek, streak, greeting, icon, delta }
  }, [data])
  const compact = (n: number) => new Intl.NumberFormat(undefined, { notation: "compact", maximumFractionDigits: 1 }).format(n)
  const publishedPosts = useMemo(
    () => posts.filter((post) => post.status === "published" && post.postedAt),
    [posts],
  )
  const hasCommunityCommentMetrics = publishedPosts.some((post) => hasMetric(post, "comment_count"))
  const communityCommentCount = publishedPosts.reduce(
    (total, post) => total + metricNumber(post, "comment_count"),
    0,
  )
  const communityPosts = useMemo(
    () => publishedPosts.filter((post) => {
      const query = communitySearch.trim().toLowerCase()
      return (communityChannel === "all" || post.platform === communityChannel) &&
        (!query || post.content.toLowerCase().includes(query) ||
        (platformOf(post.platform)?.label ?? post.platform).toLowerCase().includes(query) ||
        (accountOf(post.accountId)?.name ?? "").toLowerCase().includes(query))
    }),
    [publishedPosts, communitySearch, communityChannel, platformOf, accountOf],
  )
  const selectedCommunityPost = communityPosts.find((post) => post.id === communityPostId) ?? communityPosts[0] ?? null

  const analyticsDates = useMemo(() => {
    const today = new Date()
    const end = new Date(today.getFullYear(), today.getMonth(), today.getDate(), 23, 59, 59, 999)
    let start: Date
    if (analyticsRange === "7") {
      start = new Date(end)
      start.setDate(start.getDate() - 6)
      start.setHours(0, 0, 0, 0)
    } else if (analyticsRange === "30") {
      start = new Date(end)
      start.setDate(start.getDate() - 29)
      start.setHours(0, 0, 0, 0)
    } else if (analyticsRange === "mtd") {
      start = new Date(today.getFullYear(), today.getMonth(), 1)
    } else if (analyticsRange === "last-month") {
      start = new Date(today.getFullYear(), today.getMonth() - 1, 1)
      return { start, end: new Date(today.getFullYear(), today.getMonth(), 0, 23, 59, 59, 999) }
    } else {
      start = analyticsFrom ? new Date(`${analyticsFrom}T00:00:00`) : new Date(end)
      return { start, end: analyticsTo ? new Date(`${analyticsTo}T23:59:59.999`) : end }
    }
    return { start, end }
  }, [analyticsRange, analyticsFrom, analyticsTo])

  const insightPosts = useMemo(
    () => publishedPosts.filter((post) => {
      const posted = new Date(post.postedAt as string)
      return posted >= analyticsDates.start &&
        posted <= analyticsDates.end &&
        (analyticsChannel === "all" || post.platform === analyticsChannel) &&
        (analyticsCampaign === "all" || String(post.campaignId ?? "") === analyticsCampaign)
    }),
    [publishedPosts, analyticsDates, analyticsChannel, analyticsCampaign],
  )

  const insightMetrics = useMemo(() => {
    const measured = insightPosts.filter((post) => post.metrics && Object.keys(post.metrics).length > 0)
    const sum = (key: string) => measured.reduce((total, post) => total + metricNumber(post, key), 0)
    const hasLikes = measured.some((post) => hasMetric(post, "like_count"))
    const hasComments = measured.some((post) => hasMetric(post, "comment_count"))
    const hasShares = measured.some((post) => hasMetric(post, "share_count"))
    const hasViews = measured.some((post) => hasMetric(post, "view_count") || hasMetric(post, "impression_count"))
    const likes = sum("like_count")
    const comments = sum("comment_count")
    const shares = sum("share_count")
    const views = sum("view_count") || sum("impression_count")
    const completeInteractionPosts = measured.filter(hasCompleteInteractionMetrics)
    const interactions = completeInteractionPosts.length
      ? completeInteractionPosts.reduce((total, post) => total + postInteractions(post), 0)
      : null
    return {
      measured: measured.length,
      hasLikes,
      hasComments,
      hasShares,
      hasViews,
      likes,
      comments,
      shares,
      views,
      interactions,
      engagementRate: interactions !== null && hasViews && views > 0 ? (interactions / views) * 100 : null,
      topPosts: [...measured].filter(hasCompleteInteractionMetrics)
        .sort((a, b) => postInteractions(b) - postInteractions(a)).slice(0, 5),
    }
  }, [insightPosts])
  const rankedTopPosts = useMemo(
    () => [...insightMetrics.topPosts].sort((a, b) =>
      analyticsPostMetric === "comments"
        ? metricNumber(b, "comment_count") - metricNumber(a, "comment_count")
        : (metricNumber(b, "like_count") + metricNumber(b, "share_count")) -
          (metricNumber(a, "like_count") + metricNumber(a, "share_count")),
    ),
    [insightMetrics.topPosts, analyticsPostMetric],
  )
  const recentWeekPosts = useMemo(() => {
    const now = Date.now()
    return publishedPosts.filter((post) => {
      const posted = new Date(post.postedAt as string).getTime()
      return posted >= now - 7 * 86400000 && posted <= now
    })
  }, [publishedPosts])
  const recentWeekTopPost = useMemo(
    () => recentWeekPosts.filter(hasCompleteInteractionMetrics).sort((a, b) => postInteractions(b) - postInteractions(a))[0] ?? null,
    [recentWeekPosts],
  )

  const insightTimeline = useMemo(() => {
    const days: { date: Date; label: string; value: number }[] = []
    const cursor = new Date(analyticsDates.start)
    cursor.setHours(0, 0, 0, 0)
    const last = new Date(analyticsDates.end)
    last.setHours(0, 0, 0, 0)
    while (cursor <= last && days.length < 62) {
      const key = dateKey(cursor)
      const value = insightPosts
        .filter((post) => post.postedAt && dateKey(new Date(post.postedAt)) === key)
        .filter(hasCompleteInteractionMetrics)
        .reduce((total, post) => total + postInteractions(post), 0)
      days.push({ date: new Date(cursor), label: cursor.toLocaleDateString(undefined, { month: "short", day: "numeric" }), value })
      cursor.setDate(cursor.getDate() + 1)
    }
    return days
  }, [analyticsDates, insightPosts])

  const downloadAnalytics = (format: "csv" | "markdown") => {
    const escapeCsv = (value: unknown) => `"${String(value ?? "").replace(/"/g, '""')}"`
    const rows = [
      ["Published date", "Channel", "Account", "Campaign", "Post", "Likes", "Comments", "Shares", "Views", "Engagement"],
      ...insightPosts.map((post) => [
        post.postedAt,
        platformOf(post.platform)?.label ?? post.platform,
        accountOf(post.accountId)?.name ?? "",
        data?.campaigns.find((campaign) => campaign.id === post.campaignId)?.name ?? "",
        post.content,
        metricNumber(post, "like_count"),
        metricNumber(post, "comment_count"),
        metricNumber(post, "share_count"),
        metricNumber(post, "view_count") || metricNumber(post, "impression_count"),
        postInteractions(post),
      ]),
    ]
    const text = format === "csv"
      ? rows.map((row) => row.map(escapeCsv).join(",")).join("\r\n")
      : [
          "# Garad Social Insights",
          "",
          `Period: ${analyticsDates.start.toLocaleDateString()} – ${analyticsDates.end.toLocaleDateString()}`,
          "",
          "| Date | Channel | Account | Post | Likes | Comments | Shares | Views | Engagement |",
          "| --- | --- | --- | --- | ---: | ---: | ---: | ---: | ---: |",
          ...insightPosts.map((post) => `| ${post.postedAt ? new Date(post.postedAt).toLocaleDateString() : ""} | ${platformOf(post.platform)?.label ?? post.platform} | ${accountOf(post.accountId)?.name ?? ""} | ${post.content.replace(/\|/g, "\\|").replace(/\n/g, " ")} | ${metricNumber(post, "like_count")} | ${metricNumber(post, "comment_count")} | ${metricNumber(post, "share_count")} | ${metricNumber(post, "view_count") || metricNumber(post, "impression_count")} | ${postInteractions(post)} |`),
        ].join("\n")
    const blob = new Blob([text], { type: format === "csv" ? "text/csv;charset=utf-8" : "text/markdown;charset=utf-8" })
    const url = URL.createObjectURL(blob)
    const anchor = document.createElement("a")
    anchor.href = url
    anchor.download = `garad-social-insights-${dateKey(analyticsDates.start)}-${dateKey(analyticsDates.end)}.${format === "csv" ? "csv" : "md"}`
    anchor.click()
    URL.revokeObjectURL(url)
    setAnalyticsExportOpen(false)
  }
  const visiblePosts = posts.filter(
    (p) => (filter === "all" || p.status === filter) && (!search.trim() || p.content.toLowerCase().includes(search.trim().toLowerCase())),
  )

  const renderGroup = (g: Post[]) => {
    const first = g[0]
    const ok = g.filter((p) => p.status === "published").length
    const failed = g.filter((p) => p.status === "failed").length
    const attempted = ok + failed
    const summary =
      g.length > 1 && attempted > 0
        ? `${ok} of ${g.length} platforms completed`
        : g.length > 1
          ? `${g.length} platforms · ${first.status}`
          : ""
    return (
      <li key={first.id} className="smx-post">
        <div className="smx-post-top">
          <b>{summary || (platformOf(first.platform)?.label ?? first.platform)}</b>
          <time>{fmt(first.status === "published" ? first.postedAt : postWhen(first))}</time>
        </div>
        <p>{first.content.length > 220 ? `${first.content.slice(0, 220)}…` : first.content}</p>
        {(first.images.length > 0 || first.video) && (
          <div className="smx-thumbs">
            {first.images.slice(0, 4).map((u) => <img key={u} src={u} alt="" loading="lazy" />)}
            {first.video && <span className="smx-chip">Video attached</span>}
          </div>
        )}
        <ul className="smx-plats">
          {g.map((p) => (
            <li key={p.id}>
              <div className="smx-plat-line">
                <span className={`smx-badge ${p.status}`}>{p.status}</span>
                <strong>{platformOf(p.platform)?.label ?? p.platform}</strong>
                <small>{accountOf(p.accountId)?.name ?? ""}</small>
              </div>
              {p.status === "failed" && <p className="smx-error">{p.error || "We couldn't publish to this platform."}</p>}
              <div className="smx-actions">
                {p.status !== "published" && <button type="button" onClick={() => setComposer({ editing: p })}>Edit</button>}
                {p.status === "failed" && <button type="button" onClick={() => retryPost(p)}>Retry</button>}
                <button type="button" onClick={() => duplicatePost(p)}>Duplicate</button>
                {p.link && <a href={p.link} target="_blank" rel="noopener noreferrer">Open post</a>}
                <button type="button" className="danger" onClick={() => removePost(p)}>Delete</button>
              </div>
            </li>
          ))}
        </ul>
        <div className="smx-actions">
          <button type="button" onClick={() => setPreviewKey(previewKey === first.id ? null : first.id)}>
            {previewKey === first.id ? "Hide preview" : "Preview"}
          </button>
        </div>
        {previewKey === first.id && (
          <div className="smx-pv-list">
            {g.map((p) => {
              const a = accountOf(p.accountId)
              return <PostPreview key={p.id} platform={p.platform} label={platformOf(p.platform)?.label ?? p.platform} accountName={a?.name} picture={a?.picture} content={p.content} images={p.images} video={p.video} textLimit={platformOf(p.platform)?.textLimit} />
            })}
          </div>
        )}
      </li>
    )
  }

  const renderCalendar = () => {
    const y = calCursor.getFullYear()
    const m = calCursor.getMonth()
    const dated = posts.filter((p) => postWhen(p))
    const key = (d: Date) => `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`
    const byDay = new Map<string, Post[]>()
    for (const p of dated) {
      const k = key(new Date(postWhen(p) as string))
      byDay.set(k, [...(byDay.get(k) ?? []), p])
    }
    const shift = (n: number) => {
      const d = new Date(calCursor)
      if (calMode === "week") d.setDate(d.getDate() + 7 * n)
      else d.setMonth(d.getMonth() + n, 1)
      setCalCursor(d)
    }
    let days: Date[] = []
    if (calMode === "month") {
      const first = new Date(y, m, 1)
      const start = new Date(y, m, 1 - first.getDay())
      days = Array.from({ length: 42 }, (_, i) => new Date(start.getFullYear(), start.getMonth(), start.getDate() + i))
    } else if (calMode === "week") {
      const start = new Date(calCursor)
      start.setDate(start.getDate() - start.getDay())
      days = Array.from({ length: 7 }, (_, i) => new Date(start.getFullYear(), start.getMonth(), start.getDate() + i))
    }
    const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`
    const today = new Date()
    today.setHours(0, 0, 0, 0)
    return (
      <div className="smx-card">
        <div className="smx-cal-head">
          <div className="smx-seg">
            {(["month", "week", "list"] as const).map((v) => (
              <button key={v} type="button" className={calMode === v ? "active" : ""} onClick={() => setCalMode(v)}>
                {v[0].toUpperCase() + v.slice(1)}
              </button>
            ))}
          </div>
          {calMode !== "list" && (
            <div className="smx-cal-nav">
              <button type="button" onClick={() => shift(-1)} aria-label="Previous">‹</button>
              <strong>{calCursor.toLocaleDateString(undefined, { month: "long", year: "numeric" })}</strong>
              <button type="button" onClick={() => shift(1)} aria-label="Next">›</button>
              <button type="button" onClick={() => setCalCursor(new Date())}>Today</button>
            </div>
          )}
        </div>
        {calMode === "list" ? (
          dated.length ? (
            <ul className="smx-list">
              {groupPosts([...dated].sort((a, b) => new Date(postWhen(a) as string).getTime() - new Date(postWhen(b) as string).getTime())).map(renderGroup)}
            </ul>
          ) : (
            <p className="smx-empty">No posts have a date yet.</p>
          )
        ) : (
          <div className={`smx-grid ${calMode}`}>
            {["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"].map((d) => <div key={d} className="smx-dow">{d}</div>)}
            {days.map((d) => {
              const items = byDay.get(key(d)) ?? []
              const out = calMode === "month" && d.getMonth() !== m
              const canAdd = d.getTime() >= today.getTime()
              return (
                <div key={key(d)} className={`smx-day${out ? " out" : ""}${key(d) === key(new Date()) ? " today" : ""}`}>
                  <span>{d.getDate()}</span>
                  {items.slice(0, calMode === "week" ? 20 : 3).map((p) => (
                    <button key={p.id} type="button" className={`smx-evt ${p.status}`} onClick={() => (p.status === "published" ? setPreviewKey(p.id) : setComposer({ editing: p }))} title={p.content}>
                      {new Date(postWhen(p) as string).toLocaleTimeString(undefined, { hour: "2-digit", minute: "2-digit" })} {platformOf(p.platform)?.label ?? p.platform}
                    </button>
                  ))}
                  {items.length > 3 && calMode === "month" && <small>+{items.length - 3} more</small>}
                  {canAdd && (
                    <button type="button" className="smx-add" aria-label={`Create post on ${iso(d)}`} onClick={() => setComposer({ editing: null, date: iso(d) })}>+</button>
                  )}
                </div>
              )
            })}
          </div>
        )}
        {!dated.length && calMode !== "list" && <p className="smx-empty">Nothing is scheduled yet. Create a post to see it here.</p>}
      </div>
    )
  }

  if (!isAuthenticated) {
    return (
      <section className="smx">
        <div className="smx-card smx-center">
          <h2>Social Media Manager</h2>
          <p>Log in to connect accounts, write posts and schedule them.</p>
          <button className="smx-primary" type="button" onClick={onRequireLogin}>Log in</button>
        </div>
      </section>
    )
  }

  return (
    <section className="smx" aria-label="Social Media Manager">
      <div className="smx-top">
        <nav className="smx-nav" role="tablist" aria-label="Social Media sections">
          {SECTIONS.map((s) => (
            <button key={s.id} role="tab" aria-selected={section === s.id} className={section === s.id ? "active" : ""} onClick={() => navigateSection(s.id)} type="button">
              {s.label}
              {s.id === "posts" && data ? <em>{groupPosts(data.posts).length}</em> : null}
            </button>
          ))}
        </nav>
        <div className="smx-top-actions">
          <button type="button" className="smx-secondary" onClick={() => setConnectOpen(true)}>Connect account</button>
          <button type="button" className="smx-primary" onClick={() => setComposer({ editing: null })} disabled={!data}>+ Create Post</button>
        </div>
      </div>

      {notice && (
        <div className={`smx-notice ${notice.tone}`} role="status">
          <span>{notice.text}</span>
          <button type="button" onClick={() => setNotice(null)} aria-label="Dismiss">×</button>
        </div>
      )}

      {loading && !data && <div className="smx-skeleton" aria-busy="true"><i /><i /><i /><i /></div>}

      {loadError && !data && (
        <div className="smx-card smx-center">
          <p className="smx-error">{loadError}</p>
          <button className="smx-primary" type="button" onClick={() => void load()}>Try again</button>
        </div>
      )}

      {data && (
        <div className={`smx-body${section === "community" ? " smx-body-community" : ""}${section === "analytics" ? " smx-body-insights" : ""}`}>
          {section === "overview" && (
            <>
              <div className="smx-dash-head">
                <span className="smx-dash-sun" aria-hidden>{dash.icon}</span>
                <div>
                  <h2>{dash.greeting}{userName ? `, ${userName}` : ""}!</h2>
                  <small>{new Date().toLocaleDateString(undefined, { weekday: "short", month: "short", day: "numeric", year: "numeric" })}</small>
                </div>
              </div>

              <div className="smx-goals">
                <div className="smx-goal">
                  <span className={`smx-ring${dash.streak > 0 ? " on" : ""}`}>{dash.streak}</span>
                  <div><b>Week Streak</b><small>{dash.streak > 0 ? "Streak hit, well done!" : "Publish a post this week to start a streak"}</small></div>
                </div>
                <div className="smx-goal">
                  <span className={`smx-ring${goal > 0 && dash.thisWeek >= goal ? " on" : ""}`}>{goal > 0 ? `${Math.min(dash.thisWeek, goal)}/${goal}` : 0}</span>
                  <div>
                    <b>Posting Goals</b>
                    {goalEdit ? (
                      <small>
                        <input className="smx-goal-in" type="number" min={1} max={99} autoFocus defaultValue={goal || 3}
                          onKeyDown={(e) => { if (e.key === "Enter") saveGoal(Number((e.target as HTMLInputElement).value)); if (e.key === "Escape") setGoalEdit(false) }}
                          onBlur={(e) => saveGoal(Number(e.target.value))} /> posts / week
                      </small>
                    ) : (
                      <small>{goal > 0 ? `${dash.thisWeek} of ${goal} posts this week · ` : "No goals yet · "}<button type="button" className="smx-link" onClick={() => setGoalEdit(true)}>{goal > 0 ? "Change" : "Set goals"}</button></small>
                    )}
                  </div>
                </div>
                <div className="smx-goal">
                  <span className="smx-ring">–</span>
                  <div><b>Comment Score</b><small>Not available yet</small></div>
                </div>
              </div>

              <h4 className="smx-sec">Weekly Pulse <small>· Last 7 days · Compared to previous 7 days</small></h4>
              <div className="smx-stats smx-pulse">
                <div className="smx-stat">
                  <small>Posts</small>
                  <strong>{dash.thisWeek}</strong>
                  <em className={dash.delta === null ? "" : dash.delta >= 0 ? "up" : "down"}>
                    {dash.delta === null ? "No posts last week" : `${dash.delta >= 0 ? "▲ +" : "▼ "}${dash.delta}% vs last week`}
                  </em>
                </div>
                <div className="smx-stat">
                  <small>Total Followers</small>
                  <strong>{data.stats.followers === null ? "No data" : compact(data.stats.followers)}</strong>
                  <em>{connected.length ? `${connected.length} connected account${connected.length === 1 ? "" : "s"}` : "No accounts connected"}</em>
                </div>
                <div className="smx-stat">
                  <small>Engagement</small>
                  <strong>{data.stats.engagement === null ? "No data" : compact(data.stats.engagement)}</strong>
                  <em>{data.stats.withMetrics ? `${data.stats.withMetrics} posts with metrics` : "No metrics reported yet"}</em>
                </div>
              </div>

              <div className="smx-dash-cols">
                <div>
                  <h4 className="smx-sec">Up Next <small>· {data.stats.scheduled} posts scheduled</small></h4>
                  <div className="smx-card smx-upnext">
                    {posts.some((p) => p.status === "scheduled") ? (
                      <ul className="smx-list">{groupPosts(posts.filter((p) => p.status === "scheduled")).slice(0, 4).map(renderGroup)}</ul>
                    ) : (
                      <div className="smx-center">
                        <p className="smx-empty">No posts scheduled yet.<br />You'll see upcoming posts here.</p>
                        <button className="smx-primary" type="button" onClick={() => setComposer({ editing: null })} disabled={!connected.length && false}>+ Create Post</button>
                      </div>
                    )}
                  </div>
                </div>
                <div>
                  <h4 className="smx-sec">Comments</h4>
                  <div className="smx-card smx-center">
                    <p className="smx-empty">Comments from your networks aren't available yet.<br />Paste a comment in Community and the AI will draft a reply.</p>
                    <button type="button" onClick={() => setSection("community")}>Open Community</button>
                  </div>
                </div>
              </div>

              {!connected.length && (
                <div className="smx-card smx-center">
                  <h3>Connect your first account</h3>
                  <p>Connect a social account to start publishing.</p>
                  <button className="smx-primary" type="button" onClick={() => setConnectOpen(true)}>Connect account</button>
                </div>
              )}

              <div className="smx-stats">
                {[
                  ["Published", data.stats.published],
                  ["Scheduled", data.stats.scheduled],
                  ["Drafts", data.stats.drafts],
                  ["Failed", data.stats.failed],
                ].map(([k, v]) => (
                  <div key={String(k)} className="smx-stat"><small>{k}</small><strong>{v}</strong></div>
                ))}
              </div>

              <h4 className="smx-sec">Templates</h4>
              <div className="smx-tpl-row">
                {TEMPLATES.slice(0, 4).map((tp) => (
                  <button key={tp.title} type="button" className="smx-tpl-card" onClick={() => setComposer({ editing: null, text: tp.text })}>
                    <small>{tp.category}</small>
                    <b>{tp.title}</b>
                    <span>{tp.text.split("\n")[0]}</span>
                  </button>
                ))}
              </div>

              <div className="smx-card">
                <h3>Recent activity</h3>
                {posts.length ? <ul className="smx-list">{groupPosts(posts).slice(0, 5).map(renderGroup)}</ul> : <p className="smx-empty">No posts yet.</p>}
              </div>              <p className="smx-hint">{data.workerNote}</p>
            </>
          )}

          {section === "calendar" && renderCalendar()}

          {section === "posts" && (
            <div className="smx-card">
              <div className="smx-row">
                <div className="smx-seg smx-seg-wrap">
                  {(["all", "draft", "scheduled", "published", "failed"] as const).map((f) => (
                    <button key={f} type="button" className={filter === f ? "active" : ""} onClick={() => setFilter(f)}>
                      {f === "all" ? "All" : f[0].toUpperCase() + f.slice(1)}
                    </button>
                  ))}
                </div>
                <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search posts" aria-label="Search posts" />
              </div>
              {visiblePosts.length ? (
                <ul className="smx-list">{groupPosts(visiblePosts).map(renderGroup)}</ul>
              ) : (
                <p className="smx-empty">{posts.length ? "No posts match this filter." : "No posts yet. Create your first post."}</p>
              )}
            </div>
          )}

          {section === "community" && (
            <div className="smx-community">
              <header className="smx-community-head">
                <div>
                  <h2>Community</h2>
                </div>
                <div className="smx-community-tabs" role="tablist" aria-label="Community inbox type">
                  <button type="button" role="tab" id="smx-comments-tab" aria-controls="smx-comments-panel" aria-selected={commTab === "comments"} className={commTab === "comments" ? "active" : ""} onClick={() => navigateCommunity("comments")}>Comments{hasCommunityCommentMetrics && <em>{compact(communityCommentCount)}</em>}</button>
                  <button type="button" role="tab" id="smx-mentions-tab" aria-controls="smx-mentions-panel" aria-selected={commTab === "mentions"} className={commTab === "mentions" ? "active" : ""} onClick={() => navigateCommunity("mentions")}>Mentions</button>
                </div>
              </header>
              {commTab === "mentions" ? (
                <div className="smx-community-mentions-page" id="smx-mentions-panel" role="tabpanel" aria-labelledby="smx-mentions-tab">
                  <div className="smx-community-mentions-content">
                    <span className="smx-community-mention-mark" aria-hidden="true">@</span>
                    <h3>Mentions are not available yet</h3>
                    <p>Garad does not currently retrieve mention conversations from social networks. Connect an account to manage your publishing here; no example mentions are shown.</p>
                    <div className="smx-community-channel-card">
                      <p>Connect a channel to get started</p>
                      <div className="smx-community-channel-grid">
                        {data.platforms.map((platform) => (
                          <button key={platform.key} type="button" onClick={() => { setConnectStep(platform); setConnectOpen(true) }}>
                            <span><BrandIcon k={platform.key} size={23} /></span>
                            <b>{platform.label}</b>
                            {!platform.configured && <small>Not configured</small>}
                          </button>
                        ))}
                      </div>
                    </div>
                    <button type="button" className="smx-link smx-community-back" onClick={() => navigateCommunity("comments")}>Back to comments</button>
                  </div>
                </div>
              ) : (
                <div id="smx-comments-panel" role="tabpanel" aria-labelledby="smx-comments-tab">
              <div className="smx-community-toolbar">
                <label className="smx-community-search">
                  <span className="sr-only">Search published posts</span>
                  <input value={communitySearch} onChange={(event) => setCommunitySearch(event.target.value)} placeholder="Search published posts" />
                </label>
                <select aria-label="Filter community posts by channel" value={communityChannel} onChange={(event) => setCommunityChannel(event.target.value)}>
                  <option value="all">All channels</option>
                  {data.platforms.filter((platform) => publishedPosts.some((post) => post.platform === platform.key)).map((platform) => (
                    <option key={platform.key} value={platform.key}>{platform.label}</option>
                  ))}
                </select>
                <div className="smx-community-layout" aria-label="Post list layout">
                  <button type="button" aria-pressed={communityLayout === "post"} className={communityLayout === "post" ? "active" : ""} onClick={() => setCommunityLayout("post")}>By post</button>
                  <button type="button" aria-pressed={communityLayout === "list"} className={communityLayout === "list" ? "active" : ""} onClick={() => setCommunityLayout("list")}>List</button>
                </div>
              </div>
              <div className={`smx-community-grid ${communityPosts.length === 0 ? "empty" : ""}`}>
                <aside className="smx-community-posts" aria-label="Published posts">
                  <div className="smx-community-list-head"><b>Published posts</b><span>{communityPosts.length}</span></div>
                  {communityPosts.length ? communityPosts.map((post) => {
                    const platform = platformOf(post.platform)
                    const account = accountOf(post.accountId)
                    const selected = selectedCommunityPost?.id === post.id
                    return (
                      <button
                        type="button"
                        key={post.id}
                        className={`smx-community-post ${selected ? "active" : ""} ${communityLayout === "list" ? "list" : ""}`}
                        aria-pressed={selected}
                        onClick={() => {
                          setCommunityPostId(post.id)
                          setPostCtx(post.content)
                          setReply("")
                        }}
                      >
                        <span className="smx-community-post-top"><b>{account?.name ?? platform?.label ?? post.platform}</b><time>{fmt(post.postedAt)}</time></span>
                        <span className="smx-community-post-text">{post.content || "Published post"}</span>
                        <span className="smx-community-post-meta">
                          <span>{platform?.label ?? post.platform}</span>
                          <span>{metricLabel(post, "comment_count", compact)} comments recorded</span>
                          <span>{hasCompleteInteractionMetrics(post) ? `${compact(postInteractions(post))} interactions` : "Interactions unavailable"}</span>
                        </span>
                      </button>
                    )
                  }) : <p className="smx-community-empty">No published posts match these filters.</p>}
                </aside>
                <div className="smx-community-detail">
                {selectedCommunityPost ? (
                    <>
                      <div className="smx-community-selected-head">
                        <div><h3>Comments</h3><p>{platformOf(selectedCommunityPost.platform)?.label ?? selectedCommunityPost.platform} · {fmt(selectedCommunityPost.postedAt)}</p></div>
                        {selectedCommunityPost.link && <a href={selectedCommunityPost.link} target="_blank" rel="noopener noreferrer">Open post ↗</a>}
                      </div>
                      <article className="smx-community-preview">
                        <div className="smx-community-account">
                          {accountOf(selectedCommunityPost.accountId)?.picture
                            ? <img src={accountOf(selectedCommunityPost.accountId)?.picture ?? ""} alt="" />
                            : <span>{(accountOf(selectedCommunityPost.accountId)?.name ?? "G").slice(0, 1)}</span>}
                          <div><b>{accountOf(selectedCommunityPost.accountId)?.name ?? "Connected account"}</b><small>{platformOf(selectedCommunityPost.platform)?.label ?? selectedCommunityPost.platform}</small></div>
                        </div>
                        <p>{selectedCommunityPost.content}</p>
                        {selectedCommunityPost.images[0] && <img className="smx-community-media" src={selectedCommunityPost.images[0]} alt="Media attached to the published post" />}
                        {selectedCommunityPost.video && <span className="smx-community-video">Video attached</span>}
                        <div className="smx-community-post-metrics">
                          <span>{metricLabel(selectedCommunityPost, "like_count", compact)} likes</span>
                          <span>{metricLabel(selectedCommunityPost, "comment_count", compact)} comments</span>
                          <span>{metricLabel(selectedCommunityPost, "share_count", compact)} shares</span>
                        </div>
                      </article>
                      <div className="smx-community-unavailable smx-community-thread">
                        <span className="smx-community-icon" aria-hidden="true">▤</span>
                        <h3>Comment thread unavailable</h3>
                        <p>The count above comes from the latest saved post metrics. Garad’s current backend does not retrieve comment text or publish replies to this network, so this inbox will not invent a thread.</p>
                        <button type="button" className="smx-secondary" onClick={() => {
                          setPostCtx(selectedCommunityPost.content)
                          document.getElementById("smx-comment")?.focus()
                        }}>Draft a reply with AI</button>
                      </div>
                    </>
                  ) : (
                    <div className="smx-community-empty-state">
                      <svg className="smx-community-empty-art" viewBox="0 0 300 205" role="img" aria-label="Comment cards illustration">
                        <path d="M57 36c-5 21 2 53 16 72 19 26 55 47 91 49 46 2 78-30 84-73 4-25 0-47-12-62" fill="none" stroke="currentColor" strokeWidth="1.5" opacity=".18" />
                        <rect x="48" y="10" width="194" height="42" rx="7" fill="var(--surface)" stroke="currentColor" opacity=".78" />
                        <circle cx="67" cy="31" r="9" fill="currentColor" opacity=".12" /><path d="M85 31h105" stroke="currentColor" strokeWidth="5" strokeLinecap="round" opacity=".12" /><path d="m225 27 4 4 7-8" fill="none" stroke="#76bd83" strokeWidth="3" strokeLinecap="round" />
                        <rect x="22" y="64" width="218" height="75" rx="8" fill="var(--surface)" stroke="currentColor" opacity=".86" />
                        <circle cx="43" cy="84" r="10" fill="currentColor" opacity=".12" /><path d="M63 84h133" stroke="currentColor" strokeWidth="5" strokeLinecap="round" opacity=".12" /><path d="m222 81 4 4 7-8" fill="none" stroke="#76bd83" strokeWidth="3" strokeLinecap="round" />
                        <circle cx="43" cy="111" r="10" fill="none" stroke="currentColor" opacity=".18" /><rect x="61" y="100" width="161" height="27" rx="4" fill="none" stroke="currentColor" opacity=".15" />
                        <rect x="55" y="153" width="191" height="40" rx="7" fill="var(--surface)" stroke="currentColor" opacity=".78" />
                        <circle cx="74" cy="173" r="9" fill="currentColor" opacity=".12" /><path d="M92 173h128" stroke="currentColor" strokeWidth="5" strokeLinecap="round" opacity=".12" />
                      </svg>
                      <h3>{posts.length ? "No published posts match these filters" : "Your posts will appear here"}</h3>
                      <p>Reply to your social media comments in one place. Connect a channel to start interacting with your audience.</p>
                      <div className="smx-community-channel-card">
                        <p>Connect one of your channels to get started</p>
                        <div className="smx-community-channel-grid">
                          {data.platforms.map((platform) => (
                            <button key={platform.key} type="button" onClick={() => { setConnectStep(platform); setConnectOpen(true) }}>
                              <span><BrandIcon k={platform.key} size={23} /></span>
                              <b>{platform.label}</b>
                              {!platform.configured && <small>Not configured</small>}
                            </button>
                          ))}
                        </div>
                      </div>
                      {posts.length > 0 && <button type="button" className="smx-link" onClick={() => { setCommunitySearch(""); setCommunityChannel("all") }}>Clear filters</button>}
                    </div>
                  )}
                </div>
              </div>
                </div>
              )}
              {commTab === "comments" && <details className="smx-community-assistant">
                <summary>AI reply assistant <small>Draft only · replies are not sent from Garad</small></summary>
                <div className="smx-community-assistant-body">
                  <div className="smx-community-assistant-copy"><b>Turn a comment into a response</b><p>Paste a real comment from your platform. Garad can draft a reply, but you’ll post it directly on that network.</p></div>
                  <label className="smx-label" htmlFor="smx-comment">Comment you received</label>
                  <textarea id="smx-comment" rows={3} value={comment} onChange={(e) => setComment(e.target.value)} placeholder="Paste the comment here…" />
                  <label className="smx-label" htmlFor="smx-ctx">Post context (optional)</label>
                  <input id="smx-ctx" value={postCtx} onChange={(e) => setPostCtx(e.target.value)} placeholder="What was your post about?" />
                  <div className="smx-mc-tools">
                    {REPLY_ACTIONS.map((a) => (
                      <button key={a.id} type="button" className="smx-chipbtn" disabled={replyBusy} onClick={() => void runReply(a.id)}>{a.label}</button>
                    ))}
                    <button type="button" className="smx-chipbtn" disabled={replyBusy} onClick={() => void runReply("reply_improve")}>Improve</button>
                    <input value={replyLang} onChange={(e) => setReplyLang(e.target.value)} placeholder="Language" aria-label="Language" />
                    <button type="button" className="smx-chipbtn" disabled={replyBusy} onClick={() => void runReply("translate")}>Translate</button>
                  </div>
                  {replyBusy && <p className="smx-hint">Writing…</p>}
                  {reply && (
                    <div className="smx-ai-out">
                      <label className="smx-label" htmlFor="smx-reply">Suggested reply (editable)</label>
                      <textarea id="smx-reply" rows={4} value={reply} onChange={(e) => setReply(e.target.value)} />
                      <div className="smx-mc-tools">
                        <button type="button" className="smx-primary" onClick={() => void navigator.clipboard.writeText(reply).then(() => flash("ok", "Reply copied. Paste it on the platform."))}>Copy reply</button>
                        <button type="button" className="smx-secondary" disabled={replyBusy} onClick={() => void runReply(replyAction.startsWith("reply") ? replyAction : "reply")}>Regenerate</button>
                      </div>
                    </div>
                  )}
                </div>
              </details>}
            </div>
          )}

          {section === "analytics" && (
            <div className="smx-insights">
              <header className="smx-insights-head">
                <div className="smx-insights-title"><span aria-hidden="true">▥</span><h2>Insights</h2></div>
                <div className="smx-insights-actions">
                  <button type="button" className="smx-insights-icon-button" aria-label="Insights update information" aria-expanded={analyticsUpdateOpen} onClick={() => setAnalyticsUpdateOpen((open) => !open)}>ⓘ</button>
                  <button type="button" className="smx-insights-icon-button" aria-label="Refresh insight data" onClick={() => void load()} disabled={loading}>↻</button>
                  <div className="smx-export-wrap">
                  <button type="button" className="smx-secondary smx-export-trigger" aria-expanded={analyticsExportOpen} onClick={() => setAnalyticsExportOpen((open) => !open)}>⇩ <span>Export</span>⌄</button>
                  {analyticsExportOpen && <div className="smx-export-menu" role="menu">
                    <button type="button" role="menuitem" onClick={() => downloadAnalytics("csv")}>Export as CSV</button>
                    <button type="button" role="menuitem" onClick={() => downloadAnalytics("markdown")}>Export as Markdown</button>
                    <button type="button" role="menuitem" onClick={() => downloadAnalytics("csv")}>Download metric snapshot</button>
                  </div>}
                  </div>
                  {analyticsUpdateOpen && <div className="smx-insights-update-popover">
                    <b>How Insights stay updated</b>
                    <p>Post metrics <span>{data.serverTime ? `Latest overview · ${fmt(data.serverTime)}` : "Latest saved snapshot"}</span></p>
                    <small>Metric availability depends on the connected platform. Use refresh to request the latest Garad overview.</small>
                  </div>}
                </div>
              </header>
              <div className="smx-insights-filters">
                <div className="smx-insights-ranges" role="group" aria-label="Date range">
                  {([
                    ["7", "7 Days"],
                    ["30", "30 Days"],
                    ["mtd", "Month to Date"],
                    ["last-month", "Last Month"],
                    ["custom", "Custom"],
                  ] as const).map(([value, label]) => (
                    <button key={value} type="button" className={analyticsRange === value ? "active" : ""} aria-pressed={analyticsRange === value} onClick={() => setAnalyticsRange(value)}>{label}</button>
                  ))}
                </div>
                {analyticsRange === "custom" && <div className="smx-insights-custom">
                  <label>From <input type="date" value={analyticsFrom} max={analyticsTo || undefined} onChange={(event) => setAnalyticsFrom(event.target.value)} /></label>
                  <label>To <input type="date" value={analyticsTo} min={analyticsFrom || undefined} onChange={(event) => setAnalyticsTo(event.target.value)} /></label>
                </div>}
                <label className="smx-insights-select"><span>⊞ Channels</span>
                  <select value={analyticsChannel} onChange={(event) => setAnalyticsChannel(event.target.value)}>
                    <option value="all">All channels</option>
                    {data.platforms.filter((platform) => publishedPosts.some((post) => post.platform === platform.key)).map((platform) => <option key={platform.key} value={platform.key}>{platform.label}</option>)}
                  </select>
                </label>
                <label className="smx-insights-select"><span>◇ Campaigns</span>
                  <select value={analyticsCampaign} onChange={(event) => setAnalyticsCampaign(event.target.value)}>
                    <option value="all">All campaigns</option>
                    {data.campaigns.map((campaign) => <option key={campaign.id} value={String(campaign.id)}>{campaign.name}</option>)}
                  </select>
                </label>
              </div>
              <p className="smx-insights-period">{analyticsDates.start.toLocaleDateString(undefined, { dateStyle: "medium" })} – {analyticsDates.end.toLocaleDateString(undefined, { dateStyle: "medium" })} · {insightPosts.length} published {insightPosts.length === 1 ? "post" : "posts"}</p>
              {(!connected.length || !insightMetrics.measured) && (
                <section className="smx-insights-hero">
                  <span className="smx-insights-hero-icon" aria-hidden="true">⌕</span>
                  <span className="smx-insights-hero-icon" aria-hidden="true">▥</span>
                  <span className="smx-insights-hero-icon" aria-hidden="true">◎</span>
                  <div>
                    <h3>{connected.length ? "Your insights will appear as metrics arrive" : "Turn your posts into insights"}</h3>
                    <p>{connected.length ? "There are no saved post metrics for this selection yet. Refresh or choose another date range." : "Understand your performance using real metrics from your connected social channels."}</p>
                    <button type="button" className="smx-insights-connect" onClick={() => setConnectOpen(true)}>＋ Connect a Channel</button>
                  </div>
                </section>
              )}
              <section className="smx-insights-weekly" aria-label="Weekly takeaways">
                <h3>WEEKLY TAKEAWAYS <span aria-hidden="true">⌃</span></h3>
                {recentWeekTopPost ? (
                  <div className="smx-insights-weekly-cards">
                    <article>
                      <b>Strong recent post · {platformOf(recentWeekTopPost.platform)?.label ?? recentWeekTopPost.platform}</b>
                      <p>{(recentWeekTopPost.content || "Published post").slice(0, 125)}{recentWeekTopPost.content.length > 125 ? "…" : ""}</p>
                      <small>{compact(postInteractions(recentWeekTopPost))} latest recorded interactions</small>
                      <button type="button" onClick={() => navigateCommunity("comments")}>View in Community</button>
                    </article>
                    <article>
                      <b>Recent publishing</b>
                      <p>{recentWeekPosts.length} {recentWeekPosts.length === 1 ? "post was" : "posts were"} published in the last 7 days.</p>
                      <small>Counts reflect each post’s latest saved metrics, not new activity during this week.</small>
                    </article>
                  </div>
                ) : <p>No posts were published in the last 7 days. Weekly insights will appear as your published content and platform metrics become available.</p>}
              </section>
              <section className="smx-insights-summary">
                <header><h3>Summary</h3><small>Latest saved snapshots · {insightMetrics.measured} posts with metrics</small></header>
                <div className="smx-insights-metrics" aria-label="Selected period metrics">
                  <div className="smx-insight-stat"><small>Likes</small><strong>{insightMetrics.hasLikes ? compact(insightMetrics.likes) : "—"}</strong><span>{insightMetrics.hasLikes ? "Recorded likes" : "No metric returned"}</span></div>
                  <div className="smx-insight-stat"><small>Comments</small><strong>{insightMetrics.hasComments ? compact(insightMetrics.comments) : "—"}</strong><span>{insightMetrics.hasComments ? "Count only; thread unavailable" : "No metric returned"}</span></div>
                  <div className="smx-insight-stat"><small>Impressions / Views</small><strong>{insightMetrics.hasViews ? compact(insightMetrics.views) : "—"}</strong><span>Where provided by platform</span></div>
                  <div className="smx-insight-stat"><small>Engagement rate</small><strong>{insightMetrics.engagementRate === null ? "—" : `${insightMetrics.engagementRate.toFixed(2)}%`}</strong><span>Interactions ÷ views</span></div>
                  <div className="smx-insight-stat"><small>Shares</small><strong>{insightMetrics.hasShares ? compact(insightMetrics.shares) : "—"}</strong><span>Latest saved post metrics</span></div>
                  <div className="smx-insight-stat"><small>Published posts</small><strong>{insightPosts.length}</strong><span>In selected period</span></div>
                </div>
              </section>
              <section className="smx-insights-top-section">
                <header className="smx-insights-block-head">
                  <div><h3>Top 5 Posts</h3><p>{analyticsDates.start.toLocaleDateString(undefined, { dateStyle: "medium" })} – {analyticsDates.end.toLocaleDateString(undefined, { dateStyle: "medium" })}</p></div>
                  <div className="smx-insights-ranking" role="group" aria-label="Rank top posts by">
                    <button type="button" className={analyticsPostMetric === "reactions" ? "active" : ""} aria-pressed={analyticsPostMetric === "reactions"} onClick={() => setAnalyticsPostMetric("reactions")}>Reactions</button>
                    <button type="button" className={analyticsPostMetric === "comments" ? "active" : ""} aria-pressed={analyticsPostMetric === "comments"} onClick={() => setAnalyticsPostMetric("comments")}>Comments</button>
                  </div>
                </header>
                {rankedTopPosts.length ? <div className="smx-insights-top-posts">
                  {rankedTopPosts.map((post, index) => <article key={post.id}>
                    <div className="smx-insights-top-post-rank">#{index + 1}<span>{analyticsPostMetric === "comments" ? metricLabel(post, "comment_count", compact) : compact(metricNumber(post, "like_count") + metricNumber(post, "share_count"))} {analyticsPostMetric === "comments" ? "Comments" : "Reactions"}</span></div>
                    <div className="smx-insights-top-post-body">
                      <b>{platformOf(post.platform)?.label ?? post.platform} · {post.postedAt ? new Date(post.postedAt).toLocaleDateString() : "Published"}</b>
                      <p>{post.content || "Published post"}</p>
                      <small>{metricLabel(post, "like_count", compact)} likes · {metricLabel(post, "comment_count", compact)} comments · {metricLabel(post, "share_count", compact)} shares</small>
                    </div>
                    {post.images[0] ? <img src={post.images[0]} alt="" loading="lazy" /> : <span className="smx-insights-top-post-image" aria-hidden="true">▧</span>}
                    <div className="smx-insights-top-post-actions">
                      {post.link && <a href={post.link} target="_blank" rel="noopener noreferrer" aria-label="Open published post">↗</a>}
                      <button type="button" aria-label="Open Community comments" onClick={() => navigateCommunity("comments")}>▤</button>
                    </div>
                  </article>)}
                </div> : <p className="smx-insights-empty">Top posts will appear when Garad has complete like, comment and share metrics for published posts.</p>}
              </section>
              <section className="smx-insights-panel">
                <div className="smx-insights-section-title"><div><h3>Performance</h3><p>Latest saved interactions grouped by post publication date; not a daily history.</p></div></div>
                {insightTimeline.some((day) => day.value > 0) ? (
                  <div className="smx-insights-chart" role="img" aria-label="Latest saved interactions grouped by published date">
                    {insightTimeline.map((day) => <div className="smx-chart-column" key={dateKey(day.date)} title={`${day.label}: ${compact(day.value)} interactions`}>
                      <span style={{ height: `${Math.max(4, (day.value / Math.max(...insightTimeline.map((item) => item.value))) * 100)}%` }} />
                      <small>{day.label}</small>
                    </div>)}
                  </div>
                ) : <p className="smx-insights-empty">No interaction metrics to chart for this period.</p>}
              </section>
              <p className="smx-insights-footnote">Metrics reflect the latest snapshots Garad has saved for each published post. Availability and metric definitions vary by social network; follower totals are account-level and are not included in this period report.</p>
            </div>
          )}

          {section === "accounts" && (
            <div className="smx-card">
              <div className="smx-row smx-between">
                <h3>Connected accounts</h3>
                <button type="button" className="smx-primary" onClick={() => setConnectOpen(true)}>Connect account</button>
              </div>
              {data.accounts.length ? (
                <div className="smx-acct-cards">
                  {data.accounts.map((a) => {
                    const p = platformOf(a.platform)
                    return (
                      <div key={a.id} className="smx-acct-card">
                        <div className="smx-acct-head">
                          {a.picture ? <img src={a.picture} alt="" /> : <i>{(a.name ?? p?.label ?? "?")[0]}</i>}
                          <div>
                            <b>{a.name ?? p?.label}</b>
                            <small>
                              {p?.label ?? a.platform}
                              {a.accountType === "organization" ? " · Company Page" : a.accountType === "member" ? " · Personal profile" : a.type ? ` · ${a.type}` : ""}
                              {a.role ? ` · ${a.role.replace(/_/g, " ")}` : ""}
                            </small>
                          </div>
                          <span className={`smx-badge ${a.status === "connected" ? "published" : "failed"}`}>{a.status === "connected" ? "Connected" : "Expired"}</span>
                        </div>
                        <p className="smx-hint">
                          Connected {fmt(a.connectedAt)}
                          {a.followers !== null ? ` · ${a.followers} followers` : ""}
                          <br />
                          {a.status === "connected" ? `Valid until ${fmt(a.expiresAt)}` : "Connection expired. Reconnect to keep publishing."}
                          {a.platform === "linkedin" && a.canPublish === false ? <><br />{a.accountType === "organization" ? "Page connected, but this LinkedIn account/app does not have permission to publish to this organization." : "Personal posting is unavailable until w_member_social is approved and granted."}</> : null}
                        </p>
                        <div className="smx-actions">
                          {a.status !== "connected" && p && (
                            <button type="button" disabled={!p.configured || connecting === p.key} onClick={() => connect(p)}>{p.configured ? "Reconnect" : "Not configured"}</button>
                          )}
                          <button type="button" className="danger" onClick={() => disconnect(a)}>Disconnect</button>
                        </div>
                      </div>
                    )
                  })}
                </div>
              ) : (
                <p className="smx-empty">No accounts connected yet.</p>
              )}
            </div>
          )}

          {section === "settings" && (
            <>
              <div className="smx-card">
                <h3>Campaigns</h3>
                {data.campaigns.length ? (
                  <ul className="smx-list">
                    {data.campaigns.map((c) => (
                      <li key={c.id} className="smx-post">
                        <div className="smx-post-top"><strong>{c.name}</strong><small>{c.posts} posts</small></div>
                        <p>{c.audience}</p>
                        <div className="smx-actions">
                          <button type="button" className="danger" onClick={() => { if (window.confirm("Delete this campaign?")) void act(() => api.socialRequest("DELETE", `/campaigns/${c.id}`), "Campaign deleted.") }}>Delete</button>
                        </div>
                      </li>
                    ))}
                  </ul>
                ) : (
                  <p className="smx-empty">No campaigns yet.</p>
                )}
                <div className="smx-row">
                  <input value={campName} onChange={(e) => setCampName(e.target.value)} placeholder="Campaign name" />
                  <input value={campAudience} onChange={(e) => setCampAudience(e.target.value)} placeholder="Target audience" />
                  <button type="button" className="smx-secondary" onClick={() => void addCampaign()}>Add campaign</button>
                </div>
              </div>
              <div className="smx-card">
                <h3>Brand voice</h3>
                {data.companies.length ? (
                  <ul className="smx-list">
                    {data.companies.map((c) => (
                      <li key={c.id} className="smx-post">
                        <div className="smx-post-top"><strong>{c.name}</strong><small>{c.industry ?? ""}</small></div>
                        <p>{c.description || c.tagline || "No description."}</p>
                      </li>
                    ))}
                  </ul>
                ) : (
                  <p className="smx-empty">No brands found on your account. Brands you add in Garad are used to personalise AI copy.</p>
                )}
              </div>
              <p className="smx-hint">{data.workerNote}</p>
            </>
          )}
        </div>
      )}

      {connectOpen && data && (
        <div className="smx-overlay" role="presentation" onMouseDown={(e) => { if (e.target === e.currentTarget) closeConnect() }}>
          <div className="smx-modal smx-modal-sm smx-cm" role="dialog" aria-modal="true" aria-label="Connect a new channel">
            <header className="smx-cm-h">
              {connectStep ? <button type="button" className="smx-cm-x" onClick={() => setConnectStep(null)} aria-label="Back">←</button> : <span />}
              <h2>{connectStep ? `Connect ${connectStep.label}` : "Connect a New Channel"}</h2>
              <button type="button" className="smx-cm-x" onClick={closeConnect} aria-label="Close">×</button>
            </header>
            <div className="smx-cm-b">
              {!connectStep && (
                <div className="smx-cm-grid">
                  {[...data.platforms].sort((a, b) => Number(b.configured) - Number(a.configured)).map((p) => (
                    <button key={p.key} type="button" className="smx-cm-tile" onClick={() => setConnectStep(p)}>
                      <BrandIcon k={p.key} />
                      <b>{p.label}</b>
                      <span>{CONNECT_SUB[p.key] ?? "Account"}</span>
                      {!p.configured && <em className="smx-cm-tag">Not configured</em>}
                    </button>
                  ))}
                </div>
              )}

              {connectStep && (
                <div className="smx-cm-step">
                  <div className="smx-cm-logos"><BrandIcon k={connectStep.key} size={44} /></div>
                  {!connectStep.configured ? (
                    <>
                      <h3>{connectStep.label} isn't set up yet</h3>
                      <p>The platform administrator needs to add the {connectStep.label} app credentials before accounts can be connected.</p>
                    </>
                  ) : (
                    <>
                      <h3>{CONNECT_TITLE[connectStep.key] ?? `Connect your ${connectStep.label} account`}</h3>
                      <div className="smx-cm-opts">
                        <div className="smx-cm-opt">
                          <b>{CONNECT_OPT[connectStep.key]?.main ?? "Account"}</b>
                          <p>{CONNECT_OPT[connectStep.key]?.mainText ?? "Publish and schedule posts automatically."}</p>
                          <button type="button" className="smx-primary" disabled={connecting !== ""} onClick={() => connect(connectStep)}>
                            {connecting === connectStep.key ? "Waiting for sign-in…" : `Continue to ${connectStep.label}`}
                          </button>
                        </div>
                        {CONNECT_OPT[connectStep.key]?.other && (
                          <div className="smx-cm-opt off">
                            <b>{CONNECT_OPT[connectStep.key]?.other}</b>
                            <p>{CONNECT_OPT[connectStep.key]?.otherText}</p>
                            <span className="smx-badge pending">Not supported</span>
                          </div>
                        )}
                      </div>
                      <p className="smx-hint">A secure sign-in window opens on {connectStep.label}'s own site. This page updates automatically once you finish.</p>
                    </>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>
      )}
      {composer && data && (
        <SocialComposer
          api={api}
          data={data}
          editing={composer.editing}
          initialDate={composer.date}
          initialText={composer.text}
          onClose={() => setComposer(null)}
          onChanged={(text, tone) => {
            flash(tone, text)
            void load(true)
          }}
          onConnect={() => {
            setComposer(null)
            setConnectOpen(true)
          }}
        />
      )}
    </section>
  )
}
