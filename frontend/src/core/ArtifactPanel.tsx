import { useEffect, useState } from "react"

import "@extend-ai/react-pptx/styles.css"

import AssistantMarkdown from "./AssistantMarkdown"

import { getFileExtension, resolveFileType } from "./fileTypes"

type PptxViewerComponent = typeof import("@extend-ai/react-pptx").ReactPptxViewer

export type PanelArtifact = {
  id?: string

  name: string

  url: string

  mimeType?: string

  extension?: string

  kind?: string

  size?: number

  description?: string

  slideCount?: number | null

  createdAt?: string

  previewUrl?: string | null
}

const OPEN_EVENT = "garad:open-artifact"

export function openArtifactPanel(artifact: PanelArtifact) {
  window.dispatchEvent(new CustomEvent(OPEN_EVENT, { detail: artifact }))
}

const absolute = (url: string) => new URL(url, window.location.href).href

function PresentationPreview({ artifact }: { artifact: PanelArtifact }) {
  const [source, setSource] = useState<ArrayBuffer | null>(null)

  const [Viewer, setViewer] = useState<PptxViewerComponent | null>(null)

  const [error, setError] = useState("")

  useEffect(() => {
    let cancelled = false

    setSource(null)

    setViewer(null)

    setError("")

    void fetch(artifact.url, { credentials: "include" })
      .then(async (response) => {
        if (!response.ok)
          throw new Error(`Presentation preview failed (${response.status})`)

        const file = await response.arrayBuffer()
        const viewer = await import("@extend-ai/react-pptx")

        if (!cancelled) {
          setSource(file)
          setViewer(() => viewer.ReactPptxViewer)
        }
      })
      .catch((reason: unknown) => {
        if (!cancelled)
          setError(
            reason instanceof Error
              ? reason.message
              : "Presentation preview failed",
          )
      })

    return () => {
      cancelled = true
    }
  }, [artifact.url])

  if (error) return <div className="message-error">{error}</div>

  if (!source || !Viewer)
    return <div className="stream-state">Loading slides in Garad AI…</div>

  return (
    <div className="artifact-pptx-viewer">
      <Viewer
        source={source}
        mode="continuous"
        defaultZoom="fit-width"
        showToolbar
        showThumbnails
      />
    </div>
  )
}

function Preview({ artifact }: { artifact: PanelArtifact }) {
  const ext = getFileExtension({
    filename: artifact.name,

    extension: artifact.extension,
  })

  const fileType = resolveFileType({
    filename: artifact.name,

    mimeType: artifact.mimeType,

    extension: artifact.extension,

    kind: artifact.kind,
  })

  const plainText =
    ext === "md" || ext === "txt" || fileType === "md" || fileType === "txt"

  const [text, setText] = useState<string | null>(null)

  const [error, setError] = useState("")

  const iframeExt = ["pdf", "doc", "docx", "xls", "xlsx"].includes(fileType)

  const previewSrc =
    artifact.previewUrl ||
    `${artifact.url}${artifact.url.includes("?") ? "&" : "?"}preview=1`
  const mediaSrc = artifact.previewUrl || artifact.url

  useEffect(() => {
    setText(null)

    setError("")

    if (!plainText) return

    let cancelled = false

    fetch(artifact.url, { credentials: "include" })

      .then((response) => {
        if (!response.ok) throw new Error(`Preview failed (${response.status})`)

        return response.text()
      })

      .then((body) => {
        if (!cancelled) setText(body)
      })

      .catch((reason: unknown) => {
        if (!cancelled)
          setError(reason instanceof Error ? reason.message : "Preview failed")
      })

    return () => {
      cancelled = true
    }
  }, [artifact.url, plainText])

  if (fileType === "image")
    return (
      <div className="artifact-media-preview">
        <img src={mediaSrc} alt={artifact.name} />
      </div>
    )

  if (fileType === "video")
    return (
      <div className="artifact-media-preview">
        <video controls preload="metadata">
          <source src={mediaSrc} type={artifact.mimeType} />
        </video>
      </div>
    )

  if (fileType === "audio")
    return (
      <div className="artifact-media-preview">
        <audio controls preload="metadata">
          <source src={mediaSrc} type={artifact.mimeType} />
        </audio>
      </div>
    )

  if (iframeExt)
    return (
      <iframe
        className="artifact-frame"
        title={artifact.name}
        src={previewSrc}
      />
    )

  if (plainText) {
    if (error) return <div className="message-error">{error}</div>

    if (text === null)
      return <div className="stream-state">Loading preview…</div>

    return ext === "md" || fileType === "md" ? (
      <div className="artifact-text">
        <AssistantMarkdown content={text} />
      </div>
    ) : (
      <pre className="artifact-text artifact-plain">{text}</pre>
    )
  }

  if (ext === "pptx" || ext === "ppt")
    return <PresentationPreview artifact={artifact} />

  return (
    <div className="stream-state">
      No inline preview for this file type. Use Download.
    </div>
  )
}

