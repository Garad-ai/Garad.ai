import { useEffect, useMemo, useRef, useState } from "react"

import type { ApiClient } from "./api"
import type { Artifact } from "./types"

import { extractToolResults } from "./normalizeResponse"

import { openArtifactPanel } from "./ArtifactPanel"
import FileTypeIcon from "./FileTypeIcon"
import {
  fileTypeLabel,
  formatFileSize,
  getFileExtension,
  resolveFileType,
} from "./fileTypes"

const safeMediaUrl = (value: string): string | null => {
  try {
    const path = value.trim()

    if (!path) return null

    const appBase = new URL(".", window.location.href)
    const url =
      /^https?:\/\//i.test(path) || path.startsWith("//")
        ? new URL(path, window.location.origin)
        : path.startsWith(appBase.pathname)
          ? new URL(path, window.location.origin)
          : new URL(path.replace(/^\/+/, ""), appBase)

    return url.protocol === "http:" || url.protocol === "https:"
      ? url.href
      : null
  } catch {
    return null
  }
}

function FileCard({
  name,
  label,
  url,
  mimeType,
  extension,
  kind,
  size,
}: {
  name: string
  label: string
  url: string
  mimeType?: string
  extension?: string
  kind?: string
  size?: number
}) {
  const safeUrl = safeMediaUrl(url)
  const fileExtension = getFileExtension({ filename: name, extension })
  const metadata = [
    fileExtension.toUpperCase(),
    size ? formatFileSize(size) : "",
  ].filter(Boolean)
  const fileType = resolveFileType({ filename: name, mimeType, extension, kind })

  if (!safeUrl)
    return (
      <div className="message-error">
        <strong>File result unavailable</strong>
        <span>The provider returned an invalid download URL.</span>
      </div>
    )

  return (
    <div className="artifact tool-file-card">
      <FileTypeIcon
        className="artifact-icon violet"
        filename={name}
        mimeType={mimeType}
        extension={extension}
        kind={kind}
      />
      <div className="artifact-info">
        <span className="eyebrow">
          {label || fileTypeLabel(fileType)}{" "}
          {metadata.length ? `· ${metadata.join(" · ")}` : ""} · SAVED TO LIBRARY
        </span>
        <strong>{name}</strong>
      </div>
      <div className="artifact-actions">
        <button
          className="secondary-btn"
          onClick={() =>
            openArtifactPanel({
              name,
              url: safeUrl,
              description: label,
              mimeType,
              extension: fileExtension,
              kind,
            })
          }
        >
          Open
        </button>
        <a className="secondary-btn" href={safeUrl} download={name}>
          Download
        </a>
      </div>
    </div>
  )
}

const POLL_MS = 4000

const POLL_LIMIT_MS = 10 * 60 * 1000

function PresentationJob({
  generationId,

  exportAs,

  api,
}: {
  generationId: string

  exportAs: "pptx" | "pdf"

  api: ApiClient
}) {
  const [result, setResult] = useState<{
    state: "processing" | "ready" | "failed"

    url?: string

    message: string
  }>({ state: "processing", message: "" })

  const started = useRef(Date.now())

  useEffect(() => {
    let cancelled = false

    let timer = 0

    let consecutiveFailures = 0

    const poll = async () => {
      try {
        const next = await api.getPresentationStatus(generationId)

        if (cancelled) return

        consecutiveFailures = 0
        setResult(next)

        if (next.state !== "processing") return
      } catch (error) {
        if (cancelled) return

        consecutiveFailures += 1

        if (consecutiveFailures >= 3) {
          setResult({
            state: "failed",
            message:
              error instanceof Error
                ? error.message
                : "Unable to check the presentation generation status.",
          })

          return
        }
      }

      if (Date.now() - started.current > POLL_LIMIT_MS) {
        setResult({
          state: "failed",

          message: "The presentation is taking longer than expected.",
        })

        return
      }

      timer = window.setTimeout(() => void poll(), POLL_MS)
    }

    void poll()

    return () => {
      cancelled = true

      window.clearTimeout(timer)
    }
  }, [api, generationId])

  if (result.state === "ready")
    return result.url ? (
      <FileCard
        name={`Presentation.${exportAs}`}
        label={exportAs === "pdf" ? "PDF document" : "PowerPoint presentation"}
        url={result.url}
      />
    ) : (
      <div className="message-error">
        <strong>Presentation result unavailable</strong>
        <span>The provider did not return a downloadable file.</span>
      </div>
    )

  if (result.state === "failed")
    return (
      <div className="message-error">
        <strong>Presentation failed</strong>
        <span>{result.message}</span>
      </div>
    )

  return (
    <div className="stream-state" aria-live="polite">
      <span className="typing-dots">
        <i />
        <i />
        <i />
      </span>
      <strong>Generating presentation…</strong>
    </div>
  )
}

