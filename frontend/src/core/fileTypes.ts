export type CanonicalFileType =
  | "pdf"
  | "doc"
  | "docx"
  | "xls"
  | "xlsx"
  | "ppt"
  | "pptx"
  | "csv"
  | "txt"
  | "md"
  | "image"
  | "audio"
  | "video"
  | "zip"
  | "generic"

export type FileTypeInput = {
  mimeType?: string | null
  extension?: string | null
  kind?: string | null
  type?: string | null
  filename?: string | null
}

const MIME_TYPES: Record<string, CanonicalFileType> = {
  "application/pdf": "pdf",
  "application/msword": "doc",
  "application/vnd.openxmlformats-officedocument.wordprocessingml.document":
    "docx",
  "application/vnd.ms-excel": "xls",
  "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet": "xlsx",
  "text/csv": "csv",
  "application/csv": "csv",
  "application/vnd.ms-powerpoint": "ppt",
  "application/vnd.openxmlformats-officedocument.presentationml.presentation":
    "pptx",
  "text/plain": "txt",
  "text/markdown": "md",
  "application/zip": "zip",
  "application/x-zip-compressed": "zip",
  "application/vnd.rar": "zip",
  "application/rar": "zip",
  "application/x-rar-compressed": "zip",
  "application/x-7z-compressed": "zip",
}

const EXTENSION_TYPES: Record<string, CanonicalFileType> = {
  pdf: "pdf",
  doc: "doc",
  docx: "docx",
  xls: "xls",
  xlsx: "xlsx",
  ppt: "ppt",
  pptx: "pptx",
  csv: "csv",
  txt: "txt",
  md: "md",
  markdown: "md",
  png: "image",
  jpg: "image",
  jpeg: "image",
  webp: "image",
  gif: "image",
  bmp: "image",
  svg: "image",
  mp3: "audio",
  wav: "audio",
  m4a: "audio",
  aac: "audio",
  ogg: "audio",
  flac: "audio",
  mp4: "video",
  mov: "video",
  webm: "video",
  avi: "video",
  mkv: "video",
  zip: "zip",
  rar: "zip",
  "7z": "zip",
}

const KIND_TYPES: Record<string, CanonicalFileType> = {
  pdf: "pdf",
  doc: "doc",
  docx: "docx",
  word: "doc",
  document: "doc",
  xls: "xls",
  xlsx: "xlsx",
  excel: "xlsx",
  spreadsheet: "xlsx",
  csv: "csv",
  ppt: "ppt",
  pptx: "pptx",
  powerpoint: "pptx",
  presentation: "pptx",
  txt: "txt",
  text: "txt",
  md: "md",
  markdown: "md",
  image: "image",
  audio: "audio",
  video: "video",
  zip: "zip",
  archive: "zip",
}

export function getFileExtension(input: FileTypeInput): string {
  const explicit = input.extension?.trim().replace(/^\./, "").toLowerCase()
  if (explicit) return explicit

  const filename = input.filename?.split(/[?#]/, 1)[0] || ""
  const basename = filename.split(/[\\/]/).pop() || ""
  const dot = basename.lastIndexOf(".")
  return dot > 0 ? basename.slice(dot + 1).toLowerCase() : ""
}

export function resolveFileType(input: FileTypeInput): CanonicalFileType {
  const mimeType = input.mimeType?.split(";", 1)[0]?.trim().toLowerCase() || ""
  if (MIME_TYPES[mimeType]) return MIME_TYPES[mimeType]
  if (mimeType.startsWith("image/")) return "image"
  if (mimeType.startsWith("audio/")) return "audio"
  if (mimeType.startsWith("video/")) return "video"

  const extension = getFileExtension(input)
  if (EXTENSION_TYPES[extension]) return EXTENSION_TYPES[extension]

  const type = (input.type || "").trim().toLowerCase()
  const kind = (input.kind || "").trim().toLowerCase()
  return KIND_TYPES[type] || KIND_TYPES[kind] || "generic"
}

export function fileTypeLabel(type: CanonicalFileType): string {
  switch (type) {
    case "pdf":
      return "PDF file"
    case "doc":
    case "docx":
      return "Word document"
    case "xls":
    case "xlsx":
      return "Excel spreadsheet"
    case "ppt":
    case "pptx":
      return "PowerPoint presentation"
    case "csv":
      return "CSV spreadsheet"
    case "txt":
    case "md":
      return "Text document"
    case "image":
      return "Image attachment"
    case "audio":
      return "Audio file"
    case "video":
      return "Video file"
    case "zip":
      return "Archive file"
    default:
      return "File attachment"
  }
}

export function formatFileSize(size: number): string {
  if (!Number.isFinite(size) || size <= 0) return ""
  if (size < 1024) return `${size} B`

  const units = ["KB", "MB", "GB", "TB"]
  let value = size / 1024
  let unit = 0
  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024
    unit += 1
  }

  return `${value.toFixed(value < 10 ? 1 : 0)} ${units[unit]}`
}
