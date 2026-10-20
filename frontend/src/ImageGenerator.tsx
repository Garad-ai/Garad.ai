import {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
  type CSSProperties,
} from "react"

import type { ApiClient, GeneratedImage } from "./core/api"

import type { ImageModelDescriptor } from "./core/types"

type CreativeTool = "image" | "video" | "audio" | "social"

type Props = {
  api: ApiClient

  tool: CreativeTool

  isAuthenticated: boolean

  onRequireLogin: () => void

  onBackToChat: () => void

  onNavigateTool: (tool: CreativeTool) => void

  onUpgrade?: () => void
}

type ImageTemplate = {
  id: string

  name: string

  group: "Featured" | "Branding" | "Creative"

  prompt: string

  style: string

  lighting: string

  mood: string

  size: string

  quality: string

  count: number

  preview: string
}

type SavedTemplate = {
  id: string

  name: string

  modelId: string

  modelName: string

  prompt: string

  size: string

  quality: string

  count: number

  style: string

  lighting: string

  mood: string

  negativePrompt: string

  preview: string

  createdAt: number
}

type GenerationParams = {
  modelId: string

  prompt: string

  size: string

  quality: string

  count: number

  style: string

  lighting: string

  mood: string

  negativePrompt: string
}

type StudioImage = GeneratedImage & GenerationParams & {
  modelName: string

  createdAt: number

  usedReference: boolean
}

type IconName = "chat" | "image" | "video" | "share" | "chevronDown" | "chevronLeft" | "chevronRight" | "plus" | "minus" | "x" | "download" | "external" | "refresh" | "reference" | "trash" | "search" | "settings" | "templates" | "sparkle" | "check" | "grid" | "alert" | "lock"

