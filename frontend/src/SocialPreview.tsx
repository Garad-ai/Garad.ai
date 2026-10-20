type PreviewProps = {
  platform: string
  label: string
  accountName?: string | null
  picture?: string | null
  content: string
  images: string[]
  video?: string | null
  textLimit?: number
}

const initials = (s: string) => (s.trim()[0] ?? "?").toUpperCase()

// UI-only approximation of how a post looks. It is not a pixel-accurate render of any network.
export default function PostPreview({
  platform,
  label,
  accountName,
  picture,
  content,
  images,
  video,
  textLimit,
}: PreviewProps) {
  const name = accountName || label
  const over = Boolean(textLimit && textLimit > 0 && content.length > textLimit)
  const vertical = platform === "tiktok" || platform === "youtube-shorts"
  const media = (
    <>
      {images[0] && <img className="smx-pv-media" src={images[0]} alt="" />}
      {video && <video className={`smx-pv-media${vertical ? " tall" : ""}`} src={video} controls preload="metadata" />}
    </>
  )
  const avatar = picture ? (
    <img className="smx-pv-avatar" src={picture} alt="" />
  ) : (
    <span className="smx-pv-avatar">{initials(name)}</span>
  )

  return (
    <article className={`smx-pv smx-pv-${platform}`} aria-label={`${label} preview`}>
      <header>
        {avatar}
        <div>
          <b>{name}</b>
          <small>{label} · Just now</small>
        </div>
      </header>
      {platform === "tiktok" || platform === "youtube-shorts" ? (
        <div className="smx-pv-vert">
          {media}
          <p>{content || "Your caption appears here."}</p>
        </div>
      ) : (
        <>
          <p>{content || "Your post text appears here."}</p>
          {media}
        </>
      )}
      <footer className={over ? "over" : ""}>
        {content.length}
        {textLimit && textLimit > 0 ? ` / ${textLimit}` : ""} characters
        {platform === "x" ? " · Reply · Repost · Like" : platform === "linkedin" ? " · Like · Comment · Share" : " · Like · Comment · Share"}
      </footer>
    </article>
  )
}