function GeneratedImage({ url }: { url: string }) {
  const [failed, setFailed] = useState(false)
  const safeUrl = safeMediaUrl(url)

  useEffect(() => setFailed(false), [safeUrl])

  if (!safeUrl || failed)
    return (
      <div className="generated-image-unavailable" role="status">
        Generated image is unavailable.
      </div>
    )

  return (
    <figure className="generated-image-result">
      <a href={safeUrl} target="_blank" rel="noopener noreferrer">
        <img
          src={safeUrl}
          alt="Image generated for this conversation"
          onError={() => setFailed(true)}
        />
      </a>
      <a className="secondary-btn" href={safeUrl} download>
        Open / Download image
      </a>
    </figure>
  )
}

function VideoJob({
  videoId,

  api,
}: {
  videoId: string

  api: ApiClient
}) {
  const [result, setResult] = useState<{
    state: "processing" | "ready" | "failed"

    url?: string

    message: string
  }>({ state: "processing", message: "" })

  const started = useRef(Date.now())

  useEffect(() => {
    let cancelled = false

    let timer = 0

    const poll = async () => {
      try {
        const next = await api.getVideoStatus(videoId)

        if (cancelled) return

        setResult(next)

        if (next.state !== "processing") return
      } catch (error) {
        if (cancelled) return

        setResult({
          state: "failed",

          message:
            error instanceof Error
              ? error.message
              : "Unable to check the video generation status.",
        })

        return
      }

      if (Date.now() - started.current > POLL_LIMIT_MS) {
        setResult({
          state: "failed",

          message: "Video generation is taking longer than expected.",
        })

        return
      }

      timer = window.setTimeout(() => void poll(), POLL_MS)
    }

    void poll()

    return () => {
      cancelled = true

      window.clearTimeout(timer)
    }
  }, [api, videoId])

  const safeUrl = result.url ? safeMediaUrl(result.url) : null

  if (result.state === "ready" && safeUrl)
    return (
      <div className="generated-video-result">
        <video controls preload="metadata" src={safeUrl}>
          Your browser does not support video playback.
        </video>
        <a className="secondary-btn" href={safeUrl} download>
          Download video
        </a>
      </div>
    )

  if (result.state === "failed")
    return (
      <div className="message-error">
        <strong>Video generation failed</strong>
        <span>{result.message}</span>
      </div>
    )

  return (
    <div className="stream-state" aria-live="polite">
      <span className="typing-dots">
        <i />
        <i />
        <i />
      </span>
      <strong>Generating video…</strong>
    </div>
  )
}

export default function ToolResults({
  content,

  api,

  artifacts,
}: {
  content: string

  api: ApiClient

  artifacts: Artifact[]
}) {
  const { files: extractedFiles, images, presentationJobs, videoJobs } = useMemo(
    () => extractToolResults(content),

    [content],
  )
  const artifactPaths = useMemo(
    () =>
      new Set(
        artifacts
          .map((artifact) => artifact.url)
          .filter((url): url is string => Boolean(url))
          .map((url) => safeMediaUrl(url))
          .filter((url): url is string => Boolean(url))
          .map((url) => new URL(url).pathname),
      ),
    [artifacts],
  )
  const files = extractedFiles.filter((file) => {
    const safeUrl = safeMediaUrl(file.url)
    return !safeUrl || !artifactPaths.has(new URL(safeUrl).pathname)
  })

  if (
    !files.length &&
    !images.length &&
    !presentationJobs.length &&
    !videoJobs.length
  )
    return null

  return (
    <div className="tool-results">
      {files.map((file) => (
        <FileCard {...file} key={file.url} />
      ))}
      {images.map(({ url }) => (
        <GeneratedImage url={url} key={url} />
      ))}
      {presentationJobs.map(({ id, exportAs }) => (
        <PresentationJob
          generationId={id}
          exportAs={exportAs}
          api={api}
          key={id}
        />
      ))}
      {videoJobs.map((id) => (
        <VideoJob videoId={id} api={api} key={id} />
      ))}
    </div>
  )
}
