import {
  Children,
  cloneElement,
  isValidElement,
  memo,
  useEffect,
  useMemo,
  useState,
  type ComponentProps,
  type ReactNode,
} from "react"

import ReactMarkdown from "react-markdown"

import remarkGfm from "remark-gfm"

import { normalizeAssistantResponse } from "./normalizeResponse"

import type { EntityHighlight } from "./types"

type MarkdownLinkProps = Pick<ComponentProps<"a">, "href" | "children">

type MarkdownImageProps = Pick<ComponentProps<"img">, "src" | "alt">

function safeExternalUrl(value: string | undefined): string | null {
  if (!value?.trim()) return null

  try {
    const url = new URL(value, window.location.href)
    return url.protocol === "http:" || url.protocol === "https:"
      ? url.href
      : null
  } catch {
    return null
  }
}

function textOf(node: ReactNode): string {
  if (typeof node === "string" || typeof node === "number") return String(node)

  if (Array.isArray(node)) return node.map(textOf).join("")

  if (node && typeof node === "object" && "props" in node)
    return textOf((node as { props: { children?: ReactNode } }).props.children)

  return ""
}

const ARTIFACT_LANGS: Record<string, string> = {
  email: "Email",
  letter: "Letter",
  writing: "Writing",
  draft: "Draft",
  script: "Script",
  speech: "Speech",
  post: "Post",
  message: "Message",
}

function ArtifactCard({
  kind,
  text,
}: {
  kind: string
  text: string
}) {
  const [copied, setCopied] = useState(false)
  const [expanded, setExpanded] = useState(false)

  const lines = text.split("\n")
  const subjectIndex = lines.findIndex((line) => /^subject\s*:/i.test(line.trim()))
  const subject =
    subjectIndex >= 0 ? lines[subjectIndex].replace(/^\s*subject\s*:\s*/i, "") : ""
  const recipient = lines
    .slice(0, 4)
    .find((line) => /^(to|recipients?)\s*:/i.test(line.trim()))
    ?.replace(/^\s*(to|recipients?)\s*:\s*/i, "")
  const title = subject || lines.find((line) => line.trim())?.replace(/^#+\s*/, "") || kind
  const body = (subject || recipient
    ? lines.filter((line, index) => {
        if (index > 5) return true
        return !/^\s*(to|recipients?|subject)\s*:/i.test(line)
      })
    : lines
  )
    .join("\n")
    .replace(/^\s+/, "")

  const copy = () => {
    void navigator.clipboard.writeText(text)
    setCopied(true)
    window.setTimeout(() => setCopied(false), 1600)
  }

  return (
    <section className={`artifact-card${expanded ? " expanded" : ""}`}>
      <header className="artifact-head">
        <span className="artifact-kind">{kind}</span>
        <strong title={title}>{title.length > 70 ? `${title.slice(0, 70)}…` : title}</strong>
        <button type="button" onClick={copy} aria-label={`Copy ${kind.toLowerCase()}`}>
          {copied ? "Copied ✓" : "Copy"}
        </button>
        <button
          type="button"
          onClick={() => setExpanded((value) => !value)}
          aria-label={expanded ? "Collapse" : "Expand"}
          aria-expanded={expanded}
        >
          {expanded ? "Collapse" : "Expand"}
        </button>
      </header>
      {recipient && (
        <div className="artifact-meta">
          <span>To</span>
          <em>{recipient}</em>
        </div>
      )}
      {subject && (
        <div className="artifact-meta">
          <span>Subject</span>
          <em>{subject}</em>
        </div>
      )}
      <div className="artifact-body">{body}</div>
    </section>
  )
}

function CodeBlock({ children }: { children?: ReactNode }) {
  const [copied, setCopied] = useState(false)

  const code = textOf(children).replace(/\n$/, "")

  const child = Array.isArray(children) ? children[0] : children

  const className =
    child && typeof child === "object" && "props" in child
      ? String(
          (child as { props: { className?: string } }).props.className || "",
        )
      : ""

  const language = /language-([\w+-]+)/.exec(className)?.[1] || "code"

  if (ARTIFACT_LANGS[language.toLowerCase()])
    return <ArtifactCard kind={ARTIFACT_LANGS[language.toLowerCase()]} text={code} />

  return (
    <div className="md-code">
      <div className="md-code-head">
        <span>{language}</span>
        <button
          type="button"
          onClick={() => {
            void navigator.clipboard.writeText(code)

            setCopied(true)

            window.setTimeout(() => setCopied(false), 1400)
          }}
        >
          {copied ? "Copied" : "Copy"}
        </button>
      </div>
      <pre>{children}</pre>
    </div>
  )
}

const components = {
  pre: ({ children }: { children?: ReactNode }) => (
    <CodeBlock>{children}</CodeBlock>
  ),

  a: ({ href, children }: MarkdownLinkProps) => {
    const safeHref = safeExternalUrl(href)

    return safeHref ? (
      <a href={safeHref} target="_blank" rel="noopener noreferrer">
        {children}
      </a>
    ) : (
      <span>{children}</span>
    )
  },

  table: ({ children }: { children?: ReactNode }) => (
    <div className="md-table">
      <table>{children}</table>
    </div>
  ),

  img: ({ src, alt }: MarkdownImageProps) => (
    <MarkdownImage src={src} alt={alt || "Image attachment"} />
  ),
}

function escapeRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")
}

