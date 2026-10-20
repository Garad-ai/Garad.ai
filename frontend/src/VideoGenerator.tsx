import { useCallback, useEffect, useMemo, useRef, useState } from "react"

import type {
  ApiClient,
  VideoInputSchema,
  VideoJob,
  VideoMode,
  VideoModelDescriptor,
} from "./core/api"

type CreativeTool = "image" | "video" | "audio" | "social"

type Props = {
  api: ApiClient

  isAuthenticated: boolean

  onRequireLogin: () => void

  onBackToChat: () => void

  onNavigateTool: (tool: CreativeTool) => void

  onUpgrade?: () => void
}

type Values = Record<string, string | boolean>

type VideoTemplate = {
  id: string

  name: string

  group: "Featured" | "Branding" | "Creative"

  prompt: string

  aspect: string

  seconds: number

  preview: string

  video?: string
}

type SavedTemplate = {
  id: string

  name: string

  modelId: string

  modelName: string

  prompt: string

  values: Values

  preview: string

  createdAt: number
}

type IconName = "image" | "video" | "share" | "chevronDown" | "chevronLeft" | "chevronRight" | "plus" | "x" | "download" | "external" | "refresh" | "trash" | "search" | "settings" | "templates" | "sparkle" | "check" | "grid" | "alert" | "lock" | "play" | "star"

const iconPaths: Record<IconName, string> = {
  image:
    "M5 4h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1zm0 12 4.5-5 3.5 4 2.5-2.5L20 17M15.5 9.5h.01",

  video: "M4 6h10a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4zM16 10.5 21 8v8l-5-2.5",

  share:
    "M18 8a3 3 0 1 0-2.8-4M6 15a3 3 0 1 0 0 .01M18 21a3 3 0 1 0 0-.01M8.6 13.5l6.8 4M15.4 6.5l-6.8 4",

  chevronDown: "m6 9 6 6 6-6",

  chevronLeft: "m15 6-6 6 6 6",

  chevronRight: "m9 6 6 6-6 6",

  plus: "M12 5v14M5 12h14",

  x: "M6 6l12 12M18 6 6 18",

  download: "M12 4v11m0 0 4-4m-4 4-4-4M5 20h14",

  external:
    "M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5",

  refresh: "M20 11a8 8 0 1 0-2.3 5.7M20 4v7h-7",

  trash: "M5 7h14M10 7V4h4v3M7 7l1 13h8l1-13M10 11v6M14 11v6",

  search: "M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14zm9 3-4.5-4.5",

  settings: "M4 7h10M18 7h2M4 17h2M10 17h10M14 4v6M6 14v6",

  templates: "M5 4h9l5 5v11H5zM14 4v5h5M8 13h8M8 17h6",

  sparkle:
    "M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8zM18 16l.8 2.2L21 19l-2.2.8L18 22l-.8-2.2L15 19l2.2-.8z",

  check: "m5 12.5 4.5 4.5L19 7.5",

  grid: "M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z",

  alert:
    "M12 8v5m0 3.5h.01M10.3 4.2 2.8 17.5A2 2 0 0 0 4.5 20.5h15a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0z",

  lock: "M7 11V8a5 5 0 0 1 10 0v3M6 11h12v9H6z",

  play: "M8 5v14l11-7z",

  star: "M12 3l2.4 5.2 5.6.7-4.1 3.9 1 5.6-4.9-2.7-4.9 2.7 1-5.6L4 8.9l5.6-.7z",
}

type IconProps = {
  name: IconName

  size?: number
}

function Ico({ name, size = 16 }: IconProps) {
  return (
    <svg
      aria-hidden="true"
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.8"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <path d={iconPaths[name]} />
    </svg>
  )
}

const videoModules = import.meta.glob<string>(
  "./assets/templates/video/*.mp4",

  {
    eager: true,

    import: "default",

    query: "?url",
  },
)

const posterModules = import.meta.glob<string>(
  "./assets/templates/video/*.jpg",

  {
    eager: true,

    import: "default",

    query: "?url",
  },
)

const tplVideo = (id: string): string =>
  videoModules[`./assets/templates/video/${id}.mp4`] || ""

const tplPoster = (id: string): string =>
  posterModules[`./assets/templates/video/${id}.jpg`] || ""

const tpl = (
  id: string,

  name: string,

  group: VideoTemplate["group"],

  aspect: string,

  seconds: number,

  prompt: string,
): VideoTemplate => ({
  id,

  name,

  group,

  prompt,

  aspect,

  seconds,

  preview: tplPoster(id),

  video: tplVideo(id),
})

const templates: VideoTemplate[] = [
  tpl(
    "cityscape",

    "Cinematic city",

    "Featured",

    "16:9",

    8,

    "A cinematic aerial shot of a coastal city at golden hour, slow forward drone movement over rooftops and streets, warm sunset light, realistic detail, film grain.",
  ),

  tpl(
    "product-demo",

    "Product demo",

    "Featured",

    "16:9",

    6,

    "A sleek futuristic software interface floating in a dark space with glowing blue edges, slow camera push-in on the screen, text typing in real time, premium product launch look.",
  ),

  tpl(
    "fashion",

    "Fashion editorial",

    "Featured",

    "9:16",

    6,

    "A woman in an elegant black abaya standing in a bright boutique, smiling and gesturing gently toward the camera, soft warm lighting, smooth handheld motion, editorial style.",
  ),

  tpl(
    "title-intro",

    "Epic title intro",

    "Featured",

    "9:16",

    6,

    "A glowing holographic globe of connected data points rotating in a dark space, bold title text appearing over it with a loading bar, cinematic tech intro.",
  ),

  tpl(
    "brand-screen",

    "Brand story",

    "Branding",

    "16:9",

    8,

    "A large LED stage screen showing a bright brand logo over a blue network pattern, colorful stage lights, slow camera glide, confident launch-event atmosphere.",
  ),

  tpl(
    "field-engineer",

    "On-site engineer",

    "Branding",

    "16:9",

    8,

    "A telecom engineer in an orange safety vest and helmet walking in front of a communications tower at sunset, holding a tablet, warm light, natural handheld motion.",
  ),

  tpl(
    "live-event",

    "Live event",

    "Branding",

    "16:9",

    8,

    "An audience in a dim conference hall applauding warmly, close shot of smiling professionals clapping, soft bokeh stage lights, authentic reaction, cinematic color.",
  ),

  tpl(
    "news-report",

    "Street reporter",

    "Creative",

    "16:9",

    8,

    "A young reporter holding a microphone speaking to the camera on a busy street at golden hour, cars and passers-by behind him, natural documentary style.",
  ),

  tpl(
    "construction",

    "Construction team",

    "Creative",

    "16:9",

    8,

    "Three construction professionals reviewing a large blueprint on a building site in warm afternoon light, engaged conversation, steady camera with gentle movement.",
  ),

  tpl(
    "campus",

    "Campus life",

    "Creative",

    "9:16",

    6,

    "Two young women in colorful hijabs walking and laughing outside a modern building while carrying books, golden hour light, smooth tracking shot.",
  ),
]

const HISTORY_POLL_MS = 3000

