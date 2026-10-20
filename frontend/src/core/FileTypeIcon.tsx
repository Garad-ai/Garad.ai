import { useEffect, useState } from "react"

import { fileTypeLabel, resolveFileType, type FileTypeInput } from "./fileTypes"

type Props = FileTypeInput & {
  className: string

  size?: number

  previewUrl?: string | null

  imageFit?: "cover" | "contain"
}

export default function FileTypeIcon({
  className,

  size = 22,

  previewUrl,

  imageFit = "cover",

  ...file
}: Props) {
  const type = resolveFileType(file)

  const [thumbnailFailed, setThumbnailFailed] = useState(false)

  const showThumbnail =
    type === "image" && Boolean(previewUrl) && !thumbnailFailed

  useEffect(() => {
    setThumbnailFailed(false)
  }, [previewUrl])

  return (
    <span
      className={`${className} file-kind-${type}`}
      role="img"
      aria-label={fileTypeLabel(type)}
      style={{
        display: "inline-flex",

        alignItems: "center",

        justifyContent: "center",

        overflow: showThumbnail ? "hidden" : undefined,
      }}
    >
      {showThumbnail ? (
        <img
          src={previewUrl || undefined}
          alt=""
          style={{
            width: "100%",

            height: "100%",

            objectFit: imageFit,

            borderRadius: "inherit",
          }}
          onError={() => setThumbnailFailed(true)}
        />
      ) : (
        <svg
          width={size}
          height={size}
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.7"
          strokeLinecap="round"
          strokeLinejoin="round"
          aria-hidden="true"
        >
          {type === "xlsx" || type === "xls" || type === "csv" ? (
            <>
              <rect x="4" y="3" width="16" height="18" rx="2" />
              <path d="M4 9h16M10 9v12M4 15h16" />
            </>
          ) : type === "pptx" || type === "ppt" ? (
            <>
              <rect x="3" y="4" width="18" height="14" rx="2" />
              <path d="M8 22h8M12 18v4M8 13l3-3 2 2 3-4" />
            </>
          ) : type === "image" ? (
            <>
              <rect x="3" y="3" width="18" height="18" rx="3" />
              <circle cx="9" cy="9" r="2" />
              <path d="m21 15-5-5L5 21" />
            </>
          ) : type === "audio" ? (
            <>
              <path d="M4 10v4M8 6v12M12 3v18M16 7v10M20 10v4" />
            </>
          ) : type === "video" ? (
            <>
              <rect x="3" y="6" width="13" height="12" rx="2" />
              <path d="m16 10 5-3v10l-5-3" />
            </>
          ) : type === "zip" ? (
            <>
              <path d="M3 7h7l2 2h9v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z" />
              <path d="M12 10v2m0 2v2m0 2v1" />
            </>
          ) : type === "pdf" ? (
            <>
              <path d="M6 2.75h8l4 4V21H6Z" />
              <path d="M14 2.75v4.5h4" />
              <text
                x="12"
                y="17.5"
                fill="currentColor"
                stroke="none"
                textAnchor="middle"
                fontSize="5.2"
                fontWeight="700"
                letterSpacing=".15"
              >
                PDF
              </text>
            </>
          ) : (
            <>
              <path d="M6 2h8l4 4v16H6Z" />
              <path d="M14 2v5h5" />
              <path d="M9 13h6M9 17h6" />
            </>
          )}
        </svg>
      )}
    </span>
  )
}