function highlightText(
  value: string,

  entities: EntityHighlight[],

  occurrences: Map<number, number>,

  onEntityClick: (entity: EntityHighlight) => void,
): ReactNode {
  const matches: Array<{
    start: number

    end: number

    entityIndex: number

    occurrence: number
  }> = []

  entities.forEach((entity, entityIndex) => {
    if (!entity.text) return

    const matcher = new RegExp(escapeRegExp(entity.text), "giu")

    let match: RegExpExecArray | null

    while ((match = matcher.exec(value)) !== null) {
      const occurrence = occurrences.get(entityIndex) || 0

      occurrences.set(entityIndex, occurrence + 1)

      if (occurrence === entity.occurrence_index) {
        matches.push({
          start: match.index,

          end: match.index + match[0].length,

          entityIndex,

          occurrence,
        })
      }
    }
  })

  matches.sort(
    (left, right) =>
      left.start - right.start ||
      right.end - right.start - (left.end - left.start) ||
      left.entityIndex - right.entityIndex,
  )

  const parts: ReactNode[] = []

  let cursor = 0

  for (const match of matches) {
    if (match.start < cursor) continue

    if (match.start > cursor) parts.push(value.slice(cursor, match.start))

    const entity = entities[match.entityIndex]

    parts.push(
      <button
        className="entity-inline-highlight"
        type="button"
        key={`${match.entityIndex}-${match.occurrence}`}
        aria-label={`Show AI-generated context for ${entity.text}`}
        title={`Show AI-generated context for ${entity.text}`}
        onClick={() => onEntityClick(entity)}
      >
        {value.slice(match.start, match.end)}
      </button>,
    )

    cursor = match.end
  }

  if (cursor < value.length) parts.push(value.slice(cursor))

  return parts.length > 0 ? parts : value
}

function countOccurrences(
  children: ReactNode,

  entities: EntityHighlight[],

  occurrences: Map<number, number>,
): void {
  Children.forEach(children, (child) => {
    if (typeof child === "string") {
      entities.forEach((entity, entityIndex) => {
        if (!entity.text) return

        const matcher = new RegExp(escapeRegExp(entity.text), "giu")

        let count = 0

        while (matcher.exec(child) !== null) count++

        if (count > 0) {
          occurrences.set(
            entityIndex,

            (occurrences.get(entityIndex) || 0) + count,
          )
        }
      })

      return
    }

    if (isValidElement<{ children?: ReactNode }>(child)) {
      countOccurrences(child.props.children, entities, occurrences)
    }
  })
}