const formatElapsed = (createdAt: string | null, now: number) => {
  const start = createdAt ? Date.parse(createdAt) : NaN

  const seconds = Number.isFinite(start)
    ? Math.max(0, Math.floor((now - start) / 1000))
    : 0

  const m = Math.floor(seconds / 60)

  return m > 0
    ? `${m}m ${String(seconds % 60).padStart(2, "0")}s`
    : `${seconds}s`
}

const TEMPLATES_KEY = "garad.videoStudio.templates.v1"

const MODEL_KEY = "garad.videoStudio.model.v1"

const loadSavedTemplates = (): SavedTemplate[] => {
  try {
    const raw = JSON.parse(window.localStorage.getItem(TEMPLATES_KEY) || "[]")

    return Array.isArray(raw)
      ? raw.filter((item) => item && typeof item.id === "string")
      : []
  } catch {
    return []
  }
}

const persistTemplates = (items: SavedTemplate[]): boolean => {
  try {
    window.localStorage.setItem(TEMPLATES_KEY, JSON.stringify(items))

    return true
  } catch {
    return false
  }
}

const downscale = (file: File): Promise<string> =>
  new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file)

    const img = new Image()

    img.onload = () => {
      const scale = Math.min(1, 480 / Math.max(img.width, img.height))

      const canvas = document.createElement("canvas")

      canvas.width = Math.round(img.width * scale)

      canvas.height = Math.round(img.height * scale)

      canvas.getContext("2d")?.drawImage(img, 0, 0, canvas.width, canvas.height)

      URL.revokeObjectURL(url)

      resolve(canvas.toDataURL("image/jpeg", 0.8))
    }

    img.onerror = () => {
      URL.revokeObjectURL(url)

      reject(new Error("Could not read that image."))
    }

    img.src = url
  })

const modeLabel: Record<VideoMode, string> = {
  text_to_video: "Text",

  image_to_video: "Image",

  video_to_video: "Video",
}

const humanize = (value: string) =>
  value

    .replace(/\[\]$/, "")

    .replace(/_/g, " ")

    .replace(/^\w/, (char) => char.toUpperCase())

type VideoOption = {
  value: string | number

  label: string
}

const optionLabel = (option: VideoOption) =>
  option.label || String(option.value)

const isFileInput = (input: VideoInputSchema) => input.type === "file"

const fileKind = (input: VideoInputSchema): "image" | "video" | "audio" => {
  const accept = input.accept || ""

  if (accept.includes("video")) return "video"

  if (accept.includes("audio")) return "audio"

  return "image"
}

const PRIMARY = /(duration|seconds|aspect|resolution|size)/i

const formatTime = (iso: string | null) => {
  if (!iso) return ""

  const date = new Date(iso)

  return Number.isNaN(date.getTime())
    ? ""
    : date.toLocaleString(undefined, {
        dateStyle: "medium",

        timeStyle: "short",
      })
}

const modelShort = (job: VideoJob, models: VideoModelDescriptor[]) =>
  models

    .find((m) => m.id === job.model || m.feature === job.model)

    ?.displayName.replace(/\s*\(.*\)\s*$/, "") || job.model