export default function ArtifactPanelHost({
  onOpenLibrary,

  notify,
}: {
  onOpenLibrary: () => void

  notify: (message: string) => void
}) {
  const [artifact, setArtifact] = useState<PanelArtifact | null>(null)

  useEffect(() => {
    const handler = (event: Event) =>
      setArtifact((event as CustomEvent<PanelArtifact>).detail)

    window.addEventListener(OPEN_EVENT, handler)

    return () => window.removeEventListener(OPEN_EVENT, handler)
  }, [])

  useEffect(() => {
    if (!artifact) return

    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") setArtifact(null)
    }

    window.addEventListener("keydown", onKey)

    return () => window.removeEventListener("keydown", onKey)
  }, [artifact])

  useEffect(() => {
    document.body.classList.toggle("artifact-open", !!artifact)

    document.body.classList.toggle(
      "artifact-presentation-open",

      artifact?.kind === "presentation",
    )

    return () => {
      document.body.classList.remove("artifact-open")

      document.body.classList.remove("artifact-presentation-open")
    }
  }, [artifact])

  if (!artifact) return null

  const fileType = resolveFileType({
    filename: artifact.name,

    mimeType: artifact.mimeType,

    extension: artifact.extension,

    kind: artifact.kind,
  })

  const previewHref =
    artifact.previewUrl ||
    `${artifact.url}${artifact.url.includes("?") ? "&" : "?"}preview=1`

  const canOpenTab = ["pdf", "doc", "docx", "xls", "xlsx"].includes(fileType)

  const share = async () => {
    try {
      await navigator.clipboard.writeText(absolute(artifact.url))

      notify("Link copied (only you can open it while signed in)")
    } catch {
      notify("Could not copy link")
    }
  }

  return (
    <aside
      className={`artifact-panel ${
        fileType === "ppt" || fileType === "pptx" ? "presentation" : ""
      }`}
      role="dialog"
      aria-label={`Preview ${artifact.name}`}
    >
      <header className="artifact-panel-head">
        <div>
          <strong title={artifact.name}>{artifact.name}</strong>
          <small>
            {artifact.description ||
              artifact.extension?.toUpperCase() ||
              fileType.toUpperCase()}
          </small>
        </div>
        <button
          className="icon-btn"
          aria-label="Close preview"
          onClick={() => setArtifact(null)}
        >
          ✕
        </button>
      </header>
      <div className="artifact-panel-body">
        <Preview artifact={artifact} />
      </div>
      <footer className="artifact-panel-actions">
        <a
          className="secondary-btn"
          href={artifact.url}
          download={artifact.name}
        >
          Download
        </a>
        {canOpenTab && (
          <a
            className="secondary-btn"
            href={previewHref}
            target="_blank"
            rel="noopener noreferrer"
          >
            Open in new tab
          </a>
        )}
        <button
          className="secondary-btn"
          onClick={() => {
            onOpenLibrary()

            setArtifact(null)
          }}
        >
          Saved to Library
        </button>
        <button className="secondary-btn" onClick={() => void share()}>
          Share
        </button>
      </footer>
    </aside>
  )
}