function highlightChildren(
  children: ReactNode,

  entities: EntityHighlight[],

  occurrences: Map<number, number>,

  onEntityClick: (entity: EntityHighlight) => void,
): ReactNode {
  return Children.map(children, (child) => {
    if (typeof child === "string") {
      return highlightText(child, entities, occurrences, onEntityClick)
    }

    if (!isValidElement<{ children?: ReactNode }>(child)) return child

    const elementName = typeof child.type === "string" ? child.type : ""

    if (["a", "button", "code", "pre"].includes(elementName)) {
      countOccurrences(child.props.children, entities, occurrences)

      return child
    }

    return cloneElement(
      child,

      {},

      highlightChildren(
        child.props.children,
        entities,
        occurrences,
        onEntityClick,
      ),
    )
  })
}

function MarkdownImage({ src, alt }: MarkdownImageProps) {
  const [failed, setFailed] = useState(false)

  const safeSrc = useMemo(() => {
    if (!src?.trim()) return null

    try {
      const url = new URL(src, window.location.href)

      return url.protocol === "http:" || url.protocol === "https:"
        ? url.href
        : null
    } catch {
      return null
    }
  }, [src])

  useEffect(() => setFailed(false), [safeSrc])

  if (!safeSrc || failed)
    return (
      <span className="md-image-unavailable" role="img" aria-label={alt}>
        {alt} unavailable
      </span>
    )

  return (
    <img
      className="md-image-preview"
      src={safeSrc}
      alt={alt}
      loading="lazy"
      onError={() => setFailed(true)}
    />
  )
}

function AssistantMarkdown({
  content,

  streaming,

  entities = [],

  onEntityClick,
}: {
  content: string

  streaming?: boolean

  entities?: EntityHighlight[]

  onEntityClick?: (entity: EntityHighlight) => void
}) {
  const clean = useMemo(
    () => normalizeAssistantResponse(content, { streaming }),

    [content, streaming],
  )

  const markdownComponents = useMemo(() => {
    if (!onEntityClick || entities.length === 0) return components

    const occurrences = new Map<number, number>()

    const decorate = (children: ReactNode) =>
      highlightChildren(children, entities, occurrences, onEntityClick)

    return {
      ...components,

      p: ({ children, ...props }: { children?: ReactNode }) => (
        <p {...props}>{decorate(children)}</p>
      ),

      h1: ({ children, ...props }: { children?: ReactNode }) => (
        <h1 {...props}>{decorate(children)}</h1>
      ),

      h2: ({ children, ...props }: { children?: ReactNode }) => (
        <h2 {...props}>{decorate(children)}</h2>
      ),

      h3: ({ children, ...props }: { children?: ReactNode }) => (
        <h3 {...props}>{decorate(children)}</h3>
      ),

      h4: ({ children, ...props }: { children?: ReactNode }) => (
        <h4 {...props}>{decorate(children)}</h4>
      ),

      h5: ({ children, ...props }: { children?: ReactNode }) => (
        <h5 {...props}>{decorate(children)}</h5>
      ),

      h6: ({ children, ...props }: { children?: ReactNode }) => (
        <h6 {...props}>{decorate(children)}</h6>
      ),

      li: ({ children, ...props }: { children?: ReactNode }) => {
        const hasParagraph = Children.toArray(children).some(
          (child) => isValidElement(child) && child.type === "p",
        )

        return (
          <li {...props}>{hasParagraph ? children : decorate(children)}</li>
        )
      },

      td: ({ children, ...props }: { children?: ReactNode }) => (
        <td {...props}>{decorate(children)}</td>
      ),

      th: ({ children, ...props }: { children?: ReactNode }) => (
        <th {...props}>{decorate(children)}</th>
      ),
    }
  }, [entities, onEntityClick])

  if (!clean) return null

  return (
    <div className="md-body">
      <ReactMarkdown
        remarkPlugins={[remarkGfm]}
        components={markdownComponents}
      >
        {clean}
      </ReactMarkdown>
    </div>
  )
}

export default memo(AssistantMarkdown)