const iconPaths: Record<IconName, string> = {
  chat: "M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v8a2.5 2.5 0 0 1-2.5 2.5H10l-4.5 4v-4A2.5 2.5 0 0 1 4 13.5z",

  image:
    "M5 4h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1zm0 12 4.5-5 3.5 4 2.5-2.5L20 17M15.5 9.5h.01",

  video: "M4 6h10a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4zM16 10.5 21 8v8l-5-2.5",

  share:
    "M18 8a3 3 0 1 0-2.8-4M6 15a3 3 0 1 0 0 .01M18 21a3 3 0 1 0 0-.01M8.6 13.5l6.8 4M15.4 6.5l-6.8 4",

  chevronDown: "m6 9 6 6 6-6",

  chevronLeft: "m15 6-6 6 6 6",

  chevronRight: "m9 6 6 6-6 6",

  plus: "M12 5v14M5 12h14",

  minus: "M5 12h14",

  x: "M6 6l12 12M18 6 6 18",

  download: "M12 4v11m0 0 4-4m-4 4-4-4M5 20h14",

  external:
    "M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5",

  refresh: "M20 11a8 8 0 1 0-2.3 5.7M20 4v7h-7",

  reference:
    "M12 3l2.4 5.2 5.6.7-4.1 3.9 1 5.6-4.9-2.7-4.9 2.7 1-5.6L4 8.9l5.6-.7z",

  trash: "M5 7h14M10 7V4h4v3M7 7l1 13h8l1-13M10 11v6M14 11v6",

  search: "M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14zm9 3-4.5-4.5",

  settings:
    "M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm7.4-3a7.5 7.5 0 0 0-.1-1.2l2-1.5-2-3.4-2.3.9a7.5 7.5 0 0 0-2-1.2L14.600 3h-4l-.4 2.6a7.500 7.500 0 0 0-2 1.200l-2.300-.9-2 3.400 2 1.500a7.500 7.500 0 0 0 0 2.400l-2 1.500 2 3.400 2.300-.9a7.500 7.500 0 0 0 2 1.200l.4 2.600h4l.4-2.600a7.500 7.500 0 0 0 2-1.200l2.300.9 2-3.400-2-1.500c.1-.4.100-.8.100-1.200z",

  templates: "M5 4h9l5 5v11H5zM14 4v5h5M8 13h8M8 17h6",

  sparkle:
    "M12 3l1.8 5.2L19 10l-5.200 1.800L12 17l-1.800-5.200L5 10l5.200-1.800zM18 16l.8 2.200L21 19l-2.200.8L18 22l-.8-2.200L15 19l2.200-.8z",

  check: "m5 12.500 4.500 4.500L19 7.500",

  grid: "M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z",

  alert:
    "M12 8v5m0 3.500h.01M10.300 4.200 2.800 17.500A2 2 0 0 0 4.500 20.500h15a2 2 0 0 0 1.700-3L13.700 4.200a2 2 0 0 0-3.400 0z",

  lock: "M7 11V8a5 5 0 0 1 10 0v3M6 11h12v9H6z",
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

const previewModules = import.meta.glob<string>("./assets/templates/*.webp", {
  eager: true,

  import: "default",
})

const tplPreview = (id: string): string =>
  previewModules[`./assets/templates/${id}.webp`] || ""

const templates: ImageTemplate[] = [
  {
    id: "brand-moodboard",

    preview: tplPreview("brand-moodboard"),

    name: "Brand identity moodboard",

    group: "Featured",

    prompt:
      "A brand identity moodboard for a modern coffee house: logo tile, packaging in brand colors, menu card with clean typography, storefront signage mockup, a color palette tile, cohesive grid collage layout, premium art direction",

    style: "minimalism",

    lighting: "studio",

    mood: "bright",

    size: "1536x1024",

    quality: "high",

    count: 1,
  },

  {
    id: "movie-poster",

    preview: tplPreview("movie-poster"),

    name: "Movie poster",

    group: "Featured",

    prompt:
      "A dramatic cinematic movie poster, a lone figure facing a vast glowing horizon, bold title treatment area at the bottom, atmospheric haze, rich film color grading, theatrical composition",

    style: "realistic",

    lighting: "cinematic",

    mood: "dark",

    size: "1024x1536",

    quality: "high",

    count: 1,
  },

  {
    id: "product-ad",

    preview: tplPreview("product-ad"),

    name: "Surreal oversized product ad",

    group: "Featured",

    prompt:
      "A surreal advertising image of a giant sneaker towering over a small person, clean pale backdrop, bold blue typography shapes behind, high-end campaign photography, crisp shadows",

    style: "realistic",

    lighting: "studio",

    mood: "bright",

    size: "1024x1536",

    quality: "high",

    count: 1,
  },

  {
    id: "editorial",

    preview: tplPreview("editorial"),

    name: "Editorial fashion photo",

    group: "Featured",

    prompt:
      "A confident model in a refined editorial fashion portrait, deep red studio backdrop, off-shoulder black garment, natural expression, premium magazine photography, realistic skin texture, crisp details",

    style: "realistic",

    lighting: "studio",

    mood: "calm",

    size: "1024x1536",

    quality: "high",

    count: 1,
  },

  {
    id: "tech-product",

    preview: tplPreview("tech-product"),

    name: "Generate a tech product photo",

    group: "Branding",

    prompt:
      "A premium pair of silver wireless headphones as the hero subject on a deep navy gradient backdrop, subtle reflections, carefully controlled highlights, elegant commercial art direction, no text or watermark",

    style: "3d_render",

    lighting: "studio",

    mood: "dark",

    size: "1024x1024",

    quality: "high",

    count: 1,
  },

  {
    id: "cinematic",

    preview: tplPreview("cinematic"),

    name: "Cinematic landscape",

    group: "Creative",

    prompt:
      "A cinematic wide scene with a clear focal subject, layered depth, atmospheric environment, rich natural textures, expressive composition, dramatic film lighting",

    style: "realistic",

    lighting: "dramatic",

    mood: "dark",

    size: "1536x1024",

    quality: "high",

    count: 1,
  },

  {
    id: "illustration",

    preview: tplPreview("illustration"),

    name: "Expressive illustration",

    group: "Creative",

    prompt:
      "A carefully composed illustrated scene with distinctive shapes, balanced color, fine hand-crafted details, cohesive art direction, clean edges, no text or watermark",

    style: "watercolor",

    lighting: "ambient",

    mood: "colorful",

    size: "1024x1024",

    quality: "medium",

    count: 1,
  },

  {
    id: "packaging",

    preview: tplPreview("packaging"),

    name: "Product packaging mockup",

    group: "Branding",

    prompt:
      "A minimalist skincare jar and box packaging mockup on a soft teal surface, glass and matte textures, gentle window light, subtle depth of field, premium branding photography",

    style: "realistic",

    lighting: "natural",

    mood: "calm",

    size: "1024x1024",

    quality: "high",

    count: 1,
  },
]

type SizeOption = {
  value: string
  label: string
  ratio: string
}

const sizeOptions: SizeOption[] = [
  { value: "1024x1024", label: "Square", ratio: "1:1" },

  { value: "1536x1024", label: "Landscape", ratio: "3:2" },

  { value: "1024x1536", label: "Portrait", ratio: "2:3" },
]

const styleOptions = [
  ["", "None"],

  ["3d_render", "3D render"],

  ["anime", "Anime"],

  ["cartoon", "Cartoon"],

  ["cyberpunk", "Cyberpunk"],

  ["impressionism", "Impressionism"],

  ["line", "Line art"],

  ["minimalism", "Minimalist"],

  ["pixel", "Pixel art"],

  ["realistic", "Realistic"],

  ["watercolor", "Watercolor"],
]

const lightingOptions = [
  ["", "None"],

  ["ambient", "Ambient"],

  ["cinematic", "Cinematic"],

  ["dramatic", "Dramatic"],

  ["golden_hour", "Golden hour"],

  ["natural", "Natural"],

  ["neon", "Neon"],

  ["studio", "Studio"],
]

const moodOptions = [
  ["", "None"],

  ["bright", "Bright"],

  ["calm", "Calm"],

  ["cheerful", "Cheerful"],

  ["colorful", "Colorful"],

  ["dark", "Dark"],

  ["neutral", "Neutral"],
]

const qualityOptions = [
  ["auto", "Auto"],

  ["low", "Low"],

  ["medium", "Medium"],

  ["high", "High"],
]

const tips = [
  {
    title: "Pick a model",

    body: "Choose the model that best fits your creative goal. Models marked Refs accept a reference image.",

    icon: "grid" as IconName,
  },

  {
    title: "Add a reference",

    body: "Upload an image to guide composition, subject or style, on models that support references.",

    icon: "reference" as IconName,
  },

  {
    title: "Describe your image",

    body: "Be specific about subject, setting, lighting and mood. Templates fill in a strong starting prompt.",

    icon: "templates" as IconName,
  },

  {
    title: "Generate and refine",

    body: "Open any result to regenerate it, reuse its prompt, or use it as a reference for the next image.",

    icon: "sparkle" as IconName,
  },
]

const providerLabels: Record<string, string> = {
  openai: "OpenAI",

  stable_diffusion: "Stability AI",

  fal_ai: "Fal.ai",

  midjourney: "Midjourney",

  pi_api: "PiAPI",
}

const providerLabel = (provider: string): string =>
  providerLabels[provider] ||
  provider

    .replace(/[_-]/g, " ")

    .replace(/\b\w/g, (letter) => letter.toUpperCase())

const splitModelName = (model: ImageModelDescriptor) => {
  const match = model.displayName.match(
    /^(.*?)\s+((?:The|Most|Fast|A|An)\s.+)$/,
  )

  return match
    ? { name: match[1], note: match[2] }
    : { name: model.displayName, note: "" }
}

const toolTitles: Record<CreativeTool, string> = {
  image: "Image Generator",

  video: "AI Video Generator",

  audio: "Audio",
  social: "Social Media",
}

const HISTORY_KEY = "garad.imageStudio.history.v1"

const HISTORY_LIMIT = 60

const loadHistory = (): StudioImage[] => {
  try {
    const raw = window.localStorage.getItem(HISTORY_KEY)

    const parsed: unknown = raw ? JSON.parse(raw) : []

    if (!Array.isArray(parsed)) return []

    return parsed.filter(
      (item): item is StudioImage =>
        Boolean(item) &&
        typeof item === "object" &&
        typeof (item as StudioImage).url === "string" &&
        typeof (item as StudioImage).id === "string",
    )
  } catch {
    return []
  }
}

const saveHistory = (items: StudioImage[]) => {
  try {
    window.localStorage.setItem(
      HISTORY_KEY,

      JSON.stringify(items.slice(0, HISTORY_LIMIT)),
    )
  } catch {
    // Storage can be unavailable or full; history then lasts for this session only.
  }
}

const TEMPLATES_KEY = "garad.imageStudio.templates.v1"

const loadSavedTemplates = (): SavedTemplate[] => {
  try {
    const parsed: unknown = JSON.parse(
      window.localStorage.getItem(TEMPLATES_KEY) || "[]",
    )

    if (!Array.isArray(parsed)) return []

    return parsed.filter(
      (item): item is SavedTemplate =>
        Boolean(item) &&
        typeof (item as SavedTemplate).id === "string" &&
        typeof (item as SavedTemplate).name === "string" &&
        typeof (item as SavedTemplate).prompt === "string",
    )
  } catch {
    return []
  }
}

const persistSavedTemplates = (items: SavedTemplate[]): boolean => {
  try {
    window.localStorage.setItem(TEMPLATES_KEY, JSON.stringify(items))

    return true
  } catch {
    return false
  }
}

// Downscale an uploaded preview so stored templates stay small.

const fileToPreview = (file: File): Promise<string> =>
  new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file)

    const img = new Image()

    img.onload = () => {
      const scale = Math.min(1, 640 / Math.max(img.width, img.height))

      const canvas = document.createElement("canvas")

      canvas.width = Math.max(1, Math.round(img.width * scale))

      canvas.height = Math.max(1, Math.round(img.height * scale))

      canvas.getContext("2d")?.drawImage(img, 0, 0, canvas.width, canvas.height)

      URL.revokeObjectURL(url)

      resolve(canvas.toDataURL("image/webp", 0.8))
    }

    img.onerror = () => {
      URL.revokeObjectURL(url)

      reject(new Error("That file is not a readable image."))
    }

    img.src = url
  })