export default function VideoGenerator({
  api,

  isAuthenticated,

  onRequireLogin,

  onBackToChat,

  onNavigateTool,

  onUpgrade,
}: Props) {
  const [models, setModels] = useState<VideoModelDescriptor[]>([])

  const [modelsLoading, setModelsLoading] = useState(true)

  const [modelsError, setModelsError] = useState("")

  const [modelId, setModelId] = useState("")

  const [modelOpen, setModelOpen] = useState(false)

  const [modelSearch, setModelSearch] = useState("")

  const [modeFilter, setModeFilter] = useState<VideoMode | "all">("all")

  const [values, setValues] = useState<Values>({})

  const [files, setFiles] = useState<Record<string, File[]>>({})

  const [previews, setPreviews] = useState<Record<string, string[]>>({})

  const [prompt, setPrompt] = useState("")

  const [settingsOpen, setSettingsOpen] = useState(false)

  const [formError, setFormError] = useState("")

  const [submitting, setSubmitting] = useState(false)

  const [jobs, setJobs] = useState<VideoJob[]>([])

  const [jobsLoading, setJobsLoading] = useState(true)

  const [view, setView] = useState<"creations" | "templates" | "mine">(
    "creations",
  )

  const [saved, setSaved] = useState<SavedTemplate[]>(loadSavedTemplates)

  const [saveOpen, setSaveOpen] = useState(false)

  const [saveModelId, setSaveModelId] = useState("")

  const [saveValues, setSaveValues] = useState<Values>({})

  const [saveName, setSaveName] = useState("")

  const [savePrompt, setSavePrompt] = useState("")

  const [savePreview, setSavePreview] = useState("")

  const [saveError, setSaveError] = useState("")

  const [lightbox, setLightbox] = useState<number | null>(null)

  const [selectedTemplate, setSelectedTemplate] = useState("")

  const [dragKey, setDragKey] = useState("")

  const modelBoxRef = useRef<HTMLDivElement>(null)

  const promptRef = useRef<HTMLTextAreaElement>(null)

  const saveFileRef = useRef<HTMLInputElement>(null)

  const canvasRef = useRef<HTMLDivElement>(null)

  const fileRefs = useRef<Record<string, HTMLInputElement | null>>({})

  const selected = models.find((m) => m.id === modelId) || null

  const promptInput = selected?.inputs.find((i) => i.name === "prompt")

  const fileInputs = useMemo(
    () => selected?.inputs.filter(isFileInput) || [],

    [selected],
  )

  const settingInputs = useMemo(
    () =>
      selected?.inputs.filter((i) => !isFileInput(i) && i.name !== "prompt") ||
      [],

    [selected],
  )

  const primaryInputs = settingInputs.filter(
    (i) => !i.advanced && i.type === "select" && PRIMARY.test(i.name),
  )

  const secondaryInputs = settingInputs.filter(
    (i) => !primaryInputs.includes(i),
  )

  const saveModel = models.find((m) => m.id === saveModelId) || null

  const saveFileInputs = (saveModel?.inputs || []).filter(isFileInput)

  const saveSettings = (saveModel?.inputs || []).filter(
    (i) =>
      !isFileInput(i) &&
      i.name !== "prompt" &&
      !i.advanced &&
      (i.type === "checkbox" || (i.type === "select" && PRIMARY.test(i.name))),
  )

  const audioInput = settingInputs.find((i) => i.name === "generate_audio")

  const [now, setNow] = useState(() => Date.now())

  const activeJobs = jobs.filter(
    (job) => job.state === "queued" || job.state === "processing",
  )

  const defaultsFor = useCallback((model: VideoModelDescriptor): Values => {
    const next: Values = {}

    model.inputs.forEach((input) => {
      if (isFileInput(input) || input.name === "prompt") return

      if (input.type === "checkbox") {
        next[input.name] = Boolean(input.default)
      } else if (input.default !== undefined && input.default !== null) {
        next[input.name] = String(input.default)
      } else if (input.type === "select" && input.options?.length) {
        next[input.name] = String(input.options[0].value)
      } else {
        next[input.name] = ""
      }
    })

    return next
  }, [])

  const clearFiles = useCallback(() => {
    setFiles({})

    setPreviews((current) => {
      Object.values(current)

        .flat()

        .forEach((url) => URL.revokeObjectURL(url))

      return {}
    })
  }, [])

  const chooseModel = useCallback(
    (model: VideoModelDescriptor) => {
      setModelId(model.id)

      setValues(defaultsFor(model))

      clearFiles()

      setFormError("")

      try {
        window.localStorage.setItem(MODEL_KEY, model.id)
      } catch {
        /* ignore */
      }
    },

    [clearFiles, defaultsFor],
  )

  useEffect(() => {
    let alive = true

    api

      .getVideoModels()

      .then((rows) => {
        if (!alive) return

        setModels(rows)

        let remembered = ""

        try {
          remembered = window.localStorage.getItem(MODEL_KEY) || ""
        } catch {
          /* ignore */
        }

        const usable = rows.filter(
          (m) => m.availability === "available" && !m.locked,
        )

        const first =
          usable.find((m) => m.id === remembered) ||
          usable.find(
            (m) => m.id === "bytedance/seedance-2.0/fast/text-to-video",
          ) ||
          usable.find((m) => m.modes.includes("text_to_video")) ||
          usable[0]

        if (first) {
          setModelId(first.id)

          setValues(defaultsFor(first))
        }
      })

      .catch((error: unknown) => {
        if (alive)
          setModelsError(
            error instanceof Error ? error.message : "Models unavailable.",
          )
      })

      .finally(() => alive && setModelsLoading(false))

    return () => {
      alive = false
    }
  }, [api, defaultsFor])

  const refreshHistory = useCallback(async () => {
    if (!isAuthenticated) {
      setJobs([])

      setJobsLoading(false)

      return
    }

    try {
      const rows = await api.getVideoHistory()

      setJobs(rows)
    } catch {
      /* keep the last known list */
    } finally {
      setJobsLoading(false)
    }
  }, [api, isAuthenticated])

  useEffect(() => {
    void refreshHistory()
  }, [refreshHistory])

  // Poll real provider status for jobs that are still running.

  const pollingIds = activeJobs.map((job) => job.id).join(",")

  useEffect(() => {
    if (!pollingIds) return

    const tick = window.setInterval(() => setNow(Date.now()), 1000)

    return () => window.clearInterval(tick)
  }, [pollingIds])

  useEffect(() => {
    if (!pollingIds) return

    let busy = false

    const timer = window.setInterval(async () => {
      if (busy) return

      busy = true

      try {
        const ids = pollingIds.split(",").map(Number)

        const updates = await Promise.all(
          ids.map((id) => api.getVideoJob(id).catch(() => null)),
        )

        setJobs((current) =>
          current.map(
            (job) => updates.find((u) => u && u.id === job.id) || job,
          ),
        )
      } finally {
        busy = false
      }
    }, HISTORY_POLL_MS)

    return () => window.clearInterval(timer)
  }, [api, pollingIds])

  useEffect(() => {
    if (!modelOpen) return

    const close = (event: MouseEvent) => {
      if (!modelBoxRef.current?.contains(event.target as Node)) {
        setModelOpen(false)
      }
    }

    document.addEventListener("mousedown", close)

    return () => document.removeEventListener("mousedown", close)
  }, [modelOpen])

  useEffect(() => {
    if (lightbox === null) return

    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") setLightbox(null)
    }

    window.addEventListener("keydown", onKey)

    return () => window.removeEventListener("keydown", onKey)
  }, [lightbox])

  const groupedModels = useMemo(() => {
    const query = modelSearch.trim().toLowerCase()

    const groups = new Map<string, VideoModelDescriptor[]>()

    models

      .filter(
        (m) =>
          (modeFilter === "all" || m.modes.includes(modeFilter)) &&
          (!query ||
            `${m.displayName} ${m.family}`.toLowerCase().includes(query)),
      )

      .forEach((m) => {
        groups.set(m.family, [...(groups.get(m.family) || []), m])
      })

    return [...groups.entries()]
  }, [models, modeFilter, modelSearch])

  const addFiles = (
    input: VideoInputSchema,

    list: FileList | File[] | null,
  ) => {
    const incoming = Array.from(list || [])

    if (!incoming.length) return

    const kind = fileKind(input)

    const valid = incoming.filter((file) => file.type.startsWith(`${kind}/`))

    if (valid.length !== incoming.length) {
      setFormError(
        `${humanize(input.label || input.name)} only accepts ${kind} files.`,
      )
    } else {
      setFormError("")
    }

    if (!valid.length) return

    const max = input.multiple ? input.max_files || 8 : 1

    setFiles((current) => {
      const existing = input.multiple ? current[input.name] || [] : []

      return { ...current, [input.name]: [...existing, ...valid].slice(0, max) }
    })

    setPreviews((current) => {
      const existing = input.multiple ? current[input.name] || [] : []

      if (!input.multiple) existing.forEach((url) => URL.revokeObjectURL(url))

      return {
        ...current,

        [input.name]: [
          ...existing,

          ...valid.map((file) => URL.createObjectURL(file)),
        ].slice(0, max),
      }
    })
  }

  const removeFile = (input: VideoInputSchema, index: number) => {
    setFiles((current) => ({
      ...current,

      [input.name]: (current[input.name] || []).filter((_, i) => i !== index),
    }))

    setPreviews((current) => {
      const list = current[input.name] || []

      if (list[index]) URL.revokeObjectURL(list[index])

      return { ...current, [input.name]: list.filter((_, i) => i !== index) }
    })
  }

  const durationSeconds = (): number | null => {
    const entry = Object.entries(values).find(([name]) =>
      /(duration|seconds)/i.test(name),
    )

    const parsed = entry ? parseInt(String(entry[1]), 10) : NaN

    return Number.isFinite(parsed) ? parsed : null
  }

  const validate = (): string => {
    if (!selected) return "Choose a video model first."

    if (selected.locked) {
      onUpgrade?.()

      return "This video model requires an upgraded plan."
    }

    if (selected.availability !== "available")
      return "This model isn’t available right now."

    if (promptInput?.required && !prompt.trim())
      return "Describe the video you want to create."

    for (const input of fileInputs) {
      if (input.required && !(files[input.name] || []).length) {
        return `${humanize(input.label || input.name)} is required for this model.`
      }

      const min = input.min_files || 0

      if (
        (files[input.name] || []).length &&
        (files[input.name] || []).length < min
      ) {
        return `${humanize(input.label || input.name)} needs at least ${min} files.`
      }
    }

    return ""
  }

  const submit = async () => {
    if (!isAuthenticated) {
      onRequireLogin()

      return
    }

    if (submitting) return

    const problem = validate()

    if (problem) {
      setFormError(problem)

      return
    }

    if (!selected) return

    const form = new FormData()

    form.set("action", selected.action)

    form.set("sub_model_value", selected.subModel)

    form.set("feature", selected.feature)

    if (promptInput) form.set("prompt", prompt.trim())

    settingInputs.forEach((input) => {
      const value = values[input.name]

      if (input.type === "checkbox") {
        if (value) form.set(input.name, "1")
      } else if (value !== undefined && String(value).trim() !== "") {
        form.set(input.name, String(value))
      }
    })

    fileInputs.forEach((input) => {
      ;(files[input.name] || []).forEach((file) =>
        form.append(input.name, file, file.name),
      )
    })

    setSubmitting(true)

    setFormError("")

    try {
      const { videoId } = await api.createVideo(form)

      setView("creations")

      const status = await api.getVideoJob(videoId).catch(() => null)

      if (status) {
        setJobs((current) => [
          status,

          ...current.filter((job) => job.id !== status.id),
        ])
      } else {
        await refreshHistory()
      }

      scrollTop()
    } catch (error) {
      setFormError(
        error instanceof Error ? error.message : "Video request failed.",
      )
    } finally {
      setSubmitting(false)
    }
  }

  const scrollTop = () =>
    canvasRef.current?.scrollIntoView({ behavior: "smooth", block: "start" })

  const applyValues = (next: Values) =>
    setValues((current) => ({ ...current, ...next }))

  const applyTemplate = (template: VideoTemplate) => {
    setPrompt(template.prompt)

    setSelectedTemplate(template.id)

    const next: Values = {}

    settingInputs.forEach((input) => {
      if (input.type !== "select" || !input.options) return

      if (/aspect/i.test(input.name)) {
        const match = input.options.find(
          (o) => String(o.value) === template.aspect,
        )

        if (match) next[input.name] = String(match.value)
      } else if (/(duration|seconds)/i.test(input.name)) {
        const match = input.options.find(
          (o) => parseInt(String(o.value), 10) === template.seconds,
        )

        if (match) next[input.name] = String(match.value)
      }
    })

    applyValues(next)

    setFormError("")

    window.setTimeout(() => promptRef.current?.focus(), 0)
  }

  const applySaved = (template: SavedTemplate) => {
    const model = models.find(
      (m) =>
        m.id === template.modelId &&
        m.availability === "available" &&
        !m.locked,
    )

    if (model) {
      setModelId(model.id)

      setValues({ ...defaultsFor(model), ...template.values })

      clearFiles()
    }

    setPrompt(template.prompt)

    setSelectedTemplate(template.id)

    setFormError("")

    window.setTimeout(() => promptRef.current?.focus(), 0)
  }

  const openSave = () => {
    setSaveModelId(selected?.id || "")

    setSaveValues({ ...values })

    setSaveName("")

    setSavePrompt(prompt)

    setSavePreview("")

    setSaveError("")

    setSaveOpen(true)
  }

  const submitSave = () => {
    if (!saveName.trim()) return setSaveError("Give the template a name.")

    if (!savePrompt.trim())
      return setSaveError("Add a prompt for the template.")

    const pickedModel = models.find((m) => m.id === saveModelId)

    if (!pickedModel) return setSaveError("Choose a model first.")

    const next: SavedTemplate[] = [
      {
        id: `tpl-${Date.now()}`,

        name: saveName.trim(),

        modelId: pickedModel.id,

        modelName: pickedModel.displayName.replace(/\s*\(.*\)\s*$/, ""),

        prompt: savePrompt.trim(),

        values: saveValues,

        preview: savePreview,

        createdAt: Date.now(),
      },

      ...saved,
    ]

    if (!persistTemplates(next)) {
      return setSaveError(
        "Browser storage is full. Remove a template or use a smaller preview.",
      )
    }

    setSaved(next)

    setSaveOpen(false)

    setView("mine")
  }

  const deleteSaved = (id: string) => {
    const next = saved.filter((item) => item.id !== id)

    persistTemplates(next)

    setSaved(next)
  }

  const removeJob = async (job: VideoJob) => {
    if (!window.confirm("Delete this video permanently?")) return

    try {
      await api.deleteVideos([job.id])

      setJobs((current) => current.filter((item) => item.id !== job.id))

      setLightbox((current) => (current === job.id ? null : current))
    } catch (error) {
      setFormError(
        error instanceof Error ? error.message : "Could not delete the video.",
      )
    }
  }

  const clearAll = async () => {
    if (!jobs.length || !window.confirm("Delete all your videos permanently?"))
      return

    try {
      await api.deleteVideos(jobs.map((job) => job.id))

      setJobs([])
    } catch (error) {
      setFormError(
        error instanceof Error ? error.message : "Could not clear videos.",
      )
    }
  }

  const downloadJob = (job: VideoJob) => {
    if (!job.videoUrl) return

    const anchor = document.createElement("a")

    anchor.href = `${api.videoFileUrl(job.id)}&download=1`

    anchor.download = `garad-video-${job.id}.mp4`

    anchor.rel = "noopener"

    document.body.appendChild(anchor)

    anchor.click()

    anchor.remove()
  }

  const reuse = (job: VideoJob) => {
    setPrompt(job.prompt)

    setFormError("")

    window.setTimeout(() => promptRef.current?.focus(), 0)
  }

  const lightboxJob = jobs.find((job) => job.id === lightbox) || null

  const renderControl = (input: VideoInputSchema) => {
    const label = input.label || humanize(input.name)

    if (input.type === "checkbox") {
      return (
        <label className="sx-check" key={input.name} title={input.tooltip}>
          <input
            type="checkbox"
            checked={Boolean(values[input.name])}
            onChange={(event) =>
              applyValues({ [input.name]: event.target.checked })
            }
          />
          <span>{label}</span>
        </label>
      )
    }

    if (input.type === "select") {
      return (
        <label key={input.name} title={input.tooltip}>
          <span>{label}</span>
          <select
            value={String(values[input.name] ?? "")}
            onChange={(event) =>
              applyValues({ [input.name]: event.target.value })
            }
          >
            {(input.options || []).map((option) => (
              <option value={String(option.value)} key={String(option.value)}>
                {optionLabel(option)}
              </option>
            ))}
          </select>
        </label>
      )
    }

    if (input.type === "textarea") {
      return (
        <label className="wide" key={input.name} title={input.tooltip}>
          <span>{label}</span>
          <textarea
            rows={2}
            value={String(values[input.name] ?? "")}
            placeholder={input.placeholder}
            onChange={(event) =>
              applyValues({ [input.name]: event.target.value })
            }
          />
        </label>
      )
    }

    return (
      <label key={input.name} title={input.tooltip}>
        <span>{label}</span>
        <input
          type="number"
          min={input.min}
          max={input.max}
          step={input.step}
          value={String(values[input.name] ?? "")}
          onChange={(event) =>
            applyValues({ [input.name]: event.target.value })
          }
        />
      </label>
    )
  }

  const renderJobCard = (job: VideoJob) => {
    const ready = job.state === "completed" && job.videoUrl

    return (
      <article
        className={`sx-card sx-vcard ${job.state}`}
        key={job.id}
        aria-label={`Video ${job.id}`}
      >
        {ready ? (
          <button
            className="sx-card-image"
            onClick={() => setLightbox(job.id)}
            aria-label={`Play video: ${job.prompt}`}
            type="button"
          >
            <video
              src={job.videoUrl ? `${job.videoUrl}#t=0.1` : undefined}
              autoPlay
              loop
              muted
              playsInline
              preload="auto"
              ref={(node) => {
                if (node) {
                  node.muted = true

                  void node.play().catch(() => undefined)
                }
              }}
              onError={(event) => {
                event.currentTarget.style.display = "none"
              }}
            />
            <span className="sx-vplay">
              <Ico name="play" size={18} />
            </span>
          </button>
        ) : job.state === "failed" ? (
          <div className="sx-vstate failed" role="alert">
            <Ico name="alert" size={22} />
            <strong>Generation failed</strong>
            <small>{job.error || "The provider returned an error."}</small>
          </div>
        ) : (
          <div
            className="sx-vstate"
            role="status"
            aria-label="Generating video"
          >
            <div className="sx-shimmer" />
            <div className="sx-pending-copy">
              <i className="sx-spinner" />
              <strong>
                {job.state === "queued" ? "In queue" : "Generating video"}
              </strong>
              <small>
                {job.state === "queued"
                  ? job.queuePosition
                    ? `In provider queue (position ${job.queuePosition})`
                    : "Waiting in the provider queue"
                  : "Rendering your video"}
              </small>
              <b className="sx-elapsed">{formatElapsed(job.createdAt, now)}</b>
              <span className="sx-progress">
                <i />
              </span>
            </div>
          </div>
        )}
        <span className={`sx-vbadge ${job.state}`}>
          {job.state === "completed"
            ? job.durationSeconds
              ? `${job.durationSeconds}s`
              : "Ready"
            : job.state === "failed"
              ? "Failed"
              : job.state === "queued"
                ? "Queued"
                : "Processing"}
        </span>
        <div className="sx-card-overlay">
          <p title={job.prompt}>{job.prompt}</p>
          <small className="sx-vmeta">
            {[
              job.model.split("/").slice(-2).join(" · "),

              job.durationSeconds ? `${job.durationSeconds}s` : "",

              job.resolution,

              job.aspectRatio,
            ]

              .filter(Boolean)

              .join(" · ")}
          </small>
          <div className="sx-card-actions">
            {ready && (
              <>
                <button
                  aria-label="Open video"
                  title="Open"
                  onClick={() =>
                    window.open(
                      job.videoUrl || "",

                      "_blank",

                      "noopener,noreferrer",
                    )
                  }
                  type="button"
                >
                  <Ico name="external" size={14} />
                </button>
                <button
                  aria-label="Download video"
                  title="Download"
                  onClick={() => void downloadJob(job)}
                  type="button"
                >
                  <Ico name="download" size={14} />
                </button>
              </>
            )}
            <button
              aria-label="Reuse prompt"
              title="Reuse prompt"
              onClick={() => reuse(job)}
              type="button"
            >
              <Ico name="refresh" size={14} />
            </button>
            <button
              aria-label="Delete video"
              title="Delete"
              onClick={() => void removeJob(job)}
              type="button"
            >
              <Ico name="trash" size={14} />
            </button>
          </div>
        </div>
      </article>
    )
  }

  const renderTemplateCard = (item: {
    id: string

    name: string

    meta: string

    preview: string

    video?: string

    prompt: string

    onUse: () => void

    onDelete?: () => void
  }) => (
    <div
      className={`sx-template-card ${
        selectedTemplate === item.id ? "selected" : ""
      }`}
      key={item.id}
    >
      <button
        className="sx-template-main"
        onClick={item.onUse}
        title={item.prompt}
        type="button"
      >
        <span className="sx-template-text">
          <strong>{item.name}</strong>
          <small>{item.meta}</small>
        </span>
        <span className="sx-template-thumb sx-vtpl-thumb">
          {item.video ? (
            <video
              src={item.video}
              poster={item.preview}
              muted
              loop
              autoPlay
              playsInline
              preload="auto"
              aria-hidden="true"
              tabIndex={-1}
            />
          ) : item.preview ? (
            <img src={item.preview} alt="" />
          ) : (
            <Ico name="video" size={22} />
          )}
          <span className="sx-vtpl-play" aria-hidden="true">
            <Ico name="video" size={11} />
          </span>
        </span>
        <em>Use</em>
      </button>
      {item.onDelete && (
        <button
          className="sx-template-delete"
          aria-label={`Delete template ${item.name}`}
          onClick={item.onDelete}
          type="button"
        >
          <Ico name="trash" size={13} />
        </button>
      )}
    </div>
  )

  const renderTemplates = (groups: VideoTemplate["group"][]) =>
    groups.map((group) => {
      const items = templates.filter((t) => t.group === group)

      if (!items.length) return null

      return (
        <section className="sx-template-group" key={group}>
          <header>
            <h3>{group}</h3>
          </header>
          <div className="sx-template-grid">
            {items.map((t) =>
              renderTemplateCard({
                id: t.id,

                name: t.name,

                meta: `${t.aspect} · ${t.seconds}s`,

                preview: t.preview,

                video: t.video,

                prompt: t.prompt,

                onUse: () => applyTemplate(t),
              }),
            )}
          </div>
        </section>
      )
    })

  const modelName = (model: VideoModelDescriptor) => {
    const match = model.displayName.match(/^(.*?)\s*\((.*)\)\s*$/)

    return {
      name: match ? match[1] : model.displayName,

      note: match?.[2] || "",
    }
  }

  const blockedReason =
    selected && selected.availability !== "available"
      ? selected.availability === "credit_blocked"
        ? "No credits left for this model."
        : selected.availability === "not_configured"
          ? "This provider is unavailable right now (for example the OpenAI Sora API has been retired, or the key is missing)."
          : "This model is disabled."
      : ""

  const seconds = durationSeconds()

  return (
    <section className="studio" aria-label="Video Generator">
      <aside className="sx-panel">
        <div className="sx-tabs" role="tablist" aria-label="Creative tools">
          {(["image", "video", "audio"] as CreativeTool[]).map((item) => (
            <button
              key={item}
              role="tab"
              aria-selected={item === "video"}
              className={item === "video" ? "active" : ""}
              onClick={() => item !== "video" && onNavigateTool(item)}
              type="button"
            >
              {item === "image"
                ? "Image"
                : item === "video"
                  ? "Video"
                  : "Audio"}
            </button>
          ))}
        </div>

        <div className="sx-tool-card">
          <div>
            <button className="sx-back" onClick={onBackToChat} type="button">
              <Ico name="chevronLeft" size={12} /> Chat
            </button>
            <strong>Video Generator</strong>
          </div>
          <button
            className="sx-tool-templates"
            onClick={() => {
              setView("templates")

              scrollTop()
            }}
            type="button"
          >
            <Ico name="templates" size={15} />
            Templates
          </button>
        </div>

        <div className="sx-section">
          <label className="sx-label">Model</label>
          <div className="sx-model-row" ref={modelBoxRef}>
            <button
              className="sx-select"
              disabled={modelsLoading || models.length === 0}
              aria-haspopup="listbox"
              aria-expanded={modelOpen}
              onClick={() => setModelOpen((open) => !open)}
              type="button"
            >
              {modelsLoading ? (
                <span className="sx-skeleton-line" />
              ) : (
                <>
                  <span className="sx-select-icon">
                    <Ico name="video" size={13} />
                  </span>
                  <span className="sx-select-text">
                    {selected
                      ? modelName(selected).name
                      : modelsError
                        ? "Models unavailable"
                        : "No models available"}
                  </span>
                  <Ico name="chevronDown" size={14} />
                </>
              )}
            </button>
            <button
              className={`sx-icon-square ${settingsOpen ? "active" : ""}`}
              aria-label="Advanced settings"
              aria-expanded={settingsOpen}
              disabled={!selected || secondaryInputs.length === 0}
              onClick={() => setSettingsOpen((open) => !open)}
              type="button"
            >
              <Ico name="settings" size={15} />
            </button>

            {modelOpen && (
              <div className="sx-popover sx-model-popover" role="listbox">
                <div className="sx-search">
                  <Ico name="search" size={14} />
                  <input
                    autoFocus
                    placeholder="Search video models"
                    value={modelSearch}
                    onChange={(event) => setModelSearch(event.target.value)}
                  />
                </div>
                <div className="sx-chip-row">
                  {(["all", "text_to_video", "image_to_video"] as const).map(
                    (mode) => (
                      <button
                        key={mode}
                        className={modeFilter === mode ? "active" : ""}
                        onClick={() => setModeFilter(mode)}
                        type="button"
                      >
                        {mode === "all" ? "All" : `${modeLabel[mode]} to video`}
                      </button>
                    ),
                  )}
                </div>
                <div className="sx-model-list">
                  {groupedModels.length === 0 && (
                    <p className="sx-empty-line">
                      No models match your search.
                    </p>
                  )}
                  {groupedModels.map(([family, items]) => (
                    <div key={family}>
                      <div className="sx-group-title">
                        <span>{family}</span>
                        <small>
                          {items.length} model{items.length === 1 ? "" : "s"}
                        </small>
                      </div>
                      {items.map((model) => {
                        const parts = modelName(model)

                        const unavailable = model.availability !== "available"

                        return (
                          <button
                            key={model.id}
                            role="option"
                            aria-selected={model.id === modelId}
                            className={`sx-model-option ${
                              model.id === modelId ? "selected" : ""
                            } ${model.locked ? "model-locked" : ""}`}
                            disabled={unavailable && !model.locked}
                            onClick={() => {
                              if (model.locked) {
                                setModelOpen(false)

                                onUpgrade?.()

                                return
                              }

                              chooseModel(model)

                              setModelOpen(false)
                            }}
                            type="button"
                          >
                            <span className="sx-model-name">
                              <strong>{parts.name}</strong>
                              {parts.note && <small>{parts.note}</small>}
                            </span>
                            <span className="sx-badges">
                              {model.locked && (
                                <>
                                  <b>{model.requiredPlan}</b>
                                  <b className="upgrade">Upgrade</b>
                                </>
                              )}
                              {model.modes.map((mode) => (
                                <b key={mode}>{modeLabel[mode]}</b>
                              ))}
                              {model.availability === "credit_blocked" &&
                                !model.locked && (
                                  <b className="warn">No credits</b>
                                )}
                              {model.availability === "not_configured" && (
                                <b className="warn">Unavailable</b>
                              )}
                              {model.availability === "disabled" && (
                                <b className="warn">Disabled</b>
                              )}
                              {model.id === modelId && (
                                <Ico name="check" size={14} />
                              )}
                            </span>
                          </button>
                        )
                      })}
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
          {modelsError && (
            <p className="sx-inline-error" role="alert">
              <Ico name="alert" size={13} />
              <span>
                Could not load models: {modelsError}{" "}
                <button onClick={() => window.location.reload()} type="button">
                  Reload
                </button>
              </span>
            </p>
          )}
          {selected && (
            <p className="sx-model-meta">
              {selected.modes.map((mode) => modeLabel[mode]).join(" · ")} to
              video
              {selected.unlimited
                ? " · Unlimited credits"
                : selected.creditBalance !== null
                  ? ` · ${selected.creditBalance} credits`
                  : ""}
            </p>
          )}
          {blockedReason && (
            <p className="sx-inline-error" role="alert">
              <Ico name="alert" size={13} />
              <span>{blockedReason}</span>
            </p>
          )}
          {settingsOpen && secondaryInputs.length > 0 && (
            <div className="sx-settings">
              {secondaryInputs

                .filter((i) => i.name !== "generate_audio")

                .map(renderControl)}
            </div>
          )}
        </div>

        {fileInputs.length > 0 && (
          <div className="sx-section">
            <div className="sx-label-row">
              <label className="sx-label">References</label>
              <span>
                {Object.values(files).reduce(
                  (sum, list) => sum + list.length,

                  0,
                )}{" "}
                added
              </span>
            </div>
            <div className="sx-vrefs">
              {fileInputs.map((input) => {
                const list = files[input.name] || []

                const urls = previews[input.name] || []

                const kind = fileKind(input)

                const full = !input.multiple && list.length >= 1

                return (
                  <div className="sx-vref" key={input.name}>
                    <div className="sx-vref-label">
                      <strong>{input.label || humanize(input.name)}</strong>
                      <small>
                        {input.required ? "Required" : "Optional"} · {kind}
                      </small>
                    </div>
                    <div className="sx-ref-row wrap">
                      {list.map((file, index) => (
                        <div
                          className="sx-ref-tile filled"
                          key={`${file.name}-${index}`}
                        >
                          {kind === "image" ? (
                            <img src={urls[index]} alt={file.name} />
                          ) : kind === "video" ? (
                            <video src={urls[index]} muted preload="metadata" />
                          ) : (
                            <span>{file.name.slice(0, 8)}</span>
                          )}
                          <button
                            aria-label={`Remove ${file.name}`}
                            className="sx-ref-remove"
                            onClick={() => removeFile(input, index)}
                            type="button"
                          >
                            <Ico name="x" size={12} />
                          </button>
                        </div>
                      ))}
                      {!full && (
                        <button
                          className={`sx-ref-tile add ${
                            dragKey === input.name ? "dragging" : ""
                          }`}
                          onClick={() => fileRefs.current[input.name]?.click()}
                          onDragOver={(event) => {
                            event.preventDefault()

                            setDragKey(input.name)
                          }}
                          onDragLeave={() => setDragKey("")}
                          onDrop={(event) => {
                            event.preventDefault()

                            setDragKey("")

                            addFiles(input, event.dataTransfer.files)
                          }}
                          type="button"
                        >
                          <Ico name="plus" size={17} />
                          <span>Add</span>
                        </button>
                      )}
                    </div>
                    <input
                      ref={(node) => {
                        fileRefs.current[input.name] = node
                      }}
                      className="creative-file-input"
                      type="file"
                      accept={input.accept}
                      multiple={input.multiple}
                      onChange={(event) => {
                        addFiles(input, event.target.files)

                        event.target.value = ""
                      }}
                    />
                  </div>
                )
              })}
            </div>
          </div>
        )}

        {promptInput && (
          <div className="sx-section sx-grow">
            <div className="sx-label-row">
              <label className="sx-label" htmlFor="vx-prompt">
                Prompt
              </label>
              {prompt && (
                <button
                  className="sx-link"
                  onClick={() => {
                    setPrompt("")

                    setSelectedTemplate("")
                  }}
                  type="button"
                >
                  Clear
                </button>
              )}
            </div>
            <div
              className={`sx-prompt ${
                formError && !prompt.trim() ? "invalid" : ""
              }`}
            >
              <textarea
                id="vx-prompt"
                ref={promptRef}
                rows={6}
                value={prompt}
                maxLength={5000}
                placeholder={
                  promptInput.placeholder ||
                  "Describe the shot: subject, action, camera, lighting…"
                }
                onChange={(event) => {
                  setPrompt(event.target.value)

                  setSelectedTemplate("")
                }}
                onKeyDown={(event) => {
                  if (
                    (event.ctrlKey || event.metaKey) &&
                    event.key === "Enter"
                  ) {
                    event.preventDefault()

                    void submit()
                  }
                }}
              />
              <small>{prompt.length.toLocaleString()} / 5,000</small>
            </div>
          </div>
        )}

        {(primaryInputs.length > 0 || audioInput) && (
          <div className="sx-vsettings">
            {primaryInputs.map(renderControl)}
            {audioInput && renderControl(audioInput)}
          </div>
        )}

        {formError && (
          <p className="sx-inline-error" role="alert">
            <Ico name="alert" size={13} />
            <span>{formError}</span>
          </p>
        )}

        <button
          className="sx-generate"
          disabled={
            submitting || modelsLoading || !selected || Boolean(blockedReason)
          }
          onClick={() => void submit()}
          type="button"
        >
          {submitting ? (
            <>
              <i className="sx-spinner" /> Submitting…
            </>
          ) : (
            <>
              <span className="sx-generate-label">
                <Ico name="sparkle" size={15} /> Generate video
              </span>
              {seconds ? <small>{seconds}s</small> : null}
            </>
          )}
        </button>
        <p className="sx-credit-note">Uses your Garad credits · Ctrl+Enter</p>
      </aside>

      <div className="sx-canvas" ref={canvasRef}>
        <header className="sx-canvas-bar">
          <div className="sx-canvas-tabs">
            <button
              className={view === "creations" ? "active" : ""}
              onClick={() => setView("creations")}
              type="button"
            >
              <Ico name="video" size={14} /> Creations
              {jobs.length > 0 && <b>{jobs.length}</b>}
            </button>
            <button
              className={view === "templates" ? "active" : ""}
              onClick={() => setView("templates")}
              type="button"
            >
              <Ico name="templates" size={14} /> Templates
            </button>
            <button
              className={view === "mine" ? "active" : ""}
              onClick={() => setView("mine")}
              type="button"
            >
              <Ico name="star" size={14} /> My templates
              {saved.length > 0 && <b>{saved.length}</b>}
            </button>
          </div>
          <div className="sx-canvas-actions">
            <button className="sx-pill" onClick={openSave} type="button">
              <Ico name="plus" size={14} /> Save template
            </button>
            <button
              className="sx-pill"
              onClick={() => void refreshHistory()}
              type="button"
            >
              <Ico name="refresh" size={14} /> Refresh
            </button>
            {jobs.length > 0 && (
              <button
                className="sx-pill"
                onClick={() => void clearAll()}
                type="button"
              >
                <Ico name="trash" size={14} /> Clear
              </button>
            )}
          </div>
        </header>

        <div className="sx-canvas-body">
          {view === "creations" && (
            <>
              {jobsLoading ? (
                <p className="sx-empty-line">Loading your videos…</p>
              ) : jobs.length > 0 ? (
                <section className="sx-creations">
                  <header className="sx-section-head">
                    <h2>Your creations</h2>
                    <span>
                      {activeJobs.length > 0
                        ? `${activeJobs.length} generating…`
                        : `${jobs.length} saved`}
                    </span>
                  </header>
                  <div className="sx-gallery video">
                    {jobs.map(renderJobCard)}
                  </div>
                </section>
              ) : (
                <section className="sx-hero">
                  <span className="sx-hero-icon">
                    <Ico name="video" size={18} />
                  </span>
                  <h1>Video Generator</h1>
                  <p>
                    Create AI videos from a text prompt or reference images with
                    your Garad models
                  </p>
                </section>
              )}
              <section className="sx-start">
                <header className="sx-section-head stack">
                  <h2>Start creating</h2>
                  <span>
                    Pick a template to fill in the prompt and settings, then
                    generate.
                  </span>
                </header>
                {renderTemplates(["Featured"])}
                <button
                  className="sx-see-all"
                  onClick={() => setView("templates")}
                  type="button"
                >
                  See all templates <Ico name="chevronRight" size={13} />
                </button>
              </section>
            </>
          )}

          {view === "templates" && (
            <section className="sx-start">
              <header className="sx-section-head stack">
                <h2>Templates</h2>
                <span>
                  Ready-to-go starting points. Selecting one fills your prompt
                  and settings.
                </span>
              </header>
              <div className="sx-build">
                <h3>Build your first template</h3>
                <p>Save your favorite setups as reusable templates</p>
                <button className="sx-primary" onClick={openSave} type="button">
                  Create your first template
                </button>
              </div>
              {renderTemplates(["Featured", "Branding", "Creative"])}
            </section>
          )}

          {view === "mine" && (
            <section className="sx-start">
              <header className="sx-section-head stack">
                <h2>My templates</h2>
                <span>Your saved prompts and settings, ready to reuse.</span>
              </header>
              {saved.length === 0 ? (
                <div className="sx-empty-state">
                  <span>
                    <Ico name="templates" size={22} />
                  </span>
                  <h3>Build your first template</h3>
                  <p>
                    No saved templates yet. Save a prompt and settings and they
                    will appear here.
                  </p>
                  <button
                    className="sx-primary"
                    onClick={openSave}
                    type="button"
                  >
                    <Ico name="plus" size={15} /> Create your first template
                  </button>
                </div>
              ) : (
                <div className="sx-template-grid">
                  {saved.map((t) =>
                    renderTemplateCard({
                      id: t.id,

                      name: t.name,

                      meta: t.modelName,

                      preview: t.preview,

                      prompt: t.prompt,

                      onUse: () => applySaved(t),

                      onDelete: () => deleteSaved(t.id),
                    }),
                  )}
                </div>
              )}
            </section>
          )}
        </div>
      </div>

      {saveOpen && (
        <div
          className="sx-modal-backdrop"
          role="dialog"
          aria-modal="true"
          aria-label="Save template"
          onClick={() => setSaveOpen(false)}
        >
          <div
            className="sx-modal"
            onClick={(event) => event.stopPropagation()}
          >
            <header className="sx-modal-head">
              <h2>Save template</h2>
              <button
                className="sx-banner-close"
                aria-label="Close"
                onClick={() => setSaveOpen(false)}
                type="button"
              >
                <Ico name="x" size={15} />
              </button>
            </header>
            <div className="sx-modal-body">
              <div className="sx-modal-col">
                <div className="sx-section">
                  <span className="sx-label">Model</span>
                  <select
                    className="sx-modal-input"
                    value={saveModelId}
                    onChange={(event) => {
                      const m = models.find((x) => x.id === event.target.value)

                      setSaveModelId(event.target.value)

                      if (m) setSaveValues(defaultsFor(m))
                    }}
                  >
                    {models

                      .filter(
                        (m) => m.availability === "available" && !m.locked,
                      )

                      .map((m) => (
                        <option value={m.id} key={m.id}>
                          {modelName(m).name}
                        </option>
                      ))}
                  </select>
                </div>
                {saveFileInputs.length > 0 && (
                  <div className="sx-section">
                    <span className="sx-label">References</span>
                    <div className="sx-modal-refs">
                      {saveFileInputs.map((i) => (
                        <span key={i.name} className="sx-modal-ref">
                          <Ico name="image" size={13} />
                          {i.label || humanize(i.name)}
                          {i.required ? " *" : ""}
                        </span>
                      ))}
                    </div>
                  </div>
                )}
                <div className="sx-section">
                  <span className="sx-label">Prompt</span>
                  <textarea
                    className="sx-modal-input sx-modal-textarea"
                    value={savePrompt}
                    onChange={(event) => setSavePrompt(event.target.value)}
                    placeholder="Describe the video this template creates"
                  />
                </div>
                {saveSettings.length > 0 && (
                  <div className="sx-modal-settings">
                    {saveSettings.map((input) =>
                      input.type === "checkbox" ? (
                        <label className="sx-check" key={input.name}>
                          <input
                            type="checkbox"
                            checked={Boolean(saveValues[input.name])}
                            onChange={(event) =>
                              setSaveValues((c) => ({
                                ...c,

                                [input.name]: event.target.checked,
                              }))
                            }
                          />
                          <span>{input.label || humanize(input.name)}</span>
                        </label>
                      ) : (
                        <label key={input.name}>
                          <span>{input.label || humanize(input.name)}</span>
                          <select
                            value={String(saveValues[input.name] ?? "")}
                            onChange={(event) =>
                              setSaveValues((c) => ({
                                ...c,

                                [input.name]: event.target.value,
                              }))
                            }
                          >
                            {(input.options || []).map((option) => (
                              <option
                                value={String(option.value)}
                                key={String(option.value)}
                              >
                                {optionLabel(option)}
                              </option>
                            ))}
                          </select>
                        </label>
                      ),
                    )}
                  </div>
                )}
              </div>
              <div className="sx-modal-col">
                <div className="sx-section">
                  <label className="sx-label" htmlFor="vx-tpl-name">
                    Template name
                  </label>
                  <input
                    id="vx-tpl-name"
                    className="sx-modal-input"
                    autoFocus
                    maxLength={60}
                    value={saveName}
                    onChange={(event) => {
                      setSaveName(event.target.value)

                      setSaveError("")
                    }}
                    onKeyDown={(event) => {
                      if (event.key === "Enter") submitSave()
                    }}
                    placeholder="e.g. Cinematic product reveal"
                  />
                </div>
                <div className="sx-section">
                  <span className="sx-label">Preview</span>
                  <div className="sx-modal-preview">
                    {savePreview ? (
                      <img src={savePreview} alt="Template preview" />
                    ) : (
                      <span>
                        <Ico name="image" size={22} />
                        No preview selected
                      </span>
                    )}
                  </div>
                  <input
                    ref={saveFileRef}
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    hidden
                    onChange={(event) => {
                      const file = event.target.files?.[0]

                      event.target.value = ""

                      if (file) {
                        downscale(file)

                          .then(setSavePreview)

                          .catch((error: Error) => setSaveError(error.message))
                      }
                    }}
                  />
                  <button
                    className="sx-pill"
                    onClick={() => saveFileRef.current?.click()}
                    type="button"
                  >
                    <Ico name="image" size={14} /> Select image
                  </button>
                </div>
              </div>
            </div>
            {saveError && (
              <p className="sx-inline-error sx-modal-error" role="alert">
                <Ico name="alert" size={13} />
                <span>{saveError}</span>
              </p>
            )}
            <footer className="sx-modal-foot">
              <button
                className="sx-pill"
                onClick={() => setSaveOpen(false)}
                type="button"
              >
                Cancel
              </button>
              <button className="sx-primary" onClick={submitSave} type="button">
                Save template
              </button>
            </footer>
          </div>
        </div>
      )}

      {lightboxJob && lightboxJob.videoUrl && (
        <div
          className="sx-lightbox"
          role="dialog"
          aria-modal="true"
          aria-label="Video preview"
          onClick={() => setLightbox(null)}
        >
          <div
            className="sx-lightbox-card"
            onClick={(event) => event.stopPropagation()}
          >
            <button
              className="sx-lightbox-close"
              aria-label="Close preview"
              onClick={() => setLightbox(null)}
              type="button"
            >
              <Ico name="x" size={16} />
            </button>
            <div className="sx-lightbox-media">
              <video src={lightboxJob.videoUrl} controls autoPlay playsInline />
            </div>
            <aside className="sx-lightbox-side">
              <span className="sx-label">Prompt</span>
              <p>{lightboxJob.prompt}</p>
              <dl>
                <div>
                  <dt>Model</dt>
                  <dd>{modelShort(lightboxJob, models)}</dd>
                </div>
                {lightboxJob.durationSeconds && (
                  <div>
                    <dt>Duration</dt>
                    <dd>{lightboxJob.durationSeconds}s</dd>
                  </div>
                )}
                {lightboxJob.aspectRatio && (
                  <div>
                    <dt>Format</dt>
                    <dd>{lightboxJob.aspectRatio}</dd>
                  </div>
                )}
                {lightboxJob.resolution && (
                  <div>
                    <dt>Resolution</dt>
                    <dd>{lightboxJob.resolution}</dd>
                  </div>
                )}
                <div>
                  <dt>Created</dt>
                  <dd>{formatTime(lightboxJob.createdAt)}</dd>
                </div>
              </dl>
              <div className="sx-lightbox-actions">
                <button
                  className="sx-primary"
                  onClick={() => void downloadJob(lightboxJob)}
                  type="button"
                >
                  <Ico name="download" size={15} /> Download
                </button>
                <button
                  className="sx-pill"
                  onClick={() =>
                    window.open(
                      lightboxJob.videoUrl || "",

                      "_blank",

                      "noopener,noreferrer",
                    )
                  }
                  type="button"
                >
                  <Ico name="external" size={14} /> Open in new tab
                </button>
                <button
                  className="sx-pill"
                  onClick={() => {
                    reuse(lightboxJob)

                    setLightbox(null)
                  }}
                  type="button"
                >
                  <Ico name="refresh" size={14} /> Reuse prompt
                </button>
                <button
                  className="sx-pill"
                  onClick={() => void removeJob(lightboxJob)}
                  type="button"
                >
                  <Ico name="trash" size={14} /> Delete
                </button>
              </div>
            </aside>
          </div>
        </div>
      )}
    </section>
  )
}
