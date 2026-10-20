import { ApiError, createApiClient } from "./api"
import type { Attachment, FileKind, FileStatus } from "./types"

export type UploadUpdate = Pick<Attachment, "status" | "progress" | "previewUrl" | "error">

interface UploadTask {
  promise: Promise<void>
  cancel: () => void
}

const kindForFile = (file: File): FileKind => {
  if (file.type.startsWith("image/")) return "image"
  if (file.type.startsWith("video/")) return "video"
  if (file.type === "application/pdf") return "pdf"
  if (/sheet|excel|csv/.test(file.type + file.name)) return "spreadsheet"
  if (/presentation|powerpoint/.test(file.type + file.name))
    return "presentation"
  return "document"
}

export const createAttachment = (file: File): Attachment => ({
  id: crypto.randomUUID(),
  name: file.name,
  mimeType: file.type || "application/octet-stream",
  size: file.size,
  kind: kindForFile(file),
  status: "selected",
  progress: 0,
  previewUrl: file.type.startsWith("image/")
    ? URL.createObjectURL(file)
    : undefined,
  createdAt: new Date().toISOString(),
})

export const uploadFile = (
  file: File,
  attachment: Attachment,
  onUpdate: (update: Partial<UploadUpdate>) => void,
): UploadTask => {
  const api = createApiClient()
  if (api.configured) {
    const controller = new AbortController()
    onUpdate({ status: "uploading", progress: 0 })
    const promise = api
      .uploadFile({
        file,
        attachmentId: attachment.id,
        signal: controller.signal,
        onProgress: (progress) => onUpdate({ status: "uploading", progress }),
      })
      .then((payload) => {
        onUpdate({ status: "uploaded", progress: 100 })
        onUpdate({
          status: payload.status as FileStatus | undefined || "processing",
          previewUrl: payload.previewUrl || attachment.previewUrl,
          error: payload.error,
        })
      })
      .catch((error: unknown) => {
        if (error instanceof ApiError && error.code === "upload_cancelled") {
          onUpdate({ status: "cancelled" })
          return
        }
        onUpdate({
          status: "failed",
          error: error instanceof Error ? error.message : "Upload failed",
        })
      })
    return { promise, cancel: () => controller.abort() }
  }

  const reader = new FileReader()
  const promise = new Promise<void>((resolve) => {
    onUpdate({ status: "uploading", progress: 1 })
    reader.addEventListener("progress", (event) => {
      if (event.lengthComputable) {
        onUpdate({
          status: "uploading",
          progress: Math.max(1, Math.round((event.loaded / event.total) * 100)),
        })
      }
    })
    reader.addEventListener("load", () => {
      onUpdate({ status: "uploaded", progress: 100 })
      queueMicrotask(() => {
        onUpdate({ status: "processing" })
        queueMicrotask(() => {
          onUpdate({ status: "ready" })
          resolve()
        })
      })
    })
    reader.addEventListener("error", () => {
      onUpdate({ status: "failed", error: "The file could not be read." })
      resolve()
    })
    reader.addEventListener("abort", () => {
      onUpdate({ status: "cancelled" })
      resolve()
    })
    reader.readAsArrayBuffer(file)
  })
  return { promise, cancel: () => reader.abort() }
}