const formatTime = (timestamp: number): string =>
  new Date(timestamp).toLocaleString(undefined, {
    month: "short",

    day: "numeric",

    hour: "numeric",

    minute: "2-digit",
  })

type DownloadableImage = {
  url: string
  name: string
}

const downloadImage = (
  api: { imageProxyUrl: (url: string, download?: boolean) => string },

  image: DownloadableImage,
) => {
  // Same-origin proxy: the image host sends no CORS headers, so a direct blob download is blocked.

  const link = document.createElement("a")

  link.href = api.imageProxyUrl(image.url, true)

  link.download = image.name || "garad-image.png"

  document.body.appendChild(link)

  link.click()

  link.remove()
}

export default function ImageGenerator({
  api,

  tool,

  isAuthenticated,

  onRequireLogin,

  onBackToChat,

  onNavigateTool,

  onUpgrade,
}: Props) {
  const [models, setModels] = useState<ImageModelDescriptor[]>([])

  const [modelsLoading, setModelsLoading] = useState(tool === "image")

  const [modelsError, setModelsError] = useState("")

  const [selectedModelId, setSelectedModelId] = useState("")

  const [selectedTemplateId, setSelectedTemplateId] = useState("")

  const [prompt, setPrompt] = useState("")

  const [size, setSize] = useState("1024x1024")

  const [quality, setQuality] = useState("auto")

  const [count, setCount] = useState(1)

  const [style, setStyle] = useState("")

  const [lighting, setLighting] = useState("")

  const [mood, setMood] = useState("")

  const [negativePrompt, setNegativePrompt] = useState("")

  const [reference, setReference] = useState<File | null>(null)

  const [history, setHistory] = useState<StudioImage[]>(() => loadHistory())

  const [promptError, setPromptError] = useState("")

  const [generationError, setGenerationError] = useState("")

  const [notice, setNotice] = useState("")

  const [isGenerating, setIsGenerating] = useState(false)

  const [pendingCount, setPendingCount] = useState(0)

  const [elapsed, setElapsed] = useState(0)

  const [savedTemplates, setSavedTemplates] = useState<SavedTemplate[]>(() =>
    loadSavedTemplates(),
  )

  const [saveOpen, setSaveOpen] = useState(false)

  const [saveName, setSaveName] = useState("")

  const [savePrompt, setSavePrompt] = useState("")

  const [savePreview, setSavePreview] = useState("")

  const [saveError, setSaveError] = useState("")

  const saveFileRef = useRef<HTMLInputElement>(null)

  const [view, setView] = useState<"creations" | "templates" | "mine">(
    "creations",
  )

  const [compact, setCompact] = useState(false)

  const [tipIndex, setTipIndex] = useState(0)

  const [modelOpen, setModelOpen] = useState(false)

  const [modelSearch, setModelSearch] = useState("")

  const [refsOnly, setRefsOnly] = useState(false)

  const [ratioOpen, setRatioOpen] = useState(false)

  const [settingsOpen, setSettingsOpen] = useState(false)

  const [lightbox, setLightbox] = useState<string | null>(null)

  const [dragging, setDragging] = useState(false)

  const fileInputRef = useRef<HTMLInputElement>(null)

  const panelRef = useRef<HTMLElement>(null)

  const canvasRef = useRef<HTMLDivElement>(null)

  const promptRef = useRef<HTMLTextAreaElement>(null)

  const modelBoxRef = useRef<HTMLDivElement>(null)

  const ratioBoxRef = useRef<HTMLDivElement>(null)

  const referencePreview = useMemo(
    () => (reference ? URL.createObjectURL(reference) : ""),

    [reference],
  )

  useEffect(() => {
    if (tool !== "image") return

    let active = true

    setModelsLoading(true)

    setModelsError("")

    void api

      .getImageModels()

      .then((items) => {
        if (!active) return

        setModels(items)

        setSelectedModelId((current) =>
          items.some(
            (model) =>
              model.id === current &&
              model.availability !== "unavailable" &&
              !model.locked,
          )
            ? current
            : (items.find(
                (model) => model.availability === "available" && !model.locked,
              )?.id ??
              items[0]?.id ??
              ""),
        )
      })

      .catch((error: unknown) => {
        if (!active) return

        setModelsError(
          error instanceof Error
            ? error.message
            : "Could not load image models from Garad.",
        )
      })

      .finally(() => {
        if (active) setModelsLoading(false)
      })

    return () => {
      active = false
    }
  }, [api, tool])

  useEffect(
    () => () => {
      if (referencePreview) URL.revokeObjectURL(referencePreview)
    },

    [referencePreview],
  )

  useEffect(() => {
    if (!isGenerating) return

    const started = Date.now()

    setElapsed(0)

    const timer = window.setInterval(
      () => setElapsed(Math.floor((Date.now() - started) / 1000)),

      1000,
    )

    return () => window.clearInterval(timer)
  }, [isGenerating])

  useEffect(() => {
    if (!notice) return

    const timer = window.setTimeout(() => setNotice(""), 3200)

    return () => window.clearTimeout(timer)
  }, [notice])

  useEffect(() => {
    const onPointerDown = (event: MouseEvent) => {
      const target = event.target as Node

      if (modelBoxRef.current && !modelBoxRef.current.contains(target)) {
        setModelOpen(false)
      }

      if (ratioBoxRef.current && !ratioBoxRef.current.contains(target)) {
        setRatioOpen(false)
      }
    }

    const onKey = (event: KeyboardEvent) => {
      if (event.key !== "Escape") return

      setModelOpen(false)

      setRatioOpen(false)

      setLightbox(null)

      setSaveOpen(false)
    }

    document.addEventListener("mousedown", onPointerDown)

    document.addEventListener("keydown", onKey)

    return () => {
      document.removeEventListener("mousedown", onPointerDown)

      document.removeEventListener("keydown", onKey)
    }
  }, [])

  const selectedModel = models.find((model) => model.id === selectedModelId)

  const canUseReference = Boolean(selectedModel?.referenceMode)

  const lightboxImage = history.find((item) => item.id === lightbox)

  const groupedModels = useMemo(() => {
    const query = modelSearch.trim().toLowerCase()

    const groups = new Map<string, ImageModelDescriptor[]>()

    models.forEach((model) => {
      if (refsOnly && !model.referenceMode) return

      if (
        query &&
        !`${model.displayName} ${model.provider} ${model.falAiModel || ""}`

          .toLowerCase()

          .includes(query)
      ) {
        return
      }

      const key = providerLabel(model.provider)

      groups.set(key, [...(groups.get(key) || []), model])
    })

    return Array.from(groups.entries())
  }, [models, modelSearch, refsOnly])

  const updateHistory = useCallback(
    (updater: (items: StudioImage[]) => StudioImage[]) => {
      setHistory((current) => {
        const next = updater(current).slice(0, HISTORY_LIMIT)

        saveHistory(next)

        return next
      })
    },

    [],
  )

  const scrollTo = (target: HTMLElement | null) =>
    target?.scrollIntoView({ behavior: "smooth", block: "start" })

  const applyTemplate = (template: ImageTemplate | SavedTemplate) => {
    setSelectedTemplateId(template.id)

    if (
      "modelId" in template &&
      models.some(
        (model) =>
          model.id === template.modelId &&
          model.availability !== "unavailable" &&
          !model.locked,
      )
    ) {
      setSelectedModelId(template.modelId)
    }

    setNegativePrompt(
      "negativePrompt" in template ? template.negativePrompt : "",
    )

    setPrompt(template.prompt)

    setStyle(template.style)

    setLighting(template.lighting)

    setMood(template.mood)

    setSize(template.size)

    setQuality(template.quality)

    setCount(template.count)

    setPromptError("")

    setGenerationError("")

    setNotice(`Template applied: ${template.name}`)

    if (window.matchMedia("(max-width: 900px)").matches) {
      scrollTo(panelRef.current)
    }

    promptRef.current?.focus({ preventScroll: true })
  }

  const openSaveTemplate = (previewUrl?: string, promptText?: string) => {
    const builtIn = templates.find((item) => item.id === selectedTemplateId)

    setSaveName("")

    setSavePrompt(promptText ?? prompt)

    setSavePreview(previewUrl ?? builtIn?.preview ?? "")

    setSaveError("")

    setLightbox(null)

    setSaveOpen(true)
  }

  const submitSaveTemplate = () => {
    const name = saveName.trim()

    if (!name) {
      setSaveError("Give your template a name.")

      return
    }

    if (!savePrompt.trim()) {
      setSaveError("A template needs a prompt.")

      return
    }

    if (!selectedModel) {
      setSaveError("Choose an image model first.")

      return
    }

    const entry: SavedTemplate = {
      id: `tpl-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 6)}`,

      name,

      modelId: selectedModel.id,

      modelName: splitModelName(selectedModel).name,

      prompt: savePrompt.trim(),

      size,

      quality,

      count,

      style,

      lighting,

      mood,

      negativePrompt,

      preview: savePreview,

      createdAt: Date.now(),
    }

    const next = [entry, ...savedTemplates]

    if (!persistSavedTemplates(next)) {
      setSaveError(
        "Your browser storage is full or blocked, so the template could not be saved.",
      )

      return
    }

    setSavedTemplates(next)

    setSaveOpen(false)

    setView("mine")

    setNotice(`Template saved: ${name}`)
  }

  const deleteSavedTemplate = (id: string) => {
    const next = savedTemplates.filter((item) => item.id !== id)

    persistSavedTemplates(next)

    setSavedTemplates(next)

    setSelectedTemplateId((current) => (current === id ? "" : current))
  }

  const pickSavePreviewFile = async (file?: File) => {
    if (!file) return

    try {
      setSavePreview(await fileToPreview(file))

      setSaveError("")
    } catch (error) {
      setSaveError(
        error instanceof Error ? error.message : "Could not read image.",
      )
    }
  }

  const chooseReference = (file?: File) => {
    if (!file) return

    if (!["image/png", "image/jpeg", "image/webp"].includes(file.type)) {
      setGenerationError("Reference must be a PNG, JPG or WebP image.")

      return
    }

    if (file.size > 20 * 1024 * 1024) {
      setGenerationError("Reference image must be smaller than 20 MB.")

      return
    }

    setReference(file)

    setGenerationError("")
  }

  const runGeneration = async (params: GenerationParams) => {
    if (!isAuthenticated) {
      onRequireLogin()

      return
    }

    const model = models.find((item) => item.id === params.modelId)

    if (!model) {
      setGenerationError(
        models.length
          ? "Choose an image model before generating."
          : "No image-generation models are available for this account.",
      )

      return
    }

    if (model.locked) {
      onUpgrade?.()

      return
    }

    if (model.availability === "unavailable") {
      setGenerationError("This model is unavailable with your current credits.")

      return
    }

    if (!params.prompt.trim()) {
      setPromptError("Describe the image you want to create.")

      promptRef.current?.focus()

      return
    }

    if (reference && !model.referenceMode) {
      setGenerationError(
        "The selected model does not accept references. Choose a model marked Refs or remove the reference.",
      )

      return
    }

    setIsGenerating(true)

    setPendingCount(params.count)

    setGenerationError("")

    setPromptError("")

    setView("creations")

    window.setTimeout(() => {
      if (window.matchMedia("(max-width: 900px)").matches) {
        scrollTo(canvasRef.current)
      } else {
        canvasRef.current?.scrollTo({ top: 0, behavior: "smooth" })
      }
    }, 50)

    try {
      const result = await api.generateImage({
        model,

        prompt: params.prompt.trim(),

        size: params.size,

        quality: params.quality,

        count: params.count,

        style: params.style,

        lighting: params.lighting,

        mood: params.mood,

        negativePrompt: params.negativePrompt.trim(),

        reference: reference || undefined,
      })

      const createdAt = Date.now()

      const entries: StudioImage[] = result.map((image) => ({
        ...image,

        ...params,

        prompt: params.prompt.trim(),

        modelName: splitModelName(model).name,

        createdAt,

        usedReference: Boolean(reference),
      }))

      updateHistory((current) => [...entries, ...current])
    } catch (error) {
      setGenerationError(
        error instanceof Error
          ? error.message
          : "Image generation failed. Garad returned an unknown error.",
      )
    } finally {
      setIsGenerating(false)

      setPendingCount(0)
    }
  }

  const submit = () =>
    runGeneration({
      modelId: selectedModelId,

      prompt,

      size,

      quality,

      count,

      style,

      lighting,

      mood,

      negativePrompt,
    })

  const reusePrompt = (image: StudioImage) => {
    if (models.some((model) => model.id === image.modelId)) {
      setSelectedModelId(image.modelId)
    }

    setPrompt(image.prompt)

    setSize(image.size)

    setQuality(image.quality)

    setCount(image.count)

    setStyle(image.style)

    setLighting(image.lighting)

    setMood(image.mood)

    setNegativePrompt(image.negativePrompt)

    setSelectedTemplateId("")

    setLightbox(null)

    setPromptError("")

    if (window.matchMedia("(max-width: 900px)").matches) {
      scrollTo(panelRef.current)
    }

    promptRef.current?.focus({ preventScroll: true })
  }

  const regenerate = (image: StudioImage) => {
    reusePrompt(image)

    void runGeneration({
      modelId: models.some((model) => model.id === image.modelId)
        ? image.modelId
        : selectedModelId,

      prompt: image.prompt,

      size: image.size,

      quality: image.quality,

      count: image.count,

      style: image.style,

      lighting: image.lighting,

      mood: image.mood,

      negativePrompt: image.negativePrompt,
    })
  }

  const useAsReference = async (image: StudioImage) => {
    const referenceModel = selectedModel?.referenceMode
      ? selectedModel
      : models.find(
          (model) =>
            model.referenceMode && model.availability !== "unavailable",
        )

    if (!referenceModel) {
      setGenerationError(
        "None of your available models accept reference images.",
      )

      return
    }

    try {
      const response = await fetch(api.imageProxyUrl(image.url), {
        credentials: "include",
      })

      if (!response.ok) throw new Error("Image could not be fetched")

      const blob = await response.blob()

      const extension = blob.type.split("/")[1] || "png"

      const file = new File([blob], `reference.${extension}`, {
        type: blob.type || "image/png",
      })

      setSelectedModelId(referenceModel.id)

      setReference(file)

      setLightbox(null)

      setGenerationError("")

      setNotice("Added as reference. Edit the prompt and generate.")

      reusePromptFields(image)
    } catch {
      setGenerationError(
        "The browser could not load this image as a reference. Download it and upload it from your device instead.",
      )
    }
  }

  const reusePromptFields = (image: StudioImage) => {
    setPrompt(image.prompt)

    setSize(image.size)

    setStyle(image.style)

    setLighting(image.lighting)

    setMood(image.mood)

    if (window.matchMedia("(max-width: 900px)").matches) {
      scrollTo(panelRef.current)
    }
  }

  const removeImage = (id: string) => {
    updateHistory((current) => current.filter((item) => item.id !== id))

    setLightbox((current) => (current === id ? null : current))
  }

  if (tool !== "image") {
    return (
      <section className="creative-placeholder">
        <span className="creative-eyebrow">GARAD CREATIVE STUDIO</span>
        <h1>{toolTitles[tool]}</h1>
        <p>This tool is coming soon.</p>
        <button className="creative-secondary-btn" onClick={onBackToChat}>
          Back to chat
        </button>
      </section>
    )
  }

  const renderCard = (item: {
    id: string

    name: string

    meta: string

    preview: string

    prompt: string

    onUse: () => void

    onDelete?: () => void
  }) => (
    <div
      className={`sx-template-card ${
        selectedTemplateId === item.id ? "selected" : ""
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
        <span className="sx-template-thumb">
          {item.preview ? (
            <img
              src={item.preview}
              alt=""
              onError={(event) => {
                event.currentTarget.style.display = "none"
              }}
            />
          ) : (
            <Ico name="image" size={22} />
          )}
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

  const ratioOf = (value: string) =>
    sizeOptions.find((option) => option.value === value)?.ratio || value

  const renderTemplates = (groups: ImageTemplate["group"][]) =>
    groups.map((group) => {
      const items = templates.filter((template) => template.group === group)

      if (!items.length) return null

      return (
        <section className="sx-template-group" key={group}>
          <header>
            <h3>{group}</h3>
          </header>
          <div className="sx-template-grid">
            {items.map((template) =>
              renderCard({
                id: template.id,

                name: template.name,

                meta: `${ratioOf(template.size)} · ${template.quality}`,

                preview: template.preview,

                prompt: template.prompt,

                onUse: () => applyTemplate(template),
              }),
            )}
          </div>
        </section>
      )
    })

  const placeholders = Array.from({ length: pendingCount }, (_, index) => index)

  const hasCreations = history.length > 0 || isGenerating

  const tip = tips[tipIndex]

  return (
    <section className="studio" aria-label="Image Generator">
      <aside className="sx-panel" ref={panelRef}>
        <div className="sx-tabs" role="tablist" aria-label="Creative tools">
          {(["image", "video", "audio"] as CreativeTool[]).map((item) => (
            <button
              key={item}
              role="tab"
              aria-selected={item === "image"}
              className={item === "image" ? "active" : ""}
              onClick={() => item !== "image" && onNavigateTool(item)}
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
            <strong>Image Generator</strong>
          </div>
          <button
            className="sx-tool-templates"
            onClick={() => {
              setView("templates")

              scrollTo(canvasRef.current)
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
                    <Ico name="grid" size={13} />
                  </span>
                  <span className="sx-select-text">
                    {selectedModel
                      ? splitModelName(selectedModel).name
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
              aria-label="Generation settings"
              aria-expanded={settingsOpen}
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
                    placeholder="Search models"
                    value={modelSearch}
                    onChange={(event) => setModelSearch(event.target.value)}
                  />
                </div>
                <div className="sx-chip-row">
                  <button
                    className={!refsOnly ? "active" : ""}
                    onClick={() => setRefsOnly(false)}
                    type="button"
                  >
                    All models
                  </button>
                  <button
                    className={refsOnly ? "active" : ""}
                    onClick={() => setRefsOnly(true)}
                    type="button"
                  >
                    Supports references
                  </button>
                </div>
                <div className="sx-model-list">
                  {groupedModels.length === 0 && (
                    <p className="sx-empty-line">
                      No models match your search.
                    </p>
                  )}
                  {groupedModels.map(([provider, items]) => (
                    <div key={provider}>
                      <div className="sx-group-title">
                        <span>{provider}</span>
                        <small>
                          {items.length} model{items.length === 1 ? "" : "s"}
                        </small>
                      </div>
                      {items.map((model) => {
                        const parts = splitModelName(model)

                        const unavailable = model.availability === "unavailable"

                        return (
                          <button
                            key={model.id}
                            role="option"
                            aria-selected={model.id === selectedModelId}
                            className={`sx-model-option ${
                              model.id === selectedModelId ? "selected" : ""
                            } ${model.locked ? "model-locked" : ""}`}
                            disabled={unavailable && !model.locked}
                            onClick={() => {
                              if (model.locked) {
                                setModelOpen(false)

                                onUpgrade?.()

                                return
                              }

                              setSelectedModelId(model.id)

                              setModelOpen(false)

                              setGenerationError("")
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
                              {model.referenceMode && <b>Refs</b>}
                              {model.availability === "beta" && <b>Beta</b>}
                              {unavailable && !model.locked && (
                                <b className="warn">No credits</b>
                              )}
                              {model.id === selectedModelId && (
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

          {settingsOpen && (
            <div className="sx-settings">
              <label>
                <span>Quality</span>
                <select
                  value={quality}
                  onChange={(event) => setQuality(event.target.value)}
                >
                  {qualityOptions.map(([value, label]) => (
                    <option value={value} key={value}>
                      {label}
                    </option>
                  ))}
                </select>
              </label>
              <label>
                <span>Art style</span>
                <select
                  value={style}
                  onChange={(event) => setStyle(event.target.value)}
                >
                  {styleOptions.map(([value, label]) => (
                    <option value={value} key={value || "none"}>
                      {label}
                    </option>
                  ))}
                </select>
              </label>
              <label>
                <span>Lighting</span>
                <select
                  value={lighting}
                  onChange={(event) => setLighting(event.target.value)}
                >
                  {lightingOptions.map(([value, label]) => (
                    <option value={value} key={value || "none"}>
                      {label}
                    </option>
                  ))}
                </select>
              </label>
              <label>
                <span>Mood</span>
                <select
                  value={mood}
                  onChange={(event) => setMood(event.target.value)}
                >
                  {moodOptions.map(([value, label]) => (
                    <option value={value} key={value || "none"}>
                      {label}
                    </option>
                  ))}
                </select>
              </label>
              <label className="wide">
                <span>Negative prompt</span>
                <textarea
                  rows={2}
                  value={negativePrompt}
                  onChange={(event) => setNegativePrompt(event.target.value)}
                  placeholder="What should the image avoid?"
                />
              </label>
            </div>
          )}
        </div>

        <div className="sx-section">
          <div className="sx-label-row">
            <label className="sx-label">References</label>
            <span>{reference ? 1 : 0}/1</span>
          </div>
          <div className="sx-ref-row">
            {reference ? (
              <div className="sx-ref-tile filled">
                <img src={referencePreview} alt="Selected reference" />
                <button
                  aria-label="Remove reference image"
                  className="sx-ref-remove"
                  onClick={() => setReference(null)}
                  type="button"
                >
                  <Ico name="x" size={12} />
                </button>
              </div>
            ) : (
              <button
                className={`sx-ref-tile add ${dragging ? "dragging" : ""}`}
                disabled={!canUseReference}
                onClick={() => fileInputRef.current?.click()}
                onDragOver={(event) => {
                  event.preventDefault()

                  if (canUseReference) setDragging(true)
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={(event) => {
                  event.preventDefault()

                  setDragging(false)

                  if (canUseReference)
                    chooseReference(event.dataTransfer.files[0])
                }}
                type="button"
              >
                <Ico name={canUseReference ? "plus" : "lock"} size={17} />
                <span>{canUseReference ? "Add" : "N/A"}</span>
              </button>
            )}
            <p className="sx-ref-help">
              {reference
                ? `${reference.name} · ${(reference.size / 1024 / 1024).toFixed(1)} MB`
                : canUseReference
                  ? "Drop or add one PNG, JPG or WebP to guide the image."
                  : "The selected model does not accept references."}
            </p>
          </div>
          {reference && !canUseReference && (
            <p className="sx-inline-error" role="alert">
              <Ico name="alert" size={13} />
              <span>
                This model can’t use references. Pick a model marked Refs or
                remove the image.
              </span>
            </p>
          )}
          <input
            ref={fileInputRef}
            className="creative-file-input"
            type="file"
            accept="image/png,image/jpeg,image/webp"
            onChange={(event) => {
              chooseReference(event.target.files?.[0])

              event.target.value = ""
            }}
          />
        </div>

        <div className="sx-section sx-grow">
          <div className="sx-label-row">
            <label className="sx-label" htmlFor="sx-prompt">
              Prompt
            </label>
            {prompt && (
              <button
                className="sx-link"
                onClick={() => {
                  setPrompt("")

                  setSelectedTemplateId("")
                }}
                type="button"
              >
                Clear
              </button>
            )}
          </div>
          <div className={`sx-prompt ${promptError ? "invalid" : ""}`}>
            <textarea
              id="sx-prompt"
              ref={promptRef}
              rows={6}
              value={prompt}
              aria-invalid={Boolean(promptError)}
              maxLength={5000}
              placeholder="Describe your image, or choose a template to start…"
              onChange={(event) => {
                setPrompt(event.target.value)

                setPromptError("")

                setSelectedTemplateId("")
              }}
              onKeyDown={(event) => {
                if ((event.ctrlKey || event.metaKey) && event.key === "Enter") {
                  event.preventDefault()

                  if (!isGenerating) void submit()
                }
              }}
            />
            <small>{prompt.length.toLocaleString()} / 5,000</small>
          </div>
          {promptError && (
            <p className="sx-inline-error" role="alert">
              <Ico name="alert" size={13} />
              <span>{promptError}</span>
            </p>
          )}
        </div>

        <div className="sx-controls-row">
          <div className="sx-stepper" aria-label="Number of images">
            <button
              aria-label="Fewer images"
              disabled={count <= 1}
              onClick={() => setCount((value) => Math.max(1, value - 1))}
              type="button"
            >
              <Ico name="minus" size={12} />
            </button>
            <span>{count}</span>
            <button
              aria-label="More images"
              disabled={count >= 4}
              onClick={() => setCount((value) => Math.min(4, value + 1))}
              type="button"
            >
              <Ico name="plus" size={12} />
            </button>
          </div>
          <div className="sx-ratio" ref={ratioBoxRef}>
            <button
              className="sx-ratio-btn"
              aria-haspopup="listbox"
              aria-expanded={ratioOpen}
              onClick={() => setRatioOpen((open) => !open)}
              type="button"
            >
              <i
                className={`sx-ratio-icon r-${
                  sizeOptions
                    .find((item) => item.value === size)
                    ?.ratio.replace(
                      ":",

                      "-",
                    ) || "1-1"
                }`}
              />
              {sizeOptions.find((item) => item.value === size)?.ratio || "1:1"}
            </button>
            {ratioOpen && (
              <div className="sx-popover sx-ratio-popover" role="listbox">
                {sizeOptions.map((option) => (
                  <button
                    key={option.value}
                    role="option"
                    aria-selected={option.value === size}
                    className={option.value === size ? "selected" : ""}
                    onClick={() => {
                      setSize(option.value)

                      setRatioOpen(false)
                    }}
                    type="button"
                  >
                    <i
                      className={`sx-ratio-icon r-${option.ratio.replace(":", "-")}`}
                    />
                    <span>{option.label}</span>
                    <small>{option.ratio}</small>
                  </button>
                ))}
              </div>
            )}
          </div>
        </div>

        <button
          className="sx-generate"
          disabled={isGenerating || modelsLoading || models.length === 0}
          onClick={() => void submit()}
          type="button"
        >
          {isGenerating ? (
            <>
              <i className="sx-spinner" /> Generating… {elapsed}s
            </>
          ) : (
            <>
              <span className="sx-generate-label">
                <Ico name="sparkle" size={15} /> Generate
              </span>
              <small>
                {count} {count === 1 ? "image" : "images"}
              </small>
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
              <Ico name="image" size={14} /> Creations
              {history.length > 0 && <b>{history.length}</b>}
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
              <Ico name="reference" size={14} /> My templates
              {savedTemplates.length > 0 && <b>{savedTemplates.length}</b>}
            </button>
          </div>
          <div className="sx-canvas-actions">
            <button
              className="sx-pill"
              onClick={() => openSaveTemplate()}
              type="button"
            >
              <Ico name="plus" size={14} /> Save template
            </button>
            <button
              className="sx-pill"
              onClick={() => setCompact((value) => !value)}
              type="button"
            >
              <Ico name="grid" size={14} /> Layout
            </button>
            {history.length > 0 && (
              <button
                className="sx-pill"
                onClick={() => {
                  if (
                    window.confirm("Remove all creations from this history?")
                  ) {
                    updateHistory(() => [])
                  }
                }}
                type="button"
              >
                <Ico name="trash" size={14} /> Clear
              </button>
            )}
          </div>
        </header>

        <div className="sx-canvas-body">
          {generationError && (
            <div className="sx-banner" role="alert">
              <Ico name="alert" size={16} />
              <div>
                <strong>Couldn’t complete that request</strong>
                <span>{generationError}</span>
              </div>
              <button
                className="sx-pill"
                onClick={() => void submit()}
                disabled={isGenerating}
                type="button"
              >
                Try again
              </button>
              <button
                className="sx-banner-close"
                aria-label="Dismiss"
                onClick={() => setGenerationError("")}
                type="button"
              >
                <Ico name="x" size={14} />
              </button>
            </div>
          )}

          {view === "creations" && (
            <>
              {hasCreations ? (
                <section className="sx-creations">
                  <header className="sx-section-head">
                    <h2>Your creations</h2>
                    <span>
                      {isGenerating
                        ? "Generating with Garad…"
                        : `${history.length} saved`}
                    </span>
                  </header>
                  <div className={`sx-gallery ${compact ? "compact" : ""}`}>
                    {placeholders.map((index) => (
                      <div
                        className={`sx-card pending r-${
                          sizeOptions

                            .find((item) => item.value === size)

                            ?.ratio.replace(":", "-") || "1-1"
                        }`}
                        key={`pending-${index}`}
                        role="status"
                        aria-label="Generating image"
                      >
                        <div className="sx-shimmer" />
                        <div className="sx-pending-copy">
                          <i className="sx-spinner" />
                          <strong>Creating your image</strong>
                          <small>
                            {elapsed}s · This can take up to a minute
                          </small>
                          <span className="sx-progress">
                            <i />
                          </span>
                        </div>
                      </div>
                    ))}
                    {history.map((image) => (
                      <article
                        className={`sx-card r-${
                          sizeOptions

                            .find((item) => item.value === image.size)

                            ?.ratio.replace(":", "-") || "1-1"
                        }`}
                        key={image.id}
                      >
                        <button
                          className="sx-card-image"
                          onClick={() => setLightbox(image.id)}
                          aria-label={`Preview: ${image.prompt}`}
                          type="button"
                        >
                          <img
                            src={image.url}
                            alt={image.prompt}
                            loading="lazy"
                          />
                        </button>
                        <div className="sx-card-overlay">
                          <p title={image.prompt}>{image.prompt}</p>
                          <div className="sx-card-actions">
                            <button
                              aria-label="Open image"
                              title="Open"
                              onClick={() =>
                                window.open(
                                  image.url,
                                  "_blank",
                                  "noopener,noreferrer",
                                )
                              }
                              type="button"
                            >
                              <Ico name="external" size={14} />
                            </button>
                            <button
                              aria-label="Download image"
                              title="Download"
                              onClick={() => downloadImage(api, image)}
                              type="button"
                            >
                              <Ico name="download" size={14} />
                            </button>
                            <button
                              aria-label="Regenerate"
                              title="Regenerate"
                              disabled={isGenerating}
                              onClick={() => regenerate(image)}
                              type="button"
                            >
                              <Ico name="refresh" size={14} />
                            </button>
                            <button
                              aria-label="Delete image"
                              title="Remove from history"
                              onClick={() => removeImage(image.id)}
                              type="button"
                            >
                              <Ico name="trash" size={14} />
                            </button>
                          </div>
                        </div>
                      </article>
                    ))}
                  </div>
                </section>
              ) : (
                <section className="sx-hero">
                  <span className="sx-hero-icon">
                    <Ico name="image" size={18} />
                  </span>
                  <h1>Image Generator</h1>
                  <p>
                    Create AI images from a text prompt or an image reference
                  </p>
                  <div className="sx-tip-wrap">
                    <article className="sx-tip">
                      <div className="sx-tip-visual">
                        <span>
                          <Ico name={tip.icon} size={26} />
                        </span>
                        <div className="sx-tip-lines">
                          <i />
                          <i />
                          <i />
                        </div>
                      </div>
                      <div className="sx-tip-copy">
                        <strong>{tip.title}</strong>
                        <p>{tip.body}</p>
                      </div>
                    </article>
                    <div className="sx-tip-nav">
                      <button
                        aria-label="Previous tip"
                        onClick={() =>
                          setTipIndex(
                            (value) => (value + tips.length - 1) % tips.length,
                          )
                        }
                        type="button"
                      >
                        <Ico name="chevronLeft" size={14} />
                      </button>
                      <span>
                        {tipIndex + 1} / {tips.length}
                      </span>
                      <button
                        aria-label="Next tip"
                        onClick={() =>
                          setTipIndex((value) => (value + 1) % tips.length)
                        }
                        type="button"
                      >
                        <Ico name="chevronRight" size={14} />
                      </button>
                    </div>
                  </div>
                </section>
              )}

              <section className="sx-start">
                <header className="sx-section-head stack">
                  <h2>Start creating</h2>
                  <span>
                    Pick a template to fill in the prompt and settings, then
                    generate with your Garad models.
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
                <button
                  className="sx-primary"
                  onClick={() => openSaveTemplate()}
                  type="button"
                >
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
              {savedTemplates.length === 0 ? (
                <div className="sx-empty-state">
                  <span>
                    <Ico name="templates" size={22} />
                  </span>
                  <h3>Build your first template</h3>
                  <p>
                    No saved templates yet. Create a template from your favorite
                    settings and it will appear here.
                  </p>
                  <button
                    className="sx-primary"
                    onClick={() => openSaveTemplate()}
                    type="button"
                  >
                    <Ico name="plus" size={15} /> Create your first template
                  </button>
                </div>
              ) : (
                <div className="sx-template-grid">
                  {savedTemplates.map((template) =>
                    renderCard({
                      id: template.id,

                      name: template.name,

                      meta: `${template.modelName} · ${ratioOf(template.size)}`,

                      preview: template.preview,

                      prompt: template.prompt,

                      onUse: () => applyTemplate(template),

                      onDelete: () => deleteSavedTemplate(template.id),
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
                  <div className="sx-modal-static">
                    <Ico name="grid" size={13} />
                    {selectedModel
                      ? splitModelName(selectedModel).name
                      : "No model selected"}
                  </div>
                </div>
                <div className="sx-section">
                  <span className="sx-label">Prompt</span>
                  <textarea
                    className="sx-modal-input sx-modal-textarea"
                    value={savePrompt}
                    onChange={(event) => setSavePrompt(event.target.value)}
                    placeholder="Describe the image this template creates"
                  />
                </div>
                <div className="sx-modal-chips">
                  <span>{ratioOf(size)}</span>
                  <span>Quality: {quality}</span>
                  <span>
                    {count} image{count === 1 ? "" : "s"}
                  </span>
                  {style && <span>{style.replace("_", " ")}</span>}
                  {lighting && <span>{lighting.replace("_", " ")}</span>}
                  {mood && <span>{mood}</span>}
                </div>
              </div>
              <div className="sx-modal-col">
                <div className="sx-section">
                  <label className="sx-label" htmlFor="sx-tpl-name">
                    Template name
                  </label>
                  <input
                    id="sx-tpl-name"
                    className="sx-modal-input"
                    autoFocus
                    maxLength={60}
                    value={saveName}
                    onChange={(event) => {
                      setSaveName(event.target.value)

                      setSaveError("")
                    }}
                    onKeyDown={(event) => {
                      if (event.key === "Enter") submitSaveTemplate()
                    }}
                    placeholder="e.g. Cinematic product shot"
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
                      void pickSavePreviewFile(event.target.files?.[0])

                      event.target.value = ""
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
              <button
                className="sx-primary"
                onClick={submitSaveTemplate}
                type="button"
              >
                Save template
              </button>
            </footer>
          </div>
        </div>
      )}

      {lightboxImage && (
        <div
          className="sx-lightbox"
          role="dialog"
          aria-modal="true"
          aria-label="Image preview"
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
              <img src={lightboxImage.url} alt={lightboxImage.prompt} />
            </div>
            <aside className="sx-lightbox-side">
              <span className="sx-label">Prompt</span>
              <p>{lightboxImage.prompt}</p>
              <dl>
                <div>
                  <dt>Model</dt>
                  <dd>{lightboxImage.modelName}</dd>
                </div>
                <div>
                  <dt>Format</dt>
                  <dd>
                    {sizeOptions.find(
                      (item) => item.value === lightboxImage.size,
                    )?.ratio || lightboxImage.size}
                  </dd>
                </div>
                <div>
                  <dt>Created</dt>
                  <dd>{formatTime(lightboxImage.createdAt)}</dd>
                </div>
              </dl>
              <div className="sx-lightbox-actions">
                <button
                  className="sx-primary"
                  onClick={() => downloadImage(api, lightboxImage)}
                  type="button"
                >
                  <Ico name="download" size={15} /> Download
                </button>
                <button
                  className="sx-pill"
                  onClick={() =>
                    window.open(
                      lightboxImage.url,
                      "_blank",
                      "noopener,noreferrer",
                    )
                  }
                  type="button"
                >
                  <Ico name="external" size={14} /> Open
                </button>
                <button
                  className="sx-pill"
                  disabled={isGenerating}
                  onClick={() => {
                    setLightbox(null)

                    regenerate(lightboxImage)
                  }}
                  type="button"
                >
                  <Ico name="refresh" size={14} /> Regenerate
                </button>
                <button
                  className="sx-pill"
                  onClick={() => reusePrompt(lightboxImage)}
                  type="button"
                >
                  <Ico name="templates" size={14} /> Reuse prompt
                </button>
                <button
                  className="sx-pill"
                  onClick={() => void useAsReference(lightboxImage)}
                  type="button"
                >
                  <Ico name="reference" size={14} /> Use as reference
                </button>
                <button
                  className="sx-pill"
                  onClick={() =>
                    openSaveTemplate(lightboxImage.url, lightboxImage.prompt)
                  }
                  type="button"
                >
                  <Ico name="plus" size={14} /> Save as template
                </button>
                <button
                  className="sx-pill danger"
                  onClick={() => removeImage(lightboxImage.id)}
                  type="button"
                >
                  <Ico name="trash" size={14} /> Delete
                </button>
              </div>
            </aside>
          </div>
        </div>
      )}

      {notice && (
        <div className="sx-toast" role="status">
          <Ico name="check" size={14} /> {notice}
        </div>
      )}
    </section>
  )
}
